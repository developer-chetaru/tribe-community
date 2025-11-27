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
    ];

    protected function schedule(Schedule $schedule): void
    {
        // ---------------------------------------------------------------------
        // Existing schedules (unchanged)
        // ---------------------------------------------------------------------

    
        // Run every minute to check each user's timezone for notification time (16:30)
        // This ensures we catch 16:30 regardless of which timezone the user is in
        $schedule->command('notification:send --only=notification')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        // Run every minute to check each user's timezone for report time (23:59)
        // This ensures we catch 23:59 regardless of which timezone the user is in
        $schedule->command('notification:send --only=report')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

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
            ->timezone('Asia/Kolkata');
    }

    public function updatedSelectedYear($year)
    {
        $user = auth()->user();
        $registerDate = $user->created_at;
        $currentYear = now()->year;

        // Adjust month if year changes
        if ($year == $registerDate->year && $year == $currentYear) {
            $this->selectedMonth = max($this->selectedMonth, $registerDate->month);
            $this->selectedMonth = min($this->selectedMonth, now()->month);
        } elseif ($year == $registerDate->year) {
            $this->selectedMonth = max($this->selectedMonth, $registerDate->month);
        } elseif ($year == $currentYear) {
            $this->selectedMonth = min($this->selectedMonth, now()->month);
        } else {
            if ($this->selectedMonth < 1 || $this->selectedMonth > 12) {
                $this->selectedMonth = 1;
            }
        }

        $this->loadSummariesFromDatabase();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}