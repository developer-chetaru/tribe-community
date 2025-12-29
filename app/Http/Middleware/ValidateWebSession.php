<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Services\SessionManagementService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
        // Skip validation for login, register, and password reset routes
        $excludedRoutes = ['login', 'register', 'password.reset', 'password.email', 'password.update', 'custom.password.reset'];
        $routeName = $request->route()?->getName();
        
        if (in_array($routeName, $excludedRoutes) || 
            $request->is('login') || 
            $request->is('register') || 
            $request->is('password/*') ||
            $request->is('reset-password')) {
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
            $activeSessionInfo = $sessionService->getActiveSessionInfo($user, $currentSessionId);
            
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
            // Allow a time window (30 seconds) after login for session to stabilize
            // This handles cases where session ID changes during redirect or cache hasn't updated yet
            $sessionAge = now()->timestamp - ($activeSessionInfo['token_issued_at'] ?? 0);
            
            if (isset($activeSessionInfo['session_id']) && $activeSessionInfo['session_id'] !== $currentSessionId) {
                // If session was just created (within 30 seconds), update it instead of rejecting
                // This handles cases where session ID changes during redirect or listener hasn't run yet
                if ($sessionAge < 30) {
                    Log::info("Session ID changed shortly after login, updating active session", [
                        'user_id' => $user->id,
                        'old_session_id' => $activeSessionInfo['session_id'],
                        'new_session_id' => $currentSessionId,
                        'session_age' => $sessionAge,
                    ]);
                    
                    // Update active session with new session ID
                    try {
                        $sessionService->storeActiveSession($user, $currentSessionId);
                    } catch (\Exception $e) {
                        Log::warning("Failed to update session ID", [
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } else {
                    // Session is from previous login, invalidate it
                    Log::warning("Web session rejected - from previous login", [
                        'user_id' => $user->id,
                        'current_session_id' => $currentSessionId,
                        'active_session_id' => $activeSessionInfo['session_id'] ?? 'not set',
                        'session_age' => $sessionAge,
                    ]);
                    
                    // Delete this session from database
                    try {
                        DB::table('sessions')
                            ->where('id', $currentSessionId)
                            ->delete();
                    } catch (\Exception $e) {
                        Log::warning("Failed to delete session from database", [
                            'session_id' => $currentSessionId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                    
                    // Logout user
                    try {
                        Auth::guard('web')->logout();
                        $request->session()->invalidate();
                        $request->session()->regenerateToken();
                    } catch (\Exception $e) {
                        Log::warning("Failed to logout user", [
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                    
                    // Redirect to login with message
                    return redirect()->route('login')->with('error', 'Your session has expired. Please login again.');
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

