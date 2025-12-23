<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\HptmPrinciple; 

class AddPrinciple extends Component
{
    // 🔹 Properties binded to form inputs
    public $title;
    public $description;
	public $priority;

    /**
     * 🔹 Validation rules for the form
     */
    protected $rules = [
        'title'       => 'required|string|max:255',
        'description' => 'required|string|max:1000',
		'priority'    => 'required|integer|min:1',
    ];

    /**
     * 🔹 Save the principle
     */
    public function save()
    {
        // ✅ Validate input
        $this->validate();

        // 🔹 Create new Principle record in database
        HptmPrinciple::create([
            'title'       => $this->title,
            'description' => $this->description,
			'priority'    => $this->priority,
        ]);

        // 🔹 Reset form fields after successful save
        $this->reset(['title', 'description', 'priority']);

        // 🔹 Set flash message for success
        session()->flash('message', 'Principle added successfully');

        // 🔹 Optional: redirect to list page after add
        // return redirect()->route('principles');
    }

    /**
     * 🔹 Render method returns blade view
     */
   public function render()
    {
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        return view('livewire.add-principle')->layout('layouts.app');
    }
}
