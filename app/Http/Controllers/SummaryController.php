<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\HappyIndex;
use App\Models\UserLeave;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Http\Controllers\Concerns\UpdatesUserTimezone;

/**
 * @OA\Tag(
 *     name="Summary",
 *     description="User summary endpoints for Happy Index and leave data"
 * )
 */
class SummaryController extends Controller
{
    use UpdatesUserTimezone;
  
  /**
   * Get user's Happy Index and leave summary.
   *
   * @OA\Get(
   *     path="/api/summary/{filterType}",
   *     tags={"Summary"},
   *     summary="Get user summary by filter type",
   *     description="Retrieve user's Happy Index and leave summary based on filter type (this_week, last_7_days, previous_week, this_month, previous_month, custom, all)",
   *     security={{"bearerAuth":{}}},
   *     @OA\Parameter(
   *         name="filterType",
   *         in="path",
   *         required=true,
   *         description="Filter type for summary",
   *         @OA\Schema(
   *             type="string",
   *             enum={"this_week", "last_7_days", "previous_week", "this_month", "previous_month", "custom", "all"},
   *             example="this_week"
   *         )
   *     ),
   *     @OA\Parameter(
   *         name="start_date",
   *         in="query",
   *         required=false,
   *         description="Start date for custom filter (format: Y-m-d)",
   *         @OA\Schema(type="string", format="date", example="2024-01-01")
   *     ),
   *     @OA\Parameter(
   *         name="end_date",
   *         in="query",
   *         required=false,
   *         description="End date for custom filter (format: Y-m-d)",
   *         @OA\Schema(type="string", format="date", example="2024-01-31")
   *     ),
   *     @OA\Parameter(
   *         name="timezone",
   *         in="query",
   *         required=false,
   *         description="User timezone (optional)",
   *         @OA\Schema(type="string", example="Asia/Kolkata")
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description="Summary retrieved successfully",
   *         @OA\JsonContent(
   *             @OA\Property(property="status", type="boolean", example=true),
   *             @OA\Property(
   *                 property="data",
   *                 type="array",
   *                 @OA\Items(
   *                     type="object",
   *                     @OA\Property(property="date", type="string", example="Jan 15, 2024"),
   *                     @OA\Property(property="score", type="integer", nullable=true, example=85),
   *                     @OA\Property(property="mood_value", type="integer", nullable=true, example=3, description="1=average, 2=sad, 3=happy"),
   *                     @OA\Property(property="description", type="string", example="Feeling great today!"),
   *                     @OA\Property(property="image", type="string", example="happy-app.png"),
   *                     @OA\Property(property="status", type="string", example="Present", description="Present, Out of office, or Missed")
   *                 )
   *             )
   *         )
   *     ),
   *     @OA\Response(
   *         response=401,
   *         description="Unauthorized",
   *         @OA\JsonContent(
   *             @OA\Property(property="status", type="boolean", example=false),
   *             @OA\Property(property="message", type="string", example="Unauthenticated")
   *         )
   *     )
   * )
   *
   * @param string $filterType
   * @param \Illuminate\Http\Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getSummary($filterType, Request $request)
{
    // COMMENTED OUT: Automatic timezone update from request
    // Timezone should be set from user profile instead
    // Update user timezone from request if provided
    // $this->updateUserTimezoneIfNeeded($request);
    
    $user = Auth::user();
    $userId = $user->id;
    $startDate = $request->get('start_date');
    $endDate = $request->get('end_date');

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
    $userNow = Carbon::now($userTimezone);
    
    // Get user's registration date in user's timezone (minimum start date)
    $userRegistrationDate = \App\Helpers\TimezoneHelper::setTimezone(Carbon::parse($user->created_at), $userTimezone)->startOfDay();
    
    switch ($filterType) {
        case 'today':
            $start = $userNow->copy()->startOfDay();
            $end = $userNow->copy()->endOfDay();
            break;
        case 'this_week':
            $start = $userNow->copy()->startOfWeek();
            $end = $userNow->copy()->endOfWeek();
            break;
        case 'last_7_days':
            $start = $userNow->copy()->subDays(7);
            $end = $userNow->copy();
            break;
        case 'previous_week':
            // Calculate previous week explicitly
            // Example: If current week is 11-17, previous week should be 4-10
            // Step 1: Get current week's Monday (start of current week)
            // Manually calculate to ensure accuracy
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
            $end = $previousWeekSunday;
            break;
        case 'this_month':
            $start = $userNow->copy()->startOfMonth();
            $end = $userNow->copy()->endOfMonth();
            break;
        case 'previous_month':
            $start = $userNow->copy()->subMonth()->startOfMonth();
            $end = $userNow->copy()->subMonth()->endOfMonth();
            break;
        case 'custom':
            if ($startDate && $endDate) {
                $start = \App\Helpers\TimezoneHelper::setTimezone(Carbon::parse($startDate), $userTimezone);
                $end = \App\Helpers\TimezoneHelper::setTimezone(Carbon::parse($endDate), $userTimezone);
            } else {
                $start = $userRegistrationDate->copy();
                $end = $userNow->copy()->endOfDay();
            }
            break;
        case 'all':
        default:
            $start = $userRegistrationDate->copy();
            $end = $userNow->copy()->endOfDay();
            break;
    }
    
    // Ensure start date is never before user's registration date
    if ($start->lessThan($userRegistrationDate)) {
        $start = $userRegistrationDate->copy();
    }

    // Convert date range to UTC for database query (created_at is stored in UTC)
    $startUTC = $start->utc();
    $endUTC = $end->utc();

    // Fetch happy indexes in UTC range
    $happyIndexes = HappyIndex::where('user_id', $userId)
        ->whereBetween('created_at', [$startUTC, $endUTC])
        ->get(['id', 'user_id', 'mood_value', 'description', 'status', 'timezone', 'created_at', 'updated_at']);

    // Fetch leaves in range
    $leaves = UserLeave::where('user_id', $userId)
        ->where('leave_status', 1)
        ->where(function ($q) use ($start, $end) {
            $q->whereBetween('start_date', [$start, $end])
              ->orWhereBetween('end_date', [$start, $end]);
        })
        ->get();

    $summary = [];
    $leavesArray = [];
    $displayedEntryIds = []; // Track which entries have been displayed to prevent duplicates
    $displayedDates = []; // Track which dates (Y-m-d) have been displayed to prevent "Missed" for dates with entries

    // Helper to build full image URL with current domain
    $imageUrl = function (string $fileName): string {
        return url('images/'.$fileName);
    };

    // Generate date range using user's timezone
    $period = collect($start->copy()->daysUntil($end->copy()->addDay()))
        ->map(function ($date) use ($userTimezone) {
            return \App\Helpers\TimezoneHelper::setTimezone(Carbon::parse($date), $userTimezone);
        })
        ->sortByDesc(fn($d) => $d->timestamp);

    $userToday = Carbon::now($userTimezone);
    $isBasecamp = $user->hasRole('basecamp');

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
        
        // Store entry by its stored date key
        if (!isset($entriesByStoredDate[$entryDateKey])) {
            $entriesByStoredDate[$entryDateKey] = [];
        }
        $entriesByStoredDate[$entryDateKey][] = $entry;
    }
    
    // Now, for each period date, find the matching entry
    // We iterate through period dates and find the entry that matches
    foreach ($period as $periodDate) {
        $periodDateStr = $periodDate->format('Y-m-d'); // Date in current user timezone
        
        // Skip if this period date already has an entry mapped
        if (isset($entryMapByPeriodDate[$periodDateStr])) {
            continue;
        }
        
        // Try to find an entry that matches this period date
        // Logic: Match entries based on their stored date (day number) in their stored timezone
        // The calendar shows entries on the day they were saved (e.g., Feb 3rd entry shows on day 3)
        // So for Daily Summary, we should match: period date's day number = entry's stored day number
        // AND period date's month/year = entry's stored month/year (in entry's timezone)
        
        $exactMatchEntry = null; // Entry where timezones match (priority)
        $timezoneMatchEntry = null; // Entry from different timezone
        
        // Get period date's day, month, year
        $periodDay = (int)$periodDate->format('d');
        $periodMonth = (int)$periodDate->format('m');
        $periodYear = (int)$periodDate->format('Y');
        
        // Iterate through all entries to find matches
        foreach ($happyIndexes as $entry) {
            // Skip if already used
            if (in_array($entry->id, $usedEntryIds)) {
                continue;
            }
            
            // Get entry's stored timezone
            $entryTimezone = $entry->timezone ?? $userTimezone;
            if (!in_array($entryTimezone, timezone_identifiers_list())) {
                $entryTimezone = $userTimezone;
            }
            
            // Get entry's date in its stored timezone (this is what we stored)
            $entryDateInStoredTimezone = \App\Helpers\TimezoneHelper::setTimezone(Carbon::parse($entry->created_at), $entryTimezone)->startOfDay();
            $entryDay = (int)$entryDateInStoredTimezone->format('d');
            $entryMonth = (int)$entryDateInStoredTimezone->format('m');
            $entryYear = (int)$entryDateInStoredTimezone->format('Y');
            
            // Check if period date's day/month/year matches entry's stored day/month/year
            // This ensures entries show on the same day number they were saved (like the calendar)
            if ($periodDay === $entryDay && $periodMonth === $entryMonth && $periodYear === $entryYear) {
                // If entry's timezone matches user's timezone, it's an exact match (priority)
                if ($entryTimezone === $userTimezone) {
                    $exactMatchEntry = $entry;
                    break; // Found exact match, use it
                } else {
                    // Store as timezone match (fallback)
                    if (!$timezoneMatchEntry) {
                        $timezoneMatchEntry = $entry;
                    }
                }
            }
        }
        
        // Use exact match if available, otherwise use timezone match
        $matchedEntry = $exactMatchEntry ?? $timezoneMatchEntry;
        
        if ($matchedEntry) {
            $entryMapByPeriodDate[$periodDateStr] = $matchedEntry;
            $usedEntryIds[] = $matchedEntry->id; // Mark as used
        }
    }
    
    // Track which period dates have entries (to prevent showing "Missed" for dates with entries)
    $periodDatesWithEntries = array_keys($entryMapByPeriodDate);

    // Track which date keys we've already processed to prevent duplicates
    $processedDateKeys = [];
    
    foreach ($period as $date) {
        // Skip future dates (using user's timezone)
        if ($date->greaterThan($userToday)) continue;
        
        // Skip dates before user's registration date
        if ($date->lessThan($userRegistrationDate)) continue;

        $dayOfWeek = $date->format('D');
        $isWorkingDay = in_array($dayOfWeek, $workingDays);
        
        // For basecamp users: show all days, but only mark as "Missed" if it's a working day
        // For organization users: skip non-working days entirely
        if (!$isBasecamp && !$isWorkingDay) {
            continue;
        }

        $dateStr = $date->format('M d, Y');
        $dateKey = $date->format('Y-m-d');
        
        // Skip if we've already processed this date key (prevent duplicates)
        if (in_array($dateKey, $processedDateKeys)) {
            continue;
        }
        $processedDateKeys[] = $dateKey;

        // Check if leave
        $onLeave = $leaves->first(fn($l) =>
            $date->between(Carbon::parse($l->start_date), Carbon::parse($l->end_date))
        );

        if ($onLeave) {
            $summary[] = [
                'date'        => $dateStr,
                'score'       => null,
                'mood_value'  => null,
                'description' => "You were on leave on $dateStr",
                'image'       => $imageUrl('leave-app.png'),
                'status'      => 'Out of office',
            ];
            $leavesArray[] = ['date' => $dateStr];
            continue;
        }

        // Check if there's an entry for this period date
        $entry = $entryMapByPeriodDate[$dateKey] ?? null;

        if ($entry) {
            // Skip if this entry has already been displayed
            if (in_array($entry->id, $displayedEntryIds)) {
                continue;
            }
            
            // Mark this entry as displayed
            $displayedEntryIds[] = $entry->id;
            
            // Use entry's stored timezone to format the date string
            $entryTimezone = $entry->timezone ?? $userTimezone;
            if (!in_array($entryTimezone, timezone_identifiers_list())) {
                $entryTimezone = $userTimezone;
            }
            $entryDate = \App\Helpers\TimezoneHelper::setTimezone(Carbon::parse($entry->created_at), $entryTimezone);
            $entryDateStr = $entryDate->format('M d, Y');
            
            $image = match($entry->mood_value) {
                3 => $imageUrl('happy-app.png'),
                2 => $imageUrl('sad-app.png'),
                1 => $imageUrl('average-app.png'),
                default => $imageUrl('sad-app.png'),
            };
            $summary[] = [
                'date'        => $entryDateStr, // Use entry's date in its stored timezone
                'score'       => $entry->score,
                'mood_value'  => $entry->mood_value,
                'description' => $entry->description ?? 'No message added.',
                'image'       => $image,
                'status'      => 'Present',
            ];
            
            // Mark this date as having an entry displayed (use both dateKey and entryDateKey for safety)
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
                    3 => $imageUrl('happy-app.png'),
                    2 => $imageUrl('sad-app.png'),
                    1 => $imageUrl('average-app.png'),
                    default => $imageUrl('sad-app.png'),
                };
                $summary[] = [
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
            $shouldShowMissed = !$date->isSameDay($userToday) && $isWorkingDay;
            
            if ($shouldShowMissed) {
                $summary[] = [
                    'date'        => $dateStr,
                    'score'       => null,
                    'mood_value'  => null,
                    'description' => "Oh Dear, you missed to share your sentiment on $dateStr",
                    'image'       => $imageUrl('sentiment-missed-summary.png'),
                    'status'      => 'Missed',
                ];
                // Mark this date as displayed to prevent duplicates
                $displayedDates[] = $dateKey;
            } elseif ($isBasecamp && !$isWorkingDay && !$date->isSameDay($userToday)) {
                // For basecamp users on non-working days: show the day but don't mark as "Missed"
                // This ensures all days are visible in the summary
                $summary[] = [
                    'date'        => $dateStr,
                    'score'       => null,
                    'mood_value'  => null,
                    'description' => "No sentiment required for $dateStr (non-working day)",
                    'image'       => $imageUrl('sentiment-missed-summary.png'),
                    'status'      => 'Not Required',
                ];
            }
        }
    }

    return response()->json([
        'status' => true,
        'data'   => $summary,
    ]);
}



}
