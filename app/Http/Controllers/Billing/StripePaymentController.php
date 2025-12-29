<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\StripeService;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\Payment;
use App\Models\PaymentRecord;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StripePaymentController extends Controller
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Create payment intent for invoice payment
     */
    public function createPaymentIntent(Request $request)
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
        ]);

        $user = Auth::user();
        $invoice = Invoice::with('subscription')->findOrFail($validated['invoice_id']);

        // Authorization check
        if (!$user->hasRole('super_admin') && ($user->hasRole('director') && $invoice->organisation_id !== $user->orgId)) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        try {
            $organisation = Organisation::findOrFail($invoice->organisation_id);

            // Ensure customer exists
            if (!$organisation->stripe_customer_id) {
                $customerResult = $this->stripeService->createCustomer($organisation);
                if (!$customerResult['success']) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Failed to create customer. Please try again.',
                    ], 400);
                }
            }

            // Create payment intent
            if (!class_exists(\Stripe\PaymentIntent::class)) {
                throw new \Exception('Stripe PHP package is not installed.');
            }

            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $invoice->total_amount * 100, // Convert to pence
                'currency' => 'gbp',
                'customer' => $organisation->stripe_customer_id,
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'organisation_id' => $organisation->id,
                    'invoice_number' => $invoice->invoice_number,
                ],
                'description' => "Payment for Invoice #{$invoice->invoice_number}",
            ]);

            return response()->json([
                'status' => true,
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Stripe Payment Intent Creation Failed: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to create payment intent: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Confirm payment after Stripe payment is successful
     */
    public function confirmPayment(Request $request)
    {
        $validated = $request->validate([
            'payment_intent_id' => 'required|string',
            'invoice_id' => 'required|exists:invoices,id',
        ]);

        $user = Auth::user();
        $invoice = Invoice::with('subscription')->findOrFail($validated['invoice_id']);

        // Authorization check
        if (!$user->hasRole('super_admin') && ($user->hasRole('director') && $invoice->organisation_id !== $user->orgId)) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        try {
            // Retrieve payment intent from Stripe
            if (!class_exists(\Stripe\PaymentIntent::class)) {
                throw new \Exception('Stripe PHP package is not installed.');
            }

            $paymentIntent = \Stripe\PaymentIntent::retrieve($validated['payment_intent_id']);

            if ($paymentIntent->status !== 'succeeded') {
                return response()->json([
                    'status' => false,
                    'message' => 'Payment not completed. Status: ' . $paymentIntent->status,
                ], 400);
            }

            // Use database transaction to prevent duplicate payments
            DB::beginTransaction();
            
            try {
                // Check again if payment already exists (race condition protection)
                $existingPayment = Payment::where('invoice_id', $invoice->id)
                    ->where('status', 'completed')
                    ->lockForUpdate()
                    ->first();
                
                if ($existingPayment) {
                    DB::rollBack();
                    return response()->json([
                        'status' => false,
                        'message' => 'Payment already processed for this invoice.',
                    ], 400);
                }

                // Check if PaymentRecord already exists for this payment intent
                $existingPaymentRecord = PaymentRecord::where('stripe_payment_intent_id', $paymentIntent->id)
                    ->first();
                
                if ($existingPaymentRecord) {
                    DB::rollBack();
                    return response()->json([
                        'status' => false,
                        'message' => 'This payment has already been processed.',
                    ], 400);
                }

                // Create payment record
                $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'organisation_id' => $invoice->organisation_id,
                'paid_by_user_id' => $user->id,
                'payment_method' => 'card',
                'amount' => $paymentIntent->amount / 100, // Convert from pence
                'transaction_id' => $paymentIntent->id,
                'status' => 'completed',
                'payment_date' => now()->toDateString(),
                'payment_notes' => 'Payment processed via Stripe',
                'approved_by_admin_id' => null,
                'approved_at' => now(),
            ]);

            // Create payment record for Stripe tracking
            PaymentRecord::create([
                'organisation_id' => $invoice->organisation_id,
                'stripe_payment_intent_id' => $paymentIntent->id,
                'stripe_customer_id' => $paymentIntent->customer,
                'amount' => $paymentIntent->amount / 100,
                'currency' => $paymentIntent->currency,
                'status' => $paymentIntent->status,
                'type' => 'payment',
                'paid_at' => now(),
            ]);

            // Update invoice status
            $invoice->update([
                'status' => 'paid',
                'paid_date' => now(),
            ]);

                // Activate/renew subscription
                $subscriptionService = new SubscriptionService();
                if ($invoice->subscription) {
                    $userCount = $invoice->user_count;
                    $pricePerUser = $invoice->price_per_user;
                    $subscriptionService->renewSubscription($invoice->subscription, $userCount, $pricePerUser);
                } else {
                    $subscriptionService->activateSubscription($payment->id);
                }

                DB::commit();
                
                Log::info("Stripe payment confirmed for invoice {$invoice->id}: {$paymentIntent->id}");

                return response()->json([
                    'status' => true,
                    'message' => 'Payment processed successfully. Your subscription has been activated.',
                    'payment' => $payment,
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Stripe Payment Confirmation Failed: ' . $e->getMessage());
                return response()->json([
                    'status' => false,
                    'message' => 'Payment confirmation failed: ' . $e->getMessage(),
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Stripe Payment Confirmation Failed: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Payment confirmation failed: ' . $e->getMessage(),
            ], 400);
        }
    }
}

