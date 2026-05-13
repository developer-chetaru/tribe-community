<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonalityTypeIndividualUserStatus;
use App\Models\PersonalityTypeOption;
use App\Models\PersonalityTypeQuestion;
use App\Models\PersonalityTypeResult;
use App\Models\PersonalityTypeValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ApiPersonalitytypeController extends Controller
{
    public function getQuestions(Request $request)
    {
        try {
            $questions = PersonalityTypeQuestion::where('status', 'Active')
                ->with(['options', 'personalityTypeValue'])
                ->orderBy('order')
                ->get();

            $formattedQuestions = $questions->map(function ($question) {
                $options = $question->options->map(function ($option) {
                    return [
                        'id' => $option->id,
                        'text' => $option->option_text,
                        'score_value' => $option->score_value,
                        'dimension_key' => $option->personalityTypeValue->dimension_key ?? null,
                    ];
                });

                return [
                    'id' => $question->id,
                    'question' => $question->question,
                    'category' => $question->category,
                    'summary_trait' => $question->summary_trait,
                    'dimension_key' => $question->personalityTypeValue->dimension_key ?? null,
                    'dimension_title' => $question->personalityTypeValue->title ?? null,
                    'order' => $question->order,
                    'options' => $options,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Questions retrieved successfully',
                'data' => $formattedQuestions,
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
                'answers.*.questionId' => 'required|exists:personality_type_questions,id',
                'answers.*.optionId' => 'required|exists:personality_type_options,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $userId = $request->userId;
            $orgId = $request->orgId;

            DB::beginTransaction();
            try {
                $dimensionScores = [];

                foreach ($request->answers as $answerData) {
                    $option = PersonalityTypeOption::with('personalityTypeValue')
                        ->find($answerData['optionId']);

                    if ($option && $option->personalityTypeValue) {
                        $dimensionKey = $option->personalityTypeValue->dimension_key;
                        if (!isset($dimensionScores[$dimensionKey])) {
                            $dimensionScores[$dimensionKey] = [
                                'value_id' => $option->personalityTypeValue->id,
                                'score' => 0,
                            ];
                        }
                        $dimensionScores[$dimensionKey]['score'] += $option->score_value;
                    }
                }

                PersonalityTypeResult::where('userId', $userId)->delete();
                $totalScore = array_sum(array_column($dimensionScores, 'score'));

                $assessmentDate = now()->toDateString();
                foreach ($dimensionScores as $dimensionKey => $data) {
                    $percentage = $totalScore > 0 ? ($data['score'] / $totalScore) * 100 : 0;

                    PersonalityTypeResult::create([
                        'userId' => $userId,
                        'orgId' => $orgId,
                        'personality_type_value_id' => $data['value_id'],
                        'dimension_key' => $dimensionKey,
                        'score' => $data['score'],
                        'percentage' => round($percentage, 2),
                        'assessment_date' => $assessmentDate,
                    ]);
                }

                $statusWhere = ['userid' => $userId];
                $statusWhere['orgId'] = $orgId ?: null;

                PersonalityTypeIndividualUserStatus::updateOrCreate(
                    $statusWhere,
                    [
                        'date' => $assessmentDate,
                        'completeStatus' => true,
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

            $latestDate = PersonalityTypeResult::where('userId', $userId)->max('assessment_date');

            $results = PersonalityTypeResult::where('userId', $userId)
                ->where('assessment_date', $latestDate)
                ->with('personalityTypeValue')
                ->get();

            $formattedResults = $results->map(function ($result) {
                return [
                    'dimension_key' => $result->dimension_key,
                    'title' => $result->personalityTypeValue->title ?? $result->dimension_key,
                    'score' => $result->score,
                    'percentage' => $result->percentage,
                    'description' => $result->personalityTypeValue->description ?? '',
                    'characteristics' => $result->personalityTypeValue->characteristics ?? '',
                ];
            });

            $status = PersonalityTypeIndividualUserStatus::where('userid', $userId)
                ->where('completeStatus', true)
                ->latest('date')
                ->first();

            return response()->json([
                'status' => true,
                'message' => 'Results retrieved successfully',
                'data' => [
                    'results' => $formattedResults,
                    'assessment_date' => $latestDate,
                    'is_completed' => (bool) $status,
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
            $values = PersonalityTypeValue::where('status', 'Active')
                ->orderBy('order')
                ->get();

            $formattedValues = $values->map(function ($value) {
                return [
                    'dimension_key' => $value->dimension_key,
                    'title' => $value->title,
                    'description' => $value->description,
                    'characteristics' => $value->characteristics,
                    'real_world_applications' => $value->real_world_applications,
                    'team_collaboration_tips' => $value->team_collaboration_tips,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Personality type values retrieved successfully',
                'data' => $formattedValues,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve personality type values: ' . $e->getMessage(),
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

            $latestDate = PersonalityTypeResult::where('userId', $request->userId)->max('assessment_date');
            $results = PersonalityTypeResult::where('userId', $request->userId)
                ->where('assessment_date', $latestDate)
                ->with('personalityTypeValue')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'User assessment data retrieved successfully',
                'data' => [
                    'assessment_date' => $latestDate,
                    'results' => $results->map(function ($result) {
                        return [
                            'dimension_key' => $result->dimension_key,
                            'score' => $result->score,
                            'percentage' => $result->percentage,
                        ];
                    }),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve user answers: ' . $e->getMessage(),
            ], 500);
        }
    }
}

