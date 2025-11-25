<?php

namespace App\Services;

use App\Models\HappyIndexDashboardGraph;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Department;
use App\Models\Office;
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

    // Days in selected month/year
    $noOfDaysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

    // Fetch happy indexes for filtered users
    $happyData = HappyIndex::whereYear('created_at', $year)
        ->whereMonth('created_at', $month)
        ->whereIn('user_id', $filteredUserIds)
        ->get(['created_at', 'mood_value', 'description', 'user_id']);

    $dateData = [];
    foreach ($happyData as $entry) {
        $d = (int) date('d', strtotime($entry->created_at));
        if (!isset($dateData[$d])) {
            $dateData[$d] = ['total_users' => 0, 'total_score' => 0, 'description' => null];
        }

        $dateData[$d]['total_users'] += 1;

        // ✅ Updated logic: count only users with mood_value = 3
        if ($entry->mood_value == 3) {
            $dateData[$d]['total_score'] += 1;
        }

        // Logged-in user's description only
        if ($entry->user_id == $userId) {
            $dateData[$d]['description'] = $entry->description;
        }
    }

    $happyIndexArr = [];
    $todayDay = (int) now()->format('d');
    $todayMonth = (int) now()->format('m');
    $todayYear  = (int) now()->format('Y');

    // ✅ Your original for-loop logic remains exactly the same
    for ($i = 1; $i <= $noOfDaysInMonth; $i++) {
        $dayData = $dateData[$i] ?? ['total_users' => 0, 'total_score' => 0, 'description' => null];

        $score = null;
        $mood_value = null;

        if ($dayData['total_users'] > 0) {
            $score = round(($dayData['total_score'] / $dayData['total_users']) * 100);
            $mood_value = $score >= 81 ? 3 : ($score >= 51 ? 2 : 1);
        }

        // Hide today's data if current month & year
        if ($i === $todayDay && $month == $todayMonth && $year == $todayYear) {
            $score = $mood_value = $dayData['description'] = null;
        }

        $happyIndexArr[] = [
            'date'        => $i,
            'score'       => $score,
            'mood_value'  => $mood_value,
            'description' => $dayData['description'],
        ];
    }

    // Year & month lists
    $createdTime  = $user->hasRole('basecamp') 
        ? strtotime($user->created_at) 
        : strtotime(Organisation::find($orgId)?->created_at ?? now());
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
