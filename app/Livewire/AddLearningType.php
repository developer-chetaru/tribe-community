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
        return view('livewire.add-learning-type')->layout('layouts.app');
    }
}
