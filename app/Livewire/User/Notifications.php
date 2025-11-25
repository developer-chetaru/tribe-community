<?php

namespace App\Livewire\User;

use Livewire\Component;
use App\Models\IotNotification;
use Illuminate\Support\Facades\Auth;

class Notifications extends Component
{
    public $tab = 'active';
    public $notifications = [];
    public $notificationCount = 0;

    // public $showModal = false;
    public $selectedNotification = null;

    public function mount()
    {
        $this->loadNotifications();
    }

    public function switchTab($tab)
{
    $this->tab = $tab;
    $this->selectedNotification = null; // Reset when switching tab
    $this->loadNotifications();
}

    public function loadNotifications()
    {
        $userId = Auth::id();

        $this->notificationCount = IotNotification::where('to_bubble_user_id', $userId)
            ->where('archive', false)
            ->count();

        $query = IotNotification::where('to_bubble_user_id', $userId)->orderBy('created_at', 'desc');

        if ($this->tab === 'active') {
            $this->notifications = $query->where('archive', false)->get();
        } else {
            $this->notifications = $query->where('archive', true)->get();
        }
    }

    public function archiveAll()
{
    IotNotification::where('to_bubble_user_id', Auth::id())
        ->where('archive', false)
        ->update(['archive' => true]);

    $this->selectedNotification = null; // Reset detail view
    $this->loadNotifications();
    $this->dispatch('refreshNotificationBadge');
}

    public function moveToArchive($id)
{
    $note = IotNotification::find($id);
    if ($note) {
        $note->update(['archive' => true]);
        $this->selectedNotification = null; // Close right panel since item moved
        $this->loadNotifications();
        $this->dispatch('refreshNotificationBadge');
    }
}

    public function openNotification($id)
{
    $this->selectedNotification = IotNotification::find($id);
}

    // public function closeModal()
    // {
    //     $this->showModal = false;
    //     $this->selectedNotification = null;
    // }

    public function render()
    {
        return view('livewire.user.notifications')->layout('layouts.app');
    }
}
