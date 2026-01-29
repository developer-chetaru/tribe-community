<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\HptmLearningChecklist;
use App\Models\HptmPrinciple;
use App\Models\HptmLearningType;
use App\Models\HptmLearningChecklistForUserReadStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

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

        DB::beginTransaction();
        try {
            $checklist = HptmLearningChecklist::findOrFail($this->checklistId);
            $oldOutput = $checklist->output;
            $newOutput = $this->output;
            
            // If output (learning type) changed, recalculate user scores
            if ($oldOutput != $newOutput) {
                // Get old and new scores
                $oldScore = 0;
                if ($oldOutput) {
                    $oldScoreModel = HptmLearningType::find($oldOutput);
                    $oldScore = $oldScoreModel->score ?? 0;
                }
                
                $newScore = 0;
                if ($newOutput) {
                    $newScoreModel = HptmLearningType::find($newOutput);
                    $newScore = $newScoreModel->score ?? 0;
                }
                
                // Find all users who have marked this checklist as read
                $userReadStatuses = HptmLearningChecklistForUserReadStatus::where('checklistId', $this->checklistId)
                    ->where('readStatus', 1)
                    ->get();
                
                // Recalculate scores for affected users
                foreach ($userReadStatuses as $readStatus) {
                    if ($readStatus->userId) {
                        $user = User::find($readStatus->userId);
                        if ($user) {
                            // Remove old score and add new score
                            $currentScore = $user->hptmScore ?? 0;
                            $newHptmScore = max(0, $currentScore - $oldScore + $newScore);
                            $user->update([
                                'hptmScore' => $newHptmScore,
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }

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
            
            DB::commit();
            
            $message = 'Checklist updated successfully!';
            if ($oldOutput != $newOutput) {
                $message .= ' User scores have been recalculated.';
            }
            session()->flash('message', $message);
            return redirect()->route('learningchecklist.list');
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('message', 'Error updating checklist: ' . $e->getMessage());
            \Log::error('Error updating HPTM checklist: ' . $e->getMessage(), [
                'checklist_id' => $this->checklistId,
                'trace' => $e->getTraceAsString()
            ]);
            return back();
        }
    }

    public function render()
    {
        return view('livewire.edit-learning-checklist')->layout('layouts.app');
    }
}
