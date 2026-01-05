<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Auth;
use App\Models\Invoice;
use App\Models\SubscriptionRecord;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Support\Facades\Log;

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
