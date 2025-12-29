<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SubscriptionRecord;
use App\Models\PaymentRecord;
use App\Models\Invoice;
use App\Services\Billing\StripeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessPaymentRetries extends Command
{
    protected $signature = 'billing:retry-payments';
    protected $description = 'Retry failed payments and handle account suspension';

    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        parent::__construct();
        $this->stripeService = $stripeService;
    }

    public function handle()
    {
        $this->info('Starting payment retry process...');
        Log::info('Payment retry cron job started');

        // Get subscriptions with failed payments
        $subscriptions = SubscriptionRecord::where('status', 'past_due')
            ->where('payment_failed_count', '>', 0)
            ->get();

        foreach ($subscriptions as $subscription) {
            try {
                $this->processRetry($subscription);
            } catch (\Exception $e) {
                Log::error("Failed to retry payment for subscription {$subscription->id}: " . $e->getMessage());
                $this->error("Failed to retry payment for subscription {$subscription->id}: " . $e->getMessage());
            }
        }

        // Handle grace period expiration
        $this->handleGracePeriodExpiration();

        // Handle suspension period expiration
        $this->handleSuspensionExpiration();

        $this->info('Payment retry process completed');
    }

    protected function processRetry(SubscriptionRecord $subscription)
    {
        $lastPayment = PaymentRecord::where('subscription_id', $subscription->id)
            ->where('status', 'failed')
            ->latest()
            ->first();

        if (!$lastPayment) {
            return;
        }

        $hoursSinceFailure = Carbon::parse($lastPayment->created_at)->diffInHours(now());

        // Retry logic based on failure count
        if ($subscription->payment_failed_count == 1 && $hoursSinceFailure >= 24) {
            $this->attemptRetry($subscription, $lastPayment, 1);
        } elseif ($subscription->payment_failed_count == 2 && $hoursSinceFailure >= 48) {
            $this->attemptRetry($subscription, $lastPayment, 2);
        } elseif ($subscription->payment_failed_count == 3 && $hoursSinceFailure >= 72) {
            $this->enterGracePeriod($subscription);
        }
    }

    protected function attemptRetry(SubscriptionRecord $subscription, PaymentRecord $lastPayment, $attemptNumber)
    {
        $this->info("Retry attempt #{$attemptNumber} for subscription {$subscription->id}");

        // Note: Actual payment retry is handled by payment gateway
        // Stripe automatically retries failed payments
        // This is mainly for tracking and notifications

        Log::info("Payment retry attempt #{$attemptNumber} for subscription {$subscription->id}");

        // TODO: Check payment status via API and update accordingly
        // For now, we rely on webhooks to update payment status
    }

    protected function enterGracePeriod(SubscriptionRecord $subscription)
    {
        if ($subscription->status !== 'past_due') {
            return;
        }

        $this->warn("Entering grace period for subscription {$subscription->id}");

        // Send grace period warning email
        // TODO: Implement email notification
        Log::warning("Grace period started for subscription {$subscription->id}");

        // Restrict new feature access (handled by middleware/authorization)
        $subscription->update([
            'status' => 'past_due', // Keep as past_due during grace period
        ]);
    }

    protected function handleGracePeriodExpiration()
    {
        // Find subscriptions that have been in grace period for 7+ days
        $subscriptions = SubscriptionRecord::where('status', 'past_due')
            ->where('payment_failed_count', '>=', 3)
            ->get();

        foreach ($subscriptions as $subscription) {
            $lastPayment = PaymentRecord::where('subscription_id', $subscription->id)
                ->where('status', 'failed')
                ->latest()
                ->first();

            if ($lastPayment && Carbon::parse($lastPayment->created_at)->diffInDays(now()) >= 7) {
                $this->suspendAccount($subscription);
            }
        }
    }

    protected function suspendAccount(SubscriptionRecord $subscription)
    {
        $this->error("Suspending account for subscription {$subscription->id}");

        $subscription->update([
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);

        $subscription->organisation->update([
            'status' => 'suspended',
        ]);

        // TODO: Send suspension email
        Log::warning("Account suspended for subscription {$subscription->id}");
    }

    protected function handleSuspensionExpiration()
    {
        // Find subscriptions suspended for 30+ days
        $subscriptions = SubscriptionRecord::where('status', 'suspended')
            ->whereNotNull('suspended_at')
            ->get();

        foreach ($subscriptions as $subscription) {
            $daysSuspended = Carbon::parse($subscription->suspended_at)->diffInDays(now());

            if ($daysSuspended >= 30) {
                // Send final warning
                if ($daysSuspended < 37) {
                    // TODO: Send final warning email (7 days before deletion)
                    Log::warning("Final warning for subscription {$subscription->id} - will be deleted in " . (37 - $daysSuspended) . " days");
                } elseif ($daysSuspended >= 37) {
                    // Delete account and data
                    $this->deleteAccount($subscription);
                }
            }
        }
    }

    protected function deleteAccount(SubscriptionRecord $subscription)
    {
        $this->error("Deleting account for subscription {$subscription->id}");

        // Cancel subscription (Stripe only)
        if ($subscription->stripe_subscription_id) {
            $this->stripeService->cancelSubscription($subscription->stripe_subscription_id, false);
        }

        // Update subscription status
        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);

        // Update organisation status
        $subscription->organisation->update([
            'status' => 'deleted',
        ]);

        // TODO: Send deletion confirmation email
        Log::error("Account deleted for subscription {$subscription->id}");
    }
}

