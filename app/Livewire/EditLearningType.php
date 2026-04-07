<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\HptmLearningType;
use App\Models\HptmLearningChecklist;
use App\Models\HptmLearningChecklistForUserReadStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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

        DB::beginTransaction();
        try {
            $learningType = HptmLearningType::findOrFail($this->learningTypeId);
            $oldScore = $learningType->score ?? 0;
            $newScore = $this->score;
            
            // If score changed, recalculate user scores
            if ($oldScore != $newScore) {
                // Find all checklists that use this learning type
                $checklists = HptmLearningChecklist::where('output', $this->learningTypeId)->get();
                
                // For each checklist, find users who marked it as read and recalculate scores
                foreach ($checklists as $checklist) {
                    $userReadStatuses = HptmLearningChecklistForUserReadStatus::where('checklistId', $checklist->id)
                        ->where('readStatus', 1)
                        ->get();
                    
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
            }
            
            $learningType->title = $this->title;
            $learningType->score = $this->score;
            $learningType->priority = $this->priority;
            $learningType->save();
            
            DB::commit();
            
            $message = 'Learning Type updated successfully!';
            if ($oldScore != $newScore) {
                $message .= ' User scores have been recalculated.';
            }
            session()->flash('message', $message);
            return redirect()->route('learningtype.list');
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('message', 'Error updating learning type: ' . $e->getMessage());
            \Log::error('Error updating HPTM learning type: ' . $e->getMessage(), [
                'learning_type_id' => $this->learningTypeId,
                'trace' => $e->getTraceAsString()
            ]);
            return back();
        }
    }

    public function render()
    {
        return view('livewire.edit-learning-type')->layout('layouts.app');
    }
}
