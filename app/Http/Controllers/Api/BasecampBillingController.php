<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SubscriptionRecord;
use App\Models\User;
use App\Services\Billing\StripeInvoiceHistoryService;
use App\Services\StripePaymentService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Customer;
use Stripe\Subscription;
use Illuminate\Support\Facades\URL;

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

            // Match web /billing: prefer Stripe invoice history when customer IDs exist (same as Billing Livewire).
            /** @var StripeInvoiceHistoryService $stripeHistory */
            $stripeHistory = app(StripeInvoiceHistoryService::class);
            $customerIds = $stripeHistory->collectCustomerIdsForUser($user);

            if ($customerIds !== []) {
                try {
                    $stripeRows = $stripeHistory->fetchInvoiceDisplayRows($customerIds);
                    if (count($stripeRows) > 0) {
                        $mapped = collect($stripeRows)->map(fn (\stdClass $row) => $this->stripeInvoiceRowToApiPayload($row));

                        if ($request->has('status')) {
                            $mapped = $mapped->filter(function (array $item) use ($request) {
                                return ($item['status'] ?? '') === $request->status;
                            })->values();
                        }

                        $perPage = min(max((int) $request->get('per_page', 10), 1), 100);
                        $page = LengthAwarePaginator::resolveCurrentPage();
                        $total = $mapped->count();
                        $items = $mapped->slice(($page - 1) * $perPage, $perPage)->values()->all();

                        return response()->json([
                            'status' => true,
                            'message' => 'Invoices retrieved successfully',
                            'data' => $items,
                            'user_id' => $user->id,
                            'current_page' => $page,
                            'last_page' => (int) max(1, (int) ceil($total / $perPage)),
                            'per_page' => $perPage,
                            'total' => $total,
                            'source' => 'stripe',
                        ], 200);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Basecamp API getInvoices: Stripe failed, using local invoices', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $query = Invoice::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->orderBy('created_at', 'desc');

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $invoices = $query->paginate(min(max((int) $request->get('per_page', 10), 1), 100));

            return response()->json([
                'status' => true,
                'message' => 'Invoices retrieved successfully',
                'data' => $invoices->items(),
                'user_id' => $user->id, // Include user_id for mobile apps
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
                'source' => 'local',
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
     * Shape aligned with local Invoice API fields + Stripe URLs for View/PDF (same data as web /billing).
     *
     * @return array<string, mixed>
     */
    protected function stripeInvoiceRowToApiPayload(\stdClass $row): array
    {
        $invoiceDate = $row->invoice_date ?? null;
        $dateStr = null;
        if ($invoiceDate instanceof \Carbon\Carbon) {
            $dateStr = $invoiceDate->format('Y-m-d');
        } elseif ($invoiceDate instanceof \DateTimeInterface) {
            $dateStr = $invoiceDate->format('Y-m-d');
        }

        return [
            'from_stripe' => true,
            'id' => null,
            'stripe_invoice_id' => $row->stripe_invoice_id ?? null,
            'invoice_number' => $row->invoice_number ?? null,
            'tier' => 'basecamp',
            'user_id' => null,
            'subscription_id' => null,
            'user_count' => 1,
            'price_per_user' => null,
            'subtotal' => isset($row->subtotal) ? (float) $row->subtotal : null,
            'tax_amount' => isset($row->tax_amount) ? (float) $row->tax_amount : null,
            'total_amount' => (float) ($row->total_amount ?? 0),
            'status' => $row->status ?? 'pending',
            'invoice_date' => $dateStr,
            'due_date' => null,
            'paid_at' => null,
            'hosted_invoice_url' => $row->hosted_invoice_url ?? null,
            'invoice_pdf' => $row->invoice_pdf ?? null,
            'currency' => $row->currency ?? 'GBP',
        ];
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
     *     summary="Create Stripe Checkout session for basecamp monthly subscription",
     *     description="Create a Stripe Checkout session with monthly recurring billing for a basecamp user's invoice. Returns checkout URL to redirect user for payment. Works same as renew API - creates subscription with recurring monthly billing. Requires an unpaid invoice. Works for mobile apps.",
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
     *             @OA\Property(property="success", type="boolean", example=true, description="Indicates successful checkout session creation"),
     *             @OA\Property(property="redirect_url", type="string", example="https://checkout.stripe.com/pay/cs_test_...", description="Stripe Checkout URL to redirect user for payment"),
     *             @OA\Property(property="message", type="string", example="Checkout session created successfully")
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

            // Prevent duplicate Stripe subscriptions:
            // if this user already has an active Stripe subscription with auto-renew, do not create another one.
            $latestSubscription = SubscriptionRecord::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->latest('id')
                ->first();

            if ($latestSubscription && $latestSubscription->stripe_subscription_id) {
                try {
                    Stripe::setApiKey(config('services.stripe.secret'));
                    $stripeSub = Subscription::retrieve($latestSubscription->stripe_subscription_id);
                    $activeLikeStatuses = ['active', 'trialing', 'past_due', 'unpaid'];
                    $isActiveLike = in_array($stripeSub->status ?? '', $activeLikeStatuses, true);
                    $periodEndInFuture = isset($stripeSub->current_period_end)
                        ? \Carbon\Carbon::createFromTimestamp($stripeSub->current_period_end)->isFuture()
                        : false;
                    $autoRenewEnabled = !((bool) ($stripeSub->cancel_at_period_end ?? false));

                    if ($isActiveLike && $periodEndInFuture && $autoRenewEnabled) {
                        return response()->json([
                            'status' => true,
                            'already_active' => true,
                            'message' => 'Subscription is already active with Stripe auto-renewal. New checkout session is not required.',
                            'data' => [
                                'subscription_id' => $latestSubscription->id,
                                'stripe_subscription_id' => $latestSubscription->stripe_subscription_id,
                                'stripe_status' => $stripeSub->status ?? null,
                            ],
                        ], 200);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to validate existing Stripe subscription before creating checkout', [
                        'user_id' => $user->id,
                        'subscription_id' => $latestSubscription->id,
                        'stripe_subscription_id' => $latestSubscription->stripe_subscription_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Initialize Stripe
            if (!class_exists(Stripe::class)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Stripe SDK not available.',
                ], 500);
            }

            Stripe::setApiKey(config('services.stripe.secret'));

            // Get enabled payment methods from Stripe API
            $paymentMethods = StripePaymentService::getEnabledPaymentMethods();

            // Create success URL - use API endpoint that handles both web and mobile
            $successUrl = url('/api/basecamp/payment/success') . '?session_id={CHECKOUT_SESSION_ID}&invoice_id=' . $invoice->id . '&user_id=' . $user->id;
            
            // Create Stripe Checkout Session using same pattern as renew API
            $checkoutParams = [
                'payment_method_types' => $paymentMethods,
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'gbp',
                        'product_data' => [
                            'name' => 'Basecamp Subscription',
                            'description' => 'Monthly subscription for Basecamp tier - £10/month',
                        ],
                        'unit_amount' => (int)($invoice->total_amount * 100), // Convert to cents
                        'recurring' => [
                            'interval' => 'month', // Monthly billing
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'subscription', // Subscription mode for recurring payments
                'success_url' => $successUrl,
                'cancel_url' => url('/api/basecamp/payment/cancel') . '?invoice_id=' . $invoice->id,
                'customer_email' => $user->email,
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'user_id' => $user->id,
                    'tier' => 'basecamp',
                ],
            ];

            $checkoutSession = StripeCheckoutSession::create($checkoutParams);

            Log::info('Stripe Checkout Session created for basecamp invoice', [
                'session_id' => $checkoutSession->id,
                'checkout_url' => $checkoutSession->url,
                'invoice_id' => $invoice->id,
                'user_id' => $user->id,
            ]);

            // Return same format as renew API
            return response()->json([
                'success' => true,
                'redirect_url' => $checkoutSession->url,
                'message' => 'Checkout session created successfully',
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

            // Get subscription from Stripe if payment intent is linked to a subscription
            $stripeSubscription = null;
            $subscriptionRecord = SubscriptionRecord::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->first();

            if ($subscriptionRecord && $subscriptionRecord->stripe_subscription_id) {
                try {
                    $stripeSubscription = Subscription::retrieve($subscriptionRecord->stripe_subscription_id);
                } catch (\Exception $e) {
                    Log::warning('Failed to retrieve Stripe subscription: ' . $e->getMessage());
                }
            }

            // Process payment in transaction
            $payment = DB::transaction(function () use ($invoice, $user, $request, $paymentIntent, $stripeSubscription, $subscriptionRecord) {
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

                // Get or create subscription record
                $subscription = $subscriptionRecord;
                if (!$subscription) {
                    $subscription = SubscriptionRecord::create([
                        'user_id' => $user->id,
                        'tier' => 'basecamp',
                        'user_count' => 1,
                        'status' => 'active',
                    ]);
                }

                // Update subscription with Stripe data if available
                if ($stripeSubscription) {
                    $subscription->update([
                        'status' => $stripeSubscription->status === 'active' ? 'active' : 'inactive',
                        'stripe_subscription_id' => $stripeSubscription->id,
                        'stripe_customer_id' => $stripeSubscription->customer,
                        'current_period_start' => $stripeSubscription->current_period_start 
                            ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_start)
                            : now(),
                        'current_period_end' => $stripeSubscription->current_period_end 
                            ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end)
                            : now()->addMonth(),
                        'next_billing_date' => $stripeSubscription->current_period_end 
                            ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end)
                            : now()->addMonth(),
                        'last_payment_date' => now(),
                        'activated_at' => $subscription->activated_at ?? now(),
                    ]);
                } else {
                    // Fallback: activate subscription with default dates
                    $subscription->update([
                        'status' => 'active',
                        'current_period_start' => now(),
                        'current_period_end' => now()->addMonth(),
                        'next_billing_date' => now()->addMonth(),
                        'last_payment_date' => now(),
                        'activated_at' => $subscription->activated_at ?? now(),
                    ]);
                }

                Log::info('Basecamp payment confirmed successfully', [
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'stripe_subscription_id' => $stripeSubscription->id ?? null,
                ]);

                // Log activity
                try {
                    \App\Services\ActivityLogService::logPayment($payment, $invoice);
                } catch (\Exception $e) {
                    Log::warning('Failed to log payment activity: ' . $e->getMessage());
                }

                return $payment;
            });

            // Get updated subscription status after transaction
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

    /**
     * Handle Stripe Checkout success callback for mobile apps
     * Processes payment and redirects to app or returns JSON
     */
    public function handlePaymentSuccess(Request $request)
    {
        try {
            $sessionId = $request->query('session_id');
            $invoiceId = $request->query('invoice_id');
            $userId = $request->query('user_id');
            
            Log::info('Basecamp payment success callback (API)', [
                'session_id' => $sessionId,
                'invoice_id' => $invoiceId,
                'user_id' => $userId,
            ]);
            
            if (!$sessionId || !$invoiceId || !$userId) {
                return redirect()->route('app.redirect', [
                    'payment_success' => 'false',
                    'error' => 'Missing required parameters.',
                ]);
            }
            
            // Initialize Stripe
            Stripe::setApiKey(config('services.stripe.secret'));
            
            // Retrieve the checkout session
            $session = StripeCheckoutSession::retrieve($sessionId);
            
            if ($session->payment_status !== 'paid') {
                return redirect()->route('app.redirect', [
                    'payment_success' => 'false',
                    'error' => 'Payment was not completed.',
                ]);
            }
            
            // Get invoice and user
            $invoice = Invoice::with('subscription')->findOrFail($invoiceId);
            $user = User::findOrFail($userId);
            
            // Verify invoice belongs to user
            if ($invoice->user_id !== $user->id || $invoice->tier !== 'basecamp') {
                return redirect()->route('app.redirect', [
                    'payment_success' => 'false',
                    'error' => 'Invalid invoice.',
                ]);
            }
            
            // Check if payment already exists
            $existingPayment = Payment::where('invoice_id', $invoiceId)
                ->where('transaction_id', $session->payment_intent ?? $session->subscription)
                ->first();
                
            if ($existingPayment) {
                // Payment already processed - redirect to app
                return redirect()->route('app.redirect', [
                    'session_id' => $sessionId,
                    'invoice_id' => $invoiceId,
                    'user_id' => $userId,
                    'payment_success' => 'true',
                    'already_processed' => 'true',
                ]);
            }
            
            // Get Stripe subscription ID if available
            $stripeSubscriptionId = null;
            if ($session->mode === 'subscription' && $session->subscription) {
                $stripeSubscriptionId = $session->subscription;
            }
            
            // Process payment using the same logic as StripeCheckoutController
            $result = DB::transaction(function () use ($invoice, $user, $session, $stripeSubscriptionId) {
                // Get or create subscription
                $subscription = SubscriptionRecord::where('user_id', $user->id)
                    ->where('tier', 'basecamp')
                    ->first();
                
                // Update or create subscription with Stripe subscription ID
                if ($stripeSubscriptionId) {
                    try {
                        $stripeSubscription = Subscription::retrieve($stripeSubscriptionId);
                        
                        if ($subscription) {
                            $paymentDate = \Carbon\Carbon::today();
                            $subscription->update([
                                'stripe_subscription_id' => $stripeSubscriptionId,
                                'stripe_customer_id' => $stripeSubscription->customer ?? null,
                                'status' => 'active',
                                'current_period_start' => $paymentDate,
                                'current_period_end' => $paymentDate->copy()->addMonth(),
                                'next_billing_date' => $paymentDate->copy()->addMonth(),
                                'canceled_at' => null,
                                'last_payment_date' => $paymentDate,
                            ]);
                        } else {
                            $paymentDate = \Carbon\Carbon::today();
                            $subscription = SubscriptionRecord::create([
                                'user_id' => $user->id,
                                'organisation_id' => null,
                                'tier' => 'basecamp',
                                'stripe_subscription_id' => $stripeSubscriptionId,
                                'stripe_customer_id' => $stripeSubscription->customer ?? null,
                                'user_count' => 1,
                                'status' => 'active',
                                'current_period_start' => $paymentDate,
                                'current_period_end' => $paymentDate->copy()->addMonth(),
                                'next_billing_date' => $paymentDate->copy()->addMonth(),
                                'last_payment_date' => $paymentDate,
                            ]);
                            
                            $invoice->subscription_id = $subscription->id;
                            $invoice->save();
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to retrieve Stripe subscription: ' . $e->getMessage());
                    }
                }
                
                // Create payment record
                $payment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'user_id' => $user->id,
                    'organisation_id' => null,
                    'payment_method' => 'stripe',
                    'amount' => $invoice->total_amount,
                    'transaction_id' => $session->payment_intent ?? $session->subscription,
                    'status' => 'completed',
                    'payment_date' => now()->toDateString(),
                    'payment_notes' => 'Basecamp subscription payment via Stripe Checkout',
                ]);
                
                // Update invoice status
                $invoice->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
                
                // Activate subscription using service
                if ($subscription) {
                    $subscriptionService = new \App\Services\SubscriptionService();
                    $subscriptionService->activateSubscription($payment->id);
                    $subscription->refresh();
                }
                
                Log::info('Basecamp payment processed successfully (API)', [
                    'payment_id' => $payment->id,
                    'subscription_id' => $subscription->id ?? null,
                    'invoice_id' => $invoice->id,
                    'user_id' => $user->id,
                ]);
                
                return [
                    'payment' => $payment,
                    'subscription' => $subscription,
                ];
            });
            
            // Redirect to app-redirect route which will open the app
            return redirect()->route('app.redirect', [
                'session_id' => $sessionId,
                'invoice_id' => $invoiceId,
                'user_id' => $userId,
                'payment_success' => 'true',
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to process payment success (API): ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->route('app.redirect', [
                'payment_success' => 'false',
                'error' => 'Failed to process payment.',
            ]);
        }
    }

    /**
     * Handle Stripe Checkout cancel callback
     */
    public function handlePaymentCancel(Request $request)
    {
        $invoiceId = $request->query('invoice_id');
        
        Log::info('Basecamp payment cancelled (API)', [
            'invoice_id' => $invoiceId,
        ]);
        
        // Redirect to app with cancel status
        $userAgent = strtolower($request->header('User-Agent', ''));
        $isAndroid = str_contains($userAgent, 'android');
        $isIOS = preg_match('/iphone|ipad|ipod/i', $userAgent);
        
        if ($isAndroid || $isIOS) {
            $schemeUrl = 'tribe365://payment-cancel?invoice_id=' . ($invoiceId ?? '');
            return redirect()->away($schemeUrl);
        }
        
        return redirect()->away(url('/dashboard'));
    }
}

