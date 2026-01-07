<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;
use App\Services\OneSignalService;

class BaseCampUser extends Component
{
    use WithPagination;

    public $perPage = 12;
    public $activeTab = 'active'; 
    public $showDeleteModal = false;
    public $deleteUserId = null;
    public $deleteUserName = null;
    public $showViewModal = false;
    public $viewingUser = null;

    protected $paginationTheme = 'tailwind';

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function sendVerificationEmail($userId)
    {
        try {
            $user = User::findOrFail($userId);

            // Only send verification email if user is not active (not in active_verified or active_unverified)
            // Also check for old boolean true status for backward compatibility
            $isActive = in_array($user->status, ['active_verified', 'active_unverified']) 
                     || $user->status === true 
                     || $user->status === '1' 
                     || $user->status === 1;
                     
            if ($isActive) {
                session()->flash('message', 'User is already active. Verification email not needed.');
                session()->flash('type', 'info');
                return;
            }

            // GENERATE VERIFICATION LINK
            $expires = Carbon::now()->addMinutes(1440);
            $verificationUrl = URL::temporarySignedRoute(
                'user.verify',
                $expires,
                ['id' => $user->id]
            );

            // Load HTML template
            $emailBody = view('emails.verify-user-inline', [
                'user' => $user,
                'verificationUrl' => $verificationUrl,
            ])->render();

            // Send email via OneSignal
            $oneSignalService = new OneSignalService();
            $result = $oneSignalService->registerEmailUserFallback($user->email, $user->id, [
                'subject' => 'Activate Your Tribe365 Account',
                'body' => $emailBody,
            ]);

            if ($result) {
                session()->flash('message', 'Verification email sent successfully to ' . $user->email . '!');
                session()->flash('type', 'success');
                
                \Illuminate\Support\Facades\Log::info('Verification email sent via sendVerificationEmail', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'status' => $user->status,
                ]);
            } else {
                session()->flash('message', 'Failed to send email. Please check OneSignal configuration.');
                session()->flash('type', 'error');
                
                \Illuminate\Support\Facades\Log::error('Failed to send verification email via sendVerificationEmail', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'status' => $user->status,
                ]);
            }

        } catch (\Throwable $e) {
            session()->flash('message', 'Failed to send email: ' . $e->getMessage());
            session()->flash('type', 'error');
            
            \Illuminate\Support\Facades\Log::error('Exception in sendVerificationEmail', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function viewUser($userId)
    {
        try {
            $this->viewingUser = User::findOrFail($userId);
            $this->showViewModal = true;
        } catch (\Exception $e) {
            session()->flash('message', 'User not found: ' . $e->getMessage());
            session()->flash('type', 'error');
        }
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->viewingUser = null;
    }

    public function editUser($userId)
    {
        return redirect()->route('basecampuser.edit', ['id' => $userId]);
    }

    public function confirmDelete($userId)
    {
        $user = User::findOrFail($userId);
        $this->deleteUserId = $userId;
        $this->deleteUserName = $user->first_name . ' ' . $user->last_name;
        $this->showDeleteModal = true;
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->deleteUserId = null;
        $this->deleteUserName = null;
    }

    public function deleteUser()
    {
        if ($this->deleteUserId) {
            try {
                $user = User::findOrFail($this->deleteUserId);
                $userName = $user->first_name . ' ' . $user->last_name;
                
                // Delete user profile photo if exists
                if ($user->profile_photo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->profile_photo_path)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($user->profile_photo_path);
                }
                
                $user->delete();
                
                $this->showDeleteModal = false;
                $this->deleteUserId = null;
                $this->deleteUserName = null;
                
                session()->flash('message', 'User ' . $userName . ' deleted successfully!');
                session()->flash('type', 'success');
                
            } catch (\Throwable $e) {
                session()->flash('message', 'Failed to delete user: ' . $e->getMessage());
                session()->flash('type', 'error');
            }
        }
    }

    public function render()
    {
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        $query = User::whereHas('roles', function ($q) {
            $q->where('name', 'basecamp');
        });

        if ($this->activeTab === 'active') {
            // Show verified and unverified active users
            $query->whereIn('status', ['active_verified', 'active_unverified']);
        } else {
            // Show inactive users (all other statuses including null, false, pending_payment, etc.)
            // Exclude only active statuses
            $query->where(function($q) {
                $q->whereNotIn('status', ['active_verified', 'active_unverified'])
                  ->orWhereNull('status');
            });
        }

        $users = $query->orderBy('id', 'desc')->paginate($this->perPage);

        return view('livewire.base-camp-user', ['users' => $users])
            ->layout('layouts.app');
    }
}

