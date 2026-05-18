<?php

namespace App\Livewire;

use App\Mail\IOTSentMsgMail;
use App\Models\IotFeedback;
use App\Models\IotMessage;
use App\Models\IotNotification;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\WithFileUploads;

class OffloadingChat extends Component
{
    use WithFileUploads;

    public $feedbackId;
    public $feedback;
    public $messages = [];
    public $newMessage = '';
    public $newFile;
    public $filePreview = null;
    public $fileType = null;
    public $alertType = '';
    public $alertMessage = '';

    public function mount($feedbackId)
    {
        $this->feedbackId = $feedbackId;
        $this->loadFeedback();
        $this->loadMessages();
    }

    public function loadFeedback()
    {
        $user = Auth::user();

        $this->feedback = IotFeedback::where('id', $this->feedbackId)
            ->where('userId', $user->id)
            ->firstOrFail();
    }

    public function loadMessages()
    {
        $messages = IotMessage::where('feedbackId', $this->feedbackId)
            ->where('status', 'Active')
            ->with(['sender', 'recipient'])
            ->orderBy('id', 'asc')
            ->get();

        $this->messages = $messages->map(function ($msg) {
            $sender = User::find($msg->sendFrom);
            $fileExtension = $msg->file ? strtolower(pathinfo($msg->file, PATHINFO_EXTENSION)) : null;
            $isVideo = $fileExtension && in_array($fileExtension, ['mp4', 'mov', 'avi', 'wmv', 'flv', 'webm']);

            return [
                'id' => $msg->id,
                'sendFrom' => $msg->sendFrom,
                'sendTo' => $msg->sendTo,
                'message' => $msg->message,
                'file' => $msg->file ? asset('uploads/iot_files/' . $msg->file) : null,
                'fileType' => $isVideo ? 'video' : ($msg->file ? 'image' : null),
                'created_at' => $msg->created_at->format('d M Y, h:i A'),
                'sender_name' => $sender ? $sender->name : 'Unknown',
                'sender_photo' => $sender && $sender->profile_photo_path
                    ? asset('storage/' . $sender->profile_photo_path)
                    : null,
                'isMe' => $msg->sendFrom == Auth::id(),
                'isAdmin' => $sender ? $sender->hasAnyRole(['super_admin', 'organisation_admin']) : false,
            ];
        })->toArray();
    }

    public function updatedNewFile()
    {
        if ($this->newFile) {
            $this->validate(['newFile' => 'file|mimes:jpeg,jpg,png,gif,mp4,mov,avi,wmv,flv,webm|max:10240']);

            $extension = strtolower($this->newFile->getClientOriginalExtension());
            $this->fileType = in_array($extension, ['mp4', 'mov', 'avi', 'wmv', 'flv', 'webm']) ? 'video' : 'image';
            $this->filePreview = $this->newFile->temporaryUrl();
        }
    }

    public function removeFile()
    {
        $this->newFile = null;
        $this->filePreview = null;
        $this->fileType = null;
    }

    public function sendMessage()
    {
        $user = Auth::user();

        if (!$this->newMessage && !$this->newFile) {
            $this->alertType = 'error';
            $this->alertMessage = 'Please enter a message or attach a file.';
            return;
        }

        try {
            $fileFilename = null;

            if ($this->newFile) {
                $extension = $this->newFile->getClientOriginalExtension();
                $filename = 'iot_' . time() . '.' . $extension;
                $path = public_path('uploads/iot_files');

                if (!file_exists($path)) {
                    mkdir($path, 0755, true);
                }

                $tempPath = $this->newFile->getRealPath();
                $destinationPath = $path . '/' . $filename;

                if ($tempPath && file_exists($tempPath)) {
                    copy($tempPath, $destinationPath);
                    $fileFilename = $filename;
                } else {
                    $storedPath = $this->newFile->storeAs('iot_files', $filename, 'public');
                    if ($storedPath) {
                        $fileFilename = $filename;
                    }
                }
            }

            // Find the admin to send to: super_admin first, then org admin
            $adminUser = User::whereHas('roles', function ($q) {
                $q->where('name', 'super_admin');
            })->first();

            if (!$adminUser && $user->orgId) {
                $adminUser = User::where('orgId', $user->orgId)
                    ->whereHas('roles', function ($q) {
                        $q->where('name', 'organisation_admin');
                    })->first();
            }

            $sendToUserId = $adminUser ? $adminUser->id : $user->id;

            IotMessage::create([
                'feedbackId' => $this->feedbackId,
                'message' => $this->newMessage ?: null,
                'sendTo' => $sendToUserId,
                'sendFrom' => $user->id,
                'file' => $fileFilename,
                'status' => 'Active',
            ]);

            if ($adminUser) {
                \App\Models\IotAdminChatMessage::create([
                    'feedbackId' => $this->feedbackId,
                    'message' => $this->newMessage ?: null,
                    'sendTo' => $adminUser->id,
                    'sendFrom' => $user->id,
                    'file' => $fileFilename,
                    'status' => 'Active',
                ]);
            }

            // In-app notification to admin
            if ($adminUser) {
                IotNotification::create([
                    'title' => 'New Offloading Chat Message',
                    'description' => $user->name . ' sent a message on Feedback #' . $this->feedbackId,
                    'to_bubble_user_id' => $adminUser->id,
                    'from_bubble_user_id' => $user->id,
                    'notificationType' => 'offloading-chat',
                    'notificationLinks' => route('admin.iot.chatbox', ['feedbackId' => $this->feedbackId]),
                    'status' => 'Active',
                    'archive' => false,
                ]);

                // Email notification to admin
                try {
                    $imageUrl = $fileFilename ? url('uploads/iot_files/' . $fileFilename) : null;
                    Mail::to($adminUser->email)->send(
                        new IOTSentMsgMail($user, $this->feedback->message, $this->newMessage, $imageUrl)
                    );
                } catch (\Exception $e) {
                    Log::error('Offloading chat email to admin failed: ' . $e->getMessage());
                }
            }

            $this->newMessage = '';
            $this->newFile = null;
            $this->filePreview = null;
            $this->fileType = null;
            $this->alertType = 'success';
            $this->alertMessage = 'Message sent successfully!';

            $this->loadMessages();
        } catch (\Exception $e) {
            $this->alertType = 'error';
            $this->alertMessage = 'Failed to send message: ' . $e->getMessage();
            Log::error('Offloading chat error: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.offloading-chat')
            ->layout('layouts.app', [
                'header' => 'Offloading Chat',
            ]);
    }
}

