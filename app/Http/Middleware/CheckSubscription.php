<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\SubscriptionService;
use App\Models\SubscriptionRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
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

        // Skip check for unauthenticated users or super admin
        if (!$user || $user->hasRole('super_admin')) {
            return $next($request);
        }

        // Skip check for logout route (to allow logout even when expired)
        if ($request->routeIs('logout') || $request->is('logout')) {
            return $next($request);
        }

        $isExpired = false;
        $subscriptionStatus = null;

        // Check for organization users
        if ($user->orgId) {
            $subscriptionStatus = $this->subscriptionService->getSubscriptionStatus($user->orgId);
            
            // Check if subscription has expired (end_date is in the past)
            // Important: Cancelled subscriptions are still active until end date passes
            if (isset($subscriptionStatus['end_date']) && $subscriptionStatus['end_date']) {
                $endDate = Carbon::parse($subscriptionStatus['end_date'])->startOfDay();
                $today = Carbon::today();
                // Only expired if end date has passed
                $isExpired = $today->greaterThan($endDate);
            } elseif (!($subscriptionStatus['active'] ?? false)) {
                // Only mark as expired if there's no end date or it's truly inactive
                // Check if we have end date in subscription object
                if (isset($subscriptionStatus['subscription']) && $subscriptionStatus['subscription']->current_period_end) {
                    $endDate = Carbon::parse($subscriptionStatus['subscription']->current_period_end)->startOfDay();
                    $today = Carbon::today();
                    $isExpired = $today->greaterThan($endDate);
                } else {
                    $isExpired = true;
                }
            }
            
            if ($isExpired) {
                session()->put('subscription_expired', true);
                session()->put('subscription_status', $subscriptionStatus);
                
                // Only allow dashboard and billing routes (for payment)
                $routeName = $request->route()?->getName() ?? '';
                $path = $request->path();
                
                $allowedRoutes = ['dashboard', 'billing', 'logout'];
                $allowedRoutePrefixes = ['billing.', 'basecamp.billing.', 'basecamp.checkout.'];
                $allowedPaths = ['dashboard', 'billing'];
                
                $isAllowed = in_array($routeName, $allowedRoutes) || 
                             in_array($path, $allowedPaths) ||
                             str_starts_with($path, 'billing') ||
                             str_starts_with($path, 'basecamp/billing') ||
                             str_starts_with($path, 'basecamp/checkout') ||
                             collect($allowedRoutePrefixes)->contains(fn($prefix) => str_starts_with($routeName, $prefix));
                
                if (!$isAllowed) {
                    Log::info('Subscription expired - blocking route', [
                        'route_name' => $routeName,
                        'path' => $path,
                        'user_id' => $user->id,
                        'org_id' => $user->orgId,
                        'end_date' => $subscriptionStatus['end_date'] ?? null,
                    ]);
                    return redirect()->route('dashboard');
                }
            } else {
                session()->forget('subscription_expired');
                session()->forget('subscription_status');
            }
        }
        
        // Check for basecamp users
        if ($user->hasRole('basecamp')) {
            // Get LATEST subscription (by ID) to ensure we check the most recent one
            $subscription = SubscriptionRecord::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->orderBy('id', 'desc') // Use ID instead of created_at for better ordering
                ->first();
            
            if ($subscription) {
                $endDate = $subscription->current_period_end ? Carbon::parse($subscription->current_period_end)->startOfDay() : null;
                $today = Carbon::today();
                
                // Check if subscription is expired
                // Important: Cancelled subscriptions are still active until end date passes
                $isExpired = false;
                
                if ($endDate && $today->greaterThan($endDate)) {
                    // End date has passed - subscription is expired regardless of status
                    $isExpired = true;
                } elseif ($endDate && $endDate->isFuture()) {
                    // End date is in future - subscription is active (even if cancelled)
                    // User can access until the end date
                    $isExpired = false;
                } elseif (!$endDate) {
                    // No end date - check status
                    if ($subscription->status !== 'active') {
                        $isExpired = true;
                    }
                }
                
                Log::info('Basecamp subscription check in middleware', [
                    'subscription_id' => $subscription->id,
                    'status' => $subscription->status,
                    'end_date' => $endDate ? $endDate->format('Y-m-d') : null,
                    'today' => $today->format('Y-m-d'),
                    'is_expired' => $isExpired,
                    'end_date_is_future' => $endDate ? $endDate->isFuture() : false
                ]);
                
                if ($isExpired) {
                    $subscriptionStatus = [
                        'active' => false,
                        'status' => $subscription->status,
                        'end_date' => $endDate ? $endDate->format('Y-m-d') : null,
                        'expired' => true,
                    ];
                    
                    session()->put('subscription_expired', true);
                    session()->put('subscription_status', $subscriptionStatus);
                    
                    // Only allow dashboard and billing routes for basecamp
                    $routeName = $request->route()?->getName() ?? '';
                    $path = $request->path();
                    
                    $allowedRoutes = ['dashboard', 'billing', 'logout', 'basecamp.billing'];
                    $allowedRoutePrefixes = ['billing.', 'basecamp.billing.', 'basecamp.checkout.'];
                    $allowedPaths = ['dashboard', 'billing', 'basecamp/billing'];
                    
                    $isAllowed = in_array($routeName, $allowedRoutes) || 
                                 in_array($path, $allowedPaths) ||
                                 str_starts_with($path, 'billing') ||
                                 str_starts_with($path, 'basecamp/billing') ||
                                 str_starts_with($path, 'basecamp/checkout') ||
                                 collect($allowedRoutePrefixes)->contains(fn($prefix) => str_starts_with($routeName, $prefix));
                    
                    if (!$isAllowed) {
                        return redirect()->route('dashboard');
                    }
                } else {
                    session()->forget('subscription_expired');
                    session()->forget('subscription_status');
                }
            } else {
                // No subscription = expired
                $isExpired = true;
                $subscriptionStatus = [
                    'active' => false,
                    'status' => 'none',
                    'end_date' => null,
                    'expired' => true,
                ];
                
                session()->put('subscription_expired', true);
                session()->put('subscription_status', $subscriptionStatus);
                
                // Only allow dashboard route
                if (!$request->routeIs('dashboard') && !$request->routeIs('logout')) {
                    return redirect()->route('dashboard');
                }
            }
        }

        return $next($request);
    }
}

