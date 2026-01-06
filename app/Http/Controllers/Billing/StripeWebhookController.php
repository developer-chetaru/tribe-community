<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\StripeService;
use App\Models\SubscriptionRecord;
use App\Models\PaymentRecord;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentFailureLog;
use App\Models\SubscriptionEvent;
use App\Mail\PaymentConfirmationMail;
use App\Mail\PaymentFailedMail;
use App\Mail\AccountSuspendedMail;
use App\Mail\AccountReactivatedMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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
                DB::beginTransaction();
                try {
                    $subscription->update([
                        'status' => 'active',
                        'last_payment_date' => now(),
                        'payment_failed_count' => 0, // Reset failure count
                    ]);

                    // Update user status if basecamp user
                    if ($subscription->user_id) {
                        $user = User::find($subscription->user_id);
                        if ($user) {
                            $user->update([
                                'payment_grace_period_start' => null,
                                'last_payment_failure_date' => null,
                                'suspension_date' => null,
                                'status' => $user->email_verified_at ? 'active_verified' : 'active_unverified',
                            ]);
                        }
                    }

                    // Find and update invoice
                    $dbInvoice = Invoice::where('subscription_id', $subscription->id)
                        ->where('status', 'unpaid')
                        ->orderBy('due_date', 'asc')
                        ->first();

                    if ($dbInvoice) {
                        $dbInvoice->update([
                            'status' => 'paid',
                            'paid_date' => now(),
                        ]);
                    }

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

                    // Mark failure logs as resolved
                    if ($dbInvoice) {
                        PaymentFailureLog::where('invoice_id', $dbInvoice->id)
                            ->where('status', '!=', 'resolved')
                            ->update([
                                'status' => 'resolved',
                                'resolved_at' => now(),
                            ]);
                    }

                    // Log subscription event
                    SubscriptionEvent::create([
                        'subscription_id' => $subscription->id,
                        'user_id' => $subscription->user_id,
                        'organisation_id' => $subscription->organisation_id,
                        'event_type' => 'payment_succeeded',
                        'event_data' => [
                            'stripe_invoice_id' => $invoice->id,
                            'amount' => $invoice->amount_paid / 100,
                        ],
                        'triggered_by' => 'webhook',
                        'event_date' => now(),
                        'notes' => "Payment succeeded via Stripe webhook",
                    ]);

                    DB::commit();

                    // Send payment success email
                    if ($subscription->user_id) {
                        $user = User::find($subscription->user_id);
                        if ($user && $dbInvoice) {
                            try {
                                Mail::to($user->email)->send(new PaymentConfirmationMail($user, $dbInvoice));
                            } catch (\Exception $e) {
                                Log::error("Failed to send payment confirmation email: " . $e->getMessage());
                            }
                        }
                    } elseif ($subscription->organisation_id) {
                        $organisation = Organisation::find($subscription->organisation_id);
                        // TODO: Send email notification to organisation admin
                    }
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Failed to process payment succeeded webhook: ' . $e->getMessage());
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
                DB::beginTransaction();
                try {
                    $newFailureCount = ($subscription->payment_failed_count ?? 0) + 1;
                    
                    $subscription->update([
                        'status' => 'past_due',
                        'payment_failed_count' => $newFailureCount,
                    ]);

                    // Find associated invoice
                    $dbInvoice = Invoice::where('subscription_id', $subscription->id)
                        ->where('status', 'unpaid')
                        ->orderBy('due_date', 'asc')
                        ->first();

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

                    // Create payment failure log
                    if ($dbInvoice) {
                        PaymentFailureLog::create([
                            'user_id' => $subscription->user_id,
                            'organisation_id' => $subscription->organisation_id,
                            'subscription_id' => $subscription->id,
                            'invoice_id' => $dbInvoice->id,
                            'payment_method' => 'stripe',
                            'transaction_id' => $invoice->payment_intent,
                            'amount' => $invoice->amount_due / 100,
                            'currency' => $invoice->currency,
                            'failure_reason' => 'payment_declined',
                            'failure_message' => $invoice->last_finalization_error->message ?? 'Payment failed',
                            'retry_attempt' => $newFailureCount,
                            'failure_date' => now(),
                            'status' => 'pending_retry',
                        ]);
                    }

                    // Update user's last payment failure date
                    if ($subscription->user_id) {
                        $user = User::find($subscription->user_id);
                        if ($user) {
                            $user->update([
                                'last_payment_failure_date' => now(),
                            ]);

                            // If 3rd failure, start grace period
                            if ($newFailureCount >= 3 && !$user->payment_grace_period_start) {
                                $user->update([
                                    'payment_grace_period_start' => now(),
                                    'status' => 'suspended', // Temporarily suspended during grace period
                                ]);
                            }
                        }
                    }

                    // Log subscription event
                    SubscriptionEvent::create([
                        'subscription_id' => $subscription->id,
                        'user_id' => $subscription->user_id,
                        'organisation_id' => $subscription->organisation_id,
                        'event_type' => 'payment_failed',
                        'event_data' => [
                            'stripe_invoice_id' => $invoice->id,
                            'amount' => $invoice->amount_due / 100,
                            'failure_count' => $newFailureCount,
                            'failure_reason' => $invoice->last_finalization_error->message ?? 'Unknown',
                        ],
                        'triggered_by' => 'webhook',
                        'event_date' => now(),
                        'notes' => "Payment failed via Stripe webhook - Attempt {$newFailureCount}",
                    ]);

                    DB::commit();

                    // Send payment failed email
                    if ($subscription->user_id) {
                        $user = User::find($subscription->user_id);
                        if ($user && $dbInvoice) {
                            try {
                                // Send Day 1 payment failed email
                                Mail::to($user->email)->send(new PaymentFailedMail($user, $dbInvoice, 1));
                            } catch (\Exception $e) {
                                Log::error("Failed to send payment failed email: " . $e->getMessage());
                            }
                        }
                    } elseif ($subscription->organisation_id) {
                        $organisation = Organisation::find($subscription->organisation_id);
                        // TODO: Send email notification to organisation admin
                    }

                    // Check if we should enter grace period (after 3 failures)
                    if ($newFailureCount >= 3) {
                        // Grace period will be handled by ProcessPaymentRetries command
                        Log::warning("Payment failed 3+ times for subscription {$subscription->id} - Grace period will start");
                    }
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Failed to process payment failed webhook: ' . $e->getMessage());
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

            // Log subscription event
            SubscriptionEvent::create([
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'organisation_id' => $subscription->organisation_id,
                'event_type' => 'subscription_updated',
                'event_data' => [
                    'stripe_status' => $stripeSubscription->status,
                    'user_count' => $stripeSubscription->items->data[0]->quantity ?? $subscription->user_count,
                ],
                'triggered_by' => 'webhook',
                'event_date' => now(),
                'notes' => "Subscription updated via Stripe webhook",
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

        // Try to find associated invoice from metadata
        $invoiceId = $paymentIntent->metadata->invoice_id ?? null;
        $invoice = $invoiceId ? Invoice::find($invoiceId) : null;

        PaymentRecord::create([
            'organisation_id' => $invoice?->organisation_id,
            'subscription_id' => $invoice?->subscription_id,
            'stripe_payment_intent_id' => $paymentIntent->id,
            'amount' => $paymentIntent->amount / 100,
            'currency' => $paymentIntent->currency,
            'status' => 'failed',
            'type' => 'one_time_payment',
            'failure_reason' => $paymentIntent->last_payment_error->message ?? 'Unknown',
        ]);

        // Create payment failure log if invoice exists
        if ($invoice) {
            PaymentFailureLog::create([
                'user_id' => $invoice->user_id,
                'organisation_id' => $invoice->organisation_id,
                'subscription_id' => $invoice->subscription_id,
                'invoice_id' => $invoice->id,
                'payment_method' => 'stripe',
                'transaction_id' => $paymentIntent->id,
                'amount' => $paymentIntent->amount / 100,
                'currency' => $paymentIntent->currency,
                'failure_reason' => 'payment_declined',
                'failure_message' => $paymentIntent->last_payment_error->message ?? 'Payment intent failed',
                'retry_attempt' => 1,
                'failure_date' => now(),
                'status' => 'pending_retry',
            ]);

            // Update user's last payment failure date
            if ($invoice->user_id) {
                $user = User::find($invoice->user_id);
                if ($user) {
                    $user->update([
                        'last_payment_failure_date' => now(),
                    ]);
                }
            }
        }
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

        DB::beginTransaction();
        try {
            $subscription->update([
                'status' => 'suspended',
                'suspended_at' => now(),
            ]);

            // Update user if basecamp user
            if ($subscription->user_id) {
                $user = User::find($subscription->user_id);
                if ($user) {
                    $user->update([
                        'status' => 'suspended',
                        'suspension_date' => now(),
                    ]);
                }
            }

            // Update organisation if exists
            if ($subscription->organisation_id) {
                $organisation = Organisation::find($subscription->organisation_id);
                if ($organisation) {
                    $organisation->update([
                        'status' => 'suspended',
                    ]);
                }
            }

            // Log subscription event
            SubscriptionEvent::create([
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'organisation_id' => $subscription->organisation_id,
                'event_type' => 'suspended',
                'event_data' => [
                    'suspension_date' => now()->toDateString(),
                    'reason' => 'payment_failed_multiple_attempts',
                ],
                'triggered_by' => 'webhook',
                'event_date' => now(),
                'notes' => "Account suspended via Stripe webhook after multiple payment failures",
            ]);

            DB::commit();

            // Send suspension notification
            if ($subscription->user_id) {
                $user = User::find($subscription->user_id);
                if ($user) {
                    $unpaidInvoice = Invoice::where('subscription_id', $subscription->id)
                        ->where('status', 'unpaid')
                        ->orderBy('due_date', 'asc')
                        ->first();
                    
                    try {
                        Mail::to($user->email)->send(new AccountSuspendedMail($user, $unpaidInvoice));
                    } catch (\Exception $e) {
                        Log::error("Failed to send suspension email: " . $e->getMessage());
                    }
                }
            } elseif ($subscription->organisation_id) {
                $organisation = Organisation::find($subscription->organisation_id);
                // TODO: Send email notification to organisation admin
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to suspend account via webhook: ' . $e->getMessage());
        }
    }
}

