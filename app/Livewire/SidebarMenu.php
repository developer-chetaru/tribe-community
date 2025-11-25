<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class SidebarMenu extends Component
{
    // public $role;

    public function mount()
    {
        // $this->role = Auth::user()?->getRoleNames()?->first(); // 'super_admin' etc.
    }

    public function render()
    {
        return view('livewire.sidebar-menu');
    }
}
