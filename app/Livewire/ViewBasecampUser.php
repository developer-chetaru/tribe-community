<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\User;
use App\Models\HptmLearningChecklistForUserReadStatus;
use App\Models\HptmTeamFeedbackStatus;
use App\Models\HptmTeamFeedbackAnswer;
use App\Models\HptmLearningChecklist;
use App\Models\HptmLearningType;
use App\Models\HptmPrinciple;
use App\Services\EngagementService;
use Illuminate\Support\Facades\DB;

class ViewBasecampUser extends Component
{
    public $userId;
    public $user;
    public $hptmData = [];

    public function mount($id)
    {
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        $this->userId = $id;
        $this->user = User::findOrFail($id);
        $this->loadHptmData();
        $this->loadEngagementData();
    }
    
    public function loadEngagementData()
    {
        $userId = $this->user->id;
        
        // Get engagement service
        $engagementService = app(EngagementService::class);
        
        // Get EI Score (raw, without +250)
        $eiScore = $this->user->EIScore ?? 0;
        
        // Get HPTM Score (already calculated in loadHptmData)
        $hptmScore = $this->hptmData['totalRawScore'] ?? 0;
        
        // Calculate total engagement score manually (without the +250 addition)
        // Total = EI Score + HPTM Score (no base value added)
        $totalEngagementScore = $eiScore + $hptmScore;
        
        // Calculate engagement index for today
        $userDataArr = [
            'orgId' => $this->user->orgId,
            'userId' => $userId,
            'HI_include_saturday' => $this->user->HI_include_saturday ?? 0,
            'HI_include_sunday' => $this->user->HI_include_sunday ?? 0,
        ];
        $engagementIndex = $engagementService->getUserEngagementIndexForLastDay($userDataArr, now()->toDateString());
        
        $this->hptmData['engagement'] = [
            'totalScore' => $totalEngagementScore,
            'eiScore' => $eiScore,
            'hptmScore' => $hptmScore,
            'engagementIndex' => $engagementIndex,
        ];
    }

