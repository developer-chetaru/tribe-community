<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
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
}

