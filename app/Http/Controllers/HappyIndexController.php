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

        $user = User::where('id', $userId)
            // ->whereIn('status', ['active_verified', 'active_unverified', true, '1', 1])
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

        // Get user's timezone safely using helper
        $userTimezone = \App\Helpers\TimezoneHelper::getUserTimezone($user);
        
        // Log user's timezone for debugging
        Log::info('User timezone before HappyIndex creation (API)', [
            'user_id' => $userId,
            'user_timezone_from_db' => $user->timezone ?? 'null',
            'resolved_timezone' => $userTimezone,
        ]);
        
        // Get current date in user's timezone
        $userNow = \App\Helpers\TimezoneHelper::carbon(null, $userTimezone);
        $userDate = $userNow->toDateString(); // Y-m-d format in user's timezone
        
        // Check if user already submitted today in their CURRENT timezone
        // We check if any entry exists that, when converted to current user timezone, matches today's date
        // This allows one entry per day in the current timezone, regardless of when timezone was changed
        $existing = HappyIndex::where('user_id', $userId)
            ->get()
            ->filter(function ($entry) use ($userTimezone, $userDate) {
                // Convert entry's created_at (UTC) to current user's timezone
                $entryDateInUserTimezone = \App\Helpers\TimezoneHelper::setTimezone(\Carbon\Carbon::parse($entry->created_at), $userTimezone)->toDateString();
                // Compare with today's date in current user timezone
                return $entryDateInUserTimezone === $userDate;
            })
            ->first();

        if ($existing) {
            return response()->json([
                'status'  => false,
                'message' => 'You have already submitted your response',
                'code'    => 400,
                'data'    => [],
            ]);
        }
      
        // Create entry with timezone-aware timestamp
        // We need to ensure the created_at represents the user's local date/time
        // Get the user's current date/time in their timezone, then convert to UTC for storage
        $userLocalDateTime = \App\Helpers\TimezoneHelper::carbon(null, $userTimezone);
        $utcTimestamp = $userLocalDateTime->utc();
        
        // Ensure timezone is saved correctly (should be user's timezone, default to Asia/Kolkata)
        $timezoneToSave = !empty($userTimezone) ? $userTimezone : 'Asia/Kolkata';
        
        // Use DB facade to insert with explicit timestamps to prevent Laravel from overriding
        $happyIndexId = \DB::table('happy_indexes')->insertGetId([
            'user_id'      => $userId,
            'mood_value'   => $moodValue,
            'description' => $description,
            'status'      => 'active',
            'timezone'    => $timezoneToSave, // Store the timezone when entry was created
            'created_at'  => $utcTimestamp,
            'updated_at'  => $utcTimestamp,
        ]);
        
        Log::info('HappyIndex created with timezone-aware timestamp', [
            'user_id' => $userId,
            'happy_index_id' => $happyIndexId,
            'user_timezone' => $userTimezone,
            'timezone_saved' => $timezoneToSave,
            'user_local_date' => $userLocalDateTime->toDateString(),
            'user_local_time' => $userLocalDateTime->toTimeString(),
            'user_local_datetime' => $userLocalDateTime->toDateTimeString(),
            'utc_timestamp' => $utcTimestamp->toDateTimeString(),
            'timezone_offset' => $userLocalDateTime->format('P'), // +05:30 for IST
        ]);

        $user->EIScore += 250;
        $user->lastHIDate = $userDate; // Store date in user's timezone
        $user->updated_at = $userNow->utc();
        $user->save();
        
        Log::info('HappyIndex created with user timezone', [
            'user_id' => $userId,
            'user_timezone' => $userTimezone,
            'user_date' => $userDate,
            'stored_timestamp' => $userNow->utc()->toDateTimeString(),
        ]);

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
