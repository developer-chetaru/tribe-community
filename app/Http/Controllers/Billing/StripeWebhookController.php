<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\StripeService;
use App\Models\SubscriptionRecord;
use App\Models\PaymentRecord;
use App\Models\Organisation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Handle incoming Stripe webhooks
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        // Verify webhook signature
        $verification = $this->stripeService->verifyWebhookSignature($payload, $signature);

        if (!$verification['success']) {
            Log::error('Stripe webhook signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $event = $verification['event'];

        // Handle different event types
        switch ($event->type) {
            case 'invoice.payment_succeeded':
                $this->handleInvoicePaymentSucceeded($event->data->object);
                break;

            case 'invoice.payment_failed':
                $this->handleInvoicePaymentFailed($event->data->object);
                break;

            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($event->data->object);
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event->data->object);
                break;

            case 'payment_intent.succeeded':
                $this->handlePaymentIntentSucceeded($event->data->object);
                break;

            case 'payment_intent.payment_failed':
                $this->handlePaymentIntentFailed($event->data->object);
                break;

            case 'charge.refunded':
                $this->handleChargeRefunded($event->data->object);
                break;

            case 'checkout.session.completed':
                $this->handleCheckoutSessionCompleted($event->data->object);
                break;

            default:
                Log::info('Unhandled Stripe webhook event: ' . $event->type);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle successful invoice payment
     */
    protected function handleInvoicePaymentSucceeded($invoice)
    {
        Log::info('Invoice payment succeeded', ['invoice_id' => $invoice->id]);

        // Get subscription from invoice
        if ($invoice->subscription) {
            $subscription = SubscriptionRecord::where('stripe_subscription_id', $invoice->subscription)->first();

            if ($subscription) {
                $subscription->update([
                    'status' => 'active',
                    'last_payment_date' => now(),
                ]);

                // Record payment
                PaymentRecord::create([
                    'organisation_id' => $subscription->organisation_id,
                    'subscription_id' => $subscription->id,
                    'stripe_invoice_id' => $invoice->id,
                    'stripe_payment_intent_id' => $invoice->payment_intent,
                    'amount' => $invoice->amount_paid / 100,
                    'currency' => $invoice->currency,
                    'status' => 'succeeded',
                    'type' => 'subscription_payment',
                    'paid_at' => now(),
                ]);

                // Send payment success email
                $organisation = Organisation::find($subscription->organisation_id);
                if ($organisation) {
                    // TODO: Send email notification
                    // Mail::to($organisation->admin_email)->send(new PaymentSuccessEmail($invoice));
                }
            }
        }
    }

    /**
     * Handle failed invoice payment
     */
    protected function handleInvoicePaymentFailed($invoice)
    {
        Log::warning('Invoice payment failed', ['invoice_id' => $invoice->id]);

        if ($invoice->subscription) {
            $subscription = SubscriptionRecord::where('stripe_subscription_id', $invoice->subscription)->first();

            if ($subscription) {
                $subscription->update([
                    'status' => 'past_due',
                    'payment_failed_count' => $subscription->payment_failed_count + 1,
                ]);

                // Record failed payment
                PaymentRecord::create([
                    'organisation_id' => $subscription->organisation_id,
                    'subscription_id' => $subscription->id,
                    'stripe_invoice_id' => $invoice->id,
                    'stripe_payment_intent_id' => $invoice->payment_intent,
                    'amount' => $invoice->amount_due / 100,
                    'currency' => $invoice->currency,
                    'status' => 'failed',
                    'type' => 'subscription_payment',
                    'failure_reason' => $invoice->last_finalization_error->message ?? 'Unknown',
                ]);

                // Send payment failed email
                $organisation = Organisation::find($subscription->organisation_id);
                if ($organisation) {
                    // TODO: Send email notification
                    // Mail::to($organisation->admin_email)->send(new PaymentFailedEmail($invoice));
                }

                // Check if we should suspend the account
                if ($subscription->payment_failed_count >= 3) {
                    $this->suspendAccount($subscription);
                }
            }
        }
    }

    /**
     * Handle subscription update
     */
    protected function handleSubscriptionUpdated($stripeSubscription)
    {
        Log::info('Subscription updated', ['subscription_id' => $stripeSubscription->id]);

        $subscription = SubscriptionRecord::where('stripe_subscription_id', $stripeSubscription->id)->first();

        if ($subscription) {
            $subscription->update([
                'status' => $stripeSubscription->status,
                'user_count' => $stripeSubscription->items->data[0]->quantity ?? $subscription->user_count,
                'current_period_start' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_start),
                'current_period_end' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end),
                'next_billing_date' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end),
            ]);
        }
    }

    /**
     * Handle subscription deletion
     */
    protected function handleSubscriptionDeleted($stripeSubscription)
    {
        Log::info('Subscription deleted', ['subscription_id' => $stripeSubscription->id]);

        $subscription = SubscriptionRecord::where('stripe_subscription_id', $stripeSubscription->id)->first();

        if ($subscription) {
            $subscription->update([
                'status' => 'canceled',
                'canceled_at' => now(),
            ]);

            // Deactivate organisation access
            $organisation = Organisation::find($subscription->organisation_id);
            if ($organisation) {
                $organisation->update(['status' => 'inactive']);
                // TODO: Send cancellation confirmation email
                // Mail::to($organisation->admin_email)->send(new SubscriptionCanceledEmail());
            }
        }
    }

    /**
     * Handle successful payment intent
     */
    protected function handlePaymentIntentSucceeded($paymentIntent)
    {
        Log::info('Payment intent succeeded', ['payment_intent_id' => $paymentIntent->id]);

        // Record one-time payment
        PaymentRecord::create([
            'stripe_payment_intent_id' => $paymentIntent->id,
            'amount' => $paymentIntent->amount / 100,
            'currency' => $paymentIntent->currency,
            'status' => 'succeeded',
            'type' => 'one_time_payment',
            'paid_at' => now(),
        ]);
    }

    /**
     * Handle failed payment intent
     */
    protected function handlePaymentIntentFailed($paymentIntent)
    {
        Log::warning('Payment intent failed', ['payment_intent_id' => $paymentIntent->id]);

        PaymentRecord::create([
            'stripe_payment_intent_id' => $paymentIntent->id,
            'amount' => $paymentIntent->amount / 100,
            'currency' => $paymentIntent->currency,
            'status' => 'failed',
            'type' => 'one_time_payment',
            'failure_reason' => $paymentIntent->last_payment_error->message ?? 'Unknown',
        ]);
    }

    /**
     * Handle charge refund
     */
    protected function handleChargeRefunded($charge)
    {
        Log::info('Charge refunded', ['charge_id' => $charge->id]);

        // Update payment record
        $payment = PaymentRecord::where('stripe_payment_intent_id', $charge->payment_intent)->first();

        if ($payment) {
            $payment->update([
                'status' => 'refunded',
                'refunded_at' => now(),
                'refund_amount' => $charge->amount_refunded / 100,
            ]);
        }
    }

    /**
     * Handle checkout session completed
     */
    protected function handleCheckoutSessionCompleted($session)
    {
        Log::info('Checkout session completed', ['session_id' => $session->id]);
        
        // Get invoice ID from metadata
        $invoiceId = $session->metadata->invoice_id ?? null;
        
        if (!$invoiceId) {
            Log::warning('Checkout session completed but no invoice_id in metadata', [
                'session_id' => $session->id
            ]);
            return;
        }
        
        try {
            $invoice = \App\Models\Invoice::with('subscription')->find($invoiceId);
            
            if (!$invoice) {
                Log::warning('Invoice not found for checkout session', [
                    'session_id' => $session->id,
                    'invoice_id' => $invoiceId
                ]);
                return;
            }
            
            // Check if payment already exists
            $existingPayment = \App\Models\Payment::where('invoice_id', $invoiceId)
                ->where('status', 'completed')
                ->first();
            
            if ($existingPayment) {
                Log::info('Payment already exists for invoice', [
                    'invoice_id' => $invoiceId,
                    'payment_id' => $existingPayment->id
                ]);
                return;
            }
            
            \DB::beginTransaction();
            
            // Create payment record
            $payment = \App\Models\Payment::create([
                'invoice_id' => $invoice->id,
                'organisation_id' => $invoice->organisation_id,
                'amount' => $session->amount_total / 100,
                'payment_method' => 'stripe',
                'status' => 'completed',
                'transaction_id' => $session->payment_intent,
                'stripe_payment_intent_id' => $session->payment_intent,
                'stripe_checkout_session_id' => $session->id,
                'paid_at' => now(),
                'notes' => "Payment completed via Stripe Checkout",
            ]);
            
            // Create payment record entry
            PaymentRecord::create([
                'organisation_id' => $invoice->organisation_id,
                'subscription_id' => $invoice->subscription_id,
                'stripe_payment_intent_id' => $session->payment_intent,
                'amount' => $session->amount_total / 100,
                'currency' => 'usd',
                'status' => 'succeeded',
                'type' => 'one_time_payment',
                'paid_at' => now(),
            ]);
            
            // Update invoice status
            $invoice->status = 'paid';
            $invoice->paid_date = now();
            $invoice->save();
            $invoice->refresh();
            
            Log::info("Invoice {$invoice->id} status updated to paid via webhook", [
                'invoice_status' => $invoice->status,
                'paid_date' => $invoice->paid_date
            ]);
            
            // Activate or renew subscription
            if ($invoice->subscription) {
                $subscriptionService = new \App\Services\SubscriptionService();
                $activationResult = $subscriptionService->activateSubscription($payment->id);
                
                if (!$activationResult) {
                    Log::warning("Failed to activate subscription via webhook for payment {$payment->id}");
                } else {
                    Log::info("Subscription activated successfully via webhook for payment {$payment->id}");
                }
            } else {
                Log::warning("Invoice {$invoice->id} has no associated subscription in webhook handler");
            }
            
            \DB::commit();
            
            Log::info("Stripe Checkout payment processed via webhook for invoice {$invoice->id}: {$session->payment_intent}");
            
        } catch (\Exception $e) {
            \DB::rollBack();
            Log::error('Failed to process checkout session completed: ' . $e->getMessage(), [
                'session_id' => $session->id,
                'invoice_id' => $invoiceId,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Suspend account after multiple payment failures
     */
    protected function suspendAccount($subscription)
    {
        Log::warning('Suspending account', ['subscription_id' => $subscription->id]);

        $organisation = Organisation::find($subscription->organisation_id);

        if ($organisation) {
            $organisation->update([
                'status' => 'suspended',
            ]);

            $subscription->update([
                'status' => 'suspended',
                'suspended_at' => now(),
            ]);

            // TODO: Send suspension notification
            // Mail::to($organisation->admin_email)->send(new AccountSuspendedEmail());
        }
    }
}

