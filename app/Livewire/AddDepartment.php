<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\AllDepartment;

class AddDepartment extends Component
{
      public $name;

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255|unique:all_departments,name',
        ]);

        AllDepartment::create([
            'name' => $this->name
        ]);

        session()->flash('message', 'Department added successfully!');
          $this->redirectRoute('department');
    }

    public function render()
    {
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        return view('livewire.add-department')->layout('layouts.app');
    }
}
