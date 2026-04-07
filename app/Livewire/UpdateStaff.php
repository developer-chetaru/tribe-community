<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\User;
use App\Models\Organisation;
use App\Models\AllDepartment;
use App\Models\Office;
use App\Services\BrevoService;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Storage;
use App\Models\Department;



class UpdateStaff extends Component
{
    use WithFileUploads;

    public $staffId;
    public $first_name;
    public $last_name;
    public $email;
    public $department;
    public $office;
    public $phone;
  	public $country_code; 
    public $organisationId;
    public $organisationName;

    public $allDepartments = [];
    public $offices = [];

    public $profile_photo;   
    public $existingPhoto;   
    public $previewPhoto;    

public function mount($id)
{
    // Check if user has super_admin role
    if (!auth()->user()->hasRole('super_admin')) {
        abort(403, 'Unauthorized access. Admin privileges required.');
    }

    $user = User::with('department')->findOrFail($id);

    $this->staffId       = $user->id;
    $this->first_name    = $user->first_name;
    $this->last_name     = $user->last_name;
    $this->email         = $user->email;
    $this->phone         = $user->phone;
  	$this->country_code   = $user->country_code ?: '+91';
    $this->organisationId = $user->orgId;

    $organisation = Organisation::find($this->organisationId);
    $this->organisationName = $organisation ? $organisation->name : null;

 $this->allDepartments = AllDepartment::orderBy('name', 'asc')->get();

    $this->offices = Office::where('organisation_id', $this->organisationId)->get();

    // Set selected office
    $this->office = $user->officeId;

    // Set selected department using all_department_id
    $this->department = $user->department ? $user->department->all_department_id : null;

    // Load existing photo
    $this->existingPhoto = $user->profile_photo_path ?? null;
}

    public function updatedProfilePhoto()
    {
        // Clear previous JS preview
        $this->previewPhoto = null;
    }

 public function saveEmployee()
{
    $validatedData = $this->validate([
        'first_name' => 'required|string|max:255',
        'last_name'  => 'required|string|max:255',
       'email' => 'required|string|max:255|unique:users,email,' . $this->staffId,

        'department' => 'required|integer',  
        'office'     => 'required|integer',
        'phone'      => 'required|string|max:15',
      	'country_code' => 'required|string|max:5',
    ]);

    $user = User::findOrFail($this->staffId);
    
    // Capture old values for logging
    $oldValues = [
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'email' => $user->email,
        'phone' => $user->phone,
        'country_code' => $user->country_code,
        'officeId' => $user->officeId,
        'departmentId' => $user->departmentId,
    ];

    $department = Department::firstOrCreate(
        [
            'organisation_id'   => $this->organisationId,
            'office_id'         => $this->office,
            'all_department_id' => $this->department,
        ],
        [
            'department'        => 'Updated Dept', 
            'status'            => 1,
            'numberOfEmployees' => 1,
        ]
    );
	$departmentId = $department->id;
    // update user
    $user->update([
        'first_name'   => $this->first_name,
        'last_name'    => $this->last_name,
        'email'        => $this->email,
        'phone'        => $this->phone,
      	'country_code' => $this->country_code,
        'officeId'     => $this->office,
        'departmentId' => $departmentId,
    ]);

  
 if ($this->profile_photo) {
    // Delete old photo if exists
    if ($user->profile_photo_path && Storage::disk('public')->exists($user->profile_photo_path)) {
        Storage::disk('public')->delete($user->profile_photo_path);
    }

    // Store new photo in 'profile-photos' folder on 'public' disk
    // Laravel automatically generates a unique filename
    $path = $this->profile_photo->store('profile-photos', 'public');

    // Update user record with relative path
    $user->update(['profile_photo_path' => $path]);

    // Reset Livewire properties
    $this->existingPhoto = $path;
    $this->profile_photo = null;
    $this->previewPhoto = null;
}


  
    $brevo = new BrevoService();
    $brevo->addContact($user->email, $user->first_name, $user->last_name);

    // Log activity
    try {
        $newValues = [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'country_code' => $user->country_code,
            'officeId' => $user->officeId,
            'departmentId' => $user->departmentId,
        ];
        ActivityLogService::logUserUpdated($user, $oldValues, $newValues);
    } catch (\Exception $e) {
        \Log::warning('Failed to log staff update activity: ' . $e->getMessage());
    }

    session()->flash('success', 'Staff updated successfully');
}

    public function resetForm()
    {
        $this->mount($this->staffId);
        $this->previewPhoto = null;
        $this->profile_photo = null;
    }

    public function render()
    {
        return view('livewire.update-staff')->layout('layouts.app');
    }
}
