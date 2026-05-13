<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CultureStructureAnswer;
use App\Models\CultureStructureIndividualUserStatus;
use App\Models\CultureStructureQuestion;
use App\Models\CultureStructureResult;
use App\Models\CultureStructureType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ApiCultureStructureController extends Controller
{
    public function getQuestions(Request $request)
    {
        try {
            $questions = CultureStructureQuestion::where('status', 'Active')
                ->with(['options.cultureType'])
                ->orderBy('order')
                ->get();

            $formattedQuestions = $questions->map(function ($question) {
                $options = $question->options->map(function ($option) {
                    return [
                        'id' => $option->id,
                        'text' => $option->option_text,
                        'culture_type_id' => $option->culture_type_id,
                        'type_key' => $option->cultureType->type_key ?? null,
                        'type_title' => $option->cultureType->title ?? null,
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
                'answers.*.question_id' => 'required|exists:culture_structure_questions,id',
                'answers.*.option_id' => 'required|exists:culture_structure_options,id',
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
                CultureStructureAnswer::where('userId', $userId)
                    ->where('assessment_date', $assessmentDate)
                    ->delete();

                foreach ($request->answers as $answerData) {
                    $option = \App\Models\CultureStructureOption::with('cultureType')->find($answerData['option_id']);
                    if ($option) {
                        CultureStructureAnswer::create([
                            'userId' => $userId,
                            'orgId' => $orgId,
                            'question_id' => $answerData['question_id'],
                            'option_id' => $answerData['option_id'],
                            'culture_type_id' => $option->culture_type_id,
                            'assessment_date' => $assessmentDate,
                        ]);
                    }
                }

                $this->calculateResults($userId, $orgId, $assessmentDate);

                CultureStructureIndividualUserStatus::updateOrCreate(
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

    public function getResults($userId)
    {
        try {
            $latestDate = CultureStructureResult::where('userId', $userId)->max('assessment_date');

            $results = collect();
            if ($latestDate) {
                $results = CultureStructureResult::where('userId', $userId)
                    ->where('assessment_date', $latestDate)
                    ->with('cultureType')
                    ->orderByDesc('percentage')
                    ->get()
                    ->map(function ($result) {
                        return [
                            'type_key' => $result->type_key,
                            'type_title' => $result->cultureType->title ?? null,
                            'percentage' => $result->percentage,
                            'score' => $result->score,
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

    public function getTypes()
    {
        try {
            $types = CultureStructureType::where('status', 'Active')
                ->orderBy('order')
                ->get()
                ->map(function ($type) {
                    return [
                        'id' => $type->id,
                        'type_key' => $type->type_key,
                        'title' => $type->title,
                        'summary' => $type->summary,
                        'description' => $type->description,
                        'characteristics' => $type->characteristics,
                    ];
                });

            return response()->json([
                'status' => true,
                'message' => 'Types retrieved successfully',
                'data' => $types,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve types: ' . $e->getMessage(),
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

            $latestDate = CultureStructureAnswer::where('userId', $request->userId)->max('assessment_date');
            $answers = collect();

            if ($latestDate) {
                $answers = CultureStructureAnswer::where('userId', $request->userId)
                    ->where('assessment_date', $latestDate)
                    ->with(['question', 'option.cultureType'])
                    ->get()
                    ->groupBy('question_id')
                    ->map(function ($questionAnswers, $questionId) {
                        return [
                            'question_id' => (int) $questionId,
                            'option_id' => $questionAnswers->first()->option_id,
                            'culture_type_id' => $questionAnswers->first()->culture_type_id,
                            'type_key' => $questionAnswers->first()->option->cultureType->type_key ?? null,
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

