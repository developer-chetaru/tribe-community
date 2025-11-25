<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\HptmLearningChecklist;
use App\Models\HptmPrinciple;
use App\Models\HptmLearningType;

class AddLearningChecklist extends Component
{
    use WithFileUploads;

    public $title, $description, $link, $documentFile, $output;
    public $principleId = []; // array to store selected IDs
    public $selectedType = null;

    public $principles = [];
    public $principlesArray = []; // prepared array for Alpine.js
    public $learningTypes = [];
      public $selectedIds = []; // array to store selected IDs
  
public function mount()
{
    $this->principles = HptmPrinciple::orderBy('title')->get(['id', 'title']);

    $this->principlesArray = $this->principles->map(fn($p) => [
        'id' => $p->id,
        'title' => $p->title,
        'selected' => false, // default unselected
    ])->toArray();

    // ✅ default खाली
    $this->principleId = [];
 $this->learningTypes = HptmLearningType::orderBy('priority')->get();
}



    public function updatedOutput($value)
    {
        $lt = collect($this->learningTypes)->firstWhere('id', $value);
        if ($lt) {
            $title = strtolower($lt->title);
            $lastWord = explode(' ', $title);
            $lastWord = end($lastWord);
            $this->selectedType = $lastWord === 'video' ? 'video' : 'document';
        } else {
            $this->selectedType = null;
        }
    }
public function save()
{	 
    $this->validate([
        'title' => 'required|string|max:255',
        'output' => 'required|exists:hptm_learning_types,id',
        'description' => 'nullable|string',
        'link' => 'nullable|string',
        'documentFile' => 'nullable|file|mimes:pdf|max:10240',
        'principleId' => 'required|array|min:1',
    ]);

    $documentPath = $this->documentFile ? $this->documentFile->store('documents', 'public') : null;

    foreach ($this->principleId as $pid) {
        HptmLearningChecklist::create([
            'title' => $this->title,
            'principleId' => $pid, // single principle per row
            'output' => $this->output,
            'description' => $this->description,
            'link' => $this->link,
            'document' => $documentPath,
        ]);
    }

    session()->flash('message', 'Checklist added successfully!');
    return redirect()->route('learningchecklist.list');
}


    public function render()
    {
        return view('livewire.add-learning-checklist')->layout('layouts.app');
    }
}
