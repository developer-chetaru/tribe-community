<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CotAnswer;
use App\Models\CotQuestion;
use App\Models\CotRoleMapOption;
use App\Models\CotTeamRoleDescription;
use App\Models\CotTeamRoleIndividualUserStatus;
use App\Models\CotTeamRoleResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ApiCOTController extends Controller
{
    public function getQuestions(Request $request)
    {
        try {
            $questions = CotQuestion::where('status', 'Active')
                ->with(['roleMapOptions.roleDescription'])
                ->orderBy('order')
                ->get();

            $formattedQuestions = $questions->map(function ($question) {
                $options = $question->roleMapOptions->map(function ($option) {
                    return [
                        'id' => $option->id,
                        'text' => $option->maper,
                        'role_key' => $option->roleDescription->role_key ?? null,
                        'role_title' => $option->roleDescription->title ?? null,
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
                'answers.*.questionId' => 'required|exists:cot_questions,id',
                'answers.*.options' => 'required|array|min:1',
                'answers.*.options.*.optionId' => 'required|exists:cot_role_map_options,id',
                'answers.*.options.*.points' => 'required|integer|min:0|max:10',
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
                CotAnswer::where('userId', $userId)->delete();

                foreach ($request->answers as $answerData) {
                    foreach ($answerData['options'] as $optionData) {
                        if ($optionData['points'] > 0) {
                            CotRoleMapOption::find($optionData['optionId']);

                            CotAnswer::create([
                                'userId' => $userId,
                                'orgId' => $orgId,
                                'questionId' => $answerData['questionId'],
                                'optionId' => $optionData['optionId'],
                                'cot_role_map_option_id' => $optionData['optionId'],
                                'answer' => $optionData['points'],
                                'status' => 1,
                            ]);
                        }
                    }
                }

                $this->calculateTeamRoleResults($userId, $orgId);

                $statusWhere = ['userid' => $userId];
                $statusWhere['orgId'] = $orgId ?: null;

                CotTeamRoleIndividualUserStatus::updateOrCreate(
                    $statusWhere,
                    [
                        'date' => now()->toDateString(),
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

    private function calculateTeamRoleResults($userId, $orgId)
    {
        $answers = CotAnswer::where('userId', $userId)
            ->with('roleMapOption.roleDescription')
            ->get();

        $roleScores = [];
        foreach ($answers as $answer) {
            if ($answer->roleMapOption && $answer->roleMapOption->roleDescription) {
                $roleKey = $answer->roleMapOption->roleDescription->role_key;
                $roleScores[$roleKey] = ($roleScores[$roleKey] ?? 0) + (int) $answer->answer;
            }
        }

        CotTeamRoleResult::where('userId', $userId)->delete();

        arsort($roleScores);
        $rank = 1;
        $assessmentDate = now()->toDateString();

        foreach ($roleScores as $roleKey => $score) {
            CotTeamRoleResult::create([
                'userId' => $userId,
                'orgId' => $orgId,
                'role_key' => $roleKey,
                'score' => $score,
                'preference_rank' => $rank++,
                'assessment_date' => $assessmentDate,
            ]);
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

            $latestDate = CotTeamRoleResult::where('userId', $userId)->max('assessment_date');

            $results = CotTeamRoleResult::where('userId', $userId)
                ->where('assessment_date', $latestDate)
                ->with('roleDescription')
                ->orderBy('preference_rank')
                ->get();

            $formattedResults = $results->map(function ($result) {
                return [
                    'role_key' => $result->role_key,
                    'role_title' => $result->roleDescription->title ?? $result->role_key,
                    'value_focus' => $result->roleDescription->value_focus ?? '',
                    'score' => $result->score,
                    'preference_rank' => $result->preference_rank,
                ];
            });

            $status = CotTeamRoleIndividualUserStatus::where('userid', $userId)
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

    public function getRoleDescriptions(Request $request)
    {
        try {
            $descriptions = CotTeamRoleDescription::where('status', 'Active')
                ->orderBy('order')
                ->get();

            $formatted = $descriptions->map(function ($desc) {
                return [
                    'role_key' => $desc->role_key,
                    'title' => $desc->title,
                    'value_focus' => $desc->value_focus,
                    'description' => $desc->description,
                    'focus' => $desc->focus,
                    'standard_questions' => $desc->standard_questions,
                    'disruption' => $desc->disruption,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Role descriptions retrieved successfully',
                'data' => $formatted,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve role descriptions: ' . $e->getMessage(),
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

            $answers = CotAnswer::where('userId', $request->userId)
                ->with(['question', 'roleMapOption.roleDescription'])
                ->get()
                ->groupBy('questionId');

            $formattedAnswers = [];
            foreach ($answers as $questionId => $questionAnswers) {
                $options = [];
                foreach ($questionAnswers as $answer) {
                    $options[] = [
                        'optionId' => $answer->optionId,
                        'points' => (int) $answer->answer,
                        'role_key' => $answer->roleMapOption->roleDescription->role_key ?? null,
                    ];
                }
                $formattedAnswers[] = [
                    'questionId' => $questionId,
                    'options' => $options,
                ];
            }

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
}

