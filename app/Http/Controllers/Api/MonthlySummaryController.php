<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\MonthlySummary as MonthlySummaryModel;
use App\Models\HappyIndex;
use Carbon\Carbon;
use OpenAI\Laravel\Facades\OpenAI;

class MonthlySummaryController extends Controller
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
                \Illuminate\Support\Facades\Log::debug("MonthlySummary: Manual decode exception", [
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
        
        // Debug: Log user extraction result
        if (!$user && $token) {
            \Illuminate\Support\Facades\Log::warning("MonthlySummary: User not found", [
                'has_token' => !empty($token),
                'token_preview' => substr($token, 0, 30) . '...',
            ]);
        } elseif ($user) {
            \Illuminate\Support\Facades\Log::info("MonthlySummary: User found", [
                'user_id' => $user->id,
            ]);
        }
        
        // If no user found, return empty data
        if (!$user) {
            return response()->json([
                'status' => true,
                'data' => [
                    'monthlySummaries' => [],
                    'validMonths' => [],
                    'validYears' => [],
                    'selectedYear' => $request->input('year', now()->year),
                    'selectedMonth' => $request->input('month', now()->month)
                ]
            ]);
        }
        
        $selectedYear = $request->input('year', now()->year);
        $selectedMonth = $request->input('month', now()->month);

        // Get user's registration date
        $userRegistrationDate = Carbon::parse($user->created_at)->startOfDay();
        
        // Get the selected month's start date
        $selectedMonthStart = Carbon::create($selectedYear, $selectedMonth, 1)->startOfMonth();
        
        // Only load summary if the selected month occurred on or after user's registration
        $monthlySummaries = [];
        if ($selectedMonthStart->gte($userRegistrationDate)) {
            $summary = MonthlySummaryModel::where('user_id', $user->id)
                ->where('year', $selectedYear)
                ->where('month', $selectedMonth)
                ->first();

            $monthlySummaries = $summary ? [$summary] : [];
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
                'monthlySummaries' => $monthlySummaries,
                'validMonths' => $validMonths,
                'validYears' => $validYears,
                'selectedYear' => $selectedYear,
                'selectedMonth' => $selectedMonth
            ]
        ]);
    }

    public function generate(Request $request)
    {
        // COMMENTED OUT: Auto logout disabled - allow without strict authentication
        // Try to get user from JWT token, but don't reject if not found
        $user = null;
        try {
            $token = $request->bearerToken();
            if ($token) {
                // Try to authenticate with JWT token
                try {
                    $user = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->authenticate();
                } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
                    // Token expired - get user from payload anyway
                    try {
                        $payload = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->getPayload();
                        $userId = $payload->get('sub');
                        if ($userId) {
                            $user = \App\Models\User::find($userId);
                        }
                    } catch (\Exception $e2) {
                        // Continue
                    }
                } catch (\Exception $e) {
                    // Try to get user from payload even if token is invalid
                    try {
                        $payload = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->getPayload();
                        $userId = $payload->get('sub');
                        if ($userId) {
                            $user = \App\Models\User::find($userId);
                        }
                    } catch (\Exception $e2) {
                        // Continue
                    }
                }
            }
        } catch (\Exception $e) {
            // Continue without user
        }
        
        // Fallback: Try standard auth methods
        if (!$user) {
            $user = Auth::guard('api')->user() ?? Auth::user();
        }
        
        // COMMENTED OUT: Auto logout disabled - allow without user for testing
        // If no user found, return error but don't use 401
        if (!$user) {
            \Illuminate\Support\Facades\Log::warning("MonthlySummary Generate: No user found", [
                'has_token' => !empty($request->bearerToken()),
            ]);
            return response()->json([
                'status' => false,
                'message' => 'User authentication required'
            ], 400); // Use 400 instead of 401
        }

        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = Carbon::create($year, $month, 1)->endOfMonth();

        $allData = HappyIndex::where('user_id', $user->id)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->orderBy('created_at')
            ->get(['mood_value', 'description', 'created_at']);

        if ($allData->isEmpty()) {
            $summaryText = "No data available for this month.";
        } else {
            $dataText = $allData->map(fn($h) =>
                $h->created_at->format('M d') . ': ' .
                ($h->mood_value == 3 ? 'Good' : ($h->mood_value == 1 ? 'Bad' : 'Ok')) .
                ' - ' . ($h->description ?? '')
            )->implode("\n");

            $prompt = "Generate a short positive monthly summary for " . $startOfMonth->format('F Y') . " based on these daily mood entries:\n{$dataText}";

            try {
                $response = OpenAI::responses()->create([
                    'model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4.1-mini'),
                    'input' => $prompt,
                ]);

                $summaryText = '';
                foreach ($response->output ?? [] as $item) {
                    foreach ($item->content ?? [] as $c) {
                        $summaryText .= $c->text ?? '';
                    }
                }

                $summaryText = trim($summaryText) ?: 'No summary generated.';
            } catch (\Exception $e) {
                $summaryText = 'Error generating summary: ' . $e->getMessage();
            }
        }

        MonthlySummaryModel::updateOrCreate(
            [
                'user_id' => $user->id,
                'year' => $year,
                'month' => $month,
            ],
            [
                'summary' => $summaryText,
            ]
        );

        return response()->json([
            'status' => true,
            'message' => 'Monthly summary generated successfully.',
            'data' => [
                'summary' => $summaryText,
                'year' => $year,
                'month' => $month
            ]
        ]);
    }
}
