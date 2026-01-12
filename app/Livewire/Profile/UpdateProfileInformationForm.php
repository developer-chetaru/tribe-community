<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm as BaseUpdateProfileInformationForm;

class UpdateProfileInformationForm extends BaseUpdateProfileInformationForm
{
    /**
     * Prepare the component state.
     *
     * @return void
     */
    public function mount()
    {
        parent::mount();
        
        // Add timezone to state if not already present
        if (!isset($this->state['timezone'])) {
            $this->state['timezone'] = Auth::user()->timezone ?? '';
        }
    }

    /**
     * Delete the user's profile photo.
     *
     * @return void
     */
    public function deletePhoto(): void
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                session()->flash('error', 'User not found.');
                return;
            }
            
            // Delete the photo file from storage if it exists
            if ($user->profile_photo_path && Storage::disk('public')->exists($user->profile_photo_path)) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
            
            // Clear the photo path from database
            $user->profile_photo_path = null;
            $user->save();
            
            // Refresh the user property
            $this->user = $user->fresh();
            
            // Dispatch event to refresh the UI
            $this->dispatch('photo-deleted');
            
            session()->flash('message', 'Profile photo deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Error deleting profile photo: ' . $e->getMessage());
            session()->flash('error', 'Failed to delete profile photo. Please try again.');
        }
    }

    /**
     * COMMENTED OUT: Update user timezone from location (called by JavaScript)
     * Timezone should be set from user profile instead
     *
     * @param string $timezone
     * @return void
     */
    // public function updateTimezoneFromLocation($timezone)
    // {
    //     try {
    //         $user = Auth::user();
    //         
    //         if (!$user) {
    //             return;
    //         }
    //
    //         // Validate timezone
    //         if (empty($timezone) || !in_array($timezone, timezone_identifiers_list())) {
    //             Log::warning("Invalid timezone provided: {$timezone} for user {$user->id}");
    //             return;
    //         }
    //
    //         // Update timezone
    //         $user->timezone = $timezone;
    //         $user->save();
    //         
    //         // Update state
    //         $this->state['timezone'] = $timezone;
    //         
    //         // Refresh user property
    //         $this->user = $user->fresh();
    //         
    //         // Dispatch event to update Alpine.js
    //         $this->dispatch('timezone-updated', timezone: $timezone);
    //         
    //         Log::info("Updated timezone for user {$user->id} to '{$timezone}' from location");
    //     } catch (\Exception $e) {
    //         Log::error("Error updating user timezone: " . $e->getMessage());
    //     }
    // }

    /**
     * Render the component.
     *
     * @return \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
     */
    public function render()
    {
        return view('livewire.profile.update-profile-information-form');
    }
}

