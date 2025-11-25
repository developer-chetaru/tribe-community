<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('notification:send --only=notification')
            ->dailyAt('16:00') 
            ->timezone('Asia/Kolkata');

Schedule::command('notification:send --only=report')
        ->dailyAt('23:59')
        ->timezone('Asia/Kolkata');

Schedule::command('notification:send --only=sentiment')
        ->dailyAt('18:00')
        ->timezone('Asia/Kolkata');

Schedule::command('notification:send --only=monthly-summary')
        ->monthlyOn(28, '22:00')
        ->timezone('Asia/Kolkata');

Schedule::command('notification:send --only=weeklySummary')
        ->weeklyOn(0, '23:00')
        ->timezone('Asia/Kolkata');