<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\{Organisation, Office, Department, User, SendNotification, IotNotification};
use DateTime;
use DateTimeZone;


class SendNotifications extends Component
{
    public $recipientType = 'basecamp'; // 'basecamp', 'organisation', 'all_tribe365'
    public $organisations = [];
    public $offices = [];
    public $departments = [];
    public $staffOptions = [];
    public $basecampUsers = [];

    public $orgId = '';
    public $officeId = '';
    public $departmentId = '';
    public $selectStaff = [];
    public $selectBasecampUsers = [];
    public $sendToAllOrgUsers = false; // If true, send to all users in selected org
    public $title = '';
    public $description = '';
    public $links = '';

    public $selectedUser = [];
    // UI state
    public $showUsersModal = false;
    public $showBasecampUsersModal = false;
    public $showFilterModal = false;
    public $userSearch = '';
    public $basecampUserSearch = '';

    protected $rules = [
        'title' => 'required|string|max:255',
        'description' => 'required|string',
        'links' => 'nullable|url',
    ];

    protected $listeners = [
        'selectAllUsers',
        'deselectAllUsers',
        'selectAllBasecampUsers',
        'deselectAllBasecampUsers'
    ];

    public function mount()
    {
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        $this->organisations = Organisation::select('id', 'name')
            ->orderBy('name')
            ->get()
            ->toArray();

        // default empty
        $this->offices = [];
        $this->departments = [];
        $this->staffOptions = [];
        $this->selectStaff = [];
        $this->basecampUsers = [];
        $this->selectBasecampUsers = [];
        
        // Load Basecamp users
        $this->loadBasecampUsers();
    }

