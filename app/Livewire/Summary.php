<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\HappyIndex;
use App\Models\UserLeave;
use App\Models\Organisation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class Summary extends Component
{
    public $filterType = 'all';
    public $startDate;
    public $endDate;
    public $summary = [];
    public $entries = [];
    public $leaves = [];

    public function mount()
    {
        $this->loadSummary();
    }

    private function validateCustomDates()
    {
        $this->validate([
            'startDate' => 'required|date|before_or_equal:endDate',
            'endDate'   => 'required|date|after_or_equal:startDate',
        ], [
            'startDate.required' => 'Start date is required.',
            'endDate.required'   => 'End date is required.',
            'startDate.before_or_equal' => 'Start date must be before or equal to End date.',
            'endDate.after_or_equal'    => 'End date must be after or equal to Start date.',
        ]);
    }

    public function resetFilters()
    {
        $this->filterType = 'all';
        $this->startDate = null;
        $this->endDate = null;
        $this->loadSummary();
    }

    public function updatedFilterType() { $this->loadSummary(); }
    public function updatedStartDate() { $this->loadSummary(); }
    public function updatedEndDate() { $this->loadSummary(); }

    public function loadSummary()
    {
        $user = Auth::user();
        $userId = $user->id;

        // Debug: Log current filter type
        Log::info('loadSummary called', [
            'user_id' => $userId,
            'filterType' => $this->filterType,
        ]);

        // Get user's timezone safely using helper
        $userTimezone = \App\Helpers\TimezoneHelper::getUserTimezone($user);

        // Get user's working days
        $workingDays = ["Mon", "Tue", "Wed", "Thu", "Fri"]; // Default working days
        
        if ($user->hasRole('basecamp')) {
            // For basecamp users, all days are working days
            $workingDays = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
        } else {
            // For organization users, use organization's working days
            $org = $user->organisation;
            if ($org && $org->working_days) {
                if (is_array($org->working_days)) {
                    $workingDays = $org->working_days;
                } else {
                    $decoded = json_decode($org->working_days, true);
                    $workingDays = $decoded ?: $workingDays;
                }
            }
        }

        // Determine date range using user's timezone
        $userNow = \App\Helpers\TimezoneHelper::carbon(null, $userTimezone);
        
        // Get user's registration date in user's timezone (minimum start date)
        $userRegistrationDate = Carbon::parse($user->created_at)->setTimezone($userTimezone)->startOfDay();
        
        // Ensure filterType is set
        if (empty($this->filterType)) {
            $this->filterType = 'all';
        }
        
        switch ($this->filterType) {
            case 'today':
                $start = $userNow->copy()->startOfDay();
                $end   = $userNow->copy()->endOfDay();
                break;

            case 'this_week':
                $start = $userNow->copy()->startOfWeek();
                $end   = $userNow->copy()->endOfWeek();
                break;

            case 'last_7_days':
                $start = $userNow->copy()->subDays(7);
                $end   = $userNow->copy();
                break;

            case 'previous_week':
                // Calculate previous week explicitly
                // Example: If current week is 11-17, previous week should be 4-10
                // Step 1: Get current week's Monday (start of current week)
                // Carbon's startOfWeek(MONDAY) should give us the Monday of the current week
                $currentWeekMonday = $userNow->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
                
                // Verify: If today is Jan 13 (Wed), current week Monday should be Jan 11 (Mon)
                // If the calculation is wrong, manually calculate it
                $dayOfWeek = $userNow->dayOfWeek; // 0=Sunday, 1=Monday, ..., 6=Saturday
                if ($dayOfWeek == 0) {
                    // If today is Sunday, current week Monday is 6 days back
                    $currentWeekMonday = $userNow->copy()->subDays(6)->startOfDay();
                } elseif ($dayOfWeek == 1) {
                    // If today is Monday, current week Monday is today
                    $currentWeekMonday = $userNow->copy()->startOfDay();
                } else {
                    // If today is Tue-Sat, current week Monday is (dayOfWeek - 1) days back
                    $currentWeekMonday = $userNow->copy()->subDays($dayOfWeek - 1)->startOfDay();
                }
                
                // Step 2: Previous week's Monday = current week Monday minus 7 days
                // If current week Monday is Jan 11, previous week Monday is Jan 4
                $previousWeekMonday = $currentWeekMonday->copy()->subDays(7)->startOfDay();
                
                // Step 3: Previous week's Sunday = previous week Monday + 6 days, end of day
                // If previous week Monday is Jan 4, previous week Sunday is Jan 10
                $previousWeekSunday = $previousWeekMonday->copy()->addDays(6)->endOfDay();
                
                $start = $previousWeekMonday;
                $end   = $previousWeekSunday;
                
                // Debug logging
                Log::info('Previous Week Filter Calculation', [
                    'user_id' => $userId,
                    'user_now' => $userNow->toDateTimeString(),
                    'user_now_day' => $userNow->format('l'),
                    'day_of_week' => $dayOfWeek,
                    'user_timezone' => $userTimezone,
                    'current_week_monday' => $currentWeekMonday->toDateString(),
                    'previous_week_monday' => $previousWeekMonday->toDateString(),
                    'previous_week_sunday' => $previousWeekSunday->toDateString(),
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                    'start_timestamp' => $start->timestamp,
                    'end_timestamp' => $end->timestamp,
                ]);
                break;

            case 'this_month':
                $start = $userNow->copy()->startOfMonth();
                $end   = $userNow->copy()->endOfMonth();
                break;

            case 'previous_month':
                $start = $userNow->copy()->subMonth()->startOfMonth();
                $end   = $userNow->copy()->subMonth()->endOfMonth();
                break;

            case 'custom':
                $this->validateCustomDates();
                $start = Carbon::parse($this->startDate)->setTimezone($userTimezone);
                $end   = Carbon::parse($this->endDate)->setTimezone($userTimezone);
                break;

            case 'all':
            default:
                $start = $userRegistrationDate->copy();
                $end   = $userNow->copy()->endOfDay();
                break;
        }
        
        // Store original start and end for period generation (before any adjustments)
        $originalStart = $start->copy();
        $originalEnd = $end->copy();
        
        // Ensure start date is never before user's registration date
        if ($start->lessThan($userRegistrationDate)) {
            $start = $userRegistrationDate->copy();
        }

        // Convert date range to UTC for database query (created_at is stored in UTC)
        $startUTC = $start->utc();
        $endUTC = $end->utc();

        // Fetch happy indexes within UTC range
        $happyIndexes = HappyIndex::where('user_id', $userId)
            ->whereBetween('created_at', [$startUTC, $endUTC])
            ->get();

        // Fetch approved leaves
        $leaves = UserLeave::where('user_id', $userId)
            ->where('leave_status', 1)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start, $end])
                  ->orWhereBetween('end_date', [$start, $end]);
            })
            ->get();

        $entriesWithStatus = [];
        $leavesArray = [];

        // Create period using user's timezone dates
        // Use original start/end for period (not adjusted for registration date)
        // This ensures we show the complete filter range, even if some dates are before registration
        $periodStart = $originalStart->copy()->startOfDay();
        $periodEnd = $originalEnd->copy()->endOfDay();
        
        $period = collect($periodStart->copy()->daysUntil($periodEnd->copy()->addDay()))
            ->map(function ($date) use ($userTimezone) {
                return \App\Helpers\TimezoneHelper::setTimezone(Carbon::parse($date), $userTimezone)->startOfDay();
            })
            ->filter(function ($date) use ($periodStart, $periodEnd) {
                // Ensure date is within the period range
                return $date->gte($periodStart) && $date->lte($periodEnd);
            })
            ->sortByDesc(fn($d) => $d->timestamp);
        
        // Debug logging for period
        Log::info('Period Generation', [
            'user_id' => $userId,
            'filterType' => $this->filterType,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'period_count' => $period->count(),
            'period_dates' => $period->map(fn($d) => $d->toDateString())->toArray(),
        ]);

        $userToday = Carbon::now($userTimezone);

        foreach ($period as $date) {
            // Skip future dates (using user's timezone)
            if ($date->greaterThan($userToday)) continue;
            
            // Skip dates before user's registration date
            if ($date->lessThan($userRegistrationDate)) continue;

            $dayOfWeek = $date->format('D');
            $isBasecamp = $user->hasRole('basecamp');
            $isWorkingDay = in_array($dayOfWeek, $workingDays);
            
            // For basecamp users: show all days, but only mark as "Missed" if it's a working day
            // For organization users: skip non-working days entirely
            if (!$isBasecamp && !$isWorkingDay) {
                // Debug logging for skipped dates
                Log::info('Skipping non-working day', [
                    'user_id' => $userId,
                    'date' => $date->toDateString(),
                    'day_of_week' => $dayOfWeek,
                    'workingDays' => $workingDays,
                    'is_basecamp' => $isBasecamp,
                ]);
                continue;
            }

            $dateStr = $date->format('M d, Y');

            // Check leave
            $onLeave = $leaves->first(fn($l) =>
                $date->between(Carbon::parse($l->start_date), Carbon::parse($l->end_date))
            );

            if ($onLeave) {
                $entriesWithStatus[] = [
                    'date'        => $dateStr,
                    'score'       => null,
                    'mood_value'  => null,
                    'description' => "You were on leave on $dateStr",
                    'image'       => 'leave-user.svg',
                    'status'      => 'Out of office',
                ];
                $leavesArray[] = ['date' => $dateStr];
                continue;
            }

            // Check sentiment entry - convert entry's created_at (UTC) to user's timezone and compare dates
            $entry = $happyIndexes->first(function($h) use ($date, $userTimezone) {
                $entryDate = \App\Helpers\TimezoneHelper::setTimezone(Carbon::parse($h->created_at), $userTimezone);
                return $entryDate->isSameDay($date);
            });

            if ($entry) {
                $image = match($entry->mood_value) {
                    3       => 'happy-user.svg',
                    2       => 'sad-user.svg',
                    1       => 'avarege-user.svg',
                    default => 'sad-index.svg',
                };

                $entriesWithStatus[] = [
                    'date'        => $dateStr,
                    'score'       => $entry->score,
                    'mood_value'  => $entry->mood_value,
                    'description' => $entry->description ?? 'No message added.',
                    'image'       => $image,
                    'status'      => 'Present',
                ];

            } else {
                // Show "Missed" only for past working days
                // For basecamp users: show all days, but only mark as "Missed" if it's a working day
                // For organization users: only working days are shown, so mark as "Missed"
                $shouldShowMissed = $date->isPast() && !$date->isSameDay($userToday) && $isWorkingDay;
                
                if ($shouldShowMissed) {
                    $entriesWithStatus[] = [
                        'date'        => $dateStr,
                        'score'       => null,
                        'mood_value'  => null,
                        'description' => "Oh Dear, you missed to share your sentiment on $dateStr",
                        'image'       => 'sentiment-missed-summary.svg',
                        'status'      => 'Missed',
                    ];
                } elseif ($isBasecamp && !$isWorkingDay && $date->isPast() && !$date->isSameDay($userToday)) {
                    // For basecamp users on non-working days: show the day but don't mark as "Missed"
                    // This ensures all days are visible in the summary
                    $entriesWithStatus[] = [
                        'date'        => $dateStr,
                        'score'       => null,
                        'mood_value'  => null,
                        'description' => "No sentiment required for $dateStr (non-working day)",
                        'image'       => 'sentiment-missed-summary.svg',
                        'status'      => 'Not Required',
                    ];
                }
            }
        }

        $this->summary = $entriesWithStatus;
        $this->leaves = $leavesArray;

        $this->dispatch('summary-saved');
    }



    public function render()
    {
        return view('livewire.summary')->layout('layouts.app');
    }
}
