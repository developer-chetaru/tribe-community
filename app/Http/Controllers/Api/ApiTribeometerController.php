<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TribeometerAnswer;
use App\Models\TribeometerIndividualUserStatus;
use App\Models\TribeometerOption;
use App\Models\TribeometerQuestion;
use App\Models\TribeometerResult;
use App\Models\TribeometerValue;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ApiTribeometerController extends Controller
{
    public function getQuestions(Request $request)
    {
        try {
            $questions = TribeometerQuestion::where('status', 'Active')
                ->with(['value'])
                ->orderBy('value_id')
                ->orderBy('id')
                ->get();

            $options = TribeometerOption::where('status', 'Active')
                ->orderBy('value_score')
                ->get();

            $formattedOptions = $options->map(function ($option) {
                return [
                    'id' => $option->id,
                    'text' => $option->option_name,
                    'value_score' => $option->value_score,
                ];
            });

            $formattedQuestions = $questions->map(function ($question) use ($formattedOptions) {
                return [
                    'id' => $question->id,
                    'question' => $question->question,
                    'measure' => $question->measure,
                    'value_id' => $question->value_id,
                    'value_key' => $question->value->value_key ?? null,
                    'value_title' => $question->value->title ?? null,
                    'options' => $formattedOptions,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Questions retrieved successfully',
                'data' => [
                    'questions' => $formattedQuestions,
                    'options' => $formattedOptions,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve questions: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function submitAnswers(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'userId' => 'required|exists:users,id',
                'orgId' => 'nullable|exists:organisations,id',
                'answers' => 'required|array',
                'answers.*.questionId' => 'required|exists:tribeometer_questions,id',
                'answers.*.optionId' => 'required|exists:tribeometer_options,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $userId = $request->userId;
            $orgId = $request->orgId ?? (User::find($userId)->orgId ?? null);

            DB::beginTransaction();
            try {
                TribeometerAnswer::where('userId', $userId)->delete();

                $valueScores = [];
                foreach ($request->answers as $answerData) {
                    $question = TribeometerQuestion::with('value')->find($answerData['questionId']);
                    $option = TribeometerOption::find($answerData['optionId']);
                    if (!$question || !$option) {
                        continue;
                    }

                    TribeometerAnswer::create([
                        'userId' => $userId,
                        'orgId' => $orgId,
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

                TribeometerResult::where('userId', $userId)->delete();

                $assessmentDate = now();
                foreach ($valueScores as $valueId => $data) {
                    $maxPossibleScore = $data['question_count'] * 3;
                    $percentage = $maxPossibleScore > 0 ? ($data['total_score'] / $maxPossibleScore) * 100 : 0;
                    $averageScore = $data['question_count'] > 0 ? ($data['total_score'] / $data['question_count']) : 0;

                    TribeometerResult::create([
                        'userId' => $userId,
                        'orgId' => $orgId,
                        'value_id' => $valueId,
                        'score' => round($percentage, 2),
                        'average_score' => round($averageScore, 2),
                        'total_responses' => $data['question_count'],
                        'calculated_at' => $assessmentDate,
                    ]);
                }

                TribeometerIndividualUserStatus::updateOrCreate(
                    [
                        'userId' => $userId,
                        'orgId' => $orgId,
                    ],
                    [
                        'date' => $assessmentDate,
                        'completeStatus' => 1,
                    ]
                );

                DB::commit();

                return response()->json([
                    'status' => true,
                    'message' => 'Answers submitted successfully',
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to submit answers: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getResults(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'userId' => 'required|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $userId = $request->userId;

            $results = TribeometerResult::where('userId', $userId)
                ->with('value')
                ->orderBy('value_id')
                ->get();

            $formattedResults = $results->map(function ($result) {
                return [
                    'value_id' => $result->value_id,
                    'value_key' => $result->value->value_key ?? null,
                    'value_title' => $result->value->title ?? null,
                    'score' => round($result->score, 2),
                    'average_score' => round($result->average_score, 2),
                    'total_responses' => $result->total_responses,
                    'description' => $result->value->description ?? '',
                ];
            });

            $sortedResults = $formattedResults->sortByDesc('score')->values();

            $status = TribeometerIndividualUserStatus::where('userId', $userId)
                ->where('completeStatus', 1)
                ->latest('date')
                ->first();

            return response()->json([
                'status' => true,
                'message' => 'Results retrieved successfully',
                'data' => [
                    'results' => $sortedResults,
                    'is_completed' => $status ? true : false,
                    'completed_date' => $status ? $status->date : null,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve results: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getValues(Request $request)
    {
        try {
            $values = TribeometerValue::where('status', 'Active')
                ->orderBy('order')
                ->get();

            $formattedValues = $values->map(function ($value) {
                return [
                    'id' => $value->id,
                    'value_key' => $value->value_key,
                    'title' => $value->title,
                    'description' => $value->description,
                    'order' => $value->order,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Tribeometer values retrieved successfully',
                'data' => $formattedValues,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve Tribeometer values: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getUserAnswers(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'userId' => 'required|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $answers = TribeometerAnswer::where('userId', $request->userId)
                ->where('status', 'Active')
                ->with(['question.value', 'option'])
                ->get();

            $formattedAnswers = $answers->map(function ($answer) {
                return [
                    'questionId' => $answer->questionId,
                    'question' => $answer->question->question ?? '',
                    'value_id' => $answer->question->value_id ?? null,
                    'value_key' => $answer->question->value->value_key ?? null,
                    'optionId' => $answer->optionId,
                    'option_text' => $answer->option->option_name ?? '',
                    'value_score' => $answer->option->value_score ?? null,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'User answers retrieved successfully',
                'data' => $formattedAnswers,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve user answers: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function checkCompletion(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'userId' => 'required|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $status = TribeometerIndividualUserStatus::where('userId', $request->userId)
                ->where('completeStatus', 1)
                ->latest('date')
                ->first();

            return response()->json([
                'status' => true,
                'message' => 'Completion status retrieved successfully',
                'data' => [
                    'is_completed' => $status ? true : false,
                    'completed_date' => $status ? $status->date : null,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to check completion: ' . $e->getMessage(),
            ], 500);
        }
    }
}

