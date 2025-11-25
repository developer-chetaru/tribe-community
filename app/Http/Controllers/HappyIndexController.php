<?php

namespace App\Http\Controllers;

use App\Models\HappyIndex;
use App\Models\User;
use App\Models\HptmLearningChecklist;
use App\Models\HptmLearningType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HappyIndexController extends Controller
{
    /**
     * Store a new Happy Index (mood status) entry for a user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addHappyIndex(Request $request)
    {
        $userId      = $request->input('userId');
        $moodValue   = $request->input('moodStatus');
        $description = $request->input('description');

        $existing = HappyIndex::where('user_id', $userId)
            ->whereDate('created_at', now()->toDateString())
            ->first();

        if ($existing) {
            return response()->json([
                'status'  => false,
                'message' => 'You have already submitted your response',
                'code'    => 400,
                'data'    => [],
            ]);
        }

        $user = User::where('id', $userId)->where('status', '1')->first();

        if (! $user || $user->onLeave) {
            return response()->json(['status' => false, 'message' => 'User not eligible']);
        }
      
        HappyIndex::create([
            'user_id'      => $userId,
            'mood_value'   => $moodValue,
            'description' => $description,
            'status'      => 'active',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $user->EIScore += 250;
        $user->lastHIDate = now()->toDateString();
        $user->updated_at = now();
        $user->save();

        $learningChecklistTotalScore = HptmLearningChecklist::leftJoin('hptm_learning_types', 'hptm_learning_types.id', '=', 'hptm_learning_checklist.output')
            ->sum('hptm_learning_types.score');

        $userHptmScore = (($user->hptmScore + $user->hptmEvaluationScore) / ($learningChecklistTotalScore + 400)) * 1000;

        $todayEIScore = str_replace(',', '', number_format($user->EIScore + $userHptmScore, 2));

        return response()->json([
            'status'  => true,
            'message' => 'Sentiment submitted successfully!',
            'code'    => 200,
            'data'    => ['todayEIScore' => $todayEIScore],
        ]);
    }

}
