<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiagnosticAnswer;
use App\Models\DiagnosticIndividualUserStatus;
use App\Models\DiagnosticQuestion;
use App\Models\DiagnosticQuestionOption;
use App\Models\TribeometerIndividualUserStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ApiDiagnosticController extends Controller
{
    public function getDiagnosticQuestionList(Request $request)
    {
        try {
            $questions = DiagnosticQuestion::where('status', 'Active')
                ->with('category')
                ->orderBy('id')
                ->get();

            $options = DiagnosticQuestionOption::where('status', 'Active')
                ->orderBy('option_rating')
                ->get();

            $formattedQuestions = $questions->map(function ($question) use ($options) {
                return [
                    'questionId' => $question->id,
                    'question' => $question->question,
                    'options' => $options->map(function ($option) {
                        return [
                            'optionId' => $option->id,
                            'optionName' => $option->option_name,
                        ];
                    }),
                ];
            });

            return response()->json([
                'code' => 200,
                'status' => true,
                'service_name' => 'Diagnostic-questions-list',
                'message' => 'Diagnostic questionslist',
                'data' => $formattedQuestions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => false,
                'service_name' => 'Diagnostic-questions-list',
                'message' => 'Failed to retrieve questions: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    public function addDiagnosticAnswers(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'code' => 401,
                    'status' => false,
                    'service_name' => 'Add-diagnostic-answer',
                    'message' => 'Unauthorized',
                    'data' => [],
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'answer' => 'required|array',
                'answer.*.questionId' => 'required|exists:diagnostic_questions,id',
                'answer.*.optionId' => 'required|exists:diagnostic_question_options,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 422,
                    'status' => false,
                    'service_name' => 'Add-diagnostic-answer',
                    'message' => 'Validation failed',
                    'data' => $validator->errors(),
                ], 422);
            }

            $userId = $user->id;
            $orgId = $user->orgId;

            $existingStatus = DiagnosticIndividualUserStatus::where('userId', $userId)
                ->where(function ($query) use ($orgId) {
                    if ($orgId) {
                        $query->where('orgId', $orgId);
                    } else {
                        $query->whereNull('orgId');
                    }
                })
                ->where('completeStatus', 1)
                ->first();

            if ($existingStatus) {
                return response()->json([
                    'code' => 200,
                    'status' => true,
                    'service_name' => 'Add-diagnostic-answer',
                    'message' => 'Diagnostic answer added successfully',
                    'data' => [],
                ]);
            }

            DB::beginTransaction();
            try {
                foreach ($request->answer as $answerData) {
                    DiagnosticAnswer::create([
                        'userId' => $userId,
                        'orgId' => $orgId,
                        'questionId' => $answerData['questionId'],
                        'optionId' => $answerData['optionId'],
                        'status' => 'Active',
                    ]);
                }

                DiagnosticIndividualUserStatus::create([
                    'userId' => $userId,
                    'orgId' => $orgId,
                    'date' => now(),
                    'completeStatus' => 1,
                ]);

                $user->EIScore = ($user->EIScore ?? 0) + 100;
                $user->save();

                DB::commit();

                return response()->json([
                    'code' => 200,
                    'status' => true,
                    'service_name' => 'Add-diagnostic-answer',
                    'message' => 'Diagnostic answer added successfully',
                    'data' => [],
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => false,
                'service_name' => 'Add-diagnostic-answer',
                'message' => 'Failed to add answers: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    public function getDiagnosticCompletedAnswers(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'code' => 401,
                    'status' => false,
                    'service_name' => 'Diagnostic-question-answers',
                    'message' => 'Unauthorized',
                    'data' => [],
                ], 401);
            }

            $answers = DiagnosticAnswer::where('userId', $user->id)
                ->where('status', 'Active')
                ->with(['question', 'option'])
                ->get();

            if ($answers->isEmpty()) {
                return response()->json([
                    'code' => 400,
                    'status' => false,
                    'service_name' => 'Diagnostic-question-answers',
                    'message' => 'Diagnostic answers not filled in',
                    'data' => [],
                ], 400);
            }

            $options = DiagnosticQuestionOption::where('status', 'Active')
                ->orderBy('option_rating')
                ->get();

            $formattedData = $answers->groupBy('questionId')->map(function ($questionAnswers, $questionId) use ($options) {
                $firstAnswer = $questionAnswers->first();
                $selectedOptionId = $firstAnswer->optionId;

                return [
                    'questionId' => $questionId,
                    'question' => $firstAnswer->question->question,
                    'answerId' => $firstAnswer->id,
                    'options' => $options->map(function ($option) use ($selectedOptionId, $firstAnswer) {
                        return [
                            'optionId' => $option->id,
                            'optionName' => $option->option_name,
                            'isChecked' => $option->id == $selectedOptionId,
                            'answerId' => $option->id == $selectedOptionId ? $firstAnswer->id : null,
                        ];
                    }),
                ];
            })->values();

            return response()->json([
                'code' => 200,
                'status' => true,
                'service_name' => 'Diagnostic-question-answers',
                'message' => 'Diagnostic question answers',
                'data' => $formattedData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => false,
                'service_name' => 'Diagnostic-question-answers',
                'message' => 'Failed to retrieve answers: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    public function updateDiagnosticAnswers(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'code' => 401,
                    'status' => false,
                    'service_name' => 'Update-diagnostic-answer',
                    'message' => 'Unauthorized',
                    'data' => [],
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'answer' => 'required|array',
                'answer.*.answerId' => 'required|exists:diagnostic_answers,id',
                'answer.*.optionId' => 'required|exists:diagnostic_question_options,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 422,
                    'status' => false,
                    'service_name' => 'Update-diagnostic-answer',
                    'message' => 'Validation failed',
                    'data' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();
            try {
                foreach ($request->answer as $answerData) {
                    $answer = DiagnosticAnswer::where('id', $answerData['answerId'])
                        ->where('userId', $user->id)
                        ->first();

                    if ($answer) {
                        $answer->optionId = $answerData['optionId'];
                        $answer->save();
                    }
                }

                DB::commit();

                return response()->json([
                    'code' => 200,
                    'status' => true,
                    'service_name' => 'Update-diagnostic-answer',
                    'message' => 'Diagnostic answer updated successfully',
                    'data' => [],
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => false,
                'service_name' => 'Update-diagnostic-answer',
                'message' => 'Failed to update answers: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    public function isDiagnosticTribeometerAnswerDone(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'code' => 401,
                    'status' => false,
                    'service_name' => 'Is-Diagnostic-Tribeometer-Answer-Done',
                    'message' => 'Unauthorized',
                    'data' => [],
                ], 401);
            }

            $diagnosticStatus = DiagnosticIndividualUserStatus::where('userId', $user->id)
                ->where('completeStatus', 1)
                ->exists();

            $tribeometerStatus = TribeometerIndividualUserStatus::where('userId', $user->id)
                ->where('completeStatus', 1)
                ->exists();

            return response()->json([
                'code' => 200,
                'status' => true,
                'service_name' => 'Is-Diagnostic-Tribeometer-Answer-Done',
                'message' => '',
                'data' => [
                    'isDiagnosticAnsDone' => $diagnosticStatus,
                    'isTribeometerAnsDone' => $tribeometerStatus,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => false,
                'service_name' => 'Is-Diagnostic-Tribeometer-Answer-Done',
                'message' => 'Failed to check status: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    public function getDiagnosticReport(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'orgId' => 'required|exists:organisations,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 422,
                    'status' => false,
                    'service_name' => 'diagnostic-report',
                    'message' => 'Validation failed',
                    'data' => $validator->errors(),
                ], 422);
            }

            $orgId = $request->orgId;

            $userIds = DiagnosticAnswer::where('orgId', $orgId)
                ->distinct()
                ->pluck('userId');

            if ($userIds->isEmpty()) {
                return response()->json([
                    'code' => 200,
                    'status' => true,
                    'service_name' => 'diagnostic-report',
                    'message' => '',
                    'data' => [],
                ]);
            }

            $categories = \App\Models\DiagnosticQuestionsCategory::all();
            $optionCount = DiagnosticQuestionOption::where('status', 'Active')->count();
            $userCount = $userIds->count();

            $reportData = [];
            foreach ($categories as $category) {
                $questions = DiagnosticQuestion::where('category_id', $category->id)
                    ->where('status', 'Active')
                    ->get();

                if ($questions->isEmpty()) {
                    continue;
                }

                $questionCount = $questions->count();
                $totalSum = 0;

                foreach ($questions as $question) {
                    $answers = DiagnosticAnswer::where('questionId', $question->id)
                        ->whereIn('userId', $userIds)
                        ->where('status', 'Active')
                        ->with('option')
                        ->get();

                    foreach ($answers as $answer) {
                        if ($answer->option) {
                            $totalSum += $answer->option->option_rating;
                        }
                    }
                }

                if ($userCount > 0 && $questionCount > 0 && $optionCount > 0) {
                    $score = ($totalSum / $userCount) / ($questionCount * $optionCount);
                    $percentage = $score * 100;

                    $reportData[] = [
                        'title' => $category->title,
                        'categoryId' => $category->id,
                        'score' => number_format($score, 2),
                        'percentage' => number_format($percentage, 2),
                    ];
                }
            }

            return response()->json([
                'code' => 200,
                'status' => true,
                'service_name' => 'diagnostic-report',
                'message' => '',
                'data' => $reportData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => false,
                'service_name' => 'diagnostic-report',
                'message' => 'Failed to generate report: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }
}

