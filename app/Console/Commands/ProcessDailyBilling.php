<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SubscriptionRecord;
use App\Services\Billing\StripeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Invoice;
use Stripe\InvoiceItem;

class ProcessDailyBilling extends Command
{
    protected $signature = 'billing:process-daily';
    protected $description = 'Process monthly subscriptions daily - checks for expired/due subscriptions and processes auto-renewal';

    public function handle()
    {
        $this->info('Starting daily subscription check process...');
        Log::info('Daily subscription check cron job started');

        Stripe::setApiKey(config('services.stripe.secret'));

        // Get all active subscriptions (monthly) that are due for billing
        $subscriptions = SubscriptionRecord::where('status', 'active')
            ->whereNotNull('stripe_subscription_id')
            ->where(function($query) {
                $query->whereDate('next_billing_date', '<=', Carbon::today())
                      ->orWhereDate('current_period_end', '<=', Carbon::today());
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
            // Retrieve Stripe subscription to check status
            $stripeSub = \Stripe\Subscription::retrieve($subscription->stripe_subscription_id);
            
            // Check if subscription is already handled by Stripe (active and in sync)
            if ($stripeSub->status === 'active') {
                // Check if Stripe has already processed the billing cycle
                $stripePeriodEnd = Carbon::createFromTimestamp($stripeSub->current_period_end);
                $localPeriodEnd = $subscription->current_period_end ? Carbon::parse($subscription->current_period_end) : null;
                
                // If Stripe period end is in the future and matches our local record, subscription is already renewed
                if ($stripePeriodEnd->isFuture() && $localPeriodEnd && $stripePeriodEnd->isSameDay($localPeriodEnd)) {
                    $this->info("Subscription {$subscription->id} is already up to date in Stripe");
                    return;
                }
                
                // If subscription period has ended in Stripe, let Stripe handle the invoice
                // Stripe will automatically create and charge invoices for active subscriptions
                if ($stripePeriodEnd->isPast()) {
                    $this->info("Subscription {$subscription->id} period has ended in Stripe, checking for pending invoices...");
                    
                    // Check if there's a pending invoice in Stripe
                    $invoices = Invoice::all([
                        'subscription' => $subscription->stripe_subscription_id,
                        'status' => 'open',
                        'limit' => 1,
                    ]);
                    
                    if (count($invoices->data) > 0) {
                        $this->info("Found pending invoice for subscription {$subscription->id}, Stripe will process it automatically");
                        return;
                    }
                    
                    // Check for upcoming invoices
                    $upcomingInvoices = Invoice::upcoming([
                        'subscription' => $subscription->stripe_subscription_id,
                    ]);
                    
                    if ($upcomingInvoices) {
                        $this->info("Upcoming invoice exists for subscription {$subscription->id}, Stripe will process it on {$upcomingInvoices->period_end}");
                        return;
                    }
                }
            }
            
            // If subscription is in a state that needs manual intervention, log it
            if (in_array($stripeSub->status, ['past_due', 'unpaid', 'canceled'])) {
                Log::warning("Subscription {$subscription->id} is in {$stripeSub->status} status, manual intervention may be required");
                $this->warn("Subscription {$subscription->id} is in {$stripeSub->status} status");
                return;
            }
            
            $this->info("Subscription {$subscription->id} is active and will be handled by Stripe automatically");
            Log::info("Subscription {$subscription->id} processed - Stripe will handle billing automatically", [
                'stripe_status' => $stripeSub->status,
                'stripe_period_end' => Carbon::createFromTimestamp($stripeSub->current_period_end)->format('Y-m-d'),
                'local_period_end' => $subscription->current_period_end ? Carbon::parse($subscription->current_period_end)->format('Y-m-d') : 'N/A',
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to process subscription {$subscription->id}: " . $e->getMessage());
            $this->error("Failed to process subscription: " . $e->getMessage());
            throw $e;
        }
    }
}

