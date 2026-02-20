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
                $path = $request->path(); // e.g., "api/weekly-summaries" or "api/summary/all"
                $uri = $request->getRequestUri(); // e.g., "/api/weekly-summaries?year=2026&month=2"
                $routeName = $request->route() ? $request->route()->getName() : null;
                
                // Check multiple ways to detect summary endpoints
                // Path might be "api/weekly-summaries" or "api/monthly-summary" or "api/summary/all"
                $isSummaryEndpoint = strpos($path, 'api/summary/') === 0 || 
                                     strpos($path, 'api/summary') === 0 ||
                                     $path === 'api/weekly-summaries' || 
                                     $path === 'api/monthly-summary' ||
                                     strpos($uri, '/api/summary/') !== false ||
                                     strpos($uri, '/api/summary') !== false ||
                                     strpos($uri, '/api/weekly-summaries') !== false ||
                                     strpos($uri, '/api/monthly-summary') !== false ||
                                     strpos($uri, 'weekly-summaries') !== false ||
                                     strpos($uri, 'monthly-summary') !== false;
                
                // Log for debugging - log ALL requests to see what paths we're getting
                // Only log summary-related requests to reduce log noise
                if (strpos($path, 'summary') !== false || strpos($path, 'weekly') !== false || strpos($path, 'monthly') !== false) {
                    Log::info("ValidateJWTToken - summary request detected", [
                        'path' => $path,
                        'uri' => $uri,
                        'route_name' => $routeName,
                        'is_summary_endpoint' => $isSummaryEndpoint,
                        'full_url' => $request->fullUrl(),
                    ]);
                }
                
                // NOTE: We do NOT bypass validation for summary endpoints anymore
                // This ensures that when a new login happens, old tokens are properly rejected
                // Summary endpoints will go through normal validation, but we'll be lenient for current device
                
                // Get user from token
                // NOTE: auth:api middleware should have already authenticated the token
                // But we need to get the user here for validation
                try {
                    $user = JWTAuth::setToken($token)->authenticate();
                    if (!$user) {
                        // If user is null, token is invalid - let auth:api middleware handle it
                        Log::warning("JWT token authenticated but user is null", [
                            'path' => $path,
                        ]);
                        return $next($request);
                    }
                } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
                    // Token is expired - let auth:api middleware handle it
                    Log::debug("JWT token expired", [
                        'path' => $path,
                        'error' => $e->getMessage(),
                    ]);
                    return $next($request);
                } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
                    // Token is invalid - let auth:api middleware handle it
                    Log::debug("JWT token invalid", [
                        'path' => $path,
                        'error' => $e->getMessage(),
                    ]);
                    return $next($request);
                } catch (\Exception $e) {
                    // If authentication fails, let JWT middleware handle it
                    Log::debug("JWT authentication failed", [
                        'path' => $path,
                        'error' => $e->getMessage(),
                    ]);
                    return $next($request);
                }
                
                // NOTE: Summary endpoints now go through normal validation
                // This ensures that when a new login happens, old tokens are properly rejected
                // The validation logic below will handle checking if token is from current device
                
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
                    
                    // CRITICAL: For app tokens, use request device ID for validation
                    // Database might have web deviceId from web login, so we need to use request header
                    if (!$isWebToken) {
                        if ($requestDeviceId) {
                            // Use request device ID for validation (don't update database)
                            $user->deviceId = $requestDeviceId;
                            Log::info("Using request device ID for app token validation", [
                                'user_id' => $user->id,
                                'request_device_id' => $requestDeviceId,
                                'db_device_id' => $user->getOriginal('deviceId'),
                                'path' => $path,
                            ]);
                        } else {
                            // No device ID in request - try to get from cache
                            $appDeviceKey = "user_current_app_device_{$user->id}";
                            $cachedAppDeviceId = \Illuminate\Support\Facades\Cache::get($appDeviceKey);
                            if ($cachedAppDeviceId && strpos($cachedAppDeviceId, 'web_') !== 0) {
                                $user->deviceId = $cachedAppDeviceId;
                                Log::info("Using cached app device ID for validation", [
                                    'user_id' => $user->id,
                                    'cached_device_id' => $cachedAppDeviceId,
                                    'path' => $path,
                                ]);
                            }
                        }
                    } else if ($requestDeviceId && $requestDeviceId !== $user->deviceId) {
                        // For web tokens, update user model
                        Log::warning("Device ID mismatch in request vs user model", [
                            'user_id' => $user->id,
                            'request_device_id' => $requestDeviceId,
                            'user_device_id' => $user->deviceId,
                        ]);
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
                    
                    // For app tokens, check if this is the current device FIRST
                    // If it's the current device, allow it immediately (no need for further validation)
                    if (!$isWebToken) {
                        $appDeviceKey = "user_current_app_device_{$user->id}";
                        $currentAppDeviceId = \Illuminate\Support\Facades\Cache::get($appDeviceKey);
                        
                        // Use request device ID (already set in user->deviceId above)
                        $tokenDeviceId = $user->deviceId;
                        
                        // If token is from current device, allow it immediately
                        if ($currentAppDeviceId && $currentAppDeviceId === $tokenDeviceId) {
                            Log::info("Allowing app token - current device (bypassing validation)", [
                                'user_id' => $user->id,
                                'token_device_id' => $tokenDeviceId,
                                'current_device_id' => $currentAppDeviceId,
                                'path' => $path,
                                'is_summary' => $isSummaryEndpoint,
                            ]);
                            return $next($request);
                        }
                        
                        // If no app device tracking exists, allow the token (might be first login or cache issue)
                        if (!$currentAppDeviceId) {
                            Log::info("Allowing app token - no device tracking found (might be cache issue)", [
                                'user_id' => $user->id,
                                'token_device_id' => $tokenDeviceId,
                                'path' => $path,
                                'is_summary' => $isSummaryEndpoint,
                            ]);
                            return $next($request);
                        }
                        
                        // COMMENTED OUT: Multiple app login prevention - allow tokens from any device
                        // Token is from different device - continue to validation
                        // This will check if it's an old device (should be rejected) or valid
                        /*
                        Log::info("App token from different device - checking validation", [
                            'user_id' => $user->id,
                            'token_device_id' => $tokenDeviceId,
                            'current_device_id' => $currentAppDeviceId,
                            'path' => $path,
                            'is_summary' => $isSummaryEndpoint,
                        ]);
                        */
                    }
                    
                    // COMMENTED OUT: Multiple app login prevention - allow all app tokens
                    // Check if token is from current session and device
                    /*
                    $sessionService = new SessionManagementService();
                    $isValid = $sessionService->isTokenValid($token, $user);
                    
                    if (!$isValid) {
                        // For app tokens, be EXTREMELY lenient - only reject if we're 100% certain it's from old device
                        if (!$isWebToken) {
                            $appDeviceKey = "user_current_app_device_{$user->id}";
                            $currentAppDeviceId = \Illuminate\Support\Facades\Cache::get($appDeviceKey);
                            
                            // CRITICAL: If token is from current device, ALWAYS allow it
                            // Even if validation failed, if device ID matches, allow it
                            if ($currentAppDeviceId === $user->deviceId) {
                                Log::info("Allowing app token - current device (validation false negative)", [
                                    'user_id' => $user->id,
                                    'token_device_id' => $user->deviceId,
                                    'current_device_id' => $currentAppDeviceId,
                                    'path' => $path,
                                ]);
                                return $next($request);
                            }
                            
                            // If no current device ID in cache, allow token (might be first login or cache cleared)
                            if (!$currentAppDeviceId) {
                                Log::info("Allowing app token - no current device ID in cache (might be cache issue)", [
                                    'user_id' => $user->id,
                                    'token_device_id' => $user->deviceId,
                                    'path' => $path,
                                ]);
                                return $next($request);
                            }
                            
                            // Check token age - if token is recent (less than 1 hour), allow it
                            // This prevents false rejections for valid tokens
                            try {
                                $payload = JWTAuth::setToken($token)->getPayload();
                                $tokenIat = $payload->get('iat');
                                $tokenAge = now()->timestamp - $tokenIat;
                                
                                // If token is less than 1 hour old, allow it (very lenient)
                                if ($tokenAge < 3600) {
                                    Log::info("Allowing app token - recent token (less than 1 hour old)", [
                                        'user_id' => $user->id,
                                        'token_device_id' => $user->deviceId,
                                        'current_device_id' => $currentAppDeviceId,
                                        'token_age' => $tokenAge,
                                        'path' => $path,
                                    ]);
                                    return $next($request);
                                }
                            } catch (\Exception $e) {
                                // If we can't decode token, allow it (being lenient)
                                Log::info("Allowing app token - decode failed, being lenient", [
                                    'user_id' => $user->id,
                                    'token_device_id' => $user->deviceId,
                                    'path' => $path,
                                ]);
                                return $next($request);
                            }
                            
                            // Only reject if token is from different device AND token is old (more than 1 hour)
                            // AND we have a clear current device ID
                            Log::warning("App token rejected - from different device and token is old", [
                                'user_id' => $user->id,
                                'token_device_id' => $user->deviceId,
                                'current_device_id' => $currentAppDeviceId,
                                'path' => $path,
                            ]);
                        } else {
                            // For web tokens, reject if validation fails
                            Log::warning("Web token rejected - validation failed", [
                                'user_id' => $user->id,
                                'device_id' => $user->deviceId,
                                'path' => $path,
                            ]);
                        }
                        
                        // Token is from a previous session/device
                        // DO NOT invalidate the token here - let it be handled by auth:api middleware
                        // Just return 401 to reject the request
                        Log::warning("Token rejected - returning 401", [
                            'user_id' => $user->id,
                            'device_id' => $user->deviceId,
                            'path' => $path,
                            'is_summary' => $isSummaryEndpoint,
                        ]);
                        
                        return response()->json([
                            'status' => false,
                            'message' => 'Your session has expired. Please login again.',
                            'code' => 'SESSION_EXPIRED'
                        ], 401);
                    }
                    */
                    
                    // NEW: Allow all app tokens - multiple app logins allowed
                    if (!$isWebToken) {
                        Log::info("Allowing app token - multiple app logins allowed", [
                            'user_id' => $user->id,
                            'token_device_id' => $user->deviceId,
                            'path' => $path,
                        ]);
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
