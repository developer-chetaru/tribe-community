<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MotivationAnswer;
use App\Models\MotivationIndividualUserStatus;
use App\Models\MotivationQuestion;
use App\Models\MotivationResult;
use App\Models\MotivationValue;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ApiMotivationController extends Controller
{
    public function getQuestions(Request $request)
    {
        try {
            $questions = MotivationQuestion::where('status', 'Active')
                ->with(['options.motivationValue'])
                ->orderBy('order')
                ->get();

            $formattedQuestions = $questions->map(function ($question) {
                $options = $question->options->map(function ($option) {
                    return [
                        'id' => $option->id,
                        'text' => $option->option_text,
                        'label' => $option->option_label,
                        'motivation_value_id' => $option->motivation_value_id,
                        'value_key' => $option->motivationValue->value_key ?? null,
                        'value_title' => $option->motivationValue->title ?? null,
                    ];
                });

                return [
                    'id' => $question->id,
                    'question' => $question->question,
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
                'answers.*.question_id' => 'required|exists:motivation_questions,id',
                'answers.*.ratings' => 'required|array',
                'answers.*.ratings.*.option_id' => 'required|exists:motivation_options,id',
                'answers.*.ratings.*.rating' => 'required|integer|min:0|max:5',
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
            $assessmentDate = Carbon::today();

            DB::beginTransaction();
            try {
                MotivationAnswer::where('userId', $userId)
                    ->where('assessment_date', $assessmentDate)
                    ->delete();

                foreach ($request->answers as $answerData) {
                    foreach ($answerData['ratings'] as $ratingData) {
                        $option = \App\Models\MotivationOption::with('motivationValue')->find($ratingData['option_id']);
                        if ($option) {
                            MotivationAnswer::create([
                                'userId' => $userId,
                                'orgId' => $orgId,
                                'question_id' => $answerData['question_id'],
                                'option_id' => $ratingData['option_id'],
                                'motivation_value_id' => $option->motivation_value_id,
                                'rating' => $ratingData['rating'],
                                'assessment_date' => $assessmentDate,
                            ]);
                        }
                    }
                }

                $this->calculateResults($userId, $orgId, $assessmentDate);

                MotivationIndividualUserStatus::updateOrCreate(
                    [
                        'userid' => $userId,
                        'orgId' => $orgId ?? 0,
                        'date' => $assessmentDate,
                    ],
                    ['completeStatus' => true]
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

    private function calculateResults($userId, $orgId, $assessmentDate)
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

    public function getResults($userId)
    {
        try {
            $latestDate = MotivationResult::where('userId', $userId)->max('assessment_date');

            $results = collect();
            if ($latestDate) {
                $results = MotivationResult::where('userId', $userId)
                    ->where('assessment_date', $latestDate)
                    ->with('motivationValue')
                    ->orderBy('rank')
                    ->get()
                    ->map(function ($result) {
                        return [
                            'value_key' => $result->value_key,
                            'value_title' => $result->motivationValue->title ?? null,
                            'score' => $result->score,
                            'rank' => $result->rank,
                            'assessment_date' => $result->assessment_date,
                        ];
                    });
            }

            return response()->json([
                'status' => true,
                'message' => 'Results retrieved successfully',
                'data' => [
                    'results' => $results,
                    'assessment_date' => $latestDate,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve results: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getValues()
    {
        try {
            $values = MotivationValue::where('status', 'Active')
                ->orderBy('order')
                ->get()
                ->map(function ($value) {
                    return [
                        'id' => $value->id,
                        'value_key' => $value->value_key,
                        'title' => $value->title,
                        'description' => $value->description,
                        'characteristics' => $value->characteristics,
                        'management_strategy' => $value->management_strategy,
                    ];
                });

            return response()->json([
                'status' => true,
                'message' => 'Values retrieved successfully',
                'data' => $values,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve values: ' . $e->getMessage(),
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

            $latestDate = MotivationAnswer::where('userId', $request->userId)->max('assessment_date');
            $answers = collect();

            if ($latestDate) {
                $answers = MotivationAnswer::where('userId', $request->userId)
                    ->where('assessment_date', $latestDate)
                    ->with(['question', 'option.motivationValue'])
                    ->get()
                    ->groupBy('question_id')
                    ->map(function ($questionAnswers, $questionId) {
                        $ratings = $questionAnswers->map(function ($answer) {
                            return [
                                'option_id' => $answer->option_id,
                                'rating' => (int) $answer->rating,
                                'value_key' => $answer->option->motivationValue->value_key ?? null,
                            ];
                        })->toArray();

                        return [
                            'question_id' => (int) $questionId,
                            'ratings' => $ratings,
                            'assessment_date' => $questionAnswers->first()->assessment_date,
                        ];
                    })
                    ->values();
            }

            return response()->json([
                'status' => true,
                'message' => 'User answers retrieved successfully',
                'data' => [
                    'answers' => $answers,
                    'assessment_date' => $latestDate,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve user answers: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateAnswers(Request $request)
    {
        return $this->submitAnswers($request);
    }
}

