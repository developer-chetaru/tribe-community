<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\StripeService;
use App\Models\PaymentRecord;
use App\Models\SubscriptionRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RefundController extends Controller
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
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

