<?php

namespace App\Http\Controllers;

use App\Models\CotAnswer;
use App\Models\CotQuestion;
use App\Models\CotTeamRoleDescription;
use App\Models\CotTeamRoleIndividualUserStatus;
use App\Models\CotTeamRoleResult;
use App\Models\PersonalityTypeIndividualUserStatus;
use App\Models\PersonalityTypeQuestion;
use App\Models\PersonalityTypeResult;
use App\Models\PersonalityTypeValue;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConnectingController extends Controller
{
    public function teamRoleMap()
    {
        $user = Auth::user();

        $questions = CotQuestion::where('status', 'Active')
            ->with(['roleMapOptions.roleDescription'])
            ->orderBy('order')
            ->get();

        $userAnswers = CotAnswer::where('userId', $user->id)
            ->with(['question', 'roleMapOption.roleDescription'])
            ->get()
            ->groupBy('questionId');

        $completionStatus = CotTeamRoleIndividualUserStatus::where('userid', $user->id)
            ->where('completeStatus', true)
            ->latest('date')
            ->first();

        return view('connecting.team-role-map', compact('questions', 'userAnswers', 'completionStatus'));
    }

    public function teamRoleMapResults()
    {
        $user = Auth::user();

        $latestDate = CotTeamRoleResult::where('userId', $user->id)->max('assessment_date');

        $results = collect();
        $latestDateCarbon = null;

        if ($latestDate) {
            $latestDateCarbon = Carbon::parse($latestDate);
            $results = CotTeamRoleResult::where('userId', $user->id)
                ->where('assessment_date', $latestDate)
                ->with('roleDescription')
                ->orderBy('preference_rank')
                ->get();
        }

        $roleDescriptions = CotTeamRoleDescription::where('status', 'Active')
            ->orderBy('order')
            ->get();

        return view('connecting.team-role-map-results', compact('results', 'roleDescriptions', 'latestDateCarbon'));
    }

    public function personalityType()
    {
        $user = Auth::user();

        $questions = PersonalityTypeQuestion::where('status', 'Active')
            ->with([
                'options' => fn ($query) => $query->where('status', 'Active')->orderBy('order'),
                'personalityTypeValue',
            ])
            ->orderBy('order')
            ->get();

        $completionStatus = PersonalityTypeIndividualUserStatus::where('userid', $user->id)
            ->where('completeStatus', true)
            ->latest('date')
            ->first();

        return view('connecting.personality-type', compact('questions', 'completionStatus'));
    }

    public function personalityTypeResults()
    {
        $user = Auth::user();

        $latestDate = PersonalityTypeResult::where('userId', $user->id)->max('assessment_date');

        $results = collect();
        $latestDateCarbon = null;

        if ($latestDate) {
            $latestDateCarbon = Carbon::parse($latestDate);
            $results = PersonalityTypeResult::where('userId', $user->id)
                ->where('assessment_date', $latestDate)
                ->with('personalityTypeValue')
                ->get();
        }

        $dimensions = PersonalityTypeValue::where('status', 'Active')
            ->orderBy('order')
            ->get();

        return view('connecting.personality-type-results', compact('results', 'dimensions', 'latestDateCarbon'));
    }

    public function submitTeamRoleMap(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'answers' => 'required|array',
            'answers.*.questionId' => 'required|exists:cot_questions,id',
            'answers.*.options' => 'required|array|min:1',
            'answers.*.options.*.optionId' => 'required|exists:cot_role_map_options,id',
            'answers.*.options.*.points' => 'required|integer|min:0|max:10',
        ]);

        // Server-side: each question's points must sum to exactly 10
        foreach ($request->answers as $index => $answer) {
            $total = collect($answer['options'])->sum(fn ($o) => (int) ($o['points'] ?? 0));
            if ($total !== 10) {
                return redirect()->back()
                    ->with('error', 'Question '.($index + 1).' must have exactly 10 points distributed. You entered '.$total.'.')
                    ->withInput();
            }
        }

        $apiController = new \App\Http\Controllers\Api\ApiCOTController;
        $apiRequest = new Request([
            'userId' => $user->id,
            'orgId' => $user->orgId ?? null,
            'answers' => $request->answers,
        ]);

        try {
            $response = $apiController->submitAnswers($apiRequest);
            $responseData = json_decode($response->getContent(), true);

            if (! empty($responseData['status'])) {
                return redirect()->route('connecting.team-role-map.results')
                    ->with('success', 'Assessment submitted successfully!');
            }

            return redirect()->back()
                ->with('error', $responseData['message'] ?? 'Failed to submit assessment')
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'An error occurred: '.$e->getMessage())
                ->withInput();
        }
    }

    public function submitPersonalityType(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'answers' => 'required|array',
            'answers.*.questionId' => 'required|exists:personality_type_questions,id',
            'answers.*.optionId' => 'required|exists:personality_type_options,id',
        ]);

        $apiController = new \App\Http\Controllers\Api\ApiPersonalitytypeController;
        $apiRequest = new Request([
            'userId' => $user->id,
            'orgId' => $user->orgId ?? null,
            'answers' => $request->answers,
        ]);

        try {
            $response = $apiController->submitAnswers($apiRequest);
            $responseData = json_decode($response->getContent(), true);

            if (! empty($responseData['status'])) {
                return redirect()->route('connecting.personality-type.results')
                    ->with('success', 'Assessment submitted successfully!');
            }

            return redirect()->back()
                ->with('error', $responseData['message'] ?? 'Failed to submit assessment')
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'An error occurred: '.$e->getMessage())
                ->withInput();
        }
    }
}
