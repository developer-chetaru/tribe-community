<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiagnosticAnswer;
use App\Models\DiagnosticIndividualUserStatus;
use App\Models\DiagnosticQuestion;
use App\Models\DiagnosticQuestionOption;
use App\Models\DiagnosticQuestionsCategory;
use App\Models\DiagnosticReportGraph;
use App\Models\DiagnosticReportSubgraph;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminDiagnosticController extends Controller
{
    public function index(Request $request)
    {
        $questions = DiagnosticQuestion::with('category')
            ->orderBy('id', 'desc')
            ->paginate(10);

        return view('admin.diagnostic.listDiagnosticQuestions', compact('questions'));
    }

    public function update(Request $request, $id)
    {
        $id = base64_decode($id);

        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:225',
            'measure' => 'nullable|string|max:255',
            'categoryId' => 'required|exists:diagnostic_questions_category,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $question = DiagnosticQuestion::findOrFail($id);
        $question->update([
            'question' => $request->question,
            'measure' => $request->measure,
            'category_id' => $request->categoryId,
        ]);

        return redirect()->route('admin.diagnostic.index')->with('success', 'Question updated successfully.');
    }

    public function getDiagnosticOptionList()
    {
        $options = DiagnosticQuestionOption::orderBy('option_rating')->get();
        return view('admin.diagnostic.listDiagnosticQuestionOption', compact('options'));
    }

    public function upadateDiagnosticOption(Request $request)
    {
        $optionId = base64_decode($request->optionId);

        $validator = Validator::make($request->all(), [
            'option' => 'required|string|max:225',
            'optionRating' => 'required|integer|min:0|max:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $existingOption = DiagnosticQuestionOption::where('option_rating', $request->optionRating)
            ->where('id', '!=', $optionId)
            ->first();

        if ($existingOption) {
            return response()->json([
                'status' => false,
                'message' => 'This rating is already assigned to another option',
            ], 422);
        }

        $option = DiagnosticQuestionOption::findOrFail($optionId);
        $option->update([
            'option_name' => $request->option,
            'option_rating' => $request->optionRating,
        ]);

        return response()->json(['status' => true, 'message' => 'Option updated successfully']);
    }

    public function getDiagnosticValuesList()
    {
        $categories = DiagnosticQuestionsCategory::orderBy('id')->get();
        return view('admin.diagnostic.listDiagnosticCategoryValuesList', compact('categories'));
    }

    public function updateDiagnosticCategoryValues(Request $request)
    {
        $titleId = base64_decode($request->titleId);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $category = DiagnosticQuestionsCategory::findOrFail($titleId);
        $category->update(['title' => $request->title]);

        return response()->json(['status' => true, 'message' => 'Category updated successfully']);
    }

    public function addDiagnosticOption(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'optionName' => 'required|string|max:225',
            'optionRating' => 'required|integer|min:0|max:4|unique:diagnostic_question_options,option_rating',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DiagnosticQuestionOption::create([
            'option_name' => $request->optionName,
            'option_rating' => $request->optionRating,
            'status' => 'Active',
        ]);

        return response()->json(['status' => true, 'message' => 'Option added successfully']);
    }

    public function addDiagnosticQuestion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:225',
            'measure' => 'nullable|string|max:255',
            'categoryId' => 'required|exists:diagnostic_questions_category,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DiagnosticQuestion::create([
            'question' => $request->question,
            'measure' => $request->measure,
            'category_id' => $request->categoryId,
            'status' => 'Active',
        ]);

        return response()->json(['status' => true, 'message' => 'Question added successfully']);
    }

    public function addDiagnosticCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DiagnosticQuestionsCategory::create(['title' => $request->category]);
        return response()->json(['status' => true, 'message' => 'Category added successfully']);
    }

    public function deleteDiagnosticQuestion(Request $request)
    {
        $questionId = $request->questionId;

        DB::beginTransaction();
        try {
            DiagnosticAnswer::where('questionId', $questionId)->delete();
            DiagnosticReportSubgraph::where('quesId', $questionId)->delete();
            DiagnosticQuestion::where('id', $questionId)->delete();

            DB::commit();
            return response()->json(['status' => true, 'message' => 'Question deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'Failed to delete question: ' . $e->getMessage()], 500);
        }
    }

    public function deleteDiagnosticOption(Request $request)
    {
        $optionId = $request->optionId;

        DB::beginTransaction();
        try {
            DiagnosticAnswer::where('optionId', $optionId)->update(['optionId' => null]);
            DiagnosticQuestionOption::where('id', $optionId)->delete();

            DB::commit();
            return response()->json(['status' => true, 'message' => 'Option deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'Failed to delete option: ' . $e->getMessage()], 500);
        }
    }

    public function deleteDiagnosticCategory(Request $request)
    {
        $cateId = $request->cateId;

        DB::beginTransaction();
        try {
            DiagnosticQuestion::where('category_id', $cateId)->update(['category_id' => null]);
            DiagnosticReportGraph::where('categoryId', $cateId)->delete();
            DiagnosticReportSubgraph::where('categoryId', $cateId)->delete();
            DiagnosticQuestionsCategory::where('id', $cateId)->delete();

            DB::commit();
            return response()->json(['status' => true, 'message' => 'Category deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'Failed to delete category: ' . $e->getMessage()], 500);
        }
    }

    public function resultsIndex(Request $request)
    {
        $query = DiagnosticIndividualUserStatus::with(['user', 'organisation'])
            ->where('completeStatus', 1);

        if ($request->has('org_id') && $request->org_id) {
            $query->where('orgId', $request->org_id);
        }

        if ($request->has('user_id') && $request->user_id) {
            $query->where('userId', $request->user_id);
        }

        $completionStatuses = $query->latest('date')->get();

        $results = [];
        foreach ($completionStatuses as $status) {
            $user = $status->user;
            if (!$user) {
                continue;
            }

            $answers = DiagnosticAnswer::where('userId', $user->id)
                ->where('status', 'Active')
                ->with(['question.category', 'option'])
                ->get();

            if ($answers->isEmpty()) {
                continue;
            }

            $categoryScores = [];
            $answersByCategory = $answers->groupBy(fn ($answer) => $answer->question->category->title ?? 'Uncategorized');

            foreach ($answersByCategory as $categoryTitle => $categoryAnswers) {
                $totalRating = 0;
                $questionCount = $categoryAnswers->unique('questionId')->count();

                foreach ($categoryAnswers as $answer) {
                    if ($answer->option) {
                        $totalRating += $answer->option->option_rating;
                    }
                }

                $maxPossibleRating = $questionCount * 4;
                $percentage = $maxPossibleRating > 0 ? ($totalRating / $maxPossibleRating) * 100 : 0;
                $averageScore = $questionCount > 0 ? ($totalRating / $questionCount) : 0;

                $categoryScores[$categoryTitle] = [
                    'score' => round($averageScore, 2),
                    'percentage' => round($percentage, 2),
                    'questionCount' => $questionCount,
                ];
            }

            $results[] = [
                'user' => $user,
                'status' => $status,
                'categoryScores' => $categoryScores,
            ];
        }

        $organisations = Organisation::all();
        $users = User::when($request->org_id, fn ($q) => $q->where('orgId', $request->org_id))->get();

        return view('admin.diagnostic.results', compact('results', 'organisations', 'users'));
    }
}

