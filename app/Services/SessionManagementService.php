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
                
                // CRITICAL: Set invalidation timestamp to be 1 second BEFORE current token's iat
                // This ensures ALL tokens issued before this login are rejected IMMEDIATELY
                // Current token (iat = tokenIat) will pass because it's > invalidationTimestamp
                // Previous tokens (iat < tokenIat) will be rejected because they're < invalidationTimestamp
                $invalidationTimestamp = $tokenIat - 1;
                $appInvalidationKey = "user_app_tokens_invalidated_{$user->id}";
                Cache::put($appInvalidationKey, $invalidationTimestamp, now()->addDays(30));
                
                // Force cache to be written immediately (if using Redis/Memcached)
                // This ensures invalidation is effective immediately
                if (config('cache.default') !== 'file') {
                    Cache::store()->put($appInvalidationKey, $invalidationTimestamp, now()->addDays(30));
                }
                
                // ALWAYS invalidate previous app device if it exists (even if same device)
                // This ensures only one app session is active at a time
                if ($previousAppDeviceId && $previousAppDeviceId !== $deviceId) {
                    $this->invalidateDeviceSession($user, $previousAppDeviceId);
                    Log::info("Previous app session invalidated for user {$user->id}", [
                        'previous_app_device_id' => $previousAppDeviceId,
                        'new_app_device_id' => $deviceId,
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
            
            // If deviceId is from database and looks like web, but we're checking app token,
            // try to get app device ID from cache instead
            $isWebToken = (!$deviceId || $deviceId === 'web_default' || strpos($deviceId, 'web_') === 0);
            
            // Check platform-specific current device FIRST
            if ($isWebToken) {
                $platformDeviceKey = "user_current_web_device_{$user->id}";
            } else {
                $platformDeviceKey = "user_current_app_device_{$user->id}";
            }
            $currentDeviceId = Cache::get($platformDeviceKey);
            
            // CRITICAL: If deviceId looks like web but we have an app device in cache,
            // use the app device ID from cache instead (web login might have overwritten database)
            // Also check if there's an app device in cache even if currentDeviceId is null
            $appDeviceKey = "user_current_app_device_{$user->id}";
            $cachedAppDeviceId = Cache::get($appDeviceKey);
            
            if ($isWebToken && $cachedAppDeviceId && strpos($cachedAppDeviceId, 'web_') !== 0) {
                // This is actually an app device, not web - use cached app device ID
                $deviceId = $cachedAppDeviceId;
                $isWebToken = false;
                $platformDeviceKey = $appDeviceKey;
                $currentDeviceId = $cachedAppDeviceId;
                Log::debug("Corrected device ID - using app device from cache (web login detected)", [
                    'user_id' => $user->id,
                    'original_device_id' => $user->deviceId,
                    'corrected_device_id' => $deviceId,
                    'cached_app_device_id' => $cachedAppDeviceId,
                ]);
            }
            
            // CRITICAL: If this is the current device, ALWAYS allow it (for app tokens)
            // This ensures that tokens from the current app device are never rejected by web login
            // For web tokens, we still need to check timestamps
            if ($currentDeviceId === $deviceId) {
                try {
                    $payload = JWTAuth::setToken($token)->getPayload();
                    $tokenIat = $payload->get('iat');
                    $tokenAge = now()->timestamp - $tokenIat;
                    
                    // For app tokens from current device, always allow (web login shouldn't affect them)
                    if (!$isWebToken) {
                        Log::debug("App token allowed - current device (web login safe)", [
                            'user_id' => $user->id,
                            'token_device_id' => $deviceId,
                            'token_iat' => $tokenIat,
                            'token_age' => $tokenAge,
                        ]);
                        return true; // Current app device token is always valid
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
            
            // CRITICAL: For app tokens, ALWAYS check global app invalidation timestamp
            // This ensures ALL previous app tokens are rejected when new APP login happens
            // BUT: Web login should NOT invalidate app tokens
            if (!$isWebToken) {
                $appInvalidationKey = "user_app_tokens_invalidated_{$user->id}";
                $appInvalidationTimestamp = Cache::get($appInvalidationKey);
                
                if ($appInvalidationTimestamp) {
                    try {
                        $payload = JWTAuth::setToken($token)->getPayload();
                        $tokenIat = $payload->get('iat');
                        
                        // CRITICAL: Only reject if token is older than invalidation timestamp
                        // AND it's not the current device (current device check already passed above)
                        // This ensures web login doesn't affect app tokens
                        if ($tokenIat <= $appInvalidationTimestamp) {
                            // If this is the current device, allow it (might be valid app session)
                            // Only reject if it's NOT the current device
                            if ($currentDeviceId !== $deviceId) {
                                Log::warning("App token rejected - issued before or at global invalidation (not current device)", [
                                    'user_id' => $user->id,
                                    'token_device_id' => $deviceId,
                                    'current_device_id' => $currentDeviceId,
                                    'token_iat' => $tokenIat,
                                    'invalidation_timestamp' => $appInvalidationTimestamp,
                                ]);
                                return false;
                            } else {
                                // Current device but token is old - might be from before new app login
                                // Check if there's a newer app login by checking device timestamp
                                $currentDeviceTimestampKey = "user_token_timestamp_{$user->id}_{$deviceId}";
                                $currentDeviceTimestamp = Cache::get($currentDeviceTimestampKey);
                                
                                if ($currentDeviceTimestamp && $tokenIat < $currentDeviceTimestamp) {
                                    // Token is older than device timestamp - reject it
                                    Log::warning("App token rejected - current device but token older than device timestamp", [
                                        'user_id' => $user->id,
                                        'token_device_id' => $deviceId,
                                        'token_iat' => $tokenIat,
                                        'device_timestamp' => $currentDeviceTimestamp,
                                    ]);
                                    return false;
                                }
                                // Otherwise allow it - it's current device and timestamp matches
                            }
                        }
                        
                        // Token passed invalidation check (tokenIat > invalidationTimestamp)
                        // But still need to verify it's from current device
                        if ($currentDeviceId !== $deviceId) {
                            // Not current device - reject it
                            Log::warning("App token rejected - not current device", [
                                'user_id' => $user->id,
                                'token_device_id' => $deviceId,
                                'current_device_id' => $currentDeviceId,
                                'token_iat' => $tokenIat,
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
            
            // If no device mapping exists, allow for backward compatibility
            if (!$currentDeviceId) {
                // Check old format token timestamp (backward compatibility)
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
                    // Both are app sessions but different device IDs - reject immediately
                    // This means user logged in from another app device
                    Log::warning("App token rejected - different device ID", [
                        'user_id' => $user->id,
                        'token_device_id' => $deviceId,
                        'current_device_id' => $currentDeviceId,
                    ]);
                    return false;
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
                return false;
            }
            
            // Check timestamp for current device (the one that should be active)
            $currentDeviceTimestampKey = "user_token_timestamp_{$user->id}_{$currentDeviceId}";
            $currentDeviceTimestamp = Cache::get($currentDeviceTimestampKey);
            
            // If token was issued before current device's login, it's from an old device
            // Reject it (unless it's the same device and timestamp matches)
            if ($currentDeviceTimestamp && $tokenIat < $currentDeviceTimestamp) {
                // Token is older than current device's login - check if it's from an invalidated device
                // Check all device timestamps to see if any were invalidated (future timestamp)
                // This is a simple check: if token is older than current device, and current device exists, reject
                Log::warning("Token rejected - issued before current device login", [
                    'user_id' => $user->id,
                    'token_device_id' => $deviceId,
                    'current_device_id' => $currentDeviceId,
                    'token_iat' => $tokenIat,
                    'current_device_timestamp' => $currentDeviceTimestamp,
                ]);
                return false;
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
                // Allow a 5 second grace period for timing issues
                $gracePeriod = 5;
                return $tokenIat >= ($lastValidTimestamp - $gracePeriod);
            } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
                return false;
            } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
                return false;
            } catch (\Exception $e) {
                Log::warning("Failed to decode token for validation", [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                return false;
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

