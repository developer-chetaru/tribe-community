<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;
use App\Services\OneSignalService;
use App\Services\ActivityLogService;
use App\Services\LoginSessionService;

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
        
        // Log detailed logout session
        try {
            $sessionService = new LoginSessionService();
            // Try to get session ID before it's destroyed
            $sessionId = null;
            try {
                if (session()->isStarted()) {
                    $sessionId = session()->getId();
                }
            } catch (\Exception $e) {
                // Session might already be destroyed
                \Log::debug('Session ID not available during logout: ' . $e->getMessage());
            }
            
            // Try to logout by session ID first
            $loggedOutSession = null;
            if ($sessionId) {
                $loggedOutSession = $sessionService->logLogout($sessionId, null, null);
            }
            
            // If session ID logout failed, try to logout all active sessions for this user
            if (!$loggedOutSession) {
                \Log::info('Logging out all active sessions for user', [
                    'user_id' => $user->id,
                    'session_id' => $sessionId,
                ]);
                
                // Logout all active sessions for this user
                $count = $sessionService->logLogoutAllActiveSessions($user->id);
                \Log::info('Logged out active sessions', [
                    'user_id' => $user->id,
                    'sessions_logged_out' => $count,
                ]);
            } else {
                \Log::info('Logged out session by session ID', [
                    'user_id' => $user->id,
                    'session_id' => $sessionId,
                    'login_session_id' => $loggedOutSession->id,
                ]);
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to log logout session: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);
        }
        
        // Log activity
        try {
            ActivityLogService::logLogout($user);
        } catch (\Exception $e) {
            \Log::warning('Failed to log logout activity: ' . $e->getMessage());
        }
    }
}
