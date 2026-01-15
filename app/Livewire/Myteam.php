<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Office;
use App\Models\Department;
use App\Models\Organisation;
use App\Exports\StaffExport;
use App\Services\SubscriptionService;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\UserLeave;
use Carbon\Carbon;

class Myteam extends Component
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
	public $selectedUserId = null;

public $selectedStaffId;
public $selectedStaff;
  	public $leaveStartDate;
	public $leaveEndDate;
  	public $Myteam;
 

	public function mount($id = null)
	{
        $user = auth()->user();
        
        // Block super_admin - this page is not for them
        if ($user->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. This page is not available for administrators.');
        }

        // Check subscription status for organization users
        if ($user->orgId && !$user->hasRole('super_admin')) {
            $subscriptionService = new SubscriptionService();
            $subscriptionStatus = $subscriptionService->getSubscriptionStatus($user->orgId);
            
            if (!$subscriptionStatus['active']) {
                abort(403, 'Your organization\'s subscription has expired. Please contact your director to renew the subscription.');
            }
        }

        // Check if user has required role (organisation_user, organisation_admin, or basecamp)
        if (!$user->hasAnyRole(['organisation_user', 'organisation_admin', 'basecamp', 'director'])) {
            abort(403, 'Unauthorized access. This page is only available for organisation users.');
        }

        // Check if user has orgId (required for My Teammates)
        if (!$user->orgId) {
            abort(403, 'Unauthorized access. You must be part of an organisation to view teammates.');
        }

    	$this->organisationId = $id;

    	if($id) {
        	$this->organisation = Organisation::findOrFail($id);
        	$this->organisationName = $this->organisation->name;
    	}
	}

	public function closeFilter()
	{
      	$this->filterOffice = [];
        $this->filterDepartment = [];
    	$this->showFilter = false;
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
  
public function render()
{
    $today = Carbon::today('Asia/Kolkata')->toDateString();

    $currentUserOrgId = auth()->user()?->orgId;

    $staffQuery = User::with(['organisation', 'roles', 'department']) 
        ->where('orgId', $currentUserOrgId)
        ->where('id', '!=', auth()->id())
        ->when($this->search, function($query) {
            $query->where(function($q) {
                $q->where('first_name', 'like', '%' . $this->search . '%')
                  ->orWhere('last_name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        })
       ->when($this->filterOffice && count($this->filterOffice), function($query) {
    $query->whereIn('officeId', $this->filterOffice);
})

        ->when($this->filterDepartment && count($this->filterDepartment), function($query) {
            $query->whereIn('departmentId', $this->filterDepartment);
        });
	
    $staffList = $staffQuery->paginate(10);

    // Get unique departments - fix for duplicate entries
    $departments = User::where('orgId', $currentUserOrgId)
                       ->whereNotNull('departmentId')
                       ->distinct()
                       ->pluck('departmentId')
                       ->filter()
                       ->unique()
                       ->values();
    
    // Fetch departments with their allDepartment relationship
    $departments = Department::whereIn('id', $departments)
                              ->with('allDepartment')
                              ->get()
                              ->filter(function($dept) {
                                  return $dept->allDepartment !== null;
                              })
                              ->unique(function($dept) {
                                  // Use all_department_id to ensure uniqueness by AllDepartment name
                                  return $dept->all_department_id;
                              })
                              ->values();

    $organisationName = optional($staffList->first()?->organisation)->name 
                        ?? optional(Organisation::find($currentUserOrgId))->name;
	
    return view('livewire.myteam', [
        'staffList'        => $staffList,
        'offices'          => Office::where('organisation_id', $currentUserOrgId)->get(),
        'departments'      => $departments,
        'organisationName' => $organisationName,
    ])->layout('layouts.app');
}


}
