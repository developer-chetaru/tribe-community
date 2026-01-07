<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SubscriptionRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
                $existingInvoice = Invoice::where('user_id', $user->id)
                    ->where('subscription_id', $subscription->id)
                    ->whereDate('invoice_date', today())
                    ->where('status', 'unpaid')
                    ->first();
                
                if ($existingInvoice) {
                    Log::info('Using existing unpaid invoice for today', [
                        'invoice_id' => $existingInvoice->id,
                        'user_id' => $user->id,
                    ]);
                    $invoiceId = $existingInvoice->id;
                } else {
                    // Use basecamp monthly price of £10 (not from amount parameter)
                    $monthlyPrice = 10.00; // £10 per month for basecamp users
                    // Calculate VAT (20% of subtotal)
                    $subtotal = $monthlyPrice; // £10.00
                    $taxAmount = $subtotal * 0.20; // 20% VAT = £2.00
                    $totalAmount = $subtotal + $taxAmount; // £12.00
                    
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
                        'invoice_date' => now(),
                    ]);
                    
                    $invoiceId = $invoice->id;
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
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
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
                ->where('transaction_id', $session->payment_intent)
                ->first();
                
            if ($existingPayment) {
                Log::info('Payment already processed', [
                    'invoice_id' => $invoiceId,
                    'payment_id' => $existingPayment->id,
                ]);
                return redirect()->route('basecamp.billing', ['user_id' => $userId])
                    ->with('status', 'Payment already processed.');
            }
            
            // Create payment record
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'user_id' => $userId,
                'organisation_id' => null, // Basecamp users don't have organisation
                'payment_method' => 'card',
                'amount' => $invoice->total_amount,
                'transaction_id' => $session->payment_intent,
                'status' => 'completed', // Payment is completed via Stripe
                'payment_date' => now()->toDateString(),
                'payment_notes' => 'Basecamp subscription payment via Stripe Checkout',
            ]);
            
            // Update invoice status
            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
            
            // Get or create subscription
            $subscription = SubscriptionRecord::where('user_id', $userId)
                ->where('tier', 'basecamp')
                ->first();
                
            if ($subscription) {
                // Activate subscription
                $subscription->update([
                    'status' => 'active',
                    'current_period_start' => now(),
                    'current_period_end' => now()->addMonth(),
                    'next_billing_date' => now()->addMonth(),
                ]);
            }
            
            // Send payment confirmation email (not activation email - that was already sent during registration)
            // Only send payment confirmation to avoid duplicate activation emails
            $this->sendPaymentConfirmationEmail($user, $payment);
            
            // Log the user in after successful payment
            Auth::login($user);
            
            Log::info('Basecamp payment processed successfully', [
                'invoice_id' => $invoiceId,
                'user_id' => $userId,
                'payment_id' => $payment->id,
            ]);
            
            // Clear session data
            session()->forget('basecamp_user_id');
            session()->forget('basecamp_invoice_id');
            
            return redirect()->route('dashboard')
                ->with('status', 'Payment processed successfully! Please check your email to activate your account.');
                
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

