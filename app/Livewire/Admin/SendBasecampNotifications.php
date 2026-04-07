<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\{User, SendNotification};

class SendBasecampNotifications extends Component
{
    public $basecampUsers = [];
    public $selectBasecampUsers = [];
    public $title = '';
    public $description = '';
    public $links = '';

    // UI state
    public $showUsersModal = false;
    public $userSearch = '';

    protected $rules = [
        'title' => 'required|string|max:255',
        'description' => 'required|string',
        'links' => 'nullable|url',
    ];

    protected $listeners = [
        'selectAllBasecampUsers',
        'deselectAllBasecampUsers'
    ];

    public function mount()
    {
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        // Load Basecamp users
        $this->loadBasecampUsers();
    }

    public function render()
    {
        return view('livewire.admin.send-basecamp-notifications')->layout('layouts.app');
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

    // MAIN: send notifications
    public function sendNotification()
    {
        $this->validate();

        // If no Basecamp users selected, show error
        if (empty($this->selectBasecampUsers)) {
            session()->flash('error', 'Please select at least one Basecamp user.');
            return;
        }

        $this->processAndSendForBasecampUsers();

        session()->flash('message', 'Notification sent successfully to Basecamp users');
        return redirect()->route('admin.send-basecamp-notification');
    }

    protected function processAndSendForBasecampUsers()
    {
        try {
            // ✅ Step 1: Insert record in send_notifications table (orgId will be null for basecamp users)
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

            // ✅ Step 3: Fetch selected Basecamp users
            if (empty($userIds)) {
                \Log::warning('❌ No Basecamp users selected for notification');
                return;
            }

            $basecampUsers = DB::table('users')
                ->select('users.id', 'users.fcmToken', 'users.deviceType')
                ->whereIn('users.id', $userIds)
                ->get();

            \Log::info('✅ Basecamp users fetched', [
                'count' => $basecampUsers->count(),
            ]);

            // ✅ Step 4: Stop if no users
            if ($basecampUsers->isEmpty()) {
                \Log::warning('❌ No Basecamp users found for notification');
                return;
            }

            // ✅ Step 5: Insert into iot_notifications for each Basecamp user
            $now = now();
            $insertData = [];
            $fromUserId = auth()->id();

            foreach ($basecampUsers as $user) {
                $insertData[] = [
                    'title'               => $this->title,
                    'description'         => $this->description,
                    'to_bubble_user_id'   => $user->id,
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

            \Log::info('✅ Basecamp notifications inserted successfully', [
                'sendNotificationId' => $sendNotificationId,
                'total_users' => count($insertData),
            ]);

            // ✅ Step 6: Fire OneSignal push notifications (batch send)
            $oneSignal = app(\App\Services\OneSignalService::class);
            
            // Collect all valid FCM tokens
            $fcmTokens = collect($basecampUsers)
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
                            \Log::info('✅ OneSignal batch sent successfully (Basecamp)', [
                                'batch_number' => $batchIndex + 1,
                                'batch_size' => count($batch),
                                'onesignal_id' => $response['id'],
                                'total_sent' => $totalSent,
                            ]);
                        } else {
                            $errorMessage = $response['errors'][0] ?? 'Unknown error';
                            $totalErrors += count($batch);
                            
                            \Log::error('❌ OneSignal batch send failed (Basecamp)', [
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
                        
                        \Log::warning('⚠️ Removed invalid FCM tokens from database (Basecamp)', [
                            'invalid_count' => count($invalidTokens),
                            'tokens' => $invalidTokens,
                        ]);
                    }

                    \Log::info('📲 OneSignal push notifications summary (Basecamp)', [
                        'total_valid_tokens' => count($fcmTokens),
                        'total_sent' => $totalSent,
                        'total_errors' => $totalErrors,
                        'batches' => count($batches),
                        'invalid_tokens_removed' => count($invalidTokens),
                    ]);
                } catch (\Throwable $e) {
                    \Log::error('❌ OneSignal batch send exception (Basecamp)', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'token_count' => count($fcmTokens),
                    ]);
                }
            } else {
                \Log::warning('⚠️ No valid FCM tokens found for OneSignal push (Basecamp)', [
                    'total_users' => count($basecampUsers),
                    'users_with_tokens' => collect($basecampUsers)->filter(fn($u) => !empty($u->fcmToken))->count(),
                ]);
            }

            // Log activity
            try {
                \App\Services\ActivityLogService::log(
                    'notification',
                    'created',
                    "Sent notification: {$this->title} to {$basecampUsers->count()} Basecamp user(s)",
                    null,
                    null,
                    [
                        'notification_id' => $sendNotificationId,
                        'title' => $this->title,
                        'recipient_count' => $basecampUsers->count(),
                        'recipient_type' => 'basecamp_users',
                    ]
                );
            } catch (\Exception $e) {
                \Log::warning('Failed to log Basecamp notification activity: ' . $e->getMessage());
            }

        } catch (\Throwable $e) {
            \Log::error('❌ processAndSendForBasecampUsers failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function resetForm()
    {
        $this->title = '';
        $this->description = '';
        $this->links = '';
        $this->selectBasecampUsers = [];
    }
}
