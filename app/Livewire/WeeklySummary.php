<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\WeeklySummary as WeeklySummaryModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WeeklySummary extends Component
{
    public $selectedYear;
    public $selectedMonth;
    public $weeklySummaries = [];
    public $validMonths = [];
    public $validYears = [];

    public function mount()
    {
        $this->selectedYear = (int) now()->year;
        $this->selectedMonth = (int) now()->month;

        $this->calculateValidMonthsAndYears();
        $this->loadSummariesFromDatabase();
    }

    public function updatedSelectedMonth($value)
    {
        // Ensure month is cast to int
        $this->selectedMonth = (int) $value;
        
        // Ensure year is also int (in case it's a string)
        $this->selectedYear = (int) $this->selectedYear;
        
        // Recalculate valid months to ensure consistency
        $this->calculateValidMonthsAndYears();
        
        // Verify the selected month is still valid
        $validMonthValues = array_column($this->validMonths, 'value');
        if (!empty($validMonthValues) && !in_array($this->selectedMonth, $validMonthValues)) {
            // If month is not valid, set to first valid month
            $this->selectedMonth = (int) min($validMonthValues);
        }
        
        // Debug logging
        Log::info('WeeklySummary updatedSelectedMonth', [
            'user_id' => Auth::id(),
            'selectedYear' => $this->selectedYear,
            'selectedMonth' => $this->selectedMonth,
            'validMonths' => $validMonthValues,
        ]);
        
        $this->loadSummariesFromDatabase();
    }

    public function updatedSelectedYear($value)
    {
        // Ensure year is cast to int
        $this->selectedYear = (int) $value;
        
        // Store the current month before recalculating (cast to int)
        $previousMonth = (int) $this->selectedMonth;
        
        // Recalculate valid months for the new year
        $this->calculateValidMonthsAndYears();
        
        // Ensure selectedMonth is within validMonths after year change
        $validMonthValues = array_column($this->validMonths, 'value');
        if (!empty($validMonthValues)) {
            // Try to preserve the same month if it's valid for the new year
            // This ensures Oct 2026 -> Oct 2025 works correctly
            if (!in_array($previousMonth, $validMonthValues)) {
                // If previous month is not valid, set to last valid month (most recent)
                // This ensures we show the most recent data available
                $this->selectedMonth = (int) max($validMonthValues);
            } else {
                // Preserve the same month if it's valid
                $this->selectedMonth = $previousMonth;
            }
        }
        
        // Debug logging
        Log::info('WeeklySummary updatedSelectedYear', [
            'user_id' => Auth::id(),
            'previousMonth' => $previousMonth,
            'newYear' => $this->selectedYear,
            'selectedMonth' => $this->selectedMonth,
            'selectedMonthType' => gettype($this->selectedMonth),
            'validMonths' => $validMonthValues,
        ]);
        
        // Load summaries for the new year and (possibly adjusted) month
        $this->loadSummariesFromDatabase();
    }

    public function loadSummariesFromDatabase()
    {
        $user = Auth::user();
        if (!$user) return;

        // Ensure year and month are integers
        $year = (int) $this->selectedYear;
        $month = (int) $this->selectedMonth;

        // Debug logging
        Log::info('WeeklySummary loadSummariesFromDatabase called', [
            'user_id' => $user->id,
            'selectedYear' => $year,
            'selectedMonth' => $month,
            'selectedYearType' => gettype($this->selectedYear),
            'selectedMonthType' => gettype($this->selectedMonth),
        ]);

        $existingSummaries = WeeklySummaryModel::where('user_id', $user->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('week_number')
            ->get()
            ->keyBy('week_number');
        
        Log::info('WeeklySummary existingSummaries found', [
            'user_id' => $user->id,
            'count' => $existingSummaries->count(),
            'summaries' => $existingSummaries->map(fn($s) => ['week' => $s->week_number, 'has_summary' => !empty($s->summary)])->toArray(),
        ]);

        // Use the integer values to ensure proper date creation
        $firstDay = Carbon::create($year, $month, 1)->startOfMonth();
        $lastDay = Carbon::create($year, $month, 1)->endOfMonth();
        $weekNum = 1;
        $weekStart = $firstDay->copy()->startOfWeek(Carbon::MONDAY);

        $weeksInMonth = [];

        // Use safe timezone helper
        $defaultTimezone = \App\Helpers\TimezoneHelper::DEFAULT_TIMEZONE;
        $today = \App\Helpers\TimezoneHelper::carbon(null, $defaultTimezone);
        
        // Get user's registration date in the same timezone
        $userRegistrationDate = \App\Helpers\TimezoneHelper::setTimezone(Carbon::parse($user->created_at), $defaultTimezone)->startOfDay();
        
        while ($weekStart->lte($lastDay)) {
            $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

            // Skip future weeks and current week (only show summaries for completed weeks)
            // A week is considered completed only if its end date (Sunday) has passed
            if ($weekStart->gt($today) || $weekEnd->gt($today)) break;
            
            // Skip weeks that occurred before user's registration date
            // Only show weeks where the week's end date (Sunday) is on or after registration date
            if ($weekEnd->lt($userRegistrationDate)) {
                $weekNum++;
                $weekStart->addWeek();
                continue;
            }

            $weeksInMonth[$weekNum] = [
                'week' => $weekNum,
                'weekLabel' => $weekStart->format('M d') . ' - ' . $weekEnd->format('M d'),
                'summary' => $existingSummaries[$weekNum]->summary ?? null,
            ];

            $weekNum++;
            $weekStart->addWeek();
        }

        $this->weeklySummaries = $weeksInMonth;
    }

    private function calculateValidMonthsAndYears()
    {
        $user = Auth::user();
        if (!$user) return;

        $startYear = $user->created_at->year;
        $currentYear = now()->year;
        $this->validYears = range($startYear, $currentYear);

        if ($this->selectedYear == $startYear && $this->selectedYear == $currentYear) {
            $startMonth = $user->created_at->month;
            $maxMonth = now()->month;
        } elseif ($this->selectedYear == $startYear) {
            $startMonth = $user->created_at->month;
            $maxMonth = 12;
        } elseif ($this->selectedYear == $currentYear) {
            $startMonth = 1;
            $maxMonth = now()->month;
        } else {
            $startMonth = 1;
            $maxMonth = 12;
        }

        $this->validMonths = [];
        for ($m = $startMonth; $m <= $maxMonth; $m++) {
            $this->validMonths[] = [
                'value' => $m,
                'name' => date('F', mktime(0, 0, 0, $m, 1))
            ];
        }
    }

    public function render()
    {
        return view('livewire.weekly-summary');
    }
}
