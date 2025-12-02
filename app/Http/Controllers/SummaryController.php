<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\HappyIndex;
use App\Models\UserLeave;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Http\Controllers\Concerns\UpdatesUserTimezone;

class SummaryController extends Controller
{
    use UpdatesUserTimezone;
  
  /**
   * Get user's Happy Index and leave summary.
   *
   * @param string $filterType
   * @param \Illuminate\Http\Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getSummary($filterType, Request $request)
{
    // Update user timezone if provided in request
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
                'image'       => 'leave-app.png',
                'status'      => 'Out of office',
            ];
            $leavesArray[] = ['date' => $dateStr];
            continue;
        }

        // Check happy index
        $entry = $happyIndexes->first(fn($h) => $h->created_at->isSameDay($date));

        if ($entry) {
            $image = match($entry->mood_value) {
                3 => 'happy-app.png',
                2 => 'sad-app.png',
                1 => 'average-app.png',
                default => 'sad-index.png',
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
                    'image'       => 'sentiment-missed-summary.png',
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
