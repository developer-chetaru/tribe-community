<?php

namespace App\Http\Controllers;

use App\Models\TribeometerAnswer;
use App\Models\TribeometerIndividualUserStatus;
use App\Models\TribeometerOption;
use App\Models\TribeometerQuestion;
use App\Models\TribeometerResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TribeometerController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $questions = TribeometerQuestion::where('status', 'Active')
            ->with('value')
            ->orderBy('value_id')
            ->orderBy('id')
            ->get();

        $options = TribeometerOption::where('status', 'Active')
            ->orderBy('value_score')
            ->get();

        $userAnswers = TribeometerAnswer::where('userId', $user->id)
            ->where('status', 'Active')
            ->get()
            ->keyBy('questionId');

        $completionStatus = TribeometerIndividualUserStatus::where('userId', $user->id)
            ->where('completeStatus', 1)
            ->latest('date')
            ->first();

        return view('tribeometer.index', compact('questions', 'options', 'userAnswers', 'completionStatus'));
    }

    public function submit(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'answers' => 'required|array',
            'answers.*.questionId' => 'required|exists:tribeometer_questions,id',
            'answers.*.optionId' => 'required|exists:tribeometer_options,id',
        ]);

        DB::beginTransaction();
        try {
            TribeometerAnswer::where('userId', $user->id)->delete();

            $valueScores = [];
            foreach ($request->answers as $answerData) {
                $question = TribeometerQuestion::with('value')->find($answerData['questionId']);
                $option = TribeometerOption::find($answerData['optionId']);
                if (!$question || !$option) {
                    continue;
                }

                TribeometerAnswer::create([
                    'userId' => $user->id,
                    'orgId' => $user->orgId,
                    'questionId' => $answerData['questionId'],
                    'optionId' => $answerData['optionId'],
                    'status' => 'Active',
                ]);

                if ($question->value) {
                    $valueId = $question->value->id;
                    if (!isset($valueScores[$valueId])) {
                        $valueScores[$valueId] = [
                            'total_score' => 0,
                            'question_count' => 0,
                        ];
                    }
                    $valueScores[$valueId]['total_score'] += $option->value_score;
                    $valueScores[$valueId]['question_count'] += 1;
                }
            }

            TribeometerResult::where('userId', $user->id)->delete();

            $assessmentDate = now();
            foreach ($valueScores as $valueId => $data) {
                $maxPossibleScore = $data['question_count'] * 3;
                $percentage = $maxPossibleScore > 0 ? ($data['total_score'] / $maxPossibleScore) * 100 : 0;
                $averageScore = $data['question_count'] > 0 ? ($data['total_score'] / $data['question_count']) : 0;

                TribeometerResult::create([
                    'userId' => $user->id,
                    'orgId' => $user->orgId,
                    'value_id' => $valueId,
                    'score' => round($percentage, 2),
                    'average_score' => round($averageScore, 2),
                    'total_responses' => $data['question_count'],
                    'calculated_at' => $assessmentDate,
                ]);
            }

            TribeometerIndividualUserStatus::updateOrCreate(
                [
                    'userId' => $user->id,
                    'orgId' => $user->orgId,
                ],
                [
                    'date' => $assessmentDate,
                    'completeStatus' => 1,
                ]
            );

            DB::commit();

            return redirect()->route('tribeometer.results')
                ->with('success', 'Tribeometer assessment submitted successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to submit answers: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function results()
    {
        $user = Auth::user();

        $completionStatus = TribeometerIndividualUserStatus::where('userId', $user->id)
            ->where('completeStatus', 1)
            ->latest('date')
            ->first();

        if (!$completionStatus) {
            return redirect()->route('tribeometer.index')
                ->with('info', 'Please complete the Tribeometer assessment first.');
        }

        $results = TribeometerResult::where('userId', $user->id)
            ->with('value')
            ->orderBy('value_id')
            ->get();

        $valueScores = [];
        foreach ($results as $result) {
            if ($result->value) {
                $valueScores[$result->value->title] = [
                    'score' => round($result->score, 2),
                    'average_score' => round($result->average_score, 2),
                    'total_responses' => $result->total_responses,
                    'description' => $result->value->description ?? '',
                ];
            }
        }
        arsort($valueScores);

        return view('tribeometer.results', compact('completionStatus', 'valueScores', 'results'));
    }
}

