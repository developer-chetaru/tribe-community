<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\StripeService;
use App\Models\SubscriptionRecord;
use App\Models\PaymentRecord;
use App\Models\Organisation;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use App\Mail\PaymentConfirmationMail;
use App\Mail\PaymentFailedMail;
use App\Mail\AccountSuspendedMail;

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
                // Check if this is a daily subscription by checking Stripe subscription
                $isDailySubscription = false;
                try {
                    \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                    $stripeSubscription = \Stripe\Subscription::retrieve($invoice->subscription);
                    if (isset($stripeSubscription->items->data[0]->price->recurring->interval) && 
                        $stripeSubscription->items->data[0]->price->recurring->interval === 'day') {
                        $isDailySubscription = true;
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to check subscription interval: " . $e->getMessage());
                }
                
                // Update subscription dates based on billing interval
                $updateData = [
                    'status' => 'active',
                    'last_payment_date' => now(),
                ];
                
                if ($isDailySubscription) {
                    // For daily subscription: expires today, renews tomorrow
                    $updateData['current_period_start'] = \Carbon\Carbon::today()->startOfDay();
                    $updateData['current_period_end'] = \Carbon\Carbon::today()->endOfDay(); // Expires today
                    $updateData['next_billing_date'] = \Carbon\Carbon::today()->addDay(); // Renews tomorrow
                } else {
                    // For monthly subscription: use Stripe's period dates
                    if (isset($stripeSubscription->current_period_start) && isset($stripeSubscription->current_period_end)) {
                        $updateData['current_period_start'] = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_start);
                        $updateData['current_period_end'] = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end);
                        $updateData['next_billing_date'] = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end);
                    }
                }
                
                $subscription->update($updateData);

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

                // Send payment confirmation email
                $invoiceModel = Invoice::where('subscription_id', $subscription->id)->latest()->first();
                if ($invoiceModel) {
                    $paymentRecord = PaymentRecord::where('subscription_id', $subscription->id)
                        ->where('status', 'succeeded')
                        ->latest()
                        ->first();
                    
                    $user = null;
                    if ($subscription->user_id) {
                        $user = \App\Models\User::find($subscription->user_id);
                    } elseif ($subscription->organisation_id) {
                        $org = Organisation::find($subscription->organisation_id);
                        if ($org) {
                            $user = \App\Models\User::where('email', $org->admin_email)->first();
                        }
                    }
                    
                    if ($user) {
                        try {
                            Mail::to($user->email)->send(new PaymentConfirmationMail($invoiceModel, $paymentRecord, $user, (bool)$subscription->user_id));
                            Log::info('Payment confirmation email sent', ['user_id' => $user->id, 'invoice_id' => $invoiceModel->id]);
                        } catch (\Exception $e) {
                            Log::error('Failed to send payment confirmation email: ' . $e->getMessage());
                        }
                    }
                }
                
                Log::info('Subscription updated after payment', [
                    'subscription_id' => $subscription->id,
                    'is_daily' => $isDailySubscription,
                    'period_end' => $updateData['current_period_end']->format('Y-m-d'),
                    'next_billing' => $updateData['next_billing_date']->format('Y-m-d'),
                ]);
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

                // Send payment failed email (Day 1)
                $invoiceModel = Invoice::where('subscription_id', $subscription->id)->latest()->first();
                if ($invoiceModel) {
                    $user = null;
                    if ($subscription->user_id) {
                        $user = \App\Models\User::find($subscription->user_id);
                    } elseif ($subscription->organisation_id) {
                        $org = Organisation::find($subscription->organisation_id);
                        if ($org) {
                            $user = \App\Models\User::where('email', $org->admin_email)->first();
                        }
                    }
                    
                    if ($user) {
                        try {
                            $failureReason = $invoice->last_finalization_error->message ?? 'Payment processing failed';
                            Mail::to($user->email)->send(new PaymentFailedMail($invoiceModel, $subscription, $user, $failureReason, 1));
                            Log::info('Payment failed email (Day 1) sent', ['user_id' => $user->id, 'invoice_id' => $invoiceModel->id]);
                        } catch (\Exception $e) {
                            Log::error('Failed to send payment failed email: ' . $e->getMessage());
                        }
                    }
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
            // Check if this is a daily subscription
            $isDailySubscription = false;
            if (isset($stripeSubscription->items->data[0]->price->recurring->interval) && 
                $stripeSubscription->items->data[0]->price->recurring->interval === 'day') {
                $isDailySubscription = true;
            }
            
            $updateData = [
                'status' => $stripeSubscription->status,
                'user_count' => $stripeSubscription->items->data[0]->quantity ?? $subscription->user_count,
            ];
            
            if ($isDailySubscription) {
                // For daily subscription: override Stripe's dates (Stripe sets period_end to tomorrow, we want today)
                $updateData['current_period_start'] = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_start)->startOfDay();
                $updateData['current_period_end'] = \Carbon\Carbon::today()->endOfDay(); // Expires today
                $updateData['next_billing_date'] = \Carbon\Carbon::today()->addDay(); // Renews tomorrow
            } else {
                // For monthly subscription: use Stripe's dates
                $updateData['current_period_start'] = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_start);
                $updateData['current_period_end'] = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end);
                $updateData['next_billing_date'] = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end);
            }
            
            $subscription->update($updateData);
            
            Log::info('Subscription dates updated via webhook', [
                'subscription_id' => $subscription->id,
                'is_daily' => $isDailySubscription,
                'period_end' => $updateData['current_period_end']->format('Y-m-d'),
                'next_billing' => $updateData['next_billing_date']->format('Y-m-d'),
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
            
            // If Checkout Session mode is 'subscription', retrieve and save the subscription ID
            $stripeSubscriptionId = null;
            if ($session->mode === 'subscription' && $session->subscription) {
                $stripeSubscriptionId = $session->subscription;
                
                // Retrieve full subscription details from Stripe
                try {
                    $stripeSubscription = \Stripe\Subscription::retrieve($stripeSubscriptionId);
                    
                    // Update or create subscription record with Stripe subscription ID
                    if ($invoice->subscription) {
                        $updateData = [
                            'stripe_subscription_id' => $stripeSubscriptionId,
                            'stripe_customer_id' => $stripeSubscription->customer ?? null,
                            'status' => $stripeSubscription->status ?? 'active',
                        ];
                        
                        // Only update timestamps if they exist and are not null
                        if (isset($stripeSubscription->current_period_start) && $stripeSubscription->current_period_start !== null) {
                            $updateData['current_period_start'] = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_start);
                        }
                        if (isset($stripeSubscription->current_period_end) && $stripeSubscription->current_period_end !== null) {
                            $updateData['current_period_end'] = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end);
                            $updateData['next_billing_date'] = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end);
                        }
                        
                        $invoice->subscription->update($updateData);
                        
                        Log::info("Updated subscription with Stripe subscription ID via webhook", [
                            'subscription_id' => $invoice->subscription->id,
                            'stripe_subscription_id' => $stripeSubscriptionId
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to retrieve Stripe subscription via webhook: " . $e->getMessage());
                }
            }
            
            DB::beginTransaction();
            
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
                'stripe_subscription_id' => $stripeSubscriptionId,
                'amount' => $session->amount_total / 100,
                'currency' => 'gbp',
                'status' => 'succeeded',
                'type' => $session->mode === 'subscription' ? 'subscription' : 'one_time_payment',
                'paid_at' => now(),
            ]);
            
            // Update invoice status
            $invoice->status = 'paid';
            $invoice->paid_date = now();
            $invoice->save();
            $invoice->refresh();
            
            Log::info("Invoice {$invoice->id} status updated to paid via webhook", [
                'invoice_status' => $invoice->status,
                'paid_date' => $invoice->paid_date,
                'stripe_subscription_id' => $stripeSubscriptionId
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
            
            DB::commit();
            
            Log::info("Stripe Checkout payment processed via webhook for invoice {$invoice->id}: {$session->payment_intent}");
            
        } catch (\Exception $e) {
            DB::rollBack();
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

                        // Send suspension email
                        $invoice = Invoice::where('subscription_id', $subscription->id)
                            ->where('status', 'unpaid')
                            ->latest()
                            ->first();
                        
                        $user = null;
                        if ($subscription->user_id) {
                            $user = \App\Models\User::find($subscription->user_id);
                        } elseif ($subscription->organisation_id) {
                            $org = Organisation::find($subscription->organisation_id);
                            if ($org) {
                                $user = \App\Models\User::where('email', $org->admin_email)->first();
                            }
                        }
                        
                        if ($user) {
                            try {
                                $outstandingAmount = $invoice ? $invoice->total_amount : 0;
                                Mail::to($user->email)->send(new AccountSuspendedMail($subscription, $user, $outstandingAmount));
                                Log::info('Account suspension email sent via webhook', ['subscription_id' => $subscription->id]);
                            } catch (\Exception $e) {
                                Log::error('Failed to send suspension email via webhook: ' . $e->getMessage());
                            }
                        }
            // Mail::to($organisation->admin_email)->send(new AccountSuspendedEmail());
        }
    }
}

