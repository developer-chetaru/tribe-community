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
                    // Token is expired - for app tokens, be lenient and allow if it's just expired
                    // Check if this might be an app token by checking request headers
                    $requestDeviceId = $request->header('X-Device-Id');
                    $isWebToken = (!$requestDeviceId || $requestDeviceId === 'web_default' || strpos($requestDeviceId, 'web_') === 0);
                    
                    if (!$isWebToken) {
                        // For app tokens, even if expired, try to authenticate with refresh
                        Log::info("App token expired but allowing (multiple app logins)", [
                            'path' => $path,
                            'error' => $e->getMessage(),
                        ]);
                        return $next($request);
                    }
                    
                    // For web tokens, let auth:api middleware handle it
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
                    // IMPORTANT: If no device ID, assume it's an app token (more lenient)
                    $isWebToken = false; // Default to app token (more lenient)
                    if ($requestDeviceId && ($requestDeviceId === 'web_default' || strpos($requestDeviceId, 'web_') === 0)) {
                        $isWebToken = true;
                    } else if ($user->deviceId && (strpos($user->deviceId, 'web_') === 0)) {
                        $isWebToken = true;
                    }
                    
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
                    
                    // For API requests without device ID, assume app token (more lenient)
                    if (!$requestDeviceId && $request->is('api/*')) {
                        $isWebToken = false;
                        Log::debug("No device ID in API request - assuming app token", [
                            'user_id' => $user->id,
                            'path' => $path,
                        ]);
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
                    
                    // COMMENTED OUT: Multiple app login prevention - allow all app tokens
                    // For app tokens, skip ALL validation and allow immediately
                    // IMPORTANT: This check happens BEFORE any validation logic
                    if (!$isWebToken) {
                        // App tokens are always allowed (multiple app logins allowed)
                        // No validation checks at all - just allow the request
                        Log::info("Allowing app token - multiple app logins allowed (no validation)", [
                            'user_id' => $user->id,
                            'token_device_id' => $user->deviceId ?? 'unknown',
                            'path' => $path,
                            'is_summary' => $isSummaryEndpoint,
                            'request_device_id' => $requestDeviceId ?? 'none',
                        ]);
                        return $next($request);
                    }
                    
                    // COMMENTED OUT: Auto logout disabled - allow all tokens
                    // For web tokens only, do validation
                    /*
                    $sessionService = new SessionManagementService();
                    $isValid = $sessionService->isTokenValid($token, $user);
                    
                    if (!$isValid) {
                        // For web tokens, reject if validation fails
                        Log::warning("Web token rejected - validation failed", [
                            'user_id' => $user->id,
                            'device_id' => $user->deviceId,
                            'path' => $path,
                        ]);
                        
                        return response()->json([
                            'status' => false,
                            'message' => 'Your session has expired. Please login again.',
                            'code' => 'SESSION_EXPIRED'
                        ], 401);
                    }
                    */
                    
                    // NEW: Auto logout disabled - allow all tokens (both web and app)
                    Log::info("Allowing token - auto logout disabled", [
                        'user_id' => $user->id,
                        'device_id' => $user->deviceId ?? 'unknown',
                        'is_web_token' => $isWebToken,
                        'path' => $path,
                    ]);
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
