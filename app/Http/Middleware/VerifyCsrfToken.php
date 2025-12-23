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
}

