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
    public $timezone;
    public $status;

    public $profile_photo;   
    public $existingPhoto;   
    public $previewPhoto;    

    public function mount($id)
    {
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        $user = User::findOrFail($id);

        $this->userId = $user->id;
        $this->first_name = $user->first_name;
        $this->last_name = $user->last_name;
        $this->email = $user->email;
        $this->phone = $user->phone;
        $this->country_code = $user->country_code ?: '+1';
        $this->timezone = $user->timezone ?: 'Asia/Kolkata';
        
        // Convert status to legacy format for dropdown (for backward compatibility)
        // Check if user has verified email
        $this->status = $user->email_verified_at ? '1' : '0';
        
        \Illuminate\Support\Facades\Log::info('UpdateBasecampUser - Mount', [
            'user_id' => $user->id,
            'db_status' => $user->status,
            'db_email_verified_at' => $user->email_verified_at,
            'dropdown_status' => $this->status,
        ]);

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
        \Illuminate\Support\Facades\Log::info('UpdateBasecampUser - saveUser called', [
            'user_id' => $this->userId,
            'timezone_before_validation' => $this->timezone,
            'all_properties' => [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'email' => $this->email,
                'phone' => $this->phone,
                'country_code' => $this->country_code,
                'timezone' => $this->timezone,
                'status' => $this->status,
            ]
        ]);

        $validatedData = $this->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email' => 'required|string|max:255|unique:users,email,' . $this->userId,
            'phone' => 'nullable|string|max:20',
            'country_code' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:50|in:' . implode(',', timezone_identifiers_list()),
            'status' => 'required|in:0,1',
        ]);

        \Illuminate\Support\Facades\Log::info('UpdateBasecampUser - Validation passed', [
            'validated_timezone' => $validatedData['timezone'] ?? null,
            'timezone_property' => $this->timezone,
        ]);

        $user = User::findOrFail($this->userId);

        // Convert legacy status (0/1) to new ENUM status
        if ($this->status == '1') {
            // Verified - set email_verified_at and status
            $newStatus = 'active_verified';
            $emailVerifiedAt = $user->email_verified_at ?: now();
        } else {
            // Unverified - remove email_verified_at and set status
            $newStatus = 'active_unverified';
            $emailVerifiedAt = null;
        }

        \Illuminate\Support\Facades\Log::info('UpdateBasecampUser - Status conversion', [
            'user_id' => $this->userId,
            'dropdown_status' => $this->status,
            'new_status' => $newStatus,
            'email_verified_at' => $emailVerifiedAt,
            'old_status' => $user->status,
            'old_email_verified_at' => $user->email_verified_at,
        ]);

        // Update user
        $updateData = [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'country_code' => $this->country_code,
            'timezone' => $this->timezone ?: null, // Ensure null if empty
            'status' => $newStatus,
            'email_verified_at' => $emailVerifiedAt,
        ];
        
        \Illuminate\Support\Facades\Log::info('UpdateBasecampUser - Updating user', [
            'user_id' => $this->userId,
            'update_data' => $updateData,
        ]);
        
        $user->update($updateData);
        
        // Refresh and verify update
        $user->refresh();
        
        \Illuminate\Support\Facades\Log::info('UpdateBasecampUser - After update', [
            'user_id' => $this->userId,
            'updated_status' => $user->status,
            'updated_email_verified_at' => $user->email_verified_at,
            'updated_timezone' => $user->timezone,
            'component_timezone' => $this->timezone,
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

        // Refresh timezone property after update to ensure it's synced
        $freshUser = $user->fresh();
        $this->timezone = $freshUser->timezone ?: 'Asia/Kolkata';
        
        \Illuminate\Support\Facades\Log::info('UpdateBasecampUser - Timezone synced', [
            'user_id' => $this->userId,
            'component_timezone' => $this->timezone,
            'db_timezone' => $freshUser->timezone,
        ]);
        
        session()->flash('message', 'User updated successfully!');
        session()->flash('type', 'success');
    }

    public function resetForm()
    {
        $this->mount($this->userId);
        $this->previewPhoto = null;
        $this->profile_photo = null;
    }
    
    public function updatedTimezone($value)
    {
        // Log timezone updates for debugging
        \Illuminate\Support\Facades\Log::info('UpdateBasecampUser - Timezone changed', [
            'user_id' => $this->userId,
            'new_timezone' => $value,
            'current_timezone' => $this->timezone,
        ]);
        
        // Ensure timezone is synced when changed
        $this->timezone = $value;
    }

    public function render()
    {
        return view('livewire.update-basecamp-user')->layout('layouts.app');
    }
}

