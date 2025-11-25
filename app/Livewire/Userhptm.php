<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\HptmPrinciple;
use App\Models\HptmTeamFeedbackStatus;
use App\Models\HptmTeamFeedbackAnswer;
use App\Models\HptmLearningChecklist;
use App\Models\HptmLearningType;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Userhptm extends Component
{
    public $principleArray = [];
    public $learningCheckLists = [];
  	public $activePrincipleId;
    public $allSelectedByType = [];
    public $learningCheckListsFlat = [];
    protected $queryString = ['activePrincipleId'];

public function mount()
{
    $principleArray = [];
    $user = auth()->user();
    $userId = $user->id;

    // --- Get last feedback date ---
    $teamFeedbackStatus = HptmTeamFeedbackStatus::where('fromUserId', $userId)
        ->where('created_at', 'LIKE', date('Y-m') . '%')
        ->first();

    $lastFeedbackDate = $teamFeedbackStatus->created_at ?? 
                        HptmTeamFeedbackStatus::where('fromUserId', $userId)
                            ->orderBy('created_at', 'DESC')
                            ->value('created_at');

    // --- Count team feedback completed ---
    $teamFeedbackData = 0;
    if (!empty($lastFeedbackDate)) {
        $teamFeedbackData = HptmTeamFeedbackStatus::where('fromUserId', $userId)
            ->where('completeStatus', 2)
            ->where('created_at', 'LIKE', date('Y-m', strtotime($lastFeedbackDate)) . '%')
            ->count();
    }

    // --- Get all principles ---
    $principles = HptmPrinciple::orderBy('priority', 'ASC')->get();
    $resultArray = [];
    $this->learningCheckLists = [];
    $this->allSelectedByType = [];

    foreach ($principles as $principle) {
        $principleId = $principle->id;
        $result = [
            'id' => $principleId,
            'title' => $principle->title,
            'description' => $principle->description,
            'teamFeedbackScorePercent' => 0,
            'completionPercent' => 0
        ];

        // --- Team feedback score percent ---
        if (!empty($lastFeedbackDate)) {
            $teamFeedback1 = HptmTeamFeedbackAnswer::where('toUserId', $userId)
                ->whereHas('question', fn($q) => $q->where('principleId', $principleId))
                ->where('date', 'LIKE', date('Y-m', strtotime($lastFeedbackDate)) . '%')
                ->with('option')
                ->get()
                ->sum(fn($answer) => $answer->option->option_rating ?? 0);

            $completionScore = 0;
            if (!empty($teamFeedback1) && !empty($teamFeedbackData)) {
                $completionScore = round($teamFeedback1 / $teamFeedbackData, 2);
            }
            $result['teamFeedbackScorePercent'] = $completionScore;
        }

        // --- Completion percentage for learning checklists ---
        $totalLearningChecklist = HptmLearningChecklist::where(fn($q) => 
            $q->where('principleId', $principleId)->orWhereNull('principleId')
        )->count();

        $readLearningChecklist = HptmLearningChecklist::where(fn($q) => 
            $q->where('principleId', $principleId)->orWhereNull('principleId')
        )->whereHas('userReadStatus', fn($q) => 
            $q->where('userId', $userId)->where('readStatus', 1)
        )->count();

        if (!empty($readLearningChecklist) && !empty($totalLearningChecklist)) {
            $result['completionPercent'] = round(($readLearningChecklist / $totalLearningChecklist) * 100, 2);
        }

        $resultArray[] = $result;

        // --- Fetch learning checklists grouped by learning type ---
        $learningCheckListArray = [];
        $learningTypes = HptmLearningType::orderBy('priority', 'ASC')->get();

        foreach ($learningTypes as $learningType) {
            $checklists = HptmLearningChecklist::where('output', $learningType->id)
                ->where(fn($q) => $q->where('principleId', $principleId)->orWhereNull('principleId'))
                ->orderBy('created_at', 'ASC')
                ->get();

            $learningCheckListArr = [];
            $allRead = true; // assume all are read initially

            foreach ($checklists as $check) {
                $userReadChecklist = DB::table('hptm_learning_checklist_for_user_read_status')
                    ->where('userId', $userId)
                    ->where('checklistId', $check->id)
                    ->value('readStatus');

                $isRead = $userReadChecklist == 1;
                if (!$isRead) $allRead = false;

                $learningCheckListArr[] = [
                    'checklistId'       => $check->id,
                    'principleId'       => $check->principleId,
                    'typeId'            => $check->output,
                    'link'              => $check->link ?? '',
                    'document'          => $check->document ? url("storage/" . $check->document) : '',
                    'checklistTitle'    => $check->title ?? '',
                    'description'       => $check->description ?? '',
                    'learningTypeTitle' => $learningType->title ?? '',
                    'userReadChecklist' => $isRead
                ];
            }

            $learningCheckListArray[$learningType->title] = $learningCheckListArr;

            // --- Set initial allSelected state ---
            $this->allSelectedByType[$learningType->title] = $allRead;
        }

        $this->learningCheckLists[$principleId] = $learningCheckListArray;
    }

    $principleArray['principleData'] = $resultArray;

    // --- User HPTM Score ---
    $user = User::where('id', $userId)->where('status', 1)->first();
    $principleArray['hptmScore'] = ($user->hptmScore ?? 0) + ($user->hptmEvaluationScore ?? 0);

    $this->principleArray = $principleArray;
    $this->activePrincipleId = $this->activePrincipleId ?: ($principles->first()->id ?? null);

    logger()->info('allSelectedByType on mount', $this->allSelectedByType);
}

    public function toggleAllChecks($typeTitle)
    {
        $checks = collect($this->learningCheckLists[$this->activePrincipleId] ?? [])
            ->flatten(1)
            ->where('learningTypeTitle', $typeTitle);

        $allSelected = $this->allSelectedByType[$typeTitle] ?? false;
        $newStatus = $allSelected ? 0 : 1;

        foreach ($checks as $check) {
            $this->changeReadStatusOfUserChecklist($check['checklistId'], $newStatus);
        }

        $this->allSelectedByType[$typeTitle] = $newStatus === 1;

        $this->mount();
    }



public function setActivePrinciple($principleId)
{
    $this->activePrincipleId = $principleId;
}


public function updatedLearningCheckLists()
{
    $this->initAllSelectedByType();

}



public function changeReadStatusOfUserChecklist($checklistId, $readStatus)
{
    $userId = Auth::id();

    if (!$checklistId || $readStatus === null) {
        return;
    }

    $checklist = HptmLearningChecklist::find($checklistId);
    if (! $checklist) {
        return;
    }

    $learningScore = 0;
    if ($checklist && $checklist->output) {
        $scoreModel    = HptmLearningType::find($checklist->output);
        $learningScore = $scoreModel->score ?? 0;
    }

    $user = User::where('id', $userId)->where('status', 1)->first();

    if ($user) {
        $newHptmScore = ($readStatus == 1)
            ? ($user->hptmScore ?? 0) + $learningScore
            : ($user->hptmScore ?? 0) - $learningScore;

        $user->update([
            'hptmScore'  => max(0, $newHptmScore),
            'updated_at' => now(),
        ]);
    }

    $existingStatus = DB::table('hptm_learning_checklist_for_user_read_status')
        ->where('checklistId', $checklistId)
        ->where('userId', $userId)
        ->first();

    if ($existingStatus) {
        DB::table('hptm_learning_checklist_for_user_read_status')
            ->where('checklistId', $checklistId)
            ->where('userId', $userId)
            ->update([
                'readStatus' => $readStatus,
                'updated_at' => now(),
            ]);
    } else {
        DB::table('hptm_learning_checklist_for_user_read_status')->insert([
            'checklistId' => $checklistId,
            'userId'      => $userId,
            'readStatus'  => $readStatus,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    // User ka updated score reload karo
    $updatedUser = User::where('id', $userId)->where('status', 1)->first();
    $userScore = 0;
    if ($updatedUser) {
        $userScore += $updatedUser->hptmScore ?? 0;
        $userScore += $updatedUser->hptmEvaluationScore ?? 0;
    }

    // Refresh principle data
    $this->mount();

    $typeTitle = HptmLearningType::find($checklist->output)?->title ?? null;

    if ($typeTitle) {
        $checks = collect($this->learningCheckLists[$this->activePrincipleId] ?? [])
            ->flatten(1)
            ->where('learningTypeTitle', $typeTitle);

        $allChecked = $checks->every(fn($c) => $c['userReadChecklist'] == 1);
        $this->allSelectedByType[$typeTitle] = $allChecked;
    }


    $this->dispatch('score-updated', [
        'hptmScore' => $userScore
    ]);
    $this->mount();

}



    public function render()
    {
        return view('livewire.userhptm')->layout('layouts.app');
    }
}
