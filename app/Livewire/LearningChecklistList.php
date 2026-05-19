<?php

namespace App\Livewire;

use App\Models\HptmLearningChecklist;
use App\Models\HptmLearningChecklistForUserReadStatus;
use App\Models\HptmLearningType;
use App\Models\HptmPrinciple;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class LearningChecklistList extends Component
{
    public $checklists = [];

    public $principles = [];

    public $learningTypes = [];

    public $selectedPrincipleId = null;

    public $selectedLearningTypeId = null;

    public $search = '';

    public $sortDirection = 'desc';

    public bool $showDeleteModal = false;

    public ?int $deleteId = null;

    public function mount()
    {
        // Check if user has super_admin role
        if (! auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        $this->principles = HptmPrinciple::orderBy('title')->get(['id', 'title']);
        $this->learningTypes = \App\Models\HptmLearningType::orderBy('title')->get(['id', 'title']); // <-- NEW
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
        if (! is_null($this->selectedPrincipleId)) {
            $q->where(function ($sub) {
                $sub->where('principleId', $this->selectedPrincipleId)
                    ->orWhereNull('principleId');
            });
        }

        // learning type filter (actually "output")
        if (! is_null($this->selectedLearningTypeId)) {
            $q->where(function ($sub) {
                $sub->where('output', $this->selectedLearningTypeId)
                    ->orWhereNull('output');
            });
        }

        // search filter
        if (! empty($this->search)) {
            $q->where(function ($sub) {
                $sub->where('title', 'like', '%'.$this->search.'%')
                    ->orWhere('description', 'like', '%'.$this->search.'%')
                    ->orWhereHas('principle', function ($p) {
                        $p->where('title', 'like', '%'.$this->search.'%');
                    });
            });
        }

        $allChecklists = $q->get();

        // Group checklists by title, output, description, link, document
        $grouped = [];
        foreach ($allChecklists as $checklist) {
            $key = md5($checklist->title.'|'.$checklist->output.'|'.($checklist->description ?? '').'|'.($checklist->link ?? '').'|'.($checklist->document ?? ''));

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'checklist' => $checklist,
                    'principles' => [],
                    'primary_id' => $checklist->id,
                ];
            }

            if ($checklist->principle) {
                $grouped[$key]['principles'][] = $checklist->principle->title;
            } else {
                $grouped[$key]['principles'][] = 'All';
            }
        }

        // Convert grouped data back to a format the view can use
        $this->checklists = collect($grouped)->map(function ($group) {
            $checklist = $group['checklist'];
            $principles = array_unique($group['principles']);
            $checklist->principles_display = implode(', ', $principles);
            $checklist->primary_id = $group['primary_id'];

            return $checklist;
        })->values();
    }

    public function clearFilter()
    {
        $this->selectedPrincipleId = null;
        $this->selectedLearningTypeId = null;
        $this->search = '';
        $this->loadChecklists();
    }

    public function openDeleteConfirm(int $id): void
    {
        $this->deleteId = $id;
        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->deleteId = null;
    }

    public function confirmDelete(): void
    {
        if ($this->deleteId) {
            $this->delete($this->deleteId);
        }
        $this->cancelDelete();
    }

    public function delete(int $id): void
    {
        DB::beginTransaction();
        try {
            $checklist = HptmLearningChecklist::findOrFail($id);
            $relatedChecklists = $this->relatedChecklistsQuery($checklist)->get();

            if ($relatedChecklists->isEmpty()) {
                throw new \RuntimeException('Checklist not found.');
            }

            $learningScore = 0;
            if ($checklist->output) {
                $learningScore = HptmLearningType::find($checklist->output)?->score ?? 0;
            }

            foreach ($relatedChecklists as $item) {
                $userReadStatuses = HptmLearningChecklistForUserReadStatus::where('checklistId', $item->id)
                    ->where('readStatus', 1)
                    ->get();

                foreach ($userReadStatuses as $readStatus) {
                    if ($readStatus->userId && $learningScore > 0) {
                        $user = User::find($readStatus->userId);
                        if ($user) {
                            $user->update([
                                'hptmScore' => max(0, ($user->hptmScore ?? 0) - $learningScore),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }

                HptmLearningChecklistForUserReadStatus::where('checklistId', $item->id)->delete();
                $item->delete();
            }

            DB::commit();

            $count = $relatedChecklists->count();
            session()->flash('type', 'success');
            session()->flash('message', $count > 1
                ? "Checklist deleted successfully ({$count} principle entries removed). User scores recalculated."
                : 'Checklist deleted successfully! User scores have been recalculated.');
            $this->loadChecklists();
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('type', 'error');
            session()->flash('message', 'Error deleting checklist: '.$e->getMessage());
            \Log::error('Error deleting HPTM checklist: '.$e->getMessage(), [
                'checklist_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function relatedChecklistsQuery(HptmLearningChecklist $checklist)
    {
        return HptmLearningChecklist::where('title', $checklist->title)
            ->where('output', $checklist->output)
            ->where('description', $checklist->description)
            ->where('link', $checklist->link)
            ->where('document', $checklist->document);
    }

    public function render()
    {
        return view('livewire.learning-checklist-list')->layout('layouts.app');
    }
}
