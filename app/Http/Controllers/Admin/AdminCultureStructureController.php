<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CultureStructureOption;
use App\Models\CultureStructureQuestion;
use App\Models\CultureStructureResult;
use App\Models\CultureStructureType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminCultureStructureController extends Controller
{
    public function questionsIndex(Request $request)
    {
        $query = CultureStructureQuestion::with('options.cultureType');

        if ($request->has('search') && $request->search) {
            $query->where('question', 'like', '%' . $request->search . '%');
        }

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        $questions = $query->orderBy('order')->orderBy('id', 'desc')->paginate(20);

        return view('admin.supercharging.culture-structure.questions', compact('questions'));
    }

    public function questionsCreate()
    {
        $cultureTypes = CultureStructureType::where('status', 'Active')->orderBy('order')->get();
        return view('admin.supercharging.culture-structure.addQuestion', compact('cultureTypes'));
    }

    public function questionsStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string',
            'order' => 'nullable|integer',
            'status' => 'required|in:Active,Inactive',
            'options' => 'required|array|min:4',
            'options.*.text' => 'required|string',
            'options.*.culture_type_id' => 'required|exists:culture_structure_types,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $question = CultureStructureQuestion::create([
                'question' => $request->question,
                'order' => $request->order ?? 0,
                'status' => $request->status,
            ]);

            foreach ($request->options as $index => $option) {
                CultureStructureOption::create([
                    'question_id' => $question->id,
                    'culture_type_id' => $option['culture_type_id'],
                    'option_text' => $option['text'],
                    'order' => $index + 1,
                    'status' => 'Active',
                ]);
            }

            DB::commit();

            return redirect()->route('admin.culture-structure.questions.index')
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
        $question = CultureStructureQuestion::with('options.cultureType')->findOrFail($id);
        $cultureTypes = CultureStructureType::where('status', 'Active')->orderBy('order')->get();

        return view('admin.supercharging.culture-structure.editQuestion', compact('question', 'cultureTypes'));
    }

    public function questionsUpdate(Request $request, $id)
    {
        $question = CultureStructureQuestion::with('options')->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'question' => 'required|string',
            'order' => 'nullable|integer',
            'status' => 'required|in:Active,Inactive',
            'options' => 'required|array|min:4',
            'options.*.text' => 'required|string',
            'options.*.culture_type_id' => 'required|exists:culture_structure_types,id',
            'options.*.id' => 'nullable|exists:culture_structure_options,id',
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
                    CultureStructureOption::where('id', $optionData['id'])->update([
                        'option_text' => $optionData['text'],
                        'culture_type_id' => $optionData['culture_type_id'],
                        'order' => $index + 1,
                    ]);
                } else {
                    CultureStructureOption::create([
                        'question_id' => $question->id,
                        'culture_type_id' => $optionData['culture_type_id'],
                        'option_text' => $optionData['text'],
                        'order' => $index + 1,
                        'status' => 'Active',
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('admin.culture-structure.questions.index')
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
        $question = CultureStructureQuestion::findOrFail($id);

        DB::beginTransaction();
        try {
            CultureStructureOption::where('question_id', $question->id)->delete();
            $question->delete();
            DB::commit();

            return redirect()->route('admin.culture-structure.questions.index')
                ->with('success', 'Question deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to delete question: ' . $e->getMessage());
        }
    }

    public function typesIndex()
    {
        $types = CultureStructureType::orderBy('order')->get();
        return view('admin.supercharging.culture-structure.types', compact('types'));
    }

    public function typesEdit($id)
    {
        $type = CultureStructureType::findOrFail($id);
        return view('admin.supercharging.culture-structure.editType', compact('type'));
    }

    public function typesUpdate(Request $request, $id)
    {
        $type = CultureStructureType::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'summary' => 'nullable|string',
            'description' => 'nullable|string',
            'characteristics' => 'nullable|string',
            'order' => 'nullable|integer',
            'status' => 'required|in:Active,Inactive',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $type->update($request->only(['title', 'summary', 'description', 'characteristics', 'order', 'status']));

        return redirect()->route('admin.culture-structure.types.index')
            ->with('success', 'Culture type updated successfully.');
    }

    public function resultsIndex(Request $request)
    {
        $query = CultureStructureResult::with(['user', 'cultureType']);

        if ($request->has('orgId') && $request->orgId) {
            $query->where('orgId', $request->orgId);
        }

        if ($request->has('userId') && $request->userId) {
            $query->where('userId', $request->userId);
        }

        $results = $query->orderBy('assessment_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20);

        return view('admin.supercharging.culture-structure.results', compact('results'));
    }
}

