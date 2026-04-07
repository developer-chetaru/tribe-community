<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\AllDepartment;

class UpdateDepartment extends Component
{
     public $name;
    public $deptId;

    public function mount($id)
    {
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        $department = AllDepartment::findOrFail($id);
        $this->deptId = $department->id;
        $this->name = $department->name;
    }

    public function update()
    {
        $this->validate([
            'name' => 'required|string|max:255|unique:all_departments,name,' . $this->deptId,
        ]);

        $department = AllDepartment::findOrFail($this->deptId);
        $department->name = $this->name;
        $department->save();

        session()->flash('message', 'Department updated successfully!');
        return redirect()->route('department');
    }

    public function render()
    {
        return view('livewire.update-department')->layout('layouts.app');
    }
}
