<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\HptmLearningChecklist;
use App\Models\HptmPrinciple;
use App\Models\HptmLearningType;

class EditLearningChecklist extends Component
{
    use WithFileUploads; // Add this trait

    public $checklistId;
    public $title, $description, $link, $document, $principleId, $output, $readStatus;

    public $documentFile; // New property for uploaded file

    public $principles = [];
    public $learningTypes = [];

    public function mount($id)
    {
        $this->principles = HptmPrinciple::orderBy('title')->get();
        $this->learningTypes = HptmLearningType::orderBy('priority')->get();

        $checklist = HptmLearningChecklist::findOrFail($id);
        $this->checklistId = $checklist->id;
        $this->title = $checklist->title;
        $this->description = $checklist->description;
        $this->link = $checklist->link;
        $this->document = $checklist->document;
        $this->principleId = $checklist->principleId;
        $this->output = $checklist->output;
        $this->readStatus = $checklist->readStatus;
    }

    public function save()
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'output' => 'required|exists:hptm_learning_types,id',
            'description' => 'nullable|string',
            'link' => 'nullable|string',
            'documentFile' => 'nullable|file|mimes:pdf|max:10240', // Max 10MB PDF
        ]);

        $checklist = HptmLearningChecklist::findOrFail($this->checklistId);

        if ($this->documentFile) {
            $path = $this->documentFile->store('documents', 'public'); // storage/app/public/documents
            $checklist->document = $path; // save PDF path in DB
        }

        $checklist->title = $this->title;
        $checklist->principleId = $this->principleId;
        $checklist->output = $this->output;
        $checklist->description = $this->description;
        $checklist->link = $this->link;
        $checklist->readStatus = $this->readStatus;
        $checklist->save();

        session()->flash('message', 'Checklist updated successfully!');
        return redirect()->route('learningchecklist.list');
    }

    public function render()
    {
        return view('livewire.edit-learning-checklist')->layout('layouts.app');
    }
}
