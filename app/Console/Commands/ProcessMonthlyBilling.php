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

        // Calculate monthly amount - basecamp users have fixed $10/month
        if ($subscription->tier === 'basecamp') {
            $monthlyPrice = 10.00; // Fixed $10 for basecamp
            $totalAmount = $monthlyPrice; // Basecamp is single user, no user_count multiplier
        } else {
            $monthlyPrice = $this->getTierPrice($subscription->tier);
            $totalAmount = $monthlyPrice * $subscription->user_count;
        }

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
        $invoiceData = [
            'subscription_id' => $subscription->id,
            'organisation_id' => $subscription->organisation_id,
            'user_id' => $subscription->user_id, // For basecamp users
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'invoice_date' => now(),
            'due_date' => now()->addDays(7),
            'tier' => $subscription->tier,
            'user_count' => $subscription->tier === 'basecamp' ? 1 : $subscription->user_count,
            'price_per_user' => $monthlyPrice,
            'subtotal' => $totalAmount,
            'tax_amount' => 0, // No tax for basecamp (or adjust as needed)
            'total_amount' => $totalAmount,
            'status' => 'unpaid', // Changed from 'pending' to 'unpaid'
        ];

        $invoice = Invoice::create($invoiceData);

        // Process payment based on payment gateway (Stripe only)
        if ($subscription->stripe_subscription_id) {
            $this->processStripePayment($subscription, $invoice);
        } elseif ($subscription->tier === 'basecamp' && $subscription->user_id) {
            // For basecamp users, try to charge payment method directly
            $this->processBasecampPayment($subscription, $invoice);
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

    protected function processBasecampPayment(SubscriptionRecord $subscription, Invoice $invoice)
    {
        try {
            $user = \App\Models\User::find($subscription->user_id);
            if (!$user || !$user->stripe_customer_id) {
                Log::warning("Basecamp user {$user->id} has no Stripe customer ID");
                return;
            }

            // Get default payment method
            $paymentMethod = $this->stripeService->getDefaultPaymentMethod($user->stripe_customer_id);
            
            if ($paymentMethod) {
                // Create payment intent and charge
                $paymentIntent = $this->stripeService->createPaymentIntent([
                    'amount' => $invoice->total_amount * 100,
                    'currency' => 'usd',
                    'customer' => $user->stripe_customer_id,
                    'payment_method' => $paymentMethod->id,
                    'confirm' => true,
                    'description' => "Monthly subscription for invoice {$invoice->invoice_number}",
                    'metadata' => [
                        'invoice_id' => $invoice->id,
                        'subscription_id' => $subscription->id,
                        'user_id' => $user->id,
                        'tier' => 'basecamp',
                    ],
                ]);

                if ($paymentIntent->status === 'succeeded') {
                    // Payment succeeded
                    $invoice->update([
                        'status' => 'paid',
                        'paid_date' => now(),
                    ]);

                    \App\Models\Payment::create([
                        'invoice_id' => $invoice->id,
                        'user_id' => $user->id,
                        'payment_method' => 'stripe',
                        'amount' => $invoice->total_amount,
                        'transaction_id' => $paymentIntent->id,
                        'status' => 'completed',
                        'payment_date' => now(),
                    ]);

                    $subscription->update([
                        'status' => 'active',
                        'payment_failed_count' => 0,
                        'last_payment_date' => now(),
                    ]);

                    $user->update([
                        'payment_grace_period_start' => null,
                        'last_payment_failure_date' => null,
                    ]);

                    Log::info("Basecamp payment succeeded for subscription {$subscription->id}, invoice {$invoice->id}");
                } else {
                    // Payment failed
                    $this->handleBasecampPaymentFailure($subscription, $invoice, $paymentIntent);
                }
            } else {
                // No payment method - create failure log
                $this->handleBasecampPaymentFailure($subscription, $invoice, null);
            }
        } catch (\Exception $e) {
            Log::error("Failed to process basecamp payment: " . $e->getMessage());
            $this->handleBasecampPaymentFailure($subscription, $invoice, null);
        }
    }

    protected function handleBasecampPaymentFailure(SubscriptionRecord $subscription, Invoice $invoice, $paymentIntent = null)
    {
        // Create payment failure log
        \App\Models\PaymentFailureLog::create([
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'payment_method' => 'stripe',
            'transaction_id' => $paymentIntent?->id,
            'amount' => $invoice->total_amount,
            'currency' => 'usd',
            'failure_reason' => $paymentIntent ? 'payment_declined' : 'no_payment_method',
            'failure_message' => $paymentIntent?->last_payment_error->message ?? 'No payment method on file',
            'retry_attempt' => 1,
            'failure_date' => now(),
            'status' => 'pending_retry',
        ]);

        // Update subscription
        $subscription->update([
            'status' => 'past_due',
            'payment_failed_count' => ($subscription->payment_failed_count ?? 0) + 1,
        ]);

        // Update user
        if ($subscription->user_id) {
            $user = \App\Models\User::find($subscription->user_id);
            if ($user) {
                $user->update([
                    'last_payment_failure_date' => now(),
                ]);

                // If 3rd failure, start grace period
                if ($subscription->payment_failed_count >= 3 && !$user->payment_grace_period_start) {
                    $user->update([
                        'payment_grace_period_start' => now(),
                        'status' => 'suspended',
                    ]);
                }
            }
        }

        Log::warning("Basecamp payment failed for subscription {$subscription->id}, invoice {$invoice->id}");
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

