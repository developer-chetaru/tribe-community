<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\HptmLearningType;

class LearningTypeList extends Component
{
    public $learningTypes;

    public function mount()
    {
        $this->fetchLearningTypes();
    }

    public function fetchLearningTypes()
    {
        $this->learningTypes = HptmLearningType::orderBy('priority', 'asc')->get();
    }


public function delete($id)
{
    HptmLearningType::findOrFail($id)->delete();

    session()->flash('message', 'Value deleted successfully!');
    session()->flash('type', 'error');

    // Refresh the list
    $this->fetchLearningTypes();
}


    public function render()
    {
        return view('livewire.learning-type-list')->layout('layouts.app');
    }
}
