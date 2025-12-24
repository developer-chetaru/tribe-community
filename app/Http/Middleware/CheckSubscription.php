<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\SubscriptionService;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    protected $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // Only check for organization users (not super_admin)
        if ($user && !$user->hasRole('super_admin') && $user->orgId) {
            $subscriptionStatus = $this->subscriptionService->getSubscriptionStatus($user->orgId);
            
            // If subscription is not active, redirect to subscription expired page
            if (!$subscriptionStatus['active']) {
                // Store subscription status in session for the popup
                session()->put('subscription_expired', true);
                session()->put('subscription_status', $subscriptionStatus);
                
                // Allow access to billing page for directors to pay
                if ($user->hasRole('director') && $request->routeIs('billing')) {
                    return $next($request);
                }
                
                // Block all other access
                if (!$request->routeIs('subscription.expired')) {
                    return redirect()->route('subscription.expired');
                }
            } else {
                // Clear subscription expired flag if subscription is active
                session()->forget('subscription_expired');
                session()->forget('subscription_status');
            }
        }

        return $next($request);
    }
}

