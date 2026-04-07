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
		$resultArray = [];
		$user        = Auth::user();

		if (!$user) {
			return [
				'status'  => false,
				'message' => 'Unauthorized',
			];
		}

		$userId       = $user->id;
		$orgId        = $filters['orgId'] ?? $user->orgId;
		$officeId     = !empty($filters['officeId']) ? $filters['officeId'] : $user->officeId;
		$departmentId = !empty($filters['departmentId']) ? $filters['departmentId'] : null;
		$year         = !empty($filters['year']) ? $filters['year'] : now()->year;
		$month        = !empty($filters['month']) ? sprintf("%02d", $filters['month']) : sprintf("%02d", now()->month);

		$HI_include_saturday = $user->HI_include_saturday ?? 2;
		$HI_include_sunday   = $user->HI_include_sunday ?? 2;
		$yearAndMonth        = $year . "-" . $month;

		\Log::info("Filters applied in getFreeVersionHomeDetails", compact('orgId','officeId','departmentId','year','month'));

		$resultArray['userGivenfeedback'] = $this->userGivenfeedbackOnHIValueORM(
			$userId,
			$HI_include_saturday,
			$HI_include_sunday
		);

		// Determine days in month
		if ($yearAndMonth == date('Y-m')) {
			$noOfDaysInMonth = date('j') - 1; // exclude today
		} else {
			$noOfDaysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
		}

		$happyIndexArr = [];
		$todayStr = date('Y-m-d');
		$todayDay = (int) date('d');

		// ---------- Basecamp User ----------
		if ($user->hasRole('basecamp')) {
			$userHappyData = HappyIndex::where('user_id', $userId)
				->whereYear('created_at', $year)
				->whereMonth('created_at', $month)
				->whereDate('created_at', '<', $todayStr)
				->get(['created_at', 'mood_value', 'description', 'user_id']);

			$userScores = [];
			foreach ($userHappyData as $entry) {
				$day = date('d', strtotime($entry->created_at));
				$score = 0;
				if ($entry->mood_value == 3) $score = 100;
				elseif ($entry->mood_value == 2) $score = 51;
				elseif ($entry->mood_value == 1) $score = 0;

				$userScores[$day] = [
					'score'       => $score,
					'mood_value'  => $entry->mood_value,
					'description' => $entry->description ?? null,
					'user_id'     => $entry->user_id,
				];
			}

			for ($i = 1; $i <= $noOfDaysInMonth; $i++) {
				$d = sprintf("%02d", $i);
				$happyIndexArr[] = [
					'date'        => $d,
					'score'       => $userScores[$d]['score'] ?? null,
					'mood_value'  => $userScores[$d]['mood_value'] ?? null,
					'description' => $userScores[$d]['description'] ?? null,
					'user_id'     => $userScores[$d]['user_id'] ?? null,
				];
			}

		} else {
			// ---------- Org User ----------
			$officeIds     = is_array($officeId) ? $officeId : ($officeId ? [$officeId] : []);
			$departmentIds = is_array($departmentId) ? $departmentId : ($departmentId ? [$departmentId] : []);

			$happyDescriptions = HappyIndex::whereYear('created_at', $year)
				->whereMonth('created_at', $month)
				->whereDate('created_at', '<', $todayStr)
				->when($officeIds, fn($q) => $q->whereHas('user', fn($q2) => $q2->whereIn('officeId', $officeIds)))
				->when($departmentIds, fn($q) => $q->whereHas('user', fn($q2) => $q2->whereIn('departmentId', $departmentIds)))
				->get(['created_at', 'description', 'mood_value', 'user_id']);

			// Group by day
			$dateData = [];
			foreach ($happyDescriptions as $entry) {
				$day = date('d', strtotime($entry->created_at));
				$dateData[$day]['total_users'] = ($dateData[$day]['total_users'] ?? 0) + 1;
				$dateData[$day]['total_score'] = ($dateData[$day]['total_score'] ?? 0) + ($entry->mood_value ?? 0);
				$dateData[$day]['descriptions'][$entry->user_id] = $entry->description;
			}

			// Prepare final happy index array
			for ($i = 1; $i <= $noOfDaysInMonth; $i++) {
				$d = sprintf("%02d", $i);

				$score = null;
				$mood_value = null;

				if (!empty($dateData[$d])) {
					$totalUsers = $dateData[$d]['total_users'];
					$totalScore = $dateData[$d]['total_score'];

					// Calculate percentage score
					$score = ($totalScore / $totalUsers) * 100;
					$score = round($score);

					// Determine emoji logic
					if ($score >= 81) $mood_value = 3; // green
					elseif ($score >= 51) $mood_value = 2; // yellow
					else $mood_value = 1; // red
				}

				$description = $dateData[$d]['descriptions'][$userId] ?? null;

				$happyIndexArr[] = [
					'date'        => $d,
					'score'       => $score,
					'mood_value'  => $mood_value,
					'description' => $description,
					'user_id'     => $userId,
				];
			}
		}

		// Hide today's data for all users
		foreach ($happyIndexArr as $k => $item) {
			if ((int)$item['date'] === $todayDay) {
				$happyIndexArr[$k]['score']       = null;
				$happyIndexArr[$k]['description'] = null;
				$happyIndexArr[$k]['mood_value']  = null;
			}
		}

		$resultArray['happyIndexMonthly'] = $happyIndexArr;
		$resultArray['firstDayOfMonth']   = date('l', strtotime($yearAndMonth . "-01"));

		// ---------- Year & Month List ----------
		$orgYearList = [];
		$orgMonthList = [];

		if ($user->hasRole('basecamp')) {
			$created = strtotime($user->created_at);
		} else {
			$organisation = Organisation::where('id', $orgId)->first();
			$created = $organisation && $organisation->created_at
				? strtotime($organisation->created_at)
				: time();
		}

		$createdYear  = (int) date('Y', $created);
		$createdMonth = (int) date('n', $created);
		$currentYear  = (int) date('Y');
		$currentMonth = (int) date('n');

		for ($y = $createdYear; $y <= $currentYear; $y++) $orgYearList[] = $y;

		$orgMonthList = [];
		for ($y = $createdYear; $y <= $currentYear; $y++) {
			$startMonth = ($y == $createdYear) ? $createdMonth : 1;
			$endMonth   = ($y == $currentYear) ? $currentMonth : 12;

			for ($m = $startMonth; $m <= $endMonth; $m++) {
				$orgMonthList[$y][] = $m;
			}
		}

		$resultArray['orgYearList'] = $orgYearList;
		$resultArray['orgMonth']    = $orgMonthList;

		// ---------- Non-working Days ----------
		$notWorkingDays = [];
		if ($HI_include_saturday == 2) $notWorkingDays[] = 'saturday';
		if ($HI_include_sunday == 2) $notWorkingDays[] = 'sunday';

		$resultArray['notWorkingDays']    = $notWorkingDays;
		$resultArray['appPaymentVersion'] = $organisation->appPaymentVersion ?? '';
		$resultArray['leaveStatus']       = $user->onLeave ?? 0;

		return $resultArray;
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
