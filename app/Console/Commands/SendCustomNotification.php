<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\OneSignalService;
use Illuminate\Support\Facades\Log;

class SendCustomNotification extends Command
{
    // Command signature (the name you'll use in schedule)
    protected $signature = 'notification:custom';

    protected $description = 'Send a custom notification to users daily';

    public function handle(OneSignalService $oneSignal)
    {
        Log::info('Starting custom notification cron...');

        $playerIds = User::whereNotNull('fcmToken')
            ->where('fcmToken', '!=', '')
            ->pluck('fcmToken')
            ->toArray();

        if (!empty($playerIds)) {
            $oneSignal->sendNotification(
                'Daily Reminder',
                'Please update your sentiment today!',
                $playerIds
            );

            Log::info('Custom notification sent successfully to users.');
            $this->info('✅ Notification sent successfully.');
        } else {
            Log::warning('⚠️ No player IDs found for custom notification.');
            $this->warn('No users found with FCM tokens.');
        }

        return 0;
    }
}
