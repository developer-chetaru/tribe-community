<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Invoice;
use App\Models\PaymentRecord;

class PaymentConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $invoice;
    public $payment;
    public $user;
    public $isBasecamp;

    public function __construct(Invoice $invoice, PaymentRecord $payment = null, $user = null, $isBasecamp = false)
    {
        $this->invoice = $invoice;
        $this->payment = $payment;
        $this->user = $user;
        $this->isBasecamp = $isBasecamp;
    }

    public function build()
    {
        // Get user from invoice if not provided
        if (!$this->user) {
            if ($this->isBasecamp && $this->invoice->user_id) {
                $this->user = \App\Models\User::find($this->invoice->user_id);
            } elseif ($this->invoice->organisation_id) {
                $org = \App\Models\Organisation::find($this->invoice->organisation_id);
                $this->user = $org ? \App\Models\User::where('email', $org->admin_email)->first() : null;
            }
        }

        $amount = $this->invoice->currency === 'gbp' ? '£' : '$';
        $amount .= number_format($this->invoice->total_amount, 2);

        return $this->subject('Payment Confirmation - Tribe365')
            ->view('emails.payment-confirmation-mail')
            ->with([
                'invoice' => $this->invoice,
                'payment' => $this->payment,
                'user' => $this->user,
                'amount' => $amount,
                'isBasecamp' => $this->isBasecamp,
                'billingPeriod' => $this->getBillingPeriod(),
            ]);
    }

    private function getBillingPeriod()
    {
        if ($this->invoice->subscription) {
            $start = $this->invoice->subscription->current_period_start;
            $end = $this->invoice->subscription->current_period_end;
            if ($start && $end) {
                return $start->format('M d, Y') . ' - ' . $end->format('M d, Y');
            }
        }
        return 'N/A';
    }
}

