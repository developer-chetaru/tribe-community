<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\HptmLearningType;
use App\Models\HptmLearningChecklist;
use App\Models\HptmLearningChecklistForUserReadStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LearningTypeList extends Component
{
    public $learningTypes;

    public function mount()
    {
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        $this->fetchLearningTypes();
    }

    public function fetchLearningTypes()
    {
        $this->learningTypes = HptmLearningType::orderBy('priority', 'asc')->get();
    }


public function delete($id)
{
    DB::beginTransaction();
    try {
        $learningType = HptmLearningType::findOrFail($id);
        $learningScore = $learningType->score ?? 0;
        
        // Find all checklists that use this learning type
        $checklists = HptmLearningChecklist::where('output', $id)->get();
        
        // For each checklist, find users who marked it as read and recalculate scores
        foreach ($checklists as $checklist) {
            $userReadStatuses = HptmLearningChecklistForUserReadStatus::where('checklistId', $checklist->id)
                ->where('readStatus', 1)
                ->get();
            
            foreach ($userReadStatuses as $readStatus) {
                if ($readStatus->userId) {
                    $user = User::find($readStatus->userId);
                    if ($user && $learningScore > 0) {
                        // Subtract the score that was added when checklist was marked as read
                        $newHptmScore = max(0, ($user->hptmScore ?? 0) - $learningScore);
                        $user->update([
                            'hptmScore' => $newHptmScore,
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
        
        // Delete the learning type (soft delete)
        $learningType->delete();
        
        DB::commit();
        
        session()->flash('message', 'Learning type deleted successfully! User scores have been recalculated.');
        session()->flash('type', 'success');
        
        // Refresh the list
        $this->fetchLearningTypes();
    } catch (\Exception $e) {
        DB::rollBack();
        session()->flash('message', 'Error deleting learning type: ' . $e->getMessage());
        session()->flash('type', 'error');
        \Log::error('Error deleting HPTM learning type: ' . $e->getMessage(), [
            'learning_type_id' => $id,
            'trace' => $e->getTraceAsString()
        ]);
    }
}


    public function render()
    {
        return view('livewire.learning-type-list')->layout('layouts.app');
    }
}
