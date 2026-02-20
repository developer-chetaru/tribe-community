<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\WeeklySummary;

class WeeklySummaryController extends Controller
{
    public function index(Request $request)
    {
        // COMMENTED OUT: Auto logout disabled - bypass all authentication checks
        // Just try to get user, but never return 401
        \Illuminate\Support\Facades\Log::info("WeeklySummaryController: Request received", [
            'path' => $request->path(),
            'has_token' => !empty($request->bearerToken()),
        ]);
        // COMMENTED OUT: Auto logout disabled - allow without strict authentication
        // Try to get user from JWT token, but don't reject if not found
        $user = null;
        $token = $request->bearerToken();
        
        if ($token) {
            try {
                // First try to authenticate normally (works for valid tokens)
                $user = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->authenticate();
                if ($user) {
                    \Illuminate\Support\Facades\Log::info("WeeklySummary: User authenticated successfully", [
                        'user_id' => $user->id,
                    ]);
                }
            } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
                // Token expired - try to get user from payload anyway
                \Illuminate\Support\Facades\Log::info("WeeklySummary: Token expired, trying to get user from payload", [
                    'error' => $e->getMessage(),
                ]);
                try {
                    // Even if expired, we can still decode the payload
                    $payload = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->getPayload();
                    $userId = $payload->get('sub');
                    if ($userId) {
                        $user = \App\Models\User::find($userId);
                        if ($user) {
                            \Illuminate\Support\Facades\Log::info("WeeklySummary: User found from expired token payload", [
                                'user_id' => $userId,
                            ]);
                        } else {
                            \Illuminate\Support\Facades\Log::warning("WeeklySummary: User ID from token not found in database", [
                                'user_id' => $userId,
                            ]);
                        }
                    }
                } catch (\Exception $e2) {
                    \Illuminate\Support\Facades\Log::warning("WeeklySummary: Could not decode expired token", [
                        'error' => $e2->getMessage(),
                    ]);
                }
            } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
                // Token invalid - try to get user from payload anyway
                \Illuminate\Support\Facades\Log::info("WeeklySummary: Token invalid, trying to get user from payload", [
                    'error' => $e->getMessage(),
                ]);
                try {
                    $payload = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->getPayload();
                    $userId = $payload->get('sub');
                    if ($userId) {
                        $user = \App\Models\User::find($userId);
                        if ($user) {
                            \Illuminate\Support\Facades\Log::info("WeeklySummary: User found from invalid token payload", [
                                'user_id' => $userId,
                            ]);
                        }
                    }
                } catch (\Exception $e2) {
                    \Illuminate\Support\Facades\Log::warning("WeeklySummary: Could not decode invalid token", [
                        'error' => $e2->getMessage(),
                    ]);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("WeeklySummary: JWT auth failed", [
                    'error' => $e->getMessage(),
                    'class' => get_class($e),
                ]);
            }
        }
        
        // Fallback: Try standard auth methods (but don't throw exceptions)
        if (!$user) {
            try {
                // Use withoutAuth to prevent exceptions
                $user = Auth::guard('api')->user();
            } catch (\Exception $e) {
                // Continue without user
            }
            if (!$user) {
                try {
                    $user = Auth::user();
                } catch (\Exception $e) {
                    // Continue without user
                }
            }
        }
        
        // COMMENTED OUT: Auto logout disabled - allow without user
        // If no user found, return empty data instead of 401
        if (!$user) {
            \Illuminate\Support\Facades\Log::warning("WeeklySummary: No user found, returning empty data", [
                'has_token' => !empty($token),
                'token_preview' => $token ? substr($token, 0, 20) . '...' : 'none',
                'path' => $request->path(),
            ]);
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
        
        \Illuminate\Support\Facades\Log::info("WeeklySummary: User found, fetching data", [
            'user_id' => $user->id,
            'year' => $request->input('year'),
            'month' => $request->input('month'),
        ]);

        $selectedYear = $request->input('year', now()->year);
        $selectedMonth = $request->input('month', now()->month);

        // Load weekly summaries
        $existingSummaries = WeeklySummary::where('user_id', $user->id)
            ->where('year', $selectedYear)
            ->where('month', $selectedMonth)
            ->orderBy('week_number')
            ->get()
            ->keyBy('week_number');

        $firstDay = Carbon::create($selectedYear, $selectedMonth, 1)->startOfMonth();
        $lastDay = Carbon::create($selectedYear, $selectedMonth, 1)->endOfMonth();
        $weekNum = 1;
        $weekStart = $firstDay->copy()->startOfWeek(Carbon::MONDAY);

        $weeksInMonth = [];

        // Get user's registration date safely
        $defaultTimezone = \App\Helpers\TimezoneHelper::DEFAULT_TIMEZONE;
        $userRegistrationDate = \App\Helpers\TimezoneHelper::setTimezone(Carbon::parse($user->created_at), $defaultTimezone)->startOfDay();
        $today = \App\Helpers\TimezoneHelper::carbon(null, $defaultTimezone);

        while ($weekStart->lte($lastDay)) {
            $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

            // Skip future weeks and current week (only show summaries for completed weeks)
            // A week is considered completed only if its end date (Sunday) has passed
            if ($weekStart->gt($today) || $weekEnd->gt($today)) break;
            
            // Skip weeks that occurred before user's registration date
            // Only show weeks where the week's end date (Sunday) is on or after registration date
            if ($weekEnd->lt($userRegistrationDate)) {
                $weekNum++;
                $weekStart->addWeek();
                continue;
            }

            $weeksInMonth[$weekNum] = [
                'week' => $weekNum,
                'weekLabel' => $weekStart->format('M d') . ' - ' . $weekEnd->format('M d'),
                'summary' => $existingSummaries[$weekNum]->summary ?? null,
            ];

            $weekNum++;
            $weekStart->addWeek();
        }

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

        return response()->json([
            'status' => true,
            'data' => [
                'weeklySummaries' => array_values($weeksInMonth),
                'validMonths' => $validMonths,
                'validYears' => $validYears,
                'selectedYear' => $selectedYear,
                'selectedMonth' => $selectedMonth
            ]
        ]);
    }
}
