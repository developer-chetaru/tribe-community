<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'validate.jwt' => \App\Http\Middleware\ValidateJWTToken::class,
            'validate.web.session' => \App\Http\Middleware\ValidateWebSession::class,
            'check.basecamp.payment' => \App\Http\Middleware\CheckBasecampPayment::class,
            'check.subscription' => \App\Http\Middleware\CheckSubscription::class,
        ]);
        
        // Apply basecamp payment check and subscription check globally to all web routes
        $middleware->web(append: [
            \App\Http\Middleware\CheckBasecampPayment::class,
            \App\Http\Middleware\CheckSubscription::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle 419 CSRF token mismatch errors gracefully
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            // For AJAX/Livewire requests, return JSON instead of HTML error page
            if ($request->expectsJson() || $request->header('X-Livewire') || $request->ajax()) {
                return response()->json([
                    'message' => 'CSRF token mismatch. Please refresh the page.',
                    'error' => 'Page Expired'
                ], 419);
            }
            
            // For regular requests, redirect to login with a message
            return redirect()->route('login')
                ->with('error', 'Your session has expired. Please log in again.');
        });
        
        // COMMENTED OUT: Auto logout disabled - allow unauthenticated requests for summary endpoints
        // Handle unauthenticated exceptions for summary APIs
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            // For summary endpoints, allow unauthenticated requests (auto logout disabled)
            $path = $request->path();
            $isSummaryEndpoint = strpos($path, 'api/weekly-summaries') !== false || 
                                 strpos($path, 'api/monthly-summary') !== false ||
                                 strpos($path, 'api/summary/') !== false;
            
            if ($isSummaryEndpoint && $request->expectsJson()) {
                // Return empty data instead of 401 for summary endpoints
                \Illuminate\Support\Facades\Log::info("Summary endpoint - allowing unauthenticated request", [
                    'path' => $path,
                ]);
                // Don't return anything - let the controller handle it
                return null;
            }
            
            // For other endpoints, use default behavior
            return null;
        });
    })->create();
