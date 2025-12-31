<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Auth;
use App\Models\Invoice;
use App\Models\SubscriptionRecord;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

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

        // For basecamp users, check if payment has been completed
        if ($user->hasRole('basecamp')) {
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
                return redirect()->route('basecamp.billing')->with('error', 'Please complete your payment of $10 to continue.');
            }
            
            // If payment completed but account not activated, redirect to billing (they should verify email)
            if (!$user->status) {
                return redirect()->route('basecamp.billing')->with('status', 'Payment completed! Please check your email to activate your account.');
            }
        }

        if ($user->hasRole('basecamp') || $user->hasRole('organisation_user') || $user->hasRole('organisation_admin') || $user->hasRole('director')) {
            return redirect()->route('dashboard');
        }

        return redirect()->intended('/dashboard');
    }
}
