<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\DashboardService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\UserLeave;
use App\Models\Organisation;
use Carbon\Carbon;
use App\Models\HappyIndex;
use App\Models\HptmLearningChecklist;
use App\Models\HptmLearningType;
use App\Services\OneSignalService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class DashboardSummary extends Component
{
    public $month;
    public $year;
    public $selectedOffice = '';
    public $selectedDepartment = '';

    public $happyIndexMonthly = [];
    public $orgYearList = [];
    public $departments = [];
    public $offices = [];
    public $moodStatus = null;
    public $moodNote;
    public $leaveStartDate;
    public $leaveEndDate;
    public $userGivenFeedback;
    public $onLeaveToday;
    public $showHappyIndex;
    public $showSubscriptionExpiredModal = false;
    public $subscriptionStatus = [];
    public $todayMoodData = null; // Store today's actual mood data

    protected $service;

    protected $listeners = [
        'refreshData' => 'loadData',
        'summary-saved' => 'refreshAfterSubmit'
    ];

    public function boot(DashboardService $service)
    {
        $this->service = $service;
    }

    public function mount()
    {
        $user = auth()->user();
        $tz = \App\Helpers\TimezoneHelper::getUserTimezone($user);
        
        $today = \App\Helpers\TimezoneHelper::carbon(null, $tz)->startOfDay();

        $this->month = $today->month;
        $this->year = $today->year;
        $this->selectedOffice = '';
        $this->selectedDepartment = '';
		$this->leaveStartDate = today()->toDateString();
        $user = auth()->user();

        // Check if user is on leave today
        $this->onLeaveToday = UserLeave::where('user_id', $user->id)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->where('leave_status', 1)
            ->exists();

        // Get user's timezone safely using helper
        $userTimezone = \App\Helpers\TimezoneHelper::getUserTimezone($user);
        
        // Get current date in user's timezone
        $userNow = \App\Helpers\TimezoneHelper::carbon(null, $userTimezone);
        $userTodayDate = $userNow->toDateString(); // Y-m-d format
        
        // Check if user gave feedback today in their CURRENT timezone
        // IMPORTANT: Convert entry's created_at (UTC) to CURRENT user timezone and check if it matches today
        // Example: If user filled sentiment on 5 Feb in India, and today is 6 Feb in India, popup should NOT show
        // But if user changes timezone to US where it's still 5 Feb, popup should show for 5 Feb in US
        $this->userGivenFeedback = HappyIndex::where('user_id', $user->id)
            ->get()
            ->filter(function ($entry) use ($userTimezone, $userTodayDate) {
                // Convert entry's created_at (UTC) to CURRENT user timezone (not entry's stored timezone)
                // This ensures: if user filled on 5 Feb in India, and today is 6 Feb in India, no popup
                $entryDateInCurrentTimezone = \App\Helpers\TimezoneHelper::setTimezone(Carbon::parse($entry->created_at), $userTimezone)->toDateString();
                // Compare with today's date in CURRENT user timezone
                return $entryDateInCurrentTimezone === $userTodayDate;
            })
            ->isNotEmpty();
        
        // Fetch today's actual mood data if exists (convert to CURRENT user timezone)
        $todayHappyIndex = HappyIndex::where('user_id', $user->id)
            ->get()
            ->filter(function ($entry) use ($userTimezone, $userTodayDate) {
                // Convert entry's created_at (UTC) to CURRENT user timezone
                $entryDateInCurrentTimezone = \App\Helpers\TimezoneHelper::setTimezone(Carbon::parse($entry->created_at), $userTimezone)->toDateString();
                // Compare with today's date in CURRENT user timezone
                return $entryDateInCurrentTimezone === $userTodayDate;
            })
            ->sortByDesc('created_at') // Get latest entry if multiple exist
            ->first();
        
        if ($todayHappyIndex) {
            $moodValue = $todayHappyIndex->mood_value;
            $score = null;
            if ($moodValue == 3) {
                $score = 100;
            } elseif ($moodValue == 2) {
                $score = 51;
            } else {
                $score = 0;
            }
            
            $this->todayMoodData = [
                'mood_value' => $moodValue,
                'score' => $score,
                'description' => $todayHappyIndex->description,
            ];
        }

        // Show Happy Index only in allowed time (16:00 - 23:59)
        $now = \App\Helpers\TimezoneHelper::carbon(null, $tz);
        $dayOfWeek = $now->format('D');

        if ($user->hasRole('basecamp')) {
            $this->showHappyIndex = $now->between($now->copy()->setTime(16, 0), $now->copy()->setTime(23, 59));
        } else {
            // If user has no orgId, use default working days
            if (empty($user->orgId)) {
                $workingDays = ["Mon", "Tue", "Wed", "Thu", "Fri"];
                if (in_array($dayOfWeek, $workingDays)) {
                    $this->showHappyIndex = $now->between($now->copy()->setTime(16, 0), $now->copy()->setTime(23, 59));
                } else {
                    $this->showHappyIndex = false;
                }
            } else {
                $organisation = Organisation::find($user->orgId);
                $workingDays = [];

                if ($organisation && $organisation->working_days) {
                    $workingDaysRaw = $organisation->working_days;
                    if (is_string($workingDaysRaw)) {
                        $workingDays = json_decode($workingDaysRaw, true);
                    } elseif (is_array($workingDaysRaw)) {
                        $workingDays = $workingDaysRaw;
                    }
                }

                if (in_array($dayOfWeek, $workingDays)) {
                    $this->showHappyIndex = $now->between($now->copy()->setTime(16, 0), $now->copy()->setTime(23, 59));
                } else {
                    $this->showHappyIndex = false;
                }
            }
        }

        // Check subscription status for organization users and basecamp users
        if (!$user->hasRole('super_admin')) {
            if ($user->hasRole('basecamp')) {
                // Check basecamp user subscription
                $subscription = \App\Models\SubscriptionRecord::where('user_id', $user->id)
                    ->where('tier', 'basecamp')
                    ->orderBy('created_at', 'desc')
                    ->first();
                    
                if ($subscription) {
                    $endDate = $subscription->current_period_end ?? now()->addMonth();
                    $isExpired = now()->greaterThan($endDate);
                    
                    if ($isExpired || $subscription->status !== 'active') {
                        // Don't redirect, just show payment modal on dashboard
                        // The dashboard will show payment required modal
                        return;
                    }
                } else {
                    // No subscription found, don't redirect - dashboard will show payment modal
                    return;
                }
            } elseif ($user->orgId) {
                $subscriptionService = new SubscriptionService();
                $this->subscriptionStatus = $subscriptionService->getSubscriptionStatus($user->orgId);
                
                // Show expired modal if subscription is not active
                if (!$this->subscriptionStatus['active']) {
                    $this->showSubscriptionExpiredModal = true;
                    // Don't load data if subscription is expired
                    return;
                }
            }
        }

        $this->loadData();
    }

    public function closeSubscriptionExpiredModal()
    {
        $this->showSubscriptionExpiredModal = false;
    }

    /**
     * Update user timezone from browser or IP
     *
     * @return void
     */
    public function updateTimezoneFromBrowser()
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return;
            }

            // If user already has timezone, don't override
            if (!empty($user->timezone)) {
                return;
            }

            // Try to detect from IP if no timezone is set
            $request = request();
            $ipAddress = $request->ip();
            
            if ($ipAddress && !in_array($ipAddress, ['127.0.0.1', '::1', 'localhost'])) {
                try {
                    $response = \Illuminate\Support\Facades\Http::timeout(3)
                        ->get("https://ipapi.co/{$ipAddress}/json/");
                    
                    if ($response->successful()) {
                        $data = $response->json();
                        $timezone = $data['timezone'] ?? null;
                        
                        if ($timezone && in_array($timezone, timezone_identifiers_list())) {
                            $user->timezone = $timezone;
                            $user->save();
                            
                            Log::info("Auto-detected timezone for user {$user->id} from IP {$ipAddress}: {$timezone}");
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to detect timezone from IP {$ipAddress}: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Log::error("Error in updateTimezoneFromBrowser: " . $e->getMessage());
        }
    }

    /**
     * Update user timezone
     *
     * @param string $timezone
     * @return void
     */
    public function updateTimezone($timezone)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return;
            }

            // If timezone is blank/null, try to detect from IP
            if (empty($timezone) || $timezone === 'null' || $timezone === 'undefined') {
                $this->updateTimezoneFromBrowser();
                return;
            }

            // Validate timezone
            if (!in_array($timezone, timezone_identifiers_list())) {
                Log::warning("Invalid timezone provided: {$timezone} for user {$user->id}");
                return;
            }

            // Update only if different or if user has no timezone set
            if (empty($user->timezone) || $user->timezone !== $timezone) {
                $user->timezone = $timezone;
                $user->save();
                
                Log::info("Updated timezone for user {$user->id} to '{$timezone}' from dashboard");
                
                // Refresh calendar data after timezone change to show correct timezone-based data
                // Also refresh popup check and today's mood data
                // Clear existing data first to prevent stale data
                $this->happyIndexMonthly = [];
                $this->loadData();
                
                // Force Livewire to refresh the component
                $this->dispatch('$refresh');
            }
        } catch (\Exception $e) {
            Log::error("Error updating user timezone: " . $e->getMessage());
        }
    }

    public function updated($propertyName)
    {
        $reloadProperties = ['selectedOffice','selectedDepartment','month','year'];
        if (in_array($propertyName, $reloadProperties)) {
            $this->loadData();
        }
    }
