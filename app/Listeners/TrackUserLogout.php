<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;

class TrackUserLogout
{
   /**
    * Handle the logout event.
    *
    * @param \Illuminate\Auth\Events\Logout $event
    * @return void
    */
    public function handle(Logout $event)
    {
        $user = $event->user;
        if ($user && $user->last_login_at) {
            $seconds = $user->last_login_at->diffInSeconds(now(), false);

            if ($seconds > 0) {
                $user->time_spent_on_app += $seconds;
            }
            $saved = $user->save();
            \Log::info("Logout event triggered for user: {$user->id}, time_spent updated: {$seconds} sec, save: " . ($saved ? "success" : "fail"));
        }
    }
}
