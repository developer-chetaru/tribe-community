<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\HappyIndex;
use App\Models\UserLeave;
use App\Models\Organisation;
use Illuminate\Support\Facades\Auth;
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

        // Get user's timezone safely using helper
        $userTimezone = \App\Helpers\TimezoneHelper::getUserTimezone($user);

        // Get user's working days
        $org = $user->organisation;
        $workingDays = $org && $org->working_days
            ? $org->working_days
            : ["Mon", "Tue", "Wed", "Thu", "Fri"];

        // Determine date range using user's timezone
        $userNow = \App\Helpers\TimezoneHelper::carbon(null, $userTimezone);
        
        // Get user's registration date in user's timezone (minimum start date)
        $userRegistrationDate = Carbon::parse($user->created_at)->setTimezone($userTimezone)->startOfDay();
        
        switch ($this->filterType) {
            case 'this_week':
                $start = $userNow->copy()->startOfWeek();
                $end   = $userNow->copy()->endOfWeek();
                break;

            case 'last_7_days':
                $start = $userNow->copy()->subDays(7);
                $end   = $userNow->copy();
                break;

            case 'previous_week':
                $start = $userNow->copy()->subWeek()->startOfWeek();
                $end   = $userNow->copy()->subWeek()->endOfWeek();
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
        $period = collect($start->copy()->daysUntil($end->copy()->addDay()))
            ->map(function ($date) use ($userTimezone) {
                return \App\Helpers\TimezoneHelper::setTimezone(Carbon::parse($date), $userTimezone);
            })
            ->sortByDesc(fn($d) => $d->timestamp);

        $userToday = Carbon::now($userTimezone);

        foreach ($period as $date) {
            // Skip future dates (using user's timezone)
            if ($date->greaterThan($userToday)) continue;
            
            // Skip dates before user's registration date
            if ($date->lessThan($userRegistrationDate)) continue;

            // Skip non-working days
            if (!in_array($date->format('D'), $workingDays)) continue;

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

                // 🟢 SHOW MISSED ONLY FOR PAST DAYS (NEVER TODAY) - using user's timezone
                if ($date->isPast() && !$date->isSameDay($userToday)) {
                    $entriesWithStatus[] = [
                        'date'        => $dateStr,
                        'score'       => null,
                        'mood_value'  => null,
                        'description' => "Oh Dear, you missed to share your sentiment on $dateStr",
                        'image'       => 'sentiment-missed-summary.svg',
                        'status'      => 'Missed',
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
