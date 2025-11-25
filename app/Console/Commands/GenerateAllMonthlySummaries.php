<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\HappyIndex;
use App\Models\MonthlySummary;
use App\Services\OneSignalService;
use Carbon\Carbon;
use OpenAI\Laravel\Facades\OpenAI;
use Log;

class GenerateAllMonthlySummaries extends Command
{
    protected $signature = 'sentiment:generate-monthly {--all}';
    protected $description = 'Generate the last 2 monthly sentiment summaries for all users';

    public function handle()
    {
        if (!$this->option('all')) {
            $this->error("Use: php artisan sentiment:generate-monthly --all");
            return;
        }

        $this->info("Generating monthly summaries…");

        // 🔥 ONLY LAST 2 MONTHS (current + last month)
        $startMonth = now()->startOfMonth()->subMonth();
        $endMonth   = now()->startOfMonth();

        $oneSignal = new OneSignalService();

        while ($startMonth->lte($endMonth)) {

            $monthStartUTC = $startMonth->copy()->setTimezone('UTC');
            $monthEndUTC   = $startMonth->copy()->endOfMonth()->setTimezone('UTC');

            $userIds = HappyIndex::whereBetween('created_at', [$monthStartUTC, $monthEndUTC])
                ->distinct()
                ->pluck('user_id');

            foreach ($userIds as $uid) {

                $user = User::find($uid);
                if (!$user) continue;

                // Skip if summary already exists
                if (MonthlySummary::where([
                    'user_id' => $uid,
                    'year'    => $startMonth->year,
                    'month'   => $startMonth->month,
                ])->exists()) {
                    continue;
                }

                // Fetch monthly entries
                $entries = HappyIndex::where('user_id', $uid)
                    ->whereBetween('created_at', [$monthStartUTC, $monthEndUTC])
                    ->orderBy('created_at')
                    ->get();

                if ($entries->isEmpty()) continue;

                $mapped = $entries->map(function ($h) {
                    return $h->created_at->setTimezone('Asia/Kolkata')->format('d M (D)')
                        . " : " . $h->mood_value;
                })->implode("\n");

                $monthName = $startMonth->format('F Y');

                // Build prompt
                $prompt = <<<PROMPT
Write a short friendly monthly emotional summary for the user.
Month: {$monthName}

Entries:
{$mapped}

Summary should be:
• 3 to 5 sentences  
• Motivational  
• Positive but honest  
PROMPT;

                // -----------------------
                // OPENAI CHAT CALL
                // -----------------------
                $summary = $this->askOpenAI($prompt);

                // Save to DB
                MonthlySummary::create([
                    'user_id'     => $uid,
                    'year'        => $startMonth->year,
                    'month'       => $startMonth->month,
                    'month_label' => $monthName,
                    'summary'     => $summary
                ]);

                // Email HTML
                $html = "
                    <h2 style='color:#EB1C24;'>Your Monthly Summary</h2>
                    <p>Hi {$user->first_name},</p>
                    <p>Here is your sentiment summary for <strong>{$monthName}</strong>:</p>
                    <p style='background:#f5f5f5;padding:12px;border-radius:6px;'>{$summary}</p>
                    <br>
                    <p style='font-size:12px;color:#666;'>– The Tribe365 Team</p>
                ";

                // Send email via OneSignal
                try {
                    $oneSignal->sendEmailMessage(
                        $user->email,
                        "📅 Your Monthly Summary – {$monthName}",
                        $html
                    );
                } catch (\Throwable $e) {
                    Log::error("Monthly summary email failed for user {$uid}: ".$e->getMessage());
                }

                Log::info("Monthly summary created for user {$uid}");
            }

            $startMonth->addMonth();
        }

        $this->info("✓ Monthly summaries generated successfully!");
    }

    // -------------------------------------
    // SAFE OpenAI CALL
    // -------------------------------------
    private function askOpenAI($prompt)
    {
        $attempts = 0;

        while ($attempts < 5) {
            try {
                $response = OpenAI::chat()->create([
                    'model' => 'gpt-4.1-mini',
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ],
                ]);

                return $response['choices'][0]['message']['content']
                    ?? "No summary generated.";
            }

            catch (\OpenAI\Exceptions\RateLimitException $e) {
                $attempts++;
                $wait = 2 * $attempts; // exponential backoff
                Log::warning("OpenAI rate limit hit. Retrying in {$wait} seconds...");
                sleep($wait);
            }

            catch (\Throwable $e) {
                Log::error("OpenAI Monthly Error: " . $e->getMessage());
                return "Summary generation failed.";
            }
        }

        return "Summary generation failed after retries.";
    }

}

