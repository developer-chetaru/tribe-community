<?php

namespace App\Livewire;

use App\Models\IotFeedback;
use App\Models\IotMessage;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class OffloadingList extends Component
{
    public $feedbacks = [];

    public function mount()
    {
        $this->loadFeedbacks();
    }

    public function loadFeedbacks()
    {
        $user = Auth::user();

        $this->feedbacks = IotFeedback::where('userId', $user->id)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($feedback) {
                $lastMessage = IotMessage::where('feedbackId', $feedback->id)
                    ->where('status', 'Active')
                    ->orderBy('id', 'desc')
                    ->first();

                $hasMessages = IotMessage::where('feedbackId', $feedback->id)
                    ->where('status', 'Active')
                    ->exists();

                // Determine display status
                $displayStatus = $feedback->status === 'Completed' ? 'Completed' :
                    ($hasMessages ? 'In Progress' : 'Awaiting Response');

                return [
                    'id' => $feedback->id,
                    'message' => $feedback->message,
                    'status' => $feedback->status,
                    'display_status' => $displayStatus,
                    'feedbackStatus' => $feedback->feedbackStatus,
                    'created_at' => $feedback->created_at->format('d M Y, h:i A'),
                    'last_message' => $lastMessage ? substr($lastMessage->message ?? 'Attachment', 0, 50) : null,
                    'last_message_date' => $lastMessage ? $lastMessage->created_at->format('d M Y, h:i A') : null,
                    'has_messages' => $hasMessages,
                    'has_file' => $lastMessage && $lastMessage->file ? true : false,
                ];
            })
            ->toArray();
    }

    public function render()
    {
        return view('livewire.offloading-list')
            ->layout('layouts.app', [
                'header' => 'My Offloading Feedback',
            ]);
    }
}