    public function loadHptmData()
    {
        // Refresh user data
        $this->user->refresh();
        
        $userId = $this->userId;
        
        // HPTM Raw Scores (same as login/dashboard)
        $rawHptmScore = $this->user->hptmScore ?? 0;
        $rawEvaluationScore = $this->user->hptmEvaluationScore ?? 0;
        $totalRawScore = $rawHptmScore + $rawEvaluationScore;
        
        // Calculate total possible score from learning checklists (deduplicated, same as login)
        // Need to deduplicate checklists before summing scores to avoid counting duplicates
        $allChecklists = HptmLearningChecklist::all();
        $seenChecklists = [];
        $totalPossibleScore = 0;
        
        foreach ($allChecklists as $check) {
            $uniqueKey = md5(
                ($check->title ?? '') . '|' . 
                ($check->output ?? '') . '|' . 
                ($check->description ?? '') . '|' . 
                ($check->link ?? '') . '|' . 
                ($check->document ?? '')
            );
            
            if (!isset($seenChecklists[$uniqueKey])) {
                $seenChecklists[$uniqueKey] = true;
                $learningType = HptmLearningType::find($check->output);
                if ($learningType) {
                    $totalPossibleScore += $learningType->score ?? 0;
                }
            }
        }
        
        // Calculate HPTM Score exactly as shown on login/dashboard
        // Formula: (($user->hptmScore + $user->hptmEvaluationScore) / ($learningChecklistTotalScore + 400)) * 1000
        $maxScore = $totalPossibleScore + 400;
        $calculatedHptmScore = $maxScore > 0 
            ? round(($totalRawScore / $maxScore) * 1000, 2) 
            : 0;
        
        // Calculate percentage for display (0-100%)
        $hptmScorePercentage = $maxScore > 0 
            ? round(($totalRawScore / $maxScore) * 100, 2) 
            : 0;
        
        $this->hptmData['rawHptmScore'] = $rawHptmScore;
        $this->hptmData['rawEvaluationScore'] = $rawEvaluationScore;
        $this->hptmData['totalRawScore'] = $totalRawScore;
        $this->hptmData['totalPossibleScore'] = $totalPossibleScore;
        $this->hptmData['calculatedHptmScore'] = $calculatedHptmScore;
        $this->hptmData['hptmScorePercentage'] = $hptmScorePercentage;
        
        // Total Learning Checklists (deduplicated by unique content)
        $allChecklists = HptmLearningChecklist::all();
        $uniqueChecklists = [];
        $seenChecklists = [];
        
        foreach ($allChecklists as $check) {
            $uniqueKey = md5(
                ($check->title ?? '') . '|' . 
                ($check->output ?? '') . '|' . 
                ($check->description ?? '') . '|' . 
                ($check->link ?? '') . '|' . 
                ($check->document ?? '')
            );
            
            if (!isset($seenChecklists[$uniqueKey])) {
                $seenChecklists[$uniqueKey] = true;
                $uniqueChecklists[] = $check->id;
            }
        }
        
        $totalChecklists = count($uniqueChecklists);
        
        // Read Checklists Count (check if any related checklist is read)
        $readChecklists = 0;
        foreach ($uniqueChecklists as $checklistId) {
            $checklist = HptmLearningChecklist::find($checklistId);
            if ($checklist) {
                // Get all related checklist IDs (same content)
                $relatedChecklistIds = HptmLearningChecklist::where('title', $checklist->title)
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
                    ->pluck('id')
                    ->toArray();
                
                // Check if any related checklist is read
                $isRead = HptmLearningChecklistForUserReadStatus::where('userId', $userId)
                    ->whereIn('checklistId', $relatedChecklistIds)
                    ->where('readStatus', 1)
                    ->exists();
                
                if ($isRead) {
                    $readChecklists++;
                }
            }
        }
        
        $this->hptmData['totalChecklists'] = $totalChecklists;
        $this->hptmData['readChecklists'] = $readChecklists;
        $this->hptmData['checklistProgress'] = $totalChecklists > 0 
            ? round(($readChecklists / $totalChecklists) * 100, 2) 
            : 0;
        
        // Team Feedback Stats
        $feedbackGiven = HptmTeamFeedbackStatus::where('fromUserId', $userId)->count();
        $feedbackReceived = HptmTeamFeedbackStatus::where('toUserId', $userId)->count();
        $this->hptmData['feedbackGiven'] = $feedbackGiven;
        $this->hptmData['feedbackReceived'] = $feedbackReceived;
        
        // Last Feedback Date
        $lastFeedback = HptmTeamFeedbackStatus::where('fromUserId', $userId)
            ->orderBy('created_at', 'desc')
            ->first();
        $this->hptmData['lastFeedbackDate'] = $lastFeedback ? $lastFeedback->created_at->format('M d, Y') : 'Never';
        
        // Principles Count and Details
        $principles = HptmPrinciple::orderBy('priority', 'ASC')->get();
        $totalPrinciples = $principles->count();
        $this->hptmData['totalPrinciples'] = $totalPrinciples;
        
        // Principles with completion data
        $principlesData = [];
        foreach ($principles as $principle) {
            $principleId = $principle->id;
            
            // Get checklists for this principle
            $principleChecklists = HptmLearningChecklist::where(fn($q) => 
                $q->where('principleId', $principleId)->orWhereNull('principleId')
            )->get();
            
            // Deduplicate checklists
            $uniquePrincipleChecklists = [];
            $seenPrincipleChecklists = [];
            foreach ($principleChecklists as $check) {
                $uniqueKey = md5(
                    ($check->title ?? '') . '|' . 
                    ($check->output ?? '') . '|' . 
                    ($check->description ?? '') . '|' . 
                    ($check->link ?? '') . '|' . 
                    ($check->document ?? '')
                );
                if (!isset($seenPrincipleChecklists[$uniqueKey])) {
                    $seenPrincipleChecklists[$uniqueKey] = true;
                    $uniquePrincipleChecklists[] = $check->id;
                }
            }
            
            // Count read checklists for this principle and get checked items
            $readCount = 0;
            $checkedItems = [];
            foreach ($uniquePrincipleChecklists as $checklistId) {
                $checklist = HptmLearningChecklist::find($checklistId);
                if ($checklist) {
                    $relatedChecklistIds = HptmLearningChecklist::where('title', $checklist->title)
                        ->where('output', $checklist->output)
                        ->where(function($q) use ($checklist) {
                            if ($checklist->description) {
                                $q->where('description', $checklist->description);
                            } else {
                                $q->whereNull('description');
                            }
                        })
                        ->pluck('id')
                        ->toArray();
                    
                    $isRead = HptmLearningChecklistForUserReadStatus::where('userId', $userId)
                        ->whereIn('checklistId', $relatedChecklistIds)
                        ->where('readStatus', 1)
                        ->exists();
                    
                    if ($isRead) {
                        $readCount++;
                        // Get learning type title
                        $learningType = HptmLearningType::find($checklist->output);
                        $checkedItems[] = [
                            'title' => $checklist->title ?? 'Untitled',
                            'learningType' => $learningType->title ?? 'Unknown',
                            'hasLink' => !empty($checklist->link),
                            'hasDocument' => !empty($checklist->document),
                        ];
                    }
                }
                
                // Sort checked items alphabetically by title
                usort($checkedItems, function($a, $b) {
                    return strcasecmp($a['title'], $b['title']);
                });
            }
            
            $totalCount = count($uniquePrincipleChecklists);
            $completionPercent = $totalCount > 0 ? round(($readCount / $totalCount) * 100, 2) : 0;
            
            $principlesData[] = [
                'id' => $principleId,
                'title' => $principle->title,
                'description' => $principle->description,
                'totalChecklists' => $totalCount,
                'readChecklists' => $readCount,
                'completionPercent' => $completionPercent,
                'checkedItems' => $checkedItems,
            ];
        }
        
        // Sort principles by completion percentage (highest first), then by title
        usort($principlesData, function($a, $b) {
            if ($b['completionPercent'] != $a['completionPercent']) {
                return $b['completionPercent'] <=> $a['completionPercent'];
            }
            return strcasecmp($a['title'], $b['title']);
        });
        
        $this->hptmData['principles'] = $principlesData;
        
        // Learning Types Breakdown
        $learningTypes = HptmLearningType::orderBy('priority', 'ASC')->get();
        $learningTypesData = [];
        foreach ($learningTypes as $learningType) {
            $typeChecklists = HptmLearningChecklist::where('output', $learningType->id)->get();
            
            // Deduplicate
            $uniqueTypeChecklists = [];
            $seenTypeChecklists = [];
            foreach ($typeChecklists as $check) {
                $uniqueKey = md5(
                    ($check->title ?? '') . '|' . 
                    ($check->output ?? '') . '|' . 
                    ($check->description ?? '') . '|' . 
                    ($check->link ?? '') . '|' . 
                    ($check->document ?? '')
                );
                if (!isset($seenTypeChecklists[$uniqueKey])) {
                    $seenTypeChecklists[$uniqueKey] = true;
                    $uniqueTypeChecklists[] = $check->id;
                }
            }
            
            $readCount = 0;
            foreach ($uniqueTypeChecklists as $checklistId) {
                $checklist = HptmLearningChecklist::find($checklistId);
                if ($checklist) {
                    $relatedChecklistIds = HptmLearningChecklist::where('title', $checklist->title)
                        ->where('output', $checklist->output)
                        ->pluck('id')
                        ->toArray();
                    
                    $isRead = HptmLearningChecklistForUserReadStatus::where('userId', $userId)
                        ->whereIn('checklistId', $relatedChecklistIds)
                        ->where('readStatus', 1)
                        ->exists();
                    
                    if ($isRead) $readCount++;
                }
            }
            
            $learningTypesData[] = [
                'id' => $learningType->id,
                'title' => $learningType->title,
                'score' => $learningType->score ?? 0,
                'totalChecklists' => count($uniqueTypeChecklists),
                'readChecklists' => $readCount,
                'completionPercent' => count($uniqueTypeChecklists) > 0 
                    ? round(($readCount / count($uniqueTypeChecklists)) * 100, 2) 
                    : 0,
            ];
        }
        $this->hptmData['learningTypes'] = $learningTypesData;
    }

    public function render()
    {
        return view('livewire.view-basecamp-user')
            ->layout('layouts.app');
    }
}
