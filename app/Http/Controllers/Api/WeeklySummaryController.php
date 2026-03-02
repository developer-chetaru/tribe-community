<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\WeeklySummary;

class WeeklySummaryController extends Controller
{
    public function index(Request $request)
    {
        // Use same authentication approach as SummaryController
        // Extract user from token (SummaryController uses Auth::user() which works because of middleware)
        // Since we don't have middleware, we extract manually and use directly
        $token = $request->bearerToken();
        $user = null;
        
        if ($token) {
            // Extract user from token - try manual decode first (most reliable)
            try {
                $parts = explode('.', $token);
                if (count($parts) === 3) {
                    // Decode base64 with proper padding
                    $payloadJson = base64_decode(str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) % 4, '=', STR_PAD_RIGHT));
                    $payload = json_decode($payloadJson, true);
                    $userId = $payload['sub'] ?? null;
                    if ($userId) {
                        $userId = is_string($userId) ? (int)$userId : $userId;
                        $user = \App\Models\User::find($userId);
                    }
                }
            } catch (\Exception $e) {
                // Manual decode failed, try JWTAuth
                \Illuminate\Support\Facades\Log::debug("WeeklySummary: Manual decode exception", [
                    'error' => $e->getMessage(),
                ]);
            }
            
            // If manual decode didn't work, try JWTAuth
            if (!$user) {
                try {
                    $user = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->authenticate();
                } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
                    try {
                        $payload = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->getPayload();
                        $userId = $payload->get('sub');
                        if ($userId) {
                            $userId = is_string($userId) ? (int)$userId : $userId;
                            $user = \App\Models\User::find($userId);
                        }
                    } catch (\Exception $e2) {
                        // Continue
                    }
                } catch (\Exception $e) {
                    try {
                        $payload = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->getPayload();
                        $userId = $payload->get('sub');
                        if ($userId) {
                            $userId = is_string($userId) ? (int)$userId : $userId;
                            $user = \App\Models\User::find($userId);
                        }
                    } catch (\Exception $e2) {
                        // Continue
                    }
                }
            }
        }
        
        // Fallback: Try Auth::user() (in case middleware set it)
        if (!$user) {
            try {
                $user = Auth::guard('api')->user() ?? Auth::user();
            } catch (\Exception $e) {
                // Continue
            }
        }
        
        // Debug: Log user extraction result with full details
        Log::info("=== WeeklySummary API Request ===", [
            'path' => $request->path(),
            'has_token' => !empty($token),
            'year' => $request->input('year'),
            'month' => $request->input('month'),
        ]);
        
        if (!$user && $token) {
            Log::warning("WeeklySummary: User not found from token", [
                'has_token' => !empty($token),
                'token_preview' => $token ? substr($token, 0, 30) . '...' : 'none',
            ]);
        } elseif ($user) {
            Log::info("WeeklySummary: ✅ User found - DETAILS", [
                'user_id' => $user->id,
                'user_email' => $user->email ?? 'N/A',
                'user_name' => $user->name ?? 'N/A',
                'user_created_at' => $user->created_at ? $user->created_at->toDateTimeString() : 'N/A',
            ]);
        } else {
            Log::warning("WeeklySummary: No token provided");
        }
        
        // If no user found, return empty data
        if (!$user) {
            return response()->json([
                'status' => true,
                'data' => [
                    'weeklySummaries' => [],
                    'validMonths' => [],
                    'validYears' => [],
                    'selectedYear' => $request->input('year', now()->year),
                    'selectedMonth' => $request->input('month', now()->month)
                ]
            ]);
        }
        
        $selectedYear = $request->input('year', now()->year);
        $selectedMonth = $request->input('month', now()->month);
        
        Log::info("WeeklySummary: Fetching data for user", [
            'user_id' => $user->id,
            'selected_year' => $selectedYear,
            'selected_month' => $selectedMonth,
        ]);

        // Load weekly summaries
        $existingSummaries = WeeklySummary::where('user_id', $user->id)
            ->where('year', $selectedYear)
            ->where('month', $selectedMonth)
            ->orderBy('week_number')
            ->get()
            ->keyBy('week_number');
            
        Log::info("WeeklySummary: 📊 Database query result", [
            'user_id' => $user->id,
            'year' => $selectedYear,
            'month' => $selectedMonth,
            'summaries_found' => $existingSummaries->count(),
            'summary_ids' => $existingSummaries->pluck('id')->toArray(),
            'week_numbers' => $existingSummaries->pluck('week_number')->toArray(),
        ]);

        $firstDay = Carbon::create($selectedYear, $selectedMonth, 1)->startOfMonth();
        $lastDay = Carbon::create($selectedYear, $selectedMonth, 1)->endOfMonth();
        $weekNum = 1;
        $weekStart = $firstDay->copy()->startOfWeek(Carbon::MONDAY);

        $weeksInMonth = [];

        // Get user's registration date safely
        $defaultTimezone = \App\Helpers\TimezoneHelper::DEFAULT_TIMEZONE;
        $userRegistrationDate = \App\Helpers\TimezoneHelper::setTimezone(Carbon::parse($user->created_at), $defaultTimezone)->startOfDay();
        $today = \App\Helpers\TimezoneHelper::carbon(null, $defaultTimezone);
        
        Log::info("WeeklySummary: 📅 Date calculations", [
            'user_id' => $user->id,
            'user_registration_date' => $userRegistrationDate->toDateTimeString(),
            'today' => $today->toDateTimeString(),
            'first_day_of_month' => $firstDay->toDateTimeString(),
            'last_day_of_month' => $lastDay->toDateTimeString(),
        ]);

        $weeksProcessed = 0;
        $weeksSkippedFuture = 0;
        $weeksSkippedBeforeRegistration = 0;
        
        while ($weekStart->lte($lastDay)) {
            $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

            // Skip future weeks and current week (only show summaries for completed weeks)
            // A week is considered completed only if its end date (Sunday) has passed
            if ($weekStart->gt($today) || $weekEnd->gt($today)) {
                $weeksSkippedFuture++;
                Log::info("WeeklySummary: ⏭️ Skipping future week", [
                    'week_num' => $weekNum,
                    'week_start' => $weekStart->toDateTimeString(),
                    'week_end' => $weekEnd->toDateTimeString(),
                    'today' => $today->toDateTimeString(),
                ]);
                break;
            }
            
            // Skip weeks that occurred before user's registration date
            // Only show weeks where the week's end date (Sunday) is on or after registration date
            if ($weekEnd->lt($userRegistrationDate)) {
                $weeksSkippedBeforeRegistration++;
                Log::info("WeeklySummary: ⏭️ Skipping week before registration", [
                    'week_num' => $weekNum,
                    'week_end' => $weekEnd->toDateTimeString(),
                    'user_registration_date' => $userRegistrationDate->toDateTimeString(),
                ]);
                $weekNum++;
                $weekStart->addWeek();
                continue;
            }

            $weeksProcessed++;
            $weeksInMonth[$weekNum] = [
                'week' => $weekNum,
                'weekLabel' => $weekStart->format('M d') . ' - ' . $weekEnd->format('M d'),
                'summary' => $existingSummaries[$weekNum]->summary ?? null,
            ];
            
            Log::info("WeeklySummary: ✅ Added week", [
                'week_num' => $weekNum,
                'week_label' => $weekStart->format('M d') . ' - ' . $weekEnd->format('M d'),
                'has_summary' => isset($existingSummaries[$weekNum]),
            ]);

            $weekNum++;
            $weekStart->addWeek();
        }
        
        Log::info("WeeklySummary: 📈 Week processing summary", [
            'user_id' => $user->id,
            'weeks_processed' => $weeksProcessed,
            'weeks_skipped_future' => $weeksSkippedFuture,
            'weeks_skipped_before_registration' => $weeksSkippedBeforeRegistration,
            'total_weeks_in_result' => count($weeksInMonth),
        ]);

        // Calculate valid months and years
        $startYear = $user->created_at->year;
        $currentYear = now()->year;
        $validYears = range($startYear, $currentYear);

        if ($selectedYear == $startYear && $selectedYear == $currentYear) {
            $startMonth = $user->created_at->month;
            $maxMonth = now()->month;
        } elseif ($selectedYear == $startYear) {
            $startMonth = $user->created_at->month;
            $maxMonth = 12;
        } elseif ($selectedYear == $currentYear) {
            $startMonth = 1;
            $maxMonth = now()->month;
        } else {
            $startMonth = 1;
            $maxMonth = 12;
        }

        $validMonths = [];
        for ($m = $startMonth; $m <= $maxMonth; $m++) {
            $validMonths[] = [
                'value' => $m,
                'name' => date('F', mktime(0, 0, 0, $m, 1))
            ];
        }

        $responseData = [
            'status' => true,
            'data' => [
                'weeklySummaries' => array_values($weeksInMonth),
                'validMonths' => $validMonths,
                'validYears' => $validYears,
                'selectedYear' => $selectedYear,
                'selectedMonth' => $selectedMonth
            ]
        ];
        
        Log::info("WeeklySummary: 🎯 Final response", [
            'user_id' => $user->id,
            'weekly_summaries_count' => count($responseData['data']['weeklySummaries']),
            'valid_months_count' => count($validMonths),
            'valid_years_count' => count($validYears),
        ]);
        Log::info("=== WeeklySummary API Response Complete ===");
        
        return response()->json($responseData);
    }
}
