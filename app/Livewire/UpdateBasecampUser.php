<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\User;
use App\Services\BrevoService;
use Illuminate\Support\Facades\Storage;

class UpdateBasecampUser extends Component
{
    use WithFileUploads;

    public $userId;
    public $first_name;
    public $last_name;
    public $email;
    public $phone;
    public $country_code;
    public $status;

    public $profile_photo;   
    public $existingPhoto;   
    public $previewPhoto;    

    public function mount($id)
    {
        $user = User::findOrFail($id);

        $this->userId = $user->id;
        $this->first_name = $user->first_name;
        $this->last_name = $user->last_name;
        $this->email = $user->email;
        $this->phone = $user->phone;
        $this->country_code = $user->country_code ?: '+1';
        $this->status = $user->status;

        // Load existing photo
        $this->existingPhoto = $user->profile_photo_path ?? null;
    }

    public function updatedProfilePhoto()
    {
        // Clear previous JS preview
        $this->previewPhoto = null;
    }

    public function saveUser()
    {
        $validatedData = $this->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email' => 'required|string|max:255|unique:users,email,' . $this->userId,
            'phone' => 'nullable|string|max:20',
            'country_code' => 'nullable|string|max:10',
            'status' => 'required|integer|in:0,1',
        ]);

        $user = User::findOrFail($this->userId);

        // Update user
        $user->update([
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'country_code' => $this->country_code,
            'status' => $this->status,
        ]);

        // Handle profile photo upload
        if ($this->profile_photo) {
            // Delete old photo if exists
            if ($user->profile_photo_path && Storage::disk('public')->exists($user->profile_photo_path)) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            // Store new photo in 'profile-photos' folder on 'public' disk
            $path = $this->profile_photo->store('profile-photos', 'public');

            // Update user record with relative path
            $user->update(['profile_photo_path' => $path]);

            // Reset Livewire properties
            $this->existingPhoto = $path;
            $this->profile_photo = null;
            $this->previewPhoto = null;
        }

        // Update Brevo contact
        try {
            $brevo = new BrevoService();
            $brevo->addContact($user->email, $user->first_name, $user->last_name);
        } catch (\Exception $e) {
            // Log error but don't fail the update
            \Log::warning('Brevo contact update failed: ' . $e->getMessage());
        }

        session()->flash('message', 'User updated successfully!');
        session()->flash('type', 'success');
    }

    public function resetForm()
    {
        $this->mount($this->userId);
        $this->previewPhoto = null;
        $this->profile_photo = null;
    }

    public function render()
    {
        return view('livewire.update-basecamp-user')->layout('layouts.app');
    }
}

