<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Invoice;
use App\Models\User;

class PaymentFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $invoice;
    public $amount;
    public $day; // Day 1, 3, or 5 for grace period emails
    public $daysRemaining;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, Invoice $invoice, $day = 1, $daysRemaining = null)
    {
        $this->user = $user;
        $this->invoice = $invoice;
        $this->amount = $invoice->total_amount;
        $this->day = $day;
        $this->daysRemaining = $daysRemaining ?? (7 - $day);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->day == 1 
            ? 'Payment Failed - Action Required (Day 1)'
            : ($this->day == 3 
                ? 'Payment Reminder - Your Account is at Risk (Day 3)'
                : 'Final Warning - Account Suspension Imminent (Day 5)');

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-failed',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
