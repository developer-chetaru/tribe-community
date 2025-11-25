<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Auth;
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

        if ($user->hasRole('basecamp') || $user->hasRole('organisation_user')) {
            return redirect()->route('dashboard');
        }

        return redirect()->intended('/dashboard');
    }
}
