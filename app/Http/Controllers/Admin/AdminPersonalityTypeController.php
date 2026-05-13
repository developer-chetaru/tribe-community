<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Models\PersonalityTypeOption;
use App\Models\PersonalityTypeQuestion;
use App\Models\PersonalityTypeResult;
use App\Models\PersonalityTypeValue;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminPersonalityTypeController extends Controller
{
    public function questionsIndex(Request $request)
    {
        $query = PersonalityTypeQuestion::with('personalityTypeValue');

        if ($request->has('search') && $request->search) {
            $query->where('question', 'like', '%' . $request->search . '%');
        }

        if ($request->has('category_id') && $request->category_id) {
            $query->where('personality_type_value_id', $request->category_id);
        }

        $questions = $query->orderBy('order')->orderBy('id', 'desc')->paginate(20);
        $categories = PersonalityTypeValue::where('status', 'Active')->orderBy('order')->get();

        return view('admin.personalityType.personalityTypeQuestions', compact('questions', 'categories'));
    }

    public function questionsCreate()
    {
        $categories = PersonalityTypeValue::where('status', 'Active')->orderBy('order')->get();
        return view('admin.personalityType.addPersonalityTypeQuestion', compact('categories'));
    }

    public function questionsStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string',
            'category' => 'nullable|string|max:10',
            'personality_type_value_id' => 'nullable|exists:personality_type_values,id',
            'summary_trait' => 'nullable|string|max:255',
            'order' => 'nullable|integer',
            'status' => 'required|in:Active,Inactive',
            'options' => 'required|array|min:2',
            'options.*.text' => 'required|string',
            'options.*.score_value' => 'required|integer',
            'options.*.personality_type_value_id' => 'nullable|exists:personality_type_values,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $question = PersonalityTypeQuestion::create([
                'question' => $request->question,
                'category' => $request->category,
                'personality_type_value_id' => $request->personality_type_value_id,
                'summary_trait' => $request->summary_trait,
                'order' => $request->order ?? 0,
                'status' => $request->status,
            ]);

            foreach ($request->options as $index => $optionData) {
                PersonalityTypeOption::create([
                    'question_id' => $question->id,
                    'option_text' => $optionData['text'],
                    'personality_type_value_id' => $optionData['personality_type_value_id'] ?? null,
                    'score_value' => $optionData['score_value'],
                    'order' => $index,
                    'status' => 'Active',
                ]);
            }

            DB::commit();

            return redirect()->route('admin.personality-type.questions.index')
                ->with('success', 'Question created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to create question: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function questionsEdit($id)
    {
        $question = PersonalityTypeQuestion::with('options')->findOrFail($id);
        $categories = PersonalityTypeValue::where('status', 'Active')->orderBy('order')->get();

        return view('admin.personalityType.editPersonalityTypeQuestion', compact('question', 'categories'));
    }

    public function questionsUpdate(Request $request, $id)
    {
        $question = PersonalityTypeQuestion::with('options')->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'question' => 'required|string',
            'category' => 'nullable|string|max:10',
            'personality_type_value_id' => 'nullable|exists:personality_type_values,id',
            'summary_trait' => 'nullable|string|max:255',
            'order' => 'nullable|integer',
            'status' => 'required|in:Active,Inactive',
            'options' => 'required|array|min:2',
            'options.*.id' => 'nullable|exists:personality_type_options,id',
            'options.*.text' => 'required|string',
            'options.*.score_value' => 'required|integer',
            'options.*.personality_type_value_id' => 'nullable|exists:personality_type_values,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $question->update([
                'question' => $request->question,
                'category' => $request->category,
                'personality_type_value_id' => $request->personality_type_value_id,
                'summary_trait' => $request->summary_trait,
                'order' => $request->order ?? 0,
                'status' => $request->status,
            ]);

            $existingOptionIds = $question->options->pluck('id')->toArray();
            foreach ($request->options as $index => $optionData) {
                if (isset($optionData['id']) && in_array($optionData['id'], $existingOptionIds)) {
                    PersonalityTypeOption::where('id', $optionData['id'])->update([
                        'option_text' => $optionData['text'],
                        'personality_type_value_id' => $optionData['personality_type_value_id'] ?? null,
                        'score_value' => $optionData['score_value'],
                        'order' => $index,
                    ]);
                } else {
                    PersonalityTypeOption::create([
                        'question_id' => $question->id,
                        'option_text' => $optionData['text'],
                        'personality_type_value_id' => $optionData['personality_type_value_id'] ?? null,
                        'score_value' => $optionData['score_value'],
                        'order' => $index,
                        'status' => 'Active',
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('admin.personality-type.questions.index')
                ->with('success', 'Question updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to update question: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function questionsDestroy($id)
    {
        $question = PersonalityTypeQuestion::findOrFail($id);

        DB::beginTransaction();
        try {
            PersonalityTypeOption::where('question_id', $question->id)->delete();
            $question->delete();
            DB::commit();

            return redirect()->route('admin.personality-type.questions.index')
                ->with('success', 'Question deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to delete question: ' . $e->getMessage());
        }
    }

    public function optionsIndex(Request $request)
    {
        $query = PersonalityTypeOption::with(['question', 'personalityTypeValue']);

        if ($request->has('question_id') && $request->question_id) {
            $query->where('question_id', $request->question_id);
        }

        $options = $query->orderBy('question_id')->orderBy('order')->paginate(20);
        $questions = PersonalityTypeQuestion::where('status', 'Active')->orderBy('order')->get();

        return view('admin.personalityType.personalityTypeOptions', compact('options', 'questions'));
    }

    public function valuesIndex()
    {
        $values = PersonalityTypeValue::orderBy('order')->get();
        return view('admin.personalityType.personalityTypeValues', compact('values'));
    }

    public function valuesEdit($id)
    {
        $value = PersonalityTypeValue::findOrFail($id);
        return view('admin.personalityType.editPersonalityTypeValue', compact('value'));
    }

    public function valuesUpdate(Request $request, $id)
    {
        $value = PersonalityTypeValue::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'characteristics' => 'nullable|string',
            'real_world_applications' => 'nullable|string',
            'team_collaboration_tips' => 'nullable|string',
            'order' => 'nullable|integer',
            'status' => 'required|in:Active,Inactive',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $value->update($validator->validated());

        return redirect()->route('admin.personality-type.values.index')
            ->with('success', 'Personality type value updated successfully.');
    }

    public function resultsIndex(Request $request)
    {
        $query = PersonalityTypeResult::with(['user', 'organisation', 'personalityTypeValue']);

        if ($request->has('user_id') && $request->user_id) {
            $query->where('userId', $request->user_id);
        }

        if ($request->has('org_id') && $request->org_id) {
            $query->where('orgId', $request->org_id);
        }

        $latestAssessments = DB::table('personality_type_results')
            ->select('userId', DB::raw('MAX(assessment_date) as latest_date'))
            ->groupBy('userId')
            ->get()
            ->pluck('latest_date', 'userId');

        $results = $query->whereIn('assessment_date', $latestAssessments->values())
            ->orderBy('userId')
            ->get()
            ->groupBy('userId');

        $organisations = Organisation::all();
        $users = User::when($request->org_id, fn ($q) => $q->where('orgId', $request->org_id))->get();

        return view('admin.personalityType.personalityTypeResults', compact('results', 'organisations', 'users'));
    }

    public function exportResults(Request $request)
    {
        $query = PersonalityTypeResult::with(['user', 'organisation', 'personalityTypeValue']);

        if ($request->has('org_id') && $request->org_id) {
            $query->where('orgId', $request->org_id);
        }

        $results = $query->get();

        $filename = 'personality_type_results_' . date('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($results) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['User', 'Organisation', 'Dimension', 'Score', 'Percentage', 'Assessment Date']);

            foreach ($results as $result) {
                fputcsv($file, [
                    $result->user->name ?? '',
                    $result->organisation->name ?? '',
                    $result->personalityTypeValue->title ?? $result->dimension_key,
                    $result->score,
                    $result->percentage,
                    optional($result->assessment_date)->format('Y-m-d') ?? (string) $result->assessment_date,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

