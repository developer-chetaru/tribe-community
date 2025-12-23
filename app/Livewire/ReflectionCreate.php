<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Reflection;
use Illuminate\Support\Facades\Auth;

class ReflectionCreate extends Component
{
    use WithFileUploads;

    public $topic;
    public $message;
    public $image;

    public $alertType = '';
    public $alertMessage = '';

    public function mount()
    {
        $user = auth()->user();
        
        // Reflections is accessible to super_admin (via Universal Setting > HPTM) 
        // and organisation_user|organisation_admin|basecamp (via standalone menu)
        $allowedRoles = ['super_admin', 'organisation_user', 'organisation_admin', 'basecamp'];
        
        if (!$user->hasAnyRole($allowedRoles)) {
            abort(403, 'Unauthorized access. This page is only available for authorised users.');
        }
    }

    public function submit()
    {
        $this->validate([
            'topic' => 'required|string|max:255',
            'message' => 'required|string',
            // 'image' => 'nullable|image|max:2048',
        ]);

        // $imageName = null;
        // if ($this->image) {
        //     $imageName = 'reflection_'.time().'.'.$this->image->getClientOriginalExtension();
        //     $this->image->storeAs('public/hptm_files', $imageName);
        // }

        Reflection::create([
            'userId' => Auth::id(),
            'orgId' => Auth::user()->orgId,
            'topic' => $this->topic,
            'message' => $this->message,
            // 'image' => $imageName,
            'status' => 'new',
        ]);

        $this->alertType = 'success';
        $this->alertMessage = 'Reflection created successfully!';

        // Reset form
        $this->reset(['topic', 'message', 'image']);
    }

    public function render()
    {
        return view('livewire.reflection-create')->layout('layouts.app');
    }
}