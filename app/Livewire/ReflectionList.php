<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\{Reflection, ReflectionMessage, Organisation, Office, User};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use DateTime;
use DateTimeZone;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReflectionResolvedMail;

class ReflectionList extends Component
{
    use WithPagination, WithFileUploads;

    public $searchTopic = '';

    public $orgId = '';
    public $officeId = '';
    public $organisationsList = [];
    public $officesList = [];

    public $reflectionId;
    public $sendTo;
    public $sendFrom;
    public $chatMessage;
    public $chatFile;

    public $showChatModal = false;
    public $newChatMessage = '';
    public $newChatImage;

    public $chatMessages = [];
    public $adminIds = [];

    protected $paginationTheme = 'tailwind';
    protected $listeners = ['refreshReflections' => '$refresh','statusConfirmedResolved' => 'statusConfirmedResolved','statusCancelResolved' => 'statusCancelResolved',];

    public $cheatMessage = '';
    public $showCheatModal = false;

    public $alertType = '';
    public $alertMessage = '';

    public $showDeleteModal = false;
    public $deleteReflectionId = null;

    public $selectedReflection = [];

    protected $queryString = ['searchTopic', 'orgId', 'officeId'];

    public function mount($orgId = null, $officeId = null)
    {
        $user = Auth::user();

        $availableRoles = Role::whereIn('name', ['super_admin', 'organisation_user'])
            ->where('guard_name', 'web')
            ->pluck('name')
            ->toArray();

        if (!$user->hasAnyRole($availableRoles)) {
            return redirect()->back();
        }

        $this->orgId = $orgId ? base64_decode($orgId) : '';
        $this->officeId = $officeId ? base64_decode($officeId) : '';

        // Load organisation list
        $this->organisationsList = Organisation::where('status', '1')
            ->select('id', 'name')
            ->get();

        // Load all offices initially or filtered if orgId exists
        $this->officesList = $this->orgId
            ? Office::where('organisation_id', $this->orgId)->get()
            : Office::all();

        // Load super admin IDs
        $this->adminIds = in_array('super_admin', $availableRoles)
            ? User::role('super_admin', 'web')->where('status', 'Active')->pluck('id')->toArray()
            : [];
    }

    public function updatingSearchTopic()
    {
        $this->resetPage();
    }

    public function updatedOrgId($value)
    {
        $this->officeId = ''; // reset selected office

        if ($value) {
            $this->officesList = Office::where('organisation_id', $value)->get();
        } else {
            $this->officesList = Office::all();
        }

        $this->resetPage(); // reset pagination if list filters change
    }

    public function updatedOfficeId()
    {
        $this->resetPage();
    }

    public function openChat($reflectionId)
    {
        $this->reflectionId = $reflectionId;
        $reflection = Reflection::find($reflectionId);

        if (!$reflection) {
            $this->alertType = 'error';
            $this->alertMessage = 'Reflection not found.';
            return;
        }

        $this->selectedReflection = [
            'id' => $reflection->id,
            'topic' => $reflection->topic,
            'message' => $reflection->message ?? '',
            'status' => $reflection->status ?? 'New',
        ];

        $this->sendTo = $reflection->userId;
        $this->loadChatMessages();
        $this->showChatModal = true;
    }

    public function loadChatMessages()
    {
        $messages = ReflectionMessage::where('reflectionId', $this->reflectionId)
            ->with('user')
            ->orderBy('id', 'asc')
            ->get();

        $userTimezone = Auth::user()->timezone ?? config('app.timezone');

        $this->chatMessages = $messages->map(function ($msg) use ($userTimezone) {
            $dt = new DateTime($msg->created_at, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone($userTimezone));

            return [
                'from' => $msg->sendFrom,
                'message' => $msg->message,
                'image' => $msg->file ? url('storage/hptm_files/' . $msg->file) : null,
                'time' => $dt->format('Y-m-d H:i A'),
                'user_profile_photo' => $msg->user && $msg->user->profile_photo_path
                    ? asset('storage/' . $msg->user->profile_photo_path)
                    : null,
            ];
        })->toArray();
    }

    public function pollChatMessages()
    {
        // This method will be called automatically every few seconds
        if ($this->showChatModal && $this->reflectionId) {
            $this->loadChatMessages();
        }
    }

