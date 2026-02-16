<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;
use App\Services\OneSignalService;
use App\Services\ActivityLogService;

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
        
        if (!$user) {
            return;
        }

        $seconds = 0;
        $fcmTokenCleared = false;

        // Track time spent on app
        if ($user->last_login_at) {
            $seconds = $user->last_login_at->diffInSeconds(now(), false);

            if ($seconds > 0) {
                $user->time_spent_on_app += $seconds;
            }
        }

        // Clear OneSignal device token (fcmToken) on logout
        if ($user->fcmToken) {
            $fcmToken = $user->fcmToken;
            $fcmTokenCleared = true;
            
            // Optionally remove device from OneSignal
            try {
                $oneSignal = new OneSignalService();
                $oneSignal->removePushDevice($fcmToken);
            } catch (\Exception $e) {
                \Log::warning('Failed to remove OneSignal device in logout listener', [
                    'user_id' => $user->id,
                    'fcmToken' => $fcmToken,
                    'error' => $e->getMessage(),
                ]);
            }
            
            // Clear device tokens from database
            $user->fcmToken = null;
            $user->deviceType = null;
            $user->deviceId = null;
        }

        $saved = $user->save();
        \Log::info("Logout event triggered for user: {$user->id}, time_spent updated: {$seconds} sec, fcmToken cleared: " . ($fcmTokenCleared ? "yes" : "no") . ", save: " . ($saved ? "success" : "fail"));
        
        // Log activity
        try {
            ActivityLogService::logLogout($user);
        } catch (\Exception $e) {
            \Log::warning('Failed to log logout activity: ' . $e->getMessage());
        }
    }
}
