<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\HptmPrinciple;
use App\Models\HptmTeamFeedbackStatus;
use App\Models\HptmTeamFeedbackAnswer;
use App\Models\HptmLearningChecklist;
use App\Models\HptmLearningType;
use App\Models\User;
use App\Services\SubscriptionService;
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
    $user = auth()->user();
    
    // Block super_admin - HPTM is not for them
    if ($user->hasRole('super_admin')) {
        abort(403, 'Unauthorized access. This page is not available for administrators.');
    }

    // Check if user has required role (organisation_user, organisation_admin, or basecamp)
    if (!$user->hasAnyRole(['organisation_user', 'organisation_admin', 'basecamp', 'director'])) {
        abort(403, 'Unauthorized access. This page is only available for organisation users.');
    }

    // Check subscription status for organization users
    if ($user->orgId && !$user->hasRole('super_admin')) {
        $subscriptionService = new SubscriptionService();
        $subscriptionStatus = $subscriptionService->getSubscriptionStatus($user->orgId);
        
        if (!$subscriptionStatus['active']) {
            abort(403, 'Your organization\'s subscription has expired. Please contact your director to renew the subscription.');
        }
    }

    $principleArray = [];
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
    // For basecamp users, don't filter by status=1 as they might have different status
    $user = Auth::user();
    if (!$user || $user->id != $userId) {
        $user = User::find($userId);
    }
    
    // Refresh user data to get latest score
    if ($user) {
        $user->refresh();
        $principleArray['hptmScore'] = ($user->hptmScore ?? 0) + ($user->hptmEvaluationScore ?? 0);
    } else {
        $principleArray['hptmScore'] = 0;
    }

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

    // Get user - don't filter by status=1 for basecamp users
    $user = Auth::user();
    if (!$user || $user->id != $userId) {
        $user = User::find($userId);
    }

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

    // User ka updated score reload karo - refresh from database
    // Don't filter by status=1 for basecamp users
    $updatedUser = User::find($userId);
    $userScore = 0;
    if ($updatedUser) {
        // Refresh user model to get latest data
        $updatedUser->refresh();
        $userScore = ($updatedUser->hptmScore ?? 0) + ($updatedUser->hptmEvaluationScore ?? 0);
    }

    $typeTitle = HptmLearningType::find($checklist->output)?->title ?? null;

    if ($typeTitle) {
        $checks = collect($this->learningCheckLists[$this->activePrincipleId] ?? [])
            ->flatten(1)
            ->where('learningTypeTitle', $typeTitle);

        $allChecked = $checks->every(fn($c) => $c['userReadChecklist'] == 1);
        $this->allSelectedByType[$typeTitle] = $allChecked;
    }

    // Refresh principle data after updating score
    $this->mount();

    // Dispatch score update event - Livewire 3 format with named parameter
    $this->dispatch('score-updated', hptmScore: $userScore);
    
    // Dispatch as browser CustomEvent to ensure navigation menu catches it
    $this->js("
        setTimeout(function() {
            window.dispatchEvent(new CustomEvent('score-updated', { 
                detail: { hptmScore: {$userScore} } 
            }));
        }, 100);
    ");

}



    public function render()
    {
        return view('livewire.userhptm')->layout('layouts.app');
    }
}
