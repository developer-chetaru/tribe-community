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
     * Invalidate all previous sessions/tokens for a user
     * This ensures only one active session per device at a time
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
            
            // Also store a mapping of user_id -> current device_id for quick lookup
            $userDeviceKey = "user_current_device_{$user->id}";
            $previousDeviceId = Cache::get($userDeviceKey);
            
            // Check if this is a web session (browser/private tab)
            $isWebSession = ($deviceType === 'web' || strpos($deviceId, 'web_') === 0);
            
            // If user logged in from a different device, invalidate previous device's session
            if ($previousDeviceId && $previousDeviceId !== $deviceId) {
                $this->invalidateDeviceSession($user, $previousDeviceId);
                Log::info("Previous device session invalidated for user {$user->id}", [
                    'previous_device_id' => $previousDeviceId,
                    'new_device_id' => $deviceId,
                ]);
            }
            
            // Store last login timestamp FIRST - this will be used to invalidate old sessions
            $lastLoginKey = "user_last_login_{$user->id}";
            $lastLoginTimestamp = now()->timestamp;
            Cache::put($lastLoginKey, $lastLoginTimestamp, now()->addDays(30));
            
            // For web sessions, ALWAYS invalidate ALL previous web sessions (including private tabs)
            // This ensures only the latest browser/tab is active
            if ($isWebSession) {
                // Clear ALL previous web session cache entries by updating device mapping first
                // This ensures old sessions can't find their cache entries
                Cache::put($userDeviceKey, $deviceId, now()->addDays(30));
                
                // Then delete all old sessions from database
                $this->invalidateAllWebSessions($user, $currentSessionId);
            } else {
                // For mobile devices, invalidate all web sessions too
                Cache::put($userDeviceKey, $deviceId, now()->addDays(30));
                $this->invalidateWebSessions($user, $currentSessionId);
            }
            
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
            
            // Store current device mapping
            $userDeviceKey = "user_current_device_{$user->id}";
            Cache::put($userDeviceKey, $deviceId, now()->addDays(30));
            
            Log::info("Active session stored for user {$user->id}", [
                'session_id' => $sessionId,
                'device_id' => $deviceId,
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
            $deviceId = $user->deviceId ?? 'web_default';
            
            // Check if device ID matches current active device
            $userDeviceKey = "user_current_device_{$user->id}";
            $currentDeviceId = Cache::get($userDeviceKey);
            
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
            
            // Device ID must match - if it doesn't, the token is from a different device
            // This means user logged in from a new device, so old device's token should be rejected
            if ($currentDeviceId !== $deviceId) {
                // Check if this is a web session (both might be web but different sessions)
                $isWebToken = (!$deviceId || $deviceId === 'web_default' || strpos($deviceId, 'web_') === 0);
                $isWebCurrent = strpos($currentDeviceId, 'web_') === 0;
                
                if ($isWebToken && $isWebCurrent) {
                    // Both are web sessions - check token timestamp instead
                    // If token is old, reject it (user logged in from another browser)
                    // We'll check timestamp below
                    Log::debug("Web session device ID mismatch, checking timestamp", [
                        'user_id' => $user->id,
                        'token_device_id' => $deviceId,
                        'current_device_id' => $currentDeviceId,
                    ]);
                } else {
                    // Different device types (mobile vs web) or different mobile devices
                    // Reject immediately
                    Log::warning("Token rejected - device ID mismatch", [
                        'user_id' => $user->id,
                        'token_device_id' => $deviceId,
                        'current_device_id' => $currentDeviceId,
                    ]);
                    return false;
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
            
            // Use current device's timestamp for validation
            $lastValidTimestamp = $currentDeviceTimestamp;
            
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
            
            $cacheKey = "user_active_session_{$user->id}_{$deviceId}";
            $tokenTimestampKey = "user_token_timestamp_{$user->id}_{$deviceId}";
            $userDeviceKey = "user_current_device_{$user->id}";
            
            Cache::forget($cacheKey);
            Cache::forget($tokenTimestampKey);
            Cache::forget($userDeviceKey);
            
            // Also clear old format keys for backward compatibility
            $oldCacheKey = "user_active_session_{$user->id}";
            $oldTokenTimestampKey = "user_token_timestamp_{$user->id}";
            Cache::forget($oldCacheKey);
            Cache::forget($oldTokenTimestampKey);
            
            Log::info("Session tracking cleared for user {$user->id} on device {$deviceId}");
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
            
            // If not found, try to get from current device mapping
            if (!$sessionInfo) {
                $userDeviceKey = "user_current_device_{$user->id}";
                $currentDeviceId = Cache::get($userDeviceKey);
                if ($currentDeviceId) {
                    $cacheKey = "user_active_session_{$user->id}_{$currentDeviceId}";
                    $sessionInfo = Cache::get($cacheKey);
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
}

