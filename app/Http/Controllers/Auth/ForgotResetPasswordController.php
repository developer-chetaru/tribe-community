<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Services\OneSignalService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class ForgotResetPasswordController extends Controller
{
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return back()->withErrors(['email' => 'Email not found']);
        }

        // Generate token manually for custom email
        $token = app('auth.password.broker')->createToken($user);

        // Build reset URL
        $resetUrl = url(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ], false));

        // Render your existing Blade email template to HTML
        $emailHtml = View::make('emails.custom-reset-password', [
            'user'        => $user,
            'userFullName'=> $user->first_name . ' ' . $user->last_name,
            'orgName'     => optional($user->organisation)->name ?? 'Organisation',
            'inviterName' => null, // For normal reset this is always null
            'resetUrl'    => $resetUrl,
        ])->render();

        try {
            // Send via OneSignal
            $oneSignal = new OneSignalService();
            $oneSignal->sendForgotPasswordEmail($user->email, $emailHtml);

            return back()->with('status', 'We have emailed your password reset link!');
        } 
        catch (\Throwable $e) {
            Log::error('OneSignal Email Failed', [
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['email' => 'Error sending password reset email']);
        }
    }

    public function store(Request $request)
    {
		
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Password updated successfully!')
            : back()->withErrors(['email' => [__($status)]]);
    }

	public function showResetForm(Request $request)
    {
        return view('auth.reset-password', [
            'request' => $request
        ]);
    }
}
