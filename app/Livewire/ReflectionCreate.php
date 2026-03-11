<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Reflection;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReflectionCreate extends Component
{
    use WithFileUploads;

    public $topic;
    public $message;
    public $image;

    public $alertType = '';
    public $alertMessage = '';
    public $reflectionAdded = false; // Track if reflection was successfully added

    public function mount()
    {
        $user = auth()->user();
        
        // Reflections is accessible to super_admin (via Universal Setting > HPTM) 
        // and organisation_user|organisation_admin|basecamp|director (via standalone menu)
        $allowedRoles = ['super_admin', 'organisation_user', 'organisation_admin', 'basecamp', 'director'];
        
        // Check if user has any of the allowed roles (handle case where role might not exist)
        $hasAccess = false;
        foreach ($allowedRoles as $role) {
            try {
                if ($user->hasRole($role)) {
                    $hasAccess = true;
                    break;
                }
            } catch (\Spatie\Permission\Exceptions\RoleDoesNotExist $e) {
                // Role doesn't exist in database, skip it
                continue;
            }
        }
        
        if (!$hasAccess) {
            abort(403, 'Unauthorized access. This page is only available for authorised users.');
        }
    }

    public function submit()
    {
        $this->validate([
            'topic' => 'required|string|max:255',
            'message' => 'required|string',
            // 'image' => 'nullable|image|max:2048',
        ]);

        // $imageName = null;
        // if ($this->image) {
        //     $imageName = 'reflection_'.time().'.'.$this->image->getClientOriginalExtension();
        //     $this->image->storeAs('public/hptm_files', $imageName);
        // }

        $reflection = Reflection::create([
            'userId' => Auth::id(),
            'orgId' => Auth::user()->orgId,
            'topic' => $this->topic,
            'message' => $this->message,
            // 'image' => $imageName,
            'status' => 'new',
        ]);

        // Send notification to all admins about new reflection
        $this->sendNotificationToAdminOnCreate($reflection);

        // Log activity
        try {
            ActivityLogService::log(
                'reflection',
                'created',
                "Created reflection: {$this->topic}",
                $reflection,
                null,
                [
                    'topic' => $this->topic,
                    'message' => substr($this->message, 0, 100), // First 100 chars
                    'status' => 'new',
                ]
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to log reflection creation activity: ' . $e->getMessage());
        }

        $this->alertType = 'success';
        $this->alertMessage = 'Reflection created successfully!';
        $this->reflectionAdded = true; // Mark that reflection was added

        // Reset form
        $this->reset(['topic', 'message', 'image']);
    }

    private function sendNotificationToAdminOnCreate($reflection)
    {
        if (!$reflection) {
            \Log::warning('❌ Reflection is null in sendNotificationToAdminOnCreate');
            return;
        }

        $user = Auth::user();
        if (!$user) {
            \Log::warning('❌ User is null in sendNotificationToAdminOnCreate');
            return;
        }

        // Don't send notification if the user creating reflection is an admin
        if ($user->hasRole('super_admin')) {
            \Log::info('ℹ️ User is admin, skipping notification', [
                'user_id' => $user->id,
                'reflection_id' => $reflection->id
            ]);
            return;
        }

        $userName = $user->name ?? 'User';

        // Get all active super_admin users
        try {
            $adminIds = User::role('super_admin', 'web')
                ->whereIn('status', ['active_verified', 'active_unverified'])
                ->pluck('id')
                ->toArray();
            
            \Log::info('📧 Admin IDs found for reflection notification', [
                'reflection_id' => $reflection->id,
                'admin_ids' => $adminIds,
                'count' => count($adminIds)
            ]);
        } catch (\Spatie\Permission\Exceptions\RoleDoesNotExist $e) {
            \Log::warning('⚠️ Role does not exist: super_admin, trying to create it', ['error' => $e->getMessage()]);
            
            // Try to create the role if it doesn't exist
            try {
                $role = \Spatie\Permission\Models\Role::firstOrCreate(
                    ['name' => 'super_admin', 'guard_name' => 'web']
                );
                
                // Now try to get admin users again
                $adminIds = User::role('super_admin', 'web')
                    ->where('status', 'Active')
                    ->pluck('id')
                    ->toArray();
                
                \Log::info('✅ Role created and admin IDs found', [
                    'reflection_id' => $reflection->id,
                    'admin_ids' => $adminIds,
                    'count' => count($adminIds)
                ]);
            } catch (\Exception $createException) {
                \Log::error('❌ Failed to create role or get admin users', [
                    'error' => $createException->getMessage()
                ]);
                $adminIds = [];
            }
        }

        if (empty($adminIds)) {
            \Log::warning('⚠️ No admin IDs found for reflection notification', [
                'reflection_id' => $reflection->id
            ]);
            return;
        }

        // Send notification to each admin
        foreach ($adminIds as $adminId) {
            $adminUser = User::where('id', $adminId)
                ->whereIn('status', ['active_verified', 'active_unverified'])
                ->first();
            if (!$adminUser) continue;

            $notificationTitle = 'New Reflection Created';
            $notificationDescription = "{$userName} has created a new reflection: {$reflection->topic}";

            // Get notification badge count
            $totbadge = \App\Models\IotNotification::where('to_bubble_user_id', $adminId)
                ->where('archive', false)
                ->where('status', 'Active')
                ->where(function($q) {
                    $q->where(function($subQuery) {
                        $subQuery->where('notificationType', '!=', 'sentiment-reminder')
                                 ->orWhereNull('notificationType');
                    })
                    ->where(function($subQuery) {
                        $subQuery->where('title', '!=', 'Reminder: Please Update Your Sentiment Index')
                                 ->orWhereNull('title');
                    });
                })
                ->count();

            $notificationArray = [
                'to_bubble_user_id' => $adminId,
                'from_bubble_user_id' => $user->id,
                'title' => $notificationTitle,
                'description' => $notificationDescription,
                'notificationType' => 'reflectionChat',
                'notificationLinks' => route('admin.reflections.index'), // Link to reflection list
                'status' => 'Active',
                'archive' => false,
                'created_at' => now(),
                'updated_at' => now()
            ];

            try {
                $notificationId = DB::table('iot_notifications')->insertGetId($notificationArray);
                
                \Log::info('✅ Notification created for admin', [
                    'notification_id' => $notificationId,
                    'admin_id' => $adminId,
                    'reflection_id' => $reflection->id,
                    'title' => $notificationTitle
                ]);
            } catch (\Exception $e) {
                \Log::error('❌ Failed to insert notification', [
                    'admin_id' => $adminId,
                    'reflection_id' => $reflection->id,
                    'error' => $e->getMessage(),
                    'notification_array' => $notificationArray
                ]);
                continue;
            }

            // Send push notification via OneSignal if admin has fcmToken
            if (!empty($adminUser->fcmToken) && preg_match('/^[a-z0-9-]{8,}$/i', $adminUser->fcmToken)) {
                try {
                    $oneSignal = app(\App\Services\OneSignalService::class);
                    $oneSignal->sendNotification(
                        $notificationTitle,
                        $notificationDescription,
                        [$adminUser->fcmToken]
                    );
                    \Log::info('✅ OneSignal notification sent to admin on reflection create', [
                        'admin_id' => $adminId,
                        'reflection_id' => $reflection->id,
                    ]);
                } catch (\Throwable $e) {
                    \Log::error('❌ Failed to send OneSignal notification to admin on reflection create', [
                        'admin_id' => $adminId,
                        'reflection_id' => $reflection->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    public function render()
    {
        return view('livewire.reflection-create')->layout('layouts.app');
    }
}