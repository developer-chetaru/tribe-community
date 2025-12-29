<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentRecord;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StripeCheckoutController extends Controller
{
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
            if (!$user->hasRole('super_admin') && ($user->hasRole('director') && $invoice->organisation_id !== $user->orgId)) {
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
                // Create payment record
                $payment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'organisation_id' => $invoice->organisation_id,
                    'amount' => $invoice->total_amount,
                    'payment_method' => 'stripe',
                    'status' => 'completed',
                    'transaction_id' => $session->payment_intent,
                    'payment_date' => now(),
                    'payment_notes' => "Payment completed via Stripe Checkout - Session: {$sessionId}",
                    'paid_by_user_id' => $user->id,
                ]);
                
                // Create payment record entry
                PaymentRecord::create([
                    'organisation_id' => $invoice->organisation_id,
                    'subscription_id' => $invoice->subscription_id,
                    'stripe_payment_intent_id' => $session->payment_intent,
                    'amount' => $invoice->total_amount,
                    'currency' => 'gbp',
                    'status' => 'succeeded',
                    'type' => 'one_time_payment',
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
                    'paid_date' => $invoice->paid_date
                ]);
                
                // Activate or renew subscription
                if ($invoice->subscription) {
                    $subscriptionService = new SubscriptionService();
                    $activationResult = $subscriptionService->activateSubscription($payment->id);
                    
                    if (!$activationResult) {
                        Log::warning("Failed to activate subscription for payment {$payment->id}");
                    } else {
                        Log::info("Subscription activated successfully for payment {$payment->id}");
                    }
                } else {
                    Log::warning("Invoice {$invoice->id} has no associated subscription");
                }
                
                DB::commit();
                
                Log::info("Stripe Checkout payment completed for invoice {$invoice->id}: {$session->payment_intent}", [
                    'invoice_status' => $invoice->fresh()->status,
                    'payment_id' => $payment->id,
                    'subscription_id' => $invoice->subscription_id
                ]);
                
                // Force refresh subscription status
                $subscriptionService = new SubscriptionService();
                $subscriptionService->getSubscriptionStatus($organisation->id);
                
                session()->flash('success', 'Payment processed successfully. Your subscription has been activated.');
                session()->put('payment_success', true);
                session()->put('refresh_billing', true);
                
                // Redirect and refresh the billing page
                return redirect()->route('billing');
                
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
}

