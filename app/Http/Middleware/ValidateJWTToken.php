<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Services\SessionManagementService;
use Illuminate\Support\Facades\Log;

class ValidateJWTToken
{
    /**
     * Handle an incoming request.
     * Validates that JWT token is from the most recent login session
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $token = $request->bearerToken();
            
            if ($token) {
                // Get user from token
                $user = JWTAuth::setToken($token)->authenticate();
                
                if ($user) {
                    // Get device ID from request header or user model
                    $requestDeviceId = $request->header('X-Device-Id') ?? $user->deviceId ?? null;
                    
                    // If device ID is provided in request but doesn't match user's device ID, update it
                    if ($requestDeviceId && $requestDeviceId !== $user->deviceId) {
                        Log::warning("Device ID mismatch in request vs user model", [
                            'user_id' => $user->id,
                            'request_device_id' => $requestDeviceId,
                            'user_device_id' => $user->deviceId,
                        ]);
                        // Update user's device ID to match request
                        $user->deviceId = $requestDeviceId;
                    }
                    
                    // Get token's issued at time first
                    try {
                        $payload = JWTAuth::setToken($token)->getPayload();
                        $tokenIat = $payload->get('iat');
                        $tokenAge = now()->timestamp - $tokenIat;
                        
                        // CRITICAL: Allow tokens issued within last 5 seconds (grace period for login)
                        // This prevents newly issued tokens from being rejected during login
                        if ($tokenAge <= 5) {
                            Log::debug("Allowing token - issued very recently (within grace period)", [
                                'user_id' => $user->id,
                                'token_age' => $tokenAge,
                            ]);
                            return $next($request);
                        }
                    } catch (\Exception $e) {
                        // If we can't decode token, let JWT middleware handle it
                        Log::debug("Failed to decode token for grace period check", [
                            'error' => $e->getMessage(),
                        ]);
                    }
                    
                    // Check if token is from current session and device
                    $sessionService = new SessionManagementService();
                    $isValid = $sessionService->isTokenValid($token, $user);
                    
                    if (!$isValid) {
                        // Token is from a previous session/device, invalidate it
                        try {
                            JWTAuth::setToken($token)->invalidate();
                        } catch (\Exception $e) {
                            // Token might already be invalid
                        }
                        
                        Log::warning("Token rejected - from previous session or device", [
                            'user_id' => $user->id,
                            'device_id' => $user->deviceId,
                        ]);
                        
                        return response()->json([
                            'status' => false,
                            'message' => 'Your session has expired. Please login again.',
                            'code' => 'SESSION_EXPIRED'
                        ], 401);
                    }
                }
            }
        } catch (\Exception $e) {
            // If token validation fails, let JWT middleware handle it
            Log::debug("Token validation check failed", [
                'error' => $e->getMessage(),
            ]);
        }
        
        return $next($request);
    }
}
