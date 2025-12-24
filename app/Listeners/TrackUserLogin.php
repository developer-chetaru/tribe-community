<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\OneSignalService;

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
        $request = request();

        \Log::info("Login event triggered for user: " . $user->id);

        if (!$user->first_login_at) {
            $user->first_login_at = now();
            \Log::info("First login_at saved for user: " . $user->id);
        }
        $user->last_login_at = now();
        
        // COMMENTED OUT: Automatic timezone detection from IP
        // Timezone should be set from user profile instead
        // Get user's IP address and timezone
        // $ipAddress = $request->ip();
        // if ($ipAddress && $ipAddress !== '127.0.0.1' && $ipAddress !== '::1') {
        //     try {
        //         // Get timezone from IP using ipapi.co
        //         $response = Http::timeout(3)->get("https://ipapi.co/{$ipAddress}/timezone/");
        //         if ($response->successful()) {
        //             $timezone = trim($response->body());
        //             // Validate timezone
        //             if ($timezone && in_array($timezone, timezone_identifiers_list())) {
        //                 $user->timezone = $timezone;
        //                 Log::info("User {$user->id} timezone set to: {$timezone} from IP: {$ipAddress}");
        //             }
        //         }
        //     } catch (\Exception $e) {
        //         Log::warning("Failed to get timezone for IP {$ipAddress}: " . $e->getMessage());
        //     }
        // }
        
        $saved = $user->save();
        \Log::info("Login save result: " . ($saved ? "success" : "fail"));

        // ✅ Set OneSignal tags on login (for automation)
        try {
            $oneSignal = new OneSignalService();
            $oneSignal->setUserTagsOnLogin($user);
            Log::info("OneSignal tags set for user: " . $user->id);
        } catch (\Throwable $e) {
            Log::warning('OneSignal tag update failed on login', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
