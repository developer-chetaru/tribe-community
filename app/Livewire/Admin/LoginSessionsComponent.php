<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\LoginSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LoginSessionsComponent extends Component
{
    use WithPagination;

    public $selectedPlatform = '';
    public $selectedDeviceType = '';
    public $selectedUserId = '';
    public $selectedStatus = '';
    public $search = '';
    public $dateFrom = '';
    public $dateTo = '';

    protected $queryString = [
        'selectedPlatform' => ['except' => ''],
        'selectedDeviceType' => ['except' => ''],
        'selectedUserId' => ['except' => ''],
        'selectedStatus' => ['except' => ''],
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
        $this->selectedPlatform = '';
        $this->selectedDeviceType = '';
        $this->selectedUserId = '';
        $this->selectedStatus = '';
        $this->search = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
    }

    public function updatedSelectedPlatform()
    {
        $this->resetPage();
    }

    public function updatedSelectedDeviceType()
    {
        $this->resetPage();
    }

    public function updatedSelectedUserId()
    {
        $this->resetPage();
    }

    public function updatedSelectedStatus()
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
        $query = LoginSession::query()
            ->with('user')
            ->orderBy('login_at', 'desc');

        // Filter by platform
        if ($this->selectedPlatform) {
            $query->where('platform', $this->selectedPlatform);
        }

        // Filter by device type
        if ($this->selectedDeviceType) {
            $query->where('device_type', $this->selectedDeviceType);
        }

        // Filter by user
        if ($this->selectedUserId) {
            $query->where('user_id', $this->selectedUserId);
        }

        // Filter by status
        if ($this->selectedStatus) {
            $query->where('status', $this->selectedStatus);
        }

        // Search in user name, email, device name, IP address
        if ($this->search) {
            $query->where(function ($q) {
                $q->whereHas('user', function ($userQuery) {
                    $userQuery->where('first_name', 'like', '%' . $this->search . '%')
                        ->orWhere('last_name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                })
                ->orWhere('device_name', 'like', '%' . $this->search . '%')
                ->orWhere('ip_address', 'like', '%' . $this->search . '%')
                ->orWhere('city', 'like', '%' . $this->search . '%')
                ->orWhere('country', 'like', '%' . $this->search . '%');
            });
        }

        // Filter by date range
        if ($this->dateFrom) {
            $query->whereDate('login_at', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('login_at', '<=', $this->dateTo);
        }

        $loginSessions = $query->paginate(50);

        // Get filter options
        $platforms = ['web' => 'Web', 'mobile' => 'Mobile', 'api' => 'API'];
        $deviceTypes = LoginSession::whereNotNull('device_type')
            ->distinct()
            ->pluck('device_type')
            ->mapWithKeys(function ($type) {
                return [$type => ucfirst($type)];
            })
            ->toArray();
        
        $statuses = ['active' => 'Active', 'logged_out' => 'Logged Out', 'expired' => 'Expired'];
        
        // Get all users for filter dropdown
        $users = User::orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return view('livewire.admin.login-sessions-component', [
            'loginSessions' => $loginSessions,
            'platforms' => $platforms,
            'deviceTypes' => $deviceTypes,
            'statuses' => $statuses,
            'users' => $users,
        ]);
    }
}
