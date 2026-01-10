<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// NOTE: notification and report schedules moved to Kernel.php for timezone-based scheduling
// They now run every minute and filter users based on their individual timezones

// COMMENTED OUT: 4pm notification cron
// Run every minute to check each user's timezone for notification time (16:00)
// Schedule::command('notification:send --only=notification')
//         ->everyMinute()
//         ->withoutOverlapping()
//         ->appendOutputTo(storage_path('logs/scheduler.log'));

// Run every minute to check each user's timezone for report time (23:59)
Schedule::command('notification:send --only=report')
        ->everyMinute()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/scheduler.log'));

// COMMENTED OUT: 6pm email cron for sentiment reminder
// Schedule::command('notification:send --only=sentiment')
//         ->dailyAt('18:00')
//         ->timezone('Asia/Kolkata');

// Monthly summary - runs hourly to check last day of month at 22:00 in each user's timezone
// The notification:send command already filters by user timezone inside
Schedule::command('notification:send --only=monthly-summary')
        ->hourly()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/monthly_summary.log'));

// Weekly summary - runs hourly to check Sunday 23:00 in each user's timezone
// The notification:send command already filters by user timezone inside
Schedule::command('notification:send --only=weeklySummary')
        ->hourly()
        ->withoutOverlapping();

// Update has_working_today tag for users at midnight (00:00) in their timezone
Schedule::command('onesignal:update-working-day-status --time=00:00')
        ->hourly() // Changed from dailyAt to hourly for timezone-based filtering
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/scheduler.log'));

// Update has_working_today tag for users at 11:10 AM in their timezone
Schedule::command('onesignal:update-working-day-status --time=11:10')
        ->hourly() // Changed from dailyAt to hourly for timezone-based filtering
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/scheduler.log'));