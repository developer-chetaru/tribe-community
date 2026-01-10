<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Livewire\WeeklySummary;
use App\Livewire\MonthlySummary;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\GenerateAllWeeklySummaries::class,
        \App\Console\Commands\GenerateAllMonthlySummaries::class,
        \App\Console\Commands\ProcessMonthlyBilling::class,
        \App\Console\Commands\ProcessDailyBilling::class,
        \App\Console\Commands\ProcessPaymentRetries::class,
        \App\Console\Commands\ResetSentimentTag::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // NOTE: notification and report schedules are in routes/console.php
        // because they need to run every minute and routes/console.php schedules
        // are properly detected by the scheduler in Laravel 11

        // -------------------------
        // Weekly Summary Cron - Runs hourly to check each user's timezone
        // -------------------------
        $schedule->call(function () {
            $users = User::where('status', 1)->get();

            foreach ($users as $user) {
                try {
                    // Get user's timezone or default to Asia/Kolkata
                    $userTimezone = $user->timezone ?: 'Asia/Kolkata';
                    
                    // Validate timezone to prevent errors
                    if (!in_array($userTimezone, timezone_identifiers_list())) {
                        Log::warning("Invalid timezone for user {$user->id}: {$userTimezone}, using Asia/Kolkata");
                        $userTimezone = 'Asia/Kolkata';
                    }
                    
                    // Get current time in user's timezone
                    $userNow = \Carbon\Carbon::now($userTimezone);
                    
                    // Check if it's Sunday 23:00 in user's timezone
                    $isSunday = $userNow->isSunday();
                    $is2300 = $userNow->format('H:i') === '23:00';
                    
                    if (!$isSunday || !$is2300) {
                        continue; // Skip if not Sunday 23:00 in user's timezone
                    }
                    
                    $component = new WeeklySummary();

                    // Use current month/year based on user's timezone
                    $component->selectedYear = $userNow->year;
                    $component->selectedMonth = $userNow->month;

                    // Generate summary for user (cron-safe)
                    $component->generateMonthlySummaries($user);

                    Log::info("Weekly summary generated successfully for user: {$user->id} (timezone: {$userTimezone})");
                } catch (\Exception $e) {
                    Log::error("Failed to generate weekly summary for user {$user->id}: " . $e->getMessage());
                }
            }
        })
        ->hourly() // Run every hour to check each user's timezone
        ->name('weekly-summary-generator')
        ->withoutOverlapping();

        // -------------------------
        // Monthly Update - Runs hourly to check last day of month for each user's timezone
        // -------------------------
        $schedule->call(function () {
            $users = User::where('status', 1)->get();
            
            foreach ($users as $user) {
                try {
                    // Get user's timezone or default to Asia/Kolkata
                    $userTimezone = $user->timezone ?: 'Asia/Kolkata';
                    
                    // Validate timezone
                    if (!in_array($userTimezone, timezone_identifiers_list())) {
                        $userTimezone = 'Asia/Kolkata';
                    }
                    
                    $userNow = \Carbon\Carbon::now($userTimezone);
                    
                    // Check if it's last day of month and 22:00 in user's timezone
                    if ($userNow->isLastOfMonth() && $userNow->format('H:i') === '22:00') {
                        Artisan::call('everyday:update');
                        Log::info("Monthly update triggered for user: {$user->id} (timezone: {$userTimezone})");
                    }
                } catch (\Exception $e) {
                    Log::error("Failed to check monthly update for user {$user->id}: " . $e->getMessage());
                }
            }
        })
        ->hourly()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/monthly_summary.log'));

        // -------------------------
        // Leave Status Update - Runs hourly to check midnight for each user's timezone
        // -------------------------
        $schedule->call(function () {
            $users = User::where('status', 1)->get();
            
            foreach ($users as $user) {
                try {
                    // Get user's timezone or default to Asia/Kolkata
                    $userTimezone = $user->timezone ?: 'Asia/Kolkata';
                    
                    // Validate timezone
                    if (!in_array($userTimezone, timezone_identifiers_list())) {
                        $userTimezone = 'Asia/Kolkata';
                    }
                    
                    $userNow = \Carbon\Carbon::now($userTimezone);
                    
                    // Check if it's 00:00 (midnight) in user's timezone
                    if ($userNow->format('H:i') === '00:00') {
                        Artisan::call('leave:update-status');
                        Log::info("Leave status update triggered for user: {$user->id} (timezone: {$userTimezone})");
                    }
                } catch (\Exception $e) {
                    Log::error("Failed to check leave status update for user {$user->id}: " . $e->getMessage());
                }
            }
        })
        ->hourly()
        ->appendOutputTo(storage_path('logs/scheduler.log'));

        // -------------------------
        // Daily Sentiment Tag Reset - Runs hourly to check midnight for each user's timezone
        // -------------------------
        $schedule->command('onesignal:reset-sentiment-tag')
            ->hourly() // Changed from dailyAt to hourly for timezone-based filtering
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/scheduler.log'));

        // -------------------------
        // Monthly Billing Cron
        // -------------------------
        $schedule->command('billing:process-monthly')
            ->monthlyOn(1, '00:00') // 1st of each month at midnight
            ->timezone('UTC')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/billing.log'));

        // -------------------------
        // Daily Subscription Check Cron (checks monthly subscriptions daily for auto-renewal)
        // Uses UTC for system-level billing checks (same time for all users)
        // -------------------------
        $schedule->command('billing:process-daily')
            ->dailyAt('00:01') // Daily at 00:01 UTC (system-level check)
            ->timezone('UTC')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/billing-daily.log'));

        // -------------------------
        // Payment Retry Cron
        // -------------------------
        $schedule->command('billing:retry-payments')
            ->hourly()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/billing-retry.log'));

        // Log scheduled tasks count
        Log::info('Laravel Scheduler: All scheduled tasks registered successfully');
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}