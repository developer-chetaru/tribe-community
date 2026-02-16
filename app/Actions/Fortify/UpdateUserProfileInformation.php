<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Illuminate\Support\Facades\Storage;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
          	'country_code' => ['nullable', 'string', 'max:10'],
            'phone'      => ['nullable', 'string', 'max:20'],
            'timezone'   => ['nullable', 'string', 'max:50', Rule::in(timezone_identifiers_list())],
            'photo'      => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
        ];
        
        // Add working days validation for basecamp users (users without orgId)
        if (!$user->orgId) {
            $rules['working_monday'] = ['nullable', 'boolean'];
            $rules['working_tuesday'] = ['nullable', 'boolean'];
            $rules['working_wednesday'] = ['nullable', 'boolean'];
            $rules['working_thursday'] = ['nullable', 'boolean'];
            $rules['working_friday'] = ['nullable', 'boolean'];
            $rules['HI_include_saturday'] = ['nullable', 'boolean'];
            $rules['HI_include_sunday'] = ['nullable', 'boolean'];
        }
        
        Validator::make($input, $rules)->validateWithBag('updateProfileInformation');

        // Handle profile photo upload
        if (isset($input['photo'])) {
            $this->updateProfilePhoto($user, $input['photo']);
        }

        // Handle email verification if changed
        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $input);
        } else {
            // Update basic info
            $updateData = [
                'first_name' => $input['first_name'],
                'last_name'  => $input['last_name'],
                'email'      => $input['email'],
              	'country_code' => $input['country_code'] ?? '+44',
                'phone'      => $input['phone'] ?? null,
                'timezone'   => $input['timezone'] ?? null,
            ];
            
            // Add working days for basecamp users (users without orgId)
            if (!$user->orgId) {
                $updateData['working_monday'] = $input['working_monday'] ?? true;
                $updateData['working_tuesday'] = $input['working_tuesday'] ?? true;
                $updateData['working_wednesday'] = $input['working_wednesday'] ?? true;
                $updateData['working_thursday'] = $input['working_thursday'] ?? true;
                $updateData['working_friday'] = $input['working_friday'] ?? true;
                $updateData['HI_include_saturday'] = $input['HI_include_saturday'] ?? false;
                $updateData['HI_include_sunday'] = $input['HI_include_sunday'] ?? false;
            }
            
            $user->forceFill($updateData)->save();
        }
        
        // Update OneSignal tags after profile update (especially for timezone)
        try {
            $oneSignal = new \App\Services\OneSignalService();
            $user->refresh(); // Ensure we have latest data
            $oneSignal->setUserTagsOnLogin($user);
            \Illuminate\Support\Facades\Log::info("OneSignal tags updated after profile update for user: " . $user->id);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('OneSignal tag update failed after profile update', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update the given user's profile photo.
     */
    protected function updateProfilePhoto(User $user, $photo)
    {
        // Delete old profile photo if exists
        if ($user->profile_photo_path && Storage::disk('public')->exists($user->profile_photo_path)) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        // Generate unique filename
        $filename = time() . '_' . $photo->getClientOriginalName();

        // Save file to 'profile-preview' folder
        $path = $photo->storeAs('profile-preview', $filename, 'public');

        // Update user record
        $user->profile_photo_path = $path;
        $user->save();
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $user->forceFill([
            'first_name' => $input['first_name'],
            'last_name'  => $input['last_name'],
            'email'      => $input['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
