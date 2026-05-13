<?php

namespace App\Http\Controllers;

use App\Models\CultureStructureAnswer;
use App\Models\CultureStructureIndividualUserStatus;
use App\Models\CultureStructureQuestion;
use App\Models\CultureStructureResult;
use App\Models\CultureStructureType;
use App\Models\MotivationAnswer;
use App\Models\MotivationIndividualUserStatus;
use App\Models\MotivationQuestion;
use App\Models\MotivationResult;
use App\Models\MotivationValue;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SuperchargingController extends Controller
{
    public function cultureStructure()
    {
        $user = Auth::user();

        $questions = CultureStructureQuestion::where('status', 'Active')
            ->with(['options.cultureType'])
            ->orderBy('order')
            ->get();

        $userAnswers = CultureStructureAnswer::where('userId', $user->id)
            ->with(['question', 'option.cultureType'])
            ->get()
            ->groupBy('question_id');

        $completionStatus = CultureStructureIndividualUserStatus::where('userid', $user->id)
            ->where('completeStatus', true)
            ->latest('date')
            ->first();

        return view('supercharging.culture-structure', compact('questions', 'userAnswers', 'completionStatus'));
    }

    public function submitCultureStructure(Request $request)
    {
        $user = Auth::user();
        $orgId = $user->orgId ?? null;
        $assessmentDate = Carbon::today();

        DB::beginTransaction();
        try {
            CultureStructureAnswer::where('userId', $user->id)
                ->where('assessment_date', $assessmentDate)
                ->delete();

            foreach ($request->answers as $questionId => $optionId) {
                $option = \App\Models\CultureStructureOption::with('cultureType')->find($optionId);
                if ($option) {
                    CultureStructureAnswer::create([
                        'userId' => $user->id,
                        'orgId' => $orgId,
                        'question_id' => $questionId,
                        'option_id' => $optionId,
                        'culture_type_id' => $option->culture_type_id,
                        'assessment_date' => $assessmentDate,
                    ]);
                }
            }

            $this->calculateCultureStructureResults($user->id, $orgId, $assessmentDate);

            CultureStructureIndividualUserStatus::updateOrCreate(
                [
                    'userid' => $user->id,
                    'orgId' => $orgId ?? 0,
                    'date' => $assessmentDate,
                ],
                ['completeStatus' => true]
            );

            DB::commit();

            return redirect()->route('supercharging.culture-structure.results')
                ->with('success', 'Assessment completed successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to submit assessment: ' . $e->getMessage())
                ->withInput();
        }
    }

    private function calculateCultureStructureResults($userId, $orgId, $assessmentDate)
    {
        CultureStructureResult::where('userId', $userId)
            ->where('assessment_date', $assessmentDate)
            ->delete();

        $answers = CultureStructureAnswer::where('userId', $userId)
            ->where('assessment_date', $assessmentDate)
            ->with('cultureType')
            ->get();

        $typeCounts = $answers->groupBy('culture_type_id')->map->count();
        $totalAnswers = $answers->count();

        foreach ($typeCounts as $typeId => $count) {
            $cultureType = CultureStructureType::find($typeId);
            $percentage = $totalAnswers > 0 ? ($count / $totalAnswers) * 100 : 0;

            CultureStructureResult::create([
                'userId' => $userId,
                'orgId' => $orgId,
                'culture_type_id' => $typeId,
                'type_key' => $cultureType->type_key,
                'percentage' => round($percentage, 2),
                'score' => $count,
                'assessment_date' => $assessmentDate,
            ]);
        }
    }

    public function cultureStructureResults()
    {
        $user = Auth::user();

        $latestDate = CultureStructureResult::where('userId', $user->id)
            ->max('assessment_date');

        $results = collect();
        $latestDateCarbon = null;

        if ($latestDate) {
            $latestDateCarbon = Carbon::parse($latestDate);
            $results = CultureStructureResult::where('userId', $user->id)
                ->where('assessment_date', $latestDate)
                ->with('cultureType')
                ->orderByDesc('percentage')
                ->get();
        }

        $cultureTypes = CultureStructureType::where('status', 'Active')
            ->orderBy('order')
            ->get();

        return view('supercharging.culture-structure-results', compact('results', 'cultureTypes', 'latestDateCarbon'));
    }

    public function motivation()
    {
        $user = Auth::user();

        $questions = MotivationQuestion::where('status', 'Active')
            ->with(['options.motivationValue'])
            ->orderBy('order')
            ->get();

        $userAnswers = MotivationAnswer::where('userId', $user->id)
            ->with(['question', 'option.motivationValue'])
            ->get()
            ->groupBy('question_id');

        $completionStatus = MotivationIndividualUserStatus::where('userid', $user->id)
            ->where('completeStatus', true)
            ->latest('date')
            ->first();

        return view('supercharging.motivation', compact('questions', 'userAnswers', 'completionStatus'));
    }

    public function submitMotivation(Request $request)
    {
        $user = Auth::user();
        $orgId = $user->orgId ?? null;
        $assessmentDate = Carbon::today();

        DB::beginTransaction();
        try {
            MotivationAnswer::where('userId', $user->id)
                ->where('assessment_date', $assessmentDate)
                ->delete();

            foreach ($request->answers as $questionId => $optionRatings) {
                foreach ($optionRatings as $optionId => $rating) {
                    $option = \App\Models\MotivationOption::with('motivationValue')->find($optionId);
                    if ($option && $rating >= 0 && $rating <= 5) {
                        MotivationAnswer::create([
                            'userId' => $user->id,
                            'orgId' => $orgId,
                            'question_id' => $questionId,
                            'option_id' => $optionId,
                            'motivation_value_id' => $option->motivation_value_id,
                            'rating' => $rating,
                            'assessment_date' => $assessmentDate,
                        ]);
                    }
                }
            }

            $this->calculateMotivationResults($user->id, $orgId, $assessmentDate);

            MotivationIndividualUserStatus::updateOrCreate(
                [
                    'userid' => $user->id,
                    'orgId' => $orgId ?? 0,
                    'date' => $assessmentDate,
                ],
                ['completeStatus' => true]
            );

            DB::commit();

            return redirect()->route('supercharging.motivation.results')
                ->with('success', 'Assessment completed successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to submit assessment: ' . $e->getMessage())
                ->withInput();
        }
    }

    private function calculateMotivationResults($userId, $orgId, $assessmentDate)
    {
        MotivationResult::where('userId', $userId)
            ->where('assessment_date', $assessmentDate)
            ->delete();

        $answers = MotivationAnswer::where('userId', $userId)
            ->where('assessment_date', $assessmentDate)
            ->with('motivationValue')
            ->get();

        $valueScores = $answers->groupBy('motivation_value_id')
            ->map(fn ($group) => $group->sum('rating'));

        $sortedScores = $valueScores->sortDesc();
        $rank = 1;

        foreach ($sortedScores as $valueId => $score) {
            $motivationValue = MotivationValue::find($valueId);
            if ($motivationValue) {
                MotivationResult::create([
                    'userId' => $userId,
                    'orgId' => $orgId,
                    'motivation_value_id' => $valueId,
                    'value_key' => $motivationValue->value_key,
                    'score' => $score,
                    'rank' => $rank++,
                    'assessment_date' => $assessmentDate,
                ]);
            }
        }
    }

    public function motivationResults()
    {
        $user = Auth::user();

        $latestDate = MotivationResult::where('userId', $user->id)
            ->max('assessment_date');

        $results = collect();
        $latestDateCarbon = null;

        if ($latestDate) {
            $latestDateCarbon = Carbon::parse($latestDate);
            $results = MotivationResult::where('userId', $user->id)
                ->where('assessment_date', $latestDate)
                ->with('motivationValue')
                ->orderBy('rank')
                ->get();
        }

        $motivationValues = MotivationValue::where('status', 'Active')
            ->orderBy('order')
            ->get();

        return view('supercharging.motivation-results', compact('results', 'motivationValues', 'latestDateCarbon'));
    }
}

