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
        \App\Console\Commands\EveryDayUpdate::class,
		 \App\Console\Commands\GenerateAllWeeklySummaries::class,
        \App\Console\Commands\GenerateAllMonthlySummaries::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        $schedule->command('notification:send --only=notification')
            ->dailyAt('16:30')
            ->timezone('Asia/Kolkata');

        $schedule->command('notification:send --only=sentiment')
            ->dailyAt('18:00')
            ->timezone('Asia/Kolkata');

        $schedule->command('notification:send --only=report')
            ->dailyAt('23:59')
            ->timezone('Asia/Kolkata');

      $schedule->command('notification:send --only=monthly-summary')
        ->monthlyOn(28, '22:00')
        ->timezone('Asia/Kolkata');

      $schedule->command('notification:send --only=weeklySummary')
        ->weeklyOn(1, '23:00')
        ->timezone('Asia/Kolkata');

      $schedule->call(function () {
          \Log::info('✅ Laravel scheduler is running successfully at ' . now());
      })->everyMinute();
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
