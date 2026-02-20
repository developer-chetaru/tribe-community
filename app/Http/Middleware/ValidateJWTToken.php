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
                // CRITICAL: Check if this is a summary endpoint FIRST, before authentication
                // This allows us to skip validation for summary endpoints
                $path = $request->path();
                $isSummaryEndpoint = strpos($path, 'api/summary/') === 0 || 
                                     $path === 'api/weekly-summaries' || 
                                     $path === 'api/monthly-summary';
                
                // Log for debugging
                if ($isSummaryEndpoint) {
                    Log::info("Summary endpoint detected", [
                        'path' => $path,
                        'full_url' => $request->fullUrl(),
                    ]);
                }
                
                // Get user from token
                try {
                    $user = JWTAuth::setToken($token)->authenticate();
                } catch (\Exception $e) {
                    // If authentication fails, let JWT middleware handle it
                    Log::debug("JWT authentication failed", [
                        'path' => $path,
                        'error' => $e->getMessage(),
                    ]);
                    return $next($request);
                }
                
                // CRITICAL: For app tokens on summary endpoints, SKIP ALL VALIDATION
                // Only check if token is expired (JWT library handles that)
                if ($isSummaryEndpoint && $user) {
                    // Get device ID from request header or user model
                    $requestDeviceId = $request->header('X-Device-Id') ?? $user->deviceId ?? null;
                    $isWebToken = (!$requestDeviceId || $requestDeviceId === 'web_default' || strpos($requestDeviceId, 'web_') === 0);
                    
                    Log::info("Summary endpoint - checking token", [
                        'user_id' => $user->id,
                        'device_id' => $requestDeviceId,
                        'is_web_token' => $isWebToken,
                        'path' => $path,
                    ]);
                    
                    // For app tokens on summary endpoints, SKIP ALL VALIDATION
                    // Just verify token is not expired - if not expired, allow immediately
                    if (!$isWebToken) {
                        try {
                            // Just verify token is not expired
                            $payload = JWTAuth::setToken($token)->getPayload();
                            $exp = $payload->get('exp');
                            
                            // If token is not expired, allow it immediately (skip ALL other validation)
                            if ($exp && $exp > now()->timestamp) {
                                Log::info("Allowing app token on summary endpoint - token not expired, skipping ALL validation", [
                                    'user_id' => $user->id,
                                    'device_id' => $requestDeviceId,
                                    'endpoint' => $path,
                                    'expires_at' => $exp,
                                ]);
                                return $next($request);
                            } else {
                                Log::warning("App token on summary endpoint is expired", [
                                    'user_id' => $user->id,
                                    'device_id' => $requestDeviceId,
                                    'endpoint' => $path,
                                    'expires_at' => $exp,
                                    'current_time' => now()->timestamp,
                                ]);
                            }
                            // If expired, let it continue to JWT middleware which will handle it
                        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
                            // Token is expired - let JWT middleware handle it
                            Log::warning("App token on summary endpoint is expired (exception)", [
                                'user_id' => $user->id,
                                'device_id' => $requestDeviceId,
                                'endpoint' => $path,
                            ]);
                        } catch (\Exception $e) {
                            // If we can't decode, allow it for app tokens on summary endpoints (being very lenient)
                            Log::info("Allowing app token on summary endpoint - decode failed, being lenient", [
                                'user_id' => $user->id,
                                'device_id' => $requestDeviceId,
                                'endpoint' => $path,
                                'error' => $e->getMessage(),
                            ]);
                            return $next($request);
                        }
                    } else {
                        // Web token on summary endpoint - allow it (web tokens are handled differently)
                        Log::info("Allowing web token on summary endpoint", [
                            'user_id' => $user->id,
                            'device_id' => $requestDeviceId,
                            'endpoint' => $path,
                        ]);
                        return $next($request);
                    }
                }
                
                // If we reach here and it's a summary endpoint but user is null, still allow it
                // (JWT middleware will handle authentication failure)
                if ($isSummaryEndpoint) {
                    Log::info("Summary endpoint - user is null, allowing to continue", [
                        'path' => $path,
                    ]);
                    return $next($request);
                }
                
                if ($user) {
                    // Get device ID from request header or user model
                    $requestDeviceId = $request->header('X-Device-Id') ?? $user->deviceId ?? null;
                    
                    // CRITICAL: For app tokens, prioritize request header device ID
                    // Web login might have overwritten user's deviceId in database
                    // So we need to check cache-based device mapping instead
                    $isWebToken = (!$requestDeviceId || $requestDeviceId === 'web_default' || strpos($requestDeviceId, 'web_') === 0);
                    
                    // If no device ID in request, try to get from cache (app device might be cached)
                    if (!$requestDeviceId) {
                        $appDeviceKey = "user_current_app_device_{$user->id}";
                        $cachedAppDeviceId = \Illuminate\Support\Facades\Cache::get($appDeviceKey);
                        if ($cachedAppDeviceId && strpos($cachedAppDeviceId, 'web_') !== 0) {
                            $requestDeviceId = $cachedAppDeviceId;
                            $isWebToken = false;
                            Log::debug("Using cached app device ID (no header provided)", [
                                'user_id' => $user->id,
                                'cached_app_device_id' => $cachedAppDeviceId,
                            ]);
                        }
                    }
                    
                    if (!$isWebToken && $requestDeviceId) {
                        // This is an app token - use request device ID, don't update database
                        // Database might have web deviceId from web login
                        $user->deviceId = $requestDeviceId;
                        Log::debug("Using request device ID for app token validation", [
                            'user_id' => $user->id,
                            'request_device_id' => $requestDeviceId,
                            'db_device_id' => $user->getOriginal('deviceId'),
                        ]);
                    } else if ($requestDeviceId && $requestDeviceId !== $user->deviceId) {
                        // For web tokens or if no device ID in request, update user model
                        Log::warning("Device ID mismatch in request vs user model", [
                            'user_id' => $user->id,
                            'request_device_id' => $requestDeviceId,
                            'user_device_id' => $user->deviceId,
                        ]);
                        // Only update if it's a web token (app tokens handled above)
                        if ($isWebToken) {
                            $user->deviceId = $requestDeviceId;
                        }
                    }
                    
                    // Get token's issued at time first
                    try {
                        $payload = JWTAuth::setToken($token)->getPayload();
                        $tokenIat = $payload->get('iat');
                        $tokenAge = now()->timestamp - $tokenIat;
                        
                        // CRITICAL: Allow tokens issued within last 60 seconds (grace period for login)
                        // This prevents newly issued tokens from being rejected during login
                        // For app tokens, be even more lenient
                        $gracePeriod = $isWebToken ? 60 : 120; // 60s for web, 120s for app
                        
                        if ($tokenAge <= $gracePeriod) {
                            // Also check if this is the current device - if yes, always allow
                            $platformDeviceKey = $isWebToken 
                                ? "user_current_web_device_{$user->id}"
                                : "user_current_app_device_{$user->id}";
                            $currentDeviceId = \Illuminate\Support\Facades\Cache::get($platformDeviceKey);
                            
                            // If token is from current device and within grace period, allow it
                            if ($currentDeviceId === $user->deviceId) {
                                Log::debug("Allowing token - current device and within grace period", [
                                    'user_id' => $user->id,
                                    'token_age' => $tokenAge,
                                    'device_id' => $user->deviceId,
                                    'is_web_token' => $isWebToken,
                                ]);
                                return $next($request);
                            }
                            
                            // Even if not current device, allow if within grace period (might be during login)
                            Log::debug("Allowing token - issued very recently (within grace period)", [
                                'user_id' => $user->id,
                                'token_age' => $tokenAge,
                                'is_web_token' => $isWebToken,
                            ]);
                            return $next($request);
                        }
                    } catch (\Exception $e) {
                        // If we can't decode token, let JWT middleware handle it
                        Log::debug("Failed to decode token for grace period check", [
                            'error' => $e->getMessage(),
                        ]);
                    }
                    
                    // For app tokens, be extremely lenient - default to allowing unless we're 100% certain it's invalid
                    if (!$isWebToken) {
                        // For app tokens, check if there's any device tracking at all
                        $appDeviceKey = "user_current_app_device_{$user->id}";
                        $currentAppDeviceId = \Illuminate\Support\Facades\Cache::get($appDeviceKey);
                        
                        // If no app device tracking exists, allow the token (might be first login or cache issue)
                        if (!$currentAppDeviceId) {
                            Log::debug("Allowing app token - no device tracking found (might be cache issue)", [
                                'user_id' => $user->id,
                                'token_device_id' => $user->deviceId,
                            ]);
                            return $next($request);
                        }
                        
                        // For app tokens, check token age first - if it's recent, allow it
                        try {
                            $payload = JWTAuth::setToken($token)->getPayload();
                            $tokenIat = $payload->get('iat');
                            $tokenAge = now()->timestamp - $tokenIat;
                            
                            // If token is less than 10 minutes old, allow it (very lenient for app tokens)
                            if ($tokenAge < 600) {
                                Log::debug("Allowing app token - relatively recent (within 10 minutes)", [
                                    'user_id' => $user->id,
                                    'token_age' => $tokenAge,
                                    'device_id' => $user->deviceId,
                                ]);
                                return $next($request);
                            }
                        } catch (\Exception $e) {
                            // If we can't decode, allow it for app tokens (being lenient)
                            Log::debug("Allowing app token - decode failed, being lenient", [
                                'user_id' => $user->id,
                                'device_id' => $user->deviceId,
                            ]);
                            return $next($request);
                        }
                    }
                    
                    // Check if token is from current session and device
                    $sessionService = new SessionManagementService();
                    $isValid = $sessionService->isTokenValid($token, $user);
                    
                    if (!$isValid) {
                        // For app tokens, be extremely lenient - only reject if we're absolutely certain
                        if (!$isWebToken) {
                            // For app tokens, default to allowing unless we're 100% sure it's invalid
                            // Check token age one more time
                            try {
                                $payload = JWTAuth::setToken($token)->getPayload();
                                $tokenIat = $payload->get('iat');
                                $tokenAge = now()->timestamp - $tokenIat;
                                
                                // If token is less than 30 minutes old, allow it (very lenient)
                                if ($tokenAge < 1800) {
                                    Log::debug("Allowing app token - validation failed but token is recent (within 30 minutes)", [
                                        'user_id' => $user->id,
                                        'token_age' => $tokenAge,
                                        'device_id' => $user->deviceId,
                                    ]);
                                    return $next($request);
                                }
                            } catch (\Exception $e) {
                                // If we can't decode, allow it for app tokens
                                Log::debug("Allowing app token - validation failed but decode also failed, being lenient", [
                                    'user_id' => $user->id,
                                    'device_id' => $user->deviceId,
                                ]);
                                return $next($request);
                            }
                            
                            // Only reject if token is very old (more than 30 minutes) AND validation failed
                            // This ensures we only reject tokens that are definitely invalid
                            Log::warning("App token rejected - very old and validation failed", [
                                'user_id' => $user->id,
                                'device_id' => $user->deviceId,
                                'token_age' => $tokenAge ?? 'unknown',
                            ]);
                        } else {
                            // For web tokens, reject if validation fails
                            Log::warning("Web token rejected - validation failed", [
                                'user_id' => $user->id,
                                'device_id' => $user->deviceId,
                            ]);
                        }
                        
                        // Token is from a previous session/device, invalidate it
                        try {
                            JWTAuth::setToken($token)->invalidate();
                        } catch (\Exception $e) {
                            // Token might already be invalid
                        }
                        
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
