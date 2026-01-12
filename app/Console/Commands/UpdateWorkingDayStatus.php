<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OneSignalService;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UpdateWorkingDayStatus extends Command
{
    protected $signature = 'onesignal:update-working-day-status {--time=00:00 : Time to check (00:00 or 11:10)}';
    protected $description = 'Update has_working_today tag for users at specified time in their timezone (runs hourly)';

    public function handle(OneSignalService $oneSignal)
    {
        $targetTime = $this->option('time') ?: '00:00';
        $this->info("Checking for users at {$targetTime} in their timezone...");
        Log::info('Cron: UpdateWorkingDayStatus started', ['target_time' => $targetTime]);

        // Get all active users
        $users = User::whereIn('status', ['active_verified', 'active_unverified', 'pending_payment'])
            ->with('organisation')
            ->get();
        
        $usersToUpdate = $users->filter(function ($user) use ($targetTime) {
            // Get user's timezone safely using helper
            $userTimezone = \App\Helpers\TimezoneHelper::getUserTimezone($user);
            
            // Get current time in user's timezone
            $userNow = \App\Helpers\TimezoneHelper::carbon(null, $userTimezone);
            
            // Check if it's target time in user's timezone
            $currentTime = $userNow->format('H:i');
            return $currentTime === $targetTime;
        });

        if ($usersToUpdate->isEmpty()) {
            $this->info("No users at {$targetTime} in their timezone right now.");
            Log::info('Cron: UpdateWorkingDayStatus - No users at target time');
            return 0;
        }

        $this->info("Found {$usersToUpdate->count()} users at {$targetTime} in their timezone. Updating tags...");

        $stats = [
            'total' => $usersToUpdate->count(),
            'success' => 0,
            'failed' => 0
        ];

        foreach ($usersToUpdate as $user) {
            try {
                // Use setUserTagsOnLogin which creates user if doesn't exist and updates all tags
                $result = $oneSignal->setUserTagsOnLogin($user);

                if ($result) {
                    $stats['success']++;
                    $isWorkingDay = $oneSignal->isWorkingDayToday($user);
                    Log::info("has_working_today updated for user {$user->id} (timezone: {$user->timezone})", [
                        'has_working_today' => $isWorkingDay,
                    ]);
                } else {
                    $stats['failed']++;
                    Log::warning("has_working_today update failed for user {$user->id}");
                }
            } catch (\Exception $e) {
                $stats['failed']++;
                Log::error("Failed to update working day status for user {$user->id}: " . $e->getMessage());
            }
        }

        $this->info("✅ Update complete:");
        $this->info("   Total: {$stats['total']}");
        $this->info("   Success: {$stats['success']}");
        $this->info("   Failed: {$stats['failed']}");

        Log::info('Cron: UpdateWorkingDayStatus completed', $stats);

        return 0;
    }
}

