<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SubscriptionRecord;
use App\Services\Billing\StripeService;
use App\Models\PaymentRecord;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessMonthlyBilling extends Command
{
    protected $signature = 'billing:process-monthly';
    protected $description = 'Process monthly recurring billing for all active subscriptions';

    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        parent::__construct();
        $this->stripeService = $stripeService;
    }

    public function handle()
    {
        $this->info('Starting monthly billing process...');
        Log::info('Monthly billing cron job started');

        // Get all active subscriptions
        $subscriptions = SubscriptionRecord::where('status', 'active')
            ->whereDate('next_billing_date', '<=', now())
            ->get();

        $this->info("Found {$subscriptions->count()} subscriptions to process");

        foreach ($subscriptions as $subscription) {
            try {
                $this->processSubscription($subscription);
            } catch (\Exception $e) {
                Log::error("Failed to process subscription {$subscription->id}: " . $e->getMessage());
                $this->error("Failed to process subscription {$subscription->id}: " . $e->getMessage());
            }
        }

        $this->info('Monthly billing process completed');
        Log::info('Monthly billing cron job completed');
    }

    protected function processSubscription(SubscriptionRecord $subscription)
    {
        $this->info("Processing subscription ID: {$subscription->id}");

        // Calculate monthly amount
        $monthlyPrice = $this->getTierPrice($subscription->tier);
        $totalAmount = $monthlyPrice * $subscription->user_count;

        // Check for credits/adjustments (if implemented)
        // $credits = $this->getCreditsForSubscription($subscription);
        // $finalAmount = $totalAmount - $credits;

        // Check if invoice already exists for this billing period to prevent duplicates
        $existingInvoice = Invoice::where('subscription_id', $subscription->id)
            ->where('invoice_date', '>=', now()->startOfMonth())
            ->where('invoice_date', '<=', now()->endOfMonth())
            ->where('status', '!=', 'cancelled')
            ->first();
            
        if ($existingInvoice) {
            Log::info("Invoice already exists for subscription {$subscription->id} this month - Invoice ID: {$existingInvoice->id}");
            $this->info("Invoice already exists for subscription {$subscription->id} this month");
            return;
        }

        // Generate invoice
        $invoice = Invoice::create([
            'subscription_id' => $subscription->id,
            'organisation_id' => $subscription->organisation_id,
            'user_id' => $subscription->user_id, // For basecamp users
            'tier' => $subscription->tier, // Include tier for basecamp users
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'invoice_date' => now(),
            'due_date' => now()->addDays(7),
            'user_count' => $subscription->user_count,
            'price_per_user' => $monthlyPrice,
            'subtotal' => $totalAmount,
            'tax_amount' => $totalAmount * 0.20, // UK VAT 20%
            'total_amount' => $totalAmount * 1.20,
            'status' => 'pending',
        ]);

        // Process payment based on payment gateway (Stripe only)
        if ($subscription->stripe_subscription_id) {
            $this->processStripePayment($subscription, $invoice);
        } else {
            // Manual payment required
            $this->sendManualPaymentRequest($subscription, $invoice);
        }

        // Update next billing date
        $subscription->update([
            'next_billing_date' => now()->addMonth()->startOfMonth(),
        ]);
    }

    protected function processStripePayment(SubscriptionRecord $subscription, Invoice $invoice)
    {
        // Stripe handles recurring billing automatically via webhooks
        // This is mainly for logging and tracking
        Log::info("Stripe subscription {$subscription->stripe_subscription_id} will be billed automatically");
        $this->info("Stripe subscription will be billed automatically via webhook");
    }

    protected function processPayPalPayment(SubscriptionRecord $subscription, Invoice $invoice)
    {
        // PayPal handles recurring billing automatically via webhooks
        // This is mainly for logging and tracking
        Log::info("PayPal subscription {$subscription->paypal_subscription_id} will be billed automatically");
        $this->info("PayPal subscription will be billed automatically via webhook");
    }

    protected function sendManualPaymentRequest(SubscriptionRecord $subscription, Invoice $invoice)
    {
        // TODO: Send email notification for manual payment
        Log::info("Manual payment required for subscription {$subscription->id}, invoice {$invoice->id}");
        $this->warn("Manual payment required for subscription {$subscription->id}");
    }

    protected function getTierPrice($tier)
    {
        $prices = [
            'spark' => 10, // £10 per user/month
            'momentum' => 20, // £20 per user/month
            'vision' => 30, // £30 per user/month
        ];

        return $prices[$tier] ?? 0;
    }
}

