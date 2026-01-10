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

        // Check for suspended status FIRST (before any other redirect)
        if ($user->hasRole('basecamp')) {
            $suspendedSubscription = SubscriptionRecord::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->where('status', 'suspended')
                ->orderBy('id', 'desc')
                ->first();
                
            if ($suspendedSubscription) {
                return redirect()->route('account.suspended');
            }
        } elseif ($user->orgId) {
            $subscriptionService = new SubscriptionService();
            $subscriptionStatus = $subscriptionService->getSubscriptionStatus($user->orgId);
            
            if (isset($subscriptionStatus['status']) && $subscriptionStatus['status'] === 'suspended') {
                return redirect()->route('account.suspended');
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
