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
    public $title, $description, $link, $document, $principleId = [], $output, $readStatus;

    public $documentFile; // New property for uploaded file

    public $principles = [];
    public $principlesArray = []; // prepared array for Alpine.js
    public $learningTypes = [];

    public function mount($id)
    {
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        $this->principles = HptmPrinciple::orderBy('title')->get(['id', 'title']);
        $this->learningTypes = HptmLearningType::orderBy('priority')->get();

        $checklist = HptmLearningChecklist::findOrFail($id);
        $this->checklistId = $checklist->id;
        $this->title = $checklist->title;
        $this->description = $checklist->description;
        $this->link = $checklist->link;
        $this->document = $checklist->document;
        $this->output = $checklist->output;
        $this->readStatus = $checklist->readStatus;

        // Find all related checklist entries (same title, output, description, link, document)
        $relatedChecklists = HptmLearningChecklist::where('title', $checklist->title)
            ->where('output', $checklist->output)
            ->where('description', $checklist->description)
            ->where('link', $checklist->link)
            ->where('document', $checklist->document)
            ->pluck('principleId')
            ->filter()
            ->toArray();

        $this->principleId = array_values($relatedChecklists);

        // Prepare principles array for Alpine.js with selected state
        $this->principlesArray = $this->principles->map(fn($p) => [
            'id' => $p->id,
            'title' => $p->title,
            'selected' => in_array($p->id, $this->principleId),
        ])->toArray();
    }

    public function save()
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'output' => 'required|exists:hptm_learning_types,id',
            'description' => 'nullable|string',
            'link' => 'nullable|string',
            'documentFile' => 'nullable|file|mimes:pdf|max:10240', // Max 10MB PDF
            'principleId' => 'required|array|min:1',
        ]);

        DB::beginTransaction();
        try {
            $checklist = HptmLearningChecklist::findOrFail($this->checklistId);
            $oldOutput = $checklist->output;
            $newOutput = $this->output;
            
            // Get document path
            $documentPath = $this->document;
            if ($this->documentFile) {
                $documentPath = $this->documentFile->store('documents', 'public');
            }
            
            // Find all related checklist entries (same title, output, description, link, document)
            $relatedChecklists = HptmLearningChecklist::where('title', $checklist->title)
                ->where('output', $checklist->output)
                ->where('description', $checklist->description)
                ->where('link', $checklist->link)
                ->where('document', $checklist->document)
                ->get();
            
            $oldPrincipleIds = $relatedChecklists->pluck('principleId')->filter()->toArray();
            $newPrincipleIds = is_array($this->principleId) ? $this->principleId : [];
            
            // If output (learning type) changed, recalculate user scores for all related checklists
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
                
                // Find all users who have marked any related checklist as read
                $relatedChecklistIds = $relatedChecklists->pluck('id')->toArray();
                $userReadStatuses = HptmLearningChecklistForUserReadStatus::whereIn('checklistId', $relatedChecklistIds)
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

            // Delete checklists that are no longer needed
            $principleIdsToDelete = array_diff($oldPrincipleIds, $newPrincipleIds);
            if (!empty($principleIdsToDelete)) {
                foreach ($relatedChecklists as $relatedChecklist) {
                    if (in_array($relatedChecklist->principleId, $principleIdsToDelete)) {
                        // Delete user read statuses for this checklist
                        HptmLearningChecklistForUserReadStatus::where('checklistId', $relatedChecklist->id)->delete();
                        // Delete the checklist
                        $relatedChecklist->delete();
                    }
                }
            }

            // Update or create checklists for selected principles
            foreach ($newPrincipleIds as $principleId) {
                $existingChecklist = $relatedChecklists->firstWhere('principleId', $principleId);
                
                if ($existingChecklist) {
                    // Update existing checklist
                    $existingChecklist->title = $this->title;
                    $existingChecklist->output = $this->output;
                    $existingChecklist->description = $this->description;
                    $existingChecklist->link = $this->link;
                    $existingChecklist->document = $documentPath;
                    $existingChecklist->readStatus = $this->readStatus;
                    $existingChecklist->save();
                } else {
                    // Create new checklist for this principle
                    HptmLearningChecklist::create([
                        'title' => $this->title,
                        'principleId' => $principleId,
                        'output' => $this->output,
                        'description' => $this->description,
                        'link' => $this->link,
                        'document' => $documentPath,
                        'readStatus' => $this->readStatus,
                    ]);
                }
            }
            
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
