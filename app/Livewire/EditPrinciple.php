<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\HptmPrinciple; 

class EditPrinciple extends Component
{
    public $principleId;
    public $title;
    public $description;
	public $priority;

    public function mount($id)
    {
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        $principle = HptmPrinciple::findOrFail($id);
        $this->principleId = $principle->id;
        $this->title = $principle->title;
        $this->description = $principle->description;
		$this->priority = $principle->priority;
    }

    public function save()
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
			'priority' => 'required|integer|min:1',
        ]);

        $principle = HptmPrinciple::findOrFail($this->principleId);
        $principle->title = $this->title;
        $principle->description = $this->description;
		$principle->priority = $this->priority;
        $principle->save();

        session()->flash('message', 'Principle updated successfully!');
        return redirect()->route('principles'); 
    }

    public function render()
    {
        return view('livewire.edit-principle')->layout('layouts.app');
    }
}
