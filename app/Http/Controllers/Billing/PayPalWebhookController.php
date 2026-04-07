<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\PayPalService;
use App\Models\SubscriptionRecord;
use App\Models\PaymentRecord;
use App\Models\Organisation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayPalWebhookController extends Controller
{
    protected $paypalService;

    public function __construct(PayPalService $paypalService)
    {
        $this->paypalService = $paypalService;
    }

    /**
     * Handle incoming PayPal webhooks
     */
    public function handleWebhook(Request $request)
    {
        $headers = [
            'PAYPAL-AUTH-ALGO' => $request->header('PAYPAL-AUTH-ALGO'),
            'PAYPAL-CERT-URL' => $request->header('PAYPAL-CERT-URL'),
            'PAYPAL-TRANSMISSION-ID' => $request->header('PAYPAL-TRANSMISSION-ID'),
            'PAYPAL-TRANSMISSION-SIG' => $request->header('PAYPAL-TRANSMISSION-SIG'),
            'PAYPAL-TRANSMISSION-TIME' => $request->header('PAYPAL-TRANSMISSION-TIME'),
        ];

        $body = $request->getContent();

        // Verify webhook signature
        if (!$this->paypalService->verifyWebhookSignature($headers, $body)) {
            Log::error('PayPal webhook signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $event = json_decode($body, true);

        // Handle different event types
        switch ($event['event_type']) {
            case 'BILLING.SUBSCRIPTION.ACTIVATED':
                $this->handleSubscriptionActivated($event['resource']);
                break;

            case 'BILLING.SUBSCRIPTION.UPDATED':
                $this->handleSubscriptionUpdated($event['resource']);
                break;

            case 'BILLING.SUBSCRIPTION.CANCELLED':
                $this->handleSubscriptionCancelled($event['resource']);
                break;

            case 'BILLING.SUBSCRIPTION.SUSPENDED':
                $this->handleSubscriptionSuspended($event['resource']);
                break;

            case 'PAYMENT.SALE.COMPLETED':
                $this->handlePaymentCompleted($event['resource']);
                break;

            case 'PAYMENT.SALE.REFUNDED':
                $this->handlePaymentRefunded($event['resource']);
                break;

            default:
                Log::info('Unhandled PayPal webhook event: ' . $event['event_type']);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle subscription activation
     */
    protected function handleSubscriptionActivated($resource)
    {
        Log::info('Subscription activated', ['subscription_id' => $resource['id']]);

        SubscriptionRecord::where('paypal_subscription_id', $resource['id'])
            ->update([
                'status' => 'ACTIVE',
                'activated_at' => now(),
            ]);
    }

    /**
     * Handle subscription update
     */
    protected function handleSubscriptionUpdated($resource)
    {
        Log::info('Subscription updated', ['subscription_id' => $resource['id']]);

        SubscriptionRecord::where('paypal_subscription_id', $resource['id'])
            ->update([
                'status' => $resource['status'],
                'updated_at' => now(),
            ]);
    }

    /**
     * Handle subscription cancellation
     */
    protected function handleSubscriptionCancelled($resource)
    {
        Log::info('Subscription cancelled', ['subscription_id' => $resource['id']]);

        $subscription = SubscriptionRecord::where('paypal_subscription_id', $resource['id'])->first();

        if ($subscription) {
            $subscription->update([
                'status' => 'CANCELLED',
                'canceled_at' => now(),
            ]);

            // Deactivate organisation
            Organisation::where('id', $subscription->organisation_id)
                ->update(['status' => 'inactive']);
        }
    }

    /**
     * Handle subscription suspension
     */
    protected function handleSubscriptionSuspended($resource)
    {
        Log::warning('Subscription suspended', ['subscription_id' => $resource['id']]);

        $subscription = SubscriptionRecord::where('paypal_subscription_id', $resource['id'])->first();

        if ($subscription) {
            $subscription->update([
                'status' => 'SUSPENDED',
                'suspended_at' => now(),
            ]);

            // Suspend organisation access
            Organisation::where('id', $subscription->organisation_id)
                ->update(['status' => 'suspended']);
        }
    }

    /**
     * Handle completed payment
     */
    protected function handlePaymentCompleted($resource)
    {
        Log::info('Payment completed', ['sale_id' => $resource['id']]);

        // Extract subscription ID from billing agreement
        $billingAgreementId = $resource['billing_agreement_id'] ?? null;

        if ($billingAgreementId) {
            $subscription = SubscriptionRecord::where('paypal_subscription_id', $billingAgreementId)->first();

            if ($subscription) {
                // Record payment
                PaymentRecord::create([
                    'organisation_id' => $subscription->organisation_id,
                    'subscription_id' => $subscription->id,
                    'paypal_sale_id' => $resource['id'],
                    'amount' => $resource['amount']['total'],
                    'currency' => $resource['amount']['currency'],
                    'status' => 'succeeded',
                    'type' => 'subscription_payment',
                    'paid_at' => now(),
                ]);

                $subscription->update([
                    'status' => 'ACTIVE',
                    'last_payment_date' => now(),
                ]);
            }
        }
    }

    /**
     * Handle payment refund
     */
    protected function handlePaymentRefunded($resource)
    {
        Log::info('Payment refunded', ['refund_id' => $resource['id']]);

        $payment = PaymentRecord::where('paypal_sale_id', $resource['sale_id'])->first();

        if ($payment) {
            $payment->update([
                'status' => 'refunded',
                'refunded_at' => now(),
                'refund_amount' => $resource['amount']['total'],
            ]);
        }
    }
}

