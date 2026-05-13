<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Organisation;

class SidebarMenu extends Component
{
    public $organisations = [];

    public function mount()
    {
        $user = Auth::user();
        if ($user && $user->hasRole('super_admin')) {
            $this->organisations = Organisation::query()
                ->orderBy('name')
                ->get(['id', 'name']);
        }
    }

    public function render()
    {
        return view('livewire.sidebar-menu');
    }
}
