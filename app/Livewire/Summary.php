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
        $userId = Auth::id();

        // Get user's working days
        $org = Auth::user()->organisation;
        $workingDays = $org && $org->working_days
            ? $org->working_days
            : ["Mon", "Tue", "Wed", "Thu", "Fri"];

        // Determine date range
        switch ($this->filterType) {
            case 'this_week':
                $start = Carbon::now()->startOfWeek();
                $end   = Carbon::now()->endOfWeek();
                break;

            case 'last_7_days':
                $start = Carbon::now()->subDays(7);
                $end   = Carbon::now();
                break;

            case 'previous_week':
                $start = Carbon::now()->subWeek()->startOfWeek();
                $end   = Carbon::now()->subWeek()->endOfWeek();
                break;

            case 'this_month':
                $start = Carbon::now()->startOfMonth();
                $end   = Carbon::now()->endOfMonth();
                break;

            case 'previous_month':
                $start = Carbon::now()->subMonth()->startOfMonth();
                $end   = Carbon::now()->subMonth()->endOfMonth();
                break;

            case 'custom':
                $this->validateCustomDates();
                $start = Carbon::parse($this->startDate);
                $end   = Carbon::parse($this->endDate);
                break;

            case 'all':
            default:
                $start = Auth::user()->created_at->startOfDay();
                $end   = Carbon::now()->endOfDay();
                break;
        }

        // Fetch happy indexes
        $happyIndexes = HappyIndex::where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
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

        $period = collect(Carbon::parse($start)->daysUntil(Carbon::parse($end)->addDay()))
            ->sortByDesc(fn($d) => $d->timestamp);

        foreach ($period as $date) {

            // Skip future dates
            if ($date->greaterThan(Carbon::today())) continue;

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

            // Check sentiment entry
            $entry = $happyIndexes->first(fn($h) => $h->created_at->isSameDay($date));

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

                // 🟢 SHOW MISSED ONLY FOR PAST DAYS (NEVER TODAY)
                if ($date->isPast() && !$date->isToday()) {
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
