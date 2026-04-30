<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use App\Models\WeeklySummary;

class WeeklySummaryController extends Controller
{
    public function index(Request $request)
    {
        // CRITICAL: Return immediately with debug info to verify controller is being called
        // TEMPORARY: Remove this after confirming controller is called
        $testResponse = [
            'status' => true,
            'debug' => [
                'controller_called' => true,
                'timestamp' => now()->toDateTimeString(),
                'path' => $request->path(),
                'full_url' => $request->fullUrl(),
                'year' => $request->input('year'),
                'month' => $request->input('month'),
                'has_token' => !empty($request->bearerToken()),
                'message' => 'CONTROLLER IS BEING CALLED - This proves the route is working',
            ],
            'data' => [
                'weeklySummaries' => [],
                'validMonths' => [],
                'validYears' => [],
                'selectedYear' => $request->input('year', now()->year),
                'selectedMonth' => $request->input('month', now()->month)
            ]
        ];
        
        // REMOVED: Test return - now running actual logic
        // return response()->json($testResponse);
        
        // CRITICAL: Add debug message in response immediately
        $immediateDebug = [
            'controller_called' => true,
            'timestamp' => now()->toDateTimeString(),
            'path' => $request->path(),
        ];
        
        // CRITICAL: Log at the very start - MUST appear in logs
        Log::info("=== WeeklySummary API CALLED ===", [
            'timestamp' => now()->toDateTimeString(),
            'path' => $request->path(),
            'full_url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'year' => $request->input('year'),
            'month' => $request->input('month'),
            'has_token' => !empty($request->bearerToken()),
        ]);
        
        // Also use error_log which goes to PHP error log
        error_log("WEEKLY_API_CALLED: " . $request->path() . " | Year: " . $request->input('year') . " | Month: " . $request->input('month'));
        
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
                    
                    Log::info("WeeklySummary: Token decoded", [
                        'user_id_from_token' => $userId,
                    ]);
                    
                    if ($userId) {
                        $userId = is_string($userId) ? (int)$userId : $userId;
                        $user = \App\Models\User::find($userId);
                        
                        Log::info("WeeklySummary: User lookup result", [
                            'user_id' => $userId,
                            'user_found' => $user ? 'yes' : 'no',
                            'user_email' => $user ? $user->email : null,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                // Manual decode failed, try JWTAuth
                Log::warning("WeeklySummary: Manual decode exception", [
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
        
        // Fallback: Try Auth::user() for session-based authentication (web requests)
        // IMPORTANT: This block ONLY runs if no user was found from Bearer token
        // App requests (with Bearer token) will have $user set above, so this block is SKIPPED
        // Web requests (no Bearer token) will have $user = null, so this block executes
        if (!$user) {
            try {
                // CRITICAL: Manually start session for web requests
                // The 'web' middleware should handle this, but let's ensure it works
                $sessionCookieName = config('session.cookie', 'laravel_session');
                $sessionId = $request->cookie($sessionCookieName);
                
                // Also check all cookies to see what's available
                $allCookies = $request->cookies->all();
                
                Log::info("WeeklySummary: Session cookie check", [
                    'session_cookie_name' => $sessionCookieName,
                    'has_session_cookie' => $request->hasCookie($sessionCookieName),
                    'session_cookie_value' => $sessionId ? substr($sessionId, 0, 20) . '...' : null,
                    'has_session' => $request->hasSession(),
                    'all_cookie_names' => array_keys($allCookies),
                ]);
                
                // If session cookie exists but session not started, manually start it
                if ($sessionId && !$request->hasSession()) {
                    try {
                        // Get session manager and start session
                        $sessionManager = app('session');
                        $sessionStore = $sessionManager->driver();
                        $sessionStore->setId($sessionId);
                        $sessionStore->start();
                        $request->setLaravelSession($sessionStore);
                        
                        Log::info("WeeklySummary: Session manually started", [
                            'session_id' => $sessionStore->getId(),
                            'session_data_keys' => array_keys($sessionStore->all()),
                        ]);
                    } catch (\Exception $e) {
                        Log::warning("WeeklySummary: Could not manually start session", [
                            'error' => $e->getMessage(),
                            'session_id' => $sessionId,
                        ]);
                    }
                }
                
                // If session is now available, try to get user ID from session directly
                if ($request->hasSession()) {
                    $sessionUserId = $request->session()->get('login_web_' . sha1('App\Models\User'));
                    if (!$sessionUserId) {
                        // Try alternative session key formats
                        $sessionUserId = $request->session()->get('_token');
                        // Check all session keys
                        $sessionKeys = array_keys($request->session()->all());
                        Log::info("WeeklySummary: Session keys", [
                            'session_keys' => $sessionKeys,
                            'session_user_id_key' => 'login_web_' . sha1('App\Models\User'),
                        ]);
                    }
                }
                
                // Now try to get user from web session
                // Try web session auth first (for web requests)
                $user = Auth::guard('web')->user();
                
                if (!$user) {
                    // Then try default guard
                    $user = Auth::user();
                }
                
                if (!$user) {
                    // Finally try API guard
                    $user = Auth::guard('api')->user();
                }
                
                Log::info("WeeklySummary: Session auth check", [
                    'user_found' => $user ? 'yes' : 'no',
                    'user_id' => $user ? $user->id : null,
                    'has_session' => $request->hasSession(),
                    'has_session_cookie' => $request->hasCookie($sessionCookieName),
                    'session_id' => $request->hasSession() ? $request->session()->getId() : null,
                    'auth_guard_web' => Auth::guard('web')->check(),
                    'auth_guard_default' => Auth::check(),
                    'auth_guard_api' => Auth::guard('api')->check(),
                ]);
            } catch (\Exception $e) {
                Log::warning("WeeklySummary: Auth::user() exception", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
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
                    'has_session' => $request->hasSession(),
                    'has_session_cookie' => $request->hasCookie(config('session.cookie')),
                    'session_id' => $request->hasSession() ? $request->session()->getId() : null,
                    'auth_guard_web_check' => Auth::guard('web')->check(),
                    'auth_guard_default_check' => Auth::check(),
                    'auth_guard_api_check' => Auth::guard('api')->check(),
                ]);
                return response()->json([
                    'status' => true,
                    'debug' => array_merge($immediateDebug, [
                        'user_found' => false,
                        'user_id_from_token' => $userIdFromToken,
                        'has_token' => !empty($token),
                        'has_session' => $request->hasSession(),
                        'has_session_cookie' => $request->hasCookie(config('session.cookie')),
                        'session_id' => $request->hasSession() ? $request->session()->getId() : null,
                        'auth_guard_web_check' => Auth::guard('web')->check(),
                        'auth_guard_default_check' => Auth::check(),
                        'auth_guard_api_check' => Auth::guard('api')->check(),
                        'message' => 'No user found after all attempts. Check session/auth status above.',
                    ]),
                    'data' => [
                        'weeklySummaries' => [],
                        'validMonths' => [],
                        'validYears' => [],
                        'selectedYear' => (string)$request->input('year', now()->year),
                        'selectedMonth' => (string)$request->input('month', now()->month)
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
        Log::info("WeeklySummary: Before database query", [
            'user_id' => $user->id,
            'year' => $selectedYear,
            'year_type' => gettype($selectedYear),
            'month' => $selectedMonth,
            'month_type' => gettype($selectedMonth),
        ]);
        
        $summaryRows = WeeklySummary::where('user_id', $user->id)
            ->where('year', $selectedYear)
            ->where('month', $selectedMonth)
            ->orderBy('week_number')
            ->get();

        $keyedByWeekNumber = $summaryRows->keyBy('week_number');
        $byWeekLabel = $summaryRows->filter(fn ($s) => ! empty($s->week_label))->keyBy('week_label');

        Log::info("WeeklySummary: 📊 Database query result", [
            'user_id' => $user->id,
            'year' => $selectedYear,
            'month' => $selectedMonth,
            'summaries_found' => $summaryRows->count(),
            'summary_ids' => $summaryRows->pluck('id')->toArray(),
            'week_numbers' => $summaryRows->pluck('week_number')->toArray(),
            'raw_data' => $summaryRows->map(function ($s) {
                return ['id' => $s->id, 'week' => $s->week_number, 'has_summary' => ! empty($s->summary)];
            })->toArray(),
        ]);

        // CRITICAL: If no summaries found, log detailed warning
        if ($summaryRows->count() === 0) {
            // Try query without type casting to see if that's the issue
            $testQuery = WeeklySummary::where('user_id', $user->id)
                ->where('year', (string)$selectedYear)
                ->where('month', (string)$selectedMonth)
                ->count();
            
            Log::warning("WeeklySummary: ⚠️ NO SUMMARIES FOUND in database", [
                'user_id' => $user->id,
                'year' => $selectedYear,
                'month' => $selectedMonth,
                'test_query_with_strings' => $testQuery,
                'all_summaries_for_user' => WeeklySummary::where('user_id', $user->id)->count(),
            ]);
        }

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

        // Build week slots like the dashboard (completed weeks only; skip pre-registration weeks)
        $weeksInMonth = [];

        Log::info("WeeklySummary: Processing summaries (calendar slots)", [
            'summaries_count' => $summaryRows->count(),
            'week_numbers' => $summaryRows->pluck('week_number')->toArray(),
        ]);

        $weekNum = 1;
        $weekStart = $firstDay->copy()->startOfWeek(CarbonInterface::MONDAY)->startOfDay();

        while ($weekStart->lte($lastDay)) {
            $weekEnd = $weekStart->copy()->endOfWeek(CarbonInterface::SUNDAY)->endOfDay();

            if ($weekStart->gt($today) || $weekEnd->gt($today)) {
                break;
            }

            if ($weekEnd->lt($userRegistrationDate)) {
                $weekNum++;
                $weekStart->addWeek();

                continue;
            }

            $labelStr = $weekStart->format('M d').' - '.$weekEnd->format('M d');

            $fromNum = $keyedByWeekNumber->get($weekNum);
            $text = ($fromNum && trim((string) $fromNum->summary) !== '') ? $fromNum->summary : null;
            if ($text === null && ($alt = $byWeekLabel->get($labelStr))) {
                $text = trim((string) $alt->summary) !== '' ? $alt->summary : null;
            }

            $weeksInMonth[] = [
                'week' => (int) $weekNum,
                'weekLabel' => $labelStr,
                'summary' => $text,
            ];

            $weekNum++;
            $weekStart->addWeek();
        }

        Log::info("WeeklySummary: Finished processing summaries", [
            'total_weeks_added' => count($weeksInMonth),
        ]);
        
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
            'existing_summaries_count' => $summaryRows->count(),
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

        // Get summaries before keyBy to show in debug - query again to be sure
        $summariesBeforeKeyBy = WeeklySummary::where('user_id', $user->id)
            ->where('year', $selectedYear)
            ->where('month', $selectedMonth)
            ->orderBy('week_number')
            ->get();
        
        // Also get all summaries for this user to debug
        $allUserSummaries = WeeklySummary::where('user_id', $user->id)
            ->get(['id', 'year', 'month', 'week_number']);
        
        $responseData = [
            'status' => true,
            'debug' => array_merge($immediateDebug, [
                'message' => 'WeeklySummary API Response - DEBUG INFO',
                'user_details' => [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'user_name' => $user->name ?? 'N/A',
                    'user_created_at' => $user->created_at ? $user->created_at->toDateTimeString() : 'N/A',
                ],
                'query_params' => [
                    'selected_year' => $selectedYear,
                    'selected_year_type' => gettype($selectedYear),
                    'selected_month' => $selectedMonth,
                    'selected_month_type' => gettype($selectedMonth),
                ],
                'database_query_results' => [
                    'summaries_found_in_db' => $summariesBeforeKeyBy->count(),
                    'summaries_before_keyby' => $summariesBeforeKeyBy->map(function($s) {
                        return [
                            'id' => $s->id, 
                            'week_number' => $s->week_number, 
                            'year' => $s->year,
                            'month' => $s->month,
                            'has_summary' => !empty($s->summary),
                            'summary_length' => strlen($s->summary ?? '')
                        ];
                    })->toArray(),
                    'existing_summaries_after_keyby_count' => $keyedByWeekNumber->count(),
                    'existing_summaries_keys' => $keyedByWeekNumber->keys()->toArray(),
                ],
                'processing_results' => [
                    'weeks_in_result' => count($weeksInMonth),
                    'week_numbers_in_result' => array_column($weeksInMonth, 'week'),
                    'weeks_in_month_array' => $weeksInMonth,
                ],
                'all_user_summaries' => [
                    'total_count' => $allUserSummaries->count(),
                    'sample' => $allUserSummaries->take(10)->map(function($s) {
                        return ['id' => $s->id, 'year' => $s->year, 'month' => $s->month, 'week' => $s->week_number];
                    })->toArray(),
                ],
            ]),
            'data' => [
                'weeklySummaries' => array_values($weeksInMonth),
                'validMonths' => $validMonths,
                'validYears' => $validYears,
                'selectedYear' => (string)$selectedYear, // Convert to string for Flutter
                'selectedMonth' => (string)$selectedMonth // Convert to string for Flutter
            ]
        ];
        
        // CRITICAL: Log final response to verify data is being returned
        Log::info("WeeklySummary: 🎯 FINAL RESPONSE DATA", [
            'user_id' => $user->id,
            'weekly_summaries_count' => count($weeksInMonth),
            'summaries_found_in_db' => $summaryRows->count(),
            'week_numbers' => array_column($weeksInMonth, 'week'),
            'response_weekly_summaries_count' => count($responseData['data']['weeklySummaries']),
            'valid_months_count' => count($validMonths),
            'valid_years_count' => count($validYears),
            'debug_field_included' => isset($responseData['debug']),
        ]);
        Log::info("=== WeeklySummary API Response Complete ===");
        
        // CRITICAL: Ensure debug field is always included
        if (!isset($responseData['debug'])) {
            $responseData['debug'] = ['error' => 'Debug field missing - this should not happen'];
        }
        
        return response()->json($responseData);
    }
}
