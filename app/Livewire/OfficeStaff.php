<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Office;
use App\Models\Organisation;
use App\Models\UserLeave;
use Carbon\Carbon;

class OfficeStaff extends Component
{
    use WithPagination;

    public $officeId;
    public $office;
    public $organisation;
    public $search = '';
    protected $paginationTheme = 'tailwind';

    public $selectedStaffId;
    public $selectedStaff;
    public $leaveStartDate;
    public $leaveEndDate;

    public $showTeamLeadModal = false;
    public $showDirectorModal = false;
    public $selectedUserId = null;

    public function mount($officeId)
    {
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        $this->officeId = $officeId;
        $this->office = Office::findOrFail($officeId);
        $this->organisation = Organisation::findOrFail($this->office->organisation_id);
    }

    public function updatedSelectedStaffId($value)
    {
        $this->selectedStaff = User::with(['office', 'department.allDepartment'])->find($value);
    }

    public function getStaffListProperty()
    {
        return User::with(['office', 'department', 'roles'])
            ->where('officeId', $this->officeId)
            ->where('orgId', $this->organisation->id)
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'))
            ->when($this->search, fn($q) =>
                $q->where(function ($query) {
                    $query->where('first_name', 'like', "%{$this->search}%")
                          ->orWhere('last_name', 'like', "%{$this->search}%")
                          ->orWhere('email', 'like', "%{$this->search}%");
                })
            )
            ->orderBy('id', 'desc')
            ->paginate(12);
    }

    public function delete($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        session()->flash('success', "Staff deleted successfully.");
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
            'start_date'   => Carbon::parse($this->leaveStartDate)->toDateString(),
            'end_date'     => Carbon::parse($this->leaveEndDate)->toDateString(),
            'resume_date'  => Carbon::parse($this->leaveEndDate)->addDay()->toDateString(),
            'leave_status' => 1,
        ]);

        $user->onLeave = 1;
        $user->save();

        $this->leaveStartDate = null;
        $this->leaveEndDate = null;
        $this->selectedStaffId = null;
        $this->selectedStaff = null;

        $this->dispatch('close-leave-modal');
        session()->flash('success', "Leave applied successfully.");
    }

    public function changeLeaveStatus($staffId = null)
    {
        $userId = $staffId ?? auth()->id();

        $userLeave = UserLeave::where('user_id', $userId)
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

    public function openTeamLeadModal($userId)
    {
        $this->selectedUserId = $userId;
        $this->selectedStaffId = $userId; // ensure updatedSelectedStaffId() runs
        $this->selectedStaff = User::with(['office'])->find($userId); // directly set it
        $this->showTeamLeadModal = true;
    }

    public function closeTeamLeadModal()
    {
        $this->showTeamLeadModal = false;
        $this->selectedUserId = null;
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

        session()->flash('teamLeadMessage', $user->first_name . ' is now the new Team Lead for ' . $user->office->name . '.');

        $this->closeTeamLeadModal();
    }

    public function openDirectorModal($userId)
    {
        $this->selectedUserId = $userId;
        $this->selectedStaffId = $userId;
        $this->selectedStaff = User::with(['office'])->find($userId);
        $this->showDirectorModal = true;
    }

    public function closeDirectorModal()
    {
        $this->showDirectorModal = false;
        $this->selectedUserId = null;
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

        session()->flash('directorMessage', $user->first_name . ' is now the Director.');

        $this->closeDirectorModal();
    }

    public function render()
    {
        $today = Carbon::today('Asia/Kolkata')->toDateString();
        $onLeaveToday = UserLeave::where('user_id', auth()->id())
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->where('leave_status', 1)
            ->exists();

        return view('livewire.office-staff', [
            'staffList'     => $this->staffList,
            'onLeaveToday'  => $onLeaveToday,
        ])->layout('layouts.app');
    }
}
