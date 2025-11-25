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
    // UI state
    public $showUsersModal = false;
    public $showFilterModal = false;
    public $userSearch = '';

    protected $rules = [
        'title' => 'required|string|max:255',
        'description' => 'required|string',
        'links' => 'nullable|url',
    ];

    protected $listeners = [
        'selectAllUsers',
        'deselectAllUsers'
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
        $this->selectStaff = [];
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

    public function updatedOfficeId($value)
    {
        $this->departmentId = '';
        $this->selectStaff = [];

        if ($value) {
            // departments under office (distinct all_departments)
            $this->departments = Department::join('all_departments', 'departments.all_department_id', '=', 'all_departments.id')
                ->where('departments.office_id', $value)
                ->select('all_departments.id as all_department_id', 'all_departments.name as department')
                ->distinct()
                ->orderBy('all_departments.name')
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
            // load users for the given all_department id (across offices)
            $departmentIds = Department::where('all_department_id', $value)->pluck('id')->toArray();

            $this->staffOptions = User::whereIn('departmentId', $departmentIds)
                ->leftJoin('departments as d', 'users.departmentId', '=', 'd.id')
                ->leftJoin('all_departments as ad', 'd.all_department_id', '=', 'ad.id')
                ->leftJoin('offices as o', 'users.officeId', '=', 'o.id')
                ->select(
                    'users.id',
                    'users.first_name',
                    'users.email',
                    'users.profile_photo_path',
                    DB::raw('COALESCE(ad.name, "") as department'),
                    DB::raw('COALESCE(o.name, "") as office')
                )
                ->orderBy('users.first_name')
                ->get()
                ->map(function ($u) {
                    return [
                        'id' => $u->id,
                        'first_name' => $u->first_name,
                        'email' => $u->email,
                        'profile_photo_path' => $u->profile_photo_path,
                        'department' => $u->department,
                        'office' => $u->office,
                    ];
                })->toArray();

            // do not pre-populate selectStaff here; keep as user choices
            $this->selectStaff = array_values(array_filter($this->selectStaff, 'is_numeric'));
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
            $this->staffOptions = [];
        }

        if ($propertyName === 'officeId') {
            $this->departmentId = '';
            $this->departments = Department::where('office_id', $this->officeId)
                ->join('all_departments', 'departments.all_department_id', '=', 'all_departments.id')
                ->select('all_departments.id as all_department_id','all_departments.name as department')
                ->distinct()
                ->orderBy('all_departments.name')
                ->get()
                ->toArray();

            $this->staffOptions = [];
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
        $this->staffOptions = [];
        $this->selectStaff = [];

        if (!empty($allDepartmentId)) {
            $departmentIds = Department::where('all_department_id', $allDepartmentId)
                ->pluck('id')
                ->toArray();

            $users = User::whereIn('departmentId', $departmentIds)
                ->leftJoin('departments as d', 'users.departmentId', '=', 'd.id')
                ->leftJoin('all_departments as ad', 'd.all_department_id', '=', 'ad.id')
                ->leftJoin('offices as o', 'users.officeId', '=', 'o.id')
                ->select(
                    'users.id',
                    'users.first_name',
                    'users.email',
                    'users.profile_photo_path',
                    DB::raw('COALESCE(ad.name, "") as department'),
                    DB::raw('COALESCE(o.name, "") as office')
                )
                ->orderBy('users.first_name')
                ->get();

            $this->staffOptions = $users->map(function ($u) {
                return [
                    'id' => $u->id,
                    'first_name' => $u->first_name,
                    'email' => $u->email,
                    'profile_photo_path' => $u->profile_photo_path,
                    'department' => $u->department,
                    'office' => $u->office,
                ];
            })->toArray();
        }
    }

    // Load staff options according to current filters (orgId, officeId, departmentId)
    public function loadStaffOptions()
    {
        $query = DB::table('users')
            ->leftJoin('departments as d', 'users.departmentId', '=', 'd.id')
            ->leftJoin('all_departments as ad', 'd.all_department_id', '=', 'ad.id')
            ->leftJoin('offices as o', 'users.officeId', '=', 'o.id')
            ->select(
                'users.id',
                'users.first_name',
                'users.email',
                'users.profile_photo_path',
                DB::raw('COALESCE(ad.name, "") as department'),
                DB::raw('COALESCE(o.name, "") as office')
            )
            ->where('users.status','Active');

        if (!empty($this->orgId)) {
            $query->where('users.orgId', $this->orgId);
        }

        if (!empty($this->officeId) && empty($this->departmentId)) {
            $query->where('users.officeId', $this->officeId);
        } elseif (!empty($this->officeId) && !empty($this->departmentId)) {
            $query->where('users.officeId', $this->officeId)
                  ->where('users.departmentId', $this->departmentId);
        } elseif (empty($this->officeId) && !empty($this->departmentId)) {
            $query->whereIn('users.departmentId', function ($q) {
                $q->select('id')->from('departments')->where('all_department_id', $this->departmentId);
            });
        }

        $users = $query->orderBy('users.first_name')->get();

        $this->staffOptions = $users->map(function($u){
            return [
                'id' => $u->id,
                'first_name' => $u->first_name,
                'email' => $u->email,
                'profile_photo_path' => $u->profile_photo_path,
                'department' => $u->department,
                'office' => $u->office,
            ];
        })->toArray();

        // Keep current selection only if present in staffOptions
        $availableIds = collect($this->staffOptions)->pluck('id')->map(function($i){ return (int) $i; })->toArray();
        $this->selectStaff = array_values(array_filter($this->selectStaff, function($id) use ($availableIds) {
            return in_array((int)$id, $availableIds);
        }));
    }

    public function toggleUserSelection($userId)
{
    if (in_array($userId, $this->selectStaff)) {
        $this->selectStaff = array_values(array_diff($this->selectStaff, [$userId]));
    } else {
        $this->selectStaff[] = $userId;
    }
}

public function selectAllUsers($ids)
{
    $this->selectStaff = array_unique(array_merge($this->selectStaff, $ids));
}

public function deselectAllUsers($ids)
{
    $this->selectStaff = array_values(array_diff($this->selectStaff, $ids));
}

    // Apply filter from filter modal
    public function applyFilters()
    {
        // refresh offices/departments accordingly
        if (!empty($this->orgId)) {
            $this->offices = Office::where('organisation_id', $this->orgId)->select('id','name')->get()->toArray();
        } else {
            $this->offices = [];
        }

        $this->loadDepartments();
        $this->loadStaffOptions();

        // close modal on client
        $this->dispatch('closeFilterModal');
    }

    // Clear filter modal
    public function clearFilters()
    {
        $this->orgId = '';
        $this->officeId = '';
        $this->departmentId = '';
        $this->offices = [];
        $this->departments = [];
        $this->staffOptions = [];
        $this->selectStaff = [];
        $this->dispatch('closeFilterModal');
    }

    // MAIN: send notifications
    public function sendNotification()
    {
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
            // ✅ Step 1: Insert record in send_notifications table
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

            // ✅ Step 2: Extract clean user IDs
            $userIds = collect($this->selectStaff ?? [])
                ->map(function ($item) {
                    if (is_numeric($item)) {
                        return (int) $item;
                    }
                    if (is_array($item) && isset($item['id'])) {
                        return (int) $item['id'];
                    }
                    if (is_object($item) && isset($item->id)) {
                        return (int) $item->id;
                    }
                    return null;
                })
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            // ✅ Step 3: Fetch users properly
            if (!empty($userIds)) {
                $users = DB::table('users')
                    ->select('id', 'fcmToken', 'deviceType')
                    ->whereIn('id', $userIds)
                    ->get();

                \Log::info('✅ Users fetched using selectStaff', [
                    'count' => $users->count(),
                    'ids' => $userIds,
                ]);
            } else {
                $users = DB::table('users')
                    ->select('id', 'fcmToken', 'deviceType')
                    ->where('orgId', $orgId)
                    ->when($officeId, fn($q) => $q->where('officeId', $officeId))
                    ->when($departmentId, fn($q) => $q->where('departmentId', $departmentId))
                    ->get();

                \Log::info('⚠️ Default user fetch used', ['count' => $users->count()]);
            }

            // ✅ Step 4: Stop if no users
            if ($users->isEmpty()) {
                \Log::warning('❌ No users found for notification', [
                    'orgId' => $orgId,
                    'selectStaff' => $this->selectStaff,
                    'userIds' => $userIds,
                ]);
                return;
            }

            // ✅ Step 5: Insert into iot_notifications for each user
            $now = now();
            $insertData = [];

            foreach ($users as $user) {
                $insertData[] = [
                    'title'               => $this->title,
                    'description'         => $this->description,
                    'to_bubble_user_id'   => $user->id,
                    'from_bubble_user_id' => 1,
                    'notificationType'    => 'custom notification',
                    'notificationLinks'   => $this->links,
                    'sendNotificationId'  => $sendNotificationId,
                    'status'              => 'Active',
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];
            }

            // Bulk insert
            DB::table('iot_notifications')->insert($insertData);

            \Log::info('✅ Notifications inserted successfully', [
                'sendNotificationId' => $sendNotificationId,
                'total_users' => count($insertData),
            ]);

            /**
             * ✅ Step 6: Fire OneSignal notifications only for those users
             */
            $oneSignal = app(\App\Services\OneSignalService::class);

            foreach ($users as $user) {
                if (!empty($user->fcmToken)) {
                    try {
                        $oneSignal->sendNotification(
                            $this->title,
                            $this->description,
                            [$user->fcmToken],
                        );
                    } catch (\Throwable $e) {
                        \Log::error('❌ OneSignal send failed for user', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            \Log::info('📲 OneSignal notifications fired successfully', [
                'count' => count($users),
            ]);

        } catch (\Throwable $e) {
            \Log::error('❌ processAndSendForOrg failed', [
                'orgId' => $orgId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

}
