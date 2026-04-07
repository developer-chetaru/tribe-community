<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Office;
use App\Models\Department;
use App\Models\Organisation;
use App\Exports\StaffExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\UserLeave;
use Carbon\Carbon;

class Staff extends Component
{
    use WithPagination;

    public $organisationId;
    public $organisation;
    public $organisationName;
 	protected $paginationTheme = 'tailwind'; 
    public $search = '';
    public $filterOffice = [];
    public $filterDepartment = [];
    public $showFilter = false;
    public $activeTab = 'office'; 
	public $showTeamLeadModal = false;
	public $showRemoveTeamLeadModal = false;
	public $showDirectorModal = false;
	public $selectedUserId = null;

public $selectedStaffId;
public $selectedStaff;
  	public $leaveStartDate;
	public $leaveEndDate;
 

	public function openTeamLeadModal($userId)
	{
    	$this->selectedUserId = $userId;
    	$this->showTeamLeadModal = true;
        $this->selectedStaff = User::with('office')->findOrFail($userId);
	}

	public function openRemoveTeamLeadModal($userId)
	{
    	$this->selectedUserId = $userId;
    	$this->showRemoveTeamLeadModal = true;
	}

	public function closeTeamLeadModal()
	{
    	$this->showTeamLeadModal = false;
    	$this->selectedUserId = null;
        $this->selectedStaff = null;
	}
  
	public function makeTeamLead()
	{
    	$user = User::with(['office', 'department'])->findOrFail($this->selectedUserId);

    	if (is_null($user->officeId)) {
        	session()->flash('error', 'Office is not assigned, so a Team Lead cannot be created.');
        	return;
    	}

    	$existingLeads = User::role('organisation_admin')
        	->where('officeId', $user->officeId)
        	->where('id', '!=', $user->id)
        	->get();

    	foreach ($existingLeads as $lead) {
        	$lead->syncRoles(['organisation_user']);
    	}

    	$user->syncRoles(['organisation_admin']); 

    	session()->flash(
            'teamLeadMessage',
            '<strong>' . e($user->first_name) . '</strong> is now the new Team Lead for ' . e($user->office->name) . '.'
        );
    	$this->closeTeamLeadModal();
	}

	public function closeFilter()
	{
      	$this->filterOffice = [];
        $this->filterDepartment = [];
    	$this->showFilter = false;
	}

	public function removeTeamLead($userId)
	{
    	$user = User::findOrFail($userId);
    	$user->syncRoles(['organisation_user']);
    	session()->flash('success', $user->first_name . ' is now Staff.');
	}

	public function openDirectorModal($userId)
	{
    	$this->selectedUserId = $userId;
    	$this->showDirectorModal = true;
        $this->selectedStaff = User::with('office')->findOrFail($userId);
	}

	public function closeDirectorModal()
	{
    	$this->showDirectorModal = false;
    	$this->selectedUserId = null;
        $this->selectedStaff = null;
	}
  
	public function makeDirector()
	{
    	$user = User::with(['office', 'department'])->findOrFail($this->selectedUserId);

    	if (is_null($user->officeId)) {
        	session()->flash('error', 'Office is not assigned, so a Director cannot be created.');
        	return;
    	}

    	// Remove director role from existing director(s) in the same organisation
    	$existingDirectors = User::role('director')
        	->where('orgId', $user->orgId)
        	->where('id', '!=', $user->id)
        	->get();

    	foreach ($existingDirectors as $director) {
        	$director->syncRoles(['organisation_user']);
    	}

    	$user->syncRoles(['director']);

    	session()->flash(
            'directorMessage',
            '<strong>' . e($user->first_name) . '</strong> is now the Director.'
        );
    	$this->closeDirectorModal();
	}

	public function removeDirector($userId)
	{
    	$user = User::findOrFail($userId);
    	$user->syncRoles(['organisation_user']);
    	session()->flash('success', $user->first_name . ' is now Staff.');
	}
  
	public function mount($id)
	{
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

    	$this->organisationId = $id;
        $this->organisation = Organisation::findOrFail($id);
        $this->organisationName = $this->organisation->name;
    }

    public function updated($field)
    {
        // Only reset page for search, not for filters (filters apply on button click)
        if ($field === 'search') {
            $this->resetPage();
        }
    }

    public function resetFilters()
    {
        $this->filterOffice = [];
        $this->filterDepartment = [];
        $this->resetPage();
    }

