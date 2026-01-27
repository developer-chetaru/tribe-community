<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\OneSignalService;
use App\Services\SubscriptionService;
use App\Services\SessionManagementService;

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
        
        // Refresh user to ensure we have latest data (especially timezone) before OneSignal update
        $user->refresh();
        if ($user->orgId) {
            $user->load('organisation');
        }

        // ✅ Store active session for single session management
        // This runs after session regeneration in LoginForm
        // Note: LoginForm already stores the session, so we only store if it wasn't stored yet
        try {
            $sessionService = new SessionManagementService();
            
            // Check if this is a web login (has session) or API login
            if (session()->isStarted()) {
                $currentSessionId = session()->getId();
                
                // Check if session is already stored (LoginForm might have stored it)
                $activeSessionInfo = $sessionService->getActiveSessionInfo($user, $currentSessionId);
                
                if (!$activeSessionInfo) {
                    // Session not stored yet, store it now
                    $sessionService->storeActiveSession($user, $currentSessionId);
                    Log::info("Active session stored in listener for user {$user->id}", [
                        'session_id' => $currentSessionId,
                    ]);
                } else {
                    Log::debug("Session already stored for user {$user->id}", [
                        'session_id' => $currentSessionId,
                    ]);
                }
            }
            // For API login, session management is handled in AuthController
        } catch (\Exception $e) {
            Log::warning('Failed to store session on login event', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        // ✅ Set OneSignal tags on login (for automation)
        try {
            // Fetch fresh user instance from database to ensure latest timezone
            $freshUser = \App\Models\User::with('organisation')->find($user->id);
            
            if (!$freshUser) {
                Log::warning('User not found for OneSignal update', ['user_id' => $user->id]);
                return;
            }
            
            Log::info("OneSignal update - User timezone before update", [
                'user_id' => $freshUser->id,
                'timezone' => $freshUser->timezone,
                'org_id' => $freshUser->orgId,
            ]);
            
            $oneSignal = new OneSignalService();
            $oneSignal->setUserTagsOnLogin($freshUser);
            
            Log::info("OneSignal tags set for user: " . $freshUser->id, [
                'timezone' => $freshUser->timezone,
                'timezone_from_helper' => \App\Helpers\TimezoneHelper::getUserTimezone($freshUser),
            ]);
        } catch (\Throwable $e) {
            Log::warning('OneSignal tag update failed on login', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // ✅ Check subscription and auto-generate invoice for directors
        if ($user->hasRole('director') && $user->orgId) {
            try {
                $subscriptionService = new SubscriptionService();
                $subscriptionStatus = $subscriptionService->getSubscriptionStatus($user->orgId);
                
                // If subscription is not active, auto-generate invoice
                if (!$subscriptionStatus['active']) {
                    $invoice = $subscriptionService->checkAndGenerateInvoice($user->orgId);
                    if ($invoice) {
                        Log::info("Auto-generated invoice {$invoice->invoice_number} for director {$user->id} on login - Amount: {$invoice->total_amount}, Users: {$invoice->user_count}");
                        
                        // Store subscription status in session for display
                        session()->flash('subscription_status', [
                            'active' => false,
                            'message' => 'Your subscription has expired. A new invoice has been generated. Please pay to reactivate.',
                            'days_remaining' => 0,
                            'invoice_id' => $invoice->id,
                        ]);
                    }
                } else {
                    // Store active subscription status
                    session()->flash('subscription_status', $subscriptionStatus);
                }
            } catch (\Throwable $e) {
                Log::warning('Subscription check failed on director login', [
                    'user_id' => $user->id,
                    'org_id' => $user->orgId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
