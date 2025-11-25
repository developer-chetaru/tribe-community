<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\DotValueList;

class DirectingValueEdit extends Component
{
    public $valueId;
    public $name, $value_url, $value_desc, $status;

    public function mount($id)
    {
        $this->valueId = $id;
        $value = DotValueList::findOrFail($id);

        $this->name = $value->name;
        $this->value_url = $value->value_url;
        $this->value_desc = $value->value_desc;
        $this->status = $value->status;
    }

    protected $rules = [
        'name' => 'required|string|max:225',
        'value_url' => 'nullable|url',
        'value_desc' => 'nullable|string',
        'status' => 'required|in:Active,Inactive',
    ];

    public function update()
    {
        $this->validate();

        $value = DotValueList::findOrFail($this->valueId);
        $value->update([
            'name' => $this->name,
            'value_url' => $this->value_url,
            'value_desc' => $this->value_desc,
            'status' => $this->status,
        ]);

        session()->flash('message', 'Directing Value updated successfully!');
        return redirect()->route('directing-value.list');
    }

    public function render()
    {
        return view('livewire.directing-value-edit')->layout('layouts.app');
    }
}
