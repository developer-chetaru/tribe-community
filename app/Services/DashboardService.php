<?php

namespace App\Services;

use App\Models\HappyIndexDashboardGraph;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Department;
use App\Models\Office;
use App\Models\UserLeave;
use Illuminate\Support\Facades\Auth;
use App\Models\HappyIndex;
use Illuminate\Http\Request;
class DashboardService
{
    /**
     * Get dashboard details for the free version of the app.
     *
     * @param array $filters Optional filters like 'orgId', 'officeId', 'departmentId', 'year', 'month'.
     * @return array Returns structured dashboard data including HI values, year/month lists, not working days, etc.
     */
public function getFreeVersionHomeDetails(array $filters = [])
{
    $user = Auth::user();
    if (!$user) {
        return ['status' => false, 'message' => 'Unauthorized'];
    }

    $userId       = $user->id;
    $orgId        = $filters['orgId'] ?? $user->orgId;
    $officeId     = $filters['officeId'] ?? null;
    $departmentId = $filters['departmentId'] ?? null;
    $year         = $filters['year'] ?? now()->year;
    $month        = !empty($filters['month']) ? sprintf("%02d", $filters['month']) : sprintf("%02d", now()->month);
    $day          = $filters['day'] ?? null;

    $HI_include_saturday = $user->HI_include_saturday ?? 2;
    $HI_include_sunday   = $user->HI_include_sunday ?? 2;

    // If user doesn't have orgId, show only user-specific data
    if (empty($orgId)) {
        $filteredUserIds = [$userId];
    } else {
        // Filter office & department
        $filteredUserQuery = User::where('status', 1)->where('orgId', $orgId);

        if (!empty($officeId)) {
            $filteredUserQuery->where('officeId', $officeId);
        }

        if (!empty($departmentId)) {
            $filteredUserQuery->where('departmentId', $departmentId);
        }

        $filteredUserIds = $filteredUserQuery->pluck('id')->toArray();

        // Include current user if not in filtered list
        if (!in_array($userId, $filteredUserIds)) {
            $filteredUserIds[] = $userId;
        }
    }

    // Get user's timezone safely using helper
    $userTimezone = \App\Helpers\TimezoneHelper::getUserTimezone($user);
    
    // Get current date in user's timezone for "today" comparison
    $userNow = \App\Helpers\TimezoneHelper::carbon(null, $userTimezone);
    $userTodayDate = $userNow->toDateString(); // Y-m-d format in user's timezone

    // Days in selected month/year
    $noOfDaysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

    // Fetch happy indexes for filtered users
    // We fetch a wider range to ensure we get all entries that fall in the selected month in any user's timezone
    // IMPORTANT: We need to account for timezone differences - an entry created on Feb 5 in one timezone
    // might appear as Feb 4 or Feb 6 in another timezone. So we expand the range by ±1 day in UTC.
    $startDate = \Carbon\Carbon::create($year, $month, 1, 0, 0, 0, $userTimezone)->startOfMonth();
    $endDate = \Carbon\Carbon::create($year, $month, $noOfDaysInMonth, 23, 59, 59, $userTimezone)->endOfMonth();
    
    // Convert to UTC for database query (created_at is stored in UTC)
    $startDateUTC = $startDate->utc();
    $endDateUTC = $endDate->utc();
    
    // Expand range by ±1 day to account for timezone differences
    // This ensures entries created in different timezones are included
    $startDateUTC = $startDateUTC->subDay();
    $endDateUTC = $endDateUTC->addDay();
    
    // Fetch entries within the month range (UTC time for database query)
    $happyData = HappyIndex::whereBetween('created_at', [$startDateUTC, $endDateUTC])
        ->whereIn('user_id', $filteredUserIds)
        ->get(['created_at', 'mood_value', 'description', 'user_id', 'timezone']);

    // Eager load users to avoid N+1 query problem
    $userIds = $happyData->pluck('user_id')->unique()->toArray();
    $users = User::whereIn('id', $userIds)->get()->keyBy('id');

    $dateData = [];
    foreach ($happyData as $entry) {
        // IMPORTANT: Use stored timezone from entry if available, otherwise fallback to entry user's timezone
        // The entry's timezone field contains the timezone when the entry was created
        $entryUser = $users->get($entry->user_id);
        $entryUserTimezone = $entry->timezone ?? ($entryUser && $entryUser->timezone && in_array($entryUser->timezone, timezone_identifiers_list()) 
            ? $entryUser->timezone 
            : 'Asia/Kolkata');
        
        // Ensure timezone is valid
        if (!in_array($entryUserTimezone, timezone_identifiers_list())) {
            $entryUserTimezone = 'Asia/Kolkata';
        }
        
        // Convert entry's created_at (UTC) to entry's stored timezone to get the date
        // This ensures entries are displayed on the correct day based on when they were actually created (in their timezone)
        // Example: If entry was created on Feb 5 in US timezone, it should show on Feb 5, not Feb 6 (even if current timezone is different)
        $entryDate = \App\Helpers\TimezoneHelper::setTimezone(\Carbon\Carbon::parse($entry->created_at), $entryUserTimezone)->startOfDay();
        $entryYear = (int) $entryDate->format('Y');
        $entryMonth = (int) $entryDate->format('m');
        $d = (int) $entryDate->format('d'); // Day number in entry's stored timezone
        
        // Debug logging for recent entries or specific entry IDs
        if ($entry->id >= 380 || $entry->id == 382) { // Log recent entries or entry 382
            \Illuminate\Support\Facades\Log::info('DashboardService: Processing entry for calendar', [
                'entry_id' => $entry->id,
                'user_id' => $entry->user_id,
                'logged_in_user_id' => $userId,
                'created_at_utc' => $entry->created_at,
                'entry_timezone' => $entry->timezone,
                'entry_user_timezone' => $entryUserTimezone,
                'entry_date_in_stored_tz' => $entryDate->format('Y-m-d'),
                'entry_day' => $d,
                'entry_month' => $entryMonth,
                'entry_year' => $entryYear,
                'selected_month' => $month,
                'selected_year' => $year,
                'will_include' => ($entryYear == $year && $entryMonth == $month),
                'mood_value' => $entry->mood_value,
                'orgId' => $orgId,
                'is_user_entry' => ($entry->user_id == $userId),
            ]);
        }
        
        // Only include if the entry falls in the selected month/year in the entry creator's timezone
        if ($entryYear == $year && $entryMonth == $month) {
            if (!isset($dateData[$d])) {
                $dateData[$d] = [
                    'total_users' => 0, 
                    'total_score' => 0, 
                    'description' => null, 
                    'mood_value' => null,
                    'latest_entry_time' => null // Track latest entry for user-only data
                ];
            }

            $dateData[$d]['total_users'] += 1;

            // If user has no orgId, show individual user mood value directly
            if (empty($orgId)) {
                // For user-only data, use the latest entry's mood value (by created_at timestamp)
                $entryTimestamp = \Carbon\Carbon::parse($entry->created_at)->timestamp;
                $currentLatestTime = $dateData[$d]['latest_entry_time'];
                
                // If this is the first entry for this day, or this entry is newer, use it
                if ($currentLatestTime === null || $entryTimestamp > $currentLatestTime) {
                    $dateData[$d]['mood_value'] = $entry->mood_value;
                    if ($entry->mood_value == 3) {
                        $dateData[$d]['total_score'] = 100;
                    } elseif ($entry->mood_value == 2) {
                        $dateData[$d]['total_score'] = 51;
                    } else {
                        $dateData[$d]['total_score'] = 0;
                    }
                    $dateData[$d]['description'] = $entry->description;
                    $dateData[$d]['latest_entry_time'] = $entryTimestamp;
                }
            } else {
                // ✅ Updated logic: count only users with mood_value = 3 for organization
                if ($entry->mood_value == 3) {
                    $dateData[$d]['total_score'] += 1;
                }

                // Logged-in user's description and mood_value (use latest entry)
                // IMPORTANT: For calendar display, we need the logged-in user's individual mood_value
                if ($entry->user_id == $userId) {
                    $entryTimestamp = \Carbon\Carbon::parse($entry->created_at)->timestamp;
                    $currentLatestTime = $dateData[$d]['latest_entry_time'];
                    if ($currentLatestTime === null || $entryTimestamp > $currentLatestTime) {
                        $dateData[$d]['description'] = $entry->description;
                        $dateData[$d]['mood_value'] = $entry->mood_value; // Store logged-in user's mood_value
                        // Also store score for logged-in user
                        if ($entry->mood_value == 3) {
                            $dateData[$d]['user_score'] = 100;
                        } elseif ($entry->mood_value == 2) {
                            $dateData[$d]['user_score'] = 51;
                        } else {
                            $dateData[$d]['user_score'] = 0;
                        }
                        $dateData[$d]['latest_entry_time'] = $entryTimestamp;
                        
                        // Debug logging
                        \Illuminate\Support\Facades\Log::info('DashboardService: Stored user mood in org mode', [
                            'entry_id' => $entry->id,
                            'user_id' => $userId,
                            'day' => $d,
                            'mood_value' => $entry->mood_value,
                            'user_score' => $dateData[$d]['user_score'],
                        ]);
                    }
                }
            }
        }
    }

    // Fetch approved leaves for the logged-in user for the selected month
    $userLeaves = UserLeave::where('user_id', $userId)
        ->where('leave_status', 1) // Approved leaves only
        ->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate->toDateString(), $endDate->toDateString()])
              ->orWhereBetween('end_date', [$startDate->toDateString(), $endDate->toDateString()])
              ->orWhere(function ($subQ) use ($startDate, $endDate) {
                  $subQ->where('start_date', '<=', $startDate->toDateString())
                       ->where('end_date', '>=', $endDate->toDateString());
              });
        })
        ->get();

    $happyIndexArr = [];
    // Use user's timezone for "today" comparison
    $todayDay = (int) $userNow->format('d');
    $todayMonth = (int) $userNow->format('m');
    $todayYear  = (int) $userNow->format('Y');

    // Build array for all days in month, ensuring proper data structure
    // IMPORTANT: Array index $i-1 corresponds to calendar day $i (0-indexed array)
    // Data in $dateData[$d] is organized by day number in entry's stored timezone
    for ($i = 1; $i <= $noOfDaysInMonth; $i++) {
        // Check if this day is a leave day - use date string comparison to avoid timezone issues
        $dayDate = \Carbon\Carbon::create($year, $month, $i, 0, 0, 0, $userTimezone)->startOfDay();
        $dayDateStr = $dayDate->toDateString(); // Y-m-d format
        $isLeaveDay = false;
        foreach ($userLeaves as $leave) {
            $leaveStart = \Carbon\Carbon::parse($leave->start_date)->startOfDay()->toDateString();
            $leaveEnd = \Carbon\Carbon::parse($leave->end_date)->startOfDay()->toDateString();
            // Check if date falls within leave range (inclusive)
            if ($dayDateStr >= $leaveStart && $dayDateStr <= $leaveEnd) {
                $isLeaveDay = true;
                
                // Debug logging for Feb 6
                if ($dayDateStr === '2026-02-06') {
                    \Illuminate\Support\Facades\Log::info('DashboardService: Leave check for Feb 6', [
                        'day_date_str' => $dayDateStr,
                        'leave_id' => $leave->id,
                        'leave_start' => $leaveStart,
                        'leave_end' => $leaveEnd,
                        'is_leave_day' => true,
                        'leave_status' => $leave->leave_status,
                    ]);
                }
                
                break;
            }
        }
        // Get data for day $i (data is stored by day number in entry's stored timezone)
        $dayData = $dateData[$i] ?? ['total_users' => 0, 'total_score' => 0, 'description' => null, 'mood_value' => null];

        $score = null;
        $mood_value = null;

        if (empty($orgId)) {
            // For user-only data, use the mood_value and score directly from the entry
            if (isset($dayData['mood_value']) && $dayData['mood_value'] !== null) {
                $mood_value = $dayData['mood_value'];
                // Ensure score is set based on mood_value if not already set
                if (isset($dayData['total_score']) && $dayData['total_score'] !== null) {
                    $score = $dayData['total_score'];
                } else {
                    // Calculate score from mood_value if not set
                    if ($mood_value == 3) {
                        $score = 100;
                    } elseif ($mood_value == 2) {
                        $score = 51;
                    } else {
                        $score = 0;
                    }
                }
            }
        } else {
            // For organization data, show logged-in user's individual mood if available
            // Otherwise calculate organization average
            if (isset($dayData['mood_value']) && $dayData['mood_value'] !== null) {
                // Use logged-in user's individual mood_value and score
                $mood_value = $dayData['mood_value'];
                if (isset($dayData['user_score']) && $dayData['user_score'] !== null) {
                    $score = $dayData['user_score'];
                } else {
                    // Calculate score from mood_value if not set
                    if ($mood_value == 3) {
                        $score = 100;
                    } elseif ($mood_value == 2) {
                        $score = 51;
                    } else {
                        $score = 0;
                    }
                }
            } elseif ($dayData['total_users'] > 0) {
                // Fallback to organization average if no individual entry
                $score = round(($dayData['total_score'] / $dayData['total_users']) * 100);
                $mood_value = $score >= 81 ? 3 : ($score >= 51 ? 2 : 1);
            }
            
            // Debug logging for organization mode
            if ($i >= 2 && $i <= 6) {
                \Illuminate\Support\Facades\Log::info('DashboardService: Org mode calendar building', [
                    'calendar_day' => $i,
                    'dayData_mood_value' => $dayData['mood_value'] ?? null,
                    'dayData_user_score' => $dayData['user_score'] ?? null,
                    'dayData_total_users' => $dayData['total_users'] ?? 0,
                    'dayData_total_score' => $dayData['total_score'] ?? 0,
                    'final_mood_value' => $mood_value,
                    'final_score' => $score,
                ]);
            }
        }

        // If it's a leave day, override with leave data
        if ($isLeaveDay) {
            $score = null;
            $mood_value = null;
            $dayData['description'] = "You were on leave on " . $dayDate->format('M d, Y');
        }

        // Hide today's data if current month & year (will be shown via todayMoodData)
        if ($i === $todayDay && $month == $todayMonth && $year == $todayYear) {
            $score = null;
            $mood_value = null;
            // Only clear description if not on leave
            if (!$isLeaveDay) {
                $dayData['description'] = null;
            }
        }

        // Debug logging for specific days
        if ($i >= 2 && $i <= 6) {
            \Illuminate\Support\Facades\Log::info('DashboardService: Building calendar array', [
                'calendar_day' => $i,
                'array_index' => $i - 1,
                'dayData_exists' => isset($dateData[$i]),
                'mood_value' => $mood_value,
                'score' => $score,
                'description' => $dayData['description'] ?? null,
                'is_leave_day' => $isLeaveDay,
            ]);
        }

        $happyIndexArr[] = [
            'date'        => $i,
            'score'       => $score,
            'mood_value'  => $mood_value,
            'description' => $dayData['description'],
            'is_leave'    => $isLeaveDay,
        ];
    }

    // Year & month lists
    if (empty($orgId)) {
        // For user-only data, use user's created date
        $createdTime  = strtotime($user->created_at);
    } else {
        $createdTime  = $user->hasRole('basecamp') 
            ? strtotime($user->created_at) 
            : strtotime(Organisation::find($orgId)?->created_at ?? now());
    }
    $createdYear  = (int) date('Y', $createdTime);
    $createdMonth = (int) date('n', $createdTime);
    $currentYear  = (int) date('Y');
    $currentMonth = (int) date('n');

    $orgYearList = range($createdYear, $currentYear);
    $orgMonthList = [];
    foreach ($orgYearList as $y) {
        $startMonth = ($y == $createdYear) ? $createdMonth : 1;
        $endMonth   = ($y == $currentYear) ? $currentMonth : 12;
        $orgMonthList[$y] = range($startMonth, $endMonth);
    }

    return [
        'happyIndexMonthly' => $happyIndexArr,
        'firstDayOfMonth'   => date('l', strtotime("$year-$month-01")),
        'orgYearList'       => $orgYearList,
        'orgMonth'          => $orgMonthList,
    ];
}









    /**
     * Check if a user has provided feedback on the Happy Index for today.
     *
     * @param int $userId
     * @param int $HI_include_saturday
     * @param int $HI_include_sunday
     * @param int|null $orgId Optional organisation ID
     * @return bool True if feedback exists or day is not working, false otherwise
     */
	public function userGivenfeedbackOnHIValueORM($userId, $HI_include_saturday, $HI_include_sunday, $orgId = null)
	{
    	$isUser = HappyIndex::where('user_id', $userId)
        ->where('status', 'active')
        ->whereDate('created_at', now()->toDateString())
        ->exists();

    	$dayOfWeek  = now()->format('D');
    	$HIFeedback = false;

    	$user = Auth::user();

    	if ($user && $user->hasRole('basecamp')) {
        		$HIFeedback = $isUser;
    	} else {
        	$organisation = Organisation::where('id', $orgId)->first();
      		
        	if ($organisation && $organisation->working_days) {
            	$workingDays = json_decode($organisation->working_days, true);

            	if (in_array($dayOfWeek, $workingDays)) {
                	$HIFeedback = $isUser;
            	} else {
                	$HIFeedback = true;
            	}
        	} else {
            	if (in_array($dayOfWeek, ["Mon", "Tue", "Wed", "Thu", "Fri"])) {
               	 $HIFeedback = $isUser;
            	} elseif ($dayOfWeek == "Sat") {
                	$HIFeedback = $HI_include_saturday == 1 ? $isUser : true;
            	} elseif ($dayOfWeek == "Sun") {
                	$HIFeedback = $HI_include_sunday == 1 ? $isUser : true;
            	}
        	}
    	}

    	return $HIFeedback;
	}

    /**
     * Get a list of departments for the given organisation and optional office.
     *
     * @param array $filters Optional filters: 'orgId', 'officeId'
     * @return array Returns array of departments with 'id' and 'department' name
     */
	public function getDepartmentList(array $filters = [])
	{
    	$user  = auth()->user();
   	 	$orgId = $filters['orgId'] ?? $user->orgId;
    	
    	// If user has no orgId, return empty array
    	if (empty($orgId)) {
    	    return [];
    	}
    	
    	$query = Department::with('allDepartment')
        	->where('organisation_id', $orgId);

    	if (!empty($filters['officeId'])) {
       	 	$query->where('office_id', $filters['officeId']);
   	 	}

    	$departments = $query->get(['id', 'all_department_id', 'status'])
        	->unique('all_department_id')
        	->values();

    	$resultArray = [];
    	foreach ($departments as $dep) {
        	$resultArray[] = [
            	'id'         => (string) $dep->id,
            	'department' => optional($dep->allDepartment)->name,
        	];
    	}

    	return $resultArray;
	}

    /**
     * Get all offices along with their associated departments.
     *
     * @param array $filters Optional filters: 'orgId'
     * @return array Returns array with 'offices' and 'departments' keys
     */
	public function getAllOfficenDepartments(array $filters = [])
	{
   	 $orgId = $filters['orgId'] ?? auth()->user()->orgId;
        $resultArray = [];

        // If user has no orgId, return empty arrays
        if (empty($orgId)) {
            return [
                'offices' => [],
                'departments' => []
            ];
        }

        $offices = Office::where('organisation_id', $orgId)
            ->with(['departments' => function ($query) use ($orgId) {
                $query->where('organisation_id', $orgId)
                      ->with('allDepartment');
            }])
            ->get();

        $officeArray = [];

        foreach ($offices as $office) {
            $departmentsArray = collect();

            foreach ($office->departments as $department) {
                $deptData = [
                    'id'         => (string) $department->id,
                    'department' => optional($department->allDepartment)->name,
                ];
                $departmentsArray->push($deptData);
            }

            $departmentsArray = $departmentsArray->unique('id')->values();

            $officeArray[] = [
                'officeId'    => (string) $office->id,
                'office'      => $office->name,
                'departments' => $departmentsArray,
            ];
        }

        $allDepartments = Department::where('organisation_id', $orgId)
            ->with('allDepartment')
            ->get()
            ->map(function ($dep) {
                return [
                    'id'         => (string) $dep->id,
                    'department' => optional($dep->allDepartment)->name,
                ];
            })
            ->unique('id')
            ->values();

        $resultArray['offices']     = $officeArray;
        $resultArray['departments'] = $allDepartments;

        return $resultArray;
    }
  
    /**
     * Get monthly summary of Happy Index for the authenticated user.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse Returns JSON response with user's monthly Happy Index data
     */
	public function summary(Request $request)
	{
    	$userId = Auth::id(); 
    	$month = $request->input('month') ?? now()->month;
    	$year  = $request->input('year') ?? now()->year;

    	$happyIndexMonthly = HappyIndex::where('user_id', $userId)
        	->whereMonth('created_at', $month)
        	->whereYear('created_at', $year)
        	->orderBy('created_at', 'desc')
        	->get()
        	->map(function ($item) {
            	$image = match($item->mood_value) {
                	3 => 'happy-user.svg',
                	2 => 'sad-user.svg',
                	1 => 'avarege-user.svg',
                	default => 'sad-index.svg',
            	};

            	return [
                	'date'        => $item->created_at->format('M d, Y'),
                	'score'       => $item->score,
               	 	'mood_value'  => $item->mood_value,
                	'description' => $item->description ?? 'No message added.',
                	'image'       => $image,
                	'status'      => $item->status ?? 'Present',
            	];
        	})
        	->toArray();

    	return response()->json([
        	'status' => true,
        	'data'   => $happyIndexMonthly,
        	'message'=> "Happy index for $month/$year fetched successfully"
    	]);
	}
}
