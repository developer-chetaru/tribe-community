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
     * COMMENTED OUT: Auto logout disabled - allow all requests
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // COMMENTED OUT: Auto logout disabled - allow all requests
        // Just pass through without any validation
        Log::info("ValidateJWTToken - auto logout disabled, allowing all requests", [
            'path' => $request->path(),
        ]);
        return $next($request);
    }
}
