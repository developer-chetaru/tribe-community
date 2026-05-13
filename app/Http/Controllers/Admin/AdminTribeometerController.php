<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
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

class AdminTribeometerController extends Controller
{
    public function index(Request $request)
    {
        $questions = TribeometerQuestion::with('value')
            ->orderBy('id', 'desc')
            ->paginate(10);

        return view('admin.tribeometer.listQuestions', compact('questions'));
    }

    public function editQuestion($id)
    {
        $id = base64_decode($id);
        $question = TribeometerQuestion::with('value')->findOrFail($id);
        return view('admin.tribeometer.editQuestion', compact('question'));
    }

    public function updateQuestion(Request $request, $id)
    {
        $id = base64_decode($id);

        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:500',
            'measure' => 'nullable|string|max:255',
            'valueId' => 'required|exists:tribeometer_values,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $question = TribeometerQuestion::findOrFail($id);
        $question->update([
            'question' => $request->question,
            'measure' => $request->measure,
            'value_id' => $request->valueId,
        ]);

        return redirect()->route('admin.tribeometer.index')
            ->with('success', 'Question updated successfully.');
    }

    public function getOptionList()
    {
        $options = TribeometerOption::orderBy('value_score')->get();
        return view('admin.tribeometer.listOptions', compact('options'));
    }

    public function updateOption(Request $request)
    {
        $optionId = base64_decode($request->optionId);

        $validator = Validator::make($request->all(), [
            'option' => 'required|string|max:225',
            'valueScore' => 'required|integer|min:0|max:3',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $existingOption = TribeometerOption::where('value_score', $request->valueScore)
            ->where('id', '!=', $optionId)
            ->first();

        if ($existingOption) {
            return response()->json([
                'status' => false,
                'message' => 'This score is already assigned to another option',
            ], 422);
        }

        $option = TribeometerOption::findOrFail($optionId);
        $option->update([
            'option_name' => $request->option,
            'value_score' => $request->valueScore,
        ]);

        return response()->json(['status' => true, 'message' => 'Option updated successfully']);
    }

    public function getValuesList()
    {
        $values = TribeometerValue::orderBy('order')->get();
        return view('admin.tribeometer.listValues', compact('values'));
    }

    public function updateValue(Request $request)
    {
        $valueId = base64_decode($request->valueId);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $value = TribeometerValue::findOrFail($valueId);
        $value->update([
            'title' => $request->title,
            'description' => $request->description,
        ]);

        return response()->json(['status' => true, 'message' => 'Value updated successfully']);
    }

    public function addOption(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'optionName' => 'required|string|max:225',
            'valueScore' => 'required|integer|min:0|max:3|unique:tribeometer_options,value_score',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        TribeometerOption::create([
            'option_name' => $request->optionName,
            'value_score' => $request->valueScore,
            'status' => 'Active',
        ]);

        return response()->json(['status' => true, 'message' => 'Option added successfully']);
    }

    public function addQuestion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:500',
            'measure' => 'nullable|string|max:255',
            'valueId' => 'required|exists:tribeometer_values,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        TribeometerQuestion::create([
            'question' => $request->question,
            'measure' => $request->measure,
            'value_id' => $request->valueId,
            'status' => 'Active',
        ]);

        return response()->json(['status' => true, 'message' => 'Question added successfully']);
    }

    public function addValue(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'valueKey' => 'required|string|max:50|unique:tribeometer_values,value_key',
            'title' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $maxOrder = TribeometerValue::max('order') ?? 0;

        TribeometerValue::create([
            'value_key' => $request->valueKey,
            'title' => $request->title,
            'description' => $request->description,
            'order' => $maxOrder + 1,
        ]);

        return response()->json(['status' => true, 'message' => 'Value added successfully']);
    }

    public function deleteQuestion(Request $request)
    {
        $questionId = $request->questionId;

        DB::beginTransaction();
        try {
            TribeometerAnswer::where('questionId', $questionId)->delete();
            TribeometerQuestion::where('id', $questionId)->delete();
            DB::commit();
            return response()->json(['status' => true, 'message' => 'Question deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'Failed to delete question: ' . $e->getMessage()], 500);
        }
    }

    public function deleteOption(Request $request)
    {
        $optionId = $request->optionId;

        DB::beginTransaction();
        try {
            TribeometerAnswer::where('optionId', $optionId)->update(['optionId' => null]);
            TribeometerOption::where('id', $optionId)->delete();
            DB::commit();
            return response()->json(['status' => true, 'message' => 'Option deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'Failed to delete option: ' . $e->getMessage()], 500);
        }
    }

    public function deleteValue(Request $request)
    {
        $valueId = $request->valueId;

        DB::beginTransaction();
        try {
            TribeometerQuestion::where('value_id', $valueId)->update(['value_id' => null]);
            TribeometerResult::where('value_id', $valueId)->delete();
            TribeometerValue::where('id', $valueId)->delete();
            DB::commit();
            return response()->json(['status' => true, 'message' => 'Value deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'Failed to delete value: ' . $e->getMessage()], 500);
        }
    }

    public function resultsIndex(Request $request)
    {
        $query = TribeometerIndividualUserStatus::with(['user', 'organisation'])
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

            $userResults = TribeometerResult::where('userId', $user->id)->with('value')->get();
            if ($userResults->isEmpty()) {
                continue;
            }

            $valueScores = [];
            foreach ($userResults as $result) {
                $valueScores[$result->value->title ?? 'Unknown'] = [
                    'score' => round($result->score, 2),
                    'average_score' => round($result->average_score, 2),
                    'total_responses' => $result->total_responses,
                ];
            }

            $results[] = [
                'user' => $user,
                'status' => $status,
                'valueScores' => $valueScores,
            ];
        }

        $organisations = Organisation::all();
        $users = User::when($request->org_id, fn ($q) => $q->where('orgId', $request->org_id))->get();

        return view('admin.tribeometer.results', compact('results', 'organisations', 'users'));
    }
}

