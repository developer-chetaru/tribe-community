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

// Monthly summary - runs on last day of month at 22:00
Schedule::command('notification:send --only=monthly-summary')
        ->dailyAt('22:00')
        ->timezone('Asia/Kolkata')
        ->when(function () {
            return now('Asia/Kolkata')->isLastOfMonth();
        });

Schedule::command('notification:send --only=weeklySummary')
        ->weeklyOn(0, '23:00')
        ->timezone('Asia/Kolkata');

// Update has_working_today tag for all users daily at midnight and 11:10 AM
Schedule::command('onesignal:update-working-day-status')
        ->dailyAt('00:00')
        ->timezone('Asia/Kolkata')
        ->appendOutputTo(storage_path('logs/scheduler.log'));

Schedule::command('onesignal:update-working-day-status')
        ->dailyAt('11:10')
        ->timezone('Asia/Kolkata')
        ->appendOutputTo(storage_path('logs/scheduler.log'));