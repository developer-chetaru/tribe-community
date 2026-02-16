<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Reflection;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Auth;

class ReflectionCreate extends Component
{
    use WithFileUploads;

    public $topic;
    public $message;
    public $image;

    public $alertType = '';
    public $alertMessage = '';
    public $reflectionAdded = false; // Track if reflection was successfully added

    public function mount()
    {
        $user = auth()->user();
        
        // Reflections is accessible to super_admin (via Universal Setting > HPTM) 
        // and organisation_user|organisation_admin|basecamp|director (via standalone menu)
        $allowedRoles = ['super_admin', 'organisation_user', 'organisation_admin', 'basecamp', 'director'];
        
        // Check if user has any of the allowed roles (handle case where role might not exist)
        $hasAccess = false;
        foreach ($allowedRoles as $role) {
            try {
                if ($user->hasRole($role)) {
                    $hasAccess = true;
                    break;
                }
            } catch (\Spatie\Permission\Exceptions\RoleDoesNotExist $e) {
                // Role doesn't exist in database, skip it
                continue;
            }
        }
        
        if (!$hasAccess) {
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

        $reflection = Reflection::create([
            'userId' => Auth::id(),
            'orgId' => Auth::user()->orgId,
            'topic' => $this->topic,
            'message' => $this->message,
            // 'image' => $imageName,
            'status' => 'new',
        ]);

        // Log activity
        try {
            ActivityLogService::log(
                'reflection',
                'created',
                "Created reflection: {$this->topic}",
                $reflection,
                null,
                [
                    'topic' => $this->topic,
                    'message' => substr($this->message, 0, 100), // First 100 chars
                    'status' => 'new',
                ]
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to log reflection creation activity: ' . $e->getMessage());
        }

        $this->alertType = 'success';
        $this->alertMessage = 'Reflection created successfully!';
        $this->reflectionAdded = true; // Mark that reflection was added

        // Reset form
        $this->reset(['topic', 'message', 'image']);
    }

    public function render()
    {
        return view('livewire.reflection-create')->layout('layouts.app');
    }
}