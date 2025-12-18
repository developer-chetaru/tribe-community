<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OneSignalService;
use Illuminate\Support\Facades\Log;

class UpdateWorkingDayStatus extends Command
{
    protected $signature = 'onesignal:update-working-day-status';
    protected $description = 'Update has_working_today tag for all users (runs daily)';

    public function handle(OneSignalService $oneSignal)
    {
        $this->info('Updating has_working_today tag for all users...');
        Log::info('Cron: UpdateWorkingDayStatus started');

        $stats = $oneSignal->updateAllUsersWorkingDayStatus();

        $this->info("✅ Update complete:");
        $this->info("   Total: {$stats['total']}");
        $this->info("   Success: {$stats['success']}");
        $this->info("   Failed: {$stats['failed']}");

        Log::info('Cron: UpdateWorkingDayStatus completed', $stats);

        return 0;
    }
}

