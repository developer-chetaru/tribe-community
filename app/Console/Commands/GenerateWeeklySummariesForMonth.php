<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\HappyIndex;
use App\Models\WeeklySummary as WeeklySummaryModel;
use App\Services\OneSignalService;
use App\Models\AppSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GenerateWeeklySummariesForMonth extends Command
{
    protected $signature = 'weekly:generate-for-month 
                            {--month= : Month number (1-12)}
                            {--year= : Year (default: current year)}
                            {--user= : Specific user ID (optional)}';

    protected $description = 'Generate weekly summaries for a specific month';

    public function handle()
    {
        $month = $this->option('month') ?: now()->month;
        $year = $this->option('year') ?: now()->year;
        $userId = $this->option('user');

        $this->info("Generating weekly summaries for {$year}-{$month}");

        // Get users
        $query = User::query();
        if ($userId) {
            $query->where('id', $userId);
        }
        $users = $query->get();

        if ($users->isEmpty()) {
            $this->error('No users found.');
            return;
        }

        // Calculate weeks in the month
        $firstDay = Carbon::create($year, $month, 1)->startOfMonth();
        $lastDay = Carbon::create($year, $month, 1)->endOfMonth();
        $weekStart = $firstDay->copy()->startOfWeek(Carbon::MONDAY);
        $weekNum = 1;

        $weeks = [];
        $today = Carbon::now('Asia/Kolkata');
        
        while ($weekStart->lte($lastDay)) {
            $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
            
            // Only include weeks that have ended (week end date must be in the past)
            // A week is considered ended if its end date (Sunday) has passed
            if ($weekEnd->gt($today)) {
                $weekStart->addWeek();
                continue;
            }

            $weeks[] = [
                'number' => $weekNum,
                'start' => $weekStart->copy(),
                'end' => $weekEnd,
                'label' => $weekStart->format('M d') . ' - ' . $weekEnd->format('M d'),
            ];

            $weekNum++;
            $weekStart->addWeek();
        }

        $oneSignal = new OneSignalService();
        $generated = 0;

        foreach ($users as $user) {
            foreach ($weeks as $week) {
                // Double-check: Only generate for completed weeks (week must have ended)
                $today = Carbon::now('Asia/Kolkata');
                if ($week['end']->gt($today)) {
                    $this->line("Skipping user {$user->id}, week {$week['number']} - week not ended yet (ends {$week['end']->format('M d, Y')})");
                    continue;
                }

                // Check if summary already exists and is valid
                $existingSummary = WeeklySummaryModel::where([
                    'user_id' => $user->id,
                    'year' => $year,
                    'month' => $month,
                    'week_number' => $week['number'],
                ])->first();

                // Skip only if summary exists AND is valid (not an error message)
                if ($existingSummary && $this->isValidSummary($existingSummary->summary ?? '')) {
                    $this->line("Skipping user {$user->id}, week {$week['number']} - valid summary already exists");
                    continue;
                }
                
                // If summary exists but is invalid (error message), delete it to regenerate
                if ($existingSummary && !$this->isValidSummary($existingSummary->summary ?? '')) {
                    $this->line("Regenerating invalid summary for user {$user->id}, week {$week['number']}");
                    $existingSummary->delete();
                }

                // Get mood data for this week
                $startUTC = $week['start']->copy()->setTimezone('UTC');
                $endUTC = $week['end']->copy()->setTimezone('UTC');

                $allData = HappyIndex::where('user_id', $user->id)
                    ->whereBetween('created_at', [$startUTC, $endUTC])
                    ->orderBy('created_at')
                    ->get(['mood_value', 'description', 'created_at']);

                if ($allData->isEmpty()) {
                    $this->line("No data for user {$user->id}, week {$week['number']} (period: {$startUTC->format('Y-m-d H:i')} to {$endUTC->format('Y-m-d H:i')} UTC)");
                    continue;
                }
                
                $this->line("Found {$allData->count()} mood entries for user {$user->id}, week {$week['number']}");

                // Build entries
                $entries = $allData->map(function ($item) {
                    $date = $item->created_at->setTimezone('Asia/Kolkata')->format('M d, D');
                    $mood = match ($item->mood_value) {
                        3 => 'Good 😊',
                        2 => 'Okay 😐',
                        1 => 'Bad 🙁',
                        default => 'Unknown',
                    };
                    $desc = $item->description ? " - {$item->description}" : '';
                    return "{$date}: {$mood}{$desc}";
                })->implode("\n");

                // Get prompt from database
                $promptTemplate = AppSetting::getValue('weekly_summary_prompt', 'Generate a professional weekly emotional summary for the user based strictly on the following daily sentiment data from {weekLabel}:

{entries}

Important writing requirements:
- Do NOT start with greetings.
- Do NOT address the user directly.
- Write a polished, insightful summary of emotional trends.
- Provide 3–5 sentences analyzing patterns across the week.
- Tone should be professional, warm, supportive, and not casual.
- Focus only on the user\'s emotional journey.
- Do NOT include organisational-level references.');

                $prompt = str_replace(['{weekLabel}', '{entries}'], [$week['label'], $entries], $promptTemplate);

                // Generate summary with AI first
                $aiSummary = $this->generateAIText($prompt, $user->id);
                $summaryText = $aiSummary;

                if ($aiSummary === 'QUOTA_EXCEEDED' || $aiSummary === 'AI_SERVICE_UNAVAILABLE' || empty(trim((string) $aiSummary))) {
                    // Fallback so weekly summary generation does not fully fail when AI is unavailable.
                    $summaryText = $this->buildFallbackWeeklySummary($allData, $week['label']);
                    Log::warning("Using fallback weekly summary for user {$user->id}, week {$week['number']}", [
                        'ai_result' => $aiSummary,
                        'entries_count' => $allData->count(),
                    ]);
                }

                // Only save summary if it's valid (not an error message)
                if ($this->isValidSummary($summaryText)) {
                    WeeklySummaryModel::create([
                        'user_id' => $user->id,
                        'year' => $year,
                        'month' => $month,
                        'week_number' => $week['number'],
                        'week_label' => $week['label'],
                        'summary' => $summaryText,
                    ]);
                    $generated++;
                    $this->info("Generated summary for user {$user->id}, week {$week['number']}");
                } else {
                    $this->warn("Failed to generate valid summary for user {$user->id}, week {$week['number']} - {$summaryText}");
                }

            }
        }

        $this->info("Generated {$generated} weekly summaries.");
    }
    
    private function isValidSummary(string $text): bool
    {
        $bad = [
            '',
            'Summary could not be generated due to an error.',
            'Error generating summary.',
            'AI_SERVICE_UNAVAILABLE',
            'No summary generated.',
            'Summary unavailable due to AI quota limits.',
            'QUOTA_EXCEEDED',
        ];

        return !in_array(trim($text), $bad, true);
    }

    private function generateAIText(string $prompt, $userId = null): string
    {
        $apiKey = env('OPENAI_API_KEY');
        if (empty($apiKey)) {
            Log::error("OpenAI API key missing (OPENAI_API_KEY).");
            return 'AI_SERVICE_UNAVAILABLE';
        }

        // Model configuration — change via .env if you want different model
        $chatModel = env('OPENAI_CHAT_MODEL', 'gpt-4o-mini');
        $maxTokens = intval(env('OPENAI_MAX_TOKENS', 500));
        $temperature = floatval(env('OPENAI_TEMPERATURE', 0.7));

        $attempts = 0;
        $maxAttempts = 3;
        $backoffSeconds = 1;

        while ($attempts < $maxAttempts) {
            $attempts++;

            try {
                $res = \Illuminate\Support\Facades\Http::withToken($apiKey)
                    ->timeout(30)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => $chatModel,
                        'messages' => [
                            ['role' => 'user', 'content' => $prompt],
                        ],
                        'max_tokens' => $maxTokens,
                        'temperature' => $temperature,
                    ]);

                if ($res->ok()) {
                    $json = $res->json();
                    $text = $json['choices'][0]['message']['content'] ?? null;
                    if (!empty($text)) {
                        return trim((string)$text);
                    }
                    // empty response — warn and try again
                    Log::warning("AI chat returned empty for user {$userId}, attempt {$attempts}");
                } else {
                    $status = $res->status();
                    $body = $res->body();

                    // Quota exceeded — return sentinel immediately
                    if ($status === 429 || str_contains($body, 'insufficient_quota')) {
                        Log::warning("AI chat HTTP failed for user {$userId}: status {$status} body: {$body}");
                        return 'QUOTA_EXCEEDED';
                    }

                    // Model-not-found or 404 model error — treat as permanent service unavailability
                    if ($status === 404 && str_contains($body, 'model')) {
                        Log::warning("AI chat HTTP model error for user {$userId}: status {$status} body: {$body}");
                        return 'AI_SERVICE_UNAVAILABLE';
                    }

                    // For other non-ok status codes, log and retry
                    Log::warning("AI chat HTTP failed for user {$userId}: status {$status} body: {$body}");
                }
            } catch (\Throwable $e) {
                Log::warning("AI chat HTTP exception for user {$userId}: " . $e->getMessage());
            }

            // Exponential backoff before retry
            sleep($backoffSeconds);
            $backoffSeconds *= 2;
        }

        Log::error("AI Generation final failure for user {$userId}: no usable response after {$maxAttempts} attempts.");
        return 'AI_SERVICE_UNAVAILABLE';
    }

    private function buildFallbackWeeklySummary($allData, string $weekLabel): string
    {
        $total = $allData->count();
        $good = $allData->where('mood_value', 3)->count();
        $okay = $allData->where('mood_value', 2)->count();
        $bad = $allData->where('mood_value', 1)->count();

        $dominant = 'mixed';
        $max = max($good, $okay, $bad);
        if ($max === $good && $good > 0) {
            $dominant = 'positive';
        } elseif ($max === $okay && $okay > 0) {
            $dominant = 'steady';
        } elseif ($max === $bad && $bad > 0) {
            $dominant = 'challenging';
        }

        return "For {$weekLabel}, {$total} sentiment check-ins were recorded. " .
            "The overall pattern appears {$dominant}, with {$good} positive, {$okay} neutral, and {$bad} low-mood entries. " .
            "This weekly snapshot can guide supportive follow-ups and focused coaching conversations for the coming week.";
    }
}
