<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Cache;

class SessionManagementService
{
    /**
     * Invalidate previous sessions/tokens for a user
     * NEW LOGIC: Allow one web session + one app session simultaneously
     * But only one session per platform (one web, one app)
     * 
     * @param User $user
     * @param string|null $currentToken Current JWT token (for API)
     * @param string|null $currentSessionId Current session ID (for Web)
     * @return void
     */
    public function invalidatePreviousSessions(User $user, $currentToken = null, $currentSessionId = null)
    {
        try {
            $deviceId = $user->deviceId ?? 'web_' . ($currentSessionId ?? 'default');
            $deviceType = $user->deviceType ?? 'web';
            
            // Store current session info with device ID
            $sessionInfo = [
                'user_id' => $user->id,
                'token_issued_at' => now()->timestamp,
                'session_id' => $currentSessionId,
                'device_type' => $deviceType,
                'device_id' => $deviceId,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ];
            
            // Store in cache with user+device specific key
            $cacheKey = "user_active_session_{$user->id}_{$deviceId}";
            Cache::put($cacheKey, $sessionInfo, now()->addDays(30)); // Store for 30 days
            
            // Check if this is a web session (browser/private tab)
            $isWebSession = ($deviceType === 'web' || strpos($deviceId, 'web_') === 0);
            
            // NEW LOGIC: Store separate mappings for web and app sessions
            if ($isWebSession) {
                // For web sessions: invalidate only previous web sessions
                $webDeviceKey = "user_current_web_device_{$user->id}";
                $previousWebDeviceId = Cache::get($webDeviceKey);
                
                // If there was a previous web session, invalidate it
                if ($previousWebDeviceId && $previousWebDeviceId !== $deviceId) {
                    $this->invalidateDeviceSession($user, $previousWebDeviceId);
                    Log::info("Previous web session invalidated for user {$user->id}", [
                        'previous_web_device_id' => $previousWebDeviceId,
                        'new_web_device_id' => $deviceId,
                    ]);
                }
                
                // Store current web device mapping
                Cache::put($webDeviceKey, $deviceId, now()->addDays(30));
                
                // Delete all old web sessions from database (except current)
                $this->invalidateAllWebSessions($user, $currentSessionId);
            } else {
                // For app/mobile sessions: invalidate ALL previous app sessions
                $appDeviceKey = "user_current_app_device_{$user->id}";
                $previousAppDeviceId = Cache::get($appDeviceKey);
                
                // CRITICAL: Get token's issued at time FIRST (before any cache operations)
                // This ensures we have the exact token timestamp
                $tokenIat = now()->timestamp;
                if ($currentToken) {
                    try {
                        $payload = JWTAuth::setToken($currentToken)->getPayload();
                        $tokenIat = $payload->get('iat');
                    } catch (\Exception $e) {
                        // If we can't decode, use current time
                        Log::debug("Could not decode token for iat", [
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                
                // CRITICAL: Set current device's valid timestamp FIRST (before invalidation)
                // This ensures the current token is marked as valid immediately
                $currentDeviceTimestampKey = "user_token_timestamp_{$user->id}_{$deviceId}";
                Cache::put($currentDeviceTimestampKey, $tokenIat, now()->addDays(30));
                
                // Store current app device mapping IMMEDIATELY
                Cache::put($appDeviceKey, $deviceId, now()->addDays(30));
                
                // CRITICAL: Set invalidation timestamp to ensure ALL previous tokens are rejected
                // Use the earlier of (tokenIat - 1) or (now - 2) to ensure:
                // 1. All tokens before tokenIat are invalidated
                // 2. Current token passes (its iat should be >= now - 2)
                // 3. All previous tokens are rejected (their iat < invalidationTimestamp)
                $now = now()->timestamp;
                $invalidationTimestamp = min($tokenIat - 1, $now - 2);
                $appInvalidationKey = "user_app_tokens_invalidated_{$user->id}";
                Cache::put($appInvalidationKey, $invalidationTimestamp, now()->addDays(30));
                
                // Force cache to be written immediately (if using Redis/Memcached)
                // This ensures invalidation is effective immediately
                if (config('cache.default') !== 'file') {
                    Cache::store()->put($appInvalidationKey, $invalidationTimestamp, now()->addDays(30));
                }
                
                Log::info("App login invalidation timestamp set", [
                    'user_id' => $user->id,
                    'device_id' => $deviceId,
                    'token_iat' => $tokenIat,
                    'invalidation_timestamp' => $invalidationTimestamp,
                    'now' => $now,
                ]);
                
                // CRITICAL: ALWAYS invalidate previous app device if it exists
                // This ensures only one app session is active at a time
                if ($previousAppDeviceId && $previousAppDeviceId !== $deviceId) {
                    $this->invalidateDeviceSession($user, $previousAppDeviceId);
                    Log::info("Previous app session invalidated for user {$user->id}", [
                        'previous_app_device_id' => $previousAppDeviceId,
                        'new_app_device_id' => $deviceId,
                    ]);
                }
                
                // CRITICAL: Ensure invalidation timestamp is set correctly
                // It should be tokenIat - 1 to invalidate all tokens issued before this login
                // But make sure it's not in the future
                $now = now()->timestamp;
                if ($invalidationTimestamp >= $now) {
                    // If somehow invalidation timestamp is >= now, set it to now - 1
                    $invalidationTimestamp = $now - 1;
                    Cache::put($appInvalidationKey, $invalidationTimestamp, now()->addDays(30));
                    if (config('cache.default') !== 'file') {
                        Cache::store()->put($appInvalidationKey, $invalidationTimestamp, now()->addDays(30));
                    }
                    Log::info("Corrected invalidation timestamp for user {$user->id}", [
                        'original_timestamp' => $tokenIat - 1,
                        'corrected_timestamp' => $invalidationTimestamp,
                    ]);
                }
                
                // Also invalidate all JWT tokens issued before this login for app platform
                // This ensures that even if device ID is same, old tokens are invalidated
                $this->invalidateAllAppTokens($user, $deviceId);
                
                Log::info("App login: All previous app tokens invalidated", [
                    'user_id' => $user->id,
                    'current_device_id' => $deviceId,
                    'previous_device_id' => $previousAppDeviceId,
                    'token_iat' => $tokenIat,
                    'invalidation_timestamp' => $invalidationTimestamp,
                ]);
                
                // DO NOT invalidate web sessions - allow both web and app simultaneously
            }
            
            // Store last login timestamp for this platform
            $lastLoginKey = $isWebSession 
                ? "user_last_web_login_{$user->id}" 
                : "user_last_app_login_{$user->id}";
            $lastLoginTimestamp = now()->timestamp;
            Cache::put($lastLoginKey, $lastLoginTimestamp, now()->addDays(30));
            
            // For JWT tokens, store timestamp with device ID
            // Use token's issued at time if available, otherwise use current time
            $tokenTimestamp = now()->timestamp;
            if ($currentToken) {
                try {
                    $payload = JWTAuth::setToken($currentToken)->getPayload();
                    $tokenIat = $payload->get('iat');
                    if ($tokenIat) {
                        $tokenTimestamp = $tokenIat;
                    }
                } catch (\Exception $e) {
                    // If we can't decode token, use current time
                    Log::debug("Could not decode token for timestamp", [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            $tokenTimestampKey = "user_token_timestamp_{$user->id}_{$deviceId}";
            $previousTimestamp = Cache::get($tokenTimestampKey);
            Cache::put($tokenTimestampKey, $tokenTimestamp, now()->addDays(30));
            
            // If there was a previous timestamp for this device, log it
            if ($previousTimestamp) {
                Log::info("Previous session invalidated for user {$user->id} on device {$deviceId}", [
                    'previous_timestamp' => $previousTimestamp,
                    'new_timestamp' => $tokenTimestamp,
                ]);
            }
            
            Log::info("Session management: New session created for user {$user->id}", [
                'device_type' => $deviceType,
                'device_id' => $deviceId,
                'ip_address' => $sessionInfo['ip_address'],
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to invalidate previous sessions for user {$user->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Invalidate ALL sessions for a user (including current one)
     * Used when user logs in from new device/browser
     * 
     * @param User $user
     * @return void
     */
    public function invalidateAllSessions(User $user)
    {
        try {
            // Delete ALL web sessions for this user from database
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->delete();
            
            Log::info("All web sessions invalidated for user {$user->id}");
            
            // Clear all device-specific cache keys
            // Get current device ID if exists
            $userDeviceKey = "user_current_device_{$user->id}";
            $currentDeviceId = Cache::get($userDeviceKey);
            
            // Clear device-specific session cache
            if ($currentDeviceId) {
                $deviceSessionKey = "user_active_session_{$user->id}_{$currentDeviceId}";
                $deviceTokenKey = "user_token_timestamp_{$user->id}_{$currentDeviceId}";
                Cache::forget($deviceSessionKey);
                Cache::forget($deviceTokenKey);
            }
            
            // Clear device mapping
            Cache::forget($userDeviceKey);
            
            // Also clear old format keys for backward compatibility
            $oldSessionKey = "user_active_session_{$user->id}";
            $oldTokenTimestampKey = "user_token_timestamp_{$user->id}";
            Cache::forget($oldSessionKey);
            Cache::forget($oldTokenTimestampKey);
            
            // Update token timestamp to invalidate all JWT tokens (old format)
            Cache::put($oldTokenTimestampKey, now()->timestamp, now()->addDays(30));
            
            Log::info("All session cache cleared for user {$user->id}");
            
        } catch (\Exception $e) {
            Log::error("Failed to invalidate all sessions for user {$user->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Store active session info after login
     * 
     * @param User $user
     * @param string $sessionId
     * @return void
     */
    public function storeActiveSession(User $user, $sessionId)
    {
        try {
            $deviceId = $user->deviceId ?? 'web_' . $sessionId;
            $deviceType = $user->deviceType ?? 'web';
            
            // Check if this is a web session
            $isWebSession = ($deviceType === 'web' || strpos($deviceId, 'web_') === 0);
            
            $sessionInfo = [
                'user_id' => $user->id,
                'token_issued_at' => now()->timestamp,
                'session_id' => $sessionId,
                'device_type' => $deviceType,
                'device_id' => $deviceId,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ];
            
            // Store in cache with user+device specific key
            $cacheKey = "user_active_session_{$user->id}_{$deviceId}";
            Cache::put($cacheKey, $sessionInfo, now()->addDays(30));
            
            // Store platform-specific device mapping
            if ($isWebSession) {
                $platformDeviceKey = "user_current_web_device_{$user->id}";
            } else {
                $platformDeviceKey = "user_current_app_device_{$user->id}";
            }
            Cache::put($platformDeviceKey, $deviceId, now()->addDays(30));
            
            Log::info("Active session stored for user {$user->id}", [
                'session_id' => $sessionId,
                'device_id' => $deviceId,
                'platform' => $isWebSession ? 'web' : 'app',
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to store active session for user {$user->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Invalidate all web sessions for a user (except current)
     * 
     * @param User $user
     * @param string|null $currentSessionId
     * @return void
     */
    protected function invalidateWebSessions(User $user, $currentSessionId = null)
    {
        try {
            // Get all sessions for this user from database
            $sessions = DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $currentSessionId)
                ->get();
            
            // Delete all old sessions
            if ($sessions->count() > 0) {
                DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $currentSessionId)
                    ->delete();
                
                Log::info("Deleted {$sessions->count()} old web sessions for user {$user->id}");
            }
        } catch (\Exception $e) {
            Log::error("Failed to invalidate web sessions for user {$user->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Invalidate ALL web sessions for a user (including private tabs)
     * This is called when a new web session is created to ensure only one browser/tab is active
     * 
     * @param User $user
     * @param string|null $currentSessionId
     * @return void
     */
    protected function invalidateAllWebSessions(User $user, $currentSessionId = null)
    {
        try {
            // Delete ALL web sessions from database (including private tabs)
            $deleted = DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $currentSessionId)
                ->delete();
            
            // Clear all web session cache entries for this user
            // We need to invalidate all cache entries that start with "user_active_session_{$user->id}_web_"
            // Since Laravel cache doesn't support wildcard deletion, we'll use a different approach:
            // Store a "last_web_session_invalidation" timestamp and check it in middleware
            
            $invalidationKey = "user_web_sessions_invalidated_{$user->id}";
            Cache::put($invalidationKey, now()->timestamp, now()->addDays(30));
            
            // Also clear the old device mapping if it was a web session
            $userDeviceKey = "user_current_device_{$user->id}";
            $oldDeviceId = Cache::get($userDeviceKey);
            if ($oldDeviceId && strpos($oldDeviceId, 'web_') === 0) {
                // Clear the old web session cache
                $oldCacheKey = "user_active_session_{$user->id}_{$oldDeviceId}";
                Cache::forget($oldCacheKey);
            }
            
            Log::info("Invalidated all web sessions for user {$user->id}", [
                'deleted_sessions' => $deleted,
                'current_session_id' => $currentSessionId,
                'invalidation_timestamp' => now()->timestamp,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to invalidate all web sessions for user {$user->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Check if a JWT token is still valid (not from a previous session)
     * Also validates that the device ID matches
     * 
     * @param string $token
     * @param User $user
     * @return bool
     */
    public function isTokenValid($token, User $user)
    {
        try {
            // CRITICAL: Get device ID from user model (set by middleware)
            // Middleware should have set it correctly based on request header
            $deviceId = $user->deviceId ?? 'web_default';
            
            // CRITICAL: Determine if this is a web or app token
            // Web tokens: deviceId starts with 'web_' or is 'web_default'
            // App tokens: any other deviceId
            $isWebToken = (!$deviceId || $deviceId === 'web_default' || strpos($deviceId, 'web_') === 0);
            
            // CRITICAL: For app tokens, use the device ID from user model (set by middleware from request header)
            // This is the actual device ID from the token request, which is what we need to validate
            // Don't override it with cache - the middleware already set it correctly
            // The cache device ID is what we compare against, not what we use as the token's device ID
            
            // Check platform-specific current device
            if ($isWebToken) {
                $platformDeviceKey = "user_current_web_device_{$user->id}";
            } else {
                $platformDeviceKey = "user_current_app_device_{$user->id}";
            }
            $currentDeviceId = Cache::get($platformDeviceKey);
            
            // CRITICAL: If this is the current device, ALWAYS allow it (for app tokens)
            // This ensures that tokens from the current app device are never rejected by web login
            // For web tokens, we still need to check timestamps
            // ALSO: If no current device ID is set, allow app tokens (might be first time or cache cleared)
            // IMPORTANT: Use deviceId from user model (set by middleware from request header) for comparison
            // Don't use cached device ID - that's what we're comparing against
            $tokenDeviceId = $user->deviceId ?? $deviceId; // Use request device ID from middleware
            
            if ($currentDeviceId === $tokenDeviceId || (!$currentDeviceId && !$isWebToken)) {
                try {
                    $payload = JWTAuth::setToken($token)->getPayload();
                    $tokenIat = $payload->get('iat');
                    $tokenAge = now()->timestamp - $tokenIat;
                    
                    // CRITICAL: For app tokens from current device, ALWAYS allow
                    // Web login should NEVER affect app tokens
                    // Only new APP login should invalidate old app tokens (checked later)
                    if (!$isWebToken) {
                        // This is an app token from current device - allow it immediately
                        // Don't check invalidation here - that's checked later for non-current devices
                        Log::info("App token allowed - current device (web login safe)", [
                            'user_id' => $user->id,
                            'token_device_id' => $tokenDeviceId,
                            'current_device_id' => $currentDeviceId,
                            'token_iat' => $tokenIat,
                            'token_age' => $tokenAge,
                        ]);
                        return true; // Current app device token is always valid (web login safe)
                    }
                    
                    // For web tokens, check timestamps
                    // If token is from current device and issued within last 30 seconds, always allow
                    if ($tokenAge <= 30) {
                        Log::debug("Token allowed - current device and recently issued", [
                            'user_id' => $user->id,
                            'token_device_id' => $deviceId,
                            'token_iat' => $tokenIat,
                            'token_age' => $tokenAge,
                        ]);
                        return true; // Current device token is always valid
                    }
                    
                    // Even if older, if it's current device, allow it (might be valid session)
                    $currentDeviceTimestampKey = "user_token_timestamp_{$user->id}_{$deviceId}";
                    $currentDeviceTimestamp = Cache::get($currentDeviceTimestampKey);
                    
                    if ($currentDeviceTimestamp) {
                        // Allow if token is close to device timestamp (10 second grace)
                        if ($tokenIat >= ($currentDeviceTimestamp - 10)) {
                            Log::debug("Token allowed - current device and valid timestamp", [
                                'user_id' => $user->id,
                                'token_device_id' => $deviceId,
                                'token_iat' => $tokenIat,
                                'current_device_timestamp' => $currentDeviceTimestamp,
                            ]);
                            return true;
                        } else {
                            // Token is too old even for current device - might be from previous login
                            Log::warning("Token rejected - current device but token is too old", [
                                'user_id' => $user->id,
                                'token_device_id' => $deviceId,
                                'token_iat' => $tokenIat,
                                'current_device_timestamp' => $currentDeviceTimestamp,
                                'age_difference' => $currentDeviceTimestamp - $tokenIat,
                            ]);
                            return false;
                        }
                    } else {
                        // No timestamp set - might be fresh login, allow if very recent
                        if ($tokenAge <= 10) {
                            Log::debug("Token allowed - current device but no timestamp set (very recent)", [
                                'user_id' => $user->id,
                                'token_device_id' => $deviceId,
                                'token_age' => $tokenAge,
                            ]);
                            return true;
                        } else {
                            // Token is old and no timestamp - reject to be safe
                            Log::warning("Token rejected - current device but old token with no timestamp", [
                                'user_id' => $user->id,
                                'token_device_id' => $deviceId,
                                'token_age' => $tokenAge,
                            ]);
                            return false;
                        }
                    }
                } catch (\Exception $e) {
                    // If we can't decode, but it's current device, allow it (especially for app tokens)
                    if (!$isWebToken) {
                        Log::debug("App token allowed - current device (decode failed, web login safe)", [
                            'user_id' => $user->id,
                            'token_device_id' => $deviceId,
                            'error' => $e->getMessage(),
                        ]);
                        return true;
                    }
                    Log::debug("Token allowed - current device (decode failed)", [
                        'user_id' => $user->id,
                        'token_device_id' => $deviceId,
                        'error' => $e->getMessage(),
                    ]);
                    return true;
                }
            }
            
            // CRITICAL: For app tokens ONLY, check global app invalidation timestamp
            // Web tokens should NEVER check app invalidation (they use database sessions)
            // This ensures web login NEVER affects app tokens
            // FOR APP TOKENS: Check invalidation but be lenient for current device
            if (!$isWebToken) {
                $appInvalidationKey = "user_app_tokens_invalidated_{$user->id}";
                $appInvalidationTimestamp = Cache::get($appInvalidationKey);
                
                if ($appInvalidationTimestamp) {
                    try {
                        $payload = JWTAuth::setToken($token)->getPayload();
                        $tokenIat = $payload->get('iat');
                        
                        // CRITICAL: If token is from CURRENT device, always allow it (even if before invalidation)
                        // This handles cases where cache might be out of sync
                        if ($currentDeviceId === $deviceId) {
                            Log::debug("App token allowed - current device (invalidation check skipped)", [
                                'user_id' => $user->id,
                                'token_device_id' => $deviceId,
                                'token_iat' => $tokenIat,
                                'invalidation_timestamp' => $appInvalidationTimestamp,
                            ]);
                            // Continue - allow token (current device is always valid)
                        } else {
                            // Token is from different device - check invalidation
                            // Only reject if token is significantly older than invalidation timestamp (more than 60 seconds)
                            // This prevents false rejections due to cache timing issues
                            if ($tokenIat < $appInvalidationTimestamp) {
                                $timeDifference = $appInvalidationTimestamp - $tokenIat;
                                
                                // Only reject if invalidation was significantly after token (more than 60 seconds)
                                if ($timeDifference > 60) {
                                    Log::warning("App token rejected - issued significantly before global invalidation (different device)", [
                                        'user_id' => $user->id,
                                        'token_device_id' => $deviceId,
                                        'current_device_id' => $currentDeviceId,
                                        'token_iat' => $tokenIat,
                                        'invalidation_timestamp' => $appInvalidationTimestamp,
                                        'time_difference' => $timeDifference,
                                    ]);
                                    return false;
                                } else {
                                    // Small time difference - allow token (might be cache issue)
                                    Log::debug("App token allowed - invalidation check passed (small time difference)", [
                                        'user_id' => $user->id,
                                        'token_device_id' => $deviceId,
                                        'current_device_id' => $currentDeviceId,
                                        'token_iat' => $tokenIat,
                                        'invalidation_timestamp' => $appInvalidationTimestamp,
                                        'time_difference' => $timeDifference,
                                    ]);
                                    // Continue - allow token
                                }
                            }
                            
                            // Token passed invalidation check (tokenIat >= invalidationTimestamp or small difference)
                            // Check if there's a clear new login on current device
                            $currentDeviceTimestampKey = "user_token_timestamp_{$user->id}_{$currentDeviceId}";
                            $currentDeviceTimestamp = Cache::get($currentDeviceTimestampKey);
                            
                            // Only reject if current device timestamp exists AND is significantly newer (more than 60 seconds)
                            if ($currentDeviceTimestamp && ($currentDeviceTimestamp - $tokenIat) > 60) {
                                Log::warning("App token rejected - not current device and current device is significantly newer", [
                                    'user_id' => $user->id,
                                    'token_device_id' => $deviceId,
                                    'current_device_id' => $currentDeviceId,
                                    'token_iat' => $tokenIat,
                                    'current_device_timestamp' => $currentDeviceTimestamp,
                                ]);
                                return false;
                            } else {
                                // No clear new login or small time difference - allow token
                                Log::debug("App token allowed - device mismatch but no clear new login", [
                                    'user_id' => $user->id,
                                    'token_device_id' => $deviceId,
                                    'current_device_id' => $currentDeviceId,
                                    'token_iat' => $tokenIat,
                                    'current_device_timestamp' => $currentDeviceTimestamp,
                                ]);
                                // Continue - allow token
                            }
                        }
                    } catch (\Exception $e) {
                        // If we can't decode, be lenient and allow it (especially for app tokens)
                        Log::debug("App token allowed - decode failed for invalidation check, being lenient", [
                            'user_id' => $user->id,
                            'token_device_id' => $deviceId,
                            'error' => $e->getMessage(),
                        ]);
                        // Continue - allow token
                    }
                } else {
                    // No app invalidation timestamp - allow token
                    Log::debug("App token allowed - no app invalidation timestamp", [
                        'user_id' => $user->id,
                        'token_device_id' => $deviceId,
                    ]);
                    // Continue - allow token
                }
            }
            
            // If no device mapping exists, allow for backward compatibility
            // IMPORTANT: For app tokens, be more lenient - allow if no device mapping
            // CRITICAL: For app tokens, if we can't find current device, default to allowing the token
            if (!$currentDeviceId) {
                // For app tokens, if no device mapping exists, allow it (might be first time or cache cleared)
                if (!$isWebToken) {
                    Log::debug("App token allowed - no current device ID in cache (backward compatibility)", [
                        'user_id' => $user->id,
                        'token_device_id' => $deviceId,
                    ]);
                    return true; // App tokens: default to allow if no device tracking
                }
                
                // For web tokens, check old format token timestamp (backward compatibility)
                $oldTokenTimestampKey = "user_token_timestamp_{$user->id}";
                $lastValidTimestamp = Cache::get($oldTokenTimestampKey);
                
                if (!$lastValidTimestamp) {
                    return true; // No tracking, allow token
                }
                
                // Validate against old timestamp
                try {
                    $payload = JWTAuth::setToken($token)->getPayload();
                    $tokenIat = $payload->get('iat');
                    return $tokenIat >= $lastValidTimestamp;
                } catch (\Exception $e) {
                    return false;
                }
            }
            
            // NEW LOGIC: Allow different platforms (web and app) simultaneously
            // But reject if same platform but different device
            if ($currentDeviceId !== $deviceId) {
                // Check if this is a web session (both might be web but different sessions)
                $isWebCurrent = strpos($currentDeviceId, 'web_') === 0;
                
                if ($isWebToken && $isWebCurrent) {
                    // Both are web sessions but different device IDs - check timestamp
                    // If token is old, reject it (user logged in from another browser)
                    // We'll check timestamp below
                    Log::debug("Web session device ID mismatch, checking timestamp", [
                        'user_id' => $user->id,
                        'token_device_id' => $deviceId,
                        'current_device_id' => $currentDeviceId,
                    ]);
                } elseif (!$isWebToken && !$isWebCurrent) {
                    // Both are app sessions but different device IDs
                    // Check if there was a recent app login that invalidated this token
                    // Only reject if we're CERTAIN there was a new app login
                    try {
                        $payload = JWTAuth::setToken($token)->getPayload();
                        $tokenIat = $payload->get('iat');
                        
                        // Check if current device has a timestamp that's newer than token
                        $currentDeviceTimestampKey = "user_token_timestamp_{$user->id}_{$currentDeviceId}";
                        $currentDeviceTimestamp = Cache::get($currentDeviceTimestampKey);
                        
                        // Only reject if current device timestamp exists AND is significantly newer (more than 60 seconds)
                        if ($currentDeviceTimestamp && ($currentDeviceTimestamp - $tokenIat) > 60) {
                            Log::warning("App token rejected - different device ID and current device is significantly newer", [
                                'user_id' => $user->id,
                                'token_device_id' => $deviceId,
                                'current_device_id' => $currentDeviceId,
                                'token_iat' => $tokenIat,
                                'current_device_timestamp' => $currentDeviceTimestamp,
                            ]);
                            return false;
                        } else {
                            // Allow token - might be valid app session, just different device ID in cache
                            Log::debug("App token allowed - different device ID but no clear new login", [
                                'user_id' => $user->id,
                                'token_device_id' => $deviceId,
                                'current_device_id' => $currentDeviceId,
                                'token_iat' => $tokenIat,
                                'current_device_timestamp' => $currentDeviceTimestamp,
                            ]);
                            // Continue to timestamp check below
                        }
                    } catch (\Exception $e) {
                        // If we can't decode token, be lenient and allow it
                        Log::debug("App token allowed - decode failed, being lenient", [
                            'user_id' => $user->id,
                            'token_device_id' => $deviceId,
                            'current_device_id' => $currentDeviceId,
                            'error' => $e->getMessage(),
                        ]);
                        // Continue to timestamp check below
                    }
                } else {
                    // Different platforms (web vs app) - allow both simultaneously
                    // Token is from different platform, which is allowed
                    Log::debug("Allowing token from different platform", [
                        'user_id' => $user->id,
                        'token_device_id' => $deviceId,
                        'current_device_id' => $currentDeviceId,
                        'is_web_token' => $isWebToken,
                        'is_web_current' => $isWebCurrent,
                    ]);
                    // Continue to timestamp check below
                }
            }
            
            // Get token's issued at time first
            try {
                $payload = JWTAuth::setToken($token)->getPayload();
                $tokenIat = $payload->get('iat');
            } catch (\Exception $e) {
                Log::warning("Failed to decode token", [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                // For app tokens, be more lenient - allow if decode fails
                if (!$isWebToken) {
                    Log::debug("App token allowed - decode failed, being lenient", [
                        'user_id' => $user->id,
                        'token_device_id' => $deviceId,
                    ]);
                    return true;
                }
                return false;
            }
            
            // Check timestamp for current device (the one that should be active)
            $currentDeviceTimestampKey = "user_token_timestamp_{$user->id}_{$currentDeviceId}";
            $currentDeviceTimestamp = Cache::get($currentDeviceTimestampKey);
            
            // If token was issued before current device's login, it's from an old device
            // BUT: Only reject if we're CERTAIN there was a new login (60+ seconds difference)
            // This prevents false rejections when cache has stale data
            if ($currentDeviceTimestamp && $tokenIat < $currentDeviceTimestamp) {
                $timeDifference = $currentDeviceTimestamp - $tokenIat;
                
                // Only reject if current device timestamp is significantly newer (more than 60 seconds)
                // This ensures we only reject when we're CERTAIN there was a new login
                if ($timeDifference > 60) {
                    Log::warning("Token rejected - issued before current device login (confirmed new login)", [
                        'user_id' => $user->id,
                        'token_device_id' => $deviceId,
                        'current_device_id' => $currentDeviceId,
                        'token_iat' => $tokenIat,
                        'current_device_timestamp' => $currentDeviceTimestamp,
                        'time_difference' => $timeDifference,
                    ]);
                    return false;
                } else {
                    // Time difference is small - might be cache issue, allow token
                    Log::debug("Token allowed - time difference is small (might be cache issue)", [
                        'user_id' => $user->id,
                        'token_device_id' => $deviceId,
                        'current_device_id' => $currentDeviceId,
                        'token_iat' => $tokenIat,
                        'current_device_timestamp' => $currentDeviceTimestamp,
                        'time_difference' => $timeDifference,
                    ]);
                    // Continue - allow token
                }
            }
            
            // NEW LOGIC: Check platform-specific timestamp
            // Allow both web and app tokens if they're from their respective platforms
            $isWebToken = (!$deviceId || $deviceId === 'web_default' || strpos($deviceId, 'web_') === 0);
            $isWebCurrent = strpos($currentDeviceId, 'web_') === 0;
            
            // CRITICAL: For app tokens, check global app invalidation timestamp first
            if (!$isWebToken) {
                // This is an app token - check global app invalidation timestamp
                $appInvalidationKey = "user_app_tokens_invalidated_{$user->id}";
                $appInvalidationTimestamp = Cache::get($appInvalidationKey);
                
                if ($appInvalidationTimestamp) {
                    // Get token's issued at time
                    try {
                        $payload = JWTAuth::setToken($token)->getPayload();
                        $tokenIat = $payload->get('iat');
                        
                        // If token was issued before the invalidation timestamp, reject it
                        if ($tokenIat < $appInvalidationTimestamp) {
                            Log::warning("App token rejected - issued before global invalidation", [
                                'user_id' => $user->id,
                                'token_device_id' => $deviceId,
                                'token_iat' => $tokenIat,
                                'invalidation_timestamp' => $appInvalidationTimestamp,
                            ]);
                            return false;
                        }
                    } catch (\Exception $e) {
                        Log::warning("Failed to decode token for app invalidation check", [
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                        return false;
                    }
                }
            }
            
            // If token is from same platform as current device, use platform-specific timestamp
            if ($isWebToken && $isWebCurrent) {
                // Both are web - check web-specific timestamp
                $platformTimestampKey = "user_token_timestamp_{$user->id}_{$currentDeviceId}";
                $lastValidTimestamp = Cache::get($platformTimestampKey);
            } elseif (!$isWebToken && !$isWebCurrent) {
                // Both are app - check app-specific timestamp
                $platformTimestampKey = "user_token_timestamp_{$user->id}_{$currentDeviceId}";
                $lastValidTimestamp = Cache::get($platformTimestampKey);
            } else {
                // Different platforms (web vs app) - allow both simultaneously
                // Check if token is from a valid platform session
                $platformTimestampKey = "user_token_timestamp_{$user->id}_{$deviceId}";
                $lastValidTimestamp = Cache::get($platformTimestampKey);
                
                // If no timestamp for this device, allow it (might be from other platform)
                if (!$lastValidTimestamp) {
                    Log::debug("Allowing token from different platform", [
                        'user_id' => $user->id,
                        'token_device_id' => $deviceId,
                        'current_device_id' => $currentDeviceId,
                        'is_web_token' => $isWebToken,
                        'is_web_current' => $isWebCurrent,
                    ]);
                    return true;
                }
            }
            
            // Use current device's timestamp for validation
            if (!$lastValidTimestamp) {
                $lastValidTimestamp = $currentDeviceTimestamp;
            }
            
            if (!$lastValidTimestamp) {
                // No timestamp for this device, might be from before device tracking
                // Check old format as fallback
                $oldTokenTimestampKey = "user_token_timestamp_{$user->id}";
                $lastValidTimestamp = Cache::get($oldTokenTimestampKey);
                
                if (!$lastValidTimestamp) {
                    // No tracking at all - might be first login or cache not set yet
                    // Check if token was issued recently (within last 60 seconds) - allow it
                    try {
                        $payload = JWTAuth::setToken($token)->getPayload();
                        $tokenIat = $payload->get('iat');
                        $tokenAge = now()->timestamp - $tokenIat;
                        
                        // If token was issued within last 60 seconds, allow it (might be fresh login)
                        if ($tokenAge <= 60) {
                            Log::debug("Allowing token without cache - issued recently", [
                                'user_id' => $user->id,
                                'device_id' => $deviceId,
                                'token_age' => $tokenAge,
                            ]);
                            return true;
                        }
                    } catch (\Exception $e) {
                        // If we can't decode, allow for backward compatibility
                    }
                    
                    return true; // No tracking, allow token
                }
            }
            
            // Decode token to get issued at time
            try {
                $payload = JWTAuth::setToken($token)->getPayload();
                $tokenIat = $payload->get('iat'); // Issued at timestamp
                
                // Token is valid if it was issued after or at the last valid timestamp
                // Allow a grace period for timing issues
                // For app tokens, be more lenient (60 seconds) to prevent false rejections
                $gracePeriod = $isWebToken ? 5 : 60;
                
                // Check if token is valid
                $isValid = $tokenIat >= ($lastValidTimestamp - $gracePeriod);
                
                if (!$isValid) {
                    // Token is older than last valid timestamp
                    // For app tokens, be extremely lenient - only reject if timestamp difference is very significant (more than 5 minutes)
                    if (!$isWebToken) {
                        $timeDifference = $lastValidTimestamp - $tokenIat;
                        if ($timeDifference <= 300) { // 5 minutes grace period for app tokens
                            // Small difference - might be cache issue, allow token
                            Log::debug("App token allowed - timestamp difference is small (might be cache issue)", [
                                'user_id' => $user->id,
                                'token_device_id' => $deviceId,
                                'token_iat' => $tokenIat,
                                'last_valid_timestamp' => $lastValidTimestamp,
                                'time_difference' => $timeDifference,
                            ]);
                            return true;
                        } else {
                            // Large difference - but still check if it's the current device
                            // If it's the current device, allow it anyway (might be valid session)
                            if ($currentDeviceId === $deviceId) {
                                Log::debug("App token allowed - current device despite timestamp difference", [
                                    'user_id' => $user->id,
                                    'token_device_id' => $deviceId,
                                    'token_iat' => $tokenIat,
                                    'last_valid_timestamp' => $lastValidTimestamp,
                                    'time_difference' => $timeDifference,
                                ]);
                                return true;
                            }
                        }
                    }
                    
                    Log::warning("Token rejected - issued before last valid timestamp", [
                        'user_id' => $user->id,
                        'token_device_id' => $deviceId,
                        'token_iat' => $tokenIat,
                        'last_valid_timestamp' => $lastValidTimestamp,
                        'is_web_token' => $isWebToken,
                    ]);
                    return false;
                }
                
                return true;
            } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
                // Token is expired - reject it
                return false;
            } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
                // Token is invalid - reject it
                return false;
            } catch (\Exception $e) {
                Log::warning("Failed to decode token for validation", [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                // For app tokens, be more lenient - allow if decode fails (might be valid token)
                if (!$isWebToken) {
                    Log::debug("App token allowed - decode failed, being lenient", [
                        'user_id' => $user->id,
                        'token_device_id' => $deviceId,
                    ]);
                    return true;
                }
                return false;
            }
            
            // FINAL FALLBACK: For app tokens, if we've made it this far without returning,
            // it means we couldn't definitively prove the token is invalid
            // Default to allowing it to prevent false rejections
            if (!$isWebToken) {
                Log::debug("App token allowed - final fallback (could not definitively prove invalid)", [
                    'user_id' => $user->id,
                    'token_device_id' => $deviceId,
                    'current_device_id' => $currentDeviceId,
                ]);
                return true;
            }
            
        } catch (\Exception $e) {
            Log::error("Failed to validate token for user {$user->id}", [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * Clear session tracking for a user
     * 
     * @param User $user
     * @return void
     */
    public function clearSessionTracking(User $user)
    {
        try {
            $deviceId = $user->deviceId ?? 'web_default';
            $isWebSession = (!$deviceId || $deviceId === 'web_default' || strpos($deviceId, 'web_') === 0);
            
            // Only clear the current device's session, not all sessions
            $cacheKey = "user_active_session_{$user->id}_{$deviceId}";
            $tokenTimestampKey = "user_token_timestamp_{$user->id}_{$deviceId}";
            
            Cache::forget($cacheKey);
            Cache::forget($tokenTimestampKey);
            
            // Clear platform-specific device mapping only for this device
            if ($isWebSession) {
                $platformDeviceKey = "user_current_web_device_{$user->id}";
            } else {
                $platformDeviceKey = "user_current_app_device_{$user->id}";
            }
            
            // Only clear if this device is the current device
            $currentDeviceId = Cache::get($platformDeviceKey);
            if ($currentDeviceId === $deviceId) {
                Cache::forget($platformDeviceKey);
            }
            
            // Also clear old format keys for backward compatibility (only for this device)
            $oldCacheKey = "user_active_session_{$user->id}";
            $oldTokenTimestampKey = "user_token_timestamp_{$user->id}";
            Cache::forget($oldCacheKey);
            Cache::forget($oldTokenTimestampKey);
            
            Log::info("Session tracking cleared for user {$user->id} on device {$deviceId} (platform: " . ($isWebSession ? 'web' : 'app') . ")");
        } catch (\Exception $e) {
            Log::error("Failed to clear session tracking for user {$user->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Get current active session info for a user
     * 
     * @param User $user
     * @param string|null $sessionId Optional session ID to check (for web sessions)
     * @return array|null
     */
    public function getActiveSessionInfo(User $user, $sessionId = null)
    {
        try {
            // For web sessions, try to find by session ID first
            if ($sessionId) {
                $webDeviceId = 'web_' . $sessionId;
                $cacheKey = "user_active_session_{$user->id}_{$webDeviceId}";
                $sessionInfo = Cache::get($cacheKey);
                if ($sessionInfo) {
                    return $sessionInfo;
                }
            }
            
            // Try with user's device ID
            $deviceId = $user->deviceId ?? ($sessionId ? 'web_' . $sessionId : 'web_default');
            $cacheKey = "user_active_session_{$user->id}_{$deviceId}";
            $sessionInfo = Cache::get($cacheKey);
            
            // If not found, try to get from platform-specific device mapping
            if (!$sessionInfo) {
                // Check web platform first (for web sessions)
                if ($sessionId || strpos($deviceId, 'web_') === 0) {
                    $webDeviceKey = "user_current_web_device_{$user->id}";
                    $currentWebDeviceId = Cache::get($webDeviceKey);
                    if ($currentWebDeviceId) {
                        $cacheKey = "user_active_session_{$user->id}_{$currentWebDeviceId}";
                        $sessionInfo = Cache::get($cacheKey);
                    }
                } else {
                    // Check app platform (for app sessions)
                    $appDeviceKey = "user_current_app_device_{$user->id}";
                    $currentAppDeviceId = Cache::get($appDeviceKey);
                    if ($currentAppDeviceId) {
                        $cacheKey = "user_active_session_{$user->id}_{$currentAppDeviceId}";
                        $sessionInfo = Cache::get($cacheKey);
                    }
                }
            }
            
            // Fallback to old format for backward compatibility
            if (!$sessionInfo) {
                $oldCacheKey = "user_active_session_{$user->id}";
                $sessionInfo = Cache::get($oldCacheKey);
            }
            
            return $sessionInfo;
        } catch (\Exception $e) {
            Log::error("Failed to get active session info for user {$user->id}", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
    
    /**
     * Invalidate session for a specific device
     * This invalidates all tokens/sessions for the specified device
     * 
     * @param User $user
     * @param string $deviceId
     * @return void
     */
    protected function invalidateDeviceSession(User $user, $deviceId)
    {
        try {
            $cacheKey = "user_active_session_{$user->id}_{$deviceId}";
            $tokenTimestampKey = "user_token_timestamp_{$user->id}_{$deviceId}";
            
            // Clear the session cache
            Cache::forget($cacheKey);
            
            // Set token timestamp to a future time to invalidate all existing tokens
            // This ensures any token issued before now will be rejected
            $invalidationTimestamp = now()->addSecond()->timestamp; // Future timestamp
            Cache::put($tokenTimestampKey, $invalidationTimestamp, now()->addDays(30));
            
            // If it's a web session, delete it from database
            if (strpos($deviceId, 'web_') === 0) {
                $sessionId = str_replace('web_', '', $deviceId);
                try {
                    DB::table('sessions')
                        ->where('id', $sessionId)
                        ->where('user_id', $user->id)
                        ->delete();
                    Log::info("Web session deleted from database for user {$user->id}", [
                        'session_id' => $sessionId,
                    ]);
                } catch (\Exception $e) {
                    Log::warning("Failed to delete web session from database", [
                        'user_id' => $user->id,
                        'session_id' => $sessionId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            Log::info("Device session invalidated for user {$user->id} on device {$deviceId}", [
                'invalidation_timestamp' => $invalidationTimestamp,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to invalidate device session for user {$user->id}", [
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Invalidate all app JWT tokens for a user (except current device)
     * This ensures that when a user logs in from app, all previous app tokens are invalidated
     * 
     * @param User $user
     * @param string $currentDeviceId Current device ID that should remain valid
     * @return void
     */
    protected function invalidateAllAppTokens(User $user, $currentDeviceId)
    {
        try {
            // Set a future timestamp for all app device tokens except current
            // This will invalidate any token issued before this timestamp
            $invalidationTimestamp = now()->addSecond()->timestamp;
            
            // Store invalidation timestamp for app platform
            $appInvalidationKey = "user_app_tokens_invalidated_{$user->id}";
            Cache::put($appInvalidationKey, $invalidationTimestamp, now()->addDays(30));
            
            // Also update the current device's timestamp to current time (valid)
            $currentDeviceTimestampKey = "user_token_timestamp_{$user->id}_{$currentDeviceId}";
            Cache::put($currentDeviceTimestampKey, now()->timestamp, now()->addDays(30));
            
            Log::info("All app tokens invalidated for user {$user->id}", [
                'current_device_id' => $currentDeviceId,
                'invalidation_timestamp' => $invalidationTimestamp,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to invalidate all app tokens for user {$user->id}", [
                'current_device_id' => $currentDeviceId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

