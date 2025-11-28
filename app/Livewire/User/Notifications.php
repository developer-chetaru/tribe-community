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
        $notification = IotNotification::find($id);
        
        if (!$notification) {
            return;
        }

        // Mark notification as archived when clicked (for reminder/action notifications)
        $notificationType = $notification->notificationType ?? '';
        $shouldArchive = in_array($notificationType, ['sentiment-reminder', 'weekly-report', 'monthly-report', 'reflectionChat']) 
                        || !empty($notification->notificationLinks);

        if ($shouldArchive && !$notification->archive) {
            $notification->update(['archive' => true]);
        }

        // Handle navigation based on notification type
        $redirectUrl = $this->getNotificationRedirectUrl($notification);

        if ($redirectUrl) {
            // Reload notifications before redirect
            $this->loadNotifications();
            $this->dispatch('refreshNotificationBadge');
            
            // Check if it's an external URL (starts with http:// or https://)
            if (str_starts_with($redirectUrl, 'http://') || str_starts_with($redirectUrl, 'https://')) {
                $appUrl = config('app.url', '');
                // If it's not from our app domain, use redirect()->away()
                if (!empty($appUrl) && !str_starts_with($redirectUrl, $appUrl)) {
                    return redirect()->away($redirectUrl);
                }
            }
            
            return redirect($redirectUrl);
        }

        // If no navigation needed, just select the notification for viewing
        $this->selectedNotification = $notification;
        $this->loadNotifications();
        $this->dispatch('refreshNotificationBadge');
    }

    private function getNotificationRedirectUrl($notification)
    {
        $notificationType = $notification->notificationType ?? '';
        $notificationLinks = trim($notification->notificationLinks ?? '');

        // For sentiment-reminder notifications, navigate to dashboard
        if ($notificationType === 'sentiment-reminder') {
            return route('dashboard');
        }

        // For reflectionChat notifications, navigate to reflection list
        if ($notificationType === 'reflectionChat') {
            return route('admin.reflections.index');
        }

        // For weekly-report and monthly-report, navigate to dashboard
        if (in_array($notificationType, ['weekly-report', 'monthly-report'])) {
            return route('dashboard');
        }

        // For other notifications with links, only navigate if it's a valid URL
        if (!empty($notificationLinks)) {
            // Only redirect if it's a full URL (http:// or https://)
            if (str_starts_with($notificationLinks, 'http://') || str_starts_with($notificationLinks, 'https://')) {
                return $notificationLinks;
            }
            
            // Try to resolve as a route name if it contains a dot (e.g., 'dashboard' or 'admin.reflections.index')
            if (str_contains($notificationLinks, '.')) {
                try {
                    return route($notificationLinks);
                } catch (\Exception $e) {
                    // Route doesn't exist, don't redirect
                    return null;
                }
            }
            
            // For paths starting with /, only redirect if it's a known valid route
            // Otherwise, just show the notification in detail panel
            if (str_starts_with($notificationLinks, '/')) {
                // List of known valid routes
                $knownRoutes = [
                    '/dashboard',
                    '/reflection-list',
                    '/user/notifications',
                    '/user-profile',
                    '/myteam',
                ];
                
                if (in_array($notificationLinks, $knownRoutes)) {
                    return $notificationLinks;
                }
            }
        }

        // Don't redirect if we don't have a valid URL or route
        return null;
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
