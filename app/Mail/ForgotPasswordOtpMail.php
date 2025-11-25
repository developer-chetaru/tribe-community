<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForgotPasswordOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;
    public $first_name;

    /**
     * Create a new message instance.
     */
    public function __construct($otp, $first_name)
    {
        $this->otp = $otp;
        $this->first_name = $first_name;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Your Password Reset OTP')
                    ->view('emails.forgot-password-otp');
    }
}
