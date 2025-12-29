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
                // Get user from token
                $user = JWTAuth::setToken($token)->authenticate();
                
                if ($user) {
                    // Check if token is from current session
                    $sessionService = new SessionManagementService();
                    $isValid = $sessionService->isTokenValid($token, $user);
                    
                    if (!$isValid) {
                        // Token is from a previous session, invalidate it
                        try {
                            JWTAuth::setToken($token)->invalidate();
                        } catch (\Exception $e) {
                            // Token might already be invalid
                        }
                        
                        Log::warning("Token rejected - from previous session", [
                            'user_id' => $user->id,
                        ]);
                        
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
