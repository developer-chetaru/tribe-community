<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomResetPasswordNotification extends Notification
{
    use Queueable;

    public $token;
    public $orgName;
    public $inviterName;

    public function __construct($token, $orgName, $inviterName = null)
    {
        $this->token = $token;
        $this->orgName = $orgName;
        $this->inviterName = $inviterName;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $subject = $this->inviterName 
            ? "{$this->inviterName} invited you to join  {$this->orgName} on Tribe365"
            : "Reset your Tribe365 password";

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.custom-reset-password', [
                'user'        => $notifiable, 
                'orgName'     => $this->orgName,
                'resetUrl'    => $resetUrl,
                'userFullName'=> $notifiable->first_name . ' ' . $notifiable->last_name, 
                'inviterName' => $this->inviterName, 
            ]);
    }
}
