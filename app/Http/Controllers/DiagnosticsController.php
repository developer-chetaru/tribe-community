<?php

namespace App\Http\Controllers;

use App\Models\DiagnosticAnswer;
use App\Models\DiagnosticIndividualUserStatus;
use App\Models\DiagnosticQuestion;
use App\Models\DiagnosticQuestionOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DiagnosticsController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $questions = DiagnosticQuestion::where('status', 'Active')
            ->with('category')
            ->orderBy('id')
            ->get();

        $options = DiagnosticQuestionOption::where('status', 'Active')
            ->orderBy('option_rating')
            ->get();

        $userAnswers = DiagnosticAnswer::where('userId', $user->id)
            ->where('status', 'Active')
            ->get()
            ->keyBy('questionId');

        $completionStatus = DiagnosticIndividualUserStatus::where('userId', $user->id)
            ->where('completeStatus', 1)
            ->latest('date')
            ->first();

        return view('diagnostics.index', compact('questions', 'options', 'userAnswers', 'completionStatus'));
    }

    public function submit(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'answers' => 'required|array',
            'answers.*.questionId' => 'required|exists:diagnostic_questions,id',
            'answers.*.optionId' => 'required|exists:diagnostic_question_options,id',
        ]);

        DB::beginTransaction();
        try {
            $existingStatus = DiagnosticIndividualUserStatus::where('userId', $user->id)
                ->where(function ($query) use ($user) {
                    if ($user->orgId) {
                        $query->where('orgId', $user->orgId);
                    } else {
                        $query->whereNull('orgId');
                    }
                })
                ->where('completeStatus', 1)
                ->first();

            if (!$existingStatus) {
                DiagnosticAnswer::where('userId', $user->id)->delete();

                foreach ($request->answers as $answerData) {
                    DiagnosticAnswer::create([
                        'userId' => $user->id,
                        'orgId' => $user->orgId,
                        'questionId' => $answerData['questionId'],
                        'optionId' => $answerData['optionId'],
                        'status' => 'Active',
                    ]);
                }

                DiagnosticIndividualUserStatus::create([
                    'userId' => $user->id,
                    'orgId' => $user->orgId,
                    'date' => now(),
                    'completeStatus' => 1,
                ]);

                $user->EIScore = ($user->EIScore ?? 0) + 100;
                $user->save();
            } else {
                foreach ($request->answers as $answerData) {
                    DiagnosticAnswer::updateOrCreate(
                        [
                            'userId' => $user->id,
                            'questionId' => $answerData['questionId'],
                        ],
                        [
                            'orgId' => $user->orgId,
                            'optionId' => $answerData['optionId'],
                            'status' => 'Active',
                        ]
                    );
                }
            }

            DB::commit();

            return redirect()->route('diagnostics.results')
                ->with('success', 'Diagnostic assessment submitted successfully!');
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

        $completionStatus = DiagnosticIndividualUserStatus::where('userId', $user->id)
            ->where('completeStatus', 1)
            ->latest('date')
            ->first();

        if (!$completionStatus) {
            return redirect()->route('diagnostics.index')
                ->with('info', 'Please complete the diagnostic assessment first.');
        }

        $answers = DiagnosticAnswer::where('userId', $user->id)
            ->where('status', 'Active')
            ->with(['question.category', 'option'])
            ->get()
            ->groupBy(function ($answer) {
                return $answer->question->category->title ?? 'Uncategorized';
            });

        $categoryScores = [];
        foreach ($answers as $categoryTitle => $categoryAnswers) {
            $totalRating = 0;
            $questionCount = $categoryAnswers->unique('questionId')->count();

            foreach ($categoryAnswers as $answer) {
                if ($answer->option) {
                    $totalRating += $answer->option->option_rating;
                }
            }

            $maxPossibleRating = $questionCount * 4;
            $percentage = $maxPossibleRating > 0 ? ($totalRating / $maxPossibleRating) * 100 : 0;

            $categoryScores[$categoryTitle] = [
                'score' => $questionCount > 0 ? round($totalRating / $questionCount, 2) : 0,
                'percentage' => round($percentage, 2),
                'questionCount' => $questionCount,
            ];
        }

        return view('diagnostics.results', compact('completionStatus', 'categoryScores', 'answers'));
    }
}

