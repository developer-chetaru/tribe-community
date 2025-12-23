<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\HptmLearningType;

class AddLearningType extends Component
{
    public $title;
    public $score;
    public $priority;

    protected $rules = [
        'title' => 'required|string|max:255',
        'score' => 'required|numeric',
        'priority' => 'required|integer',
    ];

    public function save()
    {
        $this->validate();

        HptmLearningType::create([
            'title' => $this->title,
            'score' => $this->score,
            'priority' => $this->priority,
        ]);

        session()->flash('message', 'Learning Type added successfully!');
        return redirect()->route('learningtype.list'); // Adjust to your listing page
    }

    public function render()
    {
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        return view('livewire.add-learning-type')->layout('layouts.app');
    }
}
