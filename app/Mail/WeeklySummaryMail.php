<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WeeklySummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $weekLabel;
    public $summaryText;
    public $logoCid;

    public function __construct($user, $weekLabel, $summaryText)
    {
        $this->user = $user;
        $this->weekLabel = $weekLabel;
        $this->summaryText = $summaryText;
    }

    public function build()
    {
        return $this->subject('Weekly Sentiment Summary')
            ->withSymfonyMessage(function ($message) {
                $this->logoCid = $message->embed(
                    fopen(public_path('images/logo-tribe.png'), 'r'),
                    'logo-tribe.png'
                );
            })
            ->view('emails.test-summary', [
                'logoCid' => $this->logoCid,
            ]);
    }
}
