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
            
            // If there's a last login timestamp, check if current session is from before it
            // BUT: Allow a grace period of 30 seconds for new logins to stabilize
            if ($lastLoginTimestamp) {
                $timeSinceLastLogin = now()->timestamp - $lastLoginTimestamp;
                
                // If last login was very recent (within 30 seconds), be lenient
                // This handles cases where session is being set up
                if ($timeSinceLastLogin > 30) {
                    if ($activeSessionInfo) {
                        $sessionCreatedAt = $activeSessionInfo['token_issued_at'] ?? 0;
                        
                        // If session was created before last login, it's an old session - reject it
                        if ($sessionCreatedAt < $lastLoginTimestamp) {
                            Log::warning("Web session rejected - created before last login", [
                                'user_id' => $user->id,
                                'current_session_id' => $currentSessionId,
                                'session_created_at' => $sessionCreatedAt,
                                'last_login_timestamp' => $lastLoginTimestamp,
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
                    // Session ID doesn't match - check if it's a recent login
                    $sessionAge = now()->timestamp - ($activeSessionInfo['token_issued_at'] ?? 0);
                    
                    // Allow up to 30 seconds for session to stabilize after login
                    if ($sessionAge < 30) {
                        // Recent login - might be session regeneration, update it
                        Log::info("Session ID changed shortly after login, updating active session", [
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
                    } else {
                        // Old session - reject it (but only if last login was more than 30 seconds ago)
                        if ($lastLoginTimestamp) {
                            $timeSinceLastLogin = now()->timestamp - $lastLoginTimestamp;
                            if ($timeSinceLastLogin > 30) {
                                Log::warning("Web session rejected - session ID mismatch (old session)", [
                                    'user_id' => $user->id,
                                    'current_session_id' => $currentSessionId,
                                    'active_session_id' => $activeSessionInfo['session_id'],
                                    'session_age' => $sessionAge,
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
                            }
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

