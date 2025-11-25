<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Office as OfficeModel;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class Office extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind'; 
    public $organisationId;
    public $organisationName;
    public $organisationImage;
    public $headOffice;
    public $branches; 
    public $search = '';
    public $showFilter = false;
    public $activeTab = 'country';
    public $country = [];
    public $city = [];
    public $turnover = [];
    public $visibility = [];
    public $countries = [];
    public $cities = [];
    public $users = [];
    public $teamLeads = [];
 	public $officeStaff = [];
    public $officesList = [];
    public $transferOfficeId = null;
	public $deleteId;
  	public $totalOffices;
	public $transferOfficeIds = []; 
  
    /**
     * Mount component with organisation ID
     */
    public function mount($id)
    {
        $this->organisationId = $id;
        $organisation = Organisation::find($id);
        $this->organisationName = $organisation?->name;
        $this->organisationImage = $organisation?->image;

   
        $this->countries = OfficeModel::where('organisation_id', $this->organisationId)
                          ->pluck('country')->unique()->filter()->values()->toArray();
   $this->cities = OfficeModel::where('organisation_id', $this->organisationId)
    ->pluck('city')
    ->filter()
    ->map(fn($city) => ucfirst(strtolower($city))) 
    ->unique()
    ->values()
    ->toArray();


        $this->loadOffices();
        $this->loadUsers();
        $this->loadTeamLeads();
       $this->loadData();
$this->totalOffices = OfficeModel::where('organisation_id', $organisation->id)->count();

    }

    /**
     * Load offices with filters & pagination
     */
public function loadOffices()
{
    $this->headOffice = OfficeModel::where('organisation_id', $this->organisationId)
        ->where('is_head_office', true)
        ->first();

    return OfficeModel::where('organisation_id', $this->organisationId)
        ->where('is_head_office', false)
        ->when($this->search, fn($q) => 
            $q->where('name', 'like', "%{$this->search}%")
        )
        ->when(!empty($this->country), fn($q) => 
            $q->whereIn('country', $this->country)
        )
        ->when(!empty($this->city), fn($q) => 
            $q->whereIn('city', $this->city)
              ->distinct('city')  
        )
        ->orderBy('name', 'asc')
        ->paginate(6);
}



    /**
     * Load all users of organisation
     */
    public function loadUsers()
    {
        $this->users = User::where('orgId', $this->organisationId)
                        ->with('roles') 
                        ->get();
    }

public function transferStaff()
{
    if (empty($this->transferOfficeIds)) {
        $this->dispatch('alert', ['type' => 'error', 'message' => 'Please select at least one office to transfer staff.']);
        return;
    }

    $staff = User::where('officeId', $this->deleteId)->get();

    if ($staff->isEmpty()) {
        $this->dispatch('alert', ['type' => 'error', 'message' => 'No staff found in this office.']);
        return;
    }

    // Transfer staff
    $targetOffice = $this->transferOfficeIds[0];
    foreach ($staff as $s) {
        $s->officeId = $targetOffice;
        $s->save();
    }

    // Flash message
    session()->flash('success', 'All staff transferred successfully.');

   $this->dispatch('close-and-refresh');
    $this->loadData();

    $this->dispatch('hide-confirm-modal');
}

public function deleteOffice()
{
    $office = OfficeModel::with('staff')->findOrFail($this->deleteId);

  
    foreach ($office->staff as $s) {
        $s->delete();
    }

 
    $office->delete();

    session()->flash('success', 'Office and all its staff deleted successfully.');

    $this->dispatch('close-and-refresh');
    $this->loadData();

}


public function loadTeamLeads()
{
 
    $this->teamLeads = [];

   
    $teamLeadUsers = User::role('organisation_admin')->get();
	 foreach ($teamLeadUsers as $user) {
        if ($user->officeId) {
            $this->teamLeads[$user->officeId] = $user;
        }
    }
}


    /**
     * Reset pagination when a filter field updates
     */
    public function updated($field)
    {
        $this->resetPage();
    }

    /**
     * Save filters
     */
    public function saveFilters()
    {
        $this->showFilter = false;
        $this->resetPage();
    }

    /**
     * Reset filters
     */
    public function resetFilters()
    {
        $this->country = [];
        $this->city = [];
        $this->turnover = [];
        $this->visibility = [];
        $this->resetPage();
    }

    /**
     * Close filter panel
     */
    public function closeFilter()
    {
        $this->country = [];
        $this->city = [];
        $this->showFilter = false;
    }

    /**
     * Toggle head office for a given office
     */
    public function toggleHeadOffice($officeId)
    {
        $office = OfficeModel::find($officeId);
        if (!$office) return;

   
        OfficeModel::where('organisation_id', $this->organisationId)->update(['is_head_office' => false]);

     
        $office->is_head_office = true;
        $office->save();

        $this->loadOffices();
        $this->loadTeamLeads(); 
    }

    /**
     * Delete an office
     */

 	public function loadData()
    {
        $this->offices = OfficeModel::with('staff')->get();
    }

    public function confirmDelete($officeId)
    {
        $this->deleteId = $officeId;
        $this->officeStaff = User::where('officeId', $officeId)->get();
     $this->officesList = OfficeModel::where('organisation_id', $this->organisationId)
                                ->where('id', '!=', $officeId)
                                ->get();

        $this->dispatch('show-confirm-modal');
    }
public function confirmDeleteHeadOffice($officeId)
{
    // Set the office ID that will be deleted

    $this->deleteId = $officeId;

    // Optionally, you can fetch staff if you want to display info in modal
    $this->officeStaff = User::where('officeId', $officeId)->get();

    // Open the confirmation modal
    $this->dispatch('show-delete-head-office-modal');
}

   public function delete($id)
    {
        $office = OfficeModel::with('staff')->findOrFail($this->deleteId);

    // Delete all staff first
    foreach ($office->staff as $staffMember) {
        $staffMember->delete();
    }

    // Delete the office itself
    $office->delete();
        $this->loadOffices();
        $this->loadTeamLeads();

    }

    public function transferAndDelete()
    {
        if (!$this->transferOfficeId) {
            $this->dispatch('notify', type: 'error', message: 'Select office for transfer');
            return;
        }

        User::where('officeId', $this->deleteId)
            ->update(['officeId' => $this->transferOfficeId]);

        OfficeModel::find($this->deleteId)?->delete();

        $this->dispatch('notify', type: 'success', message: 'Staff transferred & office deleted');
        $this->loadData();
    }


    /**
     * Render Livewire component
     */
    public function render()
    {
        return view('livewire.office', [
            'offices' => $this->loadOffices()
        ])->layout('layouts.app');
    }
}
