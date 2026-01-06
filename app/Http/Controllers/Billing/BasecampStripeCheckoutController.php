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
            $userId = $request->input('user_id');
            $amount = $request->input('amount', 1000); // Default $10.00
            
            $user = User::findOrFail($userId);
            
            // Create invoice
            $subscription = SubscriptionRecord::firstOrCreate(
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
            
            $invoice = Invoice::create([
                'user_id' => $user->id,
                'organisation_id' => null,
                'subscription_id' => $subscription->id,
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'tier' => 'basecamp',
                'user_count' => 1,
                'price_per_user' => 10,
                'subtotal' => 10,
                'tax_amount' => 0,
                'total_amount' => 10,
                'status' => 'unpaid',
                'due_date' => now()->addDays(7),
                'invoice_date' => now(),
            ]);
            
            // Set Stripe key
            Stripe::setApiKey(config('services.stripe.secret'));
            
            // Create checkout session
            $session = Session::create([
                'payment_method_types' => ['card'],
                'customer_email' => $user->email, // Auto-fill customer email
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => 'Basecamp Subscription',
                            'description' => 'Monthly subscription for Tribe365 Basecamp',
                        ],
                        'unit_amount' => $amount,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('basecamp.billing.payment.success') . '?session_id={CHECKOUT_SESSION_ID}&invoice_id=' . $invoice->id . '&user_id=' . $user->id,
                'cancel_url' => route('basecamp.billing') . '?user_id=' . $user->id,
            ]);
            
            Log::info('Basecamp checkout session created', [
                'session_id' => $session->id,
                'invoice_id' => $invoice->id,
                'user_id' => $user->id,
                'amount' => $amount,
            ]);
            
            return redirect($session->url);
            
        } catch (\Exception $e) {
            Log::error('Failed to create basecamp checkout session: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            
            return back()->with('error', 'Failed to create payment session. Please try again.');
        }
    }
    
    public function redirectToCheckout(Request $request)
    {
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
            
            // Send activation email after payment is completed
            $this->sendActivationEmail($user);
            
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
    
    private function sendActivationEmail($user)
    {
        try {
            $expires = Carbon::now()->addMinutes(1440);
            $verificationUrl = URL::temporarySignedRoute(
                'user.verify', $expires, ['id' => $user->id]
            );
            
            $emailBody = view('emails.verify-user-inline', [
                'user' => $user,
                'verificationUrl' => $verificationUrl,
            ])->render();
            
            $oneSignalService = new OneSignalService();
            $oneSignalService->registerEmailUserFallback($user->email, $user->id, [
                'subject' => 'Activate Your Tribe365 Account',
                'body' => $emailBody,
            ]);
            
            Log::info('Activation email sent to basecamp user after payment', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send activation email after payment: ' . $e->getMessage(), [
                'user_id' => $user->id,
            ]);
        }
    }
}

