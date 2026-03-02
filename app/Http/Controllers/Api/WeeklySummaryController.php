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
        // CRITICAL: Log at the very start to ensure method is being called
        // Use both Log facade and error_log to ensure we see something
        file_put_contents(storage_path('logs/weekly-summary-debug.log'), 
            date('Y-m-d H:i:s') . " - WeeklySummary API CALLED\n" . 
            "Path: " . $request->path() . "\n" .
            "Full URL: " . $request->fullUrl() . "\n" .
            "Method: " . $request->method() . "\n" .
            "Has Token: " . (!empty($request->bearerToken()) ? 'YES' : 'NO') . "\n\n",
            FILE_APPEND
        );
        
        error_log("=== WeeklySummary API CALLED ===");
        Log::info("=== WeeklySummary API CALLED ===", [
            'timestamp' => now()->toDateTimeString(),
            'path' => $request->path(),
            'full_url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
        ]);
        error_log("WeeklySummary: Path = " . $request->path());
        
        // Use same authentication approach as SummaryController
        // Extract user from token (SummaryController uses Auth::user() which works because of middleware)
        // Since we don't have middleware, we extract manually and use directly
        $token = $request->bearerToken();
        $user = null;
        
        Log::info("WeeklySummary: Token extracted", [
            'has_token' => !empty($token),
            'token_length' => $token ? strlen($token) : 0,
        ]);
        
        if ($token) {
            // Extract user from token - try manual decode first (most reliable)
            try {
                $parts = explode('.', $token);
                if (count($parts) === 3) {
                    // Decode base64 with proper padding
                    $payloadJson = base64_decode(str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) % 4, '=', STR_PAD_RIGHT));
                    $payload = json_decode($payloadJson, true);
                    $userId = $payload['sub'] ?? null;
                    
                    file_put_contents(storage_path('logs/weekly-summary-debug.log'), 
                        date('Y-m-d H:i:s') . " - Token decoded\n" . 
                        "User ID from token: " . ($userId ?? 'NULL') . "\n",
                        FILE_APPEND
                    );
                    
                    if ($userId) {
                        $userId = is_string($userId) ? (int)$userId : $userId;
                        $user = \App\Models\User::find($userId);
                        
                        file_put_contents(storage_path('logs/weekly-summary-debug.log'), 
                            date('Y-m-d H:i:s') . " - User lookup\n" . 
                            "User found: " . ($user ? $user->email : 'NO') . "\n",
                            FILE_APPEND
                        );
                    }
                }
            } catch (\Exception $e) {
                // Manual decode failed, try JWTAuth
                file_put_contents(storage_path('logs/weekly-summary-debug.log'), 
                    date('Y-m-d H:i:s') . " - Manual decode exception: " . $e->getMessage() . "\n",
                    FILE_APPEND
                );
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
        
        // If no user found, return empty data with debug info
        if (!$user) {
            // Try one more time with direct token decode
            $userIdFromToken = null;
            if ($token) {
                try {
                    $parts = explode('.', $token);
                    if (count($parts) === 3) {
                        $payloadJson = base64_decode(str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) % 4, '=', STR_PAD_RIGHT));
                        $payload = json_decode($payloadJson, true);
                        $userIdFromToken = $payload['sub'] ?? null;
                        if ($userIdFromToken) {
                            $user = \App\Models\User::find((int)$userIdFromToken);
                            Log::info("WeeklySummary: User found on retry", [
                                'user_id' => $userIdFromToken,
                                'user_found' => $user ? 'yes' : 'no',
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("WeeklySummary: Final decode attempt failed", ['error' => $e->getMessage()]);
                }
            }
            
            if (!$user) {
                Log::error("WeeklySummary: ❌ NO USER FOUND - Returning empty data", [
                    'has_token' => !empty($token),
                    'user_id_from_token' => $userIdFromToken,
                ]);
                return response()->json([
                    'status' => true,
                    'debug' => [
                        'user_found' => false,
                        'user_id_from_token' => $userIdFromToken,
                        'has_token' => !empty($token),
                    ],
                    'data' => [
                        'weeklySummaries' => [],
                        'validMonths' => [],
                        'validYears' => [],
                        'selectedYear' => $request->input('year', now()->year),
                        'selectedMonth' => $request->input('month', now()->month)
                    ]
                ]);
            }
        }
        
        $selectedYear = (int)$request->input('year', now()->year);
        $selectedMonth = (int)$request->input('month', now()->month);
        
        Log::info("WeeklySummary: Fetching data for user", [
            'user_id' => $user->id,
            'selected_year' => $selectedYear,
            'selected_year_type' => gettype($selectedYear),
            'selected_month' => $selectedMonth,
            'selected_month_type' => gettype($selectedMonth),
        ]);

        // Load weekly summaries - ensure year and month are integers
        $existingSummaries = WeeklySummary::where('user_id', $user->id)
            ->where('year', $selectedYear)
            ->where('month', $selectedMonth)
            ->orderBy('week_number')
            ->get();
            
        Log::info("WeeklySummary: 📊 Database query result", [
            'user_id' => $user->id,
            'year' => $selectedYear,
            'month' => $selectedMonth,
            'summaries_found' => $existingSummaries->count(),
            'summary_ids' => $existingSummaries->pluck('id')->toArray(),
            'week_numbers' => $existingSummaries->pluck('week_number')->toArray(),
        ]);
        
        // Key by week_number for easy lookup
        $existingSummaries = $existingSummaries->keyBy('week_number');

        $firstDay = Carbon::create($selectedYear, $selectedMonth, 1)->startOfMonth();
        $lastDay = Carbon::create($selectedYear, $selectedMonth, 1)->endOfMonth();
        
        // Get user's timezone
        $userTimezone = \App\Helpers\TimezoneHelper::getUserTimezone($user);
        $today = Carbon::now($userTimezone);
        
        // Get user's registration date in user's timezone
        $userRegistrationDate = \App\Helpers\TimezoneHelper::setTimezone(Carbon::parse($user->created_at), $userTimezone)->startOfDay();
        
        Log::info("WeeklySummary: 📅 Date calculations", [
            'user_id' => $user->id,
            'user_timezone' => $userTimezone,
            'user_registration_date' => $userRegistrationDate->toDateTimeString(),
            'today' => $today->toDateTimeString(),
            'first_day_of_month' => $firstDay->toDateTimeString(),
            'last_day_of_month' => $lastDay->toDateTimeString(),
        ]);

        // DIRECT RETURN: Just return all summaries that exist in database
        // NO DATE FILTERING - just return what's in DB
        $weeksInMonth = [];
        
        Log::info("WeeklySummary: Processing summaries", [
            'summaries_count' => $existingSummaries->count(),
            'week_numbers' => $existingSummaries->pluck('week_number')->toArray(),
        ]);
        
        // Simply iterate through all summaries and add them - NO FILTERING AT ALL
        foreach ($existingSummaries as $weekNum => $summary) {
            // Calculate week dates simply
            $weekStart = $firstDay->copy()->startOfWeek(Carbon::MONDAY);
            if ($weekNum > 1) {
                $weekStart->addWeeks($weekNum - 1);
            }
            $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
            
            // Add ALL summaries - no date filtering, no registration check
            $weeksInMonth[] = [
                'week' => (int)$weekNum,
                'weekLabel' => $weekStart->format('M d') . ' - ' . $weekEnd->format('M d'),
                'summary' => $summary->summary ?? null,
            ];
        }
        
        // Sort by week number
        usort($weeksInMonth, function($a, $b) {
            return $a['week'] <=> $b['week'];
        });
        
        Log::info("WeeklySummary: Final weeks array", [
            'total_weeks' => count($weeksInMonth),
            'week_numbers' => array_column($weeksInMonth, 'week'),
        ]);
        
        Log::info("WeeklySummary: 📈 Week processing summary", [
            'user_id' => $user->id,
            'total_weeks_in_result' => count($weeksInMonth),
            'existing_summaries_count' => $existingSummaries->count(),
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
            'debug' => [
                'user_id' => $user->id,
                'summaries_found_in_db' => $existingSummaries->count(),
                'weeks_in_result' => count($weeksInMonth),
                'week_numbers_in_db' => $existingSummaries->pluck('week_number')->toArray(),
            ],
            'data' => [
                'weeklySummaries' => array_values($weeksInMonth),
                'validMonths' => $validMonths,
                'validYears' => $validYears,
                'selectedYear' => $selectedYear,
                'selectedMonth' => $selectedMonth
            ]
        ];
        
        // CRITICAL: Log final response to verify data is being returned
        Log::info("WeeklySummary: 🎯 FINAL RESPONSE DATA", [
            'user_id' => $user->id,
            'weekly_summaries_count' => count($weeksInMonth),
            'summaries_found_in_db' => $existingSummaries->count(),
            'week_numbers' => array_column($weeksInMonth, 'week'),
            'response_weekly_summaries_count' => count($responseData['data']['weeklySummaries']),
            'valid_months_count' => count($validMonths),
            'valid_years_count' => count($validYears),
        ]);
        Log::info("=== WeeklySummary API Response Complete ===");
        
        return response()->json($responseData);
    }
}
