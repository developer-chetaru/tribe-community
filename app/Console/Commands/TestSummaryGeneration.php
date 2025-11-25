<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\HappyIndex;
use App\Models\WeeklySummary;
use OpenAI;

class TestSummaryGeneration extends Command
{
    protected $signature = 'test:summary {--start=} {--end=}';
    protected $description = 'Generate weekly summaries immediately for testing between custom dates';

    public function handle()
    {
        $tz = 'Asia/Kolkata';

        $startInput = $this->option('start');
        $endInput   = $this->option('end');

        if (!$startInput || !$endInput) {
            $this->error("Please provide --start and --end dates. Example: --start=2025-10-13 --end=2025-10-19");
            return;
        }

        $startIST = \Carbon\Carbon::parse($startInput, $tz)->startOfDay();
        $endIST   = \Carbon\Carbon::parse($endInput, $tz)->endOfDay();

        // Convert to UTC for DB query (HappyIndex.created_at is in UTC)
        $startUTC = $startIST->copy()->timezone('UTC');
        $endUTC   = $endIST->copy()->timezone('UTC');

        $weekLabel = $startIST->format('d M') . ' - ' . $endIST->format('d M');
        $year = $startIST->year;
        $month = $startIST->month;
        $weekNumber = ceil($startIST->day / 7);

        $this->info("🧠 Generating weekly summary for: {$weekLabel}");

        // Fetch users with mood data in this date range
        $userIds = HappyIndex::whereBetween('created_at', [$startUTC, $endUTC])
            ->distinct()
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            $this->warn("⚠️ No mood data found between {$weekLabel}");
            return;
        }

        $users = User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            $allData = HappyIndex::where('user_id', $user->id)
                ->whereBetween('created_at', [$startUTC, $endUTC])
                ->orderBy('created_at')
                ->get(['mood_value', 'description', 'created_at']);

            if ($allData->isEmpty()) continue;

            // Prepare entries in IST for readability
            $entries = $allData->map(function ($item) use ($tz) {
                $date = $item->created_at->copy()->timezone($tz)->format('M d, D');
                $mood = match ($item->mood_value) {
                    3 => 'Good 😊',
                    2 => 'Okay 😐',
                    1 => 'Bad 🙁',
                    default => 'Unknown',
                };
                $desc = $item->description ? " - {$item->description}" : '';
                return "{$date}: {$mood}{$desc}";
            })->implode("\n");

            // Prepare AI prompt
            $prompt = <<<PROMPT
Generate a short, friendly weekly emotional summary for the user based on the following daily sentiment data from {$weekLabel}:

{$entries}

Your summary should:
- Highlight emotional patterns and changes over the week
- Be supportive and encouraging
- Keep the tone conversational and positive
PROMPT;

            // Generate AI summary using OpenAI Chat API
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

                $summaryText = trim($summaryText) ?: 'No summary generated.';
            } catch (\Throwable $e) {
                \Log::error("MonthlySummary: Failed for user {$user->id}. Error: " . $e->getMessage());
                $summaryText = 'Error generating summary.';
            }

            // Save/update weekly summary
            WeeklySummary::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'week_label' => $weekLabel,
                ],
                [
                    'summary' => $summaryText,
                    'year' => $year,
                    'month' => $month,
                    'week_number' => $weekNumber,
                ]
            );

            $this->info("✅ WeeklySummary generated for user {$user->id} ({$weekLabel})");
        }

        $this->info("✅ Weekly summary generation completed for all users between {$weekLabel}");
    }
}