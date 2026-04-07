<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Reflection;

class ReflectionResolvedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $reflection;
    public $admin;

    public function __construct(Reflection $reflection, $admin)
    {
        $this->reflection = $reflection;
        $this->admin = $admin;
    }

    public function build()
    {
        return $this->subject('Your Reflection has been Resolved')
            ->view('emails.reflection-resolved')
            ->with([
                'reflection' => $this->reflection,
                'admin' => $this->admin,
            ]);
    }
}
