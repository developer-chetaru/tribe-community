<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OneSignalService;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SyncAllUserTagsToOneSignal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'onesignal:sync-all-tags {--force : Force update all users regardless of changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync all user tags to OneSignal (runs every minute)';

    /**
     * Execute the console command.
     */
    public function handle(OneSignalService $oneSignal)
    {
        $force = $this->option('force');
        
        $this->info('Starting OneSignal tags sync...');
        Log::info('Cron: SyncAllUserTagsToOneSignal started');

        // Get all active users
        // Status is ENUM: 'pending_payment', 'active_unverified', 'active_verified', 'suspended', 'cancelled', 'inactive'
        $users = User::whereIn('status', ['active_verified', 'active_unverified', 'pending_payment'])
            ->with('organisation')
            ->get();

        if ($users->isEmpty()) {
            $this->warn('No active users found.');
            Log::warning('Cron: SyncAllUserTagsToOneSignal - No active users');
            return 0;
        }

        $this->info("Found {$users->count()} active users. Syncing tags...");

        $stats = [
            'total' => $users->count(),
            'synced' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        $progressBar = $this->output->createProgressBar($users->count());
        $progressBar->start();

        foreach ($users as $user) {
            try {
                // Check if user needs update (only if not forced)
                if (!$force) {
                    // Check if any tag values have changed since last sync
                    // For now, we'll always sync since tags are lightweight
                    // You can add logic here to track last sync time if needed
                }

                // Sync all tags to OneSignal
                // This creates user if doesn't exist and updates all tags
                $result = $oneSignal->setUserTagsOnLogin($user);

                if ($result) {
                    $stats['synced']++;
                } else {
                    $stats['failed']++;
                    Log::warning('OneSignal tag sync failed', [
                        'user_id' => $user->id,
                    ]);
                }
            } catch (\Throwable $e) {
                $stats['failed']++;
                Log::error('OneSignal tag sync exception', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display stats
        $this->info('✅ Sync completed:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Users', $stats['total']],
                ['Synced', $stats['synced']],
                ['Failed', $stats['failed']],
                ['Skipped', $stats['skipped']],
            ]
        );

        Log::info('Cron: SyncAllUserTagsToOneSignal completed', $stats);

        return 0;
    }
}
