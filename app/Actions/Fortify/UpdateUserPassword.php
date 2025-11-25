<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and update the user's password.
     *
     * @param  array<string, string>  $input
     */
    public function update(User $user, array $input): void
    {
      
      
      	Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', 'min:6', 'confirmed'], // Added min:6
            'password_confirmation' => ['required'],
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
            'password.required' => __('The password field is required.'),
            'password.min' => __('The password must be at least 6 characters.'),
            'password.confirmed' => __('The password confirmation does not match.'),
            'password_confirmation.required' => __('The confirm password field is required.'),
        ])->validateWithBag('updatePassword');

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();
      
      
      
      
      
      
        //Validator::make($input, [
            //'current_password' => ['required', 'string', 'current_password:web'],
           // 'password' => $this->passwordRules(),
       // ], [
          //  'current_password.current_password' => __('The provided password does not match your current password.'),
      //  ])->validateWithBag('updatePassword');

        //$user->forceFill([
         //   'password' => Hash::make($input['password']),
      //  ])->save();
    }
}
