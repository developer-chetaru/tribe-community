<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\SubscriptionRecord;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckBasecampPayment
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip check if user is not authenticated
        if (!Auth::check()) {
            return $next($request);
        }
        
        $user = Auth::user();

        // Only check for basecamp users
        if ($user && $user->hasRole('basecamp') && !$user->hasRole('super_admin')) {
            // Check if subscription is suspended or inactive (highest priority)
            $inactiveSubscription = SubscriptionRecord::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->whereIn('status', ['suspended', 'inactive'])
                ->orderBy('id', 'desc')
                ->first();
            
            if ($inactiveSubscription) {
                // Allow account restricted route to prevent redirect loop
                if ($request->routeIs('account.restricted') || $request->is('account/restricted') || $request->is('account/suspended')) {
                    return $next($request);
                }
                
                // Only allow billing/reactivation routes and logout
                $routeName = $request->route()?->getName() ?? '';
                $path = $request->path();
                
                $allowedRoutes = ['basecamp.billing', 'logout', 'billing.reactivate'];
                $allowedRoutePrefixes = ['basecamp.billing.', 'basecamp.checkout.', 'billing.'];
                $allowedPaths = ['basecamp/billing', 'billing'];
                
                $isAllowed = in_array($routeName, $allowedRoutes) || 
                             in_array($path, $allowedPaths) ||
                             str_starts_with($path, 'basecamp/billing') ||
                             str_starts_with($path, 'basecamp/checkout') ||
                             str_starts_with($path, 'billing') ||
                             collect($allowedRoutePrefixes)->contains(fn($prefix) => str_starts_with($routeName, $prefix));
                
                if (!$isAllowed) {
                    return redirect()->route('account.restricted');
                }
                return $next($request);
            }
            
            // Check if user has active subscription or paid invoice
            $hasActiveSubscription = SubscriptionRecord::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->where('status', 'active')
                ->where('current_period_end', '>', now())
                ->exists();
                
            $hasPaidInvoice = Invoice::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->where('status', 'paid')
                ->exists();
            
            // If no payment completed, redirect to billing page
            if (!$hasActiveSubscription && !$hasPaidInvoice) {
                // Allow access to billing, payment, logout, login, and verification routes
                $allowedRoutes = [
                    'basecamp.billing',
                    'basecamp.checkout.create',
                    'basecamp.checkout.redirect',
                    'basecamp.billing.payment.success',
                    'logout',
                    'login',
                    'user.verify',
                    'password.update',
                    'password.reset',
                    'custom.register',
                ];
                
                // Allow API routes
                if ($request->is('api/*')) {
                    return $next($request);
                }
                
                // Allow public routes (login, register, password reset, verification)
                if ($request->is('login') || 
                    $request->is('register') || 
                    $request->is('password/*') || 
                    $request->is('verify-user/*') ||
                    $request->is('refresh-csrf-token')) {
                    return $next($request);
                }
                
                // Allow basecamp billing and payment routes by path
                if ($request->is('basecamp/billing') || 
                    $request->is('basecamp/checkout/*') || 
                    $request->is('basecamp/billing/payment/*')) {
                    return $next($request);
                }
                
                // Allow dashboard route - it will show payment modal
                if ($request->is('dashboard') || $request->routeIs('dashboard')) {
                    return $next($request);
                }
                
                // Allow allowed routes
                if ($request->routeIs($allowedRoutes)) {
                    return $next($request);
                }
                
                // Explicitly block profile and other user routes
                // Check path patterns for user routes
                $path = $request->path();
                if (str_starts_with($path, 'user/') || 
                    $path === 'user-profile' || 
                    $request->routeIs('profile.*') ||
                    $request->routeIs('profile.show') ||
                    $request->routeIs('profile-information.update')) {
                    return redirect()->route('dashboard')
                        ->with('error', 'Please complete your payment of £10 to activate your account.');
                }
                
                // Block all other routes including profile, hptm, billing, etc.
                // Redirect to dashboard (which will show payment modal) for all other routes
                return redirect()->route('dashboard')
                    ->with('error', 'Please complete your payment of £10 to activate your account.');
            }
        }

        return $next($request);
    }
}

