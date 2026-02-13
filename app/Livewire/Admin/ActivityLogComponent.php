<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\DB;

class ActivityLogComponent extends Component
{
    use WithPagination;

    public $selectedModule = '';
    public $selectedAction = '';
    public $selectedUserId = '';
    public $search = '';
    public $dateFrom = '';
    public $dateTo = '';

    protected $queryString = [
        'selectedModule' => ['except' => ''],
        'selectedAction' => ['except' => ''],
        'selectedUserId' => ['except' => ''],
        'search' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
    ];

    public function mount()
    {
        // Don't set default dates - leave empty
    }

    public function resetFilters()
    {
        $this->selectedModule = '';
        $this->selectedAction = '';
        $this->selectedUserId = '';
        $this->search = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
    }

    public function updatedSelectedModule()
    {
        $this->resetPage();
    }

    public function updatedSelectedAction()
    {
        $this->resetPage();
    }

    public function updatedSelectedUserId()
    {
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedDateFrom()
    {
        $this->resetPage();
    }

    public function updatedDateTo()
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = ActivityLog::query()
            ->with('user')
            ->orderBy('created_at', 'desc');

        // Filter by module
        if ($this->selectedModule) {
            $query->where('module', $this->selectedModule);
        }

        // Filter by action
        if ($this->selectedAction) {
            $query->where('action', $this->selectedAction);
        }

        // Filter by user
        if ($this->selectedUserId) {
            $query->where('user_id', $this->selectedUserId);
        }

        // Search in description, user_name, user_email
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('description', 'like', '%' . $this->search . '%')
                    ->orWhere('user_name', 'like', '%' . $this->search . '%')
                    ->orWhere('user_email', 'like', '%' . $this->search . '%');
            });
        }

        // Filter by date range
        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        $activityLogs = $query->paginate(50);

        // Get filter options
        $modules = ActivityLogService::getModules();
        $actions = ActivityLogService::getActions();
        
        // Get all users for filter dropdown
        $users = User::orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return view('livewire.admin.activity-log', [
            'activityLogs' => $activityLogs,
            'modules' => $modules,
            'actions' => $actions,
            'users' => $users,
        ]);
    }
}
