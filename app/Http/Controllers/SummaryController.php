<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\HappyIndex;
use App\Models\UserLeave;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Http\Controllers\Concerns\UpdatesUserTimezone;

/**
 * @OA\Tag(
 *     name="Summary",
 *     description="User summary endpoints for Happy Index and leave data"
 * )
 */
class SummaryController extends Controller
{
    use UpdatesUserTimezone;
  
  /**
   * Get user's Happy Index and leave summary.
   *
   * @OA\Get(
   *     path="/api/summary/{filterType}",
   *     tags={"Summary"},
   *     summary="Get user summary by filter type",
   *     description="Retrieve user's Happy Index and leave summary based on filter type (this_week, last_7_days, previous_week, this_month, previous_month, custom, all)",
   *     security={{"bearerAuth":{}}},
   *     @OA\Parameter(
   *         name="filterType",
   *         in="path",
   *         required=true,
   *         description="Filter type for summary",
   *         @OA\Schema(
   *             type="string",
   *             enum={"this_week", "last_7_days", "previous_week", "this_month", "previous_month", "custom", "all"},
   *             example="this_week"
   *         )
   *     ),
   *     @OA\Parameter(
   *         name="start_date",
   *         in="query",
   *         required=false,
   *         description="Start date for custom filter (format: Y-m-d)",
   *         @OA\Schema(type="string", format="date", example="2024-01-01")
   *     ),
   *     @OA\Parameter(
   *         name="end_date",
   *         in="query",
   *         required=false,
   *         description="End date for custom filter (format: Y-m-d)",
   *         @OA\Schema(type="string", format="date", example="2024-01-31")
   *     ),
   *     @OA\Parameter(
   *         name="timezone",
   *         in="query",
   *         required=false,
   *         description="User timezone (optional)",
   *         @OA\Schema(type="string", example="Asia/Kolkata")
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description="Summary retrieved successfully",
   *         @OA\JsonContent(
   *             @OA\Property(property="status", type="boolean", example=true),
   *             @OA\Property(
   *                 property="data",
   *                 type="array",
   *                 @OA\Items(
   *                     type="object",
   *                     @OA\Property(property="date", type="string", example="Jan 15, 2024"),
   *                     @OA\Property(property="score", type="integer", nullable=true, example=85),
   *                     @OA\Property(property="mood_value", type="integer", nullable=true, example=3, description="1=average, 2=sad, 3=happy"),
   *                     @OA\Property(property="description", type="string", example="Feeling great today!"),
   *                     @OA\Property(property="image", type="string", example="happy-app.png"),
   *                     @OA\Property(property="status", type="string", example="Present", description="Present, Out of office, or Missed")
   *                 )
   *             )
   *         )
   *     ),
   *     @OA\Response(
   *         response=401,
   *         description="Unauthorized",
   *         @OA\JsonContent(
   *             @OA\Property(property="status", type="boolean", example=false),
   *             @OA\Property(property="message", type="string", example="Unauthenticated")
   *         )
   *     )
   * )
   *
   * @param string $filterType
   * @param \Illuminate\Http\Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getSummary($filterType, Request $request)
{
    // Update user timezone from request if provided
    $this->updateUserTimezoneIfNeeded($request);
    
    $userId = Auth::id();
    $startDate = $request->get('start_date');
    $endDate = $request->get('end_date');

    // Get user's organisation working days
    $org = Auth::user()->organisation;
    $workingDays = $org && $org->working_days
        ? $org->working_days
        : ["Mon", "Tue", "Wed", "Thu", "Fri"];

    // Determine date range
    switch ($filterType) {
        case 'this_week':
            $start = Carbon::now()->startOfWeek();
            $end = Carbon::now()->endOfWeek();
            break;
        case 'last_7_days':
            $start = Carbon::now()->subDays(7);
            $end = Carbon::now();
            break;
        case 'previous_week':
            $start = Carbon::now()->subWeek()->startOfWeek();
            $end = Carbon::now()->subWeek()->endOfWeek();
            break;
        case 'this_month':
            $start = Carbon::now()->startOfMonth();
            $end = Carbon::now()->endOfMonth();
            break;
        case 'previous_month':
            $start = Carbon::now()->subMonth()->startOfMonth();
            $end = Carbon::now()->subMonth()->endOfMonth();
            break;
        case 'custom':
            if ($startDate && $endDate) {
                $start = Carbon::parse($startDate);
                $end = Carbon::parse($endDate);
            } else {
                $start = Auth::user()->created_at->startOfDay();
                $end = Carbon::now()->endOfDay();
            }
            break;
        case 'all':
        default:
            $start = Auth::user()->created_at->startOfDay();
            $end = Carbon::now()->endOfDay();
            break;
    }

    // Fetch happy indexes in range
    $happyIndexes = HappyIndex::where('user_id', $userId)
        ->whereBetween('created_at', [$start, $end])
        ->get();

    // Fetch leaves in range
    $leaves = UserLeave::where('user_id', $userId)
        ->where('leave_status', 1)
        ->where(function ($q) use ($start, $end) {
            $q->whereBetween('start_date', [$start, $end])
              ->orWhereBetween('end_date', [$start, $end]);
        })
        ->get();

    $summary = [];
    $leavesArray = [];

    // Helper to build full image URL with current domain
    $imageUrl = function (string $fileName): string {
        return url('images/'.$fileName);
    };

    // Generate date range
    $period = collect(Carbon::parse($start)->daysUntil(Carbon::parse($end)->addDay()))
        ->sortByDesc(fn($d) => $d->timestamp);

    foreach ($period as $date) {
        // Skip future dates
        if ($date->greaterThan(Carbon::today())) continue;

        // Skip non-working days
        if (!in_array($date->format('D'), $workingDays)) {
            continue;
        }

        $dateStr = $date->format('M d, Y');

        // Check if leave
        $onLeave = $leaves->first(fn($l) =>
            $date->between(Carbon::parse($l->start_date), Carbon::parse($l->end_date))
        );

        if ($onLeave) {
            $summary[] = [
                'date'        => $dateStr,
                'score'       => null,
                'mood_value'  => null,
                'description' => "You were on leave on $dateStr",
                'image'       => $imageUrl('leave-app.png'),
                'status'      => 'Out of office',
            ];
            $leavesArray[] = ['date' => $dateStr];
            continue;
        }

        // Check happy index
        $entry = $happyIndexes->first(fn($h) => $h->created_at->isSameDay($date));

        if ($entry) {
            $image = match($entry->mood_value) {
                3 => $imageUrl('happy-app.png'),
                2 => $imageUrl('sad-app.png'),
                1 => $imageUrl('average-app.png'),
                default => $imageUrl('sad-app.png'),
            };
            $summary[] = [
                'date'        => $dateStr,
                'score'       => $entry->score,
                'mood_value'  => $entry->mood_value,
                'description' => $entry->description ?? 'No message added.',
                'image'       => $image,
                'status'      => 'Present',
            ];
        } else {
            // Only show missed sentiment notification for past days or today after 16:00
            $now = Carbon::now('Asia/Kolkata');
            $showMissed = !$date->isToday();

            if ($showMissed) {
                $summary[] = [
                    'date'        => $dateStr,
                    'score'       => null,
                    'mood_value'  => null,
                    'description' => "Oh Dear, you missed to share your sentiment on $dateStr",
                    'image'       => $imageUrl('sentiment-missed-summary.png'),
                    'status'      => 'Missed',
                ];
            }
        }
    }

    return response()->json([
        'status' => true,
        'data'   => $summary,
    ]);
}



}