    public function saveFilters()
    {
        $this->showFilter = false;
        $this->resetPage();
    }
  
	public function getStaffListProperty()
{
    return User::with(['office', 'department', 'roles']) 
        ->where('orgId', $this->organisationId)
        // Exclude users who have the 'basecamp' role
        ->whereDoesntHave('roles', function($query) {
            $query->where('name', 'basecamp');
        })
        ->when($this->search, fn($q) => $q->where(function($query){
            $query->where('first_name', 'like', "%{$this->search}%")
                  ->orWhere('last_name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%");
        }))
        ->when(!empty($this->filterOffice), fn($q) => $q->whereIn('officeId', array_map('intval', $this->filterOffice)))
        ->when(!empty($this->filterDepartment), function($q) {
            // filterDepartment now contains all_department_id values
            // Find all Department records that have these all_department_id values
            $selectedAllDepartmentIds = array_map('intval', $this->filterDepartment);
            
            $departmentIds = \App\Models\Department::whereIn('all_department_id', $selectedAllDepartmentIds)
                ->pluck('id')
                ->toArray();
            
            return $q->whereIn('departmentId', $departmentIds);
        })
        ->orderBy('id', 'desc') 
        ->paginate(12);
}

  
public function deleteFromBasecamp($id)
{
    $user = User::findOrFail($id);

    // Remove current roles if needed
    $currentRoles = $user->getRoleNames()->toArray();
    // Optionally remove only specific roles
    $user->syncRoles(['basecamp']); // Change role to basecamp

    session()->flash('message', $user->first_name . ' role has been changed to Basecamp.');
}
public function delete($id)
	{
    	$user = User::findOrFail($id);
    	$user->delete();
	}

    public function exportStaff()
    {
        return Excel::download(new StaffExport($this->organisationId), 'staff_list.xlsx');
    }

public function updatedSelectedStaffId($value)
{
    $this->selectedStaff = User::with(['office', 'department.allDepartment'])->find($value);
}

public function applyLeave()
{
    $this->validate([
        'leaveStartDate' => 'required|date|after_or_equal:today',
        'leaveEndDate'   => 'required|date|after_or_equal:leaveStartDate',
    ]);

    $user = User::find($this->selectedStaffId);

    if (!$user) {
        session()->flash('error', 'User not found.');
        return;
    }

    UserLeave::create([
        'user_id'      => $user->id,
        'start_date'   => \Carbon\Carbon::parse($this->leaveStartDate)->toDateString(),
        'end_date'     => \Carbon\Carbon::parse($this->leaveEndDate)->toDateString(),
        'resume_date'  => \Carbon\Carbon::parse($this->leaveEndDate)->addDay()->toDateString(),
        'leave_status' => 1,
    ]);

    $user->onLeave = 1;
    $user->save();

    $this->leaveStartDate = null;
    $this->leaveEndDate = null;
    $this->selectedStaffId = null;
    $this->selectedStaff = null;

    $this->dispatch('close-leave-modal');
}
  
 public function changeLeaveStatus($staffId = null)
{
    $userId = $staffId ?? auth()->id(); 

    $userLeave = \App\Models\UserLeave::where('user_id', $userId)
        ->where('leave_status', 1)
        ->orderBy('created_at', 'desc')
        ->first();

    if ($userLeave) {
        $userLeave->update([
            'resume_date'  => now()->toDateString(),
            'leave_status' => 0,
            'updated_at'   => now(),
        ]);

        \DB::table('users')
            ->where('id', $userId)
            ->where('status', '1')
            ->update([
                'onLeave'    => 0,
                'updated_at' => now(),
            ]);
    }

    $this->dispatch('close-leave-modal');
}


 	public function render()
	{
	    $today = Carbon::today('Asia/Kolkata')->toDateString();
    	$departmentIds = User::where('orgId', $this->organisationId)
                         ->pluck('departmentId')
                         ->unique()
                         ->toArray();
	 $onLeaveToday = UserLeave::where('user_id', auth()->id())
        ->whereDate('start_date', '<=', $today)
        ->whereDate('end_date', '>=', $today)
        ->where('leave_status', 1)
        ->exists();

    	return view('livewire.staff', [
        	'staffList'   => $this->staffList,
        	'offices'     => Office::where('organisation_id', $this->organisationId)->get(),
        	'departments' => Department::whereIn('id', $departmentIds)->with('allDepartment')->get(),
	   'onLeaveToday' => $onLeaveToday, 
    	])->layout('layouts.app');
	}
}