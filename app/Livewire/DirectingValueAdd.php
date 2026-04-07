<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\DotValueList;

class DirectingValueAdd extends Component
{
    public $name, $value_url, $value_desc, $status = 'Active';

    protected $rules = [
        'name' => 'required|string|max:225',
        'value_url' => 'nullable|url',
        'value_desc' => 'nullable|string',
        'status' => 'required|in:Active,Inactive',
    ];

    public function save()
    {
        $this->validate();

        DotValueList::create([
            'name' => $this->name,
            'value_url' => $this->value_url,
            'value_desc' => $this->value_desc,
            'status' => $this->status,
        ]);

        session()->flash('message', 'Directing Value added successfully!');
        return redirect()->route('directing-value.list');
    }

    public function render()
    {
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        return view('livewire.directing-value-add')->layout('layouts.app');
    }
}
