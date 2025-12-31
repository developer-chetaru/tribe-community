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

        // Assign basecamp role to all new users
        $user->assignRole('basecamp');
        
        // Refresh user to ensure role is saved
        $user->refresh();
        $user->load('roles');
        
        // Debug: Log role assignment
        Log::info('CreateNewUser - User ID: ' . $user->id . ', Role assigned: basecamp');
        Log::info('CreateNewUser - User roles after assignment: ' . $user->roles->pluck('name')->implode(', '));
      
        // For basecamp users, don't send activation email yet
        // Email will be sent after payment is completed
        // For non-basecamp users, send email immediately (if needed in future)

        return $user;
    }
}
