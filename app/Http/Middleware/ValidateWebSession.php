<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Services\SessionManagementService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ValidateWebSession
{
    /**
     * Handle an incoming request.
     * Validates that web session is from the most recent login
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip validation for login, register, password reset, and basecamp billing routes
        $excludedRoutes = ['login', 'register', 'password.reset', 'password.email', 'password.update', 'custom.password.reset', 'basecamp.billing'];
        $routeName = $request->route()?->getName();
        
        if (in_array($routeName, $excludedRoutes) || 
            $request->is('login') || 
            $request->is('register') || 
            $request->is('password/*') ||
            $request->is('reset-password') ||
            $request->is('basecamp/billing')) {
            return $next($request);
        }
        
        // Only check for authenticated web users
        if (!Auth::guard('web')->check()) {
            return $next($request);
        }
        
        $user = Auth::guard('web')->user();
        
        // Skip if user is null
        if (!$user) {
            return $next($request);
        }
        
        $currentSessionId = session()->getId();
        
        try {
            $sessionService = new SessionManagementService();
            
            // Get active session info
            $activeSessionInfo = $sessionService->getActiveSessionInfo($user, $currentSessionId);
            
            // Check last WEB login timestamp - only invalidate if new web login happened
            // App logins should not affect web sessions
            $lastLoginKey = "user_last_web_login_{$user->id}";
            $lastLoginTimestamp = Cache::get($lastLoginKey);
            
            // IMPORTANT: Only check last login timestamp if we have active session info
            // AND the session was definitely created BEFORE the last login
            // This prevents false rejections when cache has stale data or session regenerated
            if ($lastLoginTimestamp && $activeSessionInfo) {
                $timeSinceLastLogin = now()->timestamp - $lastLoginTimestamp;
                $sessionCreatedAt = $activeSessionInfo['token_issued_at'] ?? 0;
                
                // Only reject if ALL conditions are met:
                // 1. Last login was more than 60 seconds ago (not during login process)
                // 2. Session was created BEFORE last login (definitely old session)
                // 3. Session age is more than 60 seconds (not a recent session)
                // This ensures we only reject when we're CERTAIN there was a new login
                if ($timeSinceLastLogin > 60 && $sessionCreatedAt < $lastLoginTimestamp) {
                    $sessionAge = now()->timestamp - $sessionCreatedAt;
                    
                    if ($sessionAge > 60) {
                        Log::warning("Web session rejected - created before last login (confirmed old session)", [
                            'user_id' => $user->id,
                            'current_session_id' => $currentSessionId,
                            'session_created_at' => $sessionCreatedAt,
                            'last_login_timestamp' => $lastLoginTimestamp,
                            'time_since_last_login' => $timeSinceLastLogin,
                            'session_age' => $sessionAge,
                        ]);
                        
                        // Delete this session
                        try {
                            DB::table('sessions')->where('id', $currentSessionId)->delete();
                        } catch (\Exception $e) {
                            Log::warning("Failed to delete session", ['error' => $e->getMessage()]);
                        }
                        
                        // Logout user
                        Auth::guard('web')->logout();
                        $request->session()->invalidate();
                        $request->session()->regenerateToken();
                        
                        return redirect()->route('login')->with('error', 'Your session has expired. Another device or browser has logged in. Please login again.');
                    }
                }
            }
            
            // If no active session info stored, allow (backward compatibility)
            // But also store current session as active for future checks
            if (!$activeSessionInfo) {
                // Store current session as active (first time login after feature implementation)
                try {
                    $sessionService->storeActiveSession($user, $currentSessionId);
                    Log::info("No active session found, storing current session for user {$user->id}");
                } catch (\Exception $e) {
                    // If storing fails, still allow request
                    Log::warning("Failed to store session, but allowing request", [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
                return $next($request);
            }
            
            // Check if current session ID matches the active session
            if ($activeSessionInfo && isset($activeSessionInfo['session_id'])) {
                if ($activeSessionInfo['session_id'] !== $currentSessionId) {
                    // Session ID doesn't match - check if it's a recent login or session regeneration
                    $sessionAge = now()->timestamp - ($activeSessionInfo['token_issued_at'] ?? 0);
                    
                    // IMPORTANT: Only reject if we have a recent login timestamp AND session is old
                    // If there's no recent login, allow the session (might be session regeneration)
                    if ($lastLoginTimestamp) {
                        $timeSinceLastLogin = now()->timestamp - $lastLoginTimestamp;
                        
                        // Only reject if:
                        // 1. Last login was more than 30 seconds ago (not during login process)
                        // 2. Session age is more than 30 seconds (not a recent session regeneration)
                        // 3. Session was created BEFORE the last login (definitely an old session)
                        $sessionCreatedAt = $activeSessionInfo['token_issued_at'] ?? 0;
                        $isOldSession = ($sessionCreatedAt < $lastLoginTimestamp) && ($timeSinceLastLogin > 30);
                        
                        if ($isOldSession && $sessionAge > 30) {
                            Log::warning("Web session rejected - session ID mismatch (old session)", [
                                'user_id' => $user->id,
                                'current_session_id' => $currentSessionId,
                                'active_session_id' => $activeSessionInfo['session_id'],
                                'session_age' => $sessionAge,
                                'session_created_at' => $sessionCreatedAt,
                                'last_login_timestamp' => $lastLoginTimestamp,
                            ]);
                            
                            // Delete this session from database
                            try {
                                DB::table('sessions')->where('id', $currentSessionId)->delete();
                            } catch (\Exception $e) {
                                Log::warning("Failed to delete session", ['error' => $e->getMessage()]);
                            }
                            
                            // Logout user
                            Auth::guard('web')->logout();
                            $request->session()->invalidate();
                            $request->session()->regenerateToken();
                            
                            return redirect()->route('login')->with('error', 'Your session has expired. Another device or browser has logged in. Please login again.');
                        } else {
                            // Session might be regenerated or recent - update it
                            Log::info("Session ID changed, updating active session (not old session)", [
                                'user_id' => $user->id,
                                'old_session_id' => $activeSessionInfo['session_id'],
                                'new_session_id' => $currentSessionId,
                                'session_age' => $sessionAge,
                                'time_since_last_login' => $timeSinceLastLogin,
                            ]);
                            
                            try {
                                $sessionService->storeActiveSession($user, $currentSessionId);
                            } catch (\Exception $e) {
                                Log::warning("Failed to update session ID", [
                                    'user_id' => $user->id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    } else {
                        // No last login timestamp - might be session regeneration, update it
                        Log::info("Session ID changed but no last login timestamp, updating active session", [
                            'user_id' => $user->id,
                            'old_session_id' => $activeSessionInfo['session_id'],
                            'new_session_id' => $currentSessionId,
                            'session_age' => $sessionAge,
                        ]);
                        
                        try {
                            $sessionService->storeActiveSession($user, $currentSessionId);
                        } catch (\Exception $e) {
                            Log::warning("Failed to update session ID", [
                                'user_id' => $user->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }
            
            // Session is valid, allow request
            // Log::debug("Web session validated for user {$user->id}", [
            //     'session_id' => $currentSessionId,
            // ]);
            
        } catch (\Exception $e) {
            // On error, log but allow request (fail open for backward compatibility)
            Log::warning("Web session validation check failed - allowing request", [
                'user_id' => $user->id ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            // Always allow request on error to prevent blocking users
        }
        
        return $next($request);
    }
}

