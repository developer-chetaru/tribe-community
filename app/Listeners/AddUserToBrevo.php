<?php

namespace App\Listeners;

use App\Services\BrevoService;
use Illuminate\Auth\Events\Registered;

class AddUserToBrevo
{
    protected $brevo;

    /**
     * Create a new listener instance.
     *
     * @param \App\Services\BrevoService $brevo
     */
    public function __construct(BrevoService $brevo)
    {
        $this->brevo = $brevo;
    }

    /**
     * Handle the registered event.
     *
     * @param \Illuminate\Auth\Events\Registered $event
     * @return void
     */
    public function handle(Registered $event)
    {
        $user = $event->user;
        $this->brevo->addContact($user->email, $user->name, '');
    }
}
