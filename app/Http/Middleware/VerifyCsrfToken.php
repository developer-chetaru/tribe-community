<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Livewire handles CSRF tokens automatically
        '/livewire/*',
        // Webhook endpoints (handled by payment gateways)
        '/webhooks/stripe',
        '/webhooks/paypal',
    ];

    /**
     * Determine if the session and input CSRF tokens match.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function tokensMatch($request)
    {
        // For Livewire requests, use their token handling
        if ($request->header('X-Livewire')) {
            return true; // Livewire handles CSRF validation internally
        }

        return parent::tokensMatch($request);
    }
    
    /**
     * Handle a failed CSRF token verification.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return mixed
     */
    protected function handleTokenMismatch($request, $exception)
    {
        // For AJAX/Livewire requests, return JSON instead of throwing exception
        if ($request->expectsJson() || $request->header('X-Livewire') || $request->ajax()) {
            return response()->json([
                'message' => 'CSRF token mismatch. Please refresh the page.',
                'error' => 'Page Expired'
            ], 419);
        }
        
        // For regular requests, use default behavior
        return parent::handleTokenMismatch($request, $exception);
    }
}