    public function updatedRecipientType($value)
    {
        // Reset all selections when changing recipient type
        $this->orgId = '';
        $this->officeId = '';
        $this->departmentId = '';
        $this->selectStaff = [];
        $this->selectBasecampUsers = [];
        $this->sendToAllOrgUsers = false;
        $this->offices = [];
        $this->departments = [];
        $this->staffOptions = [];
        
        // Force re-render
        $this->dispatch('$refresh');
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
        $this->sendToAllOrgUsers = false;

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

    public function updatedSendToAllOrgUsers($value)
    {
        // When checkbox changes, reset office/department/user selections
        if ($value) {
            $this->officeId = '';
            $this->departmentId = '';
            $this->selectStaff = [];
            $this->offices = [];
            $this->departments = [];
            $this->staffOptions = [];
        }
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
                ->whereNotIn('users.id', function($query) {
                    $query->select('model_has_roles.model_id')
                          ->from('model_has_roles')
                          ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                          ->where('roles.name', 'basecamp')
                          ->where('model_has_roles.model_type', 'App\\Models\\User');
                })
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
            ->whereIn('users.status', ['Active', 'active_verified', 'active_unverified'])
            // Exclude Basecamp users from organisation staff using subquery
            ->whereNotIn('users.id', function($subQuery) {
                $subQuery->select('model_has_roles.model_id')
                         ->from('model_has_roles')
                         ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                         ->where('roles.name', 'basecamp')
                         ->where('model_has_roles.model_type', 'App\\Models\\User');
            });

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

    // Load Basecamp users
    public function loadBasecampUsers()
    {
        $users = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'basecamp')
            ->whereIn('users.status', ['active_verified', 'active_unverified'])
            ->select(
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.email',
                'users.profile_photo_path'
            )
            ->orderBy('users.first_name')
            ->get();

        $this->basecampUsers = $users->map(function($u){
            return [
                'id' => $u->id,
                'first_name' => $u->first_name,
                'last_name' => $u->last_name ?? '',
                'email' => $u->email,
                'profile_photo_path' => $u->profile_photo_path,
            ];
        })->toArray();
    }

    public function toggleBasecampUserSelection($userId)
    {
        if (in_array($userId, $this->selectBasecampUsers)) {
            $this->selectBasecampUsers = array_values(array_diff($this->selectBasecampUsers, [$userId]));
        } else {
            $this->selectBasecampUsers[] = $userId;
        }
    }

    public function selectAllBasecampUsers($ids)
    {
        $this->selectBasecampUsers = array_unique(array_merge($this->selectBasecampUsers, $ids));
    }

    public function deselectAllBasecampUsers($ids)
    {
        $this->selectBasecampUsers = array_values(array_diff($this->selectBasecampUsers, $ids));
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
        $this->validate();

        if ($this->recipientType === 'basecamp') {
            // Send to selected Basecamp users
            if (empty($this->selectBasecampUsers)) {
                session()->flash('error', 'Please select at least one Basecamp user.');
                return;
            }
            $this->processAndSendForBasecampUsers();
            session()->flash('message', 'Notification sent successfully to Basecamp users');
            
        } elseif ($this->recipientType === 'organisation') {
            // If "All Organisations" selected (orgId is empty)
            if (empty($this->orgId)) {
                $orgs = Organisation::where('status', 'Active')->select('id')->get();
                foreach ($orgs as $org) {
                    $this->processAndSendForOrg($org->id);
                }
                session()->flash('message', 'Notification sent successfully to all organisations');
            } else {
                // Specific organisation selected
                if ($this->sendToAllOrgUsers) {
                    // Send to all users in the selected organisation
                    $this->processAndSendForOrg($this->orgId);
                    session()->flash('message', 'Notification sent successfully to all users in the organisation');
                } else {
                    // Send to selected users only - validate that users are selected
                    if (empty($this->selectStaff)) {
                        session()->flash('error', 'Please select at least one user or choose "Send to all users in this organisation".');
                        return;
                    }
                    $this->processAndSendForOrg($this->orgId, $this->officeId, $this->departmentId);
                    session()->flash('message', 'Notification sent successfully');
                }
            }
            
        } elseif ($this->recipientType === 'all_tribe365') {
            // Send to all Tribe365 users (both organisation and basecamp users)
            $this->processAndSendForAllTribe365Users();
            session()->flash('message', 'Notification sent successfully to all Tribe365 users');
        }

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

            // Log activity
            try {
                $recipientCount = count($userIds ?? []);
                \App\Services\ActivityLogService::log(
                    'notification',
                    'created',
                    "Sent notification: {$this->title} to {$recipientCount} recipient(s)",
                    null,
                    null,
                    [
                        'notification_id' => $sendNotificationId,
                        'title' => $this->title,
                        'recipient_count' => $recipientCount,
                        'organisation_id' => $orgId,
                        'office_id' => $officeId,
                        'department_id' => $departmentId,
                    ]
                );
            } catch (\Exception $e) {
                \Log::warning('Failed to log notification activity: ' . $e->getMessage());
            }

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
                // All users in org/office/department (exclude Basecamp users)
                // Include all statuses except suspended/cancelled/inactive
                // Use subquery to exclude users who have basecamp role
                $users = DB::table('users')
                    ->where('users.orgId', $orgId)
                    ->whereIn('users.status', ['Active', 'active_verified', 'active_unverified', 'pending_payment'])
                    ->whereNotIn('users.id', function($query) {
                        $query->select('model_has_roles.model_id')
                              ->from('model_has_roles')
                              ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                              ->where('roles.name', 'basecamp')
                              ->where('model_has_roles.model_type', 'App\\Models\\User');
                    })
                    ->when($officeId, fn($q) => $q->where('users.officeId', $officeId))
                    ->when($departmentId, fn($q) => $q->where('users.departmentId', $departmentId))
                    ->select('users.id', 'users.fcmToken', 'users.deviceType')
                    ->get();

                \Log::info('✅ All organisation users fetched for org/office/dept', [
                    'count' => $users->count(),
                    'orgId' => $orgId,
                    'officeId' => $officeId,
                    'departmentId' => $departmentId,
                    'user_ids' => $users->pluck('id')->toArray(),
                ]);
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

            // Use the currently authenticated admin as sender, or null if not available.
            // This avoids FK violations on from_bubble_user_id.
            $fromUserId = auth()->id();

            foreach ($users as $user) {
                $insertData[] = [
                    'title'               => $this->title,
                    'description'         => $this->description,
                    'to_bubble_user_id'   => $user->id,
                    'from_bubble_user_id' => $fromUserId, // may be null, which is allowed by FK
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
             * ✅ Step 6: Fire OneSignal push notifications (batch send)
             */
            $oneSignal = app(\App\Services\OneSignalService::class);
            
            // Collect all valid FCM tokens (same validation as EveryDayUpdate command)
            $fcmTokens = collect($users)
                ->pluck('fcmToken')
                ->filter(function ($token) {
                    // Validate token format (OneSignal player ID format: alphanumeric with hyphens, min 8 chars)
                    return !empty($token) 
                        && is_string($token) 
                        && strlen($token) > 0
                        && preg_match('/^[a-z0-9-]{8,}$/i', $token);
                })
                ->unique()
                ->values()
                ->toArray();

            if (!empty($fcmTokens)) {
                try {
                    // OneSignal allows up to 2000 player IDs per request, so batch if needed
                    $batchSize = 2000;
                    $batches = array_chunk($fcmTokens, $batchSize);
                    $totalSent = 0;
                    $totalErrors = 0;
                    $invalidTokens = [];

                    foreach ($batches as $batchIndex => $batch) {
                        $response = $oneSignal->sendNotification(
                            $this->title,
                            $this->description,
                            $batch
                        );

                        // Check response for success or errors
                        if (isset($response['id'])) {
                            $totalSent += count($batch);
                            \Log::info('✅ OneSignal batch sent successfully', [
                                'batch_number' => $batchIndex + 1,
                                'batch_size' => count($batch),
                                'onesignal_id' => $response['id'],
                                'total_sent' => $totalSent,
                            ]);
                        } else {
                            // Log error details
                            $errorMessage = $response['errors'][0] ?? 'Unknown error';
                            $totalErrors += count($batch);
                            
                            \Log::error('❌ OneSignal batch send failed', [
                                'batch_number' => $batchIndex + 1,
                                'batch_size' => count($batch),
                                'error' => $errorMessage,
                                'full_response' => $response,
                            ]);

                            // Collect invalid player IDs if returned
                            if (isset($response['errors']['invalid_player_ids'])) {
                                $invalidTokens = array_merge($invalidTokens, $response['errors']['invalid_player_ids']);
                            }
                        }
                    }

                    // Remove invalid tokens from database (same as EveryDayUpdate)
                    if (!empty($invalidTokens)) {
                        DB::table('users')
                            ->whereIn('fcmToken', $invalidTokens)
                            ->update(['fcmToken' => null]);
                        
                        \Log::warning('⚠️ Removed invalid FCM tokens from database', [
                            'invalid_count' => count($invalidTokens),
                            'tokens' => $invalidTokens,
                        ]);
                    }

                    \Log::info('📲 OneSignal push notifications summary', [
                        'total_valid_tokens' => count($fcmTokens),
                        'total_sent' => $totalSent,
                        'total_errors' => $totalErrors,
                        'batches' => count($batches),
                        'invalid_tokens_removed' => count($invalidTokens),
                    ]);
                } catch (\Throwable $e) {
                    \Log::error('❌ OneSignal batch send exception', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'token_count' => count($fcmTokens),
                    ]);
                }
            } else {
                \Log::warning('⚠️ No valid FCM tokens found for OneSignal push', [
                    'total_users' => count($users),
                    'users_with_tokens' => collect($users)->filter(fn($u) => !empty($u->fcmToken))->count(),
                ]);
            }

        } catch (\Throwable $e) {
            \Log::error('❌ processAndSendForOrg failed', [
                'orgId' => $orgId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    protected function processAndSendForBasecampUsers()
    {
        try {
            // ✅ Step 1: Insert record in send_notifications table
            $sendNotificationId = DB::table('send_notifications')->insertGetId([
                'orgId'        => null,
                'officeId'     => null,
                'departmentId' => null,
                'title'        => $this->title,
                'description'  => $this->description,
                'links'        => $this->links,
                'status'       => 'Active',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // ✅ Step 2: Extract clean user IDs from selected Basecamp users
            $userIds = collect($this->selectBasecampUsers ?? [])
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

            if (empty($userIds)) {
                \Log::warning('❌ No Basecamp users selected for notification');
                return;
            }

            $basecampUsers = DB::table('users')
                ->select('users.id', 'users.fcmToken', 'users.deviceType')
                ->whereIn('users.id', $userIds)
                ->get();

            if ($basecampUsers->isEmpty()) {
                \Log::warning('❌ No Basecamp users found for notification');
                return;
            }

            $this->sendNotificationsToUsers($basecampUsers, $sendNotificationId, 'basecamp_users');

        } catch (\Throwable $e) {
            \Log::error('❌ processAndSendForBasecampUsers failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    protected function processAndSendForAllTribe365Users()
    {
        try {
            // ✅ Step 1: Insert record in send_notifications table
            $sendNotificationId = DB::table('send_notifications')->insertGetId([
                'orgId'        => null,
                'officeId'     => null,
                'departmentId' => null,
                'title'        => $this->title,
                'description'  => $this->description,
                'links'        => $this->links,
                'status'       => 'Active',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // ✅ Step 2: Fetch all active users (both organisation and basecamp)
            // Get organisation users (users with orgId, excluding basecamp role)
            // Include all statuses except suspended/cancelled/inactive for "All Tribe365 Users"
            // Use subquery to exclude users who have basecamp role
            $orgUsers = DB::table('users')
                ->whereNotNull('users.orgId')
                ->whereIn('users.status', ['Active', 'active_verified', 'active_unverified', 'pending_payment'])
                ->whereNotIn('users.id', function($query) {
                    $query->select('model_has_roles.model_id')
                          ->from('model_has_roles')
                          ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                          ->where('roles.name', 'basecamp')
                          ->where('model_has_roles.model_type', 'App\\Models\\User');
                })
                ->select('users.id', 'users.fcmToken', 'users.deviceType')
                ->get();

            \Log::info('✅ Organisation users fetched for All Tribe365', [
                'count' => $orgUsers->count(),
                'sample_ids' => $orgUsers->take(5)->pluck('id')->toArray(),
            ]);

            // Get Basecamp users (users with basecamp role)
            // Include all statuses except suspended/cancelled/inactive for "All Tribe365 Users"
            $basecampUsers = DB::table('users')
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('roles.name', 'basecamp')
                ->whereIn('users.status', ['active_verified', 'active_unverified', 'pending_payment'])
                ->select('users.id', 'users.fcmToken', 'users.deviceType')
                ->distinct()
                ->get();

            \Log::info('✅ Basecamp users fetched for All Tribe365', [
                'count' => $basecampUsers->count(),
            ]);

            // Merge both collections to get all Tribe365 users
            $allUsers = $orgUsers->merge($basecampUsers)->unique('id')->values();

            \Log::info('✅ Total users for All Tribe365 notification', [
                'total_count' => $allUsers->count(),
                'org_count' => $orgUsers->count(),
                'basecamp_count' => $basecampUsers->count(),
                'org_user_ids' => $orgUsers->pluck('id')->toArray(),
                'basecamp_user_ids' => $basecampUsers->pluck('id')->toArray(),
                'all_user_ids' => $allUsers->pluck('id')->toArray(),
            ]);

            if ($allUsers->isEmpty()) {
                \Log::warning('❌ No users found for notification');
                return;
            }

            $this->sendNotificationsToUsers($allUsers, $sendNotificationId, 'all_tribe365_users');

        } catch (\Throwable $e) {
            \Log::error('❌ processAndSendForAllTribe365Users failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    protected function sendNotificationsToUsers($users, $sendNotificationId, $recipientType = 'organisation')
    {
        try {
            // ✅ Step 1: Insert into iot_notifications for each user
            $now = now();
            $insertData = [];
            $fromUserId = auth()->id();

            foreach ($users as $user) {
                // Handle both object and array formats
                $userId = is_object($user) ? $user->id : (is_array($user) ? $user['id'] : $user);
                
                $insertData[] = [
                    'title'               => $this->title,
                    'description'         => $this->description,
                    'to_bubble_user_id'   => $userId,
                    'from_bubble_user_id' => $fromUserId,
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

            // Log inserted user IDs for debugging
            $insertedUserIds = collect($insertData)->pluck('to_bubble_user_id')->toArray();
            
            \Log::info('✅ Notifications inserted successfully', [
                'sendNotificationId' => $sendNotificationId,
                'total_users' => count($insertData),
                'recipient_type' => $recipientType,
                'inserted_user_ids' => $insertedUserIds,
            ]);

            // ✅ Step 2: Fire OneSignal push notifications (batch send)
            $oneSignal = app(\App\Services\OneSignalService::class);
            
            // Collect all valid FCM tokens
            $fcmTokens = collect($users)
                ->pluck('fcmToken')
                ->filter(function ($token) {
                    return !empty($token) 
                        && is_string($token) 
                        && strlen($token) > 0
                        && preg_match('/^[a-z0-9-]{8,}$/i', $token);
                })
                ->unique()
                ->values()
                ->toArray();

            if (!empty($fcmTokens)) {
                try {
                    $batchSize = 2000;
                    $batches = array_chunk($fcmTokens, $batchSize);
                    $totalSent = 0;
                    $totalErrors = 0;
                    $invalidTokens = [];

                    foreach ($batches as $batchIndex => $batch) {
                        $response = $oneSignal->sendNotification(
                            $this->title,
                            $this->description,
                            $batch
                        );

                        if (isset($response['id'])) {
                            $totalSent += count($batch);
                            \Log::info('✅ OneSignal batch sent successfully', [
                                'batch_number' => $batchIndex + 1,
                                'batch_size' => count($batch),
                                'onesignal_id' => $response['id'],
                                'total_sent' => $totalSent,
                                'recipient_type' => $recipientType,
                            ]);
                        } else {
                            $errorMessage = $response['errors'][0] ?? 'Unknown error';
                            $totalErrors += count($batch);
                            
                            \Log::error('❌ OneSignal batch send failed', [
                                'batch_number' => $batchIndex + 1,
                                'batch_size' => count($batch),
                                'error' => $errorMessage,
                                'full_response' => $response,
                            ]);

                            if (isset($response['errors']['invalid_player_ids'])) {
                                $invalidTokens = array_merge($invalidTokens, $response['errors']['invalid_player_ids']);
                            }
                        }
                    }

                    // Remove invalid tokens from database
                    if (!empty($invalidTokens)) {
                        DB::table('users')
                            ->whereIn('fcmToken', $invalidTokens)
                            ->update(['fcmToken' => null]);
                        
                        \Log::warning('⚠️ Removed invalid FCM tokens from database', [
                            'invalid_count' => count($invalidTokens),
                        ]);
                    }

                    \Log::info('📲 OneSignal push notifications summary', [
                        'total_valid_tokens' => count($fcmTokens),
                        'total_sent' => $totalSent,
                        'total_errors' => $totalErrors,
                        'batches' => count($batches),
                        'recipient_type' => $recipientType,
                    ]);
                } catch (\Throwable $e) {
                    \Log::error('❌ OneSignal batch send exception', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'token_count' => count($fcmTokens),
                    ]);
                }
            }

            // Log activity
            try {
                \App\Services\ActivityLogService::log(
                    'notification',
                    'created',
                    "Sent notification: {$this->title} to {$users->count()} user(s)",
                    null,
                    null,
                    [
                        'notification_id' => $sendNotificationId,
                        'title' => $this->title,
                        'recipient_count' => $users->count(),
                        'recipient_type' => $recipientType,
                    ]
                );
            } catch (\Exception $e) {
                \Log::warning('Failed to log notification activity: ' . $e->getMessage());
            }

        } catch (\Throwable $e) {
            \Log::error('❌ sendNotificationsToUsers failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

}
