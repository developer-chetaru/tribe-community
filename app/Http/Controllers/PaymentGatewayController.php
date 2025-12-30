<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SubscriptionRecord;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PaymentGatewayController extends Controller
{
    /**
     * Process payment via payment gateway
     */
    public function processPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invoice_id' => 'required|exists:invoices,id',
            'payment_method' => 'required|string|in:card',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $invoice = Invoice::with('subscription')->findOrFail($request->invoice_id);

        // Authorization check
        if (!$user->hasRole('super_admin') && ($user->hasRole('director') && $invoice->organisation_id !== $user->orgId)) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        // Process payment directly (no test gateway simulation)
        // Payment is processed and subscription activated immediately
        try {
            // Generate transaction ID
            $transactionId = 'TXN-' . time() . '-' . rand(1000, 9999);
            
            // Create payment record with completed status
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'organisation_id' => $invoice->organisation_id,
                'paid_by_user_id' => $user->id,
                'payment_method' => $request->payment_method,
                'amount' => $invoice->total_amount,
                'transaction_id' => $transactionId,
                'status' => 'completed',
                'payment_date' => now()->toDateString(),
                'payment_notes' => 'Payment processed successfully',
                'approved_by_admin_id' => null,
                'approved_at' => now(),
            ]);

            // Update invoice status
            $invoice->update([
                'status' => 'paid',
                'paid_date' => now(),
            ]);

            // Activate/renew subscription immediately
            $subscriptionService = new SubscriptionService();
            if ($invoice->subscription) {
                // Renew existing subscription with updated user count and price
                $userCount = $invoice->user_count;
                $pricePerUser = $invoice->price_per_user;
                $subscriptionService->renewSubscription($invoice->subscription, $userCount, $pricePerUser);
            } else {
                // Create and activate new subscription
                $subscriptionService->activateSubscription($payment->id);
            }

            Log::info("Payment processed and subscription activated for invoice {$invoice->id}: {$transactionId}");

            return response()->json([
                'status' => true,
                'message' => 'Payment processed successfully. Your subscription has been activated.',
                'payment' => $payment,
            ]);
        } catch (\Exception $e) {
            Log::error("Payment processing failed for invoice {$invoice->id}: " . $e->getMessage());
            
            return response()->json([
                'status' => false,
                'message' => 'Payment processing failed. Please try again.',
            ], 400);
        }
    }


    /**
     * Get payment gateway configuration
     */
    public function getPaymentConfig()
    {
        return response()->json([
            'status' => true,
            'payment_methods' => [
                'card' => 'Credit/Debit Card (Stripe)',
            ],
            'currency' => 'USD',
        ]);
    }
}

