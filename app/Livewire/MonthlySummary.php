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

    public function mount()
    {
        $this->selectedYear = now()->year;
        $this->selectedMonth = now()->month;

        $this->calculateValidMonthsAndYears();
        $this->loadSummariesFromDatabase();
    }

    public function updatedSelectedYear($value)
    {
        $this->calculateValidMonthsAndYears();

        // Ensure selectedMonth is within validMonths
        $validMonthValues = array_column($this->validMonths, 'value');
        if (!in_array($this->selectedMonth, $validMonthValues)) {
            $this->selectedMonth = max($validMonthValues);
        }

        $this->loadSummariesFromDatabase();
    }

    public function updatedSelectedMonth($value)
    {
        $this->loadSummariesFromDatabase();
    }

    public function loadSummariesFromDatabase()
    {
        $user = Auth::user();
        if (!$user) return;

        // Get user's registration date
        $userRegistrationDate = Carbon::parse($user->created_at)->startOfDay();
        
        // Get the selected month's start date
        $selectedMonthStart = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->startOfMonth();
        
        // Skip if the selected month occurred before user's registration
        if ($selectedMonthStart->lt($userRegistrationDate)) {
            $this->monthlySummaries = [];
            return;
        }

        $summary = MonthlySummaryModel::where('user_id', $user->id)
            ->where('year', $this->selectedYear)
            ->where('month', $this->selectedMonth)
            ->first();

        $this->monthlySummaries = $summary ? [$summary] : [];
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

        $startYear = $user->created_at->year;
        $currentYear = now()->year;
        $this->validYears = range($startYear, $currentYear);

        // Determine valid months for the selected year
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
        return view('livewire.monthly-summary');
    }
}