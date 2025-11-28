<?php

namespace App\Livewire\User;

use Livewire\Component;
use App\Models\IotNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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

        // Mark notification as archived when clicked (for all action notifications)
        $notificationType = trim($notification->notificationType ?? '');
        $notificationLinks = trim($notification->notificationLinks ?? '');
        $title = trim($notification->title ?? '');
        
        // Debug logging
        Log::info('Notification clicked', [
            'id' => $id,
            'type' => $notificationType,
            'links' => $notificationLinks,
            'archive' => $notification->archive,
            'title' => $title,
        ]);

        // Determine if notification should be archived
        // Always archive: sentiment-reminder, weekly-report, monthly-report, reflectionChat, custom notification
        // Also archive if it has notificationLinks (actionable notifications)
        // Also archive if title contains "Feedback" (catch Feedback notifications)
        $shouldArchive = in_array($notificationType, [
            'sentiment-reminder', 
            'weekly-report', 
            'monthly-report', 
            'reflectionChat',
            'custom notification'
        ]) || !empty($notificationLinks) || stripos($title, 'feedback') !== false;

        // Archive the notification if it should be archived
        if ($shouldArchive && !$notification->archive) {
            $notification->update(['archive' => true]);
            Log::info('Notification archived', ['id' => $id, 'type' => $notificationType]);
            // Refresh the notification object to get updated archive status
            $notification->refresh();
        }

        // Handle navigation based on notification type
        $redirectUrl = $this->getNotificationRedirectUrl($notification);
        
        Log::info('Redirect URL determined', [
            'id' => $id,
            'redirectUrl' => $redirectUrl,
        ]);

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
            
            // Use Livewire's redirect method for internal routes
            return $this->redirect($redirectUrl, navigate: true);
        }

        // If no redirect URL and notification was archived, clear selection and reload
        if ($shouldArchive) {
            $this->selectedNotification = null;
            $this->loadNotifications();
            $this->dispatch('refreshNotificationBadge');
            return;
        }

        // If notification shouldn't be archived, just select it for viewing
        $this->selectedNotification = $notification;
        $this->loadNotifications();
        $this->dispatch('refreshNotificationBadge');
    }

    private function getNotificationRedirectUrl($notification)
    {
        $notificationType = $notification->notificationType ?? '';
        $notificationLinks = trim($notification->notificationLinks ?? '');
        $title = trim($notification->title ?? '');

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

        // Check if this is a Feedback notification (by title or type)
        $isFeedback = stripos($title, 'feedback') !== false || $notificationType === 'custom notification';
        
        Log::info('Feedback check', [
            'title' => $title,
            'type' => $notificationType,
            'isFeedback' => $isFeedback,
            'hasLinks' => !empty($notificationLinks),
        ]);

        // For Feedback notifications (custom notification type or title contains Feedback), check if it has notificationLinks
        if ($isFeedback) {
            if (!empty($notificationLinks)) {
                // Use the notificationLinks if available
                if (str_starts_with($notificationLinks, 'http://') || str_starts_with($notificationLinks, 'https://')) {
                    return $notificationLinks;
                }
                
                // Try to resolve as a route name if it contains a dot
                if (str_contains($notificationLinks, '.')) {
                    try {
                        return route($notificationLinks);
                    } catch (\Exception $e) {
                        // Route doesn't exist, continue to check if it's a valid path
                    }
                }
                
                // For paths starting with /, check if it's a valid route
                if (str_starts_with($notificationLinks, '/')) {
                    $knownRoutes = [
                        '/dashboard',
                        '/reflection-list',
                        '/user/notifications',
                        '/user-profile',
                        '/myteam',
                        '/offloading',
                        '/team-feedback',
                        '/hptm',
                    ];
                    
                    if (in_array($notificationLinks, $knownRoutes)) {
                        return $notificationLinks;
                    }
                    
                    // Try to match any valid route pattern (more flexible)
                    try {
                        $request = \Illuminate\Http\Request::create($notificationLinks, 'GET');
                        $route = \Illuminate\Support\Facades\Route::getRoutes()->match($request);
                        // If route exists, return it (even without a name)
                        if ($route) {
                            return $notificationLinks;
                        }
                    } catch (\Illuminate\Routing\Exceptions\UrlGenerationException $e) {
                        // Route doesn't exist, continue
                    } catch (\Symfony\Component\Routing\Exception\RouteNotFoundException $e) {
                        // Route not found, continue
                    } catch (\Exception $e) {
                        // Other errors, try to use the link anyway if it looks valid
                        if (preg_match('/^\/[a-z0-9\-_\/]+$/i', $notificationLinks)) {
                            return $notificationLinks;
                        }
                    }
                }
                
                // If notificationLinks doesn't start with /, try to treat it as a route name
                if (!str_starts_with($notificationLinks, '/') && !str_starts_with($notificationLinks, 'http')) {
                    try {
                        return route($notificationLinks);
                    } catch (\Exception $e) {
                        // Not a valid route name, try as a path
                        if (preg_match('/^[a-z0-9\-_\/]+$/i', $notificationLinks)) {
                            return '/' . ltrim($notificationLinks, '/');
                        }
                    }
                }
            }
            // For custom notifications without links, redirect to dashboard
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
                    '/offloading',
                ];
                
                if (in_array($notificationLinks, $knownRoutes)) {
                    return $notificationLinks;
                }
            }
        }

        // Fallback: If title contains "Feedback" and no link, redirect to dashboard
        if (stripos($title, 'feedback') !== false && empty($notificationLinks)) {
            return route('dashboard');
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
