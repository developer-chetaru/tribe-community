<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Models\{Organisation, Office, Department, User, SendNotification, IotNotification};
use DateTime;
use DateTimeZone;

class SendNotifications extends Component
{
    public $organisations = [];
    public $offices = [];
    public $departments = [];
    public $staffOptions = [];

    public $orgId = '';
    public $officeId = '';
    public $departmentId = '';
    public $selectStaff = [];
    public $title = '';
    public $description = '';
    public $links = '';

    public $selectedUser = [];

    protected $rules = [
        'title' => 'required|string|max:255',
        'description' => 'required|string',
        'links' => 'nullable|url',
    ];

    protected $listeners = [
        'refreshStaff' => 'loadStaffOptions'
    ];

    public function mount()
    {
        $this->organisations = Organisation::select('id', 'name')
            ->orderBy('name')
            ->get()
            ->toArray();

        // default empty
        $this->offices = [];
        $this->departments = [];
        $this->staffOptions = [];
    }

    public function render()
    {
        return view('livewire.admin.send-notifications')->layout('layouts.app');
    }

    // When organisation changes -> load offices, departments and staff
    public function updatedOrgId($value)
    {
        $this->officeId = '';
        $this->departmentId = '';

        if ($value) {
            $this->offices = Office::where('organisation_id', $value)
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->toArray();
        } else {
            $this->offices = [];
        }

        $this->departments = [];
    }

    public function updatedOfficeId($value)
    {
        $this->departmentId = '';
        $this->selectStaff = [];

        if ($value) {
            $this->departments = Department::where('office_id', $value)
                ->select('id', 'department')
                ->orderBy('department')
                ->get()
                ->toArray();
        } else {
            $this->departments = [];
        }

        $this->staffOptions = [];
    }

    // When department changes -> load staff
    public function updatedDepartmentId($value)
    {
        $this->selectStaff = [];

        if ($value) {
            $this->staffOptions = User::where('departmentId', $value)
                ->select('id', 'first_name as text')
                ->orderBy('first_name')
                ->get()
                ->toArray();
        } else {
            $this->staffOptions = [];
        }
    }

    // Triggered when organisation dropdown changes
    public function updated($propertyName)
    {
        if ($propertyName === 'orgId') {
            $this->officeId = '';
            $this->departmentId = '';
            $this->offices = Office::where('organisation_id', $this->orgId)
                ->select('id','name')
                ->orderBy('name')
                ->get()
                ->toArray();

            $this->departments = [];
        }

        if ($propertyName === 'officeId') {
            $this->departmentId = '';
            $this->departments = Department::where('office_id', $this->officeId)
                ->select('id','department')
                ->orderBy('department')
                ->get()
                ->toArray();
        }
    }
    // ------------------------------------------------------------------------

    // Load offices for the selected organisation
    public function loadOffices($value)
    {
        $this->officeId = '';
        $this->departmentId = '';
        $this->selectStaff = [];

        if ($value) {
            $this->offices = Office::where('organisation_id', $value)
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->toArray();
        } else {
            $this->offices = [];
        }

        $this->departments = [];
        $this->staffOptions = [];
    }


    public function loadDepartments()
    {
        // If office is selected → show unique departments under that office
        if (!empty($this->officeId)) {
            $this->departments = Department::join('all_departments', 'departments.all_department_id', '=', 'all_departments.id')
                ->where('departments.office_id', $this->officeId)
                ->select('all_departments.id as all_department_id', 'all_departments.name as department')
                ->distinct()
                ->orderBy('all_departments.name')
                ->get()
                ->toArray();

            return;
        }

        // Else if only organisation is selected → show unique departments under that organisation
        if (!empty($this->orgId)) {
            $this->departments = Department::join('all_departments', 'departments.all_department_id', '=', 'all_departments.id')
                ->where('departments.organisation_id', $this->orgId)
                ->select('all_departments.id as all_department_id', 'all_departments.name as department')
                ->distinct()
                ->orderBy('all_departments.name')
                ->get()
                ->toArray();

            return;
        }

        $this->departments = [];
    }


    public function loadUsersByDepartment($allDepartmentId)
    {
        // Reset user list
        $this->staffOptions = [];
        
        if (!empty($allDepartmentId)) {

            // Get all department IDs (from different offices) under this master department
            $departmentIds = Department::where('all_department_id', $allDepartmentId)
                ->pluck('id')
                ->toArray();

            // Load all users belonging to any of those departments
            $this->staffOptions = User::whereIn('departmentId', $departmentIds)
                ->select('id', 'first_name', 'email')
                ->orderBy('first_name')
                ->get()
                ->toArray();
            $this->selectStaff = User::whereIn('departmentId', $departmentIds)
                ->orderBy('first_name')
                ->get()
                ->toArray();
        }   
    }

    // Load departments for selected org/office (fallbacks to org-level)
    public function loadDepartmentsOLDwala()
    {
        // If office is selected
        if (!empty($this->officeId)) {
            $this->departments = Department::where('departments.office_id', $this->officeId)
                ->join('all_departments', 'departments.all_department_id', '=', 'all_departments.id')
                ->select('departments.id', 'all_departments.name as department')
                ->orderBy('all_departments.name')
                ->get()
                ->toArray();
            return;
        }

        // If only organisation is selected
        if (!empty($this->orgId)) {
            $this->departments = Department::where('departments.organisation_id', $this->orgId)
                ->join('all_departments', 'departments.all_department_id', '=', 'all_departments.id')
                ->select('departments.id', 'all_departments.name as department')
                ->orderBy('all_departments.name')
                ->get()
                ->toArray();
            return;
        }

        // If nothing is selected
        $this->departments = [];
    }


