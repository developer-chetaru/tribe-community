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
use Illuminate\Support\Facades\DB;

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

        // Create user - let default status handle it, then update if needed
        $user = User::create([
            'first_name' => $input['first_name'] ?? '',
            'last_name'  => $input['last_name'] ?? '',
            'email'      => $input['email'],
            'password'   => $input['password'],
        ]);
        
        // Update status using DB raw to avoid type conversion issues
        // Use explicit cast to handle boolean column type
        try {
            DB::table('users')
                ->where('id', $user->id)
                ->update(['status' => DB::raw('0')]);
            $user->refresh();
        } catch (\Exception $e) {
            // If status update fails, log but continue (user is created)
            Log::warning('Failed to set user status to false', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        $expires = Carbon::now()->addMinutes(1440);
        $verificationUrl = URL::temporarySignedRoute(
            'user.verify', $expires, ['id' => $user->id]
        );

        // Assign basecamp role to all new users
        // Ensure role exists for both web and api guards (already created by RoleSeeder)
        try {
            $user->assignRole('basecamp');
            
            // Refresh user to ensure role is saved
            $user->refresh();
            $user->load('roles');
            
            // Debug: Log role assignment
            Log::info('CreateNewUser - User ID: ' . $user->id . ', Role assigned: basecamp');
            Log::info('CreateNewUser - User roles after assignment: ' . $user->roles->pluck('name')->implode(', '));
        } catch (\Spatie\Permission\Exceptions\RoleDoesNotExist $e) {
            // If role doesn't exist for current guard, create it
            $guardName = config('auth.defaults.guard', 'web');
            \Spatie\Permission\Models\Role::firstOrCreate([
                'name' => 'basecamp',
                'guard_name' => $guardName
            ]);
            
            // Try assigning again
            $user->assignRole('basecamp');
            Log::warning('CreateNewUser - Created missing basecamp role for guard: ' . $guardName . ' and assigned to user: ' . $user->id);
        } catch (\Exception $e) {
            Log::error('CreateNewUser - Failed to assign basecamp role: ' . $e->getMessage());
            // Don't fail user creation if role assignment fails
        }
      
        // For basecamp users, don't send activation email yet
        // Email will be sent after payment is completed
        // For non-basecamp users, send email immediately (if needed in future)

        return $user;
    }
}
