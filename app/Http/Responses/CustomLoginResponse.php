<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Auth;
use App\Models\Invoice;
use App\Models\SubscriptionRecord;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Support\Facades\Log;
use App\Services\SubscriptionService;

class CustomLoginResponse implements LoginResponseContract
{
   /**
    * Handle login response and redirect based on user role.
    *
    * @param \Illuminate\Http\Request $request
    * @return \Illuminate\Http\RedirectResponse
    */
    public function toResponse($request)
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        if ($user->hasRole('super_admin')) {
            return redirect()->route('organisations.index');
        }

        // Check for suspended or inactive status FIRST (before any other redirect)
        if ($user->hasRole('basecamp')) {
            $inactiveSubscription = SubscriptionRecord::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->whereIn('status', ['suspended', 'inactive'])
                ->orderBy('id', 'desc')
                ->first();
                
            if ($inactiveSubscription) {
                return redirect()->route('account.restricted');
            }
        } elseif ($user->orgId) {
            $subscriptionService = new SubscriptionService();
            $subscriptionStatus = $subscriptionService->getSubscriptionStatus($user->orgId);
            
            if (isset($subscriptionStatus['status']) && in_array($subscriptionStatus['status'], ['suspended', 'inactive'])) {
                return redirect()->route('account.restricted');
            }
        }

        // For basecamp users, always redirect to dashboard
        // The billing component will handle payment checks
        if ($user->hasRole('basecamp')) {
            return redirect()->route('dashboard');
        }

        if ($user->hasRole('organisation_user') || $user->hasRole('organisation_admin') || $user->hasRole('director')) {
            return redirect()->route('dashboard');
        }

        return redirect()->intended('/dashboard');
    }
}
