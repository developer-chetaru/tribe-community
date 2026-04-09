<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SubscriptionRecord;
use App\Models\Invoice as BillingInvoice;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;

class ProcessDailyBilling extends Command
{
    protected $signature = 'billing:process-daily {--email= : Only sync the basecamp subscription for this user email}';
    protected $description = 'Process monthly subscriptions daily - checks for expired/due subscriptions and processes auto-renewal';

    public function handle()
    {
        $this->info('Starting daily subscription check process...');
        Log::info('Daily subscription check cron job started');

        Stripe::setApiKey(config('services.stripe.secret'));

        if ($email = $this->option('email')) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                $this->error("No user found with email: {$email}");

                return 1;
            }

            $subscription = SubscriptionRecord::query()
                ->where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->first();

            if (! $subscription) {
                $this->error('No basecamp subscription record for this user.');

                return 1;
            }

            $this->info("Single-user sync: subscription_record.id={$subscription->id} ({$email})");
            $this->processSubscription($subscription);

            return 0;
        }

        // Check subscriptions due for billing/period-end sync.
        // Include inactive records too so we can backfill missing unpaid invoice for UI consistency.
        // Also include broken rows: Stripe id present but local period dates missing (webhook never updated DB).
        $subscriptions = SubscriptionRecord::whereIn('status', ['active', 'inactive'])
            ->where(function ($q) {
                $q->whereDate('current_period_end', '<=', Carbon::today())
                    ->orWhere(function ($q2) {
                        $q2->whereNull('current_period_end')
                            ->whereDate('next_billing_date', '<=', Carbon::today());
                    })
                    ->orWhere(function ($q3) {
                        $q3->whereNotNull('stripe_subscription_id')
                            ->where(function ($q4) {
                                $q4->whereNull('current_period_end')
                                    ->orWhereNull('next_billing_date');
                            });
                    })
                    ->orWhere(function ($q5) {
                        $q5->whereNotNull('stripe_customer_id')
                            ->whereNull('stripe_subscription_id');
                    });
            })
            ->get();

        $this->info("Found {$subscriptions->count()} subscriptions due for processing");

        foreach ($subscriptions as $subscription) {
            try {
                $this->processSubscription($subscription);
            } catch (\Exception $e) {
                Log::error("Failed to process subscription {$subscription->id}: " . $e->getMessage());
                $this->error("Failed to process subscription {$subscription->id}: " . $e->getMessage());
            }
        }

        $this->info('Daily subscription check process completed');
        Log::info('Daily subscription check cron job completed');
    }

    protected function processSubscription(SubscriptionRecord $subscription)
    {
        $this->info("Processing subscription ID: {$subscription->id}");

        try {
            $this->tryLinkStripeSubscriptionFromCustomer($subscription);

            // If subscription has no Stripe link and period already ended, mark inactive/unpaid.
            if (! $subscription->stripe_subscription_id) {
                if ($subscription->status === 'inactive') {
                    $this->ensureUnpaidInvoiceForInactive($subscription);
                    return;
                }
                $this->handleNoStripeSubscriptionAfterPeriodEnd($subscription);
                return;
            }

            // Retrieve Stripe subscription to check status
            $stripeSub = \Stripe\Subscription::retrieve($subscription->stripe_subscription_id);

            $stripePeriodStart = isset($stripeSub->current_period_start)
                ? Carbon::createFromTimestamp($stripeSub->current_period_start)
                : null;
            $stripePeriodEnd = isset($stripeSub->current_period_end)
                ? Carbon::createFromTimestamp($stripeSub->current_period_end)
                : null;
            $localPeriodEnd = $subscription->current_period_end
                ? Carbon::parse($subscription->current_period_end)->startOfDay()
                : null;
            $today = Carbon::today();

            // Stripe says subscription is not in good standing — always reconcile to inactive/unpaid.
            if (in_array($stripeSub->status, ['past_due', 'unpaid', 'canceled', 'incomplete_expired', 'incomplete', 'paused'], true)) {
                $this->markInactiveAndUnpaid($subscription, "stripe_status_{$stripeSub->status}");
                Log::warning("Subscription {$subscription->id} marked inactive/unpaid due to Stripe status {$stripeSub->status}");
                return;
            }

            // Successful renewal: Stripe's current period extends past now — sync local DB if needed.
            if ($stripePeriodEnd && $stripePeriodEnd->isFuture()) {
                if (!$localPeriodEnd || !$stripePeriodEnd->isSameDay($localPeriodEnd)) {
                    $subscription->update([
                        'status' => 'active',
                        'current_period_start' => $stripePeriodStart,
                        'current_period_end' => $stripePeriodEnd,
                        'next_billing_date' => $stripePeriodEnd,
                    ]);
                    $this->info("Subscription {$subscription->id} synced from Stripe period dates");
                    Log::info('Subscription synced from Stripe in daily cron', [
                        'subscription_id' => $subscription->id,
                        'stripe_subscription_id' => $subscription->stripe_subscription_id,
                        'old_local_period_end' => $localPeriodEnd?->format('Y-m-d'),
                        'new_period_end' => $stripePeriodEnd->format('Y-m-d'),
                    ]);
                    $this->markLatestInvoicePaidIfRenewed($subscription);
                    return;
                }

                if ($subscription->status === 'inactive') {
                    $subscription->update(['status' => 'active']);
                    $this->markLatestInvoicePaidIfRenewed($subscription);
                    Log::info('Inactive subscription re-activated from Stripe by daily cron', [
                        'subscription_id' => $subscription->id,
                        'stripe_subscription_id' => $subscription->stripe_subscription_id,
                        'stripe_period_end' => $stripePeriodEnd->format('Y-m-d'),
                    ]);
                }

                $this->info("Subscription {$subscription->id} is already up to date in Stripe");
                return;
            }

            // From here: Stripe has no future billing period (renewal did not land in Stripe).
            // If our billing period end date has passed, user must be inactive/unpaid — do not wait on
            // open/upcoming invoices (those early returns previously blocked this forever).
            if ($localPeriodEnd && $localPeriodEnd->lt($today)) {
                $this->markInactiveAndUnpaid($subscription, 'period_end_passed_no_renewal');
                $this->warn("Subscription {$subscription->id} period ended (local {$localPeriodEnd->format('Y-m-d')}) without Stripe renewal. Marked inactive/unpaid.");
                return;
            }

            $this->info("Subscription {$subscription->id} is active and will be handled by Stripe automatically");
            Log::info("Subscription {$subscription->id} processed - Stripe will handle billing automatically", [
                'stripe_status' => $stripeSub->status,
                'stripe_period_end' => $stripePeriodEnd?->format('Y-m-d'),
                'local_period_end' => $subscription->current_period_end ? Carbon::parse($subscription->current_period_end)->format('Y-m-d') : 'N/A',
            ]);
            
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'No such subscription')) {
                $this->markInactiveAndUnpaid($subscription, 'stripe_subscription_missing');
                Log::warning("Stripe subscription missing for local subscription {$subscription->id}. Marked inactive/unpaid.");
                $this->warn("Subscription {$subscription->id} Stripe subscription missing. Marked inactive/unpaid.");
                return;
            }

            Log::error("Failed to process subscription {$subscription->id}: " . $e->getMessage());
            $this->error("Failed to process subscription: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * If DB row is missing stripe_subscription_id but we have stripe_customer_id, pick the best open subscription from Stripe.
     */
    protected function tryLinkStripeSubscriptionFromCustomer(SubscriptionRecord $subscription): void
    {
        if ($subscription->stripe_subscription_id || ! $subscription->stripe_customer_id) {
            return;
        }

        try {
            $page = \Stripe\Subscription::all([
                'customer' => $subscription->stripe_customer_id,
                'limit' => 20,
            ]);

            $best = null;
            foreach ($page->autoPagingIterator() as $stripeSubscription) {
                if (in_array($stripeSubscription->status, ['active', 'trialing', 'past_due'], true)) {
                    $best = $stripeSubscription;
                    break;
                }
            }

            if ($best) {
                $subscription->update(['stripe_subscription_id' => $best->id]);
                $subscription->refresh();
                Log::info('Linked stripe_subscription_id from Stripe customer in daily cron', [
                    'subscription_record_id' => $subscription->id,
                    'stripe_customer_id' => $subscription->stripe_customer_id,
                    'stripe_subscription_id' => $best->id,
                    'stripe_status' => $best->status,
                ]);
                $this->info("Linked Stripe subscription {$best->id} from customer {$subscription->stripe_customer_id}");
            }
        } catch (\Throwable $e) {
            Log::warning('Could not list Stripe subscriptions for customer', [
                'subscription_record_id' => $subscription->id,
                'stripe_customer_id' => $subscription->stripe_customer_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function handleNoStripeSubscriptionAfterPeriodEnd(SubscriptionRecord $subscription): void
    {
        if ($subscription->current_period_end && Carbon::parse($subscription->current_period_end)->isPast()) {
            $this->markInactiveAndUnpaid($subscription, 'no_stripe_subscription_period_ended');
            $this->warn("Subscription {$subscription->id} has no Stripe subscription and period ended. Marked inactive/unpaid.");
        }
    }

    protected function markInactiveAndUnpaid(SubscriptionRecord $subscription, string $reason): void
    {
        if ($subscription->status !== 'inactive') {
            $subscription->update(['status' => 'inactive']);
        }

        $latestInvoice = BillingInvoice::where('subscription_id', $subscription->id)
            ->orderByDesc('created_at')
            ->first();

        // Keep historical paid invoice intact.
        // Ensure there is an unpaid invoice for the current cycle so UI shows "Unpaid".
        $needsCurrentUnpaidInvoice = !$latestInvoice || $latestInvoice->status === 'paid';
        if ($needsCurrentUnpaidInvoice) {
            $today = Carbon::today()->toDateString();
            $existingTodayInvoice = BillingInvoice::where('subscription_id', $subscription->id)
                ->whereDate('invoice_date', $today)
                ->first();

            if (!$existingTodayInvoice) {
                $pricePerUser = $this->getTierPrice($subscription->tier);
                $userCount = max(1, (int) ($subscription->user_count ?? 1));
                $subtotal = $pricePerUser * $userCount;
                $taxAmount = round($subtotal * 0.20, 2);
                $totalAmount = round($subtotal + $taxAmount, 2);

                $latestInvoice = BillingInvoice::create([
                    'subscription_id' => $subscription->id,
                    'organisation_id' => $subscription->organisation_id,
                    'user_id' => $subscription->user_id,
                    'tier' => $subscription->tier,
                    'invoice_number' => BillingInvoice::generateInvoiceNumber(),
                    'invoice_date' => $today,
                    'due_date' => Carbon::today()->addDays(7)->toDateString(),
                    'user_count' => $userCount,
                    'price_per_user' => $pricePerUser,
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                    'status' => 'unpaid',
                    'notes' => 'Auto-generated by daily billing cron after period end without successful renewal payment.',
                ]);
            } else {
                $latestInvoice = $existingTodayInvoice;
                if ($latestInvoice->status !== 'paid' && $latestInvoice->status !== 'unpaid') {
                    $latestInvoice->update(['status' => 'unpaid']);
                }
            }
        } elseif ($latestInvoice->status !== 'unpaid') {
            $latestInvoice->update(['status' => 'unpaid']);
        }

        Log::info('Subscription marked inactive/unpaid by daily cron', [
            'subscription_id' => $subscription->id,
            'reason' => $reason,
            'invoice_id' => $latestInvoice?->id,
            'invoice_status' => $latestInvoice?->status,
        ]);
    }

    protected function getTierPrice(?string $tier): float
    {
        $prices = [
            'spark' => 10.00,
            'momentum' => 20.00,
            'vision' => 30.00,
            'basecamp' => 10.00,
        ];

        return $prices[strtolower((string) $tier)] ?? 10.00;
    }

    protected function ensureUnpaidInvoiceForInactive(SubscriptionRecord $subscription): void
    {
        $latestInvoice = BillingInvoice::where('subscription_id', $subscription->id)
            ->orderByDesc('created_at')
            ->first();

        if (!$latestInvoice || $latestInvoice->status === 'paid') {
            $this->markInactiveAndUnpaid($subscription, 'inactive_backfill_unpaid_invoice');
            $this->info("Backfilled unpaid invoice for inactive subscription {$subscription->id}");
            return;
        }

        if ($latestInvoice->status !== 'unpaid') {
            $latestInvoice->update(['status' => 'unpaid']);
        }
    }

    /**
     * When Stripe already renewed but webhook failed, local latest invoice often stays unpaid.
     * Mark it paid so Admin payment status reflects Stripe truth.
     */
    protected function markLatestInvoicePaidIfRenewed(SubscriptionRecord $subscription): void
    {
        $latestInvoice = BillingInvoice::where(function ($q) use ($subscription) {
            if ($subscription->user_id) {
                $q->where('user_id', $subscription->user_id)->where('tier', 'basecamp');
            } else {
                $q->where('subscription_id', $subscription->id);
            }
        })->orderByDesc('created_at')->first();

        if (! $latestInvoice) {
            return;
        }

        if (in_array((string) $latestInvoice->status, ['pending', 'unpaid', 'overdue'], true)) {
            $latestInvoice->update([
                'status' => 'paid',
                'paid_date' => Carbon::today()->toDateString(),
            ]);
        }

        $existingPayment = Payment::where('invoice_id', $latestInvoice->id)
            ->whereIn('status', ['completed', 'approved'])
            ->exists();

        if (! $existingPayment) {
            Payment::create([
                'invoice_id' => $latestInvoice->id,
                'organisation_id' => $subscription->organisation_id,
                'user_id' => $subscription->user_id,
                'payment_method' => 'card',
                'amount' => $latestInvoice->total_amount,
                'transaction_id' => $subscription->stripe_subscription_id ?? ('cron-sync-' . $subscription->id . '-' . now()->timestamp),
                'status' => 'completed',
                'payment_date' => Carbon::today()->toDateString(),
                'payment_notes' => 'Auto-reconciled by billing:process-daily after Stripe renewal detected.',
            ]);
        }
    }
}

