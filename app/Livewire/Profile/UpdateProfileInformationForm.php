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
        
        // Set default country code to UK (+44) if not already set or if it's +1 (US)
        $userCountryCode = Auth::user()->country_code ?? null;
        if (!isset($this->state['country_code']) || empty($this->state['country_code']) || 
            $this->state['country_code'] === '+1' || $this->state['country_code'] === '1' ||
            $userCountryCode === '+1' || $userCountryCode === '1') {
            $this->state['country_code'] = '+44';
        } else {
            $this->state['country_code'] = $userCountryCode ?? '+44';
        }
        
        // Add working days to state for basecamp users (users without orgId) - default: Monday-Friday = true, Saturday-Sunday = false
        $user = Auth::user();
        if (!$user->orgId) {
            // Set defaults: Monday-Friday = true, Saturday-Sunday = false
            // Only use user's existing values if they're explicitly set (not null)
            $this->state['working_monday'] = ($user->working_monday !== null) ? (bool)$user->working_monday : true;
            $this->state['working_tuesday'] = ($user->working_tuesday !== null) ? (bool)$user->working_tuesday : true;
            $this->state['working_wednesday'] = ($user->working_wednesday !== null) ? (bool)$user->working_wednesday : true;
            $this->state['working_thursday'] = ($user->working_thursday !== null) ? (bool)$user->working_thursday : true;
            $this->state['working_friday'] = ($user->working_friday !== null) ? (bool)$user->working_friday : true;
            $this->state['HI_include_saturday'] = ($user->HI_include_saturday !== null) ? (bool)$user->HI_include_saturday : false;
            $this->state['HI_include_sunday'] = ($user->HI_include_sunday !== null) ? (bool)$user->HI_include_sunday : false;
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
            
            // Refresh the user property immediately from database
            $this->user = Auth::user()->fresh();
            
            // Reload the component state to refresh all properties
            $this->mount();
            
            // Force component refresh to update the view immediately
            $this->dispatch('$refresh');
            
            // Dispatch browser events for navigation menu and other components
            $this->dispatch('photo-deleted');
            $this->dispatch('profile-photo-deleted');
            
            // Also dispatch to window for Alpine.js listeners
            $this->js('window.dispatchEvent(new CustomEvent("profile-photo-deleted"))');
            $this->js('window.dispatchEvent(new CustomEvent("photo-deleted"))');
            
            // Force page reload to ensure all components refresh
            $this->js('setTimeout(() => { window.location.reload(); }, 500)');
            
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

