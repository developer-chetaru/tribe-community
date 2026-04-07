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
use App\Models\HappyIndex;
use App\Models\Organisation;
use App\Models\SubscriptionRecord;
use App\Services\EngagementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Charge as StripeCharge;
use Stripe\Invoice as StripeInvoice;
use Stripe\Stripe;

class ViewBasecampUser extends Component
{
    public $userId;
    public $user;
    public $hptmData = [];

    /** @var array Recent sentiment check-ins + counts for admin coaching view */
    public array $sentimentSummary = [];

    /** @var array<int, array<string, mixed>> Stripe invoices for admin payment history */
    public array $stripePaymentHistory = [];

    public ?string $stripePaymentHistoryError = null;

    public bool $stripeCustomerLinked = false;

    public function mount($id)
    {
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        $this->userId = $id;
        $this->user = User::findOrFail($id);
        // loadHptmData ends by refreshing engagement + sentiment (same after wire:poll)
        $this->loadHptmData();
        $this->loadStripePaymentHistory();
    }

    /**
     * Load invoice + non-invoice charge history from Stripe (all pages; all linked customers).
     */
    protected function loadStripePaymentHistory(): void
    {
        $this->stripePaymentHistory = [];
        $this->stripePaymentHistoryError = null;
        $this->stripeCustomerLinked = false;

        $customerIds = $this->collectStripeCustomerIdsForUser();

        if ($customerIds === []) {
            return;
        }

        $this->stripeCustomerLinked = true;

        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            $rows = [];
            $seenInvoiceIds = [];

            foreach ($customerIds as $customerId) {
                foreach ($this->fetchAllInvoicesForCustomer($customerId) as $inv) {
                    if (isset($seenInvoiceIds[$inv->id])) {
                        continue;
                    }
                    $seenInvoiceIds[$inv->id] = true;
                    $rows[] = $this->mapStripeInvoiceToRow($inv);
                }

                foreach ($this->fetchChargesWithoutInvoiceForCustomer($customerId) as $ch) {
                    $rows[] = $this->mapStripeChargeToRow($ch);
                }
            }

            usort($rows, function (array $a, array $b) {
                return ($b['sort_ts'] ?? 0) <=> ($a['sort_ts'] ?? 0);
            });

            foreach ($rows as $row) {
                unset($row['sort_ts']);
                $this->stripePaymentHistory[] = $row;
            }
        } catch (\Throwable $e) {
            Log::warning('ViewBasecampUser: Stripe payment history failed', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
            ]);
            $this->stripePaymentHistoryError = 'Unable to load payment history from Stripe.';
        }
    }

    /**
     * @return list<string>
     */
    protected function collectStripeCustomerIdsForUser(): array
    {
        $ids = SubscriptionRecord::query()
            ->where('user_id', $this->user->id)
            ->whereNotNull('stripe_customer_id')
            ->pluck('stripe_customer_id')
            ->unique()
            ->filter()
            ->values()
            ->all();

        if ($this->user->orgId) {
            $orgCid = Organisation::query()->whereKey($this->user->orgId)->value('stripe_customer_id');
            if ($orgCid && ! in_array($orgCid, $ids, true)) {
                $ids[] = $orgCid;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<\Stripe\Invoice>
     */
    protected function fetchAllInvoicesForCustomer(string $customerId): array
    {
        $all = [];
        $params = ['customer' => $customerId, 'limit' => 100];

        while (true) {
            $page = StripeInvoice::all($params);
            foreach ($page->data as $inv) {
                $all[] = $inv;
            }
            if (! $page->has_more || empty($page->data)) {
                break;
            }
            $last = $page->data[count($page->data) - 1];
            $params['starting_after'] = $last->id;
        }

        return $all;
    }

    /**
     * Charges not linked to an invoice (avoids duplicating subscription invoice payments).
     *
     * @return list<\Stripe\Charge>
     */
    protected function fetchChargesWithoutInvoiceForCustomer(string $customerId): array
    {
        $all = [];
        $params = ['customer' => $customerId, 'limit' => 100];

        while (true) {
            $page = StripeCharge::all($params);
            foreach ($page->data as $ch) {
                if (! empty($ch->invoice)) {
                    continue;
                }
                $all[] = $ch;
            }
            if (! $page->has_more || empty($page->data)) {
                break;
            }
            $last = $page->data[count($page->data) - 1];
            $params['starting_after'] = $last->id;
        }

        return $all;
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapStripeInvoiceToRow(\Stripe\Invoice $inv): array
    {
        $amountPaid = isset($inv->amount_paid) ? ((int) $inv->amount_paid) / 100 : 0.0;
        $currency = strtoupper((string) ($inv->currency ?? 'gbp'));
        $created = isset($inv->created) ? \Carbon\Carbon::createFromTimestamp((int) $inv->created) : null;

        $lineDesc = null;
        if (isset($inv->lines->data[0])) {
            $lineDesc = $inv->lines->data[0]->description ?? null;
        }

        return [
            'kind' => 'invoice',
            'id' => $inv->id,
            'number' => $inv->number ?? $inv->id,
            'status' => $inv->status ?? 'unknown',
            'amount_paid' => $amountPaid,
            'amount_formatted' => number_format($amountPaid, 2) . ' ' . $currency,
            'currency' => $currency,
            'created_at' => $created,
            'created_formatted' => $created?->format('M j, Y g:i A') ?? '',
            'hosted_invoice_url' => $inv->hosted_invoice_url ?? null,
            'invoice_pdf' => $inv->invoice_pdf ?? null,
            'description' => $inv->description ?? $lineDesc,
            'receipt_url' => null,
            'sort_ts' => (int) ($inv->created ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapStripeChargeToRow(\Stripe\Charge $ch): array
    {
        $amount = isset($ch->amount) ? ((int) $ch->amount) / 100 : 0.0;
        $currency = strtoupper((string) ($ch->currency ?? 'gbp'));
        $created = isset($ch->created) ? \Carbon\Carbon::createFromTimestamp((int) $ch->created) : null;

        $status = match ($ch->status ?? '') {
            'succeeded' => 'paid',
            'failed' => 'failed',
            default => $ch->status ?? 'unknown',
        };

        return [
            'kind' => 'charge',
            'id' => $ch->id,
            'number' => $ch->id,
            'status' => $status,
            'amount_paid' => $amount,
            'amount_formatted' => number_format($amount, 2) . ' ' . $currency,
            'currency' => $currency,
            'created_at' => $created,
            'created_formatted' => $created?->format('M j, Y g:i A') ?? '',
            'hosted_invoice_url' => null,
            'invoice_pdf' => null,
            'description' => $ch->description ?? 'Card payment',
            'receipt_url' => $ch->receipt_url ?? null,
            'sort_ts' => (int) ($ch->created ?? 0),
        ];
    }
    
    public function loadEngagementData()
    {
        $userId = $this->user->id;
        
        // Get engagement service
        $engagementService = app(EngagementService::class);
        
        // Get user's timezone
        $userTimezone = \App\Helpers\TimezoneHelper::getUserTimezone($this->user);
        $userNow = \App\Helpers\TimezoneHelper::carbon(null, $userTimezone);
        
        // Get user's working days
        $workingDays = ["Mon", "Tue", "Wed", "Thu", "Fri"]; // Default working days
        if ($this->user->hasRole('basecamp')) {
            // For basecamp users, all days are working days
            $workingDays = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
        } else {
            // For organization users, use organization's working days
            $org = $this->user->organisation;
            if ($org && $org->working_days) {
                if (is_array($org->working_days)) {
                    $workingDays = $org->working_days;
                } else {
                    $decoded = json_decode($org->working_days, true);
                    $workingDays = $decoded ?: $workingDays;
                }
            }
        }
        
        // Calculate period: from user registration to today
        $startDate = \Carbon\Carbon::parse($this->user->created_at)->setTimezone($userTimezone)->startOfDay();
        $endDate = $userNow->copy()->endOfDay();
        
        // Count sentiment submissions in period
        $sentimentSubmissions = \App\Models\HappyIndex::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate->utc(), $endDate->utc()])
            ->count();
        
        // Count working days in period
        $workingDaysCount = $this->countWorkingDays($startDate, $endDate, $workingDays, $this->user);
        
        // Calculate EI Score as percentage: (Sentiment Submissions / Working Days) x 100
        $eiScorePercentage = $workingDaysCount > 0 
            ? round(($sentimentSubmissions / $workingDaysCount) * 100, 2) 
            : 0;
        
        // Get HPTM Score (raw score - lifetime, never resets)
        $hptmScore = $this->hptmData['totalRawScore'] ?? 0;
        
        // Calculate Engagement Index: (EI Score % / 100 x HPTM Score) + HPTM Score
        $engagementIndex = ($eiScorePercentage / 100 * $hptmScore) + $hptmScore;
        
        // Calculate total engagement score (for display - same as Engagement Index)
        $totalEngagementScore = $engagementIndex;
        
        // Calculate engagement index for today (in user's timezone) - for legacy compatibility
        $userTodayDate = $userNow->toDateString();
        $userDataArr = [
            'orgId' => $this->user->orgId,
            'userId' => $userId,
            'HI_include_saturday' => $this->user->HI_include_saturday ?? 0,
            'HI_include_sunday' => $this->user->HI_include_sunday ?? 0,
        ];
        $legacyEngagementIndex = $engagementService->getUserEngagementIndexForLastDay($userDataArr, $userTodayDate);
        
        $this->hptmData['engagement'] = [
            'totalScore' => $totalEngagementScore,
            'eiScore' => $eiScorePercentage, // Now a percentage
            'eiScorePoints' => $this->user->EIScore ?? 0, // Keep old points for reference
            'eiScoreSubmissions' => $sentimentSubmissions,
            'eiScoreWorkingDays' => $workingDaysCount,
            'hptmScore' => $hptmScore,
            'engagementIndex' => $engagementIndex,
            'legacyEngagementIndex' => $legacyEngagementIndex, // Keep for compatibility
        ];
    }
    
    /**
     * Count working days between two dates based on user's working days configuration
     */
    private function countWorkingDays($startDate, $endDate, $workingDays, $user)
    {
        $count = 0;
        $current = $startDate->copy();
        
        // Map day names to Carbon day of week (0=Sunday, 1=Monday, etc.)
        $dayMap = [
            'Sun' => 0,
            'Mon' => 1,
            'Tue' => 2,
            'Wed' => 3,
            'Thu' => 4,
            'Fri' => 5,
            'Sat' => 6,
        ];
        
        $workingDaysNumeric = [];
        foreach ($workingDays as $day) {
            if (isset($dayMap[$day])) {
                $workingDaysNumeric[] = $dayMap[$day];
            }
        }
        
        // Also consider HI_include_saturday and HI_include_sunday
        $includeSaturday = $user->HI_include_saturday ?? 0;
        $includeSunday = $user->HI_include_sunday ?? 0;
        
        while ($current->lte($endDate)) {
            $dayOfWeek = $current->dayOfWeek; // 0=Sunday, 1=Monday, etc.
            
            // Check if this day is a working day
            if (in_array($dayOfWeek, $workingDaysNumeric)) {
                $count++;
            } elseif ($dayOfWeek == 6 && $includeSaturday == 1) { // Saturday
                $count++;
            } elseif ($dayOfWeek == 0 && $includeSunday == 1) { // Sunday
                $count++;
            }
            
            $current->addDay();
        }
        
        return $count;
    }

    /**
     * Recent Happy Index (sentiment) entries and simple stats for the control panel.
     */
    public function loadSentimentSummary(): void
    {
        $userId = $this->user->id;
        $now = \Carbon\Carbon::now();

        $base = fn () => HappyIndex::query()
            ->where('user_id', $userId)
            ->where(function ($q) {
                $q->where('status', 'Active')->orWhereNull('status');
            });

        $countLast7 = $base()->where('created_at', '>=', $now->copy()->subDays(7))->count();
        $countLast30 = $base()->where('created_at', '>=', $now->copy()->subDays(30))->count();
        $totalAll = $base()->count();

        $weeklyMoodDistribution = $base()
            ->where('created_at', '>=', $now->copy()->subDays(7))
            ->selectRaw('mood_value, COUNT(*) as c')
            ->groupBy('mood_value')
            ->pluck('c', 'mood_value')
            ->toArray();

        $monthlyMoodDistribution = $base()
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->selectRaw('mood_value, COUNT(*) as c')
            ->groupBy('mood_value')
            ->pluck('c', 'mood_value')
            ->toArray();

        $last7Entries = $base()
            ->with('mood')
            ->orderByDesc('created_at')
            ->limit(7)
            ->get()
            ->map(function (HappyIndex $row) {
                $label = $row->mood?->moodName ?? 'Mood';
                $emoji = $this->moodEmojiForMoodValueId((int) $row->mood_value);

                return [
                    'id' => $row->id,
                    'at' => $row->created_at,
                    'at_formatted' => $row->created_at?->format('M j, Y g:i A') ?? '',
                    'mood_label' => $label,
                    'emoji' => $emoji,
                    'description' => $row->description ? \Illuminate\Support\Str::limit(strip_tags($row->description), 200) : null,
                ];
            })
            ->values()
            ->all();

        $recent = $base()
            ->with('mood')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get()
            ->map(function (HappyIndex $row) {
                $label = $row->mood?->moodName ?? 'Mood';
                $emoji = $this->moodEmojiForMoodValueId((int) $row->mood_value);

                return [
                    'id' => $row->id,
                    'at' => $row->created_at,
                    'at_formatted' => $row->created_at?->format('M j, Y g:i A') ?? '',
                    'mood_label' => $label,
                    'emoji' => $emoji,
                    'description' => $row->description ? \Illuminate\Support\Str::limit(strip_tags($row->description), 200) : null,
                ];
            })
            ->values()
            ->all();

        $this->sentimentSummary = [
            'total_all_time' => $totalAll,
            'last_7_days' => $countLast7,
            'last_30_days' => $countLast30,
            'mood_distribution_7d' => $weeklyMoodDistribution,
            'mood_distribution_30d' => $monthlyMoodDistribution,
            'last_7_entries' => $last7Entries,
            'recent' => $recent,
        ];
    }

    private function moodEmojiForMoodValueId(int $moodValueId): string
    {
        return match ($moodValueId) {
            3 => '😊',
            2 => '😐',
            1 => '😢',
            default => '💭',
        };
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

        // wire:poll refreshes loadHptmData only — re-attach engagement + sentiment or they disappear on poll
        $this->loadEngagementData();
        $this->loadSentimentSummary();
    }

    public function render()
    {
        return view('livewire.view-basecamp-user')
            ->layout('layouts.app');
    }
}
