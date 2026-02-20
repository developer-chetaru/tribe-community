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
    public $refreshKey = 0;

    public function mount()
    {
        $this->selectedYear = (int) now()->year;
        $this->selectedMonth = (int) now()->month;

        $this->calculateValidMonthsAndYears();
        $this->loadSummariesFromDatabase();
    }

    public function filterByMonth($value)
    {
        Log::info('WeeklySummary filterByMonth CALLED', [
            'user_id' => Auth::id(),
            'received_value' => $value,
            'value_type' => gettype($value),
        ]);
        
        $this->selectedMonth = (int) $value;
        $this->selectedYear = (int) $this->selectedYear;
        
        // Recalculate valid months to ensure consistency
        $this->calculateValidMonthsAndYears();
        
        // Verify the selected month is still valid
        $validMonthValues = array_column($this->validMonths, 'value');
        if (!empty($validMonthValues) && !in_array($this->selectedMonth, $validMonthValues)) {
            // If month is not valid, set to first valid month
            $this->selectedMonth = (int) min($validMonthValues);
        }
        
        // Load data
        $this->loadSummariesFromDatabase();
        
        // Increment refresh key to force re-render
        $this->refreshKey = time();
        
        Log::info('WeeklySummary filterByMonth COMPLETED', [
            'user_id' => Auth::id(),
            'selectedYear' => $this->selectedYear,
            'selectedMonth' => $this->selectedMonth,
            'weeklySummaries_count' => count($this->weeklySummaries),
            'refreshKey' => $this->refreshKey,
        ]);
    }

    public function updatedSelectedMonth($value)
    {
        // Keep this for wire:model compatibility but use filterByMonth for actual filtering
        $this->filterByMonth($value);
    }

    public function filterByYear($value)
    {
        Log::info('WeeklySummary filterByYear CALLED', [
            'user_id' => Auth::id(),
            'received_value' => $value,
            'value_type' => gettype($value),
        ]);
        
        $this->selectedYear = (int) $value;
        $previousMonth = (int) $this->selectedMonth;
        
        // Recalculate valid months for the new year
        $this->calculateValidMonthsAndYears();
        
        // Ensure selectedMonth is within validMonths after year change
        $validMonthValues = array_column($this->validMonths, 'value');
        if (!empty($validMonthValues)) {
            // Try to preserve the same month if it's valid for the new year
            if (!in_array($previousMonth, $validMonthValues)) {
                // If previous month is not valid, set to last valid month (most recent)
                $this->selectedMonth = (int) max($validMonthValues);
            } else {
                // Preserve the same month if it's valid
                $this->selectedMonth = (int) $previousMonth;
            }
        }
        
        // Load data
        $this->loadSummariesFromDatabase();
        
        // Increment refresh key to force re-render
        $this->refreshKey = time();
        
        Log::info('WeeklySummary filterByYear COMPLETED', [
            'user_id' => Auth::id(),
            'selectedYear' => $this->selectedYear,
            'selectedMonth' => $this->selectedMonth,
            'weeklySummaries_count' => count($this->weeklySummaries),
            'refreshKey' => $this->refreshKey,
        ]);
    }

    public function updatedSelectedYear($value)
    {
        // Keep this for wire:model compatibility but use filterByYear for actual filtering
        $this->filterByYear($value);
    }

    public function loadSummariesFromDatabase()
    {
        $user = Auth::user();
        if (!$user) return;

        // Ensure year and month are integers
        $year = (int) $this->selectedYear;
        $month = (int) $this->selectedMonth;

        // Reset array first to ensure Livewire detects the change
        $this->weeklySummaries = [];

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

        // Convert to indexed array - use array_values to ensure Livewire detects the change
        $this->weeklySummaries = array_values($weeksInMonth);
        
        Log::info('WeeklySummary weeklySummaries updated', [
            'user_id' => $user->id,
            'count' => count($this->weeklySummaries),
            'weeks' => array_column($this->weeklySummaries, 'week'),
        ]);
    }

    private function calculateValidMonthsAndYears()
    {
        $user = Auth::user();
        if (!$user) return;

        $startYear = (int) $user->created_at->year;
        $currentYear = (int) now()->year;
        $this->validYears = range($startYear, $currentYear);

        // Ensure selectedYear is int for comparison
        $selectedYear = (int) $this->selectedYear;

        if ($selectedYear == $startYear && $selectedYear == $currentYear) {
            $startMonth = (int) $user->created_at->month;
            $maxMonth = (int) now()->month;
        } elseif ($selectedYear == $startYear) {
            $startMonth = (int) $user->created_at->month;
            $maxMonth = 12;
        } elseif ($selectedYear == $currentYear) {
            $startMonth = 1;
            $maxMonth = (int) now()->month;
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
        // Recalculate valid months and years on each render to ensure consistency
        $this->calculateValidMonthsAndYears();
        
        // Log render to debug
        Log::info('WeeklySummary render called', [
            'user_id' => Auth::id(),
            'selectedYear' => $this->selectedYear,
            'selectedMonth' => $this->selectedMonth,
            'weeklySummaries_count' => count($this->weeklySummaries),
            'refreshKey' => $this->refreshKey,
        ]);
        
        return view('livewire.weekly-summary');
    }
}
