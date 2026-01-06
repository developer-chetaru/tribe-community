<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SubscriptionRecord;
use App\Models\PaymentRecord;
use App\Models\Payment;
use App\Models\PaymentFailureLog;
use App\Models\SubscriptionEvent;
use App\Models\User;
use App\Models\Invoice;
use App\Services\Billing\StripeService;
use App\Services\OneSignalService;
use App\Mail\PaymentFailedMail;
use App\Mail\AccountSuspendedMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessPaymentRetries extends Command
{
    protected $signature = 'billing:retry-payments';
    protected $description = 'Retry failed payments and handle account suspension';

    protected $stripeService;
    protected $oneSignalService;

    public function __construct(StripeService $stripeService, OneSignalService $oneSignalService)
    {
        parent::__construct();
        $this->stripeService = $stripeService;
        $this->oneSignalService = $oneSignalService;
    }

    public function handle()
    {
        $this->info('Starting payment retry process...');
        Log::info('Payment retry cron job started');

        // Process retries for subscriptions with failed payments
        $this->processPaymentRetries();

        // Handle grace period notifications
        $this->handleGracePeriodNotifications();

        // Handle grace period expiration
        $this->handleGracePeriodExpiration();

        // Handle suspension period expiration
        $this->handleSuspensionExpiration();

        $this->info('Payment retry process completed');
    }

    /**
     * Process payment retries based on Day 1, 2, 4, 6 schedule
     */
    protected function processPaymentRetries()
    {
        // Get subscriptions with unpaid invoices
        $subscriptions = SubscriptionRecord::whereIn('status', ['active', 'past_due'])
            ->whereHas('invoices', function($query) {
                $query->where('status', 'unpaid')
                      ->where('due_date', '<=', now());
            })
            ->get();

        foreach ($subscriptions as $subscription) {
            try {
                // Get the oldest unpaid invoice
                $unpaidInvoice = Invoice::where('subscription_id', $subscription->id)
                    ->where('status', 'unpaid')
                    ->where('due_date', '<=', now())
                    ->orderBy('due_date', 'asc')
                    ->first();

                if (!$unpaidInvoice) {
                    continue;
                }

                // Get last payment failure for this invoice
                $lastFailure = PaymentFailureLog::where('invoice_id', $unpaidInvoice->id)
                    ->where('status', 'pending_retry')
                    ->latest('failure_date')
                    ->first();

                if (!$lastFailure) {
                    // Check if invoice is overdue and create failure log
                    $daysOverdue = now()->diffInDays($unpaidInvoice->due_date);
                    if ($daysOverdue > 0) {
                        $this->createPaymentFailureLog($subscription, $unpaidInvoice, $daysOverdue);
                    }
                    continue;
                }

                $daysSinceFailure = now()->diffInDays($lastFailure->failure_date);
                
                // Retry schedule: Day 1, 2, 4, 6
                $retryDays = [1, 2, 4, 6];
                $attemptNumber = $lastFailure->retry_attempt;

                if (in_array($daysSinceFailure, $retryDays) && $attemptNumber <= count($retryDays)) {
                    $this->attemptRetry($subscription, $unpaidInvoice, $lastFailure, $attemptNumber);
                }

            } catch (\Exception $e) {
                Log::error("Failed to process retry for subscription {$subscription->id}: " . $e->getMessage());
                $this->error("Failed to process retry for subscription {$subscription->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Create payment failure log entry
     */
    protected function createPaymentFailureLog(SubscriptionRecord $subscription, Invoice $invoice, $daysOverdue)
    {
        // Check if failure log already exists
        $existingLog = PaymentFailureLog::where('invoice_id', $invoice->id)
            ->where('status', 'pending_retry')
            ->first();

        if ($existingLog) {
            return $existingLog;
        }

        $user = $subscription->user_id ? User::find($subscription->user_id) : null;
        $org = $subscription->organisation_id ? $subscription->organisation : null;

        $failureLog = PaymentFailureLog::create([
            'user_id' => $subscription->user_id,
            'organisation_id' => $subscription->organisation_id,
            'subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'payment_method' => 'stripe',
            'amount' => $invoice->total_amount,
            'currency' => 'USD',
            'failure_reason' => 'overdue_invoice',
            'failure_message' => "Invoice overdue by {$daysOverdue} days",
            'retry_attempt' => 1,
            'failure_date' => $invoice->due_date,
            'status' => 'pending_retry',
        ]);

        // Update user's last payment failure date
        if ($user) {
            $user->update([
                'last_payment_failure_date' => now(),
            ]);
        }

        // Log subscription event
        SubscriptionEvent::create([
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'organisation_id' => $subscription->organisation_id,
            'event_type' => 'payment_failed',
            'event_data' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount' => $invoice->total_amount,
                'days_overdue' => $daysOverdue,
            ],
            'triggered_by' => 'system',
            'event_date' => now(),
            'notes' => "Payment failed - Invoice overdue by {$daysOverdue} days",
        ]);

        return $failureLog;
    }

    /**
     * Attempt to retry payment
     */
    protected function attemptRetry(SubscriptionRecord $subscription, Invoice $invoice, PaymentFailureLog $failureLog, $attemptNumber)
    {
        $this->info("Retry attempt #{$attemptNumber} for subscription {$subscription->id}, invoice {$invoice->id}");

        // Update failure log
        $failureLog->update([
            'retry_attempt' => $attemptNumber + 1,
            'status' => 'retried',
        ]);

        // Try to charge the payment method
        try {
            if ($subscription->stripe_customer_id) {
                // Get default payment method
                $paymentMethod = $this->stripeService->getDefaultPaymentMethod($subscription->stripe_customer_id);
                
                if ($paymentMethod) {
                    // Create payment intent and charge
                    $paymentIntent = $this->stripeService->createPaymentIntent([
                        'amount' => $invoice->total_amount * 100, // Convert to cents
                        'currency' => 'usd',
                        'customer' => $subscription->stripe_customer_id,
                        'payment_method' => $paymentMethod->id,
                        'confirm' => true,
                        'description' => "Retry payment for invoice {$invoice->invoice_number}",
                    ]);

                    if ($paymentIntent->status === 'succeeded') {
                        // Payment succeeded - update invoice and subscription
                        $this->handlePaymentSuccess($subscription, $invoice, $paymentIntent->id);
                        return;
                    }
                }
            }

            // Payment failed - create new failure log
            $this->createPaymentFailureLog($subscription, $invoice, now()->diffInDays($invoice->due_date));

            // If this is attempt 3 (Day 4), enter grace period
            if ($attemptNumber >= 3) {
                $this->enterGracePeriod($subscription, $invoice);
            }

        } catch (\Exception $e) {
            Log::error("Payment retry failed for subscription {$subscription->id}: " . $e->getMessage());
            
            // Create new failure log
            $this->createPaymentFailureLog($subscription, $invoice, now()->diffInDays($invoice->due_date));
        }
    }

    /**
     * Handle successful payment
     */
    protected function handlePaymentSuccess(SubscriptionRecord $subscription, Invoice $invoice, $transactionId)
    {
        // Update invoice
        $invoice->update([
            'status' => 'paid',
            'paid_date' => now(),
        ]);

        // Create payment record
        Payment::create([
            'invoice_id' => $invoice->id,
            'user_id' => $subscription->user_id,
            'organisation_id' => $subscription->organisation_id,
            'payment_method' => 'stripe',
            'amount' => $invoice->total_amount,
            'transaction_id' => $transactionId,
            'status' => 'completed',
            'payment_date' => now(),
        ]);

        // Update subscription
        $subscription->update([
            'status' => 'active',
            'payment_failed_count' => 0,
        ]);

        // Update user status
        if ($subscription->user_id) {
            $user = User::find($subscription->user_id);
            if ($user) {
                $user->update([
                    'payment_grace_period_start' => null,
                    'last_payment_failure_date' => null,
                    'status' => $user->email_verified_at ? 'active_verified' : 'active_unverified',
                ]);
            }
        }

        // Mark failure logs as resolved
        PaymentFailureLog::where('invoice_id', $invoice->id)
            ->where('status', '!=', 'resolved')
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);

        // Log subscription event
        SubscriptionEvent::create([
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'organisation_id' => $subscription->organisation_id,
            'event_type' => 'payment_succeeded',
            'event_data' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount' => $invoice->total_amount,
                'transaction_id' => $transactionId,
            ],
            'triggered_by' => 'system',
            'event_date' => now(),
            'notes' => "Payment succeeded after retry",
        ]);

        Log::info("Payment succeeded for subscription {$subscription->id}, invoice {$invoice->id}");
    }

    /**
     * Enter grace period (after 3 failed attempts)
     */
    protected function enterGracePeriod(SubscriptionRecord $subscription, Invoice $invoice)
    {
        if ($subscription->status === 'suspended') {
            return;
        }

        $this->warn("Entering grace period for subscription {$subscription->id}");

        // Update subscription
        $subscription->update([
            'status' => 'past_due',
        ]);

        // Update user grace period start
        if ($subscription->user_id) {
            $user = User::find($subscription->user_id);
            if ($user && !$user->payment_grace_period_start) {
                $user->update([
                    'payment_grace_period_start' => now(),
                    'status' => 'suspended', // Temporarily suspended during grace period
                ]);
            }
        }

        // Log subscription event
        SubscriptionEvent::create([
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'organisation_id' => $subscription->organisation_id,
            'event_type' => 'grace_period_started',
            'event_data' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'grace_period_start' => now()->toDateString(),
            ],
            'triggered_by' => 'system',
            'event_date' => now(),
            'notes' => "Grace period started - 7 days to resolve payment",
        ]);

        // Send Day 1 grace period email
        $this->sendGracePeriodEmail($subscription, 1);

        Log::warning("Grace period started for subscription {$subscription->id}");
    }

    /**
     * Handle grace period notifications (Day 1, 3, 5)
     */
    protected function handleGracePeriodNotifications()
    {
        $users = User::whereNotNull('payment_grace_period_start')
            ->where('status', 'suspended')
            ->get();

        foreach ($users as $user) {
            $gracePeriodStart = Carbon::parse($user->payment_grace_period_start);
            $daysInGracePeriod = now()->diffInDays($gracePeriodStart);

            // Send notifications on Day 1, 3, 5
            if ($daysInGracePeriod == 1) {
                $this->sendGracePeriodEmail($user->subscriptions()->where('tier', 'basecamp')->first(), 1);
            } elseif ($daysInGracePeriod == 3) {
                $this->sendGracePeriodEmail($user->subscriptions()->where('tier', 'basecamp')->first(), 3);
            } elseif ($daysInGracePeriod == 5) {
                $this->sendGracePeriodEmail($user->subscriptions()->where('tier', 'basecamp')->first(), 5);
            }
        }
    }

    /**
     * Send grace period email notification
     */
    protected function sendGracePeriodEmail($subscription, $day)
    {
        if (!$subscription) {
            return;
        }

        $user = $subscription->user_id ? User::find($subscription->user_id) : null;
        if (!$user) {
            return;
        }

        $gracePeriodStart = $user->payment_grace_period_start 
            ? Carbon::parse($user->payment_grace_period_start) 
            : now();
        $daysRemaining = 7 - now()->diffInDays($gracePeriodStart);

        // Get unpaid invoice
        $unpaidInvoice = Invoice::where('subscription_id', $subscription->id)
            ->where('status', 'unpaid')
            ->orderBy('due_date', 'asc')
            ->first();

        if (!$unpaidInvoice) {
            return;
        }

        $subject = $day == 1 
            ? "Payment Failed - Action Required (Day 1)"
            : ($day == 3 
                ? "Payment Reminder - Your Account is at Risk (Day 3)"
                : "Final Warning - Account Suspension Imminent (Day 5)");

        $message = $day == 1
            ? "Your payment has failed. Please update your payment method to avoid account suspension."
            : ($day == 3
                ? "Your payment is still pending. Please resolve this immediately to avoid account suspension."
                : "This is your final warning. Your account will be suspended in {$daysRemaining} days if payment is not received.");

        // Send email notification
        try {
            Mail::to($user->email)->send(new PaymentFailedMail($user, $unpaidInvoice, $day, $daysRemaining));
            Log::info("Grace period email sent to {$user->email} - Day {$day}: {$subject}");
        } catch (\Exception $e) {
            Log::error("Failed to send grace period email: " . $e->getMessage());
        }

        // Send via OneSignal if available
        if ($this->oneSignalService) {
            try {
                $this->oneSignalService->sendNotification(
                    $user->fcmToken,
                    $subject,
                    $message,
                    ['type' => 'payment_failed', 'invoice_id' => $unpaidInvoice->id]
                );
            } catch (\Exception $e) {
                Log::warning("Failed to send push notification: " . $e->getMessage());
            }
        }
    }

    /**
     * Handle grace period expiration (Day 7+)
     */
    protected function handleGracePeriodExpiration()
    {
        $users = User::whereNotNull('payment_grace_period_start')
            ->where('status', 'suspended')
            ->get();

        foreach ($users as $user) {
            $gracePeriodStart = Carbon::parse($user->payment_grace_period_start);
            $daysInGracePeriod = now()->diffInDays($gracePeriodStart);

            if ($daysInGracePeriod >= 7) {
                $subscription = $user->subscriptions()->where('tier', 'basecamp')->first();
                if ($subscription) {
                    $this->suspendAccount($subscription);
                }
            }
        }
    }

    /**
     * Suspend account
     */
    protected function suspendAccount(SubscriptionRecord $subscription)
    {
        $this->error("Suspending account for subscription {$subscription->id}");

        // Update subscription
        $subscription->update([
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);

        // Update user
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
            $subscription->organisation->update([
                'status' => 'suspended',
            ]);
        }

        // Log subscription event
        SubscriptionEvent::create([
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'organisation_id' => $subscription->organisation_id,
            'event_type' => 'suspended',
            'event_data' => [
                'suspension_date' => now()->toDateString(),
                'reason' => 'payment_failed_grace_period_expired',
            ],
            'triggered_by' => 'system',
            'event_date' => now(),
            'notes' => "Account suspended after 7-day grace period expired",
        ]);

        // Send suspension email
        $this->sendSuspensionEmail($subscription);

        Log::warning("Account suspended for subscription {$subscription->id}");
    }

    /**
     * Send suspension email
     */
    protected function sendSuspensionEmail(SubscriptionRecord $subscription)
    {
        $user = $subscription->user_id ? User::find($subscription->user_id) : null;
        if (!$user) {
            return;
        }

        // Send suspension email
        try {
            $unpaidInvoice = Invoice::where('subscription_id', $subscription->id)
                ->where('status', 'unpaid')
                ->orderBy('due_date', 'asc')
                ->first();
            
            Mail::to($user->email)->send(new AccountSuspendedMail($user, $unpaidInvoice));
            Log::info("Suspension email sent to {$user->email}");
        } catch (\Exception $e) {
            Log::error("Failed to send suspension email: " . $e->getMessage());
        }

        // Send push notification
        if ($this->oneSignalService && $user->fcmToken) {
            try {
                $this->oneSignalService->sendNotification(
                    $user->fcmToken,
                    "Account Suspended",
                    "Your account has been suspended due to payment failure. Please reactivate to continue.",
                    ['type' => 'account_suspended', 'subscription_id' => $subscription->id]
                );
            } catch (\Exception $e) {
                Log::warning("Failed to send push notification: " . $e->getMessage());
            }
        }
    }

    /**
     * Handle suspension period expiration (30+ days)
     */
    protected function handleSuspensionExpiration()
    {
        $users = User::where('status', 'suspended')
            ->whereNotNull('suspension_date')
            ->get();

        foreach ($users as $user) {
            $suspensionDate = Carbon::parse($user->suspension_date);
            $daysSuspended = now()->diffInDays($suspensionDate);

            if ($daysSuspended >= 30) {
                $subscription = $user->subscriptions()->where('tier', 'basecamp')->first();
                if ($subscription) {
                    // Send final warning (7 days before deletion)
                    if ($daysSuspended < 37) {
                        $this->sendFinalWarningEmail($subscription, 37 - $daysSuspended);
                    } elseif ($daysSuspended >= 37) {
                        // Delete account and data
                        $this->deleteAccount($subscription);
                    }
                }
            }
        }
    }

    /**
     * Send final warning email
     */
    protected function sendFinalWarningEmail(SubscriptionRecord $subscription, $daysRemaining)
    {
        $user = $subscription->user_id ? User::find($subscription->user_id) : null;
        if (!$user) {
            return;
        }

        // TODO: Create email template and send via Mail facade
        Log::warning("Final warning email sent to {$user->email} - Account will be deleted in {$daysRemaining} days");
    }

    /**
     * Delete account after 37 days of suspension
     */
    protected function deleteAccount(SubscriptionRecord $subscription)
    {
        $this->error("Deleting account for subscription {$subscription->id}");

        // Cancel subscription in Stripe
        if ($subscription->stripe_subscription_id) {
            try {
                $this->stripeService->cancelSubscription($subscription->stripe_subscription_id, false);
            } catch (\Exception $e) {
                Log::error("Failed to cancel Stripe subscription: " . $e->getMessage());
            }
        }

        // Update subscription status
        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);

        // Update user status
        if ($subscription->user_id) {
            $user = User::find($subscription->user_id);
            if ($user) {
                $user->update([
                    'status' => 'cancelled',
                ]);
            }
        }

        // Update organisation if exists
        if ($subscription->organisation_id) {
            $subscription->organisation->update([
                'status' => 'deleted',
            ]);
        }

        // Log subscription event
        SubscriptionEvent::create([
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'organisation_id' => $subscription->organisation_id,
            'event_type' => 'account_deleted',
            'event_data' => [
                'deletion_date' => now()->toDateString(),
                'reason' => 'suspension_period_expired',
            ],
            'triggered_by' => 'system',
            'event_date' => now(),
            'notes' => "Account deleted after 37 days of suspension",
        ]);

        // TODO: Send deletion confirmation email
        Log::error("Account deleted for subscription {$subscription->id}");
    }
}
