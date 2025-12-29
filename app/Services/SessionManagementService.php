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
     * This ensures only one active session at a time
     * 
     * @param User $user
     * @param string|null $currentToken Current JWT token (for API)
     * @param string|null $currentSessionId Current session ID (for Web)
     * @return void
     */
    public function invalidatePreviousSessions(User $user, $currentToken = null, $currentSessionId = null)
    {
        try {
            // Store current session info
            $sessionInfo = [
                'user_id' => $user->id,
                'token_issued_at' => now()->timestamp,
                'session_id' => $currentSessionId,
                'device_type' => $user->deviceType ?? 'web',
                'device_id' => $user->deviceId ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ];
            
            // Store in cache with user-specific key
            $cacheKey = "user_active_session_{$user->id}";
            Cache::put($cacheKey, $sessionInfo, now()->addDays(30)); // Store for 30 days
            
            // Invalidate all web sessions for this user (except current)
            $this->invalidateWebSessions($user, $currentSessionId);
            
            // For JWT tokens, we'll use a timestamp-based approach
            // Store timestamp when new token is issued
            $tokenTimestampKey = "user_token_timestamp_{$user->id}";
            $previousTimestamp = Cache::get($tokenTimestampKey);
            Cache::put($tokenTimestampKey, now()->timestamp, now()->addDays(30));
            
            // If there was a previous timestamp, we can log it
            if ($previousTimestamp) {
                Log::info("Previous session invalidated for user {$user->id}", [
                    'previous_timestamp' => $previousTimestamp,
                    'new_timestamp' => now()->timestamp,
                ]);
            }
            
            Log::info("Session management: New session created for user {$user->id}", [
                'device_type' => $sessionInfo['device_type'],
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
            
            // Update token timestamp to invalidate all JWT tokens
            $tokenTimestampKey = "user_token_timestamp_{$user->id}";
            Cache::put($tokenTimestampKey, now()->timestamp, now()->addDays(30));
            
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
            $sessionInfo = [
                'user_id' => $user->id,
                'token_issued_at' => now()->timestamp,
                'session_id' => $sessionId,
                'device_type' => $user->deviceType ?? 'web',
                'device_id' => $user->deviceId ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ];
            
            // Store in cache with user-specific key
            $cacheKey = "user_active_session_{$user->id}";
            Cache::put($cacheKey, $sessionInfo, now()->addDays(30));
            
            Log::info("Active session stored for user {$user->id}", [
                'session_id' => $sessionId,
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
     * Check if a JWT token is still valid (not from a previous session)
     * 
     * @param string $token
     * @param User $user
     * @return bool
     */
    public function isTokenValid($token, User $user)
    {
        try {
            $tokenTimestampKey = "user_token_timestamp_{$user->id}";
            $lastValidTimestamp = Cache::get($tokenTimestampKey);
            
            if (!$lastValidTimestamp) {
                // No timestamp stored, token might be from before this feature
                // Allow it for backward compatibility
                return true;
            }
            
            // Decode token to get issued at time
            try {
                $payload = JWTAuth::setToken($token)->getPayload();
                $tokenIat = $payload->get('iat'); // Issued at timestamp
                
                // Token is valid if it was issued after or at the last valid timestamp
                // We use >= to allow tokens issued at the exact same second
                return $tokenIat >= $lastValidTimestamp;
            } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
                // Token expired, not valid
                return false;
            } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
                // Token invalid, not valid
                return false;
            } catch (\Exception $e) {
                // Other error decoding token, assume invalid
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
            // On error, reject token (fail closed for security)
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
            $cacheKey = "user_active_session_{$user->id}";
            $tokenTimestampKey = "user_token_timestamp_{$user->id}";
            
            Cache::forget($cacheKey);
            Cache::forget($tokenTimestampKey);
            
            Log::info("Session tracking cleared for user {$user->id}");
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
     * @return array|null
     */
    public function getActiveSessionInfo(User $user)
    {
        try {
            $cacheKey = "user_active_session_{$user->id}";
            return Cache::get($cacheKey);
        } catch (\Exception $e) {
            Log::error("Failed to get active session info for user {$user->id}", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}

