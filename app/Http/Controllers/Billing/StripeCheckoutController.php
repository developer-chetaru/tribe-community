<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentRecord;
use App\Models\Organisation;
use App\Models\SubscriptionRecord;
use App\Services\SubscriptionService;
use App\Services\Billing\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * @OA\Tag(
 *     name="Billing - Subscription Management",
 *     description="Subscription management endpoints for renewal, reactivation, and cancellation (works for both organisation and basecamp users)"
 * )
 */
class StripeCheckoutController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/billing/subscription/renew",
     *     tags={"Billing - Subscription Management", "Organisation Billing", "Basecamp Billing"},
     *     summary="Renew expired subscription",
     *     description="Creates a Stripe Checkout session to renew an expired subscription. Works for both organisation users (directors) and basecamp users. The user will be redirected to Stripe Checkout to complete payment. After successful payment, subscription will be activated/renewed for another month. This endpoint works for mobile apps.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         description="No request body required. Subscription is identified from authenticated user.",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Checkout session created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true, description="Indicates successful checkout session creation"),
     *             @OA\Property(property="redirect_url", type="string", example="https://checkout.stripe.com/pay/cs_test_...", description="Stripe Checkout URL to redirect user for payment"),
     *             @OA\Property(property="message", type="string", example="Checkout session created successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - Failed to create renewal checkout",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to create checkout session: Error message")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User is not a director or basecamp user",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Only directors and basecamp users can renew subscriptions.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to create checkout session: Error message")
     *         )
     *     )
     * )
     * 
     * @OA\Post(
     *     path="/api/basecamp/subscription/renew",
     *     tags={"Billing - Subscription Management", "Basecamp Billing", "Basecamp Users"},
     *     summary="Renew expired basecamp subscription",
     *     description="Creates a Stripe Checkout session to renew an expired basecamp subscription. This is an alias endpoint for basecamp users. The user will be redirected to Stripe Checkout to complete payment (£12.00 including VAT). After successful payment, subscription will be activated/renewed for another month. This endpoint works for mobile apps.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         description="No request body required. Subscription is identified from authenticated user.",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Checkout session created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="redirect_url", type="string", example="https://checkout.stripe.com/pay/cs_test_...", description="Stripe Checkout URL to redirect user for payment"),
     *             @OA\Property(property="message", type="string", example="Checkout session created successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - Failed to create renewal checkout",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to create checkout session: Error message")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User is not a basecamp user",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Only directors and basecamp users can renew subscriptions.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to create checkout session: Error message")
     *         )
     *     )
     * )
     */
    public function createRenewalCheckout(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'error' => 'Unauthorized'
                ], 401);
            }
            
            // Check if user is director or basecamp
            if (!$user->hasRole('director') && !$user->hasRole('basecamp')) {
                return response()->json([
                    'error' => 'Only directors and basecamp users can renew subscriptions.'
                ], 403);
            }
            
            // Get or create renewal invoice using the same logic as renewSubscription
            $invoice = null;
            
            DB::transaction(function() use ($user, &$invoice) {
                if ($user->hasRole('basecamp')) {
                    $subscription = \App\Models\SubscriptionRecord::where('user_id', $user->id)
                        ->where('tier', 'basecamp')
                        ->orderBy('id', 'desc') // Use ID for better ordering - gets latest subscription
                        ->first();
                    
                    if (!$subscription) {
                        $subscription = \App\Models\SubscriptionRecord::create([
                            'user_id' => $user->id,
                            'organisation_id' => null,
                            'tier' => 'basecamp',
                            'user_count' => 1,
                            'status' => 'suspended',
                            'current_period_start' => now(),
                            'current_period_end' => now()->subDay(),
                            'next_billing_date' => now(),
                        ]);
                    }
                    
                    // Check for existing invoice using the same criteria as unique constraint
                    // unique_user_subscription_date = user_id, subscription_id, invoice_date
                    $invoiceDate = now()->toDateString();
                    $existingInvoice = Invoice::where('subscription_id', $subscription->id)
                        ->where('user_id', $user->id)
                        ->whereDate('invoice_date', $invoiceDate)
                        ->first();
                    
                    if ($existingInvoice) {
                        // Use existing invoice (even if status is not pending, we can still use it for checkout)
                        $invoice = $existingInvoice;
                        Log::info('Using existing invoice for renewal', [
                            'invoice_id' => $invoice->id,
                            'invoice_date' => $invoiceDate,
                            'status' => $invoice->status
                        ]);
                    } else {
                        try {
                            $subtotal = 10.00;
                            $vatAmount = $subtotal * 0.20;
                            $totalAmount = $subtotal + $vatAmount;
                            
                            $invoice = Invoice::create([
                                'subscription_id' => $subscription->id,
                                'user_id' => $user->id,
                                'invoice_number' => Invoice::generateInvoiceNumber(),
                                'invoice_date' => $invoiceDate,
                                'due_date' => now()->addDays(7)->toDateString(),
                                'user_count' => 1,
                                'price_per_user' => $subtotal,
                                'subtotal' => $subtotal,
                                'tax_amount' => $vatAmount,
                                'total_amount' => $totalAmount,
                                'status' => 'pending',
                                'tier' => 'basecamp',
                            ]);
                        } catch (\Illuminate\Database\QueryException $e) {
                            // Handle duplicate entry - invoice was created by another request
                            if ($e->getCode() == 23000 && str_contains($e->getMessage(), 'Duplicate entry')) {
                                Log::warning('Duplicate invoice detected, fetching existing invoice', [
                                    'user_id' => $user->id,
                                    'subscription_id' => $subscription->id,
                                    'invoice_date' => $invoiceDate
                                ]);
                                
                                // Fetch the existing invoice that was just created
                                $invoice = Invoice::where('subscription_id', $subscription->id)
                                    ->where('user_id', $user->id)
                                    ->whereDate('invoice_date', $invoiceDate)
                                    ->first();
                                
                                if (!$invoice) {
                                    throw new \Exception('Failed to create or find invoice after duplicate error');
                                }
                            } else {
                                throw $e;
                            }
                        }
                    }
                } else {
                    // For directors
                    $subscription = \App\Models\SubscriptionRecord::where('organisation_id', $user->orgId)
                        ->orderBy('id', 'desc') // Use ID for better ordering - gets latest subscription
                        ->first();
                    
                    if (!$subscription) {
                        $subscription = \App\Models\SubscriptionRecord::create([
                            'organisation_id' => $user->orgId,
                            'tier' => 'spark',
                            'user_count' => \App\Models\User::where('orgId', $user->orgId)
                                ->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'))
                                ->count(),
                            'status' => 'suspended',
                            'current_period_start' => now(),
                            'current_period_end' => now()->subDay(),
                            'next_billing_date' => now(),
                        ]);
                    }
                    
                    $userCount = \App\Models\User::where('orgId', $user->orgId)
                        ->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'))
                        ->count();
                    
                    $pricePerUser = 10.00;
                    $totalAmount = $userCount * $pricePerUser;
                    
                    // Check for existing invoice using the same criteria as unique constraint
                    // For organisations, check by subscription_id, organisation_id, and invoice_date
                    $invoiceDate = now()->toDateString();
                    $existingInvoice = Invoice::where('subscription_id', $subscription->id)
                        ->where('organisation_id', $user->orgId)
                        ->whereDate('invoice_date', $invoiceDate)
                        ->first();
                    
                    if ($existingInvoice) {
                        // Use existing invoice (even if status is not pending, we can still use it for checkout)
                        $invoice = $existingInvoice;
                        Log::info('Using existing invoice for renewal', [
                            'invoice_id' => $invoice->id,
                            'invoice_date' => $invoiceDate,
                            'status' => $invoice->status
                        ]);
                    } else {
                        try {
                            $invoice = Invoice::create([
                                'subscription_id' => $subscription->id,
                                'organisation_id' => $user->orgId,
                                'invoice_number' => Invoice::generateInvoiceNumber(),
                                'invoice_date' => $invoiceDate,
                                'due_date' => now()->addDays(7)->toDateString(),
                                'user_count' => $userCount,
                                'price_per_user' => $pricePerUser,
                                'subtotal' => $totalAmount,
                                'tax_amount' => 0.00,
                                'total_amount' => $totalAmount,
                                'status' => 'pending',
                            ]);
                        } catch (\Illuminate\Database\QueryException $e) {
                            // Handle duplicate entry - invoice was created by another request
                            if ($e->getCode() == 23000 && str_contains($e->getMessage(), 'Duplicate entry')) {
                                Log::warning('Duplicate invoice detected, fetching existing invoice', [
                                    'organisation_id' => $user->orgId,
                                    'subscription_id' => $subscription->id,
                                    'invoice_date' => $invoiceDate
                                ]);
                                
                                // Fetch the existing invoice that was just created
                                $invoice = Invoice::where('subscription_id', $subscription->id)
                                    ->where('organisation_id', $user->orgId)
                                    ->whereDate('invoice_date', $invoiceDate)
                                    ->first();
                                
                                if (!$invoice) {
                                    throw new \Exception('Failed to create or find invoice after duplicate error');
                                }
                            } else {
                                throw $e;
                            }
                        }
                    }
                }
            });
            
            if (!$invoice) {
                Log::error('Invoice is null after transaction', [
                    'user_id' => $user->id,
                    'is_basecamp' => $user->hasRole('basecamp')
                ]);
                return response()->json([
                    'error' => 'Failed to create invoice'
                ], 500);
            }
            
            // Create Stripe Checkout Session
            Log::info('Creating checkout session for invoice', [
                'invoice_id' => $invoice->id,
                'user_id' => $user->id,
                'is_basecamp' => $user->hasRole('basecamp'),
                'total_amount' => $invoice->total_amount
            ]);
            
            $checkoutUrl = $this->createCheckoutSessionForInvoice($invoice, $user);
            
            if (!$checkoutUrl) {
                Log::error('Checkout session creation returned null', [
                    'invoice_id' => $invoice->id,
                    'user_id' => $user->id
                ]);
                return response()->json([
                    'error' => 'Failed to create checkout session. Please check your Stripe configuration.'
                ], 500);
            }
            
            Log::info('Checkout session created successfully', [
                'invoice_id' => $invoice->id,
                'checkout_url' => $checkoutUrl
            ]);
            
            // Check if this is an API request (mobile app)
            if ($request->header('X-Requested-With') === 'XMLHttpRequest' || 
                $request->ajax() || 
                $request->wantsJson() ||
                $request->expectsJson() ||
                str_starts_with($request->path(), 'api/')) {
                // Return JSON for API/mobile requests
                return response()->json([
                    'success' => true,
                    'redirect_url' => $checkoutUrl,
                    'message' => 'Checkout session created successfully'
                ]);
            }
            
            // For web requests, return redirect URL
            return response()->json([
                'redirect_url' => $checkoutUrl
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to create renewal checkout: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to create checkout session: ' . $e->getMessage()
            ], 500);
        }
    }
    
    private function createCheckoutSessionForInvoice($invoice, $user)
    {
        try {
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            
            if ($user->hasRole('basecamp')) {
                $checkoutParams = [
                    'payment_method_types' => ['card'],
                    'line_items' => [[
                        'price_data' => [
                            'currency' => 'gbp',
                            'product_data' => [
                                'name' => 'Basecamp Subscription',
                                'description' => 'Monthly subscription for Basecamp tier - £10/month',
                            ],
                            'unit_amount' => $invoice->total_amount * 100,
                            'recurring' => [
                                'interval' => 'month',
                            ],
                        ],
                        'quantity' => 1,
                    ]],
                    'mode' => 'subscription',
                    'success_url' => route('billing.payment.success') . '?session_id={CHECKOUT_SESSION_ID}&invoice_id=' . $invoice->id,
                    'cancel_url' => route('billing') . '?canceled=true',
                    'customer_email' => $user->email,
                    'metadata' => [
                        'invoice_id' => $invoice->id,
                        'user_id' => $user->id,
                        'tier' => 'basecamp',
                    ],
                ];
            } else {
                $organisation = \App\Models\Organisation::findOrFail($invoice->organisation_id);
                $customerEmail = $user->email ?? $organisation->admin_email ?? $organisation->users()->first()?->email;
                
                $checkoutParams = [
                    'payment_method_types' => ['card'],
                    'line_items' => [[
                        'price_data' => [
                            'currency' => 'gbp',
                            'product_data' => [
                                'name' => "Invoice #{$invoice->invoice_number}",
                                'description' => "Payment for {$invoice->user_count} users - {$organisation->name}",
                            ],
                            'unit_amount' => $invoice->total_amount * 100,
                            'recurring' => [
                                'interval' => 'month',
                            ],
                        ],
                        'quantity' => 1,
                    ]],
                    'mode' => 'subscription',
                    'success_url' => route('billing.payment.success') . '?session_id={CHECKOUT_SESSION_ID}&invoice_id=' . $invoice->id,
                    'cancel_url' => route('billing') . '?canceled=true',
                    'billing_address_collection' => 'auto',
                    'metadata' => [
                        'invoice_id' => $invoice->id,
                        'organisation_id' => $organisation->id,
                        'invoice_number' => $invoice->invoice_number,
                        'user_id' => $user->id,
                        'user_email' => $customerEmail,
                    ],
                ];
                
                if ($organisation->stripe_customer_id) {
                    $checkoutParams['customer'] = $organisation->stripe_customer_id;
                } else {
                    $checkoutParams['customer_email'] = $customerEmail;
                }
            }
            
            $checkoutSession = \Stripe\Checkout\Session::create($checkoutParams);
            
            return $checkoutSession->url;
            
        } catch (\Exception $e) {
            Log::error('Failed to create Stripe checkout session: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'invoice_id' => $invoice->id ?? null,
                'user_id' => $user->id ?? null,
                'is_basecamp' => $user->hasRole('basecamp') ?? false,
            ]);
            return null;
        }
    }
    
    public function handleSuccess(Request $request)
    {
        try {
            $sessionId = $request->query('session_id');
            $invoiceId = $request->query('invoice_id');
            
            if (!$sessionId || !$invoiceId) {
                session()->flash('error', 'Invalid payment session.');
                return redirect()->route('billing');
            }
            
            // Initialize Stripe API key
            $stripeSecretKey = config('services.stripe.secret');
            if (!$stripeSecretKey) {
                throw new \Exception('Stripe API key not configured.');
            }
            
            // Set Stripe API key
            if (class_exists(\Stripe\Stripe::class)) {
                \Stripe\Stripe::setApiKey($stripeSecretKey);
            }
            
            // Retrieve the checkout session from Stripe
            if (!class_exists(\Stripe\Checkout\Session::class)) {
                throw new \Exception('Stripe PHP package is not installed.');
            }
            
            $session = \Stripe\Checkout\Session::retrieve($sessionId);
            
            if ($session->payment_status !== 'paid') {
                session()->flash('error', 'Payment was not completed.');
                return redirect()->route('billing');
            }
            
            $invoice = Invoice::with('subscription')->findOrFail($invoiceId);
            $user = Auth::user();
            
            // Check if user has permission
            if (!$user) {
                session()->flash('error', 'User not authenticated.');
                return redirect()->route('billing');
            }
            
            // For basecamp users, check user_id
            if ($user->hasRole('basecamp')) {
                if ($invoice->user_id !== $user->id) {
                    session()->flash('error', 'Unauthorized access.');
                    return redirect()->route('billing');
                }
            } elseif ($user->hasRole('director')) {
                if ($invoice->organisation_id !== $user->orgId) {
                    session()->flash('error', 'Unauthorized access.');
                    return redirect()->route('billing');
                }
            } elseif (!$user->hasRole('super_admin')) {
                session()->flash('error', 'Unauthorized access.');
                return redirect()->route('billing');
            }
            
            // Check if payment already exists
            $existingPayment = Payment::where('invoice_id', $invoiceId)
                ->where('status', 'completed')
                ->first();
            
            if ($existingPayment) {
                session()->flash('success', 'Payment already processed successfully.');
                return redirect()->route('billing');
            }
            
            DB::beginTransaction();
            
            try {
                // If Checkout Session mode is 'subscription', retrieve and save the subscription ID
                $stripeSubscriptionId = null;
                if ($session->mode === 'subscription' && $session->subscription) {
                    $stripeSubscriptionId = $session->subscription;
                    
                    // Retrieve full subscription details from Stripe
                    try {
                        $stripeSubscription = \Stripe\Subscription::retrieve($stripeSubscriptionId);
                        
                        // Update or create subscription record with Stripe subscription ID
                        if ($invoice->subscription) {
                            $updateData = [
                                'stripe_subscription_id' => $stripeSubscriptionId,
                                'stripe_customer_id' => $stripeSubscription->customer ?? null,
                                'status' => 'active', // Always set to active after successful payment
                            ];
                            
                            // Only update timestamps if they exist and are not null
                            if (isset($stripeSubscription->current_period_start) && $stripeSubscription->current_period_start !== null) {
                                $updateData['current_period_start'] = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_start);
                            }
                            if (isset($stripeSubscription->current_period_end) && $stripeSubscription->current_period_end !== null) {
                                $updateData['current_period_end'] = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end);
                                $updateData['next_billing_date'] = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end);
                            }
                            
                            // Remove cancellation status - clear any cancellation-related fields
                            $updateData['canceled_at'] = null;
                            
                            $invoice->subscription->update($updateData);
                            
                            // Ensure end date is set if not provided
                            if (!isset($updateData['current_period_end']) || $updateData['current_period_end'] === null) {
                                $startDate = $updateData['current_period_start'] ?? Carbon::today();
                                $updateData['current_period_end'] = Carbon::parse($startDate)->addMonth();
                                $updateData['next_billing_date'] = Carbon::parse($startDate)->addMonth();
                                $invoice->subscription->update([
                                    'current_period_end' => $updateData['current_period_end'],
                                    'next_billing_date' => $updateData['next_billing_date'],
                                ]);
                            }
                            
                            Log::info("Updated subscription with Stripe subscription ID and removed cancellation", [
                                'subscription_id' => $invoice->subscription->id,
                                'stripe_subscription_id' => $stripeSubscriptionId,
                                'status' => 'active',
                                'current_period_start' => $updateData['current_period_start'] ? Carbon::parse($updateData['current_period_start'])->format('Y-m-d') : null,
                                'current_period_end' => $updateData['current_period_end'] ? Carbon::parse($updateData['current_period_end'])->format('Y-m-d') : null
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::warning("Failed to retrieve Stripe subscription: " . $e->getMessage());
                    }
                }
                
                // Create payment record
                $payment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'organisation_id' => $invoice->organisation_id,
                    'user_id' => $invoice->user_id, // Add user_id for basecamp users
                    'amount' => $invoice->total_amount,
                    'payment_method' => 'stripe',
                    'status' => 'completed',
                    'transaction_id' => $session->payment_intent ?? $session->subscription,
                    'payment_date' => now(),
                    'payment_notes' => "Payment completed via Stripe Checkout - Session: {$sessionId}",
                    'paid_by_user_id' => $user->id,
                ]);
                
                // Create payment record entry
                PaymentRecord::create([
                    'organisation_id' => $invoice->organisation_id,
                    'subscription_id' => $invoice->subscription_id,
                    'stripe_payment_intent_id' => $session->payment_intent,
                    'stripe_subscription_id' => $stripeSubscriptionId,
                    'amount' => $invoice->total_amount,
                    'currency' => 'gbp',
                    'status' => 'succeeded',
                    'type' => $session->mode === 'subscription' ? 'subscription_payment' : 'one_time_payment', // Fixed: use 'subscription_payment' not 'subscription'
                    'paid_at' => now(),
                ]);
                
                // Update invoice status FIRST
                $invoice->status = 'paid';
                $invoice->paid_date = now();
                $invoice->save();
                
                // Refresh invoice to ensure changes are saved
                $invoice->refresh();
                
                Log::info("Invoice {$invoice->id} status updated to paid", [
                    'invoice_status' => $invoice->status,
                    'paid_date' => $invoice->paid_date,
                    'stripe_subscription_id' => $stripeSubscriptionId
                ]);
                
                // Activate or renew subscription
                // IMPORTANT: Update subscription regardless of checkout session mode (payment or subscription)
                if ($invoice->subscription) {
                    // Refresh subscription to get latest data
                    $invoice->subscription->refresh();
                    
                    // Always call activateSubscription to properly update subscription
                    // It handles both expired and active subscriptions correctly
                    $subscriptionService = new SubscriptionService();
                    $activationResult = $subscriptionService->activateSubscription($payment->id);
                    
                    if (!$activationResult) {
                        Log::warning("Failed to activate subscription for payment {$payment->id}");
                    } else {
                        Log::info("Subscription activated successfully for payment {$payment->id}");
                    }
                    
                    // If mode is 'payment' and subscription wasn't updated by Stripe, ensure dates are set
                    if ($session->mode === 'payment') {
                        $invoice->subscription->refresh();
                        $endDate = $invoice->subscription->current_period_end ? Carbon::parse($invoice->subscription->current_period_end) : null;
                        
                        // If end date is not in future, update it
                        if (!$endDate || !$endDate->isFuture()) {
                            $startDate = Carbon::today();
                            $invoice->subscription->update([
                                'current_period_start' => $startDate,
                                'current_period_end' => $startDate->copy()->addMonth(),
                                'next_billing_date' => $startDate->copy()->addMonth(),
                            ]);
                            Log::info("Updated subscription dates for one-time payment", [
                                'subscription_id' => $invoice->subscription->id,
                                'end_date' => $startDate->copy()->addMonth()->format('Y-m-d')
                            ]);
                        }
                    }
                    
                    // Final safety check - ensure subscription is active and has future end date
                    $invoice->subscription->refresh();
                    $finalEndDate = $invoice->subscription->current_period_end ? Carbon::parse($invoice->subscription->current_period_end)->startOfDay() : null;
                    $finalStartDate = $invoice->subscription->current_period_start ? Carbon::parse($invoice->subscription->current_period_start)->startOfDay() : null;
                    
                    // Check if subscription needs fixing
                    $needsFix = false;
                    if ($invoice->subscription->status !== 'active' || 
                        $invoice->subscription->canceled_at !== null) {
                        $needsFix = true;
                    } elseif (!$finalEndDate || !$finalEndDate->isFuture()) {
                        $needsFix = true;
                    } elseif ($finalStartDate && $finalEndDate && $finalEndDate->equalTo($finalStartDate)) {
                        // End date same as start date - broken record
                        $needsFix = true;
                    }
                    
                    if ($needsFix) {
                        $startDate = Carbon::today()->startOfDay();
                        $endDate = $startDate->copy()->addMonth();
                        
                        $invoice->subscription->update([
                            'status' => 'active',
                            'canceled_at' => null,
                            'current_period_start' => $startDate,
                            'current_period_end' => $endDate,
                            'next_billing_date' => $endDate,
                        ]);
                        
                        $invoice->subscription->refresh();
                        Log::info("Force updated subscription to ensure active status and future end date", [
                            'subscription_id' => $invoice->subscription->id,
                            'start_date' => $startDate->format('Y-m-d'),
                            'end_date' => $endDate->format('Y-m-d'),
                            'actual_end_date' => $invoice->subscription->current_period_end ? Carbon::parse($invoice->subscription->current_period_end)->format('Y-m-d') : null
                        ]);
                    }
                } else {
                    Log::warning("Invoice {$invoice->id} has no associated subscription");
                }
                
                DB::commit();
                
                // Refresh subscription to get latest data
                $invoice->subscription->refresh();
                
                Log::info("Stripe Checkout payment completed for invoice {$invoice->id}: {$session->payment_intent}", [
                    'invoice_status' => $invoice->fresh()->status,
                    'payment_id' => $payment->id,
                    'subscription_id' => $invoice->subscription_id,
                    'subscription_status' => $invoice->subscription->status,
                    'subscription_end_date' => $invoice->subscription->current_period_end,
                    'canceled_at' => $invoice->subscription->canceled_at
                ]);
                
                // Force refresh subscription to ensure latest data
                if ($invoice->subscription) {
                    $invoice->subscription->refresh();
                    
                    Log::info("Final subscription status after payment", [
                        'subscription_id' => $invoice->subscription->id,
                        'status' => $invoice->subscription->status,
                        'current_period_end' => $invoice->subscription->current_period_end ? $invoice->subscription->current_period_end->format('Y-m-d') : null,
                        'is_future' => $invoice->subscription->current_period_end ? Carbon::parse($invoice->subscription->current_period_end)->isFuture() : false,
                    ]);
                }
                
                // Force refresh subscription status via service
                $subscriptionService = new SubscriptionService();
                if ($invoice->organisation_id) {
                    $subscriptionService->getSubscriptionStatus($invoice->organisation_id);
                } elseif ($invoice->user_id) {
                    // For basecamp users, refresh is handled differently  
                    $subscriptionService->getSubscriptionStatus(null);
                }
                
                // Clear subscription expired flags from session IMMEDIATELY
                session()->forget('subscription_expired');
                session()->forget('subscription_status');
                
                Log::info("Session flags cleared after payment success", [
                    'subscription_id' => $invoice->subscription_id,
                    'subscription_status' => $invoice->subscription->status ?? 'N/A',
                    'subscription_end_date' => $invoice->subscription->current_period_end ? $invoice->subscription->current_period_end->format('Y-m-d') : 'N/A'
                ]);
                
                // Regenerate session ID to clear any cached data
                session()->regenerate();
                
                session()->flash('success', 'Payment processed successfully. Your subscription has been activated.');
                session()->put('payment_success', true);
                session()->put('refresh_billing', true);
                session()->put('payment_completed', true);
                
                // Redirect to dashboard instead of billing to show success message
                // Dashboard will check subscription status fresh and won't show popup
                return redirect()->route('dashboard', ['payment_completed' => time()])->with('payment_completed', true);
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to handle Stripe Checkout success: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'Failed to process payment: ' . $e->getMessage());
            return redirect()->route('billing');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/billing/subscription/reactivate",
     *     tags={"Billing - Subscription Management", "Organisation Billing", "Basecamp Billing"},
     *     summary="Reactivate cancelled subscription",
     *     description="Reactivates a cancelled subscription without requiring payment. This only works if the subscription was cancelled but the end date has not yet passed. If the subscription has expired (end date passed), use the renew endpoint instead. Works for both organisation users (directors) and basecamp users. This endpoint works for mobile apps.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         description="No request body required. Subscription is identified from authenticated user.",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subscription reactivated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Subscription activated successfully"),
     *             @OA\Property(property="refresh", type="boolean", example=true, description="Indicates that the client should refresh subscription status"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="subscription_id", type="integer", example=1),
     *                 @OA\Property(property="status", type="string", example="active"),
     *                 @OA\Property(property="end_date", type="string", format="date", example="2026-03-08"),
     *                 @OA\Property(property="user_id", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Subscription is not cancelled"),
     *             @OA\Property(property="needs_payment", type="boolean", example=true, description="If true, subscription has expired and renewal payment is required")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Subscription not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Subscription not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Error message")
     *         )
     *     )
     * )
     * 
     * @OA\Post(
     *     path="/api/basecamp/subscription/reactivate",
     *     tags={"Billing - Subscription Management", "Basecamp Billing", "Basecamp Users"},
     *     summary="Reactivate cancelled basecamp subscription",
     *     description="Reactivates a cancelled basecamp subscription without requiring payment. This is an alias endpoint for basecamp users. This only works if the subscription was cancelled but the end date has not yet passed. If the subscription has expired (end date passed), use the renew endpoint instead. This endpoint works for mobile apps.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         description="No request body required. Subscription is identified from authenticated user.",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subscription reactivated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Subscription activated successfully"),
     *             @OA\Property(property="refresh", type="boolean", example=true, description="Indicates that the client should refresh subscription status"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="subscription_id", type="integer", example=1),
     *                 @OA\Property(property="status", type="string", example="active"),
     *                 @OA\Property(property="end_date", type="string", format="date", example="2026-03-08"),
     *                 @OA\Property(property="user_id", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Subscription is not cancelled"),
     *             @OA\Property(property="needs_payment", type="boolean", example=true, description="If true, subscription has expired and renewal payment is required")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Subscription not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Subscription not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Error message")
     *         )
     *     )
     * )
     * Reactivate a cancelled subscription (without payment)
     * Only works if subscription is cancelled but end date hasn't passed
     */
    public function reactivateSubscription(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized'
                ], 401);
            }

            // Get subscription
            $subscription = null;
            if ($user->hasRole('basecamp')) {
                $subscription = SubscriptionRecord::where('user_id', $user->id)
                    ->where('tier', 'basecamp')
                    ->orderBy('id', 'desc')
                    ->first();
            } elseif ($user->orgId) {
                $subscription = SubscriptionRecord::where('organisation_id', $user->orgId)
                    ->orderBy('id', 'desc')
                    ->first();
            }

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'error' => 'Subscription not found'
                ], 404);
            }

            // Check if subscription is cancelled
            $isCancelled = in_array(strtolower($subscription->status), ['canceled', 'cancelled', 'cancel_at_period_end']);
            
            // If already active, just return success (might have been activated in another tab/session)
            if (!$isCancelled && $subscription->status === 'active') {
                // Clear any session flags and return success
                session()->forget('subscription_expired');
                session()->forget('subscription_status');
                
                return response()->json([
                    'success' => true,
                    'message' => 'Subscription is already active'
                ]);
            }
            
            if (!$isCancelled) {
                return response()->json([
                    'success' => false,
                    'error' => 'Subscription is not cancelled'
                ], 400);
            }

            // Check if end date has passed (expired)
            $endDate = $subscription->current_period_end ? Carbon::parse($subscription->current_period_end)->startOfDay() : null;
            $today = Carbon::today();
            
            if ($endDate && $today->greaterThan($endDate)) {
                // Subscription has expired - need payment, not reactivation
                return response()->json([
                    'success' => false,
                    'error' => 'Subscription has expired. Please renew with payment.',
                    'needs_payment' => true
                ], 400);
            }

            // Update database status to active (regardless of Stripe status)
            // This allows user to continue using service until end date
            $subscription->update([
                'status' => 'active',
                'canceled_at' => null, // Remove cancellation timestamp
            ]);
            
            // Try to reactivate in Stripe if subscription ID exists
            // But don't fail if Stripe reactivation fails - database is already updated
            if ($subscription->stripe_subscription_id) {
                try {
                    $stripeService = new StripeService();
                    $result = $stripeService->reactivateSubscription($subscription->stripe_subscription_id, $subscription);
                    
                    if ($result['success']) {
                        // If a new subscription was created, update the database with the new subscription ID
                        if (isset($result['new_subscription_id'])) {
                            $oldStripeSubscriptionId = $subscription->stripe_subscription_id;
                            $subscription->update([
                                'stripe_subscription_id' => $result['new_subscription_id'],
                            ]);
                            Log::info('New Stripe subscription created and saved to database', [
                                'subscription_id' => $subscription->id,
                                'old_stripe_subscription_id' => $oldStripeSubscriptionId,
                                'new_stripe_subscription_id' => $result['new_subscription_id'],
                                'user_id' => $user->id
                            ]);
                        } else {
                            Log::info('Subscription reactivated in both database and Stripe', [
                                'subscription_id' => $subscription->id,
                                'stripe_subscription_id' => $subscription->stripe_subscription_id,
                                'user_id' => $user->id
                            ]);
                        }
                    } else {
                        // Stripe reactivation failed, but database is already updated
                        // Log the error with full details for debugging
                        Log::error('Database subscription activated but Stripe reactivation failed', [
                            'subscription_id' => $subscription->id,
                            'stripe_subscription_id' => $subscription->stripe_subscription_id,
                            'stripe_error' => $result['error'] ?? 'Unknown error',
                            'stripe_message' => $result['message'] ?? null,
                            'user_id' => $user->id,
                            'tier' => $subscription->tier,
                            'has_customer_id' => !empty($subscription->stripe_customer_id),
                            'full_result' => $result
                        ]);
                        
                        // Continue with success response but include warning in message
                        // Database is already updated, so subscription is active in our system
                        // User will see the subscription as active, but Stripe won't charge automatically
                        // This is acceptable - they can manually pay invoices
                    }
                } catch (\Exception $e) {
                    // Stripe error - but database is already updated, so continue
                    Log::warning('Database subscription activated but Stripe reactivation threw exception', [
                        'subscription_id' => $subscription->id,
                        'stripe_subscription_id' => $subscription->stripe_subscription_id,
                        'error' => $e->getMessage(),
                        'user_id' => $user->id
                    ]);
                }
            }
            
            // Refresh subscription from database to get updated values
            $subscription->refresh();
            
            // Clear session flags to ensure UI updates correctly (only for web requests)
            if (!$request->expectsJson() && !str_starts_with($request->path(), 'api/')) {
                session()->forget('subscription_expired');
                session()->forget('subscription_status');
                session()->put('refresh_billing', true);
            }
            
            Log::info('Subscription activated successfully in database', [
                'subscription_id' => $subscription->id,
                'status' => $subscription->status,
                'canceled_at' => $subscription->canceled_at,
                'end_date' => $subscription->current_period_end,
                'user_id' => $user->id
            ]);
            
            // Check if Stripe reactivation was attempted and failed
            $stripeWarning = null;
            if ($subscription->stripe_subscription_id) {
                // Check logs to see if there was a Stripe error (we already logged it above)
                // We'll include a note in the response if Stripe reactivation failed
                $stripeWarning = 'Note: Subscription activated in database. If Stripe reactivation failed, please check logs.';
            }
            
            // Return JSON response (works for both API and web requests)
            return response()->json([
                'success' => true,
                'message' => 'Subscription activated successfully' . ($stripeWarning ? '. ' . $stripeWarning : ''),
                'refresh' => true,
                'data' => [
                    'subscription_id' => $subscription->id,
                    'status' => $subscription->status,
                    'end_date' => $subscription->current_period_end ? $subscription->current_period_end->format('Y-m-d') : null,
                    'user_id' => $user->id,
                    'stripe_subscription_id' => $subscription->stripe_subscription_id,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to reactivate subscription: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to reactivate subscription: ' . $e->getMessage()
            ], 500);
        }
    }
}

