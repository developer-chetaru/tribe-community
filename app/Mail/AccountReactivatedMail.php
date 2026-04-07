<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\SubscriptionRecord;

class AccountReactivatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subscription;
    public $user;
    public $nextBillingDate;

    public function __construct(SubscriptionRecord $subscription, $user = null)
    {
        $this->subscription = $subscription;
        $this->user = $user;
        $this->nextBillingDate = $subscription->next_billing_date 
            ? $subscription->next_billing_date->format('M d, Y') 
            : ($subscription->current_period_end 
                ? $subscription->current_period_end->format('M d, Y') 
                : 'N/A');
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

        return $this->subject('Welcome Back! Your Account Has Been Reactivated - Tribe365')
            ->view('emails.account-reactivated-mail')
            ->with([
                'subscription' => $this->subscription,
                'user' => $this->user,
                'nextBillingDate' => $this->nextBillingDate,
                'dashboardUrl' => config('app.url') . '/dashboard',
            ]);
    }
}

