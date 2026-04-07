<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class StaffResetPassword extends Notification implements ShouldQueue
{
    use Queueable;

    public $token;
    public $organizationName;

    /**
     * Create a new notification instance.
     */
    public function __construct($token, $organizationName)
    {
        $this->token = $token;
        $this->organizationName = $organizationName;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

  public function toMail($notifiable)
	{
    	$url = url(route('password.reset', [
        	'token' => $this->token,
        	'email' => $notifiable->getEmailForPasswordReset(),
    	], false));

   	 return (new MailMessage)
    	    ->subject('Tribe365 - Password Reset')
      	  ->view('emails.staff_reset_password', [
        	    'user' => $notifiable,
            	'url'  => $url,
            	'organisation' => $this->organizationName,
        	]);
	}
}
