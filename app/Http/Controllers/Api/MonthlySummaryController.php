<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\MonthlySummary as MonthlySummaryModel;
use App\Models\HappyIndex;
use Carbon\Carbon;
use OpenAI\Laravel\Facades\OpenAI;

class MonthlySummaryController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        $selectedYear = $request->input('year', now()->year);
        $selectedMonth = $request->input('month', now()->month);

        // Load monthly summary
        $summary = MonthlySummaryModel::where('user_id', $user->id)
            ->where('year', $selectedYear)
            ->where('month', $selectedMonth)
            ->first();

        $monthlySummaries = $summary ? [$summary] : [];

        // Calculate valid months and years
        $startYear = $user->created_at->year;
        $currentYear = now()->year;
        $validYears = range($startYear, $currentYear);

        if ($selectedYear == $startYear && $selectedYear == $currentYear) {
            $startMonth = $user->created_at->month;
            $maxMonth = now()->month;
        } elseif ($selectedYear == $startYear) {
            $startMonth = $user->created_at->month;
            $maxMonth = 12;
        } elseif ($selectedYear == $currentYear) {
            $startMonth = 1;
            $maxMonth = now()->month;
        } else {
            $startMonth = 1;
            $maxMonth = 12;
        }

        $validMonths = [];
        for ($m = $startMonth; $m <= $maxMonth; $m++) {
            $validMonths[] = [
                'value' => $m,
                'name' => date('F', mktime(0, 0, 0, $m, 1))
            ];
        }

        return response()->json([
            'status' => true,
            'data' => [
                'monthlySummaries' => $monthlySummaries,
                'validMonths' => $validMonths,
                'validYears' => $validYears,
                'selectedYear' => $selectedYear,
                'selectedMonth' => $selectedMonth
            ]
        ]);
    }

    public function generate(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

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

        return response()->json([
            'status' => true,
            'message' => 'Monthly summary generated successfully.',
            'data' => [
                'summary' => $summaryText,
                'year' => $year,
                'month' => $month
            ]
        ]);
    }
}
