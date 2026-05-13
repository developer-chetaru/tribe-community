<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CotQuestion;
use App\Models\CotRoleMapOption;
use App\Models\CotTeamRoleDescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminCOTquestionController extends Controller
{
    public function index(Request $request)
    {
        $query = CotQuestion::query();

        if ($request->has('search') && $request->search) {
            $query->where('question', 'like', '%' . $request->search . '%');
        }

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        $questions = $query->orderBy('order')->orderBy('id', 'desc')->paginate(20);

        return view('admin.COT.question.listCOTquestions', compact('questions'));
    }

    public function create()
    {
        $roleDescriptions = CotTeamRoleDescription::where('status', 'Active')
            ->orderBy('order')
            ->get();

        return view('admin.COT.question.addCOTquestion', compact('roleDescriptions'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string',
            'order' => 'nullable|integer',
            'status' => 'required|in:Active,Inactive',
            'options' => 'required|array|min:8|max:8',
            'options.*.text' => 'required|string',
            'options.*.role_key' => 'required|string|exists:cot_team_role_descriptions,role_key',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $question = CotQuestion::create([
                'question' => $request->question,
                'order' => $request->order ?? 0,
                'status' => $request->status,
            ]);

            foreach ($request->options as $option) {
                $roleDescription = CotTeamRoleDescription::where('role_key', $option['role_key'])->first();

                CotRoleMapOption::create([
                    'maper' => $option['text'],
                    'maper_key' => strtolower(str_replace(' ', '_', $option['text'])),
                    'categoryId' => $question->id,
                    'role_description_id' => $roleDescription->id ?? null,
                    'short_description' => $option['text'],
                    'long_description' => $option['text'],
                    'status' => 'Active',
                ]);
            }

            DB::commit();

            return redirect()->route('admin.cot.questions.index')
                ->with('success', 'Question created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to create question: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function edit($id)
    {
        $question = CotQuestion::with('roleMapOptions.roleDescription')->findOrFail($id);
        $roleDescriptions = CotTeamRoleDescription::where('status', 'Active')
            ->orderBy('order')
            ->get();

        return view('admin.COT.question.editCOTquestion', compact('question', 'roleDescriptions'));
    }

    public function update(Request $request, $id)
    {
        $question = CotQuestion::with('roleMapOptions')->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'question' => 'required|string',
            'order' => 'nullable|integer',
            'status' => 'required|in:Active,Inactive',
            'options' => 'required|array|min:8|max:8',
            'options.*.text' => 'required|string',
            'options.*.role_key' => 'required|string|exists:cot_team_role_descriptions,role_key',
            'options.*.id' => 'nullable|exists:cot_role_map_options,id',
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

            $existingOptionIds = $question->roleMapOptions->pluck('id')->toArray();

            foreach ($request->options as $optionData) {
                $roleDescription = CotTeamRoleDescription::where('role_key', $optionData['role_key'])->first();

                if (isset($optionData['id']) && in_array($optionData['id'], $existingOptionIds)) {
                    CotRoleMapOption::where('id', $optionData['id'])->update([
                        'maper' => $optionData['text'],
                        'maper_key' => strtolower(str_replace(' ', '_', $optionData['text'])),
                        'role_description_id' => $roleDescription->id ?? null,
                        'short_description' => $optionData['text'],
                        'long_description' => $optionData['text'],
                    ]);
                } else {
                    CotRoleMapOption::create([
                        'maper' => $optionData['text'],
                        'maper_key' => strtolower(str_replace(' ', '_', $optionData['text'])),
                        'categoryId' => $question->id,
                        'role_description_id' => $roleDescription->id ?? null,
                        'short_description' => $optionData['text'],
                        'long_description' => $optionData['text'],
                        'status' => 'Active',
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('admin.cot.questions.index')
                ->with('success', 'Question updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to update question: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function destroy($id)
    {
        $question = CotQuestion::findOrFail($id);

        DB::beginTransaction();
        try {
            CotRoleMapOption::where('categoryId', $question->id)->delete();
            $question->delete();

            DB::commit();

            return redirect()->route('admin.cot.questions.index')
                ->with('success', 'Question deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to delete question: ' . $e->getMessage());
        }
    }
}

