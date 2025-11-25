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

    protected $paginationTheme = 'tailwind';

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function sendVerificationEmail($userId)
    {
        $user = User::findOrFail($userId);

        if ($user->status != 0) {
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

    public function render()
    {
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

