<?php

namespace App\Actions\Fortify;

use Carbon\Carbon;
use App\Models\User;
use App\Mail\VerifyUserEmail;
use Laravel\Jetstream\Jetstream;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use App\Services\OneSignalService;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;
use Illuminate\Support\Facades\Log;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],
            'email'      => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'   => array_merge($this->passwordRules(), ['confirmed']),
            'password_confirmation' => ['required'],
            'terms'      => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ], [
            'password_confirmation.required' => 'The confirm password field is required.',
            'password.confirmed'              => 'The password confirmation does not match.',
        ])->validate();

        $user = User::create([
            'first_name' => $input['first_name'],
            'last_name'  => $input['last_name'],
            'email'      => $input['email'],
            'password'   => $input['password'],
            'status'     => false,
        ]);

        $expires = Carbon::now()->addMinutes(1440);
        $verificationUrl = URL::temporarySignedRoute(
            'user.verify', $expires, ['id' => $user->id]
        );

        $user->assignRole('basecamp');
      
        try {
            Log::info('📧 Starting email send for new user registration', [
                'user_id' => $user->id,
                'email' => $user->email,
                'verification_url' => $verificationUrl,
            ]);

            $oneSignal = new OneSignalService();

            $verifyBody = view('emails.verify-user-inline', [
                'user' => $user,
                'verificationUrl' => $verificationUrl,
            ])->render();

            Log::info('📧 Email body rendered, calling OneSignal', [
                'email' => $user->email,
                'body_length' => strlen($verifyBody),
            ]);

            $result = $oneSignal->registerEmailUserFallback($user->email, $user->id, [
                'subject' => 'Activate Your Tribe365 Account',
                'body'    => $verifyBody,
            ]);

            Log::info('✅ OneSignal verification email sent', [
                'email' => $user->email,
                'result' => $result,
            ]);            

        } catch (\Throwable $e) {
            Log::error('❌ OneSignal registration/email failed for new user', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error'   => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $user;
    }
}
