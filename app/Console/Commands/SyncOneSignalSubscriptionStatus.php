<?php

namespace App\Console\Commands;

use App\Models\SubscriptionRecord;
use App\Models\User;
use App\Services\OneSignalService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncOneSignalSubscriptionStatus extends Command
{
    protected $signature = 'onesignal:sync-subscription-status {--dry-run : Show actions without making OneSignal API changes}';

    protected $description = 'Keep OneSignal subscribed only for users with active subscription access, unsubscribe others';

    public function handle(OneSignalService $oneSignalService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $today = Carbon::today()->startOfDay();

        $users = User::with('roles')->get();

        $stats = [
            'total' => $users->count(),
            'kept_subscribed' => 0,
            'unsubscribed' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($users as $user) {
            try {
                if ($user->hasRole('super_admin')) {
                    $stats['skipped']++;
                    continue;
                }

                $hasAccess = $this->hasSubscriptionAccess($user, $today);

                if ($hasAccess) {
                    if ($dryRun) {
                        $this->line("[dry-run] keep subscribed: user {$user->id} ({$user->email})");
                        $stats['kept_subscribed']++;
                        continue;
                    }

                    $ok = $oneSignalService->setUserTagsOnLogin($user);
                    if ($ok) {
                        $stats['kept_subscribed']++;
                    } else {
                        $stats['failed']++;
                    }
                    continue;
                }

                if ($dryRun) {
                    $this->line("[dry-run] unsubscribe: user {$user->id} ({$user->email})");
                    $stats['unsubscribed']++;
                    continue;
                }

                $ok = $oneSignalService->deleteUserByExternalId($user->id);
                if ($ok) {
                    $stats['unsubscribed']++;
                } else {
                    $stats['failed']++;
                }
            } catch (\Throwable $e) {
                $stats['failed']++;
                Log::error('OneSignal subscription sync failed for user', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total users scanned', $stats['total']],
                ['Kept subscribed', $stats['kept_subscribed']],
                ['Unsubscribed/removed', $stats['unsubscribed']],
                ['Skipped', $stats['skipped']],
                ['Failed', $stats['failed']],
            ]
        );

        Log::info('OneSignal subscription access sync completed', $stats + ['dry_run' => $dryRun]);

        return self::SUCCESS;
    }

    private function hasSubscriptionAccess(User $user, Carbon $today): bool
    {
        // Basecamp user: subscription is tied to user_id.
        if ($user->hasRole('basecamp')) {
            $subscription = SubscriptionRecord::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->latest('id')
                ->first();

            if (!$subscription) {
                return false;
            }

            $endDate = $subscription->current_period_end
                ? Carbon::parse($subscription->current_period_end)->startOfDay()
                : null;

            if ($endDate && $endDate->greaterThanOrEqualTo($today)) {
                return !in_array($subscription->status, ['suspended', 'inactive'], true);
            }

            return $subscription->status === 'active';
        }

        // Organisation user: subscription is tied to organisation_id.
        if ($user->orgId) {
            $subscription = SubscriptionRecord::where('organisation_id', $user->orgId)
                ->latest('id')
                ->first();

            if (!$subscription) {
                return false;
            }

            $endDate = $subscription->current_period_end
                ? Carbon::parse($subscription->current_period_end)->startOfDay()
                : null;

            if ($endDate && $endDate->greaterThanOrEqualTo($today)) {
                return !in_array($subscription->status, ['suspended', 'inactive'], true);
            }

            return $subscription->status === 'active';
        }

        return false;
    }
}
