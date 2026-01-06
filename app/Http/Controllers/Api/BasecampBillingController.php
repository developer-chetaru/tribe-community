<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SubscriptionRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Checkout\Session as StripeCheckoutSession;

/**
 * @OA\Tag(
 *     name="Basecamp Billing",
 *     description="Billing API endpoints for basecamp users ($10/month subscription)"
 * )
 */
class BasecampBillingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/basecamp/stripe-config",
     *     tags={"Basecamp Billing", "Basecamp Users"},
     *     summary="Get Stripe configuration for mobile app",
     *     description="Retrieve Stripe publishable key and payment configuration for initializing Stripe SDK in mobile applications. This endpoint does not require authentication as it only returns public configuration.",
     *     @OA\Response(
     *         response=200,
     *         description="Stripe configuration retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Stripe configuration retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="publishable_key", type="string", example="pk_test_51...", description="Stripe publishable key for client-side SDK"),
     *                 @OA\Property(property="currency", type="string", example="usd", description="Payment currency"),
     *                 @OA\Property(property="monthly_price", type="number", format="float", example=10.00, description="Monthly subscription price in USD")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve Stripe configuration.")
     *         )
     *     )
     * )
     */
    public function getStripeConfig(Request $request)
    {
        try {
            $publishableKey = config('services.stripe.public');
            
            if (!$publishableKey) {
                return response()->json([
                    'status' => false,
                    'message' => 'Stripe configuration not available.',
                ], 500);
            }

            return response()->json([
                'status' => true,
                'message' => 'Stripe configuration retrieved successfully',
                'data' => [
                    'publishable_key' => $publishableKey,
                    'currency' => 'usd',
                    'monthly_price' => 10.00,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve Stripe config: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve Stripe configuration.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/basecamp/invoices",
     *     tags={"Basecamp Billing", "Basecamp Users"},
     *     summary="Get basecamp user invoices",
     *     description="Retrieve all invoices for the authenticated basecamp user. Returns invoices with status, amounts, and payment information.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter invoices by status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"unpaid", "paid", "pending", "overdue"}, example="unpaid")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoices retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoices retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="invoice_number", type="string", example="INV-202512-0001"),
     *                     @OA\Property(property="tier", type="string", example="basecamp"),
     *                     @OA\Property(property="total_amount", type="number", format="float", example=10.00),
     *                     @OA\Property(property="status", type="string", example="unpaid"),
     *                     @OA\Property(property="invoice_date", type="string", format="date", example="2025-01-05"),
     *                     @OA\Property(property="due_date", type="string", format="date", example="2025-01-12"),
     *                     @OA\Property(property="paid_at", type="string", format="date-time", nullable=true, example="2025-01-05T10:30:00.000000Z")
     *                 )
     *             ),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="total", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized. Please login.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User is not a basecamp user",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Only basecamp users can access this endpoint.")
     *         )
     *     )
     * )
     */
    public function getInvoices(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized. Please login.',
                ], 401);
            }

            // Check if user is basecamp user
            if (!$user->hasRole('basecamp')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Only basecamp users can access this endpoint.',
                ], 403);
            }

            $query = Invoice::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->orderBy('created_at', 'desc');

            // Filter by status if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $invoices = $query->paginate(10);

            return response()->json([
                'status' => true,
                'message' => 'Invoices retrieved successfully',
                'data' => $invoices->items(),
                'user_id' => $user->id, // Include user_id for mobile apps
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve basecamp invoices: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve invoices.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/basecamp/subscription",
     *     tags={"Basecamp Billing", "Basecamp Users"},
     *     summary="Get basecamp user subscription details",
     *     description="Retrieve subscription details for the authenticated basecamp user including status, billing dates, and tier information.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Subscription details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Subscription details retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="tier", type="string", example="basecamp"),
     *                 @OA\Property(property="status", type="string", example="active", enum={"active", "inactive", "cancelled"}),
     *                 @OA\Property(property="user_count", type="integer", example=1),
     *                 @OA\Property(property="current_period_start", type="string", format="date-time", nullable=true, example="2025-01-05T00:00:00.000000Z"),
     *                 @OA\Property(property="current_period_end", type="string", format="date-time", nullable=true, example="2025-02-05T00:00:00.000000Z"),
     *                 @OA\Property(property="next_billing_date", type="string", format="date-time", nullable=true, example="2025-02-05T00:00:00.000000Z"),
     *                 @OA\Property(property="monthly_price", type="number", format="float", example=10.00, description="Monthly subscription price in USD")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized. Please login.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User is not a basecamp user",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Only basecamp users can access this endpoint.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Subscription not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Subscription not found.")
     *         )
     *     )
     * )
     */
    public function getSubscription(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized. Please login.',
                ], 401);
            }

            // Check if user is basecamp user
            if (!$user->hasRole('basecamp')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Only basecamp users can access this endpoint.',
                ], 403);
            }

            $subscription = SubscriptionRecord::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->first();

            if (!$subscription) {
                return response()->json([
                    'status' => false,
                    'message' => 'Subscription not found.',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Subscription details retrieved successfully',
                'data' => [
                    'id' => $subscription->id,
                    'user_id' => $user->id, // Include user_id for mobile apps
                    'tier' => $subscription->tier,
                    'status' => $subscription->status,
                    'user_count' => $subscription->user_count,
                    'current_period_start' => $subscription->current_period_start,
                    'current_period_end' => $subscription->current_period_end,
                    'next_billing_date' => $subscription->next_billing_date,
                    'monthly_price' => 10.00, // Basecamp subscription is $10/month
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve basecamp subscription: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve subscription details.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/basecamp/payment-intent",
     *     tags={"Basecamp Billing", "Basecamp Users"},
     *     summary="Create Stripe payment intent for basecamp invoice",
     *     description="Create a Stripe payment intent for a basecamp user's invoice. Returns client_secret for Stripe.js integration. Requires an unpaid invoice.",
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
     *             @OA\Property(property="message", type="string", example="Payment intent created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="client_secret", type="string", example="pi_1234567890_secret_...", description="Stripe payment intent client secret for frontend integration"),
     *                 @OA\Property(property="payment_intent_id", type="string", example="pi_1234567890", description="Stripe payment intent ID"),
     *                 @OA\Property(property="amount", type="number", format="float", example=10.00, description="Payment amount in USD"),
     *                 @OA\Property(property="currency", type="string", example="usd")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - Invoice not found or already paid",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invoice not found or already paid.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized. Please login.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User is not a basecamp user or invoice doesn't belong to user",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You can only pay your own invoices.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to create payment intent."),
     *             @OA\Property(property="error", type="string", example="Error message")
     *         )
     *     )
     * )
     */
    public function createPaymentIntent(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized. Please login.',
                ], 401);
            }

            // Check if user is basecamp user
            if (!$user->hasRole('basecamp')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Only basecamp users can access this endpoint.',
                ], 403);
            }

            $request->validate([
                'invoice_id' => 'required|integer|exists:invoices,id',
            ]);

            // Get invoice
            $invoice = Invoice::findOrFail($request->invoice_id);

            // Verify invoice belongs to user
            if ($invoice->user_id !== $user->id || $invoice->tier !== 'basecamp') {
                return response()->json([
                    'status' => false,
                    'message' => 'You can only pay your own invoices.',
                ], 403);
            }

            // Check if invoice is unpaid
            if ($invoice->status !== 'unpaid') {
                return response()->json([
                    'status' => false,
                    'message' => 'Invoice is already paid or not available for payment.',
                ], 400);
            }

            // Initialize Stripe
            if (!class_exists(Stripe::class)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Stripe SDK not available.',
                ], 500);
            }

            Stripe::setApiKey(config('services.stripe.secret'));

            // Create payment intent
            $paymentIntent = PaymentIntent::create([
                'amount' => (int)($invoice->total_amount * 100), // Convert to cents
                'currency' => 'usd',
                'payment_method_types' => ['card'],
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'user_id' => $user->id,
                    'tier' => 'basecamp',
                    'description' => "Basecamp subscription - $10/month",
                ],
                'description' => "Basecamp Subscription - {$user->first_name} {$user->last_name}",
            ]);

            Log::info('Stripe payment intent created for basecamp invoice', [
                'payment_intent_id' => $paymentIntent->id,
                'invoice_id' => $invoice->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Payment intent created successfully',
                'data' => [
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                    'invoice_id' => $invoice->id,
                    'user_id' => $user->id, // Include user_id for mobile apps
                    'amount' => $invoice->total_amount,
                    'currency' => 'usd',
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invoice not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to create payment intent for basecamp invoice: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to create payment intent.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/basecamp/confirm-payment",
     *     tags={"Basecamp Billing", "Basecamp Users"},
     *     summary="Confirm payment for basecamp invoice",
     *     description="Confirm and process payment for a basecamp invoice after successful Stripe payment. Updates invoice status, creates payment record, and activates subscription.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"invoice_id", "payment_intent_id"},
     *             @OA\Property(property="invoice_id", type="integer", example=1, description="ID of the invoice being paid"),
     *             @OA\Property(property="payment_intent_id", type="string", example="pi_1234567890", description="Stripe payment intent ID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment confirmed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payment confirmed successfully. Your subscription is now active."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="payment_id", type="integer", example=1),
     *                 @OA\Property(property="invoice_id", type="integer", example=1),
     *                 @OA\Property(property="invoice_status", type="string", example="paid"),
     *                 @OA\Property(property="subscription_status", type="string", example="active")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - Payment already processed or invalid payment intent",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Payment already processed or invalid payment intent.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized. Please login.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You can only confirm payments for your own invoices.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to confirm payment."),
     *             @OA\Property(property="error", type="string", example="Error message")
     *         )
     *     )
     * )
     */
    public function confirmPayment(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized. Please login.',
                ], 401);
            }

            // Check if user is basecamp user
            if (!$user->hasRole('basecamp')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Only basecamp users can access this endpoint.',
                ], 403);
            }

            $request->validate([
                'invoice_id' => 'required|integer|exists:invoices,id',
                'payment_intent_id' => 'required|string',
            ]);

            // Get invoice
            $invoice = Invoice::findOrFail($request->invoice_id);

            // Verify invoice belongs to user
            if ($invoice->user_id !== $user->id || $invoice->tier !== 'basecamp') {
                return response()->json([
                    'status' => false,
                    'message' => 'You can only confirm payments for your own invoices.',
                ], 403);
            }

            // Check if payment already exists
            $existingPayment = Payment::where('invoice_id', $invoice->id)
                ->where('transaction_id', $request->payment_intent_id)
                ->first();

            if ($existingPayment) {
                return response()->json([
                    'status' => false,
                    'message' => 'Payment already processed.',
                ], 400);
            }

            // Initialize Stripe and verify payment intent
            if (!class_exists(Stripe::class)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Stripe SDK not available.',
                ], 500);
            }

            Stripe::setApiKey(config('services.stripe.secret'));

            $paymentIntent = PaymentIntent::retrieve($request->payment_intent_id);

            if ($paymentIntent->status !== 'succeeded') {
                return response()->json([
                    'status' => false,
                    'message' => 'Payment intent not succeeded. Payment may have failed.',
                ], 400);
            }

            // Process payment in transaction
            $payment = DB::transaction(function () use ($invoice, $user, $request, $paymentIntent) {
                // Create payment record
                $payment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'user_id' => $user->id,
                    'organisation_id' => null, // Basecamp users don't have organisation
                    'payment_method' => 'card',
                    'amount' => $invoice->total_amount,
                    'transaction_id' => $request->payment_intent_id,
                    'status' => 'completed',
                    'payment_date' => now()->toDateString(),
                    'payment_notes' => 'Basecamp subscription payment via Stripe',
                ]);

                // Update invoice status
                $invoice->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                // Get or create subscription
                $subscription = SubscriptionRecord::where('user_id', $user->id)
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

                Log::info('Basecamp payment confirmed successfully', [
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'user_id' => $user->id,
                ]);

                return $payment;
            });

            // Get updated subscription status after transaction
            $subscription = SubscriptionRecord::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->first();

            // Get updated subscription status
            $subscription = SubscriptionRecord::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->first();

            return response()->json([
                'status' => true,
                'message' => 'Payment confirmed successfully. Your subscription is now active.',
                'data' => [
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'user_id' => $user->id, // Include user_id for mobile apps
                    'invoice_status' => 'paid',
                    'subscription_status' => $subscription->status ?? 'active',
                    'subscription_id' => $subscription->id ?? null,
                    'amount' => $payment->amount,
                    'transaction_id' => $payment->transaction_id,
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invoice not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to confirm basecamp payment: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to confirm payment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get upcoming billing information
     */
    public function getUpcoming(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user || !$user->hasRole('basecamp')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized. Only basecamp users can access this endpoint.',
                ], 403);
            }

            $subscription = SubscriptionRecord::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->first();

            if (!$subscription) {
                return response()->json([
                    'status' => false,
                    'message' => 'No subscription found.',
                ], 404);
            }

            // Get next unpaid invoice
            $upcomingInvoice = Invoice::where('subscription_id', $subscription->id)
                ->where('status', 'unpaid')
                ->orderBy('due_date', 'asc')
                ->first();

            return response()->json([
                'status' => true,
                'data' => [
                    'next_billing_date' => $subscription->next_billing_date?->toDateString(),
                    'amount' => $upcomingInvoice ? $upcomingInvoice->total_amount : 10.00,
                    'currency' => 'USD',
                    'billing_period_start' => $subscription->current_period_start?->toDateString(),
                    'billing_period_end' => $subscription->current_period_end?->toDateString(),
                    'invoice_id' => $upcomingInvoice?->id,
                    'invoice_number' => $upcomingInvoice?->invoice_number,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get upcoming billing: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve upcoming billing information.',
            ], 500);
        }
    }

    /**
     * Get invoice details
     */
    public function getInvoice($id, Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user || !$user->hasRole('basecamp')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized.',
                ], 403);
            }

            $invoice = Invoice::with(['subscription', 'payments'])
                ->where('id', $id)
                ->where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->first();

            if (!$invoice) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invoice not found.',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'amount' => $invoice->total_amount,
                    'status' => $invoice->status,
                    'due_date' => $invoice->due_date?->toDateString(),
                    'paid_date' => $invoice->paid_date?->toDateString(),
                    'invoice_date' => $invoice->invoice_date?->toDateString(),
                    'payments' => $invoice->payments->map(function($payment) {
                        return [
                            'id' => $payment->id,
                            'amount' => $payment->amount,
                            'payment_method' => $payment->payment_method,
                            'payment_date' => $payment->payment_date?->toDateString(),
                            'status' => $payment->status,
                        ];
                    }),
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get invoice: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve invoice.',
            ], 500);
        }
    }

    /**
     * Pay now for unpaid invoice
     */
    public function payNow(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user || !$user->hasRole('basecamp')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized.',
                ], 403);
            }

            $invoiceId = $request->input('invoice_id');
            $invoice = Invoice::where('id', $invoiceId)
                ->where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->where('status', 'unpaid')
                ->first();

            if (!$invoice) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invoice not found or already paid.',
                ], 404);
            }

            // Create payment intent
            Stripe::setApiKey(config('services.stripe.secret'));
            
            $paymentIntent = PaymentIntent::create([
                'amount' => $invoice->total_amount * 100,
                'currency' => 'usd',
                'customer' => $user->stripe_customer_id,
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'user_id' => $user->id,
                    'tier' => 'basecamp',
                ],
            ]);

            return response()->json([
                'status' => true,
                'data' => [
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to create payment intent: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to initialize payment.',
            ], 500);
        }
    }

    /**
     * Update payment method
     */
    public function updatePaymentMethod(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user || !$user->hasRole('basecamp')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized.',
                ], 403);
            }

            $paymentMethodId = $request->input('payment_method_id');
            
            if (!$paymentMethodId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Payment method ID is required.',
                ], 400);
            }

            Stripe::setApiKey(config('services.stripe.secret'));
            
            // Attach payment method to customer
            $paymentMethod = \Stripe\PaymentMethod::retrieve($paymentMethodId);
            $paymentMethod->attach(['customer' => $user->stripe_customer_id]);

            // Set as default
            \Stripe\Customer::update($user->stripe_customer_id, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ]);

            // If there's an unpaid invoice, retry payment
            $subscription = SubscriptionRecord::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->first();

            if ($subscription) {
                $unpaidInvoice = Invoice::where('subscription_id', $subscription->id)
                    ->where('status', 'unpaid')
                    ->orderBy('due_date', 'asc')
                    ->first();

                if ($unpaidInvoice) {
                    // Attempt to charge the new payment method
                    try {
                        $paymentIntent = PaymentIntent::create([
                            'amount' => $unpaidInvoice->total_amount * 100,
                            'currency' => 'usd',
                            'customer' => $user->stripe_customer_id,
                            'payment_method' => $paymentMethodId,
                            'confirm' => true,
                            'metadata' => [
                                'invoice_id' => $unpaidInvoice->id,
                                'user_id' => $user->id,
                                'tier' => 'basecamp',
                                'is_retry' => 'true',
                            ],
                        ]);

                        if ($paymentIntent->status === 'succeeded') {
                            // Payment succeeded - update invoice
                            $unpaidInvoice->update([
                                'status' => 'paid',
                                'paid_date' => now(),
                            ]);

                            // Create payment record
                            Payment::create([
                                'invoice_id' => $unpaidInvoice->id,
                                'user_id' => $user->id,
                                'payment_method' => 'stripe',
                                'amount' => $unpaidInvoice->total_amount,
                                'transaction_id' => $paymentIntent->id,
                                'status' => 'completed',
                                'payment_date' => now(),
                            ]);

                            // Update subscription
                            $subscription->update([
                                'status' => 'active',
                                'payment_failed_count' => 0,
                            ]);

                            // Update user
                            $user->update([
                                'payment_grace_period_start' => null,
                                'last_payment_failure_date' => null,
                                'status' => $user->email_verified_at ? 'active_verified' : 'active_unverified',
                            ]);

                            return response()->json([
                                'status' => true,
                                'message' => 'Payment method updated and payment processed successfully.',
                                'data' => [
                                    'payment_succeeded' => true,
                                    'invoice_id' => $unpaidInvoice->id,
                                ],
                            ], 200);
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to retry payment after method update: ' . $e->getMessage());
                    }
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Payment method updated successfully.',
                'data' => [
                    'payment_method_id' => $paymentMethodId,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to update payment method: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to update payment method.',
            ], 500);
        }
    }

    /**
     * Reactivate account
     */
    public function reactivate(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user || !$user->hasRole('basecamp')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized.',
                ], 403);
            }

            if ($user->status !== 'suspended') {
                return response()->json([
                    'status' => false,
                    'message' => 'Account is not suspended.',
                ], 400);
            }

            // Get unpaid invoice
            $subscription = SubscriptionRecord::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->first();

            if (!$subscription) {
                return response()->json([
                    'status' => false,
                    'message' => 'No subscription found.',
                ], 404);
            }

            $unpaidInvoice = Invoice::where('subscription_id', $subscription->id)
                ->where('status', 'unpaid')
                ->orderBy('due_date', 'asc')
                ->first();

            if (!$unpaidInvoice) {
                return response()->json([
                    'status' => false,
                    'message' => 'No unpaid invoice found.',
                ], 404);
            }

            // Create payment intent for reactivation
            Stripe::setApiKey(config('services.stripe.secret'));
            
            $paymentIntent = PaymentIntent::create([
                'amount' => $unpaidInvoice->total_amount * 100,
                'currency' => 'usd',
                'customer' => $user->stripe_customer_id,
                'metadata' => [
                    'invoice_id' => $unpaidInvoice->id,
                    'user_id' => $user->id,
                    'tier' => 'basecamp',
                    'is_reactivation' => 'true',
                ],
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Payment intent created for reactivation.',
                'data' => [
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                    'invoice_id' => $unpaidInvoice->id,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to reactivate account: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to initiate reactivation.',
            ], 500);
        }
    }

    /**
     * Cancel subscription
     */
    public function cancel(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user || !$user->hasRole('basecamp')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized.',
                ], 403);
            }

            $subscription = SubscriptionRecord::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->first();

            if (!$subscription) {
                return response()->json([
                    'status' => false,
                    'message' => 'No subscription found.',
                ], 404);
            }

            // Cancel in Stripe if subscription exists
            if ($subscription->stripe_subscription_id) {
                Stripe::setApiKey(config('services.stripe.secret'));
                $stripeSubscription = \Stripe\Subscription::retrieve($subscription->stripe_subscription_id);
                $stripeSubscription->cancel(['at_period_end' => true]);
            }

            // Update subscription status
            $subscription->update([
                'status' => 'cancel_at_period_end',
                'canceled_at' => now(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Subscription cancelled successfully. You will continue to have access until the end of your billing period.',
                'data' => [
                    'subscription_id' => $subscription->id,
                    'canceled_at' => $subscription->canceled_at?->toIso8601String(),
                    'next_billing_date' => $subscription->next_billing_date?->toDateString(),
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to cancel subscription: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to cancel subscription.',
            ], 500);
        }
    }
}

