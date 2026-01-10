<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Invoice;
use App\Models\SubscriptionRecord;

class PaymentFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $invoice;
    public $subscription;
    public $user;
    public $failureReason;
    public $gracePeriodDays;
    public $dayNumber; // Day 1, 3, or 5

    public function __construct(Invoice $invoice, SubscriptionRecord $subscription = null, $user = null, $failureReason = 'Insufficient funds', $dayNumber = 1)
    {
        $this->invoice = $invoice;
        $this->subscription = $subscription;
        $this->user = $user;
        $this->failureReason = $failureReason;
        $this->dayNumber = $dayNumber;
        $this->gracePeriodDays = $this->calculateGracePeriodDays();
    }

    public function build()
    {
        // Get user from invoice if not provided
        if (!$this->user) {
            if ($this->invoice->user_id) {
                $this->user = \App\Models\User::find($this->invoice->user_id);
            } elseif ($this->invoice->organisation_id) {
                $org = \App\Models\Organisation::find($this->invoice->organisation_id);
                $this->user = $org ? \App\Models\User::where('email', $org->admin_email)->first() : null;
            }
        }

        // Get subscription if not provided
        if (!$this->subscription && $this->invoice->subscription_id) {
            $this->subscription = SubscriptionRecord::find($this->invoice->subscription_id);
        }

        $amount = $this->invoice->currency === 'gbp' ? '£' : '$';
        $amount .= number_format($this->invoice->total_amount, 2);

        $subject = $this->dayNumber === 1 
            ? 'Payment Failed - Action Required - Tribe365'
            : ($this->dayNumber === 3 
                ? 'Payment Reminder - ' . $this->gracePeriodDays . ' Days Remaining - Tribe365'
                : 'Final Warning - Payment Required - Tribe365');

        return $this->subject($subject)
            ->view('emails.payment-failed-mail')
            ->with([
                'invoice' => $this->invoice,
                'subscription' => $this->subscription,
                'user' => $this->user,
                'amount' => $amount,
                'failureReason' => $this->failureReason,
                'gracePeriodDays' => $this->gracePeriodDays,
                'dayNumber' => $this->dayNumber,
                'updatePaymentUrl' => config('app.url') . '/billing',
            ]);
    }

    private function calculateGracePeriodDays()
    {
        if (!$this->subscription) {
            return 7;
        }

        // Calculate days remaining in grace period (7 days total)
        $lastFailedPayment = \App\Models\PaymentRecord::where('subscription_id', $this->subscription->id)
            ->where('status', 'failed')
            ->latest()
            ->first();

        if ($lastFailedPayment) {
            $daysElapsed = \Carbon\Carbon::parse($lastFailedPayment->created_at)->diffInDays(now());
            return max(0, 7 - $daysElapsed);
        }

        return 7;
    }
}

