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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

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

    protected $service;

    protected $listeners = [
        'refreshData' => 'loadData'
    ];

    public function boot(DashboardService $service)
    {
        $this->service = $service;
    }

    public function mount()
    {
        $user = auth()->user();
        $tz = $user->timezone ?? 'Asia/Kolkata';
        $today = Carbon::now($tz)->startOfDay();

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

        // Check if user gave feedback today
        $this->userGivenFeedback = HappyIndex::where('user_id', $user->id)
            ->whereDate('created_at', $today)
            ->exists();

        // Show Happy Index only in allowed time (16:00 - 23:59)
        $now = Carbon::now($tz);
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
                        // Redirect to billing page if subscription is expired
                        return redirect()->route('billing')
                            ->with('error', 'Your subscription has expired. Please renew to continue using the service.');
                    }
                } else {
                    // No subscription found, redirect to billing
                    return redirect()->route('billing')
                        ->with('error', 'No active subscription found. Please complete your payment.');
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

        $this->happyIndexMonthly = $data['happyIndexMonthly'] ?? [];
        $this->orgYearList = $data['orgYearList'] ?? [];
        $this->departments = $this->service->getDepartmentList($filters);
        $this->offices = $this->service->getAllOfficenDepartments($filters);
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
        $userId = auth()->id();
        $moodValue = $this->moodStatus;

        $description = $this->moodNote ?? '';  

        $existing = HappyIndex::where('user_id', $userId)
            ->whereDate('created_at', now()->toDateString())
            ->first();

        if ($existing) {
            $this->dispatch('close-leave-modal'); 
            session()->flash('error', 'You have already submitted your response today.');
            return;
        }

        $user = User::where('id', $userId)->where('status', '1')->first();

        if (!$user || $user->onLeave) {
            $this->dispatch('close-leave-modal');
            session()->flash('error', 'User not eligible.');
            return;
        }

        HappyIndex::create([
            'user_id'      => $userId,
            'mood_value'   => $moodValue,
            'description'  => $description,
            'status'       => 'active',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $user->EIScore += 250;
        $user->lastHIDate = now()->toDateString();
        $user->updated_at = now();
        $user->save();

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

        $this->dispatch('close-leave-modal'); 
        return redirect()->route('dashboard')->with('success', "Sentiment submitted successfully! Today's EI Score: $todayEIScore");
    }

    public function render()
    {
        return view('livewire.dashboard-summary');
    }
}
