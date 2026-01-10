<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OneSignalService;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ResetSentimentTag extends Command
{
    protected $signature = 'onesignal:reset-sentiment-tag';
    protected $description = 'Reset has_submitted_today tag to false for users at midnight in their timezone (runs hourly)';

    public function handle(OneSignalService $oneSignal)
    {
        $this->info('Checking for users at midnight in their timezone...');
        Log::info('Cron: ResetSentimentTag started');

        // Get all active users
        $users = User::where('status', 1)->get();
        
        $usersToReset = $users->filter(function ($user) {
            // Get user's timezone or default to Asia/Kolkata
            $userTimezone = $user->timezone ?: 'Asia/Kolkata';
            
            // Validate timezone to prevent errors
            if (!in_array($userTimezone, timezone_identifiers_list())) {
                Log::warning("Invalid timezone for user {$user->id}: {$userTimezone}, using Asia/Kolkata");
                $userTimezone = 'Asia/Kolkata';
            }
            
            // Get current time in user's timezone
            $userNow = Carbon::now($userTimezone);
            
            // Check if it's 00:00 (midnight) in user's timezone
            $currentTime = $userNow->format('H:i');
            return $currentTime === '00:00';
        });

        if ($usersToReset->isEmpty()) {
            $this->info('No users at midnight in their timezone right now.');
            Log::info('Cron: ResetSentimentTag - No users at midnight');
            return 0;
        }

        $this->info("Found {$usersToReset->count()} users at midnight in their timezone. Resetting tags...");

        $stats = [
            'total' => $usersToReset->count(),
            'success' => 0,
            'failed' => 0
        ];

        foreach ($usersToReset as $user) {
            try {
                $oneSignal->resetSentimentTag($user->id);
                $stats['success']++;
                Log::info("Reset sentiment tag for user {$user->id} (timezone: {$user->timezone})");
            } catch (\Exception $e) {
                $stats['failed']++;
                Log::error("Failed to reset sentiment tag for user {$user->id}: " . $e->getMessage());
            }
        }

        $this->info("✅ Reset complete:");
        $this->info("   Total: {$stats['total']}");
        $this->info("   Success: {$stats['success']}");
        $this->info("   Failed: {$stats['failed']}");

        Log::info('Cron: ResetSentimentTag completed', $stats);

        return 0;
    }
}

