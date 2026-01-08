<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SubscriptionRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;
use App\Services\OneSignalService;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class BasecampStripeCheckoutController extends Controller
{
    public function createCheckoutSession(Request $request)
    {
        try {
            Log::info('createCheckoutSession called', [
                'all_input' => $request->all(),
                'method' => $request->method(),
                'headers' => $request->headers->all(),
            ]);
            
            $invoiceId = $request->input('invoice_id');
            $userId = $request->input('user_id');
            $amount = $request->input('amount', null); // For dashboard payment without invoice
            
            // If no invoice_id but user_id and amount provided, create invoice first
            if (!$invoiceId && $userId && $amount) {
                $user = User::findOrFail($userId);
                
                // Create or get subscription
                $subscription = \App\Models\SubscriptionRecord::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'tier' => 'basecamp',
                    ],
                    [
                        'organisation_id' => null,
                        'status' => 'inactive',
                        'user_count' => 1,
                    ]
                );
                
                // Check if invoice already exists for today (avoid duplicates)
                // Check for ANY invoice for today (not just unpaid) to prevent duplicates
                $invoiceDate = now()->toDateString();
                $existingInvoice = Invoice::where('user_id', $user->id)
                    ->where('subscription_id', $subscription->id)
                    ->whereDate('invoice_date', $invoiceDate)
                    ->first();
                
                if ($existingInvoice) {
                    Log::info('Using existing invoice for today', [
                        'invoice_id' => $existingInvoice->id,
                        'user_id' => $user->id,
                        'status' => $existingInvoice->status,
                        'invoice_date' => $invoiceDate
                    ]);
                    $invoiceId = $existingInvoice->id;
                } else {
                    // Use basecamp monthly price of £10 (not from amount parameter)
                    $monthlyPrice = 10.00; // £10 per month for basecamp users
                    // Calculate VAT (20% of subtotal)
                    $subtotal = $monthlyPrice; // £10.00
                    $taxAmount = $subtotal * 0.20; // 20% VAT = £2.00
                    $totalAmount = $subtotal + $taxAmount; // £12.00
                    
                    try {
                        // Create new invoice
                        $invoice = Invoice::create([
                            'user_id' => $user->id,
                            'organisation_id' => null,
                            'subscription_id' => $subscription->id,
                            'invoice_number' => Invoice::generateInvoiceNumber(),
                            'tier' => 'basecamp',
                            'user_count' => 1,
                            'price_per_user' => $monthlyPrice, // Base price without VAT
                            'subtotal' => $subtotal,
                            'tax_amount' => $taxAmount,
                            'total_amount' => $totalAmount,
                            'status' => 'unpaid',
                            'due_date' => now()->addDays(7),
                            'invoice_date' => $invoiceDate,
                        ]);
                        
                        $invoiceId = $invoice->id;
                        
                        Log::info('New invoice created successfully', [
                            'invoice_id' => $invoiceId,
                            'user_id' => $user->id,
                            'invoice_date' => $invoiceDate
                        ]);
                    } catch (\Illuminate\Database\QueryException $e) {
                        // Handle duplicate entry error - invoice was created by another request
                        if ($e->getCode() == 23000 && str_contains($e->getMessage(), 'Duplicate entry')) {
                            Log::warning('Duplicate invoice detected, fetching existing invoice', [
                                'user_id' => $user->id,
                                'subscription_id' => $subscription->id,
                                'invoice_date' => $invoiceDate,
                                'error' => $e->getMessage()
                            ]);
                            
                            // Fetch the existing invoice that was just created
                            $existingInvoice = Invoice::where('user_id', $user->id)
                                ->where('subscription_id', $subscription->id)
                                ->whereDate('invoice_date', $invoiceDate)
                                ->first();
                            
                            if ($existingInvoice) {
                                $invoiceId = $existingInvoice->id;
                                Log::info('Using existing invoice after duplicate error', [
                                    'invoice_id' => $invoiceId,
                                    'status' => $existingInvoice->status
                                ]);
                            } else {
                                Log::error('Failed to find existing invoice after duplicate error', [
                                    'user_id' => $user->id,
                                    'subscription_id' => $subscription->id,
                                    'invoice_date' => $invoiceDate
                                ]);
                                throw new \Exception('Failed to create or find invoice after duplicate error');
                            }
                        } else {
                            // Re-throw if it's a different database error
                            throw $e;
                        }
                    }
                }
            }
            
            if (!$invoiceId || !$userId) {
                Log::error('Missing parameters', [
                    'invoice_id' => $invoiceId,
                    'user_id' => $userId,
                ]);
                
                // Return JSON for AJAX requests
                if ($request->header('X-Requested-With') === 'XMLHttpRequest' || $request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Missing required parameters.',
                    ], 400);
                }
                
                return back()->with('error', 'Missing required parameters.');
            }
            
            $invoice = Invoice::findOrFail($invoiceId);
            $user = User::findOrFail($userId);
            
            // Verify invoice belongs to user
            if ($invoice->user_id != $userId) {
                Log::error('Invoice mismatch', [
                    'invoice_user_id' => $invoice->user_id,
                    'request_user_id' => $userId,
                ]);
                
                // Return JSON for AJAX requests
                if ($request->header('X-Requested-With') === 'XMLHttpRequest' || $request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Invalid invoice.',
                    ], 403);
                }
                
                return back()->with('error', 'Invalid invoice.');
            }
            
            // Set Stripe key
            Stripe::setApiKey(config('services.stripe.secret'));
            
            // Create checkout session
            $session = Session::create([
                'payment_method_types' => ['card'],
                'customer_email' => $user->email,
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'gbp',
                        'product_data' => [
                            'name' => 'Basecamp Subscription',
                            'description' => 'Monthly subscription for Tribe365 Basecamp',
                        ],
                        'unit_amount' => $invoice->total_amount * 100, // Convert to cents
                        'recurring' => [
                            'interval' => 'month',
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => route('basecamp.billing.payment.success') . '?session_id={CHECKOUT_SESSION_ID}&invoice_id=' . $invoice->id . '&user_id=' . $user->id,
                'cancel_url' => route('basecamp.billing') . '?user_id=' . $user->id,
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'user_id' => $user->id,
                    'tier' => 'basecamp',
                ],
            ]);
            
            Log::info('Basecamp checkout session created', [
                'session_id' => $session->id,
                'session_url' => $session->url,
                'invoice_id' => $invoice->id,
                'user_id' => $user->id,
            ]);
            
            // Return view with JavaScript redirect - more reliable than redirect()->away()
            $redirectUrl = $session->url;
            Log::info('Redirecting to Stripe via JavaScript', [
                'url' => $redirectUrl,
                'is_ajax' => $request->ajax(),
                'wants_json' => $request->wantsJson(),
                'x_requested_with' => $request->header('X-Requested-With'),
            ]);
            
            // Check if request wants JSON (AJAX request) - ALWAYS return JSON for fetch requests
            if ($request->header('X-Requested-With') === 'XMLHttpRequest' || 
                $request->ajax() || 
                $request->wantsJson() ||
                $request->expectsJson()) {
                // Return JSON with redirect URL for AJAX requests
                Log::info('Returning JSON response with redirect URL', ['redirect_url' => $redirectUrl]);
                return response()->json([
                    'success' => true,
                    'redirect_url' => $redirectUrl,
                ], 200, [
                    'X-Redirect-URL' => $redirectUrl, // Custom header for redirect
                ]);
            }
            
            // Return HTML view for regular requests
            Log::info('Returning HTML view with redirect URL', ['redirect_url' => $redirectUrl]);
            return response()->view('stripe-redirect', ['url' => $redirectUrl]);
            
        } catch (\Exception $e) {
            Log::error('Failed to create basecamp checkout session: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'input' => $request->all(),
            ]);
            
            // Return JSON for AJAX requests
            if ($request->header('X-Requested-With') === 'XMLHttpRequest' || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to create payment session. Please try again.',
                    'message' => $e->getMessage(),
                ], 500);
            }
            
            return back()->with('error', 'Failed to create payment session. Please try again.');
        }
    }
    
    public function redirectToCheckout(Request $request)
    {
        // For testing - if test parameter is provided, redirect to test URL
        if ($request->has('test')) {
            return redirect()->away('https://checkout.stripe.com/test');
        }
        
        $checkoutUrl = session()->pull('stripe_checkout_redirect');
        
        if (!$checkoutUrl) {
            Log::error('No Stripe checkout URL found in session', [
                'session_keys' => array_keys(session()->all()),
            ]);
            $userId = $request->query('user_id') ?? session('basecamp_user_id');
            return redirect()->route('basecamp.billing', ['user_id' => $userId])
                ->with('error', 'Payment session expired. Please try again.');
        }
        
        Log::info('Redirecting to Stripe checkout', [
            'url' => $checkoutUrl,
            'user_id' => $request->query('user_id'),
        ]);
        
        // Use redirect()->away() for external URLs
        return redirect()->away($checkoutUrl);
    }
    
    public function handleSuccess(Request $request)
    {
        try {
            $sessionId = $request->query('session_id');
            $invoiceId = $request->query('invoice_id');
            $userId = $request->query('user_id');
            
            Log::info('Basecamp payment success callback', [
                'session_id' => $sessionId,
                'invoice_id' => $invoiceId,
                'user_id' => $userId,
            ]);
            
            if (!$sessionId || !$invoiceId || !$userId) {
                Log::error('Missing required parameters in payment success callback');
                return redirect()->route('basecamp.billing', ['user_id' => $userId])
                    ->with('error', 'Payment verification failed. Please contact support.');
            }
            
            // Initialize Stripe
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            
            // Retrieve the checkout session
            $session = \Stripe\Checkout\Session::retrieve($sessionId);
            
            Log::info('Stripe checkout session retrieved', [
                'session_id' => $session->id,
                'payment_status' => $session->payment_status,
                'payment_intent' => $session->payment_intent ?? null,
            ]);
            
            if ($session->payment_status !== 'paid') {
                Log::warning('Payment not completed', [
                    'session_id' => $sessionId,
                    'payment_status' => $session->payment_status,
                ]);
                return redirect()->route('basecamp.billing', ['user_id' => $userId])
                    ->with('error', 'Payment was not completed. Please try again.');
            }
            
            // Get invoice and user
            $invoice = Invoice::findOrFail($invoiceId);
            $user = User::findOrFail($userId);
            
            // Check if payment already exists
            $existingPayment = Payment::where('invoice_id', $invoiceId)
                ->where('transaction_id', $session->payment_intent ?? $session->subscription)
                ->first();
                
            if ($existingPayment) {
                Log::info('Payment already processed', [
                    'invoice_id' => $invoiceId,
                    'payment_id' => $existingPayment->id,
                ]);
                return redirect()->route('basecamp.billing', ['user_id' => $userId])
                    ->with('status', 'Payment already processed.');
            }
            
            // If Checkout Session mode is 'subscription', retrieve and save the subscription ID
            $stripeSubscriptionId = null;
            if ($session->mode === 'subscription' && $session->subscription) {
                $stripeSubscriptionId = $session->subscription;
                
                // Retrieve full subscription details from Stripe
                try {
                    $stripeSubscription = \Stripe\Subscription::retrieve($stripeSubscriptionId);
                    
                    Log::info('Retrieved Stripe subscription for basecamp user', [
                        'stripe_subscription_id' => $stripeSubscriptionId,
                        'status' => $stripeSubscription->status,
                        'customer' => $stripeSubscription->customer
                    ]);
                } catch (\Exception $e) {
                    Log::warning("Failed to retrieve Stripe subscription: " . $e->getMessage());
                }
            }
            
            // Use transaction to ensure all updates happen atomically
            DB::beginTransaction();
            
            try {
                // Create payment record
                $payment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'user_id' => $userId,
                    'organisation_id' => null, // Basecamp users don't have organisation
                    'payment_method' => 'card',
                    'amount' => $invoice->total_amount,
                    'transaction_id' => $session->payment_intent ?? $session->subscription,
                    'status' => 'completed', // Payment is completed via Stripe
                    'payment_date' => now()->toDateString(),
                    'payment_notes' => 'Basecamp subscription payment via Stripe Checkout',
                ]);
                
                // Update invoice status - use save() to ensure it's saved
                $invoice->status = 'paid';
                $invoice->paid_date = now();
                $invoice->save();
                
                // Refresh invoice to verify update
                $invoice->refresh();
                
                Log::info('Invoice updated to paid in basecamp payment', [
                    'invoice_id' => $invoice->id,
                    'status' => $invoice->status,
                    'paid_date' => $invoice->paid_date,
                    'total_amount' => $invoice->total_amount
                ]);
            
            // Get or create subscription
            $subscription = SubscriptionRecord::where('user_id', $userId)
                ->where('tier', 'basecamp')
                ->first();
                
            if ($subscription) {
                // Determine start date: if subscription is expired, start from today, otherwise extend from end date
                $existingEndDate = $subscription->current_period_end ? Carbon::parse($subscription->current_period_end)->startOfDay() : null;
                $existingStartDate = $subscription->current_period_start ? Carbon::parse($subscription->current_period_start)->startOfDay() : null;
                
                // Check if subscription is expired
                $isExpired = !$existingEndDate || $existingEndDate->isPast();
                
                // If expired or end date is same as start date (broken record), start from today
                // Otherwise extend from end date
                if ($existingEndDate && $existingEndDate->isFuture() && $existingEndDate->greaterThan($existingStartDate ?? Carbon::today())) {
                    $startDate = $existingEndDate;
                } else {
                    $startDate = Carbon::today()->startOfDay();
                }
                
                // Ensure end date is always 1 month after start date
                $endDate = $startDate->copy()->addMonth();
                
                Log::info('Calculating subscription dates for basecamp', [
                    'subscription_id' => $subscription->id,
                    'existing_start' => $existingStartDate ? $existingStartDate->format('Y-m-d') : null,
                    'existing_end' => $existingEndDate ? $existingEndDate->format('Y-m-d') : null,
                    'is_expired' => $isExpired,
                    'new_start' => $startDate->format('Y-m-d'),
                    'new_end' => $endDate->format('Y-m-d')
                ]);
                
                // Update subscription with Stripe subscription ID and details
                $updateData = [
                    'status' => 'active',
                    'current_period_start' => $startDate,
                    'current_period_end' => $endDate,
                    'next_billing_date' => $endDate,
                    'last_payment_date' => Carbon::today(),
                    'canceled_at' => null, // Remove cancellation if any
                ];
                
                // If we have Stripe subscription ID, update with Stripe data
                if ($stripeSubscriptionId) {
                    try {
                        $stripeSubscription = \Stripe\Subscription::retrieve($stripeSubscriptionId);
                        $updateData['stripe_subscription_id'] = $stripeSubscriptionId;
                        $updateData['stripe_customer_id'] = $stripeSubscription->customer ?? null;
                        $updateData['status'] = $stripeSubscription->status ?? 'active';
                        
                        // Only update timestamps if they exist and are not null
                        if (isset($stripeSubscription->current_period_start) && $stripeSubscription->current_period_start !== null) {
                            $stripeStart = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_start)->startOfDay();
                            $updateData['current_period_start'] = $stripeStart;
                            // Always extend 1 month from start date
                            $updateData['current_period_end'] = $stripeStart->copy()->addMonth();
                            $updateData['next_billing_date'] = $stripeStart->copy()->addMonth();
                        } else {
                            // If Stripe doesn't provide start date, use today
                            $updateData['current_period_start'] = Carbon::today()->startOfDay();
                            $updateData['current_period_end'] = Carbon::today()->startOfDay()->addMonth();
                            $updateData['next_billing_date'] = Carbon::today()->startOfDay()->addMonth();
                        }
                        if (isset($stripeSubscription->current_period_end) && $stripeSubscription->current_period_end !== null) {
                            $stripeEnd = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end);
                            // Only use Stripe end date if it's in future and more than start date
                            if ($stripeEnd->isFuture() && $stripeEnd->greaterThan($updateData['current_period_start'])) {
                                $updateData['current_period_end'] = $stripeEnd;
                                $updateData['next_billing_date'] = $stripeEnd;
                            } else {
                                // Ensure end date is always set - use start date + 1 month
                                if (isset($updateData['current_period_start'])) {
                                    $updateData['current_period_end'] = $updateData['current_period_start']->copy()->addMonth();
                                    $updateData['next_billing_date'] = $updateData['current_period_start']->copy()->addMonth();
                                }
                            }
                        } else {
                            // If Stripe doesn't provide end date, ensure we set it from start date
                            if (isset($updateData['current_period_start'])) {
                                $updateData['current_period_end'] = $updateData['current_period_start']->copy()->addMonth();
                                $updateData['next_billing_date'] = $updateData['current_period_start']->copy()->addMonth();
                            }
                        }
                        
                        Log::info('Updated basecamp subscription with Stripe subscription ID', [
                            'subscription_id' => $subscription->id,
                            'stripe_subscription_id' => $stripeSubscriptionId,
                            'start_date' => $updateData['current_period_start']->format('Y-m-d H:i:s'),
                            'end_date' => $updateData['current_period_end']->format('Y-m-d H:i:s')
                        ]);
                    } catch (\Exception $e) {
                        Log::warning("Failed to update subscription with Stripe data: " . $e->getMessage());
                    }
                }
                
                // Activate subscription - update within transaction
                // Recalculate dates to ensure they're correct (in case updateData was modified by Stripe logic above)
                $finalStartDate = $updateData['current_period_start'] ?? $startDate;
                $finalEndDate = $updateData['current_period_end'] ?? $endDate;
                
                // Ensure end date is always 1 month after start date
                if ($finalStartDate && $finalEndDate) {
                    $finalStartDateCarbon = Carbon::parse($finalStartDate)->startOfDay();
                    $finalEndDateCarbon = $finalStartDateCarbon->copy()->addMonth();
                    
                    // Only use Stripe end date if it's properly set and in future
                    if (isset($updateData['current_period_end']) && $updateData['current_period_end'] instanceof Carbon) {
                        $stripeEnd = $updateData['current_period_end'];
                        if ($stripeEnd->isFuture() && $stripeEnd->greaterThan($finalStartDateCarbon)) {
                            $finalEndDateCarbon = $stripeEnd;
                        }
                    }
                } else {
                    $finalStartDateCarbon = Carbon::today()->startOfDay();
                    $finalEndDateCarbon = $finalStartDateCarbon->copy()->addMonth();
                }
                
                // Update subscription with explicit date setting
                $subscription->status = 'active';
                $subscription->current_period_start = $finalStartDateCarbon;
                $subscription->current_period_end = $finalEndDateCarbon;
                $subscription->next_billing_date = $finalEndDateCarbon;
                $subscription->last_payment_date = Carbon::today();
                $subscription->canceled_at = null;
                
                if (isset($updateData['stripe_subscription_id'])) {
                    $subscription->stripe_subscription_id = $updateData['stripe_subscription_id'];
                }
                if (isset($updateData['stripe_customer_id'])) {
                    $subscription->stripe_customer_id = $updateData['stripe_customer_id'];
                }
                
                // Force save
                $saved = $subscription->save();
                
                if (!$saved) {
                    Log::error('Failed to save subscription update', [
                        'subscription_id' => $subscription->id
                    ]);
                }
                
                Log::info('Basecamp subscription activated/renewed', [
                    'subscription_id' => $subscription->id,
                    'start_date' => $finalStartDateCarbon->format('Y-m-d H:i:s'),
                    'end_date' => $finalEndDateCarbon->format('Y-m-d H:i:s'),
                    'status' => 'active',
                    'is_expired' => $isExpired,
                    'saved' => $saved
                ]);
                
                // Refresh subscription to verify update
                $subscription->refresh();
                Log::info('Subscription after update', [
                    'subscription_id' => $subscription->id,
                    'current_period_start' => $subscription->current_period_start,
                    'current_period_end' => $subscription->current_period_end,
                    'status' => $subscription->status,
                    'end_date_is_future' => $subscription->current_period_end ? Carbon::parse($subscription->current_period_end)->isFuture() : false,
                    'end_date_formatted' => $subscription->current_period_end ? Carbon::parse($subscription->current_period_end)->format('Y-m-d H:i:s') : null
                ]);
            } else {
                // Create new subscription if it doesn't exist
                $subscription = SubscriptionRecord::create([
                    'user_id' => $userId,
                    'organisation_id' => null,
                    'tier' => 'basecamp',
                    'user_count' => 1,
                    'status' => 'active',
                    'current_period_start' => Carbon::today(),
                    'current_period_end' => Carbon::today()->addMonth(),
                    'next_billing_date' => Carbon::today()->addMonth(),
                    'last_payment_date' => Carbon::today(),
                ]);
                
                Log::info('Created new basecamp subscription', [
                    'subscription_id' => $subscription->id,
                    'end_date' => $subscription->current_period_end->format('Y-m-d')
                ]);
            }
            
            // Commit transaction
            DB::commit();
            
            // Send payment confirmation email (not activation email - that was already sent during registration)
            // Only send payment confirmation to avoid duplicate activation emails
            $this->sendPaymentConfirmationEmail($user, $payment);
            
            // Log the user in after successful payment
            Auth::login($user);
            
            Log::info('Basecamp payment processed successfully', [
                'invoice_id' => $invoiceId,
                'user_id' => $userId,
                'payment_id' => $payment->id,
                'subscription_id' => $subscription->id ?? null,
            ]);
            
            // Clear session data IMMEDIATELY after successful update
            session()->forget('basecamp_user_id');
            session()->forget('basecamp_invoice_id');
            session()->forget('subscription_expired');
            session()->forget('subscription_status');
            
            // Regenerate session to clear cached data
            session()->regenerate();
            
            Log::info('Session cleared and regenerated after payment');
            
            return redirect()->route('dashboard')
                ->with('status', 'Payment processed successfully! Your subscription has been activated.');
                
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error in payment processing transaction: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
                
        } catch (\Exception $e) {
            Log::error('Failed to process basecamp payment success: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            
            $userId = $request->query('user_id');
            return redirect()->route('basecamp.billing', ['user_id' => $userId])
                ->with('error', 'Payment processing failed: ' . $e->getMessage());
        }
    }
    
    private function sendPaymentConfirmationEmail($user, $payment)
    {
        try {
            $emailBody = view('emails.payment-confirmation', [
                'user' => $user,
                'payment' => $payment,
                'amount' => '£' . number_format($payment->amount, 2),
                'date' => now()->format('d M Y'),
            ])->render();
            
            $oneSignalService = new OneSignalService();
            $oneSignalService->registerEmailUserFallback($user->email, $user->id, [
                'subject' => 'Payment Confirmation - Tribe365',
                'body' => $emailBody,
            ]);
            
            Log::info('Payment confirmation email sent to basecamp user', [
                'user_id' => $user->id,
                'email' => $user->email,
                'payment_id' => $payment->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send payment confirmation email: ' . $e->getMessage(), [
                'user_id' => $user->id,
            ]);
        }
    }
}

