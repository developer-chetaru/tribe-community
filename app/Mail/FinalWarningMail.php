<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Invoice;
use App\Models\SubscriptionRecord;

class FinalWarningMail extends Mailable
{
    use Queueable, SerializesModels;

    public $invoice;
    public $subscription;
    public $user;
    public $daysRemaining;
    public $suspensionDate;

    public function __construct(Invoice $invoice, SubscriptionRecord $subscription = null, $user = null, $daysRemaining = 2)
    {
        $this->invoice = $invoice;
        $this->subscription = $subscription;
        $this->user = $user;
        $this->daysRemaining = $daysRemaining;
        $this->suspensionDate = now()->addDays($daysRemaining)->format('M d, Y');
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

        return $this->subject('Final Warning - Account Suspension Imminent - Tribe365')
            ->view('emails.final-warning-mail')
            ->with([
                'invoice' => $this->invoice,
                'subscription' => $this->subscription,
                'user' => $this->user,
                'amount' => $amount,
                'daysRemaining' => $this->daysRemaining,
                'suspensionDate' => $this->suspensionDate,
                'paymentUrl' => config('app.url') . '/billing',
            ]);
    }
}

