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

                // Check if summary already exists
                $exists = WeeklySummaryModel::where([
                    'user_id' => $user->id,
                    'year' => $year,
                    'month' => $month,
                    'week_number' => $week['number'],
                ])->exists();

                if ($exists) {
                    $this->line("Skipping user {$user->id}, week {$week['number']} - already exists");
                    continue;
                }

                // Get mood data for this week
                $startUTC = $week['start']->copy()->setTimezone('UTC');
                $endUTC = $week['end']->copy()->setTimezone('UTC');

                $allData = HappyIndex::where('user_id', $user->id)
                    ->whereBetween('created_at', [$startUTC, $endUTC])
                    ->orderBy('created_at')
                    ->get(['mood_value', 'description', 'created_at']);

                if ($allData->isEmpty()) {
                    $this->line("No data for user {$user->id}, week {$week['number']}");
                    continue;
                }

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

                // Generate summary using the same method as EveryDayUpdate
                $summaryText = $this->generateAIText($prompt, $user->id);

                if ($summaryText === 'QUOTA_EXCEEDED') {
                    $summaryText = 'Summary unavailable due to AI quota limits.';
                } elseif ($summaryText === 'AI_SERVICE_UNAVAILABLE' || empty(trim($summaryText))) {
                    $summaryText = 'No summary generated.';
                }

                // Save summary
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
            }
        }

        $this->info("Generated {$generated} weekly summaries.");
    }

    private function generateAIText(string $prompt, $userId = null): string
    {
        try {
            $response = \OpenAI\Laravel\Facades\OpenAI::responses()->create([
                'model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4.1-mini'),
                'input' => $prompt,
            ]);

            $summaryText = '';
            if (!empty($response->output)) {
                foreach ($response->output as $item) {
                    foreach ($item->content ?? [] as $c) {
                        $summaryText .= $c->text ?? '';
                    }
                }
            }

            return trim($summaryText) ?: 'Summary could not be generated due to an error.';
        } catch (\Exception $e) {
            Log::error("AI generation error for user {$userId}: " . $e->getMessage());
            return 'AI_SERVICE_UNAVAILABLE';
        }
    }
}
