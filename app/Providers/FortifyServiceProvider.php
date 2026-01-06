<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;
use App\Actions\Fortify\CreateNewUser;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use Illuminate\Support\Facades\RateLimiter;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use App\Http\Responses\LoginResponse as CustomLoginResponse;
use Illuminate\Support\Facades\Password;
use App\Services\OneSignalService;
use Illuminate\Support\Facades\Log;


class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //$this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(LoginResponse::class, CustomLoginResponse::class);
        $this->app->singleton(TwoFactorLoginResponse::class, CustomLoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);
    	$this->app->singleton(
            LoginResponseContract::class,
            LoginResponse::class
        );

            Fortify::authenticateUsing(function (Request $request) {
	    $user = User::where('email', $request->email)->first();

    	// Check if email exists
    	if (!$user) {
    	    throw ValidationException::withMessages([
    	        'email' => __('The provided email address is not registered.'),
    	    ]);
    	}

    	// Check if password is correct
    	if (!Hash::check($request->password, $user->password)) {
    	    throw ValidationException::withMessages([
    	        'password' => __('The provided password is incorrect.'),
    	    ]);
    	}

    	// Check if account is suspended
    	if ($user->status === 'suspended') {
    	    $subscription = \App\Models\SubscriptionRecord::where('user_id', $user->id)
    	        ->where('tier', 'basecamp')
    	        ->first();
    	    
    	    $unpaidInvoice = null;
    	    if ($subscription) {
    	        $unpaidInvoice = \App\Models\Invoice::where('subscription_id', $subscription->id)
    	            ->where('status', 'unpaid')
    	            ->orderBy('due_date', 'asc')
    	            ->first();
    	    }
    	    
    	    // Store suspension info in session for reactivation page
    	    session()->put('suspended_user_id', $user->id);
    	    session()->put('suspended_invoice_id', $unpaidInvoice?->id);
    	    
    	    throw ValidationException::withMessages([
    	        'email' => __('Your account has been suspended due to payment failure. Please reactivate your account.'),
    	    ]);
    	}

    	// Check if account is activated
    	// Note: status is now ENUM, so check for active statuses
    	if (!in_array($user->status, ['active_verified', 'active_unverified', 'pending_payment'])) {
    	    throw ValidationException::withMessages([
    	        'email' => __('Your account is not activated yet, please check your email and follow the instruction to verify your account.'),
    	    ]);
    	}

    	return $user;
	});
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

		



		app('router')->post('/reset-password', function (Request $request) {
            $request->validate([
                'token' => 'required',
                'email' => 'required|email',
                'password' => 'required|min:8|confirmed',
            ]);

            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                    ])->save();

                    // ✅ Send OneSignal notification after successful reset
                    try {
                        $oneSignal = new OneSignalService();
                        $subject = config('app.name') . ' - Password Reset Successful';
                        $body = "<p>Hello {$user->first_name},</p>
                                 <p>Your password has been successfully reset on " . config('app.name') . ".</p>
                                 <p>If you didn’t perform this action, please contact our support immediately.</p>
                                 <p>Thanks,<br>" . config('app.name') . " Team</p>";

                        $payload = [
                            'subject' => $subject,
                            'body' => $body,
                        ];

                        $oneSignal->registerEmailUserFallback($user->email, $user->id, $payload);

                        Log::info('✅ OneSignal password reset confirmation email sent', [
                            'email' => $user->email,
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('❌ OneSignal password reset confirmation failed', [
                            'email' => $user->email,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            );

            return $status === Password::PASSWORD_RESET
                ? redirect()->route('login')->with('status', __($status))
                : back()->withErrors(['email' => [__($status)]]);
        })->middleware(['guest'])->name('password.update');
    }
}
