<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PostFeedbackNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $message;
    public $imageUrl;

    public function __construct(User $user, $message, $imageUrl = null)
    {
        $this->user = $user;
        $this->message = $message;
        $this->imageUrl = $imageUrl;
    }

    public function build()
    {
        return $this->subject('Tribe365: Offloading Received')
            ->view('emails.post-feedback-notification')
            ->with([
                'user' => $this->user,
                'message' => $this->message,
                'imageUrl' => $this->imageUrl,
            ]);
    }
}

