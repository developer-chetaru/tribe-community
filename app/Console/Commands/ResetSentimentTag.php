<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OneSignalService;
use Illuminate\Support\Facades\Log;

class ResetSentimentTag extends Command
{
    protected $signature = 'onesignal:reset-sentiment-tag';
    protected $description = 'Reset has_submitted_today tag to false for all users (runs daily at midnight)';

    public function handle(OneSignalService $oneSignal)
    {
        $this->info('Resetting has_submitted_today tag for all users...');
        Log::info('Cron: ResetSentimentTag started');

        $stats = $oneSignal->resetAllUsersSentimentTag();

        $this->info("✅ Reset complete:");
        $this->info("   Total: {$stats['total']}");
        $this->info("   Success: {$stats['success']}");
        $this->info("   Failed: {$stats['failed']}");

        Log::info('Cron: ResetSentimentTag completed', $stats);

        return 0;
    }
}