    // Load staff options according to current filters (orgId, officeId, departmentId)
    public function loadStaffOptions()
    {
        // If explicit staff selected previously, keep them empty for repopulation by UI
        // Build query similar to old logic
        $query = DB::table('users')
            ->select('users.id','users.first_name','users.email') // change fields as needed
            ->where('users.status','Active');

        // If user selected specific org
        if (!empty($this->orgId)) {
            $query->where('users.orgId', $this->orgId);
        }

        // Cases for office/department like old code
        if (!empty($this->officeId) && empty($this->departmentId)) {
            $query->where('users.officeId', $this->officeId);
        } elseif (!empty($this->officeId) && !empty($this->departmentId)) {
            $query->where('users.officeId', $this->officeId)
                  ->where('users.departmentId', $this->departmentId);
        } elseif (empty($this->officeId) && !empty($this->departmentId)) {
            // If office is empty and departmentId provided, fallback to joining departments table like old code
            $query->leftJoin('departments','departments.id','users.departmentId')
                  ->where('departments.status','Active')
                  ->where('departments.departmentId', $this->departmentId);
        }

        $users = $query->orderBy('users.first_name')->get();

        $this->staffOptions = $users->map(function($u){
            return ['id' => $u->id, 'text' => ($u->first_name ?? $u->email)];
        })->toArray();
    }

    // MAIN: send notifications
    public function sendNotification()
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
        ]);

        // If no organisation selected, send to all active orgs
        if (empty($this->orgId)) {
            $orgs = Organisation::where('status', 'Active')->select('id')->get();

            foreach ($orgs as $org) {
                $this->processAndSendForOrg($org->id);
            }

            session()->flash('message', 'Notification sent successfully (all orgs)');
            return redirect()->route('admin.send-notification');
        }

        // Else send only for selected org
        $this->processAndSendForOrg($this->orgId, $this->officeId, $this->departmentId);

        session()->flash('message', 'Notification sent successfully');
        return redirect()->route('admin.send-notification');
    }


    protected function processAndSendForOrg($orgId, $officeId = null, $departmentId = null)
{
    try {
        // ✅ Step 1: Create master send_notifications record
        $sendNotificationId = DB::table('send_notifications')->insertGetId([
            'orgId'        => $orgId,
            'officeId'     => $officeId,
            'departmentId' => $departmentId,
            'title'        => $this->title,
            'description'  => $this->description,
            'links'        => $this->links,
            'status'       => 'Active',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // ✅ Step 2: Determine which users to notify
        if (!empty($this->selectStaff) && is_array($this->selectStaff)) {
            $userIds = collect($this->selectStaff)
                ->filter(fn($v) => is_numeric($v))
                ->unique()
                ->values()
                ->toArray();

            $users = DB::table('users')
                ->select('id', 'fcmToken', 'deviceType')
                ->whereIn('id', $userIds)
                ->where('status', 'Active')
                ->get();
        } else {
            $users = DB::table('users')
                ->select('id', 'fcmToken', 'deviceType')
                ->where('status', 'Active')
                ->where('roleId', 3)
                ->where('orgId', $orgId)
                ->when($officeId, fn($q) => $q->where('officeId', $officeId))
                ->when($departmentId, fn($q) => $q->where('departmentId', $departmentId))
                ->get();
        }

        // ✅ Step 3: Stop if no users found
        if ($users->isEmpty()) {
            \Log::info('No users found for notification', [
                'orgId' => $orgId,
                'officeId' => $officeId,
                'departmentId' => $departmentId,
                'selectedStaff' => $this->selectStaff,
            ]);
            return;
        }

        // ✅ Step 4: Insert iot_notifications for each user
        foreach ($users as $user) {
            if (empty($user->id)) {
                continue;
            }

            try {
                $notificationId = DB::table('iot_notifications')->insertGetId([
                    'title'               => $this->title,
                    'description'         => $this->description,
                    'to_bubble_user_id'   => $user->id,
                    'from_bubble_user_id' => 1,
                    'notificationType'    => 'custom notification',
                    'notificationLinks'   => $this->links,
                    'sendNotificationId'  => $sendNotificationId,
                    'status'              => 'Active',
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);

                // ✅ Send FCM notification if token and login exist
                if (!empty($user->fcmToken)) {
                    $loggedInUser = DB::table('oauth_access_tokens')
                        ->where('user_id', $user->id)
                        ->where('revoked', 0)
                        ->first();

                    if ($loggedInUser) {
                        app('App\Http\Controllers\Admin\CommonController')->sendFcmNotify([
                            'fcmToken'         => $user->fcmToken,
                            'title'            => $this->title,
                            'message'          => $this->description,
                            'totbadge'         => 0,
                            'notificationType' => 'custom_notification',
                            'deviceType'       => $user->deviceType,
                            'notificationId'   => $notificationId,
                            'link'             => $this->links,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                \Log::error('Failed inserting iot_notification', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        // ✅ Log final result
        \Log::info('Notification sent successfully', [
            'sendNotificationId' => $sendNotificationId,
            'total_users'        => $users->count(),
        ]);

    } catch (\Throwable $e) {
        \Log::error('processAndSendForOrg failed', [
            'orgId' => $orgId,
            'error' => $e->getMessage(),
        ]);
    }
}


}
