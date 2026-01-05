<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\StripeService;
use App\Models\PaymentRecord;
use App\Models\SubscriptionRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Billing - Refunds",
 *     description="Refund processing and history endpoints for Stripe payments"
 * )
 */
class RefundController extends Controller
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * @OA\Post(
     *     path="/billing/refunds/process",
     *     tags={"Billing - Refunds"},
     *     summary="Process a refund request",
     *     description="Processes a refund for a Stripe payment. Supports full or partial refunds. Requires director or super_admin role.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"payment_id", "reason"},
     *             @OA\Property(property="payment_id", type="integer", example=1, description="ID of the payment record to refund"),
     *             @OA\Property(property="reason", type="string", enum={"overcharge", "duplicate_payment", "service_cancellation", "module_removal", "other"}, example="overcharge", description="Reason for refund"),
     *             @OA\Property(property="amount", type="number", format="float", example=50.00, description="Partial refund amount (optional, defaults to full refund)", nullable=true),
     *             @OA\Property(property="description", type="string", maxLength=500, example="Customer requested refund due to billing error", description="Additional refund description", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Refund processed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Refund processed successfully"),
     *             @OA\Property(
     *                 property="refund",
     *                 type="object",
     *                 description="Stripe refund object",
     *                 @OA\Property(property="id", type="string", example="re_1234567890"),
     *                 @OA\Property(property="amount", type="number", format="float", example=50.00),
     *                 @OA\Property(property="status", type="string", example="succeeded")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad request - Invalid refund request or payment already refunded"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Payment not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     * Process a refund request
     */
    public function processRefund(Request $request)
    {
        $validated = $request->validate([
            'payment_id' => 'required|exists:payment_records,id',
            'reason' => 'required|in:overcharge,duplicate_payment,service_cancellation,module_removal,other',
            'amount' => 'nullable|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
        ]);

        $payment = PaymentRecord::findOrFail($validated['payment_id']);

        // Validate refund request
        $validation = $this->validateRefundRequest($payment, $validated['reason'], $validated['amount'] ?? null);

        if (!$validation['valid']) {
            return response()->json([
                'success' => false,
                'error' => $validation['error'],
            ], 400);
        }

        // Process refund (Stripe only)
        if ($payment->stripe_payment_intent_id) {
            $result = $this->stripeService->processRefund(
                $payment->stripe_payment_intent_id,
                $validated['amount'],
                $validated['reason']
            );
        } else {
            return response()->json([
                'success' => false,
                'error' => 'Only Stripe payments can be refunded',
            ], 400);
        }

        if ($result['success']) {
            // Update payment record
            $payment->update([
                'status' => 'refunded',
                'refund_amount' => $validated['amount'] ?? $payment->amount,
                'refunded_at' => now(),
            ]);

            // TODO: Send refund confirmation email
            // TODO: Generate refund receipt

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
                'refund' => $result['refund'] ?? null,
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'],
        ], 400);
    }

    /**
     * Validate refund request
     */
    protected function validateRefundRequest(PaymentRecord $payment, $reason, $amount = null)
    {
        // Check if payment is eligible for refund
        if ($payment->status !== 'succeeded') {
            return [
                'valid' => false,
                'error' => 'Only successful payments can be refunded',
            ];
        }

        if ($payment->status === 'refunded') {
            return [
                'valid' => false,
                'error' => 'Payment has already been refunded',
            ];
        }

        // Validate amount
        if ($amount !== null && $amount > $payment->amount) {
            return [
                'valid' => false,
                'error' => 'Refund amount cannot exceed payment amount',
            ];
        }

        // Reason-specific validation
        switch ($reason) {
            case 'overcharge':
                // Verify billing error
                // TODO: Implement verification logic
                break;

            case 'duplicate_payment':
                // Verify duplicate transaction
                // TODO: Implement verification logic
                break;

            case 'service_cancellation':
                // Check cancellation policy
                // TODO: Implement policy check
                break;

            case 'module_removal':
                // Verify pro-rata credit
                // TODO: Implement verification logic
                break;

            case 'other':
                // Manual review required
                // TODO: Flag for manual review
                break;
        }

        return ['valid' => true];
    }

    /**
     * @OA\Get(
     *     path="/billing/refunds/history",
     *     tags={"Billing - Refunds"},
     *     summary="Get refund history",
     *     description="Retrieves refund history with optional filtering by organisation or subscription. Requires director or super_admin role.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="organisation_id",
     *         in="query",
     *         description="Filter refunds by organisation ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="subscription_id",
     *         in="query",
     *         description="Filter refunds by subscription ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
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
     *         description="Refund history retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="refunds",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="stripe_refund_id", type="string", example="re_1234567890"),
     *                         @OA\Property(property="amount", type="number", format="float", example=50.00),
     *                         @OA\Property(property="currency", type="string", example="usd"),
     *                         @OA\Property(property="status", type="string", example="succeeded"),
     *                         @OA\Property(property="refunded_at", type="string", format="date-time", example="2025-12-24T10:00:00Z")
     *                     )
     *                 ),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="total", type="integer", example=10)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=500, description="Server error")
     * )
     * Get refund history
     */
    public function getRefundHistory(Request $request)
    {
        $validated = $request->validate([
            'organisation_id' => 'nullable|exists:organisations,id',
            'subscription_id' => 'nullable|exists:subscription_records,id',
        ]);

        $query = PaymentRecord::where('type', 'refund')
            ->orWhere('status', 'refunded');

        if ($validated['organisation_id'] ?? null) {
            $query->where('organisation_id', $validated['organisation_id']);
        }

        if ($validated['subscription_id'] ?? null) {
            $query->where('subscription_id', $validated['subscription_id']);
        }

        $refunds = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'refunds' => $refunds,
        ]);
    }
}

