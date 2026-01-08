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
                'currency' => 'gbp',
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
                'currency' => 'gbp',
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
     * 
     * @param string $subscriptionId Stripe subscription ID
     * @param bool $cancelAtPeriodEnd If true, cancels at period end. If false, cancels immediately.
     * 
     * When cancelAtPeriodEnd = false (immediate cancellation):
     * - Uses Stripe API: DELETE /v1/subscriptions/{id}
     * - Stops ALL future payments immediately
     * - No future invoices will be created
     * - Subscription status becomes "canceled"
     * - Cannot be reactivated (requires new subscription)
     * 
     * When cancelAtPeriodEnd = true:
     * - Uses Stripe API: POST /v1/subscriptions/{id} with cancel_at_period_end=true
     * - Continues until current period ends
     * - Final payment will be made at period end
     * - Can be reactivated before period ends
     */
    public function cancelSubscription($subscriptionId, $cancelAtPeriodEnd = true)
    {
        try {
            if (!class_exists(\Stripe\Subscription::class)) {
                throw new \Exception('Stripe PHP package is not installed.');
            }

            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            
            // First check if subscription exists in Stripe
            $stripeSubscription = null;
            $subscriptionExistsInStripe = false;
            try {
                $stripeSubscription = \Stripe\Subscription::retrieve($subscriptionId);
                $subscriptionExistsInStripe = true;
                Log::info('Stripe subscription found', [
                    'subscription_id' => $subscriptionId,
                    'stripe_status' => $stripeSubscription->status
                ]);
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                // Subscription doesn't exist in Stripe - this is OK if it was already cancelled
                Log::info('Stripe subscription not found - will update database only', [
                    'subscription_id' => $subscriptionId,
                    'error' => $e->getMessage()
                ]);
            } catch (\Exception $e) {
                Log::warning('Error checking Stripe subscription existence', [
                    'subscription_id' => $subscriptionId,
                    'error' => $e->getMessage()
                ]);
            }
            
            if ($subscriptionExistsInStripe && $stripeSubscription) {
                // Subscription exists in Stripe - cancel it
                if ($cancelAtPeriodEnd) {
                    // Cancel at the end of the billing period
                    // API: POST /v1/subscriptions/{id} with cancel_at_period_end=true
                    $subscription = \Stripe\Subscription::update($subscriptionId, [
                        'cancel_at_period_end' => true
                    ]);
                    Log::info('Subscription scheduled for cancellation at period end in Stripe', [
                        'subscription_id' => $subscriptionId,
                        'period_end' => $subscription->current_period_end
                    ]);
                } else {
                    // Cancel immediately - stops all future payments
                    // API: DELETE /v1/subscriptions/{id}
                    // This permanently cancels the subscription and prevents all future charges
                    $canceledSubscription = $stripeSubscription->cancel();
                    
                    Log::info('Subscription cancelled immediately in Stripe - all future payments stopped', [
                        'subscription_id' => $subscriptionId,
                        'status' => $canceledSubscription->status,
                        'canceled_at' => $canceledSubscription->canceled_at
                    ]);
                    $subscription = $canceledSubscription;
                }
            } else {
                // Subscription doesn't exist in Stripe - just update database
                Log::info('Stripe subscription does not exist - updating database only', [
                    'subscription_id' => $subscriptionId,
                    'cancel_at_period_end' => $cancelAtPeriodEnd
                ]);
                $subscription = null; // No Stripe subscription to return
            }

            // Update database regardless of Stripe status
            $updated = SubscriptionRecord::where('stripe_subscription_id', $subscriptionId)
                ->update([
                    'status' => $cancelAtPeriodEnd ? 'cancel_at_period_end' : 'canceled',
                    'canceled_at' => now(),
                ]);
            
            Log::info('Database updated for subscription cancellation', [
                'subscription_id' => $subscriptionId,
                'status' => $cancelAtPeriodEnd ? 'cancel_at_period_end' : 'canceled',
                'updated' => $updated
            ]);

            return [
                'success' => true,
                'subscription' => $subscription,
                'database_only' => !$subscriptionExistsInStripe, // Indicate if only database was updated
            ];
        } catch (\Exception $e) {
            Log::error('Stripe Subscription Cancellation Failed: ' . $e->getMessage(), [
                'subscription_id' => $subscriptionId,
                'cancel_at_period_end' => $cancelAtPeriodEnd,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Reactivate a cancelled subscription in Stripe
     * Only works if subscription was cancelled with cancel_at_period_end=true
     * If subscription was immediately cancelled (status='canceled'), creates a new subscription
     * Note: Database should be updated separately - this only handles Stripe side
     * 
     * @param string $subscriptionId The Stripe subscription ID
     * @param SubscriptionRecord|null $subscriptionRecord Optional subscription record for creating new subscription
     * @return array
     */
    public function reactivateSubscription($subscriptionId, $subscriptionRecord = null)
    {
        try {
            if (!class_exists(\Stripe\Subscription::class)) {
                // Stripe not available - return success anyway since database will be updated
                return [
                    'success' => true,
                    'message' => 'Stripe package not available, database will be updated separately'
                ];
            }

            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            
            // Retrieve the subscription
            $stripeSubscription = \Stripe\Subscription::retrieve($subscriptionId);
            
            // Check if subscription can be reactivated
            // If status is 'canceled' (immediate cancellation), we need to create a new subscription
            if ($stripeSubscription->status === 'canceled') {
                Log::info('Stripe subscription is immediately cancelled - creating new subscription', [
                    'old_subscription_id' => $subscriptionId,
                    'stripe_status' => $stripeSubscription->status
                ]);
                
                // Try to create a new subscription if we have the necessary information
                if ($subscriptionRecord && $stripeSubscription->customer) {
                    try {
                        // Get customer to find default payment method
                        $customer = \Stripe\Customer::retrieve($stripeSubscription->customer);
                        $paymentMethodId = null;
                        
                        // Try to get default payment method from multiple sources
                        $paymentMethodId = null;
                        
                        // First, try customer's invoice settings default payment method
                        if (isset($customer->invoice_settings) && isset($customer->invoice_settings->default_payment_method)) {
                            $paymentMethodId = $customer->invoice_settings->default_payment_method;
                            Log::info('Found payment method from customer invoice settings', [
                                'payment_method_id' => $paymentMethodId,
                                'customer_id' => $customer->id
                            ]);
                        }
                        
                        // Second, try the cancelled subscription's default payment method
                        if (!$paymentMethodId && isset($stripeSubscription->default_payment_method)) {
                            $paymentMethodId = $stripeSubscription->default_payment_method;
                            Log::info('Found payment method from cancelled subscription', [
                                'payment_method_id' => $paymentMethodId,
                                'subscription_id' => $subscriptionId
                            ]);
                        }
                        
                        // Third, try to get payment methods from customer
                        if (!$paymentMethodId) {
                            try {
                                $paymentMethods = \Stripe\PaymentMethod::all([
                                    'customer' => $customer->id,
                                    'type' => 'card',
                                ]);
                                if ($paymentMethods->data && count($paymentMethods->data) > 0) {
                                    $paymentMethodId = $paymentMethods->data[0]->id;
                                    Log::info('Found payment method from customer payment methods list', [
                                        'payment_method_id' => $paymentMethodId,
                                        'customer_id' => $customer->id,
                                        'total_methods' => count($paymentMethods->data)
                                    ]);
                                }
                            } catch (\Exception $e) {
                                Log::warning('Failed to retrieve payment methods from customer', [
                                    'customer_id' => $customer->id,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                        
                        // Fourth, try to get from subscription's payment intent or latest invoice
                        if (!$paymentMethodId) {
                            try {
                                // Get latest invoice from subscription
                                $invoices = \Stripe\Invoice::all([
                                    'subscription' => $subscriptionId,
                                    'limit' => 1,
                                ]);
                                if ($invoices->data && count($invoices->data) > 0) {
                                    $latestInvoice = $invoices->data[0];
                                    if (isset($latestInvoice->payment_intent)) {
                                        $paymentIntent = \Stripe\PaymentIntent::retrieve($latestInvoice->payment_intent);
                                        if (isset($paymentIntent->payment_method)) {
                                            $paymentMethodId = $paymentIntent->payment_method;
                                            Log::info('Found payment method from latest invoice payment intent', [
                                                'payment_method_id' => $paymentMethodId,
                                                'invoice_id' => $latestInvoice->id
                                            ]);
                                        }
                                    }
                                }
                            } catch (\Exception $e) {
                                Log::warning('Failed to retrieve payment method from invoice', [
                                    'subscription_id' => $subscriptionId,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                        
                        if (!$paymentMethodId) {
                            Log::error('No payment method found for customer - cannot create new subscription', [
                                'customer_id' => $customer->id,
                                'subscription_id' => $subscriptionRecord->id,
                                'subscription_record_id' => $subscriptionRecord->id,
                                'customer_email' => $customer->email ?? null,
                                'has_invoice_settings' => isset($customer->invoice_settings),
                                'has_default_payment_method' => isset($customer->invoice_settings->default_payment_method),
                                'old_subscription_default_pm' => $stripeSubscription->default_payment_method ?? null
                            ]);
                            return [
                                'success' => false,
                                'error' => 'No payment method found. Please add a payment method first.',
                                'message' => 'Stripe subscription was immediately cancelled - new subscription requires payment method. Please contact support to add a payment method.',
                                'debug_info' => [
                                    'customer_id' => $customer->id,
                                    'has_customer_invoice_settings' => isset($customer->invoice_settings),
                                ]
                            ];
                        }
                        
                        Log::info('Payment method found for reactivation', [
                            'payment_method_id' => $paymentMethodId,
                            'customer_id' => $customer->id,
                            'subscription_record_id' => $subscriptionRecord->id
                        ]);
                        
                        // Determine price based on tier
                        $amount = 1200; // Default: £12.00 (basecamp with VAT) in cents
                        if ($subscriptionRecord->tier === 'basecamp') {
                            $amount = 1200; // £12.00 with VAT (£10 + 20% VAT)
                        } else {
                            // For organisation subscriptions, calculate based on user count
                            $pricePerUser = 1000; // £10 per user in cents
                            $amount = ($pricePerUser * $subscriptionRecord->user_count) * 1.2; // Add 20% VAT
                        }
                        
                        // For Subscription::create(), we need to create product and price first
                        // Then use the price ID (price_data with product_data is not supported)
                        $productName = $subscriptionRecord->tier === 'basecamp' ? 'Basecamp Subscription' : 'Organisation Subscription';
                        
                        // Create or get product
                        $products = \Stripe\Product::all([
                            'limit' => 100,
                            'active' => true,
                        ]);
                        $product = null;
                        foreach ($products->data as $p) {
                            if ($p->name === $productName) {
                                $product = $p;
                                break;
                            }
                        }
                        
                        if (!$product) {
                            $product = \Stripe\Product::create([
                                'name' => $productName,
                                'description' => 'Monthly subscription for Tribe365',
                            ]);
                            Log::info('Created new Stripe product', [
                                'product_id' => $product->id,
                                'product_name' => $productName
                            ]);
                        }
                        
                        // Create or get price for this product
                        $prices = \Stripe\Price::all([
                            'product' => $product->id,
                            'active' => true,
                            'limit' => 100,
                        ]);
                        $price = null;
                        foreach ($prices->data as $p) {
                            if ($p->unit_amount == (int)$amount && 
                                $p->currency === 'gbp' && 
                                $p->recurring && 
                                $p->recurring->interval === 'month') {
                                $price = $p;
                                break;
                            }
                        }
                        
                        if (!$price) {
                            $price = \Stripe\Price::create([
                                'product' => $product->id,
                                'unit_amount' => (int)$amount,
                                'currency' => 'gbp',
                                'recurring' => [
                                    'interval' => 'month',
                                ],
                            ]);
                            Log::info('Created new Stripe price', [
                                'price_id' => $price->id,
                                'amount' => $amount,
                                'product_id' => $product->id
                            ]);
                        }
                        
                        // Create new subscription using price ID
                        Log::info('Creating new Stripe subscription for reactivation', [
                            'customer_id' => $customer->id,
                            'payment_method_id' => $paymentMethodId,
                            'price_id' => $price->id,
                            'amount' => $amount,
                            'tier' => $subscriptionRecord->tier,
                            'user_count' => $subscriptionRecord->user_count,
                            'old_subscription_id' => $subscriptionId
                        ]);
                        
                        $newSubscription = \Stripe\Subscription::create([
                            'customer' => $customer->id,
                            'items' => [[
                                'price' => $price->id,
                            ]],
                            'default_payment_method' => $paymentMethodId,
                            'metadata' => [
                                'subscription_record_id' => $subscriptionRecord->id,
                                'tier' => $subscriptionRecord->tier,
                                'user_count' => $subscriptionRecord->user_count,
                                'reactivated_from' => $subscriptionId,
                            ],
                            'collection_method' => 'charge_automatically',
                        ]);
                        
                        Log::info('New Stripe subscription created successfully', [
                            'new_subscription_id' => $newSubscription->id,
                            'status' => $newSubscription->status,
                            'customer_id' => $customer->id
                        ]);
                        
                        Log::info('New Stripe subscription created for reactivation', [
                            'old_subscription_id' => $subscriptionId,
                            'new_subscription_id' => $newSubscription->id,
                            'customer_id' => $customer->id,
                            'subscription_record_id' => $subscriptionRecord->id
                        ]);
                        
                        return [
                            'success' => true,
                            'subscription' => $newSubscription,
                            'new_subscription_id' => $newSubscription->id,
                            'message' => 'New subscription created successfully'
                        ];
                    } catch (\Exception $e) {
                        Log::error('Failed to create new subscription for reactivation: ' . $e->getMessage(), [
                            'old_subscription_id' => $subscriptionId,
                            'subscription_record_id' => $subscriptionRecord->id,
                            'error' => $e->getMessage()
                        ]);
                        return [
                            'success' => false,
                            'error' => 'Failed to create new subscription: ' . $e->getMessage(),
                            'message' => 'Stripe subscription was immediately cancelled - could not create new subscription'
                        ];
                    }
                } else {
                    Log::warning('Cannot create new subscription - missing subscription record or customer', [
                        'subscription_id' => $subscriptionId,
                        'has_subscription_record' => $subscriptionRecord !== null
                    ]);
                    return [
                        'success' => false,
                        'error' => 'Cannot create new subscription - missing required information',
                        'message' => 'Stripe subscription was immediately cancelled - database will be activated separately'
                    ];
                }
            }
            
            // If cancel_at_period_end is true, remove it to reactivate
            if ($stripeSubscription->cancel_at_period_end === true) {
                $subscription = \Stripe\Subscription::update($subscriptionId, [
                    'cancel_at_period_end' => false
                ]);
                
                Log::info('Stripe subscription reactivated', [
                    'subscription_id' => $subscriptionId,
                    'status' => $subscription->status,
                    'cancel_at_period_end' => $subscription->cancel_at_period_end
                ]);
                
                return [
                    'success' => true,
                    'subscription' => $subscription,
                ];
            } else {
                // Subscription is already active or not cancelled in Stripe
                Log::info('Stripe subscription is not cancelled or already active', [
                    'subscription_id' => $subscriptionId,
                    'status' => $stripeSubscription->status,
                    'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end
                ]);
                
                return [
                    'success' => true,
                    'subscription' => $stripeSubscription,
                    'message' => 'Subscription is already active in Stripe'
                ];
            }
        } catch (\Exception $e) {
            // Log error but don't fail - database will be updated anyway
            Log::warning('Stripe Subscription Reactivation Failed (non-critical): ' . $e->getMessage(), [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
                'note' => 'Database will be updated separately'
            ]);
            // Return success anyway - database update is more important
            return [
                'success' => true,
                'message' => 'Stripe reactivation failed but database will be updated',
                'stripe_error' => $e->getMessage()
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
}

