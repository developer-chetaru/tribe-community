<?php

namespace App\Livewire;

use Livewire\Component;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Department;
use App\Models\AllDepartment;
use App\Models\Office;
use Illuminate\Support\Facades\Password; 
use App\Notifications\CustomResetPasswordNotification;
use Illuminate\Support\Facades\Auth;
use App\Services\BrevoService;
use App\Services\OneSignalService;

class AddStaff extends Component
{
    public $organisationId;

    // Dropdown data
    public $allDepartments = [];
    public $offices = [];

    // Form fields
    public $first_name;
    public $last_name;
    public $email;
    public $department;
    public $office;
    public $phone;
  	public $country_code = '+91';
  	public $organisationName;

    public function mount($id)
    {
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        $this->organisationId = $id;
		
        // fetch offices & departments
        $this->allDepartments = AllDepartment::all();
        $this->offices = Office::where('organisation_id', $this->organisationId)->get();
  		$organisation = Organisation::find($this->organisationId);
        $this->organisationName = $organisation ? $organisation->name : null;
    }

    public function saveEmployee()
    {
        $validatedData = $this->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
             'email'      => 'required|string|max:255|unique:users,email',
            'department' => 'required|integer',
            'office'     => 'required|integer',
            'phone'      => 'required|string|max:15',
          	'country_code' => 'required|string|max:5',
        ]);

        $organisation = Organisation::findOrFail($this->organisationId);

        // check if user already exists
        $existingUser = User::where('orgId', $organisation->id)
                            ->where('email', $this->email)
                            ->first();

        if (!$existingUser) {
            // create department
            $department = Department::create([
                'organisation_id'   => $organisation->id,
                'office_id'         => $this->office,
                'all_department_id' => $this->department,
                'status'            => 1,
                'numberOfEmployees' => 1,
                'department'        => 'testing',
            ]);
			
          	$departmentId = $department->id;
            
          // create user
            $user = User::create([
                'orgId'        => $organisation->id,
                'first_name'   => $this->first_name,
                'last_name'    => $this->last_name,
                'email'        => $this->email,
                'password'     => bcrypt('sourabh@chetaru.com'),
                'departmentId' => $departmentId,
                'officeId'     => $this->office,
                'phone'        => $this->phone,
              	'country_code' => $this->country_code,
              	'status'       => true,
            ]);
    $token = Password::broker('users')->createToken($user);

// send custom notification with logged-in user
$inviterName = Auth::user()->first_name; 
$user->notify(new CustomResetPasswordNotification($token, $organisation->name, $inviterName));
        } else {
            $user = $existingUser;
        }

      
        // assign role
        if (!$user->hasRole('organisation_user')) {
            $user->assignRole('organisation_user');
        }

		try {
            $oneSignal = new OneSignalService();
            $oneSignal->registerEmailUser($user->email, $user->id);
        } catch (\Throwable $e) {
            \Log::error('❌ OneSignal registration failed for new user', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
		
        // Log activity
        try {
            ActivityLogService::logUserCreated($user, [
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'organisation_id' => $organisation->id,
                'organisation_name' => $organisation->name,
                'department_id' => $departmentId,
                'office_id' => $this->office,
                'role' => 'organisation_user',
            ]);
        } catch (\Exception $e) {
            \Log::warning('Failed to log staff creation activity: ' . $e->getMessage());
        }
		
        // reset form fields
        $this->reset(['first_name', 'last_name', 'email', 'department', 'office', 'phone','country_code']);

        // redirect with success
        session()->flash('success', 'Staff added successfully');
      

    }
   public function resetForm()
    {
        $this->reset(['first_name', 'last_name', 'email', 'department', 'office', 'phone','country_code']);
    }

    public function render()
    {
        return view('livewire.add-staff')->layout('layouts.app');
    }
}
