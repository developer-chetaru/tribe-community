<?php

namespace App\Http\Controllers;

use App\Models\HappyIndex;
use App\Models\User;
use App\Models\HptmLearningChecklist;
use App\Models\HptmLearningType;
use App\Services\OneSignalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Happy Index",
 *     description="Happy Index (mood tracking) endpoints"
 * )
 */
class HappyIndexController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/add-happy-index",
     *     tags={"Happy Index"},
     *     summary="Submit daily mood/sentiment",
     *     description="Store a new Happy Index (mood status) entry for a user",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"userId", "moodStatus"},
     *             @OA\Property(property="userId", type="integer", example=1),
     *             @OA\Property(property="moodStatus", type="integer", description="1=Bad, 2=Okay, 3=Good", example=3),
     *             @OA\Property(property="description", type="string", example="Feeling great today!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sentiment submitted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sentiment submitted successfully!"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="todayEIScore", type="string", example="1250.50")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Already submitted today",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You have already submitted your response")
     *         )
     *     )
     * )
     */
    public function addHappyIndex(Request $request)
    {
        $userId      = $request->input('userId');
        $moodValue   = $request->input('moodStatus');
        $description = $request->input('description');

        Log::info('addHappyIndex called', [
            'user_id' => $userId,
            'moodValue' => $moodValue,
        ]);

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

        $user = User::where('id', $userId)
            ->whereIn('status', ['active_verified', 'active_unverified', true, '1', 1])
            ->first();

        if (! $user || $user->onLeave) {
            Log::warning('User not eligible for HappyIndex', [
                'user_id' => $userId,
                'user_found' => $user ? true : false,
                'user_status' => $user ? $user->status : null,
                'onLeave' => $user ? $user->onLeave : null,
            ]);
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

        // ✅ Mark sentiment submitted in OneSignal (stops 6PM email reminder)
        try {
            $oneSignal = new OneSignalService();
            $result = $oneSignal->markSentimentSubmitted($userId);
            Log::info('OneSignal markSentimentSubmitted called', [
                'user_id' => $userId,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::warning('OneSignal markSentimentSubmitted failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

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
