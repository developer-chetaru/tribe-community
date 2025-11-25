<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Industry;
use App\Models\Organisation;

class IndustryEdit extends Component
{
    public $industryId;
    public $name, $status, $org_id;

    public function mount($id)
    {
        $this->industryId = $id;

        $industry = Industry::findOrFail($id);

        $this->name   = $industry->name;
        $this->status = $industry->status == 1 ? '1' : '0'; // Keep as numeric string for select dropdown
    }

    protected $rules = [
        'name'   => 'required|string|max:225',
        'status' => 'required',
    ];

    public function update()
    {
        $this->validate();

        $industry = Industry::findOrFail($this->industryId);

        $industry->update([
            'name'   => $this->name,
            'status' => $this->status == '1' ? 1 : 0,
        ]);

        session()->flash('message', 'Industry updated successfully!');
        return redirect()->route('industries.list');
    }

    public function render()
    {
        $organisations = Organisation::all();

        return view('livewire.industry-edit', compact('organisations'))
            ->layout('layouts.app');
    }
}
