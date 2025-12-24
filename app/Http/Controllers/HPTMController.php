<?php
namespace App\Http\Controllers;

use App\Models\CotRoleMapOption;
use Illuminate\Support\Facades\DB;
use App\Models\Department;
use App\Models\HappyIndex;
use App\Models\HappyIndexDashboardGraph;
use App\Models\HptmLearningChecklist;
use App\Models\HptmLearningChecklistForUserReadStatus;
use App\Models\HptmLearningType;
use App\Models\Office;
use App\Models\Organisation;
use App\Models\SotMotivationValueRecord;
use App\Models\IotNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Concerns\UpdatesUserTimezone;

/**
 * @OA\Tag(
 *     name="User Profile",
 *     description="User profile management endpoints"
 * )
 */
class HPTMController extends Controller
{
    use UpdatesUserTimezone;
   /**
    * Get the authenticated user's profile with role, office,
    * department, organisation, COT/SOT data, etc.
    *
    * @OA\Get(
    *     path="/api/user-profile",
    *     tags={"User Profile"},
    *     summary="Get user profile",
    *     description="Retrieve the authenticated user's complete profile information including personal details, organization, role, COT/SOT data, and timezone",
    *     security={{"bearerAuth":{}}},
    *     @OA\Response(
    *         response=200,
    *         description="User profile retrieved successfully",
    *         @OA\JsonContent(
    *             @OA\Property(property="status", type="boolean", example=true),
    *             @OA\Property(property="message", type="string", example="User Profile"),
    *             @OA\Property(
    *                 property="data",
    *                 type="object",
    *                 @OA\Property(property="id", type="integer", example=1),
    *                 @OA\Property(property="first_name", type="string", example="John"),
    *                 @OA\Property(property="last_name", type="string", example="Doe"),
    *                 @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
    *                 @OA\Property(property="officeId", type="integer", nullable=true, example=1),
    *                 @OA\Property(property="departmentId", type="integer", nullable=true, example=1),
    *                 @OA\Property(property="orgId", type="integer", nullable=true, example=1),
    *                 @OA\Property(property="officeName", type="string", nullable=true, example="Main Office"),
    *                 @OA\Property(property="departmentName", type="string", nullable=true, example="Engineering"),
    *                 @OA\Property(property="profileImage", type="string", nullable=true, example="http://example.com/storage/profile-photos/image.jpg"),
    *                 @OA\Property(property="organisation_logo", type="string", nullable=true),
    *                 @OA\Property(property="personaliseData", type="string", nullable=true),
    *                 @OA\Property(property="status", type="string", example="Active"),
    *                 @OA\Property(property="userContact", type="string", nullable=true, example="+1234567890"),
    *                 @OA\Property(property="country_code", type="string", nullable=true, example="+1"),
    *                 @OA\Property(property="timezone", type="string", nullable=true, example="America/New_York", description="User's timezone from profile"),
    *                 @OA\Property(property="organisationName", type="string", nullable=true, example="Acme Corp"),
    *                 @OA\Property(property="role", type="string", example="organisation_user"),
    *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00.000000Z"),
    *                 @OA\Property(property="cotTeamRoleMap", type="string", example="Leader, Collaborator, Innovator"),
    *                 @OA\Property(property="cotTeamRoleMapArr", type="array", @OA\Items(type="string")),
    *                 @OA\Property(property="sotMotivationDetail", type="string", example="Growth, Recognition, Autonomy"),
    *                 @OA\Property(property="sotMotivationDetailArr", type="array", @OA\Items(type="string")),
    *                 @OA\Property(property="sotDetail", type="string", example=""),
    *                 @OA\Property(property="perTypeStatus", type="boolean", example=false),
    *                 @OA\Property(property="personalityTypeDetails", type="string", example="You have not submitted your answers yet."),
    *                 @OA\Property(property="personalityTypeDetailsArr", type="array", @OA\Items(type="string"))
    *             )
    *         )
    *     ),
    *     @OA\Response(
    *         response=401,
    *         description="Unauthorized",
    *         @OA\JsonContent(
    *             @OA\Property(property="status", type="boolean", example=false),
    *             @OA\Property(property="message", type="string", example="User not authenticated")
    *         )
    *     ),
    *     @OA\Response(
    *         response=500,
    *         description="Server error",
    *         @OA\JsonContent(
    *             @OA\Property(property="status", type="boolean", example=false),
    *             @OA\Property(property="message", type="string", example="Failed to fetch user profile"),
    *             @OA\Property(property="error", type="string", example="Error message")
    *         )
    *     )
    * )
    *
    * @param Request $request
    * @return \Illuminate\Http\JsonResponse
    */
    public function userProfile(Request $request)
    {
        try {
            // COMMENTED OUT: Automatic timezone update from request
            // Timezone should be set from user profile instead
            // Update user timezone from request if provided
            // $this->updateUserTimezoneIfNeeded($request);
            
            $user = auth()->user();

            if (! $user) {
                return response()->json(['status' => false, 'message' => 'User not authenticated'], 401);
            }

            $user->load(['office', 'department', 'organisation']);

            $roleName = $user->getRoleNames()->first() ?? 'No role assigned';

            $resultArray = [
                'id'                => $user->id,
                'first_name'        => $user->first_name,
                'last_name'         => $user->last_name,
                'email'             => $user->email,
                'officeId'          => optional($user->office)->id,
                'departmentId'      => optional($user->department)->id,
                'orgId'             => optional($user->organisation)->id,
                'officeName'        => optional($user->office)->name,
                'departmentName'    => optional($user->department->allDepartment ?? null)->name ?? '',
   		        'profileImage' => $user->profile_photo_path ? url('storage/' . $user->profile_photo_path) : null,
                'organisation_logo' => optional($user->organisation)->image,
                'personaliseData'   => optional($user->organisation)->personaliseData,
                'status'            => $user->status,
                'userContact'       => $user->phone,
              	'country_code'      => $user->country_code,
                'timezone'          => $user->timezone,
                "organisationName"  => optional($user->organisation)->name,
                "role"              => $roleName,
              	"created_at"        => $user->created_at,
            ];

            $resultArray['cotTeamRoleMap']    = '';
            $resultArray['cotTeamRoleMapArr'] = [];

            $Value  = [];
            $mapers = CotRoleMapOption::where('status', 'Active')->orderBy('id')->get();

            foreach ($mapers as $maper) {
                $maperCount = $user->cotAnswers()
                    ->where('cot_role_map_option_id', $maper->id)
                    ->where('status', 'Active')
                    ->sum('answer');
                $Value[$maper->maper_key] = $maperCount;
            }

            arsort($Value);

            $teamArr = [];
            foreach (array_keys($Value) as $i => $key) {
                if ($i == 3) {
                    break;
                }

                $mapersObj = CotRoleMapOption::where('maper_key', $key)->where('status', 'Active')->first();
                if ($mapersObj) {
                    $teamArr[] = $mapersObj->maper;
                }
            }

            if ($user->cotAnswers()->where('status', 'Active')->exists()) {
                $resultArray['cotTeamRoleMap']    = implode(', ', $teamArr);
                $resultArray['cotTeamRoleMapArr'] = $teamArr;
            } else {
                $resultArray['cotTeamRoleMap']    = "You have not submitted your answers yet.";
                $resultArray['cotTeamRoleMapArr'] = [];
            }

            $categories = SotMotivationValueRecord::where('status', 'Active')->get();
            $resultArr  = [];

            foreach ($categories as $category) {
                $sum = $user->sotMotivationAnswers()
                    ->whereHas('option', function ($query) use ($category) {
                        $query->where('category_id', $category->id);
                    })
                    ->where('status', 'Active')
                    ->sum('answer');
                $resultArr[$category->title] = (string) $sum;
            }

            arsort($resultArr);

            $finalMotArr = array_slice(array_keys($resultArr), 0, 3);

            if ($user->sotMotivationAnswers()->where('status', 'Active')->exists()) {
                $resultArray['sotMotivationDetail']    = implode(', ', $finalMotArr);
                $resultArray['sotMotivationDetailArr'] = $finalMotArr;
            } else {
                $resultArray['sotMotivationDetail']    = "You have not submitted your answers yet.";
                $resultArray['sotMotivationDetailArr'] = [];
            }

            $resultArray['sotDetail']                 = '';
            $resultArray['perTypeStatus']             = false;
            $resultArray['personalityTypeDetails']    = "You have not submitted your answers yet.";
            $resultArray['personalityTypeDetailsArr'] = [];

            return response()->json([
                'status'  => true,
                'message' => 'User Profile',
                'data'    => $resultArray,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to fetch user profile', 'error' => $e->getMessage()], 500);
        }
    }

   /**
    * Get department list for authenticated user's organisation.
    *
    * @return \Illuminate\Http\JsonResponse
    */
    public function getDepartmentList()
    {
        $user = auth()->user();

        $orgId = $user->orgId;

        $departments = Department::with('allDepartment')
            ->where('organisation_id', $orgId)
            ->get(['id', 'all_department_id', 'status'])
            ->unique('all_department_id')
            ->values();

        $resultArray = [];

        foreach ($departments as $dep) {
            $resultArray[] = [
                'id'           => (string) $dep->id,
                'department' => optional($dep->allDepartment)->name,
            ];
        }

        return response()->json([
            'status'  => count($resultArray) > 0,
            'message' => count($resultArray) > 0 ? 'Department list' : 'Department not found',
            'data'    => $resultArray,
        ]);
    }
  
    /**
     * Get all offices with their departments and distinct org-wide departments.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
	public function getAllOfficenDepartments(Request $request)
	{
    	$orgId = $request->input('orgId');

    	if (!$orgId) {
        	return response()->json([
            	'status'  => false,
            	'message' => 'Missing organisation ID',
            	'data'    => [],
        	]);
    	}

    	$resultArray = [];
	 $offices = Office::where('organisation_id', $orgId)
        ->with('departments')
        ->get();

    	$officeArray = [];

    	foreach ($offices as $office) {
        	$departmentsArray = collect();

	        foreach ($office->departments as $department) {
            	$deptData = [
                	'id'         => (string) $department->id,
                	'department' => $department->name ?? optional($department->allDepartment)->name,
            	];
            	$departmentsArray->push($deptData);
        	}

     	   $departmentsArray = $departmentsArray->unique('department')->values();

        	$officeArray[] = [
            	'officeId'    => (string) $office->id,
            	'office'      => $office->name,
            	'departments' => $departmentsArray,
        	];
    	}

    	$allDepartments = Department::where('organisation_id', $orgId)
        	->get()
        	->map(function ($dep) {
            	return [
                	'id'         => (string) $dep->id,
                	'department' => $dep->name ?? optional($dep->allDepartment)->name,
            	];
        	})
        	->unique('department')
        	->values();

  	  $resultArray['offices']     = $officeArray;
    	$resultArray['departments'] = $allDepartments;

    	return response()->json([
        	'status'  => true,
        	'message' => 'All office and departments.',
        	'data'    => $resultArray,
    	]);
	}


    /**
     * Get free version home dashboard details
     * including HI values, graphs, feedback, year list, etc.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
 	public function getFreeVersionHomeDetails(Request $request)
    {
        // COMMENTED OUT: Automatic timezone update from request
        // Timezone should be set from user profile instead
        // Update user timezone from request if provided
        // $this->updateUserTimezoneIfNeeded($request);
        
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'code'    => 401,
                'status'  => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $userId              = $user->id;
        $orgId               = $request->input('orgId') ?? $user->orgId;
        $officeId            = $request->input('officeId');
        $departmentId        = $request->input('departmentId');
        $deviceType          = $request->input('deviceType');
        $HI_include_saturday = $user->HI_include_saturday ?? 2;
        $HI_include_sunday   = $user->HI_include_sunday ?? 2;
        $year                = $request->input('year') ?? now()->year;
        $month               = sprintf("%02d", $request->input('month') ?? now()->month);
        $yearAndMonth        = $year . "-" . $month;

        $user->deviceType = $deviceType;
        $user->save();

        // ✅ Update OneSignal tags when user accesses dashboard (mobile API)
        try {
            $oneSignal = new \App\Services\OneSignalService();
            $oneSignal->setUserTagsOnLogin($user);
            \Log::info('OneSignal tags updated on mobile dashboard access', [
                'user_id' => $user->id,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('OneSignal tag update failed on mobile dashboard access', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        $resultArray['userGivenfeedback'] = $this->userGivenfeedbackOnHIValueORM($userId, $HI_include_saturday, $HI_include_sunday);

        $todayStr = date('Y-m-d');

        $noOfDaysInMonth = ($yearAndMonth == date('Y-m')) ? date('j') - 1 : cal_days_in_month(CAL_GREGORIAN, $month, $year);

        $happyIndexArr = [];

        $officeIds     = $officeId ? [$officeId] : [];
        $departmentIds = $departmentId ? [$departmentId] : [];

        if ($user->hasRole('basecamp')) {
            // Basecamp user: get only their data
            $userHappyData = HappyIndex::where('user_id', $userId)
                ->where('created_at', 'like', $yearAndMonth . '%')
                ->get(['created_at', 'mood_value', 'description']);

            $dateData = [];
            foreach ($userHappyData as $entry) {
                $day = date('d', strtotime($entry->created_at));
                $score = 0;
                if ($entry->mood_value == 3) $score = 100;
                elseif ($entry->mood_value == 2) $score = 51;
                elseif ($entry->mood_value == 1) $score = 0;

                $dateData[$day] = [
                    'score'       => $score,
                    'description' => $entry->description ?? '',
                ];
            }

            for ($i = 1; $i <= $noOfDaysInMonth; $i++) {
                $d = sprintf("%02d", $i);
                $happyIndexArr[] = [
                    'date'        => $d,
                    'score'       => $dateData[$d]['score'] ?? 0,
                    'description' => $dateData[$d]['description'] ?? '',
                ];
            }
        } else {
            // Organisation users: calculate total happy users per day
            $usersQuery = User::where('status', 1)->where('orgId', $orgId);
            if ($officeIds) $usersQuery->whereIn('officeId', $officeIds);
            if ($departmentIds) $usersQuery->whereIn('departmentId', $departmentIds);
            $filteredUserIds = $usersQuery->pluck('id')->toArray();

            // Include current user if not in filtered list
            if (!in_array($userId, $filteredUserIds)) {
                $filteredUserIds[] = $userId;
            }

            $happyData = HappyIndex::whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->whereDate('created_at', '<', $todayStr)
                ->whereIn('user_id', $filteredUserIds)
                ->get(['created_at', 'mood_value', 'description', 'user_id']);

            $dateData = [];
            foreach ($happyData as $entry) {
                $day = date('d', strtotime($entry->created_at));

                if (!isset($dateData[$day])) {
                    $dateData[$day] = [
                        'total_users' => 0,
                        'total_score' => 0, // Count of happy users (mood_value=3)
                        'description' => null,
                    ];
                }

                $dateData[$day]['total_users'] += 1;

                if ($entry->mood_value == 3) {
                    $dateData[$day]['total_score'] += 1;
                }

                if ($entry->user_id == $userId) {
                    $dateData[$day]['description'] = $entry->description ?? '';
                }
            }

            for ($i = 1; $i <= $noOfDaysInMonth; $i++) {
                $d = sprintf("%02d", $i);
                $dayData = $dateData[$d] ?? ['total_users' => 0, 'total_score' => 0, 'description' => null];

                $score = null;
                $mood_value = null;

                if ($dayData['total_users'] > 0) {
                    // Keep original calculation logic
                    $score = round(($dayData['total_score'] / $dayData['total_users']) * 100);
                    $mood_value = $score >= 81 ? 3 : ($score >= 51 ? 2 : 1);
                }

                $happyIndexArr[] = [
                    'date'        => $d,
                    'score'       => $score,
                    'mood_value'  => $mood_value,
                    'description' => $dayData['description'],
                ];
            }
        }

        // Include today's data
        if ($yearAndMonth == date('Y-m')) {
            $today = date('d');
            $score = 0;
            $descriptionToday = '';

            if ($user->hasRole('basecamp')) {
                $userMood = HappyIndex::where('user_id', $userId)
                    ->whereDate('created_at', $todayStr)
                    ->value('mood_value');

                if ($userMood == 3) $score = 100;
                elseif ($userMood == 2) $score = 51;
                elseif ($userMood == 1) $score = 0;

                $descriptionToday = HappyIndex::where('user_id', $userId)
                    ->whereDate('created_at', $todayStr)
                    ->value('description');

            } else {
                $happyToday = HappyIndex::whereDate('created_at', $todayStr)
                    ->whereIn('user_id', $filteredUserIds)
                    ->get(['mood_value', 'user_id']);

                $totalUsers = $happyToday->count();
                $happyUsers = $happyToday->where('mood_value', 3)->count();

                $score = $totalUsers ? round(($happyUsers / $totalUsers) * 100) : 0;

                $descriptionToday = HappyIndex::where('user_id', $userId)
                    ->whereDate('created_at', $todayStr)
                    ->value('description');
            }

            $happyIndexArr[] = [
                'date'        => $today,
                'score'       => $score,
                'description' => $descriptionToday ?? '',
            ];
        }

        $resultArray['happyIndexMonthly'] = $happyIndexArr;
        $resultArray['firstDayOfMonth']   = date('l', strtotime($yearAndMonth . "-01"));

        // Year list
        $orgYearList = [];
        if ($user->hasRole('basecamp')) {
            $createdYear = (int) date('Y', strtotime($user->created_at));
        } else {
            $organisation = Organisation::find($orgId);
            $createdYear = $organisation && $organisation->created_at
                ? (int) date('Y', strtotime($organisation->created_at))
                : (int) date('Y');
        }
        for ($i = $createdYear; $i <= (int) date('Y'); $i++) {
            $orgYearList[] = $i;
        }
        $resultArray['orgYearList'] = $orgYearList;

        $notWorkingDays = [];
        if ($HI_include_saturday == 2) $notWorkingDays[] = 'saturday';
        if ($HI_include_sunday == 2) $notWorkingDays[] = 'sunday';

        $resultArray['notWorkingDays']    = $notWorkingDays;
        $resultArray['appPaymentVersion'] = $organisation->appPaymentVersion ?? '';
        $resultArray['leaveStatus']       = $user->onLeave ?? 0;
		$resultArray['notificationCount'] = IotNotification::where('to_bubble_user_id', $userId)
            ->where('status', 'Active')
            ->where('archive', false)
            ->where(function($q) {
                // Exclude sentiment reminder notifications
                $q->where(function($subQuery) {
                    $subQuery->where('notificationType', '!=', 'sentiment-reminder')
                             ->orWhereNull('notificationType');
                })
                ->where(function($subQuery) {
                    $subQuery->where('title', '!=', 'Reminder: Please Update Your Sentiment Index')
                             ->orWhereNull('title');
                });
            })
            ->count();
        return response()->json([
            'code'         => 200,
            'status'       => true,
            'service_name' => 'free-version-home-detail',
            'message'      => '',
            'data'         => $resultArray,
        ]);
    }

  	/**
     * Check whether the user has given HI feedback today
     * considering working days and weekends.
     *
     * @param int $userId
     * @param int $HI_include_saturday
     * @param int $HI_include_sunday
     * @param int|null $orgId
     * @return bool
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
     * Get principles with team feedback scores and learning checklist completion percentages.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPrinciplesList()
    {
        $principleArray = [];
        $user           = auth()->user();
        $userId         = $user->id;
		
        $teamFeedbackStatus = \App\Models\HptmTeamFeedbackStatus::where('fromUserId', $userId)
            ->where('created_at', 'LIKE', date('Y-m') . '%')
            ->first();

        $lastFeedbackDate = '';
        if (! empty($teamFeedbackStatus)) {
            $lastFeedbackDate = $teamFeedbackStatus->created_at;
        } else {
            $lastFeedData = \App\Models\HptmTeamFeedbackStatus::where('fromUserId', $userId)
                ->orderBy('created_at', 'DESC')
                ->first();

            if (! empty($lastFeedData)) {
                $lastFeedbackDate = $lastFeedData->created_at;
            }
        }

        $teamFeedbackData = 0;
        if (! empty($lastFeedbackDate)) {
            $teamFeedbackData = \App\Models\HptmTeamFeedbackStatus::where('fromUserId', $userId)
                ->where('completeStatus', 2)
                ->where('created_at', 'LIKE', date('Y-m', strtotime($lastFeedbackDate)) . '%')
                ->count();
        }

        $principles = \App\Models\HptmPrinciple::all();

        $resultArray = [];
        foreach ($principles as $priciVal) {
            $result                = [];
            $result['id']          = $priciVal->id;
            $result['title']       = $priciVal->title;
            $result['description'] = $priciVal->description;
            $principleId           = $priciVal->id;

            $result['teamFeedbackScorePercent'] = 0;
            if (! empty($lastFeedbackDate)) {
                $teamFeedback1 = \App\Models\HptmTeamFeedbackAnswer::where('toUserId', $userId)
                    ->whereHas('question', function ($q) use ($principleId) {
                        $q->where('principleId', $principleId);
                    })
                    ->where('date', 'LIKE', date('Y-m', strtotime($lastFeedbackDate)) . '%')
                    ->with('option')
                    ->get()
                    ->sum(function ($answer) {
                        return $answer->option->option_rating ?? 0;
                    });

                $completionScore = 0;
                if (! empty($teamFeedback1) && ! empty($teamFeedbackData)) {
                    $completionScore = str_replace(',', '', number_format($teamFeedback1 / $teamFeedbackData, 2));
                }
                $result['teamFeedbackScorePercent'] = $completionScore;
            }

            $totalLearningChecklist = \App\Models\HptmLearningChecklist::where(function ($query) use ($principleId) {
                $query->where('principleId', $principleId)
                    ->orWhereNull('principleId');
            })
                ->count();

            $readLearningChecklist = \App\Models\HptmLearningChecklist::where(function ($query) use ($principleId) {
                $query->where('principleId', $principleId)
                    ->orWhereNull('principleId');
            })
                ->whereHas('userReadStatus', function ($q) use ($userId) {
                    $q->where('userId', $userId)->where('readStatus', 1);
                })
                ->count();

            $completionPercent = 0;
            if (! empty($readLearningChecklist) && ! empty($totalLearningChecklist)) {
                $completionPercent = str_replace(',', '', number_format(($readLearningChecklist / $totalLearningChecklist) * 100, 2));
            }
            $result['completionPercent'] = $completionPercent;

            $resultArray[] = $result;
        }

        $principleArray['principleData'] = $resultArray;

        $user = \App\Models\User::where('id', $userId)->where('status', 1)->first();

        $userHptmScore = 0;
        if (! empty($user)) {
            $userHptmScore = ($user->hptmScore ?? 0) + ($user->hptmEvaluationScore ?? 0);
        }

        $principleArray['hptmScore'] = $userHptmScore;

        return response()->json([
            'code'         => 200,
            'status'       => true,
            'service_name' => 'get-principles-list',
            'message'      => '',
            'data'         => $principleArray,
        ]);
    }
  
   /**
    * Get learning checklist grouped by learning type and principleId.
    *
    * @param Request $request
    * @return \Illuminate\Http\JsonResponse
    */
  	public function getLearningCheckList(Request $request)
	{
    	$userId      = Auth::id(); 
    	$principleId = $request->input('principleId');
		$learningTypes = HptmLearningType::orderBy('priority', 'ASC')->get();
		$learningCheckListArray = [];
    	$learningTypeArr        = [];

    	foreach ($learningTypes as $learningType) {
        	$checklists = HptmLearningChecklist::where('output', $learningType->id)
            	->where(function ($query) use ($principleId) {
                	$query->where('principleId', $principleId)
                      ->orWhereNull('principleId');
           	 })
            ->orderBy('created_at', 'ASC')
            ->get();

        	$learningCheckListArr = [];
        	foreach ($checklists as $checkVal) {
            	$userReadChecklist = DB::table('hptm_learning_checklist_for_user_read_status')
                	->select('readStatus')
                	->where('userId', $userId)
                	->where('checklistId', $checkVal->id)
                	->first();

            	$learningCheckListArr[] = [
                	'checklistId'       => $checkVal->id,
                	'principleId'       => $checkVal->principleId,
                	'typeId'            => $checkVal->output,
                	'link'              => $checkVal->link ?? '',
                	'document'          => $checkVal->document
                    	                        ? url("storage/" . $checkVal->document)
                        	                    : '',
                	'checklistTitle'    => $checkVal->title ?? '',
                	'description'       => $checkVal->description ?? '',
                	'learningTypeTitle' => $learningType->title ?? '',
                	'userReadChecklist' => $userReadChecklist && $userReadChecklist->readStatus == 1,
            	];
        	}

        	$learningCheckListArray[$learningType->title] = $learningCheckListArr;
        	$learningTypeArr[] = $learningType->title;
    	}

    	$resultArray = [
        	'learningCheckList' => $learningCheckListArray,
        	'learningTypeArr'   => $learningTypeArr,
    	];

    	return response()->json([
        	'code'         => 200,
        	'status'       => true,
        	'service_name' => 'get-learning-checklist',
        	'message'      => '',
        	'data'         => $resultArray,
    	]);
	}

   	/**
     * Change the read status of a user's checklist,
     * update HPTM score, and return updated score.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeReadStatusOfUserChecklist(Request $request)
    {
        $checklistId = $request->input('checklistId');
        $readStatus  = $request->input('readStatus');
        $userId      = Auth::id();

        if (! $checklistId || $readStatus === null) {
            return response()->json([
                'code'    => 400,
                'status'  => false,
                'message' => 'Checklist ID and read status are required.',
            ]);
        }

        $checklist = HptmLearningChecklist::find($checklistId);
        if (! $checklist) {
            return response()->json([
                'code'    => 400,
                'status'  => false,
                'message' => 'Invalid checklist ID.',
            ]);
        }

        $learningScore = 0;
        if ($checklist && $checklist->output) {
            $scoreModel    = HptmLearningType::find($checklist->output);
            $learningScore = $scoreModel->score ?? 0;
        }

        $user = User::where('id', $userId)->where('status', 1)->first();

        if ($user) {
            $newHptmScore = ($readStatus == 1)
            ? ($user->hptmScore ?? 0) + $learningScore
            : ($user->hptmScore ?? 0) - $learningScore;

            $user->update([
                'hptmScore'  => max(0, $newHptmScore),
                'updated_at' => now(),
            ]);
        }

        $existingStatus = HptmLearningChecklistForUserReadStatus::where('checklistId', $checklistId)
            ->where('userId', $userId)
            ->first();

        if ($existingStatus) {
            $existingStatus->update([
                'readStatus' => $readStatus,
                'updated_at' => now(),
            ]);
        } else {
            HptmLearningChecklistForUserReadStatus::create([
                'checklistId' => $checklistId,
                'userId'      => $userId,
                'readStatus'  => $readStatus,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        $updatedUser = User::where('id', $userId)->where('status', 1)->first();

        $userScore = 0;
        if ($updatedUser) {
            $userScore += $updatedUser->hptmScore ?? 0;
            $userScore += $updatedUser->hptmEvaluationScore ?? 0;
        }

        return response()->json([
            'code'         => 200,
            'status'       => true,
            'service_name' => 'change-read-status-of-user-checklist',
            'message'      => '',
            'data'         => ['hptmScore' => $userScore],
        ]);
    }

   /**
    * Set/reset password for user using token and email.
    *
    * @param Request $request
    * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
    */
    public function setPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'token'    => 'required',
            'password' => 'required|min:6|confirmed',
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->save();
            }
        );

