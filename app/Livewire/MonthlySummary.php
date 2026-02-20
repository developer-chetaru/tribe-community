<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\MonthlySummary as MonthlySummaryModel;
use App\Models\HappyIndex;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use OpenAI\Laravel\Facades\OpenAI;

class MonthlySummary extends Component
{
    public $selectedYear;
    public $selectedMonth;
    public $monthlySummaries = [];
    public $validMonths = [];
    public $validYears = [];
    public $isGenerating = false;
    public $refreshKey = 0;

    public function mount()
    {
        $this->selectedYear = (int) now()->year;
        $this->selectedMonth = (int) now()->month;

        $this->calculateValidMonthsAndYears();
        $this->loadSummariesFromDatabase();
    }

    public function filterByYear($value)
    {
        \Illuminate\Support\Facades\Log::info('MonthlySummary filterByYear CALLED', [
            'user_id' => Auth::id(),
            'received_value' => $value,
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
        
        // Set refresh key to force re-render
        $this->refreshKey = time();
        
        \Illuminate\Support\Facades\Log::info('MonthlySummary filterByYear COMPLETED', [
            'user_id' => Auth::id(),
            'selectedYear' => $this->selectedYear,
            'selectedMonth' => $this->selectedMonth,
            'monthlySummaries_count' => count($this->monthlySummaries),
            'refreshKey' => $this->refreshKey,
        ]);
    }

    public function updatedSelectedYear($value)
    {
        // Keep this for wire:model compatibility but use filterByYear for actual filtering
        $this->filterByYear($value);
    }

    public function filterByMonth($value)
    {
        \Illuminate\Support\Facades\Log::info('MonthlySummary filterByMonth CALLED', [
            'user_id' => Auth::id(),
            'received_value' => $value,
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
        
        // Set refresh key to force re-render
        $this->refreshKey = time();
        
        \Illuminate\Support\Facades\Log::info('MonthlySummary filterByMonth COMPLETED', [
            'user_id' => Auth::id(),
            'selectedYear' => $this->selectedYear,
            'selectedMonth' => $this->selectedMonth,
            'monthlySummaries_count' => count($this->monthlySummaries),
            'refreshKey' => $this->refreshKey,
        ]);
    }

    public function updatedSelectedMonth($value)
    {
        // Keep this for wire:model compatibility but use filterByMonth for actual filtering
        $this->filterByMonth($value);
    }

    public function loadSummariesFromDatabase()
    {
        $user = Auth::user();
        if (!$user) return;

        // Ensure year and month are integers
        $year = (int) $this->selectedYear;
        $month = (int) $this->selectedMonth;

        // Reset array first to ensure Livewire detects the change
        $this->monthlySummaries = [];

        // Debug logging
        \Illuminate\Support\Facades\Log::info('MonthlySummary loadSummariesFromDatabase called', [
            'user_id' => $user->id,
            'selectedYear' => $year,
            'selectedMonth' => $month,
        ]);

        // Get user's registration date safely
        $userRegistrationDate = \App\Helpers\TimezoneHelper::setTimezone(Carbon::parse($user->created_at), \App\Helpers\TimezoneHelper::DEFAULT_TIMEZONE)->startOfDay();
        
        // Get the selected month's start date
        $selectedMonthStart = Carbon::create($year, $month, 1)->startOfMonth();
        
        // Skip if the selected month occurred before user's registration
        if ($selectedMonthStart->lt($userRegistrationDate)) {
            $this->monthlySummaries = [];
            \Illuminate\Support\Facades\Log::info('MonthlySummary: Month before user registration', [
                'user_id' => $user->id,
                'selectedMonthStart' => $selectedMonthStart->format('Y-m-d'),
                'userRegistrationDate' => $userRegistrationDate->format('Y-m-d'),
            ]);
            return;
        }

        $summary = MonthlySummaryModel::where('user_id', $user->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        // Assign the new data - Livewire should detect this change
        $this->monthlySummaries = $summary ? [$summary] : [];
        
        \Illuminate\Support\Facades\Log::info('MonthlySummary monthlySummaries updated', [
            'user_id' => $user->id,
            'count' => count($this->monthlySummaries),
        ]);
        
        \Illuminate\Support\Facades\Log::info('MonthlySummary: Summary loaded', [
            'user_id' => $user->id,
            'year' => $year,
            'month' => $month,
            'found' => $summary ? 'yes' : 'no',
        ]);
    }

    public function generateMonthlySummary($userId = null, $year = null, $month = null)
    {
        $user = $userId ? Auth::user()->where('id', $userId)->first() : Auth::user();
        if (!$user) return;

        $year = $year ?? $this->selectedYear ?? now()->year;
        $month = $month ?? $this->selectedMonth ?? now()->month;

        $this->isGenerating = true;

        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = Carbon::create($year, $month, 1)->endOfMonth();

        $allData = HappyIndex::where('user_id', $user->id)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->orderBy('created_at')
            ->get(['mood_value', 'description', 'created_at']);

        if ($allData->isEmpty()) {
            $summaryText = "No data available for this month.";
        } else {
            $dataText = $allData->map(fn($h) =>
                $h->created_at->format('M d') . ': ' .
                ($h->mood_value == 3 ? 'Good' : ($h->mood_value == 1 ? 'Bad' : 'Ok')) .
                ' - ' . ($h->description ?? '')
            )->implode("\n");

            $prompt = "Generate a short positive monthly summary for " . $startOfMonth->format('F Y') . " based on these daily mood entries:\n{$dataText}";

            try {
                $response = OpenAI::responses()->create([
                    'model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4.1-mini'),
                    'input' => $prompt,
                ]);

                $summaryText = '';
                foreach ($response->output ?? [] as $item) {
                    foreach ($item->content ?? [] as $c) {
                        $summaryText .= $c->text ?? '';
                    }
                }

                $summaryText = trim($summaryText) ?: 'No summary generated.';
            } catch (\Exception $e) {
                $summaryText = 'Error generating summary: ' . $e->getMessage();
            }
        }

        MonthlySummaryModel::updateOrCreate(
            [
                'user_id' => $user->id,
                'year' => $year,
                'month' => $month,
            ],
            [
                'summary' => $summaryText,
            ]
        );

        $this->loadSummariesFromDatabase();
        $this->isGenerating = false;
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

        // Determine valid months for the selected year
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
        \Illuminate\Support\Facades\Log::info('MonthlySummary render called', [
            'user_id' => Auth::id(),
            'selectedYear' => $this->selectedYear,
            'selectedMonth' => $this->selectedMonth,
            'monthlySummaries_count' => count($this->monthlySummaries),
            'refreshKey' => $this->refreshKey,
        ]);
        
        return view('livewire.monthly-summary');
    }
}