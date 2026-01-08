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
 *     description="Billing API endpoints for basecamp users (£10/month subscription)"
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
 *                 @OA\Property(property="currency", type="string", example="gbp", description="Payment currency"),
 *                 @OA\Property(property="monthly_price", type="number", format="float", example=10.00, description="Monthly subscription price in GBP")
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
                    'currency' => 'gbp',
                    'monthly_price' => 10.00,
                    'monthly_price_with_vat' => 12.00, // £10 + 20% VAT (£2.00)
                    'vat_rate' => 20.00, // VAT percentage
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
     *                     @OA\Property(property="subtotal", type="number", format="float", example=10.00, description="Amount before VAT"),
     *                     @OA\Property(property="tax_amount", type="number", format="float", example=2.00, description="VAT amount (20%)"),
     *                     @OA\Property(property="total_amount", type="number", format="float", example=12.00, description="Total amount including VAT"),
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
     *                 @OA\Property(property="user_id", type="integer", example=5, description="User ID for mobile apps"),
     *                 @OA\Property(property="tier", type="string", example="basecamp"),
     *                 @OA\Property(property="status", type="string", example="active", enum={"active", "inactive", "cancelled", "canceled", "cancel_at_period_end"}),
     *                 @OA\Property(property="user_count", type="integer", example=1),
     *                 @OA\Property(property="current_period_start", type="string", format="date-time", nullable=true, example="2025-01-07T00:00:00.000000Z"),
     *                 @OA\Property(property="current_period_end", type="string", format="date-time", nullable=true, example="2025-02-07T00:00:00.000000Z"),
     *                 @OA\Property(property="next_billing_date", type="string", format="date-time", nullable=true, example="2025-02-07T00:00:00.000000Z"),
     *                 @OA\Property(property="monthly_price", type="number", format="float", example=10.00, description="Monthly subscription price in GBP (before VAT)"),
     *                 @OA\Property(property="monthly_price_with_vat", type="number", format="float", example=12.00, description="Monthly subscription price including 20% VAT in GBP"),
     *                 @OA\Property(property="vat_rate", type="number", format="float", example=20.00, description="VAT percentage"),
     *                 @OA\Property(
     *                     property="stripe_subscription",
     *                     type="object",
     *                     nullable=true,
     *                     description="Stripe subscription details (if available)",
     *                     @OA\Property(property="id", type="string", example="sub_1SnFnILsZTe0ouTrgXo1ea0w", description="Stripe subscription ID"),
     *                     @OA\Property(property="status", type="string", example="active", description="Stripe subscription status"),
     *                     @OA\Property(property="current_period_start", type="string", format="date-time", nullable=true, example="2025-01-07T00:00:00.000000Z"),
     *                     @OA\Property(property="current_period_end", type="string", format="date-time", nullable=true, example="2025-02-07T00:00:00.000000Z"),
     *                     @OA\Property(property="cancel_at_period_end", type="boolean", example=false, description="Will cancel at period end"),
     *                     @OA\Property(property="canceled_at", type="string", format="date-time", nullable=true, example=null, description="Cancellation timestamp if cancelled")
     *                 ),
     *                 @OA\Property(
     *                     property="payment_method",
     *                     type="object",
     *                     nullable=true,
     *                     description="Payment method details (if available)",
     *                     @OA\Property(property="id", type="string", example="pm_1SnFnILsZTe0ouTrgXo1ea0w", description="Payment method ID"),
     *                     @OA\Property(property="type", type="string", example="card", description="Payment method type"),
     *                     @OA\Property(
     *                         property="card",
     *                         type="object",
     *                         @OA\Property(property="brand", type="string", example="visa", description="Card brand (visa, mastercard, amex)"),
     *                         @OA\Property(property="last4", type="string", example="4242", description="Last 4 digits of card"),
     *                         @OA\Property(property="exp_month", type="integer", example=12, description="Expiry month (1-12)"),
     *                         @OA\Property(property="exp_year", type="integer", example=2029, description="Expiry year"),
     *                         @OA\Property(property="funding", type="string", example="credit", description="Card funding type (credit, debit)")
     *                     )
     *                 )
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

            // Fetch Stripe subscription details and payment method
            $stripeSubscription = null;
            $paymentMethod = null;
            $stripeSubscriptionId = $subscription->stripe_subscription_id;

            if ($stripeSubscriptionId && class_exists(\Stripe\Stripe::class)) {
                try {
                    \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                    
                    // Get Stripe subscription
                    $stripeSubscription = \Stripe\Subscription::retrieve($stripeSubscriptionId);
                    
                    // Get payment method from subscription's default payment method
                    if ($stripeSubscription->default_payment_method) {
                        $paymentMethod = \Stripe\PaymentMethod::retrieve($stripeSubscription->default_payment_method);
                    } elseif ($stripeSubscription->customer) {
                        // Try to get payment method from customer
                        $customer = \Stripe\Customer::retrieve($stripeSubscription->customer);
                        if ($customer->invoice_settings->default_payment_method) {
                            $paymentMethod = \Stripe\PaymentMethod::retrieve($customer->invoice_settings->default_payment_method);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to retrieve Stripe subscription details: ' . $e->getMessage(), [
                        'subscription_id' => $subscription->id,
                        'stripe_subscription_id' => $stripeSubscriptionId,
                    ]);
                    // Continue without Stripe details if API call fails
                }
            }

            // Build response data
            $responseData = [
                'id' => $subscription->id,
                'user_id' => $user->id, // Include user_id for mobile apps
                'tier' => $subscription->tier,
                'status' => $subscription->status,
                'user_count' => $subscription->user_count,
                'current_period_start' => $subscription->current_period_start,
                'current_period_end' => $subscription->current_period_end,
                'next_billing_date' => $subscription->next_billing_date,
                'monthly_price' => 10.00, // Basecamp subscription is £10/month (before VAT)
                'monthly_price_with_vat' => 12.00, // £10 + 20% VAT (£2.00)
                'vat_rate' => 20.00, // VAT percentage
            ];

            // Add Stripe subscription details if available
            if ($stripeSubscription) {
                $responseData['stripe_subscription'] = [
                    'id' => $stripeSubscription->id,
                    'status' => $stripeSubscription->status,
                    'current_period_start' => $stripeSubscription->current_period_start ? date('Y-m-d\TH:i:s.000000\Z', $stripeSubscription->current_period_start) : null,
                    'current_period_end' => $stripeSubscription->current_period_end ? date('Y-m-d\TH:i:s.000000\Z', $stripeSubscription->current_period_end) : null,
                    'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end ?? false,
                    'canceled_at' => $stripeSubscription->canceled_at ? date('Y-m-d\TH:i:s.000000\Z', $stripeSubscription->canceled_at) : null,
                ];
            }

            // Add payment method details if available
            if ($paymentMethod && isset($paymentMethod->card)) {
                $responseData['payment_method'] = [
                    'id' => $paymentMethod->id,
                    'type' => $paymentMethod->type,
                    'card' => [
                        'brand' => $paymentMethod->card->brand ?? null,
                        'last4' => $paymentMethod->card->last4 ?? null,
                        'exp_month' => $paymentMethod->card->exp_month ?? null,
                        'exp_year' => $paymentMethod->card->exp_year ?? null,
                        'funding' => $paymentMethod->card->funding ?? null, // credit, debit, etc.
                    ],
                ];
            }

            return response()->json([
                'status' => true,
                'message' => 'Subscription details retrieved successfully',
                'data' => $responseData,
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
     *                 @OA\Property(property="invoice_id", type="integer", example=1, description="Invoice ID"),
     *                 @OA\Property(property="user_id", type="integer", example=1, description="User ID"),
     *                 @OA\Property(property="email", type="string", format="email", example="user@example.com", description="User email address for payment processing"),
     *                 @OA\Property(property="amount", type="number", format="float", example=12.00, description="Payment amount in GBP (includes 20% VAT)"),
     *                 @OA\Property(property="currency", type="string", example="gbp", description="Payment currency")
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
                'currency' => 'gbp',
                'payment_method_types' => ['card'],
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'user_id' => $user->id,
                    'tier' => 'basecamp',
                    'description' => "Basecamp subscription - £10/month",
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
                    'email' => $user->email, // Include email for payment processing
                    'amount' => $invoice->total_amount,
                    'currency' => 'gbp',
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
     * @OA\Get(
     *     path="/api/basecamp/invoice/{id}/view",
     *     tags={"Basecamp Billing", "Basecamp Users"},
     *     summary="View invoice details",
     *     description="Retrieve detailed invoice information including invoice items, payment details, subscription information, and payment method details. Returns complete invoice data for viewing in mobile app.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Invoice ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoice details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoice details retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="invoice_number", type="string", example="INV-202512-0001"),
     *                 @OA\Property(property="tier", type="string", example="basecamp"),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="subscription_id", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="user_count", type="integer", example=1),
     *                 @OA\Property(property="price_per_user", type="number", format="float", example=10.00, description="Price per user before VAT"),
     *                 @OA\Property(property="subtotal", type="number", format="float", example=10.00, description="Subtotal before VAT"),
     *                 @OA\Property(property="tax_amount", type="number", format="float", example=2.00, description="VAT amount (20%)"),
     *                 @OA\Property(property="total_amount", type="number", format="float", example=12.00, description="Total amount including VAT"),
     *                 @OA\Property(property="status", type="string", example="paid", enum={"unpaid", "paid", "pending", "overdue"}),
     *                 @OA\Property(property="invoice_date", type="string", format="date", example="2025-01-05"),
     *                 @OA\Property(property="due_date", type="string", format="date", example="2025-01-12"),
     *                 @OA\Property(property="paid_at", type="string", format="date-time", nullable=true, example="2025-01-05T10:30:00.000000Z"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-05T10:30:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-05T10:30:00.000000Z"),
     *                 @OA\Property(
     *                     property="subscription",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="tier", type="string", example="basecamp"),
     *                     @OA\Property(property="status", type="string", example="active")
     *                 ),
     *                 @OA\Property(
     *                     property="payments",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="amount", type="number", format="float", example=12.00, description="Payment amount including VAT"),
     *                         @OA\Property(property="payment_method", type="string", example="card"),
     *                         @OA\Property(property="transaction_id", type="string", example="pi_1234567890"),
     *                         @OA\Property(property="status", type="string", example="completed"),
     *                         @OA\Property(property="payment_date", type="string", format="date", example="2025-01-05")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="first_name", type="string", example="John"),
     *                     @OA\Property(property="last_name", type="string", example="Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com")
     *                 )
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
     *         description="Forbidden - User cannot access this invoice",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You can only view your own invoices.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Invoice not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invoice not found.")
     *         )
     *     )
     * )
     */
    public function viewInvoice($id)
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

            $invoice = Invoice::with(['subscription', 'payments', 'user'])
                ->where('id', $id)
                ->where('tier', 'basecamp')
                ->first();

            if (!$invoice) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invoice not found.',
                ], 404);
            }

            // Verify invoice belongs to user
            if ($invoice->user_id !== $user->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'You can only view your own invoices.',
                ], 403);
            }

            return response()->json([
                'status' => true,
                'message' => 'Invoice details retrieved successfully',
                'data' => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'tier' => $invoice->tier,
                    'user_id' => $invoice->user_id,
                    'subscription_id' => $invoice->subscription_id,
                    'user_count' => $invoice->user_count,
                    'price_per_user' => (float) $invoice->price_per_user,
                    'subtotal' => (float) $invoice->subtotal,
                    'tax_amount' => (float) $invoice->tax_amount,
                    'total_amount' => (float) $invoice->total_amount,
                    'status' => $invoice->status,
                    'invoice_date' => $invoice->invoice_date ? $invoice->invoice_date->format('Y-m-d') : null,
                    'due_date' => $invoice->due_date ? $invoice->due_date->format('Y-m-d') : null,
                    'paid_at' => $invoice->paid_at ? $invoice->paid_at->toIso8601String() : null,
                    'created_at' => $invoice->created_at ? $invoice->created_at->toIso8601String() : null,
                    'updated_at' => $invoice->updated_at ? $invoice->updated_at->toIso8601String() : null,
                    'subscription' => $invoice->subscription ? [
                        'id' => $invoice->subscription->id,
                        'tier' => $invoice->subscription->tier,
                        'status' => $invoice->subscription->status,
                        'current_period_start' => $invoice->subscription->current_period_start ? $invoice->subscription->current_period_start->toIso8601String() : null,
                        'current_period_end' => $invoice->subscription->current_period_end ? $invoice->subscription->current_period_end->toIso8601String() : null,
                    ] : null,
                    'payments' => $invoice->payments->map(function ($payment) {
                        return [
                            'id' => $payment->id,
                            'amount' => (float) $payment->amount,
                            'payment_method' => $payment->payment_method,
                            'transaction_id' => $payment->transaction_id,
                            'status' => $payment->status,
                            'payment_date' => $payment->payment_date ? $payment->payment_date->format('Y-m-d') : null,
                            'created_at' => $payment->created_at ? $payment->created_at->toIso8601String() : null,
                        ];
                    }),
                    'user' => $invoice->user ? [
                        'id' => $invoice->user->id,
                        'first_name' => $invoice->user->first_name,
                        'last_name' => $invoice->user->last_name,
                        'email' => $invoice->user->email,
                    ] : null,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve invoice details: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve invoice details.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/basecamp/invoice/{id}/download",
     *     tags={"Basecamp Billing", "Basecamp Users"},
     *     summary="Download invoice as PDF/HTML",
     *     description="Download invoice as HTML file that can be printed as PDF. Returns invoice HTML content with proper headers for download. Mobile apps can save this HTML and convert to PDF or display in webview.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Invoice ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoice downloaded successfully",
     *         @OA\MediaType(
     *             mediaType="text/html",
     *             @OA\Schema(
     *                 type="string",
     *                 description="HTML content of the invoice"
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
     *         description="Forbidden - User cannot download this invoice",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You can only download your own invoices.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Invoice not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invoice not found.")
     *         )
     *     )
     * )
     */
    public function downloadInvoice($id)
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

            $invoice = Invoice::with(['organisation', 'subscription', 'payments.paidBy', 'user'])
                ->where('id', $id)
                ->where('tier', 'basecamp')
                ->first();

            if (!$invoice) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invoice not found.',
                ], 404);
            }

            // Verify invoice belongs to user
            if ($invoice->user_id !== $user->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'You can only download your own invoices.',
                ], 403);
            }

            // Load Stripe payment method details if needed
            if (method_exists(\App\Http\Controllers\InvoiceController::class, 'loadStripePaymentMethods')) {
                $invoiceController = new \App\Http\Controllers\InvoiceController();
                $invoiceController->loadStripePaymentMethods($invoice->payments);
            }

            // For basecamp users, get user instead of organisation
            $invoiceUser = $invoice->user_id ? \App\Models\User::find($invoice->user_id) : null;
            
            // Return HTML view with download headers (can be printed as PDF by browser)
            $html = view('invoices.pdf', [
                'invoice' => $invoice,
                'organisation' => $invoice->organisation,
                'user' => $invoiceUser, // For basecamp users
                'subscription' => $invoice->subscription,
                'payments' => $invoice->payments,
            ])->render();

            return response($html)
                ->header('Content-Type', 'text/html')
                ->header('Content-Disposition', 'attachment; filename="invoice-' . $invoice->invoice_number . '.html"');

        } catch (\Exception $e) {
            Log::error('Failed to download invoice: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to download invoice.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/basecamp/cancel-subscription",
     *     tags={"Basecamp Billing", "Basecamp Users", "Billing - Subscription Management"},
     *     summary="Cancel basecamp user subscription",
     *     description="Cancel the subscription for the authenticated basecamp user. By default, subscription will be cancelled at the end of the billing period (cancel_at_period_end=true). User can continue using the service until the end date. Works for mobile apps.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="cancel_at_period_end", type="boolean", example=true, description="If true (default), cancels at end of billing period. Subscription remains active until end date. If false, cancels immediately and stops all future payments.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subscription cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Your subscription has been cancelled successfully. Monthly payments have been stopped."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="subscription_id", type="integer", example=1),
     *                 @OA\Property(property="stripe_subscription_id", type="string", nullable=true, example="sub_1234567890"),
     *                 @OA\Property(property="status", type="string", example="canceled", enum={"canceled", "cancel_at_period_end"}),
     *                 @OA\Property(property="canceled_at", type="string", format="date-time", example="2025-01-07T10:30:00.000000Z"),
     *                 @OA\Property(property="current_period_end", type="string", format="date", example="2026-03-08", description="Subscription end date - user can still use service until this date"),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="cancel_at_period_end", type="boolean", example=true, description="Whether subscription will cancel at period end")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - No active subscription found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No active subscription found to cancel.")
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
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to cancel subscription."),
     *             @OA\Property(property="error", type="string", example="Error message")
     *         )
     *     )
     * )
     */
    public function cancelSubscription(Request $request)
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

            // Get user's subscription
            $subscription = SubscriptionRecord::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->first();

            if (!$subscription || !$subscription->stripe_subscription_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active subscription found to cancel.',
                ], 400);
            }

            // Always cancel at period end (not immediately)
            // Subscription will remain active until end date or next invoice date
            $cancelAtPeriodEnd = $request->input('cancel_at_period_end', true);

            // Initialize Stripe Service
            $stripeService = new \App\Services\Billing\StripeService();
            
            // Cancel subscription
            $result = $stripeService->cancelSubscription(
                $subscription->stripe_subscription_id,
                $cancelAtPeriodEnd
            );

            if ($result['success']) {
                // Refresh subscription from database
                $subscription->refresh();

                Log::info('Basecamp subscription cancelled successfully via API', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'stripe_subscription_id' => $subscription->stripe_subscription_id,
                    'cancel_at_period_end' => $cancelAtPeriodEnd,
                ]);

                return response()->json([
                    'status' => true,
                    'message' => $cancelAtPeriodEnd 
                        ? 'Your subscription will be cancelled at the end of the billing period.' 
                        : 'Your subscription has been cancelled successfully. Monthly payments have been stopped.',
                    'data' => [
                        'subscription_id' => $subscription->id,
                        'stripe_subscription_id' => $subscription->stripe_subscription_id,
                        'status' => $subscription->status,
                        'canceled_at' => $subscription->canceled_at ? $subscription->canceled_at->toIso8601String() : null,
                        'current_period_end' => $subscription->current_period_end ? $subscription->current_period_end->format('Y-m-d') : null,
                        'user_id' => $user->id,
                        'cancel_at_period_end' => $cancelAtPeriodEnd,
                    ],
                ], 200);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to cancel subscription: ' . ($result['error'] ?? 'Unknown error'),
                    'error' => $result['error'] ?? 'Unknown error',
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to cancel basecamp subscription via API: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to cancel subscription. Please try again or contact support.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

