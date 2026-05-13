<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class IOTSentMsgMail extends Mailable
{
    use Queueable, SerializesModels;

    public $sender;
    public $originalMessage;
    public $newMessage;
    public $imageUrl;

    public function __construct(User $sender, $originalMessage, $newMessage, $imageUrl = null)
    {
        $this->sender = $sender;
        $this->originalMessage = $originalMessage;
        $this->newMessage = $newMessage;
        $this->imageUrl = $imageUrl;
    }

    public function build()
    {
        return $this->subject('Tribe365: Chat Message Received')
            ->view('emails.iot-sent-msg')
            ->with([
                'sender' => $this->sender,
                'originalMessage' => $this->originalMessage,
                'newMessage' => $this->newMessage,
                'imageUrl' => $this->imageUrl,
            ]);
    }
}

