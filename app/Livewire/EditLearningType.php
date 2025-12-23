<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\HptmLearningType;

class EditLearningType extends Component
{
    public $learningTypeId;
    public $title;
    public $score;
    public $priority;

    public function mount($id)
    {
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        $learningType = HptmLearningType::findOrFail($id);

        $this->learningTypeId = $learningType->id;
        $this->title = $learningType->title;
        $this->score = $learningType->score;
        $this->priority = $learningType->priority;
    }

    public function save()
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'score' => 'required|numeric',
            'priority' => 'required|integer',
        ]);

        $learningType = HptmLearningType::findOrFail($this->learningTypeId);
        $learningType->title = $this->title;
        $learningType->score = $this->score;
        $learningType->priority = $this->priority;
        $learningType->save();

        session()->flash('message', 'Learning Type updated successfully!');
        return redirect()->route('learningtype.list'); // Adjust this route to your listing page
    }

    public function render()
    {
        return view('livewire.edit-learning-type')->layout('layouts.app');
    }
}
