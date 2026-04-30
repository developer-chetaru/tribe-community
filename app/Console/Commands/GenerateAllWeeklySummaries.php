<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\HappyIndex;
use App\Helpers\WeeklySummaryCalendarHelper;
use App\Models\WeeklySummary;
use App\Services\OneSignalService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Log;
use OpenAI\Laravel\Facades\OpenAI;

class GenerateAllWeeklySummaries extends Command
{
    protected $signature = 'sentiment:generate-weekly {--all}';
    protected $description = 'Generate the last 6 weeks sentiment summaries for all users';

    public function handle()
    {
        if (!$this->option('all')) {
            $this->error("Use: php artisan sentiment:generate-weekly --all");
            return;
        }

        $this->info("Generating last 6 weekly summaries…");

        $oneSignal = new OneSignalService();

        // 🔥 Only 6 weeks back
        $startWeek = now()->startOfWeek(CarbonInterface::MONDAY)->subWeeks(5)->startOfDay();
        $endWeek   = now()->startOfWeek(CarbonInterface::MONDAY)->startOfDay();

        while ($startWeek->lte($endWeek)) {

            $weekStartUTC = $startWeek->copy()->utc();
            $weekEndUTC = $startWeek->copy()->endOfWeek(CarbonInterface::SUNDAY)->endOfDay()->utc();

            [$bucketYear, $bucketMonth] = WeeklySummaryCalendarHelper::dashboardYearMonthForWeek($startWeek);
            $weekIndex = WeeklySummaryCalendarHelper::sequentialWeekNumberForMonth($startWeek, $bucketYear, $bucketMonth);

            $userIds = HappyIndex::whereBetween('created_at', [$weekStartUTC, $weekEndUTC])
                ->distinct()
                ->pluck('user_id');

            foreach ($userIds as $uid) {
                $user = User::find($uid);
                if (!$user) continue;

                // Skip if summary exists
                if (WeeklySummary::where([
                    'user_id' => $uid,
                    'year'    => $bucketYear,
                    'month'   => $bucketMonth,
                    'week_number' => $weekIndex,
                ])->exists()) {
                    continue;
                }

                // Pull weekly mood entries
                $entries = HappyIndex::where('user_id', $uid)
                    ->whereBetween('created_at', [$weekStartUTC, $weekEndUTC])
                    ->orderBy('created_at')
                    ->get();

                if ($entries->isEmpty()) continue;

                $mapped = $entries->map(function ($h) {
                    return $h->created_at->setTimezone('Asia/Kolkata')->format('M d (D)')
                           . " : " . $h->mood_value;
                })->implode("\n");

                // Build AI prompt
                $prompt = "Write a friendly weekly emotional summary for the user based on:\n\n".$mapped;

                $summary = $this->askOpenAI($prompt);

                // Save summary
                WeeklySummary::create([
                    'user_id' => $uid,
                    'year'    => $bucketYear,
                    'month'   => $bucketMonth,
                    'week_number' => $weekIndex,
                    'week_label'  => $startWeek->format('M d').' - '.$startWeek->copy()->endOfWeek(CarbonInterface::SUNDAY)->format('M d'),
                    'summary' => $summary,
                ]);

                $html = "
                    <h2 style='color:#EB1C24;'>Your Weekly Summary</h2>
                    <p>{$summary}</p>
                    <br>
                    <p style='font-size:12px;color:#555;'>– The Tribe365 Team</p>
                ";

                try {
                    $oneSignal->registerEmailUser($user->email, $uid);
                } catch (\Throwable $e) {
                    Log::error("Email failed for {$user->email}: ".$e->getMessage());
                }

                Log::info("Weekly summary generated for User {$uid}");
            }

            $startWeek->addWeek();
        }

        $this->info("✓ Last 6 weekly summaries generated successfully!");
    }

    private function askOpenAI($prompt)
    {
        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4.1-mini',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
            ]);

            return $response['choices'][0]['message']['content'] ?? 'No summary generated.';
        }
        catch (\Throwable $e) {
            Log::error("OpenAI Error: " . $e->getMessage());
            return "Summary generation failed.";
        }
    }
}
