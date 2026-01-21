<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OneSignalService;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class MarkEmailsAsSubmitted extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'onesignal:mark-submitted 
                            {emails* : Space-separated list of email addresses}
                            {--file= : Path to file containing emails (one per line)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark specific emails as has_submitted: true in OneSignal';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $emails = $this->argument('emails');
        $filePath = $this->option('file');

        // If file option is provided, read emails from file
        if ($filePath) {
            if (!file_exists($filePath)) {
                $this->error("File not found: {$filePath}");
                return 1;
            }

            $fileEmails = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $emails = array_merge($emails, $fileEmails);
        }

        // Remove duplicates and trim whitespace
        $emails = array_unique(array_map('trim', $emails));
        $emails = array_filter($emails, function($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });

        if (empty($emails)) {
            $this->error('No valid email addresses provided.');
            return 1;
        }

        $this->info("Processing " . count($emails) . " email(s)...");
        $this->newLine();

        $oneSignalService = new OneSignalService();
        $results = $oneSignalService->markEmailsAsSubmitted($emails);

        $successCount = 0;
        $failureCount = 0;

        foreach ($results as $email => $result) {
            if ($result['success']) {
                $this->info("✅ {$email} - User ID: {$result['user_id']} - {$result['message']}");
                $successCount++;
            } else {
                $this->error("❌ {$email} - {$result['message']}");
                $failureCount++;
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->info("  Success: {$successCount}");
        $this->info("  Failed: {$failureCount}");

        return 0;
    }
}
