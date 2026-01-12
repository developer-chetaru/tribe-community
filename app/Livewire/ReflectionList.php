<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\{Reflection, ReflectionMessage, Organisation, Office, User};
use App\Services\SubscriptionService;
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
    public $newChatImages = [];
    public $maxFiles = 10; // Maximum number of files allowed

    public $chatMessages = [];
    public $adminIds = [];

    protected $paginationTheme = 'tailwind';
    protected $listeners = ['refreshReflections' => '$refresh','statusConfirmedResolved' => 'statusConfirmedResolved','statusCancelResolved' => 'statusCancelResolved',];
    
    // Handle Livewire upload errors
    public function updatedNewChatImages($value)
    {
        // This method is called when files are uploaded
        // If upload fails, Livewire will handle it, but we can add custom logic here if needed
    }

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

        // Load super admin IDs (only if super_admin role exists)
        try {
            $this->adminIds = $user->hasRole('super_admin')
                ? User::role('super_admin', 'web')->where('status', 'Active')->pluck('id')->toArray()
                : [];
        } catch (\Spatie\Permission\Exceptions\RoleDoesNotExist $e) {
            // Role doesn't exist in database, set empty array
            $this->adminIds = [];
        }

        // If user is allowed to create reflections and has none, redirect to create form
        if ($user->hasAnyRole(['organisation_user', 'organisation_admin', 'basecamp', 'director'])) {
            $hasReflections = Reflection::where('userId', $user->id)->exists();
            if (!$hasReflections) {
                return redirect()->route('reflection.create');
            }
        }
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
        $user = Auth::user();
        $this->reflectionId = $reflectionId;
        $reflection = Reflection::find($reflectionId);

        if (!$reflection) {
            $this->alertType = 'error';
            $this->alertMessage = 'Reflection not found.';
            return;
        }

        // Security check: basecamp, organisation_user, and organisation_admin (team lead) can only access their own reflections
        if (($user->hasRole('basecamp') || $user->hasRole('organisation_user') || $user->hasRole('organisation_admin') || $user->hasRole('director')) && $reflection->userId !== $user->id) {
            $this->alertType = 'error';
            $this->alertMessage = 'You do not have permission to access this reflection.';
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
        
        // Clear any previous error messages when opening chat
        $this->alertMessage = '';
        $this->alertType = '';
    }

    public function loadChatMessages()
    {
        $user = Auth::user();
        
        // Security check: basecamp, organisation_user, and organisation_admin (team lead) can only load messages for their own reflections
        $reflection = Reflection::find($this->reflectionId);
        if ($reflection && ($user->hasRole('basecamp') || $user->hasRole('organisation_user') || $user->hasRole('organisation_admin')) && $reflection->userId !== $user->id) {
            $this->chatMessages = [];
            return;
        }

        $messages = ReflectionMessage::where('reflectionId', $this->reflectionId)
            ->with('user')
            ->orderBy('id', 'asc')
            ->get();

        // Use safe timezone helper to prevent invalid timezone errors
        $userTimezone = \App\Helpers\TimezoneHelper::getUserTimezone($user);

        $this->chatMessages = $messages->map(function ($msg) use ($userTimezone) {
            $dt = new DateTime($msg->created_at, new DateTimeZone('UTC'));
            $dt->setTimezone(\App\Helpers\TimezoneHelper::dateTimeZone($userTimezone));

            // Handle multiple files (JSON array) or single file
            $files = [];
            if ($msg->file) {
                $decoded = json_decode($msg->file, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Multiple files stored as JSON array
                    foreach ($decoded as $fileName) {
                        $files[] = url('storage/hptm_files/' . $fileName);
                    }
                } else {
                    // Single file stored as string
                    $files[] = url('storage/hptm_files/' . $msg->file);
                }
            }

            return [
                'from' => $msg->sendFrom,
                'message' => $msg->message,
                'image' => !empty($files) ? $files[0] : null, // Keep for backward compatibility
                'images' => $files, // New: array of all files
                'time' => $dt->format('Y-m-d H:i A'),
                'user_profile_photo' => $msg->user && $msg->user->profile_photo_path
                    ? asset('storage/' . $msg->user->profile_photo_path)
                    : null,
                'user_name' => $msg->user ? $msg->user->name : 'Unknown',
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
    
    public function removeFile($index)
    {
        if (isset($this->newChatImages[$index])) {
            unset($this->newChatImages[$index]);
            $this->newChatImages = array_values($this->newChatImages); // Re-index array
        }
    }

    public function sendChatMessage()
    {
        $user = Auth::user();
        $this->sendFrom = $user->id;

        // Check if message or files are provided
        $hasMessage = !empty(trim($this->newChatMessage ?? ''));
        
        // Check for files - handle both single file (backward compatibility) and multiple files
        $hasFiles = false;
        $fileCount = 0;
        
        // Check for multiple files (new way)
        // Livewire file uploads: files are available as TemporaryUploadedFile objects
        // Simple check: if array exists and has items, files are attached
        if (!empty($this->newChatImages)) {
            if (is_array($this->newChatImages)) {
                // Count non-null, non-empty items in array
                // Even if items are not fully uploaded objects yet, if array has items, files are being uploaded
                $nonEmptyFiles = array_filter($this->newChatImages, function($file) {
                    return $file !== null && $file !== '';
                });
                $fileCount = count($nonEmptyFiles);
                $hasFiles = $fileCount > 0;
            } elseif (is_object($this->newChatImages)) {
                // Single file object (not in array)
                $hasFiles = true;
                $fileCount = 1;
            }
        }
        
        // Also check for single file (backward compatibility)
        if (!$hasFiles && !empty($this->newChatImage)) {
            $hasFiles = true;
            $fileCount = 1;
        }
        
        // Only show error if BOTH message text is blank AND no files are attached
        // Error should show ONLY when: message is empty AND files are not attached AND user clicks send
        // If files are attached (even without message), allow sending
        // If message is written (even without files), allow sending
        if (!$hasMessage && !$hasFiles) {
            // Clear any previous success messages
            $this->alertType = 'error';
            $this->alertMessage = 'Please write a message or attach a file and then click on send.';
            return;
        }
        
        // Clear any previous error messages before processing successful send
        $this->alertMessage = '';
        $this->alertType = '';
        
        // Validate file count
        if ($hasFiles && $fileCount > $this->maxFiles) {
            $this->alertType = 'error';
            $this->alertMessage = "You can attach maximum {$this->maxFiles} files at once. You selected {$fileCount} files.";
            return;
        }
        
        // Validate file sizes (25MB = 25 * 1024 * 1024 bytes)
        if ($hasFiles) {
            $maxSizeBytes = 25 * 1024 * 1024; // 25MB in bytes
            $filesToCheck = [];
            
            // Collect all files to check
            if (is_array($this->newChatImages)) {
                $filesToCheck = array_filter($this->newChatImages, function($f) { return $f !== null && $f !== ''; });
            } elseif (is_object($this->newChatImages)) {
                $filesToCheck = [$this->newChatImages];
            }
            
            if (empty($filesToCheck) && !empty($this->newChatImage)) {
                $filesToCheck = [$this->newChatImage];
            }
            
            foreach ($filesToCheck as $file) {
                if ($file && is_object($file)) {
                    try {
                        // Get file size - Livewire TemporaryUploadedFile has getSize() method
                        $fileSize = null;
                        if (method_exists($file, 'getSize')) {
                            $fileSize = $file->getSize();
                        } elseif (method_exists($file, 'getClientSize')) {
                            $fileSize = $file->getClientSize();
                        } elseif (method_exists($file, 'getRealPath') && file_exists($file->getRealPath())) {
                            $fileSize = filesize($file->getRealPath());
                        } elseif (property_exists($file, 'size')) {
                            $fileSize = $file->size;
                        }
                        
                        if ($fileSize !== null && $fileSize > $maxSizeBytes) {
                            $fileName = method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : 'file';
                            $fileSizeMB = round($fileSize / (1024 * 1024), 2);
                            $this->alertType = 'error';
                            $this->alertMessage = "File '{$fileName}' is too large ({$fileSizeMB}MB). Maximum file size is 25MB.";
                            return;
                        }
                    } catch (\Exception $e) {
                        // Continue if we can't check size
                        continue;
                    }
                }
            }
        }

        if (!$this->reflectionId || !$this->sendTo) {
            $this->alertType = 'error';
            $this->alertMessage = 'Invalid conversation.';
            return;
        }

        // Security check: basecamp, organisation_user, and organisation_admin (team lead) can only send messages to their own reflections
        $reflection = Reflection::find($this->reflectionId);
        if ($reflection && ($user->hasRole('basecamp') || $user->hasRole('organisation_user') || $user->hasRole('organisation_admin')) && $reflection->userId !== $user->id) {
            $this->alertType = 'error';
            $this->alertMessage = 'You do not have permission to send messages to this reflection.';
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

        // Handle multiple files (new way)
        $fileNames = [];
        if (!empty($this->newChatImages)) {
            // Handle array of files
            if (is_array($this->newChatImages)) {
                foreach ($this->newChatImages as $index => $file) {
                    if ($file) {
                        try {
                            // Check if file has the required methods
                            if (method_exists($file, 'getClientOriginalExtension')) {
                                $extension = $file->getClientOriginalExtension();
                            } elseif (method_exists($file, 'extension')) {
                                $extension = $file->extension();
                            } else {
                                // Try to get extension from path
                                $path = method_exists($file, 'getRealPath') ? $file->getRealPath() : (property_exists($file, 'path') ? $file->path : '');
                                $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'file';
                            }
                            
                            $fileName = 'hptmChat_' . time() . '_' . uniqid() . '_' . $index . '.' . $extension;
                            
                            // Store file
                            if (method_exists($file, 'storeAs')) {
                                $file->storeAs('public/hptm_files', $fileName);
                            } elseif (method_exists($file, 'store')) {
                                $storedPath = $file->store('public/hptm_files');
                                $fileName = basename($storedPath);
                            } else {
                                \Log::error('File object does not have storeAs or store method', ['file_type' => get_class($file)]);
                                continue;
                            }
                            
                            $fileNames[] = $fileName;
                        } catch (\Exception $e) {
                            \Log::error('File upload error for file ' . $index . ': ' . $e->getMessage(), [
                                'file_type' => is_object($file) ? get_class($file) : gettype($file),
                                'trace' => $e->getTraceAsString()
                            ]);
                        }
                    }
                }
            } elseif (is_object($this->newChatImages)) {
                // Single file object (not in array)
                try {
                    if (method_exists($this->newChatImages, 'getClientOriginalExtension')) {
                        $extension = $this->newChatImages->getClientOriginalExtension();
                    } else {
                        $extension = 'file';
                    }
                    
                    $fileName = 'hptmChat_' . time() . '_' . uniqid() . '.' . $extension;
                    
                    if (method_exists($this->newChatImages, 'storeAs')) {
                        $this->newChatImages->storeAs('public/hptm_files', $fileName);
                    } elseif (method_exists($this->newChatImages, 'store')) {
                        $storedPath = $this->newChatImages->store('public/hptm_files');
                        $fileName = basename($storedPath);
                    }
                    
                    $fileNames[] = $fileName;
                } catch (\Exception $e) {
                    \Log::error('Single file upload error: ' . $e->getMessage());
                }
            }
        }
        
        // Handle single file (backward compatibility)
        if (empty($fileNames) && !empty($this->newChatImage)) {
            try {
                $extension = method_exists($this->newChatImage, 'getClientOriginalExtension') 
                    ? $this->newChatImage->getClientOriginalExtension() 
                    : 'file';
                    
                $fileName = 'hptmChat_' . time() . '_' . uniqid() . '.' . $extension;
                
                if (method_exists($this->newChatImage, 'storeAs')) {
                    $this->newChatImage->storeAs('public/hptm_files', $fileName);
                } elseif (method_exists($this->newChatImage, 'store')) {
                    $storedPath = $this->newChatImage->store('public/hptm_files');
                    $fileName = basename($storedPath);
                }
                
                $fileNames[] = $fileName;
            } catch (\Exception $e) {
                \Log::error('Backward compatibility file upload error: ' . $e->getMessage());
            }
        }
        
        // Store files in database
        if (!empty($fileNames)) {
            // Store files as JSON array if multiple, or single string if one file
            if (count($fileNames) === 1) {
                $data['file'] = $fileNames[0];
            } else {
                $data['file'] = json_encode($fileNames);
            }
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

        // Clear form fields and error messages FIRST
        $this->alertMessage = '';
        $this->alertType = '';
        $this->newChatMessage = '';
        $this->newChatImage = null;
        $this->newChatImages = [];

        // Reload messages and scroll to bottom
        $this->loadChatMessages();
        $this->dispatch('scrollToBottom');
        
        // Force clear any remaining error state and ensure UI updates
        $this->resetErrorBag();
        $this->dispatch('message-sent'); // Custom event to ensure UI updates
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
            // In Livewire 3, use dispatch() instead of dispatchBrowserEvent
            $this->dispatch('confirmResolved');
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
        
        // Dispatch browser event for Alpine.js to update button state
        // In Livewire 3, dispatch() creates browser events
        $this->dispatch('reflectionStatusUpdated', status: $status);

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

    // Helper method to get preview URL for a file
    public function getFilePreviewUrl($file)
    {
        if (!$file || !is_object($file)) {
            return null;
        }
        
        try {
            // Try Livewire's temporaryUrl method
            if (method_exists($file, 'temporaryUrl')) {
                return $file->temporaryUrl();
            }
            
            // Try getRealPath for local files
            if (method_exists($file, 'getRealPath')) {
                $realPath = $file->getRealPath();
                if ($realPath && file_exists($realPath)) {
                    // For Livewire temp files, we need to use the Livewire endpoint
                    // But for now, return null and let the view handle it
                    return null;
                }
            }
        } catch (\Exception $e) {
            \Log::debug('Preview URL error: ' . $e->getMessage());
        }
        
        return null;
    }

    public function render()
    {
        $user = Auth::user();

        $query = Reflection::with(['user.office', 'user.organisation', 'user.department']);

        // Load organisation list
            $this->organisationsList = Organisation::where('status', '1') ->select('id', 'name') ->get();

        if ($user->hasRole('organisation_user') || $user->hasRole('basecamp') || $user->hasRole('organisation_admin') || $user->hasRole('director')) {
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
