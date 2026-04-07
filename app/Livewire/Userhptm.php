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
    public $hptmScore = 0; // Real-time score property

public function mount($activePrincipleId = null)
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
        // Get all checklists for this principle (including null principleId)
        $allChecklists = HptmLearningChecklist::where(fn($q) => 
            $q->where('principleId', $principleId)->orWhereNull('principleId')
        )->get();

        // Deduplicate checklists based on unique content (same as display logic)
        $uniqueChecklists = [];
        $seenChecklists = [];
        
        foreach ($allChecklists as $check) {
            // Create a unique key based on title, output, description, link, and document
            $uniqueKey = md5(
                ($check->title ?? '') . '|' . 
                ($check->output ?? '') . '|' . 
                ($check->description ?? '') . '|' . 
                ($check->link ?? '') . '|' . 
                ($check->document ?? '')
            );

            // Skip if we've already seen this checklist
            if (isset($seenChecklists[$uniqueKey])) {
                continue;
            }

            $seenChecklists[$uniqueKey] = true;
            
            // Get all related checklist IDs (same content, different IDs)
            $relatedChecklistIds = HptmLearningChecklist::where('title', $check->title)
                ->where('output', $check->output)
                ->where(function($q) use ($check) {
                    if ($check->description) {
                        $q->where('description', $check->description);
                    } else {
                        $q->whereNull('description');
                    }
                })
                ->where(function($q) use ($check) {
                    if ($check->link) {
                        $q->where('link', $check->link);
                    } else {
                        $q->whereNull('link');
                    }
                })
                ->where(function($q) use ($check) {
                    if ($check->document) {
                        $q->where('document', $check->document);
                    } else {
                        $q->whereNull('document');
                    }
                })
                ->pluck('id')
                ->toArray();

            $uniqueChecklists[] = [
                'ids' => $relatedChecklistIds,
                'uniqueKey' => $uniqueKey
            ];
        }

        $totalLearningChecklist = count($uniqueChecklists);

        // Count how many unique checklists are read
        $readLearningChecklist = 0;
        foreach ($uniqueChecklists as $uniqueChecklist) {
            // Check if any of the related checklist IDs is read
            $isRead = DB::table('hptm_learning_checklist_for_user_read_status')
                ->where('userId', $userId)
                ->whereIn('checklistId', $uniqueChecklist['ids'])
                ->where('readStatus', 1)
                ->exists();
            
            if ($isRead) {
                $readLearningChecklist++;
            }
        }

        if ($totalLearningChecklist > 0) {
            $result['completionPercent'] = round(($readLearningChecklist / $totalLearningChecklist) * 100, 2);
        } else {
            $result['completionPercent'] = 0;
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
            $allRead = true; // assume all are read initially (if no checklists, consider as read)
            $seenChecklists = []; // Track unique checklists to avoid duplicates

            // Always add learning type to array, even if empty (so it shows on UI)
            foreach ($checklists as $check) {
                // Create a unique key based on title, output, description, link, and document
                // This prevents showing the same checklist multiple times
                $uniqueKey = md5(
                    ($check->title ?? '') . '|' . 
                    ($check->output ?? '') . '|' . 
                    ($check->description ?? '') . '|' . 
                    ($check->link ?? '') . '|' . 
                    ($check->document ?? '')
                );

                // Skip if we've already seen this checklist
                if (isset($seenChecklists[$uniqueKey])) {
                    continue;
                }

                $seenChecklists[$uniqueKey] = true;

                // Get read status - check all related checklists (same title, output, etc.)
                $relatedChecklistIds = HptmLearningChecklist::where('title', $check->title)
                    ->where('output', $check->output)
                    ->where(function($q) use ($check) {
                        if ($check->description) {
                            $q->where('description', $check->description);
                        } else {
                            $q->whereNull('description');
                        }
                    })
                    ->where(function($q) use ($check) {
                        if ($check->link) {
                            $q->where('link', $check->link);
                        } else {
                            $q->whereNull('link');
                        }
                    })
                    ->where(function($q) use ($check) {
                        if ($check->document) {
                            $q->where('document', $check->document);
                        } else {
                            $q->whereNull('document');
                        }
                    })
                    ->pluck('id')
                    ->toArray();

                // Check if any of the related checklists is read
                $userReadChecklist = DB::table('hptm_learning_checklist_for_user_read_status')
                    ->where('userId', $userId)
                    ->whereIn('checklistId', $relatedChecklistIds)
                    ->where('readStatus', 1)
                    ->exists();

                $isRead = $userReadChecklist;
                if (!$isRead) $allRead = false;

                // Use the first checklist ID from related checklists as the primary ID
                $primaryChecklistId = $relatedChecklistIds[0] ?? $check->id;

                $learningCheckListArr[] = [
                    'checklistId'       => $primaryChecklistId,
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
    $principleArray['count'] = count($resultArray);

    // --- User HPTM Score ---
    // For basecamp users, don't filter by status=1 as they might have different status
    $user = Auth::user();
    if (!$user || $user->id != $userId) {
        $user = User::find($userId);
    }
    
    // Recalculate to ensure accuracy and sync with database
    // Display only hptmScore (sum of checked checklists), not including hptmEvaluationScore
    if ($user) {
        // Recalculate score based on actual checked checklists
        $calculatedScore = $this->recalculateHptmScore($userId);
        
        // Update user's hptmScore in database to match calculated score
        // This ensures database and display are always in sync
        if ($user->hptmScore != $calculatedScore) {
            $user->update([
                'hptmScore' => $calculatedScore,
                'updated_at' => now(),
            ]);
        }
        
        $principleArray['hptmScore'] = $calculatedScore;
        $this->hptmScore = $calculatedScore; // Update real-time property
    } else {
        $principleArray['hptmScore'] = 0;
        $this->hptmScore = 0;
    }

    $this->principleArray = $principleArray;
    
    // Set activePrincipleId from route parameter or use first principle
    if ($activePrincipleId) {
        $this->activePrincipleId = $activePrincipleId;
    } elseif (!$this->activePrincipleId) {
        $this->activePrincipleId = $principles->first()->id ?? null;
    }

    logger()->info('allSelectedByType on mount', $this->allSelectedByType);
}

    public function toggleAllChecks($typeTitle)
    {
        $userId = Auth::id();
        
        // Get all checklists for this learning type from the active principle
        $checks = collect($this->learningCheckLists[$this->activePrincipleId] ?? [])
            ->get($typeTitle, []);

        // Determine current state - check if all are selected
        $allSelected = true;
        if (count($checks) > 0) {
            foreach ($checks as $check) {
                if (!isset($check['userReadChecklist']) || $check['userReadChecklist'] !== true) {
                    $allSelected = false;
                    break;
                }
            }
        } else {
            $allSelected = false;
        }

        // Toggle: if all selected, unselect all; otherwise select all
        $newStatus = $allSelected ? 0 : 1;

        $checklistIds = collect($checks)->pluck('checklistId')->toArray();

        // Get all related checklists for each checklist in this group
        $allRelatedChecklistIds = [];
        foreach ($checklistIds as $checklistId) {
            $checklist = HptmLearningChecklist::find($checklistId);
            if ($checklist) {
                $relatedChecklists = HptmLearningChecklist::where('title', $checklist->title)
                    ->where('output', $checklist->output)
                    ->where(function($q) use ($checklist) {
                        if ($checklist->description) {
                            $q->where('description', $checklist->description);
                        } else {
                            $q->whereNull('description');
                        }
                    })
                    ->where(function($q) use ($checklist) {
                        if ($checklist->link) {
                            $q->where('link', $checklist->link);
                        } else {
                            $q->whereNull('link');
                        }
                    })
                    ->where(function($q) use ($checklist) {
                        if ($checklist->document) {
                            $q->where('document', $checklist->document);
                        } else {
                            $q->whereNull('document');
                        }
                    })
                    ->pluck('id')
                    ->toArray();
                
                $allRelatedChecklistIds = array_merge($allRelatedChecklistIds, $relatedChecklists);
            }
        }

        // Remove duplicates
        $allRelatedChecklistIds = array_unique($allRelatedChecklistIds);

        // Update all related checklists in batch
        foreach ($allRelatedChecklistIds as $relatedChecklistId) {
            $existingStatus = DB::table('hptm_learning_checklist_for_user_read_status')
                ->where('checklistId', $relatedChecklistId)
                ->where('userId', $userId)
                ->first();

            if ($existingStatus) {
                DB::table('hptm_learning_checklist_for_user_read_status')
                    ->where('checklistId', $relatedChecklistId)
                    ->where('userId', $userId)
                    ->update([
                        'readStatus' => $newStatus,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('hptm_learning_checklist_for_user_read_status')->insert([
                    'checklistId' => $relatedChecklistId,
                    'userId'      => $userId,
                    'readStatus'  => $newStatus,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }

        // Recalculate score
        $user = Auth::user();
        if (!$user || $user->id != $userId) {
            $user = User::find($userId);
        }
        
        if ($user) {
            $newHptmScore = $this->recalculateHptmScore($userId);
            $user->update([
                'hptmScore'  => $newHptmScore,
                'updated_at' => now(),
            ]);
            $user->refresh();
            $userScore = $user->hptmScore ?? 0;
            
            // Update real-time score property
            $this->hptmScore = $userScore;
            $this->principleArray['hptmScore'] = $userScore;
            
            // Dispatch score update
            $this->dispatch('score-updated', hptmScore: $userScore);
            $this->js("
                setTimeout(function() {
                    window.dispatchEvent(new CustomEvent('score-updated', { 
                        detail: { hptmScore: {$userScore} } 
                    }));
                }, 100);
            ");
        }

        // Refresh affected principles
        $affectedPrincipleIds = HptmLearningChecklist::whereIn('id', $allRelatedChecklistIds)
            ->distinct()
            ->pluck('principleId')
            ->filter()
            ->toArray();

        $hasNullPrinciple = HptmLearningChecklist::whereIn('id', $allRelatedChecklistIds)
            ->whereNull('principleId')
            ->exists();

        if ($hasNullPrinciple) {
            $allPrincipleIds = HptmPrinciple::pluck('id')->toArray();
            foreach ($allPrincipleIds as $principleId) {
                $this->refreshLearningChecklistsForPrinciple($principleId, $userId);
            }
        } else {
            foreach ($affectedPrincipleIds as $principleId) {
                $this->refreshLearningChecklistsForPrinciple($principleId, $userId);
            }
        }

        $this->refreshPrincipleCompletionPercentages($userId);
        
        // Update allSelectedByType for the current active principle
        if ($this->activePrincipleId) {
            $this->refreshLearningChecklistsForPrinciple($this->activePrincipleId, $userId);
        }
        
        $this->allSelectedByType[$typeTitle] = $newStatus === 1;
    }



public function setActivePrinciple($principleId)
{
    $this->activePrincipleId = $principleId;
    // Redirect to new URL pattern
    return $this->redirect(route('hptm.list', ['activePrincipleId' => $principleId]), navigate: true);
}


public function updatedLearningCheckLists()
{
    $this->initAllSelectedByType();

}



/**
 * Recalculate HPTM score based on all checked checklists
 * Score = (Count of unique checked items per learning type) × (Learning type score)
 */
private function recalculateHptmScore($userId)
{
    // Get all checklists that are marked as read for this user
    $readChecklistIds = DB::table('hptm_learning_checklist_for_user_read_status')
        ->where('userId', $userId)
        ->where('readStatus', 1)
        ->pluck('checklistId')
        ->toArray();

    if (empty($readChecklistIds)) {
        return 0;
    }

    // Get all checked checklists with their learning types
    $checkedChecklists = HptmLearningChecklist::whereIn('id', $readChecklistIds)
        ->with('learningType')
        ->get();

    // Group by learning type and deduplicate by unique checklist (title, output, description, link, document)
    $typeGroups = [];
    $seenChecklists = [];

    foreach ($checkedChecklists as $checklist) {
        if (!$checklist->learningType || !$checklist->learningType->score) {
            continue;
        }

        $learningTypeId = $checklist->output;
        $learningTypeTitle = $checklist->learningType->title ?? '';

        // Create unique key for deduplication
        $uniqueKey = md5(
            ($checklist->title ?? '') . '|' . 
            ($checklist->output ?? '') . '|' . 
            ($checklist->description ?? '') . '|' . 
            ($checklist->link ?? '') . '|' . 
            ($checklist->document ?? '')
        );

        // Skip if we've already counted this unique checklist
        if (isset($seenChecklists[$uniqueKey])) {
            continue;
        }

        $seenChecklists[$uniqueKey] = true;

        // Initialize type group if not exists
        if (!isset($typeGroups[$learningTypeId])) {
            $typeGroups[$learningTypeId] = [
                'count' => 0,
                'score' => $checklist->learningType->score,
                'title' => $learningTypeTitle
            ];
        }

        // Count unique items per type
        $typeGroups[$learningTypeId]['count']++;
    }

    // Calculate total score: (Count per type) × (Type score)
    $totalScore = 0;
    foreach ($typeGroups as $typeGroup) {
        $totalScore += $typeGroup['count'] * $typeGroup['score'];
    }

    return $totalScore;
}

/**
 * Toggle checklist status - reads current state from database and toggles it
 */
public function toggleChecklistStatus($checklistId)
{
    $userId = Auth::id();

    if (!$checklistId) {
        return;
    }

    $checklist = HptmLearningChecklist::find($checklistId);
    if (! $checklist) {
        return;
    }

    // Get user - don't filter by status=1 for basecamp users
    $user = Auth::user();
    if (!$user || $user->id != $userId) {
        $user = User::find($userId);
    }

    if (!$user) {
        return;
    }

    // Get current read status from database (not from UI state)
    // If no record exists, treat as unread (0)
    $existingStatus = DB::table('hptm_learning_checklist_for_user_read_status')
        ->where('checklistId', $checklistId)
        ->where('userId', $userId)
        ->value('readStatus');

    // Toggle: if currently read (1), set to unread (0), otherwise set to read (1)
    // Handle null case: if no record exists (null), treat as unread (0), so toggle to read (1)
    $newReadStatus = ($existingStatus === 1) ? 0 : 1;

    // Now call the update method with the correct status
    $this->changeReadStatusOfUserChecklist($checklistId, $newReadStatus);
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

    // Get user - don't filter by status=1 for basecamp users
    $user = Auth::user();
    if (!$user || $user->id != $userId) {
        $user = User::find($userId);
    }

    if (!$user) {
        return;
    }

    // Find all checklist items with the same title, output, description, link, and document
    // This groups items that were created together with multiple principles
    $relatedChecklists = HptmLearningChecklist::where('title', $checklist->title)
        ->where('output', $checklist->output)
        ->where(function($q) use ($checklist) {
            if ($checklist->description) {
                $q->where('description', $checklist->description);
            } else {
                $q->whereNull('description');
            }
        })
        ->where(function($q) use ($checklist) {
            if ($checklist->link) {
                $q->where('link', $checklist->link);
            } else {
                $q->whereNull('link');
            }
        })
        ->where(function($q) use ($checklist) {
            if ($checklist->document) {
                $q->where('document', $checklist->document);
            } else {
                $q->whereNull('document');
            }
        })
        ->pluck('id')
        ->toArray();

    // Update read status for all related checklist items
    foreach ($relatedChecklists as $relatedChecklistId) {
        $existingStatus = DB::table('hptm_learning_checklist_for_user_read_status')
            ->where('checklistId', $relatedChecklistId)
            ->where('userId', $userId)
            ->first();

        if ($existingStatus) {
            DB::table('hptm_learning_checklist_for_user_read_status')
                ->where('checklistId', $relatedChecklistId)
                ->where('userId', $userId)
                ->update([
                    'readStatus' => $readStatus,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('hptm_learning_checklist_for_user_read_status')->insert([
                'checklistId' => $relatedChecklistId,
                'userId'      => $userId,
                'readStatus'  => $readStatus,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    // Recalculate total score based on all checked checklists
    $newHptmScore = $this->recalculateHptmScore($userId);

    // Update user's hptmScore
    $user->update([
        'hptmScore'  => $newHptmScore,
        'updated_at' => now(),
    ]);

    // Get updated user score for display (only hptmScore, not including evaluation score)
    $user->refresh();
    $userScore = $user->hptmScore ?? 0;
    
    // Update real-time score property
    $this->hptmScore = $userScore;
    $this->principleArray['hptmScore'] = $userScore;

    // Refresh learning checklists for all affected principles (where related checklists exist)
    $affectedPrincipleIds = HptmLearningChecklist::whereIn('id', $relatedChecklists)
        ->distinct()
        ->pluck('principleId')
        ->filter()
        ->toArray();

    // Also refresh for null principleId (All principles)
    $hasNullPrinciple = HptmLearningChecklist::whereIn('id', $relatedChecklists)
        ->whereNull('principleId')
        ->exists();

    // Refresh all principles if any checklist has null principleId, otherwise refresh only affected ones
    if ($hasNullPrinciple) {
        $allPrincipleIds = HptmPrinciple::pluck('id')->toArray();
        foreach ($allPrincipleIds as $principleId) {
            $this->refreshLearningChecklistsForPrinciple($principleId, $userId);
        }
    } else {
        foreach ($affectedPrincipleIds as $principleId) {
            $this->refreshLearningChecklistsForPrinciple($principleId, $userId);
        }
    }

    // Always refresh the active principle to ensure UI is up to date
    if ($this->activePrincipleId) {
        $this->refreshLearningChecklistsForPrinciple($this->activePrincipleId, $userId);
    }

    // Refresh principle completion percentages
    $this->refreshPrincipleCompletionPercentages($userId);

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

/**
 * Refresh learning checklists for a specific principle
 */
private function refreshLearningChecklistsForPrinciple($principleId, $userId)
{
    $learningCheckListArray = [];
    $learningTypes = HptmLearningType::orderBy('priority', 'ASC')->get();

    foreach ($learningTypes as $learningType) {
        $checklists = HptmLearningChecklist::where('output', $learningType->id)
            ->where(fn($q) => $q->where('principleId', $principleId)->orWhereNull('principleId'))
            ->orderBy('created_at', 'ASC')
            ->get();

        $learningCheckListArr = [];
        $allRead = true;
        $seenChecklists = []; // Track unique checklists to avoid duplicates

        foreach ($checklists as $check) {
            // Create a unique key based on title, output, description, link, and document
            // This prevents showing the same checklist multiple times
            $uniqueKey = md5(
                ($check->title ?? '') . '|' . 
                ($check->output ?? '') . '|' . 
                ($check->description ?? '') . '|' . 
                ($check->link ?? '') . '|' . 
                ($check->document ?? '')
            );

            // Skip if we've already seen this checklist
            if (isset($seenChecklists[$uniqueKey])) {
                continue;
            }

            $seenChecklists[$uniqueKey] = true;

            // Get read status - check all related checklists (same title, output, etc.)
            $relatedChecklistIds = HptmLearningChecklist::where('title', $check->title)
                ->where('output', $check->output)
                ->where(function($q) use ($check) {
                    if ($check->description) {
                        $q->where('description', $check->description);
                    } else {
                        $q->whereNull('description');
                    }
                })
                ->where(function($q) use ($check) {
                    if ($check->link) {
                        $q->where('link', $check->link);
                    } else {
                        $q->whereNull('link');
                    }
                })
                ->where(function($q) use ($check) {
                    if ($check->document) {
                        $q->where('document', $check->document);
                    } else {
                        $q->whereNull('document');
                    }
                })
                ->pluck('id')
                ->toArray();

            // Check if any of the related checklists is read
            $userReadChecklist = DB::table('hptm_learning_checklist_for_user_read_status')
                ->where('userId', $userId)
                ->whereIn('checklistId', $relatedChecklistIds)
                ->where('readStatus', 1)
                ->exists();

            $isRead = $userReadChecklist;
            if (!$isRead) $allRead = false;

            // Use the first checklist ID from related checklists as the primary ID
            $primaryChecklistId = $relatedChecklistIds[0] ?? $check->id;

            $learningCheckListArr[] = [
                'checklistId'       => $primaryChecklistId,
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
        
        // Update allSelectedByType for this learning type
        // Only mark as all selected if there are items AND all are read
        $typeKey = $learningType->title;
        $this->allSelectedByType[$typeKey] = (count($learningCheckListArr) > 0) && $allRead;
    }

    $this->learningCheckLists[$principleId] = $learningCheckListArray;
}

/**
 * Refresh principle completion percentages
 */
private function refreshPrincipleCompletionPercentages($userId)
{
    $principles = HptmPrinciple::orderBy('priority', 'ASC')->get();
    
    foreach ($principles as $principle) {
        $principleId = $principle->id;
        
        // Get all checklists for this principle (including null principleId)
        $allChecklists = HptmLearningChecklist::where(fn($q) => 
            $q->where('principleId', $principleId)->orWhereNull('principleId')
        )->get();

        // Deduplicate checklists based on unique content (same as display logic)
        $uniqueChecklists = [];
        $seenChecklists = [];
        
        foreach ($allChecklists as $check) {
            // Create a unique key based on title, output, description, link, and document
            $uniqueKey = md5(
                ($check->title ?? '') . '|' . 
                ($check->output ?? '') . '|' . 
                ($check->description ?? '') . '|' . 
                ($check->link ?? '') . '|' . 
                ($check->document ?? '')
            );

            // Skip if we've already seen this checklist
            if (isset($seenChecklists[$uniqueKey])) {
                continue;
            }

            $seenChecklists[$uniqueKey] = true;
            
            // Get all related checklist IDs (same content, different IDs)
            $relatedChecklistIds = HptmLearningChecklist::where('title', $check->title)
                ->where('output', $check->output)
                ->where(function($q) use ($check) {
                    if ($check->description) {
                        $q->where('description', $check->description);
                    } else {
                        $q->whereNull('description');
                    }
                })
                ->where(function($q) use ($check) {
                    if ($check->link) {
                        $q->where('link', $check->link);
                    } else {
                        $q->whereNull('link');
                    }
                })
                ->where(function($q) use ($check) {
                    if ($check->document) {
                        $q->where('document', $check->document);
                    } else {
                        $q->whereNull('document');
                    }
                })
                ->pluck('id')
                ->toArray();

            $uniqueChecklists[] = [
                'ids' => $relatedChecklistIds,
                'uniqueKey' => $uniqueKey
            ];
        }

        $totalLearningChecklist = count($uniqueChecklists);

        // Count how many unique checklists are read
        $readLearningChecklist = 0;
        foreach ($uniqueChecklists as $uniqueChecklist) {
            // Check if any of the related checklist IDs is read
            $isRead = DB::table('hptm_learning_checklist_for_user_read_status')
                ->where('userId', $userId)
                ->whereIn('checklistId', $uniqueChecklist['ids'])
                ->where('readStatus', 1)
                ->exists();
            
            if ($isRead) {
                $readLearningChecklist++;
            }
        }

        $completionPercent = 0;
        if ($totalLearningChecklist > 0) {
            $completionPercent = round(($readLearningChecklist / $totalLearningChecklist) * 100, 2);
        }

        // Update in principleArray - ensure Livewire detects the change
        if (isset($this->principleArray['principleData'])) {
            foreach ($this->principleArray['principleData'] as &$principleData) {
                if ($principleData['id'] == $principleId) {
                    $principleData['completionPercent'] = $completionPercent;
                    break;
                }
            }
            // Force Livewire to detect the change by reassigning the array
            $this->principleArray = $this->principleArray;
        }
    }
}

    public function render()
    {
        return view('livewire.userhptm')->layout('layouts.app');
    }
}
