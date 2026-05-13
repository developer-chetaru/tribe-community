<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MotivationOption;
use App\Models\MotivationQuestion;
use App\Models\MotivationResult;
use App\Models\MotivationValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminMotivationController extends Controller
{
    public function questionsIndex(Request $request)
    {
        $query = MotivationQuestion::with('options.motivationValue');

        if ($request->has('search') && $request->search) {
            $query->where('question', 'like', '%' . $request->search . '%');
        }

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        $questions = $query->orderBy('order')->orderBy('id', 'desc')->paginate(20);
        return view('admin.supercharging.motivation.questions', compact('questions'));
    }

    public function questionsCreate()
    {
        $motivationValues = MotivationValue::where('status', 'Active')->orderBy('order')->get();
        return view('admin.supercharging.motivation.addQuestion', compact('motivationValues'));
    }

    public function questionsStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string',
            'order' => 'nullable|integer',
            'status' => 'required|in:Active,Inactive',
            'options' => 'required|array|min:2|max:2',
            'options.*.text' => 'required|string',
            'options.*.motivation_value_id' => 'required|exists:motivation_values,id',
            'options.*.label' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $question = MotivationQuestion::create([
                'question' => $request->question,
                'order' => $request->order ?? 0,
                'status' => $request->status,
            ]);

            foreach ($request->options as $index => $option) {
                MotivationOption::create([
                    'question_id' => $question->id,
                    'motivation_value_id' => $option['motivation_value_id'],
                    'option_text' => $option['text'],
                    'option_label' => $option['label'] ?? ('Option ' . ($index === 0 ? 'A' : 'B')),
                    'order' => $index + 1,
                    'status' => 'Active',
                ]);
            }

            DB::commit();
            return redirect()->route('admin.motivation.questions.index')->with('success', 'Question created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to create question: ' . $e->getMessage())->withInput();
        }
    }

    public function questionsEdit($id)
    {
        $question = MotivationQuestion::with('options.motivationValue')->findOrFail($id);
        $motivationValues = MotivationValue::where('status', 'Active')->orderBy('order')->get();

        return view('admin.supercharging.motivation.editQuestion', compact('question', 'motivationValues'));
    }

    public function questionsUpdate(Request $request, $id)
    {
        $question = MotivationQuestion::with('options')->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'question' => 'required|string',
            'order' => 'nullable|integer',
            'status' => 'required|in:Active,Inactive',
            'options' => 'required|array|min:2|max:2',
            'options.*.text' => 'required|string',
            'options.*.motivation_value_id' => 'required|exists:motivation_values,id',
            'options.*.label' => 'nullable|string',
            'options.*.id' => 'nullable|exists:motivation_options,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $question->update([
                'question' => $request->question,
                'order' => $request->order ?? 0,
                'status' => $request->status,
            ]);

            $existingOptionIds = $question->options->pluck('id')->toArray();

            foreach ($request->options as $index => $optionData) {
                if (isset($optionData['id']) && in_array($optionData['id'], $existingOptionIds)) {
                    MotivationOption::where('id', $optionData['id'])->update([
                        'option_text' => $optionData['text'],
                        'motivation_value_id' => $optionData['motivation_value_id'],
                        'option_label' => $optionData['label'] ?? ('Option ' . ($index === 0 ? 'A' : 'B')),
                        'order' => $index + 1,
                    ]);
                } else {
                    MotivationOption::create([
                        'question_id' => $question->id,
                        'motivation_value_id' => $optionData['motivation_value_id'],
                        'option_text' => $optionData['text'],
                        'option_label' => $optionData['label'] ?? ('Option ' . ($index === 0 ? 'A' : 'B')),
                        'order' => $index + 1,
                        'status' => 'Active',
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('admin.motivation.questions.index')->with('success', 'Question updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to update question: ' . $e->getMessage())->withInput();
        }
    }

    public function questionsDestroy($id)
    {
        $question = MotivationQuestion::findOrFail($id);

        DB::beginTransaction();
        try {
            MotivationOption::where('question_id', $question->id)->delete();
            $question->delete();
            DB::commit();
            return redirect()->route('admin.motivation.questions.index')->with('success', 'Question deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to delete question: ' . $e->getMessage());
        }
    }

    public function valuesIndex()
    {
        $values = MotivationValue::orderBy('order')->get();
        return view('admin.supercharging.motivation.values', compact('values'));
    }

    public function valuesEdit($id)
    {
        $value = MotivationValue::findOrFail($id);
        return view('admin.supercharging.motivation.editValue', compact('value'));
    }

    public function valuesUpdate(Request $request, $id)
    {
        $value = MotivationValue::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'description' => 'nullable|string',
            'characteristics' => 'nullable|string',
            'management_strategy' => 'nullable|string',
            'order' => 'nullable|integer',
            'status' => 'required|in:Active,Inactive',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $value->update($request->only(['title', 'description', 'characteristics', 'management_strategy', 'order', 'status']));

        return redirect()->route('admin.motivation.values.index')->with('success', 'Motivation value updated successfully.');
    }

    public function resultsIndex(Request $request)
    {
        $query = MotivationResult::with(['user', 'motivationValue']);

        if ($request->has('orgId') && $request->orgId) {
            $query->where('orgId', $request->orgId);
        }

        if ($request->has('userId') && $request->userId) {
            $query->where('userId', $request->userId);
        }

        $results = $query->orderBy('assessment_date', 'desc')
            ->orderBy('rank', 'asc')
            ->orderBy('id', 'desc')
            ->paginate(20);

        return view('admin.supercharging.motivation.results', compact('results'));
    }
}