        if ($status == Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Password set successfully!']);
        }

        return response()->json(['message' => __($status)], 400);
    }
  
  	/**
	* Update the authenticated user's profile.
	*
	* @OA\Post(
	*     path="/api/update-user-profile",
	*     tags={"User Profile"},
	*     summary="Update user profile",
	*     description="Update the authenticated user's profile information including name, phone, country code, timezone, and profile image",
	*     security={{"bearerAuth":{}}},
	*     @OA\RequestBody(
	*         required=false,
	*         @OA\MediaType(
	*             mediaType="multipart/form-data",
	*             @OA\Schema(
	*                 @OA\Property(property="first_name", type="string", maxLength=255, example="John"),
	*                 @OA\Property(property="last_name", type="string", maxLength=255, example="Doe"),
	*                 @OA\Property(property="phone", type="string", maxLength=20, example="+1234567890"),
	*                 @OA\Property(property="country_code", type="string", example="+1"),
	*                 @OA\Property(property="timezone", type="string", maxLength=50, example="America/New_York", description="Valid timezone identifier (e.g., America/New_York, Europe/London, Asia/Kolkata)"),
	*                 @OA\Property(property="profileImage", type="string", format="binary", description="Profile image file (jpg, jpeg, png, max 2MB)")
	*             )
	*         ),
	*         @OA\MediaType(
	*             mediaType="application/json",
	*             @OA\Schema(
	*                 @OA\Property(property="first_name", type="string", maxLength=255, example="John"),
	*                 @OA\Property(property="last_name", type="string", maxLength=255, example="Doe"),
	*                 @OA\Property(property="phone", type="string", maxLength=20, example="+1234567890"),
	*                 @OA\Property(property="country_code", type="string", example="+1"),
	*                 @OA\Property(property="timezone", type="string", maxLength=50, example="America/New_York", description="Valid timezone identifier")
	*             )
	*         )
	*     ),
	*     @OA\Response(
	*         response=200,
	*         description="Profile updated successfully",
	*         @OA\JsonContent(
	*             @OA\Property(property="status", type="boolean", example=true),
	*             @OA\Property(property="message", type="string", example="Profile updated successfully"),
	*             @OA\Property(
	*                 property="data",
	*                 type="object",
	*                 @OA\Property(property="id", type="integer", example=1),
	*                 @OA\Property(property="first_name", type="string", example="John"),
	*                 @OA\Property(property="last_name", type="string", example="Doe"),
	*                 @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
	*                 @OA\Property(property="phone", type="string", nullable=true, example="+1234567890"),
	*                 @OA\Property(property="country_code", type="string", nullable=true, example="+1"),
	*                 @OA\Property(property="timezone", type="string", nullable=true, example="America/New_York", description="User's timezone from profile"),
	*                 @OA\Property(property="profileImage", type="string", nullable=true, example="http://example.com/storage/profile-photos/image.jpg")
	*             )
	*         )
	*     ),
	*     @OA\Response(
	*         response=401,
	*         description="Unauthorized",
	*         @OA\JsonContent(
	*             @OA\Property(property="status", type="boolean", example=false),
	*             @OA\Property(property="message", type="string", example="User not authenticated")
	*         )
	*     ),
	*     @OA\Response(
	*         response=422,
	*         description="Validation error",
	*         @OA\JsonContent(
	*             @OA\Property(property="message", type="string", example="The given data was invalid."),
	*             @OA\Property(property="errors", type="object")
	*         )
	*     ),
	*     @OA\Response(
	*         response=500,
	*         description="Server error",
	*         @OA\JsonContent(
	*             @OA\Property(property="status", type="boolean", example=false),
	*             @OA\Property(property="message", type="string", example="Failed to update profile"),
	*             @OA\Property(property="error", type="string", example="Error message")
	*         )
	*     )
	* )
	*
	* @param \Illuminate\Http\Request $request
	* @return \Illuminate\Http\JsonResponse
	*/
	public function updateUserProfile(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'status'  => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Validate input (added country_code)
            $validated = $request->validate([
                'first_name'   => 'sometimes|string|max:255',
                'last_name'    => 'sometimes|string|max:255',
                'phone'        => 'sometimes|string|max:20',
                'country_code' => 'sometimes|string|min:1',
                'timezone'     => ['sometimes', 'string', 'max:50', \Illuminate\Validation\Rule::in(timezone_identifiers_list())],
                'profileImage' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            ]);

            if (isset($validated['first_name'])) {
                $user->first_name = $validated['first_name'];
            }

            if (isset($validated['last_name'])) {
                $user->last_name = $validated['last_name'];
            }

            if (isset($validated['phone'])) {
                $user->phone = $validated['phone'];
            }

            if (isset($validated['country_code'])) {
                $user->country_code = $validated['country_code'];
            }

            if (isset($validated['timezone'])) {
                $user->timezone = $validated['timezone'];
            }

            // Handle profile image upload
            if ($request->hasFile('profileImage')) {
                $image = $request->file('profileImage');

                if ($user->profile_photo_path && Storage::disk('public')->exists($user->profile_photo_path)) {
                    Storage::disk('public')->delete($user->profile_photo_path);
                }

                $path = $image->store('profile-photos', 'public');
                $user->profile_photo_path = $path;
            }

            $user->save();

            return response()->json([
                'status'  => true,
                'message' => 'Profile updated successfully',
                'data'    => [
                    'id'            => $user->id,
                    'first_name'    => $user->first_name,
                    'last_name'     => $user->last_name,
                    'email'         => $user->email,
                    'phone'         => $user->phone,
                    'country_code'  => $user->country_code,
                    'timezone'      => $user->timezone,
                    'profileImage'  => $user->profile_photo_path 
                                        ? url('storage/' . $user->profile_photo_path) 
                                        : null,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to update profile',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get timezone from latitude and longitude (for mobile and web)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTimezoneFromLocation(Request $request)
    {
        try {
            $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
            ]);

            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');

            $timezoneService = app(\App\Services\TimezoneService::class);
            $timezone = $timezoneService->getTimezoneFromLocation($latitude, $longitude);

            // If external APIs failed to detect timezone, fall back gracefully
            if (!$timezone) {
                // 1) Try browser / client provided timezone header if any
                $headerTz = $request->header('X-Timezone') ?? $request->input('timezone');
                if (!empty($headerTz) && in_array($headerTz, timezone_identifiers_list())) {
                    $timezone = $headerTz;
                    \Log::warning("getTimezoneFromLocation: Falling back to timezone from request header/body: {$timezone}");
                } else {
                    // 2) Safe default – Asia/Kolkata (used for most of your users)
                    $timezone = 'Asia/Kolkata';
                    \Log::warning("getTimezoneFromLocation: Timezone API failed, falling back to default {$timezone} for ({$latitude}, {$longitude})");
                }
            }

            // COMMENTED OUT: Automatic timezone update
            // Timezone should be set from user profile instead
            // Update user's timezone if authenticated
            // $user = auth()->user();
            // if ($user && $user->timezone !== $timezone) {
            //     $user->timezone = $timezone;
            //     $user->save();
            // }

            return response()->json([
                'status' => true,
                'timezone' => $timezone,
                'message' => 'Timezone detected successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to get timezone from location',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of all available timezones.
     *
     * @OA\Get(
     *     path="/api/timezone-list",
     *     tags={"User Profile"},
     *     summary="Get timezone list",
     *     description="Retrieve a list of all available timezone identifiers that can be used for user profile timezone setting",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Timezone list retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Timezone list retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="string",
     *                     example="America/New_York"
     *                 ),
     *                 example={"America/New_York", "America/Chicago", "America/Denver", "America/Los_Angeles", "Europe/London", "Europe/Paris", "Asia/Kolkata", "Asia/Tokyo", "Australia/Sydney"}
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTimezoneList()
    {
        try {
            $timezones = timezone_identifiers_list();
            
            return response()->json([
                'status'  => true,
                'message' => 'Timezone list retrieved successfully',
                'data'    => $timezones,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to retrieve timezone list',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

}
