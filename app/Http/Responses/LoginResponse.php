<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Services\SessionManagementService;
use Illuminate\Support\Facades\Log;

class LoginResponse implements LoginResponseContract
{
    /**
     * Generate the login response.
     *
     * Returns JSON response with JWT token for API requests,
     * or redirects based on user role for web requests.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function toResponse($request)
    {
        $user = Auth::guard('web')->user();
  		if (($request->wantsJson() || $request->is('api/*')) && !$user) {
            try {
                $user = JWTAuth::attempt($request->only('email', 'password'))
                    ? JWTAuth::user()
                    : null;
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }
        }

        if ($request->wantsJson() || $request->is('api/*')) {
            $token = $user ? JWTAuth::fromUser($user) : null;

            return response()->json([
                'status'  => true,
                'message' => 'Login successful',
                'user'    => $user,
                'token'   => $token,
            ]);
        }

        if ($user) {
            // ✅ Invalidate previous web sessions when user logs in from web
            try {
                $sessionId = $request->session()->getId();
                $sessionService = new SessionManagementService();
                
                // CRITICAL: Don't update user's deviceId in database for web sessions
                // This preserves the app deviceId so app tokens remain valid
                // Web sessions are tracked separately in cache only
                $webDeviceId = 'web_' . $sessionId;
                
                // Create a temporary user object with web device info for session management
                // But don't save it to database - only use for cache operations
                $tempUser = clone $user;
                $tempUser->deviceType = 'web';
                $tempUser->deviceId = $webDeviceId;
                
                // Invalidate previous web sessions (same platform only)
                $sessionService->invalidatePreviousSessions($tempUser, null, $sessionId);
                
                Log::info("Web login: Previous web sessions invalidated", [
                    'user_id' => $user->id,
                    'session_id' => $sessionId,
                    'web_device_id' => $webDeviceId,
                    'preserved_app_device_id' => $user->deviceId,
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to invalidate previous web sessions on login', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
            
            if ($user->hasRole('super_admin')) {
                return redirect()->route('organisations.index');
            } elseif ($user->hasRole('organisation_user')) {
                return redirect()->route('dashboard');
            }elseif ($user->hasRole('organisation_admin')) {
                return redirect()->route('dashboard');
            }elseif ($user->hasRole('director')) {
                return redirect()->route('dashboard');
            }
             else {
                return redirect()->intended('/dashboard');
            }
        }

        return redirect()->route('login');
    }
}
