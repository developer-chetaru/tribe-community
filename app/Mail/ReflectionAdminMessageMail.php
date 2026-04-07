<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Reflection;

class ReflectionAdminMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public $reflection;
    public $admin;

    /**
     * Create a new message instance.
     */
    public function __construct(Reflection $reflection, $admin)
    {
        $this->reflection = $reflection;
        $this->admin = $admin;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Admin Started Conversation About Your Reflection')
            ->view('emails.reflection-admin-message')
            ->with([
                'reflection' => $this->reflection,
                'admin' => $this->admin,
            ]);
    }
}
