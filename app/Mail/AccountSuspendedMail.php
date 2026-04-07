<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\SubscriptionRecord;

class AccountSuspendedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subscription;
    public $user;
    public $suspensionDate;
    public $outstandingAmount;
    public $dataRetentionDays;

    public function __construct(SubscriptionRecord $subscription, $user = null, $outstandingAmount = 0)
    {
        $this->subscription = $subscription;
        $this->user = $user;
        $this->suspensionDate = $subscription->suspended_at ? $subscription->suspended_at->format('M d, Y') : now()->format('M d, Y');
        $this->outstandingAmount = $outstandingAmount;
        $this->dataRetentionDays = 30;
    }

    public function build()
    {
        // Get user from subscription if not provided
        if (!$this->user) {
            if ($this->subscription->user_id) {
                $this->user = \App\Models\User::find($this->subscription->user_id);
            } elseif ($this->subscription->organisation_id) {
                $org = \App\Models\Organisation::find($this->subscription->organisation_id);
                $this->user = $org ? \App\Models\User::where('email', $org->admin_email)->first() : null;
            }
        }

        $amount = '£';
        if ($this->outstandingAmount > 0) {
            $amount .= number_format($this->outstandingAmount, 2);
        } else {
            // Get from latest unpaid invoice
            $invoice = \App\Models\Invoice::where('subscription_id', $this->subscription->id)
                ->where('status', 'unpaid')
                ->latest()
                ->first();
            if ($invoice) {
                $amount = $invoice->currency === 'gbp' ? '£' : '$';
                $amount .= number_format($invoice->total_amount, 2);
            } else {
                $amount .= '0.00';
            }
        }

        return $this->subject('Account Suspended - Action Required - Tribe365')
            ->view('emails.account-suspended-mail')
            ->with([
                'subscription' => $this->subscription,
                'user' => $this->user,
                'suspensionDate' => $this->suspensionDate,
                'outstandingAmount' => $amount,
                'dataRetentionDays' => $this->dataRetentionDays,
                'deletionDate' => now()->addDays($this->dataRetentionDays)->format('M d, Y'),
                'reactivationUrl' => config('app.url') . '/billing/reactivate',
            ]);
    }
}