public function updatedSelectedDepartment($value)
{
    $this->loadData();
}
    
    public function loadData()
    {
        $filters = [
            'month' => $this->month ? (int) $this->month : null,
            'year' => $this->year ? (int) $this->year : null,
            'officeId' => $this->selectedOffice ?: null,
            'departmentId' => $this->selectedDepartment ?: null,
            'orgId' => auth()->user()->orgId,
        ];

        $data = $this->service->getFreeVersionHomeDetails($filters);

        // Force update calendar data - ensure fresh timezone-based data
        // IMPORTANT: Clear existing data first to prevent stale data
        $this->happyIndexMonthly = [];
        
        // Get fresh data from service and ensure it's an array
        $calendarData = $data['happyIndexMonthly'] ?? [];
        if (is_array($calendarData)) {
            $this->happyIndexMonthly = $calendarData;
        } else {
            $this->happyIndexMonthly = [];
        }
        
        // Debug: Log calendar data for verification
        Log::info('DashboardSummary: Calendar data loaded', [
            'user_id' => auth()->id(),
            'month' => $this->month,
            'year' => $this->year,
            'orgId' => $filters['orgId'],
            'user_timezone' => \App\Helpers\TimezoneHelper::getUserTimezone(auth()->user()),
            'total_days' => count($this->happyIndexMonthly),
            'days_with_data' => collect($this->happyIndexMonthly)->filter(function($day) {
                return isset($day['mood_value']) && $day['mood_value'] !== null;
            })->map(function($day, $index) {
                return ['day' => $index + 1, 'mood' => $day['mood_value'], 'score' => $day['score']];
            })->values()->toArray(),
            'sample_data' => array_slice($this->happyIndexMonthly, 0, 7), // First 7 days for debugging
        ]);
        $this->orgYearList = $data['orgYearList'] ?? [];
        $this->departments = $this->service->getDepartmentList($filters);
        $this->offices = $this->service->getAllOfficenDepartments($filters);
        
        // Refresh today's mood data
        $user = auth()->user();
        $userTimezone = \App\Helpers\TimezoneHelper::getUserTimezone($user);
        
        // Get current date in user's timezone
        $userNow = \App\Helpers\TimezoneHelper::carbon(null, $userTimezone);
        $userTodayDate = $userNow->toDateString(); // Y-m-d format
        
        // Fetch today's mood data (using entry's stored timezone)
        $todayHappyIndex = HappyIndex::where('user_id', $user->id)
            ->get()
            ->filter(function ($entry) use ($userTimezone, $userTodayDate) {
                // Use stored timezone if available, otherwise fallback to current user timezone
                $entryTimezone = $entry->timezone ?? $userTimezone;
                // Convert entry's created_at (UTC) to entry's stored timezone and compare dates
                $entryDate = \App\Helpers\TimezoneHelper::setTimezone(Carbon::parse($entry->created_at), $entryTimezone)->toDateString();
                // Compare with today's date in the entry's timezone
                $entryTodayDate = \App\Helpers\TimezoneHelper::carbon(null, $entryTimezone)->toDateString();
                return $entryDate === $entryTodayDate;
            })
            ->first();
        
        if ($todayHappyIndex) {
            $moodValue = $todayHappyIndex->mood_value;
            $score = null;
            if ($moodValue == 3) {
                $score = 100;
            } elseif ($moodValue == 2) {
                $score = 51;
            } else {
                $score = 0;
            }
            
            $this->todayMoodData = [
                'mood_value' => $moodValue,
                'score' => $score,
                'description' => $todayHappyIndex->description,
            ];
        } else {
            $this->todayMoodData = null;
        }
    }

    public function applyLeave()
    {
        $this->validate([
            'leaveStartDate' => 'required|date|after_or_equal:' . auth()->user()->created_at,
            'leaveEndDate'   => 'required|date|after_or_equal:leaveStartDate',
        ]);

        $user = auth()->user();

        if (!$user || !in_array($user->status, ['active_verified', 'active_unverified'])) {
            session()->flash('error', 'User not found or inactive.');
            return;
        }

        UserLeave::create([
            'user_id'      => $user->id,
            'start_date'   => \Carbon\Carbon::parse($this->leaveStartDate)->toDateString(),
            'end_date'     => \Carbon\Carbon::parse($this->leaveEndDate)->toDateString(),
            'resume_date'  => \Carbon\Carbon::parse($this->leaveEndDate)->addDay()->toDateString(),
            'leave_status' => 1,
        ]);

        $user->onLeave = 1;
        $user->save();

    
        $this->dispatch('close-leave-modal');

    
        $this->leaveStartDate = null;
        $this->leaveEndDate   = null;

        return redirect()->route('dashboard')->with('success', "Sentiment");
    }

    public function changeLeaveStatus()
    {
        $userId = auth()->id();

        $userLeave = \App\Models\UserLeave::where('user_id', $userId)
            ->where('leave_status', 1)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($userLeave) {
            $userLeave->update([
                'resume_date'  => now()->toDateString(),
                'leave_status' => 0,
                'updated_at'   => now(),
            ]);

            \DB::table('users')
                ->where('id', $userId)
                ->where('status', '1')
                ->update([
                    'onLeave'    => 0,
                    'updated_at' => now(),
                ]);
        }

        // Refresh UI
        $this->dispatch('close-leave-modal');
        return redirect()->route('dashboard')->with('success', "Sentiment");
    }

    public function happyIndex()
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                Log::error('HappyIndex: User not authenticated');
                $this->dispatch('close-leave-modal');
                session()->flash('error', 'Please login to submit sentiment.');
                return;
            }
            
            $user = User::find($userId);
            
            // Debug: Log moodStatus value and user details
            Log::info('happyIndex called', [
                'moodStatus' => $this->moodStatus,
                'moodNote' => $this->moodNote,
                'user_id' => $userId,
                'user_status' => $user ? $user->status : null,
                'user_orgId' => $user ? $user->orgId : null,
                'user_has_basecamp_role' => $user ? $user->hasRole('basecamp') : false,
            ]);

        // Validate moodStatus
        try {
            $this->validate([
                'moodStatus' => 'required|integer|in:1,2,3',
            ], [
                'moodStatus.required' => 'Please select a mood status.',
                'moodStatus.in' => 'Invalid mood status selected.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('HappyIndex validation failed', [
                'user_id' => $userId,
                'errors' => $e->errors(),
                'moodStatus' => $this->moodStatus,
            ]);
            throw $e;
        }

        $moodValue = $this->moodStatus;
        $description = $this->moodNote ?? '';  

        // Check user eligibility - updated to handle all active statuses
        if (!$user) {
            Log::warning('User not found for HappyIndex', ['user_id' => $userId]);
            $this->dispatch('close-leave-modal');
            session()->flash('error', 'User not found.');
            return;
        }

        // Check if user is on leave
        if ($user->onLeave) {
            Log::warning('User on leave - cannot submit HappyIndex', [
                'user_id' => $userId,
                'onLeave' => $user->onLeave,
            ]);
            $this->dispatch('close-leave-modal');
            session()->flash('error', 'You are currently on leave.');
            return;
        }

        // Check user status - allow all active statuses (verified/unverified)
        $allowedStatuses = ['active_verified', 'active_unverified', true, '1', 1, 'pending_payment'];
        if (!in_array($user->status, $allowedStatuses)) {
            Log::warning('User status not eligible for HappyIndex', [
                'user_id' => $userId,
                'user_status' => $user->status,
                'allowed_statuses' => $allowedStatuses,
            ]);
            $this->dispatch('close-leave-modal');
            session()->flash('error', 'Your account status does not allow sentiment submission.');
            return;
        }

        // Get user's timezone safely using helper
        $userTimezone = \App\Helpers\TimezoneHelper::getUserTimezone($user);
        
        // Log user's timezone for debugging
        Log::info('User timezone before HappyIndex creation', [
            'user_id' => $userId,
            'user_timezone_from_db' => $user->timezone ?? 'null',
            'resolved_timezone' => $userTimezone,
        ]);
        
        // Get current date in user's timezone
        $userNow = \App\Helpers\TimezoneHelper::carbon(null, $userTimezone);
        $userDate = $userNow->toDateString(); // Y-m-d format in user's timezone
        
        Log::info('HappyIndex save attempt - checking existing entries', [
            'user_id' => $userId,
            'current_timezone' => $userTimezone,
            'today_date_in_current_tz' => $userDate,
            'user_now_datetime' => $userNow->toDateTimeString(),
        ]);
        
        // Check if user already submitted today in their CURRENT timezone
        // Logic: One entry per day based on current timezone's "today"
        // We only check entries that were created "today" in the current timezone
        // This means: if user submitted on 4 Feb in LA, and today is 5 Feb in India, they can submit for 5 Feb
        // Because the 4 Feb LA entry represents 4 Feb, not 5 Feb (even though in India timezone it shows as 5 Feb)
        
        // Get entries from the last 2 days to avoid checking all entries
        $twoDaysAgo = \App\Helpers\TimezoneHelper::carbon(null, $userTimezone)->subDays(2)->startOfDay()->utc();
        $allEntries = HappyIndex::where('user_id', $userId)
            ->where('created_at', '>=', $twoDaysAgo)
            ->orderBy('created_at', 'desc')
            ->get();
        
        $debugInfo = [
            'user_id' => $userId,
            'total_entries_checked' => $allEntries->count(),
            'current_timezone' => $userTimezone,
            'today_date_in_current_tz' => $userDate,
            'user_now_datetime' => $userNow->toDateTimeString(),
            'entries' => [],
        ];
        
        $existing = null;
        foreach ($allEntries as $entry) {
            try {
                // Get entry's stored timezone (or fallback to current timezone if not set)
                $entryTimezone = $entry->timezone ?? $userTimezone;
                
                // Convert entry's created_at (UTC) to entry's stored timezone to get the actual date when it was submitted
                $entryDateInStoredTimezone = \App\Helpers\TimezoneHelper::setTimezone(
                    \Carbon\Carbon::parse($entry->created_at), 
                    $entryTimezone
                )->toDateString();
                
                // Get today's date in the entry's stored timezone
                $todayInEntryTimezone = \App\Helpers\TimezoneHelper::carbon(null, $entryTimezone)->toDateString();
                
                // Check: Was this entry created "today" in its stored timezone?
                // If yes, then check if that "today" in entry's timezone matches "today" in current timezone
                // We need to check if the entry's date (in its timezone) and today's date (in current timezone) 
                // represent the same calendar day when considering timezone differences
                
                // Convert entry's UTC timestamp directly to current timezone to see what date it represents
                $entryDateInCurrentTimezone = \App\Helpers\TimezoneHelper::setTimezone(
                    \Carbon\Carbon::parse($entry->created_at), 
                    $userTimezone
                )->toDateString();
                
                // Only block if BOTH conditions are true:
                // 1. Entry represents "today" in the current timezone
                // 2. Entry was created "today" in its stored timezone (to prevent blocking old entries)
                // This allows: if user submitted on 4 Feb in LA, and now it's 5 Feb in India, they can submit for 5 Feb
                // Because the 4 Feb LA entry was created on 4 Feb (not 5 Feb) in LA timezone
                $entryWasCreatedTodayInStoredTz = ($entryDateInStoredTimezone === $todayInEntryTimezone);
                $entryRepresentsTodayInCurrentTz = ($entryDateInCurrentTimezone === $userDate);
                
                // Block only if entry was created "today" in its stored timezone AND represents "today" in current timezone
                // This means: if entry was created yesterday in a different timezone, don't block
                $isMatch = $entryWasCreatedTodayInStoredTz && $entryRepresentsTodayInCurrentTz;
                
                $entryDebug = [
                    'id' => $entry->id,
                    'created_at_utc' => $entry->created_at,
                    'entry_timezone' => $entryTimezone,
                    'entry_date_in_stored_tz' => $entryDateInStoredTimezone,
                    'today_in_entry_tz' => $todayInEntryTimezone,
                    'entry_was_today_in_stored_tz' => $entryWasCreatedTodayInStoredTz,
                    'entry_date_in_current_tz' => $entryDateInCurrentTimezone,
                    'today_in_current_tz' => $userDate,
                    'entry_represents_today_in_current_tz' => $entryRepresentsTodayInCurrentTz,
                    'mood_value' => $entry->mood_value,
                    'is_match' => $isMatch,
                ];
                
                $debugInfo['entries'][] = $entryDebug;
                
                if ($isMatch && !$existing) {
                    $existing = $entry;
                }
            } catch (\Exception $e) {
                Log::error('Error checking entry for existing submission', [
                    'entry_id' => $entry->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        Log::info('HappyIndex existing entry check', $debugInfo);
        
        // Dispatch debug info to console
        $this->dispatch('happyindex-debug', debugInfo: $debugInfo);

        if ($existing) {
            $blockInfo = [
                'user_id' => $userId,
                'existing_entry_id' => $existing->id,
                'existing_entry_created_at' => $existing->created_at,
                'existing_entry_timezone' => $existing->timezone ?? 'null',
                'current_timezone' => $userTimezone,
                'today_date' => $userDate,
                'message' => 'You have already submitted your response today.',
            ];
            
            Log::warning('HappyIndex save blocked - already submitted today', $blockInfo);
            
            // Dispatch to console
            $this->dispatch('happyindex-blocked', blockInfo: $blockInfo);
            
            $this->dispatch('close-leave-modal'); 
            session()->flash('error', 'You have already submitted your response today.');
            return;
        }
        
        $saveInfo = [
            'user_id' => $userId,
            'current_timezone' => $userTimezone,
            'today_date' => $userDate,
            'mood_value' => $moodValue,
            'message' => 'No existing entry found - proceeding with save',
        ];
        
        Log::info('HappyIndex proceeding with save', $saveInfo);
        
        // Dispatch to console
        $this->dispatch('happyindex-saving', saveInfo: $saveInfo);

        // Create entry with timezone-aware timestamp
        // We need to ensure the created_at represents the user's local date/time
        // Get the user's current date/time in their timezone, then convert to UTC for storage
        $userLocalDateTime = \App\Helpers\TimezoneHelper::carbon(null, $userTimezone);
        $utcTimestamp = $userLocalDateTime->utc();
        
        // Ensure timezone is saved correctly (should be user's timezone, default to Asia/Kolkata)
        $timezoneToSave = !empty($userTimezone) ? $userTimezone : 'Asia/Kolkata';
        
        // Use DB facade to insert with explicit timestamps to prevent Laravel from overriding
        $happyIndexId = \DB::table('happy_indexes')->insertGetId([
            'user_id'      => $userId,
            'mood_value'   => $moodValue,
            'description'  => $description,
            'status'       => 'active',
            'timezone'     => $timezoneToSave, // Store the timezone when entry was created
            'created_at'   => $utcTimestamp,
            'updated_at'   => $utcTimestamp,
        ]);
        
        Log::info('HappyIndex created with timezone-aware timestamp', [
            'user_id' => $userId,
            'happy_index_id' => $happyIndexId,
            'user_timezone' => $userTimezone,
            'timezone_saved' => $timezoneToSave,
            'user_local_date' => $userLocalDateTime->toDateString(),
            'user_local_time' => $userLocalDateTime->toTimeString(),
            'user_local_datetime' => $userLocalDateTime->toDateTimeString(),
            'utc_timestamp' => $utcTimestamp->toDateTimeString(),
            'timezone_offset' => $userLocalDateTime->format('P'), // +05:30 for IST
        ]);

        $user->EIScore += 250;
        $user->lastHIDate = $userDate; // Store date in user's timezone
        $user->lastHIDate = now()->toDateString();
        $user->updated_at = now();
        $user->save();

        // ✅ Mark sentiment submitted in OneSignal (stops 6PM email reminder)
        try {
            $oneSignal = new OneSignalService();
            $result = $oneSignal->markSentimentSubmitted($userId);
            Log::info('OneSignal markSentimentSubmitted called from DashboardSummary', [
                'user_id' => $userId,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::warning('OneSignal markSentimentSubmitted failed in DashboardSummary', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        $learningChecklistTotalScore = HptmLearningChecklist::leftJoin(
                'hptm_learning_types',
                'hptm_learning_types.id',
                '=',
                'hptm_learning_checklist.output'
            )
            ->sum('hptm_learning_types.score');

        $userHptmScore = (($user->hptmScore + $user->hptmEvaluationScore)
                / ($learningChecklistTotalScore + 400)) * 1000;

        $todayEIScore = str_replace(',', '', number_format($user->EIScore + $userHptmScore, 2));

        // Update today's mood data after submission
        $this->userGivenFeedback = true;
        $this->todayMoodData = [
            'mood_value' => $moodValue,
            'score' => $moodValue == 3 ? 100 : ($moodValue == 2 ? 51 : 0),
            'description' => $description,
        ];
        
        // Reset form fields
        $this->moodStatus = null;
        $this->moodNote = null;
        
            $successInfo = [
                'user_id' => $userId,
                'mood_value' => $moodValue,
                'happy_index_id' => $happyIndexId,
                'timezone' => $timezoneToSave,
                'created_at_utc' => $utcTimestamp->toDateTimeString(),
                'ei_score' => $todayEIScore,
                'message' => "Sentiment submitted successfully! Today's EI Score: $todayEIScore",
            ];
            
            Log::info('HappyIndex submitted successfully', $successInfo);
            
            // Dispatch to console
            $this->dispatch('happyindex-success', successInfo: $successInfo);
            
            // Show success message
            session()->flash('success', "Sentiment submitted successfully! Today's EI Score: $todayEIScore");
            
            // Dispatch event to close modal
            $this->dispatch('close-leave-modal');
            
            // Refresh the page to show updated data
            return $this->redirect(route('dashboard'), navigate: true);
        } catch (\Exception $e) {
            Log::error('HappyIndex save failed with exception', [
                'user_id' => $userId ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->dispatch('close-leave-modal');
            session()->flash('error', 'Failed to save sentiment. Please try again.');
            return;
        }
    }

    /**
     * Refresh data after sentiment submission
     * This is called when 'summary-saved' event is dispatched
     */
    public function refreshAfterSubmit()
    {
        // Refresh calendar and today's mood data
        $this->loadData();
    }

    public function render()
    {
        return view('livewire.dashboard-summary');
    }
}
