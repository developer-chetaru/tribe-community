<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Livewire\WeeklySummary;
use App\Livewire\MonthlySummary;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\GenerateAllWeeklySummaries::class,
        \App\Console\Commands\GenerateAllMonthlySummaries::class,
        \App\Console\Commands\ProcessMonthlyBilling::class,
        \App\Console\Commands\ProcessPaymentRetries::class,
        \App\Console\Commands\ResetSentimentTag::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // NOTE: notification and report schedules are in routes/console.php
        // because they need to run every minute and routes/console.php schedules
        // are properly detected by the scheduler in Laravel 11

        // -------------------------
        // Weekly Summary Cron
        // -------------------------
        $schedule->call(function () {
            $users = User::all();

            foreach ($users as $user) {
                try {
                    $component = new WeeklySummary();

                    // Use current month/year
                    $component->selectedYear = now()->year;
                    $component->selectedMonth = now()->month;

                    // Generate summary for user (cron-safe)
                    $component->generateMonthlySummaries($user);

                    Log::info("Weekly summary generated successfully for user: {$user->id}");
                } catch (\Exception $e) {
                    Log::error("Failed to generate weekly summary for user {$user->id}: " . $e->getMessage());
                }
            }
        })
        ->weeklyOn(0, '23:00') // Sunday 11 PM
        ->timezone('Asia/Kolkata')
        ->name('weekly-summary-generator')
        ->withoutOverlapping();

        $schedule->command('everyday:update')
            ->monthlyOn(now()->endOfMonth()->day, '22:00')
            ->timezone('Asia/Kolkata') // adjust timezone as needed
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/monthly_summary.log'));

        $schedule->command('leave:update-status')
            ->daily()
            ->timezone('Asia/Kolkata')
            ->appendOutputTo(storage_path('logs/scheduler.log'));

        // -------------------------
        // Daily Sentiment Tag Reset
        // -------------------------
        $schedule->command('onesignal:reset-sentiment-tag')
            ->dailyAt('00:00')
            ->timezone('Asia/Kolkata')
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