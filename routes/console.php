<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// NOTE: notification and report schedules moved to Kernel.php for timezone-based scheduling
// They now run every minute and filter users based on their individual timezones

// Run every minute to check each user's timezone for notification time (16:30)
Schedule::command('notification:send --only=notification')
        ->everyMinute()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/scheduler.log'));

// Run every minute to check each user's timezone for report time (23:59)
Schedule::command('notification:send --only=report')
        ->everyMinute()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/scheduler.log'));

Schedule::command('notification:send --only=sentiment')
        ->dailyAt('18:00')
        ->timezone('Asia/Kolkata');

Schedule::command('notification:send --only=monthly-summary')
        ->monthlyOn(28, '22:00')
        ->timezone('Asia/Kolkata');

Schedule::command('notification:send --only=weeklySummary')
        ->weeklyOn(0, '23:00')
        ->timezone('Asia/Kolkata');