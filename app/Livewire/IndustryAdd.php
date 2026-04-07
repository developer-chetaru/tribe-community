<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Industry;

class IndustryAdd extends Component
{
    public $name, $status = 1, $other; 

    protected $rules = [
        'name'   => 'required|string|max:225',
        'status' => 'required',
    ];

    public function save()
    {
        $this->validate();

        Industry::create([
            'name'   => $this->name,
 			
            'status' => $this->status,
        ]);

        session()->flash('message', 'Industry added successfully!');
        return redirect()->route('industries.list');
    }

    public function render()
    {
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        return view('livewire.industry-add')->layout('layouts.app');
    }
}
