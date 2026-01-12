<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OneSignalService
{
    protected ?string $appId;
    protected ?string $restApiKey;
    protected ?string $userAuthKey;

    /**
     * Constructor — initialize config values from services.php
     */
    public function __construct()
    {
        $this->appId = config('services.onesignal.app_id') ?? '';
        $this->restApiKey = config('services.onesignal.rest_api_key') ?? '';
        $this->userAuthKey = (string) (config('services.onesignal.user_auth_key') ?? $this->restApiKey);
    }

    /**
     * Basic email registration & test notification
     */
    public function registerEmailUser(string $email, $userId = null)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::error('❌ Invalid email format for OneSignal registration', ['email' => $email]);
            return false;
        }

        $emailHtml = view('emails.welcome-user')->render();

        $payload = [
            'app_id' => $this->appId,
            'include_email_tokens' => [$email],
            'email_subject' => 'Welcome to our platform',
            'email_body' => $emailHtml,
        ];

        $response = Http::withHeaders([
            'Authorization' => "Basic {$this->restApiKey}",
            'Content-Type'  => 'application/json',
        ])->post('https://onesignal.com/api/v1/notifications', $payload);

        if ($response->failed()) {
            Log::error('❌ OneSignal email send failed', [
                'email' => $email,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        }

        Log::info('✅ Email notification sent via OneSignal', [
            'email' => $email,
            'response' => $response->json(),
        ]);

        return $response->json();
    }

    /**
     * Register Push Device
     */
    public function registerPushDevice($userId, string $deviceToken, string $deviceType = 'web')
    {
        $payload = [
            'app_id'           => $this->appId,
            'identifier'       => $deviceToken,
            'device_type'      => $deviceType === 'android' ? 1 : ($deviceType === 'ios' ? 0 : 5),
            'external_user_id' => (string) $userId,
        ];

        $response = Http::withHeaders([
            'Authorization' => "Basic {$this->restApiKey}",
            'Content-Type'  => 'application/json',
        ])->post('https://api.onesignal.com/api/v1/players', $payload);

        if ($response->failed()) {
            Log::error('❌ OneSignal push register failed', [
                'user_id' => $userId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        }

        Log::info('✅ Push device added to OneSignal', [
            'user_id' => $userId,
            'response' => $response->json(),
        ]);

        return $response->json();
    }

    /**
     * Send push notification
     */
    public function sendNotification($title, $message = '', $playerIds = [])
    {
        if (is_array($title)) {
            $playerIds = $title;
            $title = 'Notification';
        }

        // Validate OneSignal configuration
        if (empty($this->appId) || empty($this->restApiKey)) {
            Log::error('❌ OneSignal not configured', [
                'has_app_id' => !empty($this->appId),
                'has_api_key' => !empty($this->restApiKey),
            ]);
            return ['errors' => ['OneSignal not properly configured']];
        }

        // Validate player IDs
        if (empty($playerIds) || !is_array($playerIds)) {
            Log::warning('⚠️ No player IDs provided for OneSignal notification');
            return ['errors' => ['No player IDs provided']];
        }

        $payload = [
            'app_id' => $this->appId,
            'include_player_ids' => $playerIds,
            'headings' => ['en' => $title],
            'contents' => ['en' => $message],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => "Basic {$this->restApiKey}",
                'Content-Type'  => 'application/json',
            ])->timeout(30)->post('https://onesignal.com/api/v1/notifications', $payload);

            $responseData = $response->json();

            if ($response->failed()) {
                Log::error('❌ OneSignal API request failed', [
                    'status' => $response->status(),
                    'response' => $responseData,
                    'player_count' => count($playerIds),
                ]);
            } else {
                Log::info('✅ OneSignal notification sent', [
                    'onesignal_id' => $responseData['id'] ?? null,
                    'player_count' => count($playerIds),
                ]);
            }

            return $responseData;
        } catch (\Throwable $e) {
            Log::error('❌ OneSignal send exception', [
                'error' => $e->getMessage(),
                'player_count' => count($playerIds),
            ]);
            return ['errors' => [$e->getMessage()]];
        }
    }

    /**
     * Send Forgot Password Email
     */

	public function sendForgotPasswordEmail(string $email, string $emailHtml)
{
    $subject = "Reset Your Tribe365 Password";

    $payload = [
        'app_id' => $this->appId,
        'include_email_tokens' => [$email],
        'email_subject' => $subject,
        'email_body' => $emailHtml, // ⬅ FULL HTML FROM YOUR BLADE
    ];

    $response = Http::withHeaders([
        'Authorization' => "Basic {$this->restApiKey}",
        'Content-Type'  => 'application/json',
    ])->post('https://onesignal.com/api/v1/notifications', $payload);

    if ($response->failed()) {
        Log::error('❌ OneSignal forgot-password email failed', [
            'email' => $email,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
        return false;
    }

    Log::info('✅ Reset Email Sent via OneSignal', [
        'email' => $email,
        'response' => $response->json(),
    ]);

    return true;
}
    public function sendForgotPasswordEmailOLD(string $email, string $resetUrl)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('⚠️ Invalid email format for OneSignal Forgot Password', ['email' => $email]);
            return false;
        }

        $subject = 'Reset Your Password';
        $body = "
            <div style='font-family:Arial,sans-serif;'>
                <p>Hi,</p>
                <p>We received a request to reset your password. Please click below:</p>
                <p><a href='{$resetUrl}' style='background:#EB1C24;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;'>Reset Password</a></p>
                <p>If you didn’t request this, please ignore this email.</p>
                <br><p>Thanks,<br><strong>" . config('app.name') . "</strong></p>
            </div>
        ";

        $payload = [
            'app_id' => $this->appId,
            'include_email_tokens' => [$email],
            'email_subject' => $subject,
            'email_body' => $body,
        ];

        $response = Http::withHeaders([
            'Authorization' => "Basic {$this->restApiKey}",
            'Content-Type'  => 'application/json',
        ])->post('https://onesignal.com/api/v1/notifications', $payload);

        if ($response->failed()) {
            Log::error('❌ OneSignal forgot-password email failed', [
                'email' => $email,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        }

        Log::info('✅ OneSignal forgot-password email sent', [
            'email' => $email,
            'response' => $response->json(),
        ]);

        return $response->json();
    }

    /**
     * Send Welcome Email
     */
    public function sendWelcomeEmail(string $email, string $firstName = '')
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('⚠️ Invalid email format for OneSignal welcome email', ['email' => $email]);
            return false;
        }

        $subject = 'Welcome to ' . config('app.name') . ' 🎉';
        $body = "
            <div style='font-family:Arial,sans-serif;'>
                <h2 style='color:#EB1C24;'>Welcome aboard, {$firstName}!</h2>
                <p>We’re excited to have you join <strong>" . config('app.name') . "</strong>.</p>
                <p>Explore your account and get started today!</p>
                <p style='margin-top:20px;'>Best regards,<br><strong>The " . config('app.name') . " Team</strong></p>
            </div>
        ";

        $payload = [
            'app_id' => $this->appId,
            'include_email_tokens' => [$email],
            'email_subject' => $subject,
            'email_body' => $body,
        ];

        $response = Http::withHeaders([
            'Authorization' => "Basic {$this->restApiKey}",
            'Content-Type'  => 'application/json',
        ])->post('https://onesignal.com/api/v1/notifications', $payload);

        if ($response->failed()) {
            Log::error('❌ OneSignal welcome email failed', [
                'email' => $email,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        }

        Log::info('✅ OneSignal welcome email sent', [
            'email' => $email,
            'response' => $response->json(),
        ]);

        return $response->json();
    }

    /**
     * Unified Email Registration + Sending with Fallback
     */
    public function registerEmailUserFallback(string $email, $userId = null, ?array $payload = null)
    {
        try {
            // Duplicate email prevention - check if same email was sent in last 60 seconds
            if ($payload && isset($payload['subject'])) {
                $cacheKey = 'email_sent_' . md5($email . $payload['subject']);
                if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                    Log::info('📧 Duplicate email prevented (sent within last 60 seconds)', [
                        'email' => $email,
                        'subject' => $payload['subject'],
                    ]);
                    return true; // Return true to not trigger error handling
                }
                // Mark email as sent for 60 seconds
                \Illuminate\Support\Facades\Cache::put($cacheKey, true, 60);
            }
            
            Log::info('📧 registerEmailUserFallback() called', [
                'email' => $email,
                'user_id' => $userId,
                'has_payload' => !empty($payload),
                'app_id' => $this->appId,
                'has_api_key' => !empty($this->restApiKey),
            ]);

            // Register email in OneSignal
            $registerPayload = [
                'subscriptions' => [
                    [
                        'type'  => 'email',
                        'token' => $email,
                    ],
                ],
                'alias_label' => 'external_id',
                'alias_id'    => (string) ($userId ?? uniqid('user_')),
            ];

            Log::info('📧 Registering user in OneSignal', ['payload' => $registerPayload]);

            $registerResponse = Http::withHeaders([
                'Authorization' => "Basic {$this->restApiKey}",
                'Content-Type'  => 'application/json',
            ])->post("https://api.onesignal.com/apps/{$this->appId}/users", $registerPayload);

            if ($registerResponse->failed()) {
                Log::warning('⚠️ OneSignal email register failed', [
                    'email' => $email,
                    'status' => $registerResponse->status(),
                    'body' => $registerResponse->body(),
                ]);
            } else {
                Log::info('✅ OneSignal user registered for email', [
                    'email' => $email,
                    'response' => $registerResponse->json(),
                ]);
            }

            // If OneSignal email payload exists → send email
            if ($payload && isset($payload['subject'], $payload['body'])) {
                $sendPayload = [
                    'app_id' => $this->appId,
                    'include_email_tokens' => [$email],
                    'email_subject' => $payload['subject'],
                    'email_body' => $payload['body'],
                ];

                Log::info('📧 Sending email via OneSignal', [
                    'email' => $email,
                    'subject' => $payload['subject'],
                ]);

                $sendResponse = Http::withHeaders([
                    'Authorization' => "Basic {$this->restApiKey}",
                    'Content-Type'  => 'application/json',
                ])->post('https://onesignal.com/api/v1/notifications', $sendPayload);

                if ($sendResponse->failed()) {
                    Log::error('❌ OneSignal email send failed', [
                        'email' => $email,
                        'status' => $sendResponse->status(),
                        'body' => $sendResponse->body(),
                    ]);
                    return false;
                }

                Log::info('✅ OneSignal email sent successfully', [
                    'email' => $email,
                    'response' => $sendResponse->json(),
                ]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('❌ registerEmailUserFallback() failed', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

	public function sendEmailMessage(string $email, string $subject, string $html)
	{
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			\Log::error("❌ Invalid email for OneSignal email send", ['email' => $email]);
			return false;
		}

		$payload = [
			'app_id' => $this->appId,
			'include_email_tokens' => [$email],
			'email_subject' => $subject,
			'email_body' => $html,
		];

		$response = Http::withHeaders([
			'Authorization' => "Basic {$this->restApiKey}",
			'Content-Type'  => 'application/json',
		])->post('https://onesignal.com/api/v1/notifications', $payload);

		if ($response->failed()) {
			\Log::error("❌ OneSignal custom email failed", [
				'email' => $email,
				'status' => $response->status(),
				'response' => $response->body()
			]);
			return false;
		}

		\Log::info("📧 OneSignal custom email sent", [
			'email' => $email,
			'subject' => $subject
		]);

		return $response->json();
	}

    // =========================================================================
    // TAG MANAGEMENT FOR AUTOMATION (Backend updates these, OneSignal automates)
    // =========================================================================

    /**
     * Update user tags by external_id (your app's user ID)
     * Use this on login, sentiment submission, etc.
     *
     * @param int|string $userId Your app's user ID
     * @param array $tags Key-value pairs
     * @return bool
     */
    public function updateUserTags($userId, array $tags): bool
    {
        $externalId = "user_{$userId}";

        $response = Http::withHeaders([
            'Authorization' => "Basic {$this->restApiKey}",
            'Content-Type'  => 'application/json',
        ])->patch("https://api.onesignal.com/apps/{$this->appId}/users/by/external_id/{$externalId}", [
            'properties' => [
                'tags' => $tags,
            ],
        ]);

        if ($response->failed()) {
            Log::error('❌ OneSignal updateUserTags failed', [
                'user_id' => $userId,
                'external_id' => $externalId,
                'tags' => $tags,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        }

        Log::info('✅ OneSignal tags updated', [
            'user_id' => $userId,
            'external_id' => $externalId,
            'tags' => $tags,
        ]);

        return true;
    }

    /**
     * Set initial tags on user login/registration
     * Call this when user logs in or opens app
     *
     * @param \App\Models\User $user
     * @return bool
     */
    public function setUserTagsOnLogin($user): bool
    {
        // Determine user type
        $userType = $user->orgId ? 'org' : 'basecamp';

        // Check if today is a working day for this user (default: true)
        $hasWorkingToday = $this->isWorkingDayToday($user);

        // Check if user submitted sentiment today
        $hasSubmittedToday = \App\Models\HappyIndex::where('user_id', $user->id)
            ->whereDate('created_at', now()->toDateString())
            ->exists();

        // Combine first_name and last_name for full name
        $fullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        $tags = [
            'user_type' => $userType,
            'has_working_today' => $hasWorkingToday ? 'true' : 'false',
            'timezone' => \App\Helpers\TimezoneHelper::getUserTimezone($user),
            'has_submitted_today' => $hasSubmittedToday ? 'true' : 'false',
            'email_subscribed' => 'true',
            'status' => (string) $user->status,
            'name' => $fullName ?: 'Unknown',
            'first_name' => $user->first_name ?? '',
            'last_name' => $user->last_name ?? '',
        ];

        // First, ensure user exists in OneSignal with external_id
        $this->createOrUpdateOneSignalUser($user, $tags);

        return true;
    }

    /**
     * Check if today is a working day for the user
     *
     * @param \App\Models\User $user
     * @return bool Default: true
     */
    public function isWorkingDayToday($user): bool
    {
        // Default: true
        $defaultWorkingDay = true;

        // Basecamp users: working every day
        if (!$user->organisation) {
            return true;
        }

        // Org users: check working_days
        if (!$user->organisation->working_days) {
            return $defaultWorkingDay;
        }

        $workingDays = is_string($user->organisation->working_days)
            ? json_decode($user->organisation->working_days, true)
            : $user->organisation->working_days;

        if (empty($workingDays)) {
            return $defaultWorkingDay;
        }

        // Get today's day name (Mon, Tue, Wed, etc.)
        $todayName = \App\Helpers\TimezoneHelper::carbon(null, $user->timezone)->format('D');

        // Check if today is in working days array
        return in_array($todayName, $workingDays);
    }

    /**
     * Create or update user in OneSignal with external_id and tags
     * This ensures the user exists before we try to update tags
     *
     * @param \App\Models\User $user
     * @param array $tags
     * @return bool
     */
    public function createOrUpdateOneSignalUser($user, array $tags = []): bool
    {
        $externalId = "user_{$user->id}";

        // Build subscriptions array
        $subscriptions = [];

        // Add push subscription if fcmToken exists
        if (!empty($user->fcmToken)) {
            $deviceType = match($user->deviceType) {
                'android' => 1,
                'ios' => 0,
                default => 5, // web
            };
            $subscriptions[] = [
                'type' => $deviceType === 1 ? 'AndroidPush' : ($deviceType === 0 ? 'iOSPush' : 'ChromePush'),
                'token' => $user->fcmToken,
            ];
        }

        // Add email subscription
        if (!empty($user->email)) {
            $subscriptions[] = [
                'type' => 'Email',
                'token' => $user->email,
            ];
        }

        $payload = [
            'properties' => [
                'tags' => $tags,
                'timezone_id' => $user->timezone ?? 'Asia/Kolkata',
            ],
            'identity' => [
                'external_id' => $externalId,
            ],
        ];

        if (!empty($subscriptions)) {
            $payload['subscriptions'] = $subscriptions;
        }

        // Use POST to create/update user
        $response = Http::withHeaders([
            'Authorization' => "Basic {$this->restApiKey}",
            'Content-Type' => 'application/json',
        ])->post("https://api.onesignal.com/apps/{$this->appId}/users", $payload);

        if ($response->failed()) {
            // If user exists, try PATCH to update
            $patchResponse = Http::withHeaders([
                'Authorization' => "Basic {$this->restApiKey}",
                'Content-Type' => 'application/json',
            ])->patch("https://api.onesignal.com/apps/{$this->appId}/users/by/external_id/{$externalId}", [
                'properties' => [
                    'tags' => $tags,
                    'timezone_id' => $user->timezone ?? 'Asia/Kolkata',
                ],
            ]);

            if ($patchResponse->failed()) {
                Log::error('❌ OneSignal createOrUpdateUser failed', [
                    'user_id' => $user->id,
                    'external_id' => $externalId,
                    'post_status' => $response->status(),
                    'post_body' => $response->body(),
                    'patch_status' => $patchResponse->status(),
                    'patch_body' => $patchResponse->body(),
                ]);
                return false;
            }

            Log::info('✅ OneSignal user updated (PATCH)', [
                'user_id' => $user->id,
                'external_id' => $externalId,
                'tags' => $tags,
            ]);
            return true;
        }

        Log::info('✅ OneSignal user created/updated', [
            'user_id' => $user->id,
            'external_id' => $externalId,
            'tags' => $tags,
            'response' => $response->json(),
        ]);

        return true;
    }

    /**
     * Mark sentiment as submitted (call after user submits sentiment)
     *
     * @param int|string $userId
     * @return bool
     */
    public function markSentimentSubmitted($userId): bool
    {
        return $this->updateUserTags($userId, [
            'has_submitted_today' => 'true',
        ]);
    }

    /**
     * Reset has_submitted_today tag (for daily reset)
     * Can be called via OneSignal webhook or a simple midnight cron
     *
     * @param int|string $userId
     * @return bool
     */
    public function resetDailySentimentTag($userId): bool
    {
        return $this->updateUserTags($userId, [
            'has_submitted_today' => 'false',
        ]);
    }

    /**
     * Bulk reset all users' has_submitted_today tag
     * Call this at midnight (simple cron, no notification logic)
     *
     * @param array $userIds Array of user IDs
     * @return int Number of successful updates
     */
    public function bulkResetDailySentimentTags(array $userIds): int
    {
        $successCount = 0;
        foreach ($userIds as $userId) {
            if ($this->resetDailySentimentTag($userId)) {
                $successCount++;
            }
        }
        Log::info("✅ Bulk reset sentiment tags", [
            'total' => count($userIds),
            'success' => $successCount,
        ]);
        return $successCount;
    }

    /**
     * Reset has_submitted_today tag to false for all active users
     * Runs daily at midnight via cron to reset the tag for a new day
     * Creates users in OneSignal if they don't exist
     *
     * @return array Stats: ['total' => int, 'success' => int, 'failed' => int]
     */
    public function resetAllUsersSentimentTag(): array
    {
        // Status is ENUM: 'pending_payment', 'active_unverified', 'active_verified', 'suspended', 'cancelled', 'inactive'
        // Only reset tags for active users (not suspended, cancelled, or inactive)
        $users = \App\Models\User::whereIn('status', ['active_verified', 'active_unverified', 'pending_payment'])
            ->get();

        $stats = [
            'total' => $users->count(),
            'success' => 0,
            'failed' => 0,
        ];

        Log::info('Starting daily has_submitted_today tag reset', [
            'total_users' => $stats['total'],
        ]);

        foreach ($users as $user) {
            try {
                // Reset has_submitted_today to false for all users (new day starts)
                $result = $this->resetDailySentimentTag($user->id);

                if ($result) {
                    $stats['success']++;
                    Log::info('has_submitted_today reset to false', [
                        'user_id' => $user->id,
                    ]);
                } else {
                    $stats['failed']++;
                    Log::warning('has_submitted_today reset failed', [
                        'user_id' => $user->id,
                    ]);
                }
            } catch (\Throwable $e) {
                $stats['failed']++;
                Log::error('has_submitted_today reset exception', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Daily has_submitted_today tag reset completed', $stats);

        return $stats;
    }

    /**
     * Update user timezone tag
     *
     * @param int|string $userId
     * @param string $timezone
     * @return bool
     */
    public function updateUserTimezone($userId, string $timezone): bool
    {
        return $this->updateUserTags($userId, [
            'timezone' => $timezone,
        ]);
    }

    /**
     * Update email subscription status
     *
     * @param int|string $userId
     * @param bool $subscribed
     * @return bool
     */
    public function updateEmailSubscription($userId, bool $subscribed): bool
    {
        return $this->updateUserTags($userId, [
            'email_subscribed' => $subscribed ? 'true' : 'false',
        ]);
    }

    /**
     * Update has_working_today tag for all users
     * Runs daily via cron to check if today is a working day for each user
     * Creates users in OneSignal if they don't exist
     *
     * @return array Stats: ['total' => int, 'success' => int, 'failed' => int]
     */
    public function updateAllUsersWorkingDayStatus(): array
    {
        // Status is ENUM: 'pending_payment', 'active_unverified', 'active_verified', 'suspended', 'cancelled', 'inactive'
        // Only update tags for active users (not suspended, cancelled, or inactive)
        $users = \App\Models\User::whereIn('status', ['active_verified', 'active_unverified', 'pending_payment'])
            ->with('organisation')
            ->get();

        $stats = [
            'total' => $users->count(),
            'success' => 0,
            'failed' => 0,
        ];

        Log::info('Starting daily has_working_today tag update', [
            'total_users' => $stats['total'],
        ]);

        foreach ($users as $user) {
            try {
                // Use setUserTagsOnLogin which creates user if doesn't exist and updates all tags
                // This ensures the user exists in OneSignal with all current tag values
                $result = $this->setUserTagsOnLogin($user);

                if ($result) {
                    $stats['success']++;
                    $isWorkingDay = $this->isWorkingDayToday($user);
                    Log::info('has_working_today updated (user created/updated in OneSignal)', [
                        'user_id' => $user->id,
                        'has_working_today' => $isWorkingDay,
                    ]);
                } else {
                    $stats['failed']++;
                    Log::warning('has_working_today update failed', [
                        'user_id' => $user->id,
                    ]);
                }
            } catch (\Throwable $e) {
                $stats['failed']++;
                Log::error('has_working_today update exception', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Daily has_working_today tag update completed', $stats);

        return $stats;
    }

    // =========================================================================
    // END TAG MANAGEMENT
    // =========================================================================

    /**
     * Remove/Delete Push Device from OneSignal
     * 
     * @param string $playerId The OneSignal player ID (device token)
     * @return bool
     */
    public function removePushDevice(string $playerId): bool
    {
        if (empty($playerId)) {
            Log::warning('⚠️ Empty player ID provided for OneSignal device removal');
            return false;
        }

        try {
            // Delete player device from OneSignal
            // Note: OneSignal delete endpoint format may vary, but clearing DB token is primary fix
            $response = Http::withHeaders([
                'Authorization' => "Basic {$this->restApiKey}",
                'Content-Type'  => 'application/json',
            ])->delete("https://api.onesignal.com/api/v1/players/{$playerId}?app_id={$this->appId}");

            if ($response->failed()) {
                Log::warning('⚠️ OneSignal device removal failed', [
                    'player_id' => $playerId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            Log::info('✅ Push device removed from OneSignal', [
                'player_id' => $playerId,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('❌ OneSignal device removal exception', [
                'player_id' => $playerId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Login user to OneSignal by associating device with external_user_id
     * Equivalent to OneSignal.login("user_{userId}") on client-side
     * 
     * @param string $playerId The OneSignal player ID (device token/fcmToken)
     * @param int $userId The user ID
     * @return bool
     */
    public function loginUser(string $playerId, int $userId): bool
    {
        if (empty($playerId) || empty($userId)) {
            Log::warning('⚠️ Empty player ID or user ID provided for OneSignal login');
            return false;
        }

        try {
            $externalUserId = "user_{$userId}";
            
            $payload = [
                'app_id' => $this->appId,
                'external_user_id' => $externalUserId,
            ];

            $response = Http::withHeaders([
                'Authorization' => "Basic {$this->restApiKey}",
                'Content-Type'  => 'application/json',
            ])->put("https://api.onesignal.com/api/v1/players/{$playerId}", $payload);

            if ($response->failed()) {
                Log::warning('⚠️ OneSignal user login failed', [
                    'player_id' => $playerId,
                    'user_id' => $userId,
                    'external_user_id' => $externalUserId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            Log::info('✅ OneSignal user logged in', [
                'player_id' => $playerId,
                'user_id' => $userId,
                'external_user_id' => $externalUserId,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('❌ OneSignal user login exception', [
                'player_id' => $playerId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Logout user from OneSignal by clearing external_user_id
     * Equivalent to OneSignal.logout() on client-side
     * 
     * @param string $playerId The OneSignal player ID (device token/fcmToken)
     * @return bool
     */
    public function logoutUser(string $playerId): bool
    {
        if (empty($playerId)) {
            Log::warning('⚠️ Empty player ID provided for OneSignal logout');
            return false;
        }

        try {
            // Clear external_user_id by setting it to null
            $payload = [
                'app_id' => $this->appId,
                'external_user_id' => null,
            ];

            $response = Http::withHeaders([
                'Authorization' => "Basic {$this->restApiKey}",
                'Content-Type'  => 'application/json',
            ])->put("https://api.onesignal.com/api/v1/players/{$playerId}", $payload);

            if ($response->failed()) {
                Log::warning('⚠️ OneSignal user logout failed', [
                    'player_id' => $playerId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            Log::info('✅ OneSignal user logged out', [
                'player_id' => $playerId,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('❌ OneSignal user logout exception', [
                'player_id' => $playerId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
