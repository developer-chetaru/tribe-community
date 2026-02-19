<?php

namespace App\Services;

use App\Models\LoginSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class LoginSessionService
{
    /**
     * Parse user agent to extract device and browser info
     */
    protected function parseUserAgent($userAgent)
    {
        $data = [
            'os_name' => null,
            'os_version' => null,
            'browser_name' => null,
            'browser_version' => null,
            'device_name' => null,
        ];

        if (!$userAgent) {
            return $data;
        }

        // Parse OS
        if (preg_match('/Windows NT ([0-9.]+)/', $userAgent, $matches)) {
            $data['os_name'] = 'Windows';
            $data['os_version'] = $matches[1];
        } elseif (preg_match('/Mac OS X ([0-9_]+)/', $userAgent, $matches)) {
            $data['os_name'] = 'macOS';
            $data['os_version'] = str_replace('_', '.', $matches[1]);
        } elseif (preg_match('/Linux/', $userAgent)) {
            $data['os_name'] = 'Linux';
        } elseif (preg_match('/Android ([0-9.]+)/', $userAgent, $matches)) {
            $data['os_name'] = 'Android';
            $data['os_version'] = $matches[1];
        } elseif (preg_match('/iPhone OS ([0-9_]+)/', $userAgent, $matches)) {
            $data['os_name'] = 'iOS';
            $data['os_version'] = str_replace('_', '.', $matches[1]);
            $data['device_name'] = 'iPhone';
        } elseif (preg_match('/iPad.*OS ([0-9_]+)/', $userAgent, $matches)) {
            $data['os_name'] = 'iOS';
            $data['os_version'] = str_replace('_', '.', $matches[1]);
            $data['device_name'] = 'iPad';
        }

        // Parse Browser
        if (preg_match('/Chrome\/([0-9.]+)/', $userAgent, $matches)) {
            $data['browser_name'] = 'Chrome';
            $data['browser_version'] = $matches[1];
        } elseif (preg_match('/Firefox\/([0-9.]+)/', $userAgent, $matches)) {
            $data['browser_name'] = 'Firefox';
            $data['browser_version'] = $matches[1];
        } elseif (preg_match('/Safari\/([0-9.]+)/', $userAgent, $matches) && !preg_match('/Chrome/', $userAgent)) {
            $data['browser_name'] = 'Safari';
            $data['browser_version'] = $matches[1];
        } elseif (preg_match('/Edge\/([0-9.]+)/', $userAgent, $matches)) {
            $data['browser_name'] = 'Edge';
            $data['browser_version'] = $matches[1];
        }

        return $data;
    }

    /**
     * Get location info from IP (optional, can be slow)
     */
    protected function getLocationFromIP($ipAddress)
    {
        if (!$ipAddress || in_array($ipAddress, ['127.0.0.1', '::1', 'localhost'])) {
            return ['country' => null, 'city' => null, 'timezone' => null];
        }

        try {
            // Using ipapi.co (free tier: 1000 requests/day)
            $response = Http::timeout(2)->get("https://ipapi.co/{$ipAddress}/json/");
            
            if ($response->successful()) {
                $data = $response->json();
                return [
                    'country' => $data['country_name'] ?? null,
                    'city' => $data['city'] ?? null,
                    'timezone' => $data['timezone'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            Log::debug('Failed to get location from IP', [
                'ip' => $ipAddress,
                'error' => $e->getMessage(),
            ]);
        }

        return ['country' => null, 'city' => null, 'timezone' => null];
    }

    /**
     * Determine platform from request
     */
    protected function determinePlatform(Request $request, $deviceType = null)
    {
        // If deviceType is provided and it's mobile, it's mobile app
        if ($deviceType && in_array(strtolower($deviceType), ['ios', 'android'])) {
            return 'mobile';
        }

        // If it's an API request with device info, it's mobile
        if ($request->is('api/*') && ($request->deviceType || $request->deviceId)) {
            return 'mobile';
        }

        // If it's a web request with session, it's web
        if ($request->hasSession() || session()->isStarted()) {
            return 'web';
        }

        // Default to API for API routes
        if ($request->is('api/*')) {
            return 'api';
        }

        return 'web';
    }

    /**
     * Log a login session
     */
    public function logLogin(User $user, Request $request, $tokenId = null, $sessionId = null, $deviceType = null, $deviceId = null, $fcmToken = null)
    {
        try {
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();
            
            // Parse user agent
            $uaData = $this->parseUserAgent($userAgent);
            
            // Determine platform
            $platform = $this->determinePlatform($request, $deviceType);
            
            // Get location (optional, can be commented out if too slow)
            $location = $this->getLocationFromIP($ipAddress);
            
            // Get user timezone
            $userTimezone = \App\Helpers\TimezoneHelper::getUserTimezone($user) ?? 'UTC';

            $sessionData = [
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'token_id' => $tokenId,
                'platform' => $platform,
                'device_type' => $deviceType ?? ($platform === 'mobile' ? 'mobile' : 'web'),
                'device_id' => $deviceId,
                'device_name' => $uaData['device_name'],
                'os_name' => $uaData['os_name'],
                'os_version' => $uaData['os_version'],
                'browser_name' => $uaData['browser_name'],
                'browser_version' => $uaData['browser_version'],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'country' => $location['country'],
                'city' => $location['city'],
                'timezone' => $location['timezone'] ?? $userTimezone,
                'login_at' => now(),
                'fcm_token' => $fcmToken,
                'status' => 'active',
                'additional_data' => [
                    'org_id' => $user->orgId,
                    'role' => $user->getRoleNames()->first(),
                ],
            ];

            $session = LoginSession::create($sessionData);

            Log::info('Login session logged', [
                'session_id' => $session->id,
                'user_id' => $user->id,
                'platform' => $platform,
            ]);

            return $session;
        } catch (\Exception $e) {
            Log::error('Failed to log login session', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Log a logout session
     */
    public function logLogout($sessionId = null, $tokenId = null, $userId = null)
    {
        try {
            $query = LoginSession::where('status', 'active');

            if ($sessionId) {
                $query->where('session_id', $sessionId);
            } elseif ($tokenId) {
                $query->where('token_id', $tokenId);
            } elseif ($userId) {
                // Find the most recent active session for this user
                $query->where('user_id', $userId)
                      ->orderBy('login_at', 'desc');
            } else {
                return null;
            }

            $session = $query->first();

            if ($session) {
                $session->logout_at = now();
                $session->status = 'logged_out';
                
                // Calculate duration before saving
                if ($session->login_at) {
                    $session->session_duration_seconds = $session->login_at->diffInSeconds($session->logout_at);
                }
                
                $session->save();

                Log::info('Logout session logged', [
                    'session_id' => $session->id,
                    'user_id' => $session->user_id,
                    'duration_seconds' => $session->session_duration_seconds,
                    'duration_formatted' => $session->formatted_duration,
                ]);

                return $session;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to log logout session', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get active sessions for a user
     */
    public function getActiveSessions($userId)
    {
        return LoginSession::where('user_id', $userId)
            ->where('status', 'active')
            ->orderBy('login_at', 'desc')
            ->get();
    }

    /**
     * Get session history for a user
     */
    public function getSessionHistory($userId, $limit = 50)
    {
        return LoginSession::where('user_id', $userId)
            ->orderBy('login_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Logout all active sessions for a user (fallback method)
     */
    public function logLogoutAllActiveSessions($userId)
    {
        try {
            $activeSessions = LoginSession::where('user_id', $userId)
                ->where('status', 'active')
                ->get();

            foreach ($activeSessions as $session) {
                $session->logout_at = now();
                $session->status = 'logged_out';
                
                if ($session->login_at) {
                    $session->session_duration_seconds = $session->login_at->diffInSeconds($session->logout_at);
                }
                
                $session->save();
            }

            Log::info('Logged out all active sessions for user', [
                'user_id' => $userId,
                'sessions_logged_out' => $activeSessions->count(),
            ]);

            return $activeSessions->count();
        } catch (\Exception $e) {
            Log::error('Failed to logout all active sessions', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }
}
