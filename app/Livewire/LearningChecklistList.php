<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\HptmLearningChecklist;
use App\Models\HptmPrinciple;

class LearningChecklistList extends Component
{
    public $checklists = [];
    public $principles = [];
    public $learningTypes = [];   
    public $selectedPrincipleId = null;
    public $selectedLearningTypeId = null; 
    public $search = '';        
    public $sortDirection = 'desc';
    public function mount()
    {
        $this->principles = HptmPrinciple::orderBy('title')->get(['id','title']);
        $this->learningTypes = \App\Models\HptmLearningType::orderBy('title')->get(['id','title']); // <-- NEW
        $this->loadChecklists();
    }

    public function updatedSelectedPrincipleId($value)
    {
        $this->selectedPrincipleId = $value === '' ? null : (int) $value;
        $this->loadChecklists();
    }

    public function updatedSelectedLearningTypeId($value)
    {
        $this->selectedLearningTypeId = $value === '' ? null : (int) $value;
        $this->loadChecklists();
    }

    public function updatedSearch()
    {
        $this->loadChecklists();
    }

private function baseQuery()
{
    return HptmLearningChecklist::with(['principle:id,title', 'learningType:id,title'])
        ->orderBy('created_at', $this->sortDirection); 
}

public function updatedSortDirection()
{
    $this->loadChecklists();
}
    public function loadChecklists()
    {
        $q = $this->baseQuery();

        // principle filter
      // principle filter
if (!is_null($this->selectedPrincipleId)) {
    $q->where(function($sub) {
        $sub->where('principleId', $this->selectedPrincipleId)
            ->orWhereNull('principleId'); 
    });
}

// learning type filter (actually "output")
if (!is_null($this->selectedLearningTypeId)) {
    $q->where(function($sub) {
        $sub->where('output', $this->selectedLearningTypeId)
            ->orWhereNull('output');
    });
}

        // search filter
        if (!empty($this->search)) {
            $q->where(function($sub) {
                $sub->where('title', 'like', '%'.$this->search.'%')
                    ->orWhere('description', 'like', '%'.$this->search.'%')
                    ->orWhereHas('principle', function($p) {
                        $p->where('title', 'like', '%'.$this->search.'%');
                    });
            });
        }

        $this->checklists = $q->get();
    }

    public function clearFilter()
    {
        $this->selectedPrincipleId = null;
        $this->selectedLearningTypeId = null;
        $this->search = '';
        $this->loadChecklists();
    }



    public function delete($id)
    {
        HptmLearningChecklist::findOrFail($id)->delete();
        session()->flash('type', 'success');
        session()->flash('message', 'Checklist deleted successfully!');
        $this->loadChecklists();
    }

    public function render()
    {
        return view('livewire.learning-checklist-list')->layout('layouts.app');
    }
}
