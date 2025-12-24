<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
}

