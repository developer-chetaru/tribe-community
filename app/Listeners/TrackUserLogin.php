<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class TrackUserLogin
{
    /**
     * Handle the login event.
     *
     * @param \Illuminate\Auth\Events\Login $event
     * @return void
     */
    public function handle(Login $event)
    {
        $user = $event->user;

        \Log::info("Login event triggered for user: " . $user->id);

        if (!$user->first_login_at) {
            $user->first_login_at = now();
            \Log::info("First login_at saved for user: " . $user->id);
        }
        $user->last_login_at = now();
        $saved = $user->save();
        \Log::info("Login save result: " . ($saved ? "success" : "fail"));
    }
}
