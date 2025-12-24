<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Tymon\JWTAuth\Facades\JWTAuth;

class LoginResponse implements LoginResponseContract
{
    /**
     * Generate the login response.
     *
     * Returns JSON response with JWT token for API requests,
     * or redirects based on user role for web requests.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function toResponse($request)
    {
        $user = Auth::guard('web')->user();
  		if (($request->wantsJson() || $request->is('api/*')) && !$user) {
            try {
                $user = JWTAuth::attempt($request->only('email', 'password'))
                    ? JWTAuth::user()
                    : null;
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }
        }

        if ($request->wantsJson() || $request->is('api/*')) {
            $token = $user ? JWTAuth::fromUser($user) : null;

            return response()->json([
                'status'  => true,
                'message' => 'Login successful',
                'user'    => $user,
                'token'   => $token,
            ]);
        }

        if ($user) {
            if ($user->hasRole('super_admin')) {
                return redirect()->route('organisations.index');
            } elseif ($user->hasRole('organisation_user')) {
                return redirect()->route('dashboard');
            }elseif ($user->hasRole('organisation_admin')) {
                return redirect()->route('dashboard');
            }elseif ($user->hasRole('director')) {
                return redirect()->route('dashboard');
            }
             else {
                return redirect()->intended('/dashboard');
            }
        }

        return redirect()->route('login');
    }
}
