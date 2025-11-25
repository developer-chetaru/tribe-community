<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OneSignalService
{
    protected string $appId;
    protected string $restApiKey;
    protected string $userAuthKey;

    /**
     * Constructor — initialize config values from services.php
     */
    public function __construct()
    {
        $this->appId = config('services.onesignal.app_id');
        $this->restApiKey = config('services.onesignal.rest_api_key');
        $this->userAuthKey = (string) config('services.onesignal.user_auth_key', $this->restApiKey);
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

        $payload = [
            'app_id' => $this->appId,
            'include_player_ids' => $playerIds,
            'headings' => ['en' => $title],
            'contents' => ['en' => $message],
        ];

        $response = Http::withHeaders([
            'Authorization' => "Basic {$this->restApiKey}",
            'Content-Type'  => 'application/json',
        ])->post('https://onesignal.com/api/v1/notifications', $payload);

        return $response->json();
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
        $registerResponse = Http::withHeaders([
            'Authorization' => "Basic {$this->restApiKey}",
            'Content-Type'  => 'application/json',
        ])->post("https://api.onesignal.com/apps/{$this->appId}/users", $registerPayload);
        if ($registerResponse->failed()) {
            Log::warning('OneSignal email register failed', [
                'email' => $email,
                'status' => $registerResponse->status(),
                'body' => $registerResponse->body(),
            ]);
        } else {
            Log::info('OneSignal user registered for email', [
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
            $sendResponse = Http::withHeaders([
                'Authorization' => "Basic {$this->restApiKey}",
                'Content-Type'  => 'application/json',
            ])->post('https://onesignal.com/api/v1/notifications', $sendPayload);
            if ($sendResponse->failed()) {
                Log::error('OneSignal email send failed', [
                    'email' => $email,
                    'status' => $sendResponse->status(),
                    'body' => $sendResponse->body(),
                ]);
                // :x: REMOVE Laravel fallback email
                // No Mail::raw() → avoids SMTP error completely
                return false;
            }
            Log::info('OneSignal email sent successfully', [
                'email' => $email,
                'response' => $sendResponse->json(),
            ]);
        }
        return true;
    } catch (\Throwable $e) {
        Log::error('registerEmailUserFallback() failed', [
            'email' => $email,
            'error' => $e->getMessage(),
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
}
