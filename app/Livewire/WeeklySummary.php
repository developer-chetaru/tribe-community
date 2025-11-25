<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\WeeklySummary as WeeklySummaryModel;
use Illuminate\Support\Facades\Auth;
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
        $this->selectedYear = now()->year;
        $this->selectedMonth = now()->month;

        $this->calculateValidMonthsAndYears();
        $this->loadSummariesFromDatabase();
    }

    public function updatedSelectedMonth()
    {
        $this->loadSummariesFromDatabase();
    }

    public function updatedSelectedYear()
    {
        $this->calculateValidMonthsAndYears();
        $this->loadSummariesFromDatabase();
    }

    public function loadSummariesFromDatabase()
    {
        $user = Auth::user();
        if (!$user) return;

        $existingSummaries = WeeklySummaryModel::where('user_id', $user->id)
            ->where('year', $this->selectedYear)
            ->where('month', $this->selectedMonth)
            ->orderBy('week_number')
            ->get()
            ->keyBy('week_number');

        $firstDay = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->startOfMonth();
        $lastDay = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->endOfMonth();
        $weekNum = 1;
        $weekStart = $firstDay->copy()->startOfWeek(Carbon::MONDAY);

        $weeksInMonth = [];

        while ($weekStart->lte($lastDay)) {
            $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

            // Skip future weeks
            if ($weekStart->gt(now())) break;

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
