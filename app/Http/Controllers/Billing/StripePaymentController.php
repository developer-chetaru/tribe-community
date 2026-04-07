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

/**
 * @OA\Tag(
 *     name="Billing - Stripe Payments",
 *     description="Stripe payment processing endpoints for invoices and subscriptions"
 * )
 */
class StripePaymentController extends Controller
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * @OA\Post(
     *     path="/billing/stripe/payment-intent/create",
     *     tags={"Billing - Stripe Payments"},
     *     summary="Create Stripe payment intent for invoice",
     *     description="Creates a Stripe payment intent for paying an invoice. Requires director or super_admin role.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"invoice_id"},
     *             @OA\Property(property="invoice_id", type="integer", example=1, description="ID of the invoice to pay")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment intent created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="client_secret", type="string", example="pi_1234567890_secret_abc123", description="Stripe payment intent client secret for frontend"),
     *             @OA\Property(property="payment_intent_id", type="string", example="pi_1234567890", description="Stripe payment intent ID")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad request - Failed to create payment intent"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - User does not have permission"),
     *     @OA\Response(response=404, description="Invoice not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
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
                'amount' => $invoice->total_amount * 100, // Convert to cents
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
     * @OA\Post(
     *     path="/billing/stripe/payment/confirm",
     *     tags={"Billing - Stripe Payments"},
     *     summary="Confirm Stripe payment after successful transaction",
     *     description="Confirms a Stripe payment after the payment intent has been successfully processed. Updates invoice status and activates subscription. Requires director or super_admin role.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"payment_intent_id", "invoice_id"},
     *             @OA\Property(property="payment_intent_id", type="string", example="pi_1234567890", description="Stripe payment intent ID"),
     *             @OA\Property(property="invoice_id", type="integer", example=1, description="ID of the invoice being paid")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment confirmed and processed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payment processed successfully. Your subscription has been activated."),
     *             @OA\Property(
     *                 property="payment",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="invoice_id", type="integer", example=1),
     *                 @OA\Property(property="amount", type="number", format="float", example=90.00),
     *                 @OA\Property(property="status", type="string", example="completed"),
     *                 @OA\Property(property="payment_date", type="string", format="date", example="2025-12-24")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad request - Payment not completed or already processed"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - User does not have permission"),
     *     @OA\Response(response=404, description="Invoice not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
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
                'amount' => $paymentIntent->amount / 100, // Convert from cents
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

