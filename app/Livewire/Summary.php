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

    protected $listeners = [
        'summary-saved' => 'loadSummary',
    ];

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
            ->get(['id', 'user_id', 'mood_value', 'description', 'status', 'timezone', 'created_at', 'updated_at']);

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
        $displayedEntryIds = []; // Track which entries have been displayed to prevent duplicates
        $displayedDates = []; // Track which dates (Y-m-d) have been displayed to prevent "Missed" for dates with entries

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

        $userToday = Carbon::now($userTimezone)->startOfDay(); // Use start of day for date comparison

        // Process all entries and determine which period date each should appear on
        // Key: period date (Y-m-d in user timezone), Value: entry object
        $entryMapByPeriodDate = [];
        $usedEntryIds = []; // Track which entries have been used to prevent duplicates
        
        // First, build a map of entries by their stored date (in their stored timezone)
        // This helps us quickly find entries for a given date
        $entriesByStoredDate = [];
        foreach ($happyIndexes as $entry) {
            // Get stored timezone from database
            $entryTimezone = $entry->timezone ?? $userTimezone;
            if (!in_array($entryTimezone, timezone_identifiers_list())) {
                $entryTimezone = $userTimezone;
            }
            
            // Get entry's date in its stored timezone
            $entryDate = \App\Helpers\TimezoneHelper::setTimezone(Carbon::parse($entry->created_at), $entryTimezone)->startOfDay();
            $entryDateKey = $entryDate->format('Y-m-d'); // Date in entry's stored timezone
            
            // Debug: Log entry processing
            if ($entry->id == 379) {
                Log::info('Processing entry 379 (Feb 4th)', [
                    'user_id' => $userId,
                    'entry_id' => $entry->id,
                    'entry_timezone' => $entryTimezone,
                    'entry_created_at_utc' => $entry->created_at,
                    'entry_date_in_stored_tz' => $entryDateKey,
                    'entry_date_full' => $entryDate->toDateTimeString(),
                ]);
            }
            
            // Store entry by its stored date key
            if (!isset($entriesByStoredDate[$entryDateKey])) {
                $entriesByStoredDate[$entryDateKey] = [];
            }
            $entriesByStoredDate[$entryDateKey][] = $entry;
        }
        
        // Debug: Log entries by stored date
        Log::info('Entries by stored date', [
            'user_id' => $userId,
            'entries_by_stored_date_keys' => array_keys($entriesByStoredDate),
            'entry_379_in_map' => isset($entriesByStoredDate['2026-02-04']) ? 'yes' : 'no',
        ]);
        
        // Group entries by their stored timezone date (Y-m-d format in entry's stored timezone)
        // This ensures we use the timezone-based date for matching
        $entriesByStoredTimezoneDate = [];
        foreach ($happyIndexes as $entry) {
            // Get entry's stored timezone
            $entryTimezone = $entry->timezone ?? $userTimezone;
            if (!in_array($entryTimezone, timezone_identifiers_list())) {
                $entryTimezone = $userTimezone;
            }
            
            // Get entry's date in its stored timezone (this is the date we should use)
            $entryDateInStoredTimezone = \App\Helpers\TimezoneHelper::setTimezone(Carbon::parse($entry->created_at), $entryTimezone)->startOfDay();
            $entryStoredDateKey = $entryDateInStoredTimezone->format('Y-m-d'); // Date in entry's stored timezone
            
            // Group entries by their stored timezone date
            if (!isset($entriesByStoredTimezoneDate[$entryStoredDateKey])) {
                $entriesByStoredTimezoneDate[$entryStoredDateKey] = [];
            }
            $entriesByStoredTimezoneDate[$entryStoredDateKey][] = $entry;
        }
        
        // For each stored timezone date, get the latest entry
        $latestEntryByStoredDate = [];
        foreach ($entriesByStoredTimezoneDate as $storedDateKey => $entriesForDate) {
            // Sort by created_at descending and take the first (latest) one
            usort($entriesForDate, function($a, $b) {
                return \Carbon\Carbon::parse($b->created_at)->timestamp <=> \Carbon\Carbon::parse($a->created_at)->timestamp;
            });
            $latestEntryByStoredDate[$storedDateKey] = $entriesForDate[0];
        }
        
        // Now map entries to period dates based on stored timezone date
        // Match: period date's day/month/year = entry's stored day/month/year (in entry's timezone)
        // IMPORTANT: Each entry can only be mapped to ONE period date to prevent duplicates
        foreach ($period as $periodDate) {
            $periodDateStr = $periodDate->format('Y-m-d'); // Date in current user timezone
            
            // Skip if this period date already has an entry mapped
            if (isset($entryMapByPeriodDate[$periodDateStr])) {
                continue;
            }
            
            // Get period date's day, month, year
            $periodDay = (int)$periodDate->format('d');
            $periodMonth = (int)$periodDate->format('m');
            $periodYear = (int)$periodDate->format('Y');
            
            // Find entry that matches this period date based on stored timezone date
            $matchedEntry = null;
            $exactMatchEntry = null;
            $timezoneMatchEntry = null;
            
            foreach ($latestEntryByStoredDate as $storedDateKey => $entry) {
                // Skip if already used (this entry is already mapped to another period date)
                if (in_array($entry->id, $usedEntryIds)) {
                    continue;
                }
                
                // Get entry's stored timezone
                $entryTimezone = $entry->timezone ?? $userTimezone;
                if (!in_array($entryTimezone, timezone_identifiers_list())) {
                    $entryTimezone = $userTimezone;
                }
                
                // Get entry's date in its stored timezone
                $entryDateInStoredTimezone = \App\Helpers\TimezoneHelper::setTimezone(Carbon::parse($entry->created_at), $entryTimezone)->startOfDay();
                $entryDay = (int)$entryDateInStoredTimezone->format('d');
                $entryMonth = (int)$entryDateInStoredTimezone->format('m');
                $entryYear = (int)$entryDateInStoredTimezone->format('Y');
                
                // Check if period date's day/month/year matches entry's stored day/month/year
                // This ensures entries show on the same day number they were saved (like the calendar)
                if ($periodDay === $entryDay && $periodMonth === $entryMonth && $entryYear === $periodYear) {
                    // Priority: exact timezone match first
                    if ($entryTimezone === $userTimezone) {
                        $exactMatchEntry = $entry;
                        break; // Found exact match, use it immediately
                    } elseif ($timezoneMatchEntry === null) {
                        // Store as timezone match (fallback, but only if no exact match found)
                        $timezoneMatchEntry = $entry;
                    }
                }
            }
            
            // Use exact match if available, otherwise use timezone match
            $matchedEntry = $exactMatchEntry ?? $timezoneMatchEntry;
            
            if ($matchedEntry) {
                $entryMapByPeriodDate[$periodDateStr] = $matchedEntry;
                $usedEntryIds[] = $matchedEntry->id; // Mark as used - prevents this entry from being mapped again
                
                // Debug: Log mapping result
                if ($periodDateStr === '2026-02-04' || $periodDateStr === '2026-02-05' || $periodDateStr === '2026-02-03') {
                    $matchedEntryDate = \App\Helpers\TimezoneHelper::setTimezone(Carbon::parse($matchedEntry->created_at), $matchedEntry->timezone ?? $userTimezone)->startOfDay();
                    Log::info('Entry mapped', [
                        'user_id' => $userId,
                        'period_date_str' => $periodDateStr,
                        'period_day' => $periodDay,
                        'entry_id' => $matchedEntry->id,
                        'entry_timezone' => $matchedEntry->timezone,
                        'entry_stored_date' => $matchedEntryDate->format('Y-m-d'),
                        'entry_day' => (int)$matchedEntryDate->format('d'),
                        'match_type' => $exactMatchEntry ? 'exact' : 'timezone',
                    ]);
                }
            }
        }
        
        // Track which period dates have entries (to prevent showing "Missed" for dates with entries)
        $periodDatesWithEntries = array_keys($entryMapByPeriodDate);
        
        // Debug: Log entry mapping
        Log::info('Entry mapping completed', [
            'user_id' => $userId,
            'total_entries' => $happyIndexes->count(),
            'mapped_entries' => count($entryMapByPeriodDate),
            'mapped_dates' => array_keys($entryMapByPeriodDate),
        ]);

        // Track which date keys we've already processed to prevent duplicates
        $processedDateKeys = [];
        // Track displayed entry date strings to prevent duplicates based on formatted date
        $displayedEntryDateStrings = [];
        
        foreach ($period as $date) {
            // Skip future dates (using user's timezone)
            // Compare dates only (not time) by using startOfDay
            $dateStartOfDay = $date->copy()->startOfDay();
            if ($dateStartOfDay->greaterThan($userToday)) {
                Log::info('Skipping future date', [
                    'user_id' => $userId,
                    'date' => $date->toDateString(),
                    'user_today' => $userToday->toDateString(),
                ]);
                continue;
            }
            
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
            $dateKey = $date->format('Y-m-d');
            
            // Skip if we've already processed this date key (prevent duplicates)
            if (in_array($dateKey, $processedDateKeys)) {
                continue;
            }
            $processedDateKeys[] = $dateKey;

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

            // Check if there's an entry for this period date
            $entry = $entryMapByPeriodDate[$dateKey] ?? null;
            
            // Debug: Log entry check for Feb 4th
            if ($dateKey === '2026-02-04') {
                Log::info('Checking Feb 4th entry', [
                    'user_id' => $userId,
                    'dateKey' => $dateKey,
                    'has_entry' => $entry ? true : false,
                    'entry_id' => $entry ? $entry->id : null,
                    'all_mapped_dates' => array_keys($entryMapByPeriodDate),
                ]);
            }

            if ($entry) {
                // Skip if this entry has already been displayed (by ID)
                if (in_array($entry->id, $displayedEntryIds)) {
                    continue;
                }
                
                // Use entry's stored timezone to format the date string
                $entryTimezone = $entry->timezone ?? $userTimezone;
                if (!in_array($entryTimezone, timezone_identifiers_list())) {
                    $entryTimezone = $userTimezone;
                }
                $entryDate = \App\Helpers\TimezoneHelper::setTimezone(Carbon::parse($entry->created_at), $entryTimezone);
                $entryDateStr = $entryDate->format('M d, Y');
                $entryDateKeyFormatted = $entryDate->format('Y-m-d');
                
                // Skip if we've already displayed an entry with this formatted date string
                // This prevents duplicates when multiple entries exist for the same date
                if (in_array($entryDateStr, $displayedEntryDateStrings)) {
                    // Mark this entry as displayed to prevent it from being checked again
                    $displayedEntryIds[] = $entry->id;
                    continue;
                }
                
                // Also check if this date key (Y-m-d format) has already been processed
                // This is an additional safety check
                if (in_array($entryDateKeyFormatted, $displayedDates)) {
                    // Mark this entry as displayed to prevent it from being checked again
                    $displayedEntryIds[] = $entry->id;
                    continue;
                }
                
                // Mark this entry as displayed BEFORE adding to array to prevent race conditions
                $displayedEntryIds[] = $entry->id;
                $displayedEntryDateStrings[] = $entryDateStr;
                $displayedDates[] = $entryDateKeyFormatted;
                
                $image = match($entry->mood_value) {
                    3       => 'happy-user.svg',
                    2       => 'sad-user.svg',
                    1       => 'avarege-user.svg',
                    default => 'sad-index.svg',
                };

                $entriesWithStatus[] = [
                    'date'        => $entryDateStr, // Use entry's date in its stored timezone
                    'score'       => $entry->score,
                    'mood_value'  => $entry->mood_value,
                    'description' => $entry->description ?? 'No message added.',
                    'image'       => $image,
                    'status'      => 'Present',
                ];
                
                // Mark this date as having an entry displayed (use both dateKey and entryDateStr for safety)
                $displayedDates[] = $dateKey;
                $entryDateKey = $entryDate->format('Y-m-d');
                if ($entryDateKey !== $dateKey) {
                    $displayedDates[] = $entryDateKey;
                }
                
                // Continue to next date - don't process "Missed" for this date
                continue;

            } else {
                // Before showing "Missed", check if this date already has an entry mapped or displayed
                // Check directly in the map to ensure we have the latest state
                if (isset($entryMapByPeriodDate[$dateKey]) || in_array($dateKey, $displayedDates)) {
                    continue; // Skip showing "Missed" if entry exists for this date
                }
                
                // Additional check: verify no entry exists for this date by checking all entries
                // This is a safety check in case the mapping missed an entry due to timezone issues
                $hasEntryForThisDate = false;
                $foundEntry = null;
                
                foreach ($happyIndexes as $checkEntry) {
                    // Skip if already displayed (to avoid checking entries we've already shown)
                    if (in_array($checkEntry->id, $displayedEntryIds)) {
                        continue;
                    }
                    
                    $checkEntryTimezone = $checkEntry->timezone ?? $userTimezone;
                    if (!in_array($checkEntryTimezone, timezone_identifiers_list())) {
                        $checkEntryTimezone = $userTimezone;
                    }
                    
                    // Convert period date to entry's timezone
                    $dateInEntryTimezone = \App\Helpers\TimezoneHelper::setTimezone($date->copy(), $checkEntryTimezone)->startOfDay();
                    
                    // Get entry's date in its stored timezone
                    $checkEntryDate = \App\Helpers\TimezoneHelper::setTimezone(Carbon::parse($checkEntry->created_at), $checkEntryTimezone)->startOfDay();
                    
                    // If dates match, an entry exists for this date
                    if ($dateInEntryTimezone->format('Y-m-d') === $checkEntryDate->format('Y-m-d')) {
                        $hasEntryForThisDate = true;
                        $foundEntry = $checkEntry;
                        // Map it now to prevent "Missed" and ensure it's displayed
                        $entryMapByPeriodDate[$dateKey] = $checkEntry;
                        break;
                    }
                }
                
                if ($hasEntryForThisDate && $foundEntry && !in_array($foundEntry->id, $displayedEntryIds)) {
                    // Display the entry as "Present" (it wasn't mapped earlier, so we display it now)
                    $displayedEntryIds[] = $foundEntry->id;
                    
                    $entryTimezone = $foundEntry->timezone ?? $userTimezone;
                    if (!in_array($entryTimezone, timezone_identifiers_list())) {
                        $entryTimezone = $userTimezone;
                    }
                    $entryDate = \App\Helpers\TimezoneHelper::setTimezone(Carbon::parse($foundEntry->created_at), $entryTimezone);
                    $entryDateStr = $entryDate->format('M d, Y');
                    
                    $image = match($foundEntry->mood_value) {
                        3       => 'happy-user.svg',
                        2       => 'sad-user.svg',
                        1       => 'avarege-user.svg',
                        default => 'sad-index.svg',
                    };

                    $entriesWithStatus[] = [
                        'date'        => $entryDateStr,
                        'score'       => $foundEntry->score,
                        'mood_value'  => $foundEntry->mood_value,
                        'description' => $foundEntry->description ?? 'No message added.',
                        'image'       => $image,
                        'status'      => 'Present',
                    ];
                    
                    // Mark this date as having an entry displayed
                    $displayedDates[] = $dateKey;
                    continue; // Skip showing "Missed" since we just displayed "Present"
                }
                
                // If entry exists but was already displayed, skip "Missed"
                if ($hasEntryForThisDate) {
                    // Mark this date as having an entry (even if already displayed)
                    $displayedDates[] = $dateKey;
                    continue;
                }
                
                // Final check: ensure this date hasn't been displayed yet
                if (in_array($dateKey, $displayedDates)) {
                    continue; // Skip if this date already has an entry displayed
                }
                
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
                    // Mark this date as displayed to prevent duplicates
                    $displayedDates[] = $dateKey;
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

        // Don't dispatch 'summary-saved' here - it causes infinite loop
        // This event should only be dispatched from external components (like DashboardSummary)
    }



    public function render()
    {
        return view('livewire.summary')->layout('layouts.app');
    }
}
