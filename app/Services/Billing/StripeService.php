<?php

namespace App\Services\Billing;

use App\Models\Organisation;
use App\Models\SubscriptionRecord;
use App\Models\PaymentRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class StripeService
{
    protected $stripeSecretKey;
    protected $taxRateId;
    protected $stripe;

    public function __construct()
    {
        $this->stripeSecretKey = config('services.stripe.secret');
        $this->taxRateId = config('services.stripe.tax_rate_id');
        
        // Initialize Stripe if package is available
        if (class_exists(\Stripe\Stripe::class) && $this->stripeSecretKey) {
            \Stripe\Stripe::setApiKey($this->stripeSecretKey);
            // Don't set API version - let Stripe use the default/latest
            $this->stripe = new \Stripe\StripeClient($this->stripeSecretKey);
        }
    }

    /**
     * Create a Stripe customer for an organisation
     */
    public function createCustomer(Organisation $organisation)
    {
        try {
            if (!class_exists(\Stripe\Customer::class)) {
                throw new \Exception('Stripe PHP package is not installed. Please run: composer require stripe/stripe-php');
            }

            // Get customer email - prefer logged in user's email
            $user = \Illuminate\Support\Facades\Auth::user();
            $customerEmail = $user->email ?? $organisation->admin_email ?? $organisation->users()->first()?->email;
            $customerName = $user->name ?? $organisation->name;
            
            $customer = \Stripe\Customer::create([
                'email' => $customerEmail,
                'name' => $customerName,
                'description' => "Organisation ID: {$organisation->id}",
                'metadata' => [
                    'organisation_id' => $organisation->id,
                    'tier' => $organisation->subscription_tier ?? 'basecamp',
                    'user_count' => $organisation->users()->count(),
                    'user_id' => $user->id ?? null,
                ],
                'address' => [
                    'line1' => $organisation->billing_address_line1,
                    'line2' => $organisation->billing_address_line2,
                    'city' => $organisation->billing_city,
                    'postal_code' => $organisation->billing_postcode,
                    'country' => $organisation->billing_country ?? 'GB',
                ],
            ]);

            // Save Stripe customer ID to database
            $organisation->update([
                'stripe_customer_id' => $customer->id
            ]);

            return [
                'success' => true,
                'customer' => $customer,
            ];
        } catch (\Exception $e) {
            Log::error('Stripe Customer Creation Failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create or retrieve Stripe product for Tribe365 tiers
     */
    public function createProduct($tierName, $description)
    {
        try {
            if (!class_exists(\Stripe\Product::class)) {
                throw new \Exception('Stripe PHP package is not installed.');
            }

            // Check if product already exists
            $products = \Stripe\Product::all(['limit' => 100]);
            foreach ($products->data as $product) {
                if ($product->name === "Tribe365 {$tierName}") {
                    return $product;
                }
            }

            // Create new product
            $product = \Stripe\Product::create([
                'name' => "Tribe365 {$tierName}",
                'description' => $description,
                'metadata' => [
                    'tier' => $tierName,
                ],
            ]);

            return $product;
        } catch (\Exception $e) {
            Log::error('Stripe Product Creation Failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a price for a product (tier pricing)
     */
    public function createPrice($productId, $unitAmount, $tierName)
    {
        try {
            if (!class_exists(\Stripe\Price::class)) {
                throw new \Exception('Stripe PHP package is not installed.');
            }

            $price = \Stripe\Price::create([
                'product' => $productId,
                'unit_amount' => $unitAmount * 100, // Convert $ to cents
                'currency' => 'usd',
                'recurring' => [
                    'interval' => 'month',
                ],
                'metadata' => [
                    'tier' => $tierName,
                ],
            ]);

            return $price;
        } catch (\Exception $e) {
            Log::error('Stripe Price Creation Failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a subscription for an organisation
     */
    public function createSubscription(Organisation $organisation, $priceId, $userCount)
    {
        try {
            if (!class_exists(\Stripe\Subscription::class)) {
                throw new \Exception('Stripe PHP package is not installed.');
            }

            // Ensure customer exists
            if (!$organisation->stripe_customer_id) {
                $customerResult = $this->createCustomer($organisation);
                if (!$customerResult['success']) {
                    throw new \Exception('Failed to create Stripe customer');
                }
            }

            // Create subscription
            $subscription = \Stripe\Subscription::create([
                'customer' => $organisation->stripe_customer_id,
                'items' => [
                    [
                        'price' => $priceId,
                        'quantity' => $userCount,
                    ],
                ],
                'default_tax_rates' => $this->taxRateId ? [$this->taxRateId] : [],
                'metadata' => [
                    'organisation_id' => $organisation->id,
                    'tier' => $organisation->subscription_tier ?? 'basecamp',
                    'user_count' => $userCount,
                ],
                'billing_cycle_anchor_config' => [
                    'day_of_month' => 1, // Bill on 1st of each month
                ],
                'proration_behavior' => 'create_prorations',
                'collection_method' => 'charge_automatically',
            ]);

            // Save subscription to database
            SubscriptionRecord::create([
                'organisation_id' => $organisation->id,
                'stripe_subscription_id' => $subscription->id,
                'stripe_customer_id' => $organisation->stripe_customer_id,
                'tier' => $organisation->subscription_tier ?? 'basecamp',
                'user_count' => $userCount,
                'status' => $subscription->status,
                'current_period_start' => Carbon::createFromTimestamp($subscription->current_period_start),
                'current_period_end' => Carbon::createFromTimestamp($subscription->current_period_end),
                'next_billing_date' => Carbon::createFromTimestamp($subscription->current_period_end),
                'activated_at' => now(),
            ]);

            return [
                'success' => true,
                'subscription' => $subscription,
            ];
        } catch (\Exception $e) {
            Log::error('Stripe Subscription Creation Failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update subscription quantity (for user count changes)
     */
    public function updateSubscriptionQuantity($subscriptionId, $newUserCount, $prorationDate = null)
    {
        try {
            if (!class_exists(\Stripe\Subscription::class)) {
                throw new \Exception('Stripe PHP package is not installed.');
            }

            $subscription = \Stripe\Subscription::retrieve($subscriptionId);
            $updateParams = [
                'items' => [
                    [
                        'id' => $subscription->items->data[0]->id,
                        'quantity' => $newUserCount,
                    ],
                ],
                'proration_behavior' => 'create_prorations',
                'metadata' => [
                    'user_count' => $newUserCount,
                    'updated_at' => now()->toIso8601String(),
                ],
            ];

            // Set proration date if provided (for mid-month changes)
            if ($prorationDate) {
                $updateParams['proration_date'] = $prorationDate->timestamp;
            }

            $updatedSubscription = $subscription->update($updateParams);

            // Update database
            SubscriptionRecord::where('stripe_subscription_id', $subscriptionId)
                ->update([
                    'user_count' => $newUserCount,
                    'updated_at' => now(),
                ]);

            return [
                'success' => true,
                'subscription' => $updatedSubscription,
            ];
        } catch (\Exception $e) {
            Log::error('Stripe Subscription Update Failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate pro-rata charge for user addition
     */
    public function calculateProRataAddition($monthlyPrice, $daysRemaining, $daysInMonth)
    {
        $dailyRate = $monthlyPrice / $daysInMonth;
        $proRataAmount = $dailyRate * $daysRemaining;
        return [
            'daily_rate' => round($dailyRate, 2),
            'days_remaining' => $daysRemaining,
            'pro_rata_amount' => round($proRataAmount, 2),
        ];
    }

    /**
     * Calculate pro-rata credit for user removal
     */
    public function calculateProRataRemoval($monthlyPrice, $daysActive, $daysInMonth)
    {
        $dailyRate = $monthlyPrice / $daysInMonth;
        $actualCharge = $dailyRate * $daysActive;
        $creditAmount = $monthlyPrice - $actualCharge;
        return [
            'daily_rate' => round($dailyRate, 2),
            'days_active' => $daysActive,
            'actual_charge' => round($actualCharge, 2),
            'credit_amount' => round($creditAmount, 2),
        ];
    }

    /**
     * Create an invoice item (for one-time charges)
     */
    public function createInvoiceItem($customerId, $amount, $description, $metadata = [])
    {
        try {
            if (!class_exists(\Stripe\InvoiceItem::class)) {
                throw new \Exception('Stripe PHP package is not installed.');
            }

            $invoiceItem = \Stripe\InvoiceItem::create([
                'customer' => $customerId,
                'amount' => $amount * 100, // Convert $ to cents
                'currency' => 'usd',
                'description' => $description,
                'metadata' => $metadata,
            ]);

            return [
                'success' => true,
                'invoice_item' => $invoiceItem,
            ];
        } catch (\Exception $e) {
            Log::error('Stripe Invoice Item Creation Failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create and finalize an invoice
     */
    public function createInvoice($customerId, $autoAdvance = true)
    {
        try {
            if (!class_exists(\Stripe\Invoice::class)) {
                throw new \Exception('Stripe PHP package is not installed.');
            }

            $invoice = \Stripe\Invoice::create([
                'customer' => $customerId,
                'auto_advance' => $autoAdvance,
                'collection_method' => 'charge_automatically',
            ]);

            if ($autoAdvance) {
                $invoice->finalizeInvoice();
            }

            return [
                'success' => true,
                'invoice' => $invoice,
            ];
        } catch (\Exception $e) {
            Log::error('Stripe Invoice Creation Failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Retrieve an invoice
     */
    public function retrieveInvoice($invoiceId)
    {
        try {
            if (!class_exists(\Stripe\Invoice::class)) {
                throw new \Exception('Stripe PHP package is not installed.');
            }

            $invoice = \Stripe\Invoice::retrieve($invoiceId);
            return [
                'success' => true,
                'invoice' => $invoice,
            ];
        } catch (\Exception $e) {
            Log::error('Stripe Invoice Retrieval Failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process a refund
     */
    public function processRefund($paymentIntentId, $amount = null, $reason = 'requested_by_customer')
    {
        try {
            if (!class_exists(\Stripe\Refund::class)) {
                throw new \Exception('Stripe PHP package is not installed.');
            }

            $refundParams = [
                'payment_intent' => $paymentIntentId,
                'reason' => $reason,
            ];

            // Partial refund if amount specified
            if ($amount !== null) {
                $refundParams['amount'] = $amount * 100; // Convert $ to cents
            }

            $refund = \Stripe\Refund::create($refundParams);

            // Log refund in database
            PaymentRecord::create([
                'stripe_refund_id' => $refund->id,
                'stripe_payment_intent_id' => $paymentIntentId,
                'amount' => $refund->amount / 100,
                'currency' => $refund->currency,
                'status' => $refund->status,
                'type' => 'refund',
                'refunded_at' => now(),
            ]);

            return [
                'success' => true,
                'refund' => $refund,
            ];
        } catch (\Exception $e) {
            Log::error('Stripe Refund Failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription($subscriptionId, $cancelAtPeriodEnd = true)
    {
        try {
            if (!class_exists(\Stripe\Subscription::class)) {
                throw new \Exception('Stripe PHP package is not installed.');
            }

            $subscription = \Stripe\Subscription::retrieve($subscriptionId);
            if ($cancelAtPeriodEnd) {
                $subscription->cancel(['at_period_end' => true]);
            } else {
                $subscription->cancel();
            }

            // Update database
            SubscriptionRecord::where('stripe_subscription_id', $subscriptionId)
                ->update([
                    'status' => $cancelAtPeriodEnd ? 'cancel_at_period_end' : 'canceled',
                    'canceled_at' => now(),
                ]);

            return [
                'success' => true,
                'subscription' => $subscription,
            ];
        } catch (\Exception $e) {
            Log::error('Stripe Subscription Cancellation Failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature($payload, $signature)
    {
        try {
            if (!class_exists(\Stripe\Webhook::class)) {
                throw new \Exception('Stripe PHP package is not installed.');
            }

            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                config('services.stripe.webhook_secret')
            );

            return [
                'success' => true,
                'event' => $event,
            ];
        } catch (\Exception $e) {
            Log::error('Stripe Webhook Verification Failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get default payment method for a customer
     */
    public function getDefaultPaymentMethod($customerId)
    {
        try {
            if (!class_exists(\Stripe\Customer::class)) {
                throw new \Exception('Stripe PHP package is not installed.');
            }

            $customer = \Stripe\Customer::retrieve($customerId);
            
            if ($customer->invoice_settings->default_payment_method) {
                $paymentMethod = \Stripe\PaymentMethod::retrieve($customer->invoice_settings->default_payment_method);
                return $paymentMethod;
            }

            // Try to get the first payment method
            $paymentMethods = \Stripe\PaymentMethod::all([
                'customer' => $customerId,
                'type' => 'card',
            ]);

            if (count($paymentMethods->data) > 0) {
                return $paymentMethods->data[0];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Stripe Get Default Payment Method Failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a payment intent
     */
    public function createPaymentIntent(array $params)
    {
        try {
            if (!class_exists(\Stripe\PaymentIntent::class)) {
                throw new \Exception('Stripe PHP package is not installed.');
            }

            $paymentIntent = \Stripe\PaymentIntent::create($params);
            return $paymentIntent;
        } catch (\Exception $e) {
            Log::error('Stripe Payment Intent Creation Failed: ' . $e->getMessage());
            throw $e;
        }
    }
}

