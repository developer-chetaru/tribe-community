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
        $user = User::findOrFail($userId);

        // Only send if email is not verified
        if ($user->email_verified_at) {
            return;
        }

        // GENERATE OLD VERIFICATION LINK AGAIN (Same as your create code)
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

        try {
            (new OneSignalService())->registerEmailUserFallback($user->email, $user->id, [
                'subject' => 'Activate Your Tribe365 Account',
                'body' => $emailBody,
            ]);

            session()->flash('message', 'Verification email sent successfully!');
            session()->flash('type', 'success');

        } catch (\Throwable $e) {
            session()->flash('message', 'Failed to send email: '.$e->getMessage());
            session()->flash('type', 'error');
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
            $q->where('id', 5);
        });

        if ($this->activeTab === 'active') {
            $query->where('status', 1);
        } else {
            $query->where('status', 0);
        }

        $users = $query->orderBy('id', 'desc')->paginate($this->perPage);

        return view('livewire.base-camp-user', ['users' => $users])
            ->layout('layouts.app');
    }
}