    public function sendChatMessage()
    {
        $user = Auth::user();
        $this->sendFrom = $user->id;

        if (!$this->newChatMessage && !$this->newChatImage) {
            $this->alertType = 'error';
            $this->alertMessage = 'Please type a message or attach a file.';
            return;
        }

        if (!$this->reflectionId || !$this->sendTo) {
            $this->alertType = 'error';
            $this->alertMessage = 'Invalid conversation.';
            return;
        }

        $data = [
            'sendFrom'     => $this->sendFrom,
            'sendTo'       => $this->sendTo,
            'reflectionId' => $this->reflectionId,
            'status'       => 'Active',
            'created_at'   => now(),
            'updated_at'   => now(),
        ];

        if ($this->newChatMessage) {
            $data['message'] = trim($this->newChatMessage);
        }

        if ($this->newChatImage) {
            $fileName = 'hptmChat_' . time() . '.' . $this->newChatImage->getClientOriginalExtension();
            $this->newChatImage->storeAs('public/hptm_files', $fileName);
            $data['file'] = $fileName;
        }

        ReflectionMessage::insert($data);

        $this->sendNotificationToUser($this->sendTo, $this->reflectionId);

        if ($user->hasRole('super_admin')) {
            try {
                $recipient = \App\Models\User::find($this->sendTo);

                if ($recipient && !empty($recipient->fcmToken)) {
                    $oneSignal = app(\App\Services\OneSignalService::class);
                    
                    $title = $user->name ?? 'Admin';
                    $message = $this->newChatMessage 
                        ? trim($this->newChatMessage)
                        : 'You received a new message.';

                    $oneSignal->sendNotification(
                        $title,
                        $message,
                        [$recipient->fcmToken]
                    );

                    \Log::info('📲 OneSignal push sent successfully from Admin', [
                        'from' => $user->id,
                        'to'   => $recipient->id,
                        'token' => $recipient->fcmToken,
                    ]);
                } else {
                    \Log::warning('⚠️ OneSignal skipped — no fcmToken for recipient', [
                        'recipient_id' => $this->sendTo,
                    ]);
                }
            } catch (\Throwable $e) {
                \Log::error('❌ OneSignal send failed', [
                    'from_user' => $user->id,
                    'to_user'   => $this->sendTo,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        if (!empty($this->selectedReflection) && $this->selectedReflection['status'] !== 'resolved') {
            $this->updateReflectionStatus('inprogress');
        }

        $this->newChatMessage = null;
        $this->newChatImage = null;

        $this->loadChatMessages();
        $this->dispatch('scrollToBottom');
    }


    public function confirmDelete($encodedId)
    {
        $this->deleteReflectionId = $encodedId;
        $this->showDeleteModal = true;
    }

    public function deleteReflectionConfirmed()
    {
        if (!$this->deleteReflectionId) {
            $this->alertType = 'error';
            $this->alertMessage = 'No reflection selected.';
            return;
        }

        $this->deleteReflection($this->deleteReflectionId);
        $this->showDeleteModal = false;
        $this->deleteReflectionId = null;
    }

    public function deleteReflection($encodedId)
    {
        try {
            $reflectionId = base64_decode($encodedId);
            $reflection = Reflection::find($reflectionId);

            if (!$reflection) {
                $this->alertType = 'error';
                $this->alertMessage = 'Reflection not found.';
                return;
            }

            $messages = ReflectionMessage::where('reflectionId', $reflectionId)->get();
            foreach ($messages as $msg) {
                if (!empty($msg->file)) {
                    $filePath = storage_path('app/public/hptm_files/' . $msg->file);
                    if (file_exists($filePath)) @unlink($filePath);
                }
            }

            ReflectionMessage::where('reflectionId', $reflectionId)->delete();
            $reflection->delete();

            $this->alertType = 'success';
            $this->alertMessage = 'Reflection deleted successfully.';
        } catch (\Exception $e) {
            \Log::error('Reflection delete failed: ' . $e->getMessage());
            $this->alertType = 'error';
            $this->alertMessage = 'Something went wrong while deleting the reflection.';
        }
    }

    

    // Called when dropdown changes
    public function confirmStatusChange()
    {
        if (!isset($this->selectedReflection['id'])) return;

        $newStatus = $this->selectedReflection['status'];

        if ($newStatus === 'resolved') {
            $this->selectedReflection['status'] = $this->selectedReflection['status_original'] ?? 'inprogress';
            $this->dispatchBrowserEvent('confirmResolved');
            return;
        }

        $this->updateReflectionStatus($newStatus);
    }

    // ✅ Updated version of updateReflectionStatus()
    public function updateReflectionStatus($status)
    {
        if (!isset($this->selectedReflection['id'])) return;

        $reflection = \App\Models\Reflection::find($this->selectedReflection['id']);
        if (!$reflection) return;

        if (!in_array($status, ['new','inprogress','resolved'])) return;

        $reflection->status = $status;
        $reflection->save();

        $this->selectedReflection['status'] = $status;
        $this->selectedReflection['status_original'] = $status;

        $this->alertType = 'success';
        $this->alertMessage = 'Reflection status updated to ' . ucfirst($status);

        // ✅ Send Email only if Super Admin & status is resolved
        $user = Auth::user();
        if ($status === 'resolved' && $user && $user->hasRole('super_admin')) {
            try {
                $reflectionOwner = $reflection->user ?? null;

                if ($reflectionOwner && !empty($reflectionOwner->email)) {
                    Mail::to($reflectionOwner->email)
                        ->send(new ReflectionResolvedMail($reflection, $user));

                    \Log::info('📧 Reflection resolved email sent', [
                        'reflection_id' => $reflection->id,
                        'to' => $reflectionOwner->email,
                        'by' => $user->id,
                    ]);
                } else {
                    \Log::warning('⚠️ No email found for reflection owner', [
                        'reflection_id' => $reflection->id,
                    ]);
                }
            } catch (\Throwable $e) {
                \Log::error('❌ Failed to send reflection resolved email', [
                    'error' => $e->getMessage(),
                    'reflection_id' => $reflection->id,
                ]);
            }
        }
    }



// Confirmed resolved
public function statusConfirmedResolved()
{
    $this->updateReflectionStatus('resolved');
}

// Cancel resolved
public function statusCancelResolved()
{
    // Reset dropdown to previous status
    $this->selectedReflection['status'] = $this->selectedReflection['status_original'] ?? 'inprogress';
}

    public function render()
    {
        $user = Auth::user();

        $query = Reflection::with(['user.office', 'user.organisation', 'user.department']);

        // Load organisation list
            $this->organisationsList = Organisation::where('status', '1') ->select('id', 'name') ->get();

        if ($user->hasRole('organisation_user')) {
            $query->where('userId', $user->id);
        }

        if ($this->orgId) {
            $query->whereHas('user', fn($q) => $q->where('orgId', $this->orgId));
        }

        if ($this->officeId) {
            $query->whereHas('user', fn($q) => $q->where('officeId', $this->officeId));
        }

        if (!empty($this->searchTopic)) {
            $search = $this->searchTopic;
            $query->where('topic', 'like', "%$search%");
        }

        $reflectionListTbl = $query->orderByDesc('id')->paginate(5);

        $reflectionList = $reflectionListTbl->map(fn($r) => [
            'id' => $r->id,
            'userName' => $r->user?->name ?? '—',
            'topic' => $r->topic,
            'status' => $r->status,
            'message' => $r->message,
            'created_at' => $r->created_at,
            'organisation' => $r->user?->organisation?->name ?? '—',
            'office' => $r->user?->office?->name ?? '—',
            'department' => $r->user?->department?->department ?? '—',
            'userId' => $r->userId,
            'orgId' => $r->orgId,
        ]);

        return view('livewire.reflection-list', 
        [ 'reflectionListTbl' => $reflectionListTbl,
         'reflectionList' => $reflectionList, 
         'organisationsList' => $this->organisationsList,
         
          ])->layout('layouts.app');
    }

    private function sendNotificationToUser($toUserId, $reflectionId)
    {
        $user = User::where('id', $toUserId)->where('status', 'Active')->first();
        if (!$user) return;

        $totbadge = app('App\Http\Controllers\API\ApiDotController')
            ->getIotNotificationBadgeCount(['userId' => $toUserId]);

        $notificationArray = [
            'to_bubble_user_id' => $toUserId,
            'from_bubble_user_id' => $this->sendFrom,
            'title' => 'Reflection',
            'description' => 'You have received a new message',
            'reflectionId' => $reflectionId,
            'notificationType' => 'reflectionChat',
            'created_at' => now()
        ];

        $notificationId = DB::table('iot_notifications')->insertGetId($notificationArray);

        $notiArray = [
            'fcmToken' => $user->fcmToken,
            'title' => 'Reflection',
            'message' => 'You have received a new message',
            'totbadge' => $totbadge,
            'feedbackId' => $reflectionId,
            'notificationType' => 'reflectionChat',
            'deviceType' => $user->deviceType,
            'notificationId' => $notificationId
        ];

        app('App\Http\Controllers\Admin\CommonController')->sendFcmNotify($notiArray);
    }
}
