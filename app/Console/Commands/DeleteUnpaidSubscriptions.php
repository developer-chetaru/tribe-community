<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Log;

class DeleteUnpaidSubscriptions extends Command
{
    protected $signature = 'subscriptions:delete-unpaid
                            {--days=20 : Number of days unpaid after period end before auto-delete}
                            {--dry-run : List subscriptions that would be deleted without deleting}';
    protected $description = 'Auto-delete subscriptions that have been unpaid for the given number of days (default 20)';

    public function handle(SubscriptionService $subscriptionService): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info($dryRun
            ? "Dry run: finding subscriptions unpaid for {$days}+ days..."
            : "Deleting subscriptions unpaid for {$days}+ days...");
        Log::info('Delete unpaid subscriptions command started', ['days' => $days, 'dry_run' => $dryRun]);

        $subscriptions = $subscriptionService->getSubscriptionsUnpaidForDays($days);
        $count = $subscriptions->count();

        if ($count === 0) {
            $this->info('No subscriptions found that are unpaid for ' . $days . '+ days.');
            Log::info('Delete unpaid subscriptions: none found', ['days' => $days]);
            return self::SUCCESS;
        }

        $this->info("Found {$count} subscription(s) unpaid for {$days}+ days.");

        foreach ($subscriptions as $subscription) {
            $label = $subscription->tier === 'basecamp' && $subscription->user_id
                ? "Basecamp subscription id {$subscription->id} (user_id: {$subscription->user_id})"
                : "Subscription id {$subscription->id} (org: {$subscription->organisation_id})";
            if ($dryRun) {
                $this->line("  [dry-run] Would delete: {$label}");
                continue;
            }
            try {
                $subscriptionService->deleteUnpaidSubscriptionRecord($subscription);
                $this->info("  Deleted: {$label}");
            } catch (\Throwable $e) {
                Log::error('Error deleting unpaid subscription: ' . $e->getMessage(), [
                    'subscription_id' => $subscription->id,
                    'exception' => $e,
                ]);
                $this->error("  Failed: {$label} - " . $e->getMessage());
            }
        }

        if (!$dryRun) {
            $this->info("Completed. Deleted {$count} unpaid subscription(s).");
            Log::info('Delete unpaid subscriptions command completed', ['deleted_count' => $count, 'days' => $days]);
        }

        return self::SUCCESS;
    }
}
