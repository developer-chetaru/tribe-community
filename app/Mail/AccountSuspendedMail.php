<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\Invoice;

class AccountSuspendedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $invoice;
    public $suspensionDate;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, Invoice $invoice = null)
    {
        $this->user = $user;
        $this->invoice = $invoice;
        $this->suspensionDate = $user->suspension_date ?? now();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Account Suspended - Payment Required',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.account-suspended',
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
