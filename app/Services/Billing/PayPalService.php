<?php

namespace App\Services\Billing;

use GuzzleHttp\Client;
use App\Models\Organisation;
use App\Models\SubscriptionRecord;
use App\Models\PaymentRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PayPalService
{
    protected $client;
    protected $clientId;
    protected $clientSecret;
    protected $apiBaseUrl;
    protected $accessToken;
    protected $isConfigured = false;

    public function __construct()
    {
        $this->clientId = config('services.paypal.client_id');
        $this->clientSecret = config('services.paypal.client_secret');
        $this->apiBaseUrl = config('services.paypal.api_base_url');
        
        // Check if credentials are configured
        if ($this->clientId && $this->clientSecret && $this->apiBaseUrl) {
            $this->isConfigured = true;
            $this->client = new Client([
                'base_uri' => $this->apiBaseUrl,
                'timeout' => 30,
            ]);
            // Don't authenticate in constructor - do it lazily when needed
        }
        // PayPal is disabled - no warning needed
    }

    /**
     * Check if PayPal is configured
     */
    protected function ensureConfigured()
    {
        if (!$this->isConfigured) {
            throw new \Exception('PayPal is not configured. Please set PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET in .env');
        }
        
        if (!$this->client) {
            $this->client = new Client([
                'base_uri' => $this->apiBaseUrl,
                'timeout' => 30,
            ]);
        }
    }

    /**
     * Authenticate and get access token (lazy loading)
     */
    protected function authenticate()
    {
        $this->ensureConfigured();
        
        // If we already have a valid token, return it
        if ($this->accessToken) {
            return $this->accessToken;
        }
        
        try {
            $response = $this->client->post('/v1/oauth2/token', [
                'auth' => [$this->clientId, $this->clientSecret],
                'form_params' => [
                    'grant_type' => 'client_credentials',
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            $this->accessToken = $data['access_token'] ?? null;
            
            if (!$this->accessToken) {
                throw new \Exception('Failed to obtain PayPal access token');
            }
            
            return $this->accessToken;
        } catch (\Exception $e) {
            Log::error('PayPal Authentication Failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a product for billing plans
     */
    public function createProduct($tierName, $description)
    {
        $this->authenticate(); // Ensure authenticated before API calls
        
        try {
            $response = $this->client->post('/v1/catalogs/products', [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'name' => "Tribe365 {$tierName}",
                    'description' => $description,
                    'type' => 'SERVICE',
                    'category' => 'SOFTWARE',
                ],
            ]);

            $product = json_decode($response->getBody(), true);
            return [
                'success' => true,
                'product' => $product,
            ];
        } catch (\Exception $e) {
            Log::error('PayPal Product Creation Failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a billing plan
     */
    public function createBillingPlan($productId, $tierName, $pricePerUser)
    {
        $this->authenticate();
        
        try {
            $response = $this->client->post('/v1/billing/plans', [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'product_id' => $productId,
                    'name' => "Tribe365 {$tierName} Monthly Plan",
                    'description' => "Monthly subscription for Tribe365 {$tierName} tier",
                    'status' => 'ACTIVE',
                    'billing_cycles' => [
                        [
                            'frequency' => [
                                'interval_unit' => 'MONTH',
                                'interval_count' => 1,
                            ],
                            'tenure_type' => 'REGULAR',
                            'sequence' => 1,
                            'total_cycles' => 0, // Infinite
                            'pricing_scheme' => [
                                'fixed_price' => [
                                    'value' => $pricePerUser,
                                    'currency_code' => 'USD',
                                ],
                            ],
                        ],
                    ],
                    'payment_preferences' => [
                        'auto_bill_outstanding' => true,
                        'setup_fee' => [
                            'value' => '0',
                            'currency_code' => 'GBP',
                        ],
                        'setup_fee_failure_action' => 'CONTINUE',
                        'payment_failure_threshold' => 3,
                    ],
                    'taxes' => [
                        'percentage' => '20', // UK VAT 20%
                        'inclusive' => false,
                    ],
                ],
            ]);

            $plan = json_decode($response->getBody(), true);
            return [
                'success' => true,
                'plan' => $plan,
            ];
        } catch (\Exception $e) {
            Log::error('PayPal Billing Plan Creation Failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a subscription
     */
    public function createSubscription(Organisation $organisation, $planId, $userCount)
    {
        $this->authenticate();
        
        try {
            $response = $this->client->post('/v1/billing/subscriptions', [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'plan_id' => $planId,
                    'quantity' => $userCount,
                    'subscriber' => [
                        'name' => [
                            'given_name' => $organisation->admin_first_name ?? 'Admin',
                            'surname' => $organisation->admin_last_name ?? 'User',
                        ],
                        'email_address' => $organisation->admin_email ?? $organisation->users()->first()?->email,
                    ],
                    'application_context' => [
                        'brand_name' => 'Tribe365',
                        'locale' => 'en-GB',
                        'shipping_preference' => 'NO_SHIPPING',
                        'user_action' => 'SUBSCRIBE_NOW',
                        'payment_method' => [
                            'payer_selected' => 'PAYPAL',
                            'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED',
                        ],
                        'return_url' => route('billing.paypal.success'),
                        'cancel_url' => route('billing.paypal.cancel'),
                    ],
                    'custom_id' => (string) $organisation->id,
                ],
            ]);

            $subscription = json_decode($response->getBody(), true);

            // Save subscription to database
            SubscriptionRecord::create([
                'organisation_id' => $organisation->id,
                'paypal_subscription_id' => $subscription['id'],
                'tier' => $organisation->subscription_tier ?? 'basecamp',
                'user_count' => $userCount,
                'status' => $subscription['status'],
                'created_at' => now(),
            ]);

            return [
                'success' => true,
                'subscription' => $subscription,
                'approval_url' => $this->getApprovalUrl($subscription),
            ];
        } catch (\Exception $e) {
            Log::error('PayPal Subscription Creation Failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get approval URL from subscription response
     */
    protected function getApprovalUrl($subscription)
    {
        foreach ($subscription['links'] ?? [] as $link) {
            if ($link['rel'] === 'approve') {
                return $link['href'];
            }
        }
        return null;
    }

    /**
     * Update subscription quantity
     */
    public function updateSubscriptionQuantity($subscriptionId, $newUserCount)
    {
        $this->authenticate();
        
        try {
            $response = $this->client->post("/v1/billing/subscriptions/{$subscriptionId}/revise", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'quantity' => $newUserCount,
                ],
            ]);

            $updatedSubscription = json_decode($response->getBody(), true);

            // Update database
            SubscriptionRecord::where('paypal_subscription_id', $subscriptionId)
                ->update([
                    'user_count' => $newUserCount,
                    'updated_at' => now(),
                ]);

            return [
                'success' => true,
                'subscription' => $updatedSubscription,
            ];
        } catch (\Exception $e) {
            Log::error('PayPal Subscription Update Failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription($subscriptionId, $reason = 'Customer request')
    {
        $this->authenticate();
        
        try {
            $response = $this->client->post("/v1/billing/subscriptions/{$subscriptionId}/cancel", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'reason' => $reason,
                ],
            ]);

            // Update database
            SubscriptionRecord::where('paypal_subscription_id', $subscriptionId)
                ->update([
                    'status' => 'CANCELLED',
                    'canceled_at' => now(),
                ]);

            return [
                'success' => true,
            ];
        } catch (\Exception $e) {
            Log::error('PayPal Subscription Cancellation Failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get subscription details
     */
    public function getSubscriptionDetails($subscriptionId)
    {
        $this->authenticate();
        
        try {
            $response = $this->client->get("/v1/billing/subscriptions/{$subscriptionId}", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                ],
            ]);

            $subscription = json_decode($response->getBody(), true);
            return [
                'success' => true,
                'subscription' => $subscription,
            ];
        } catch (\Exception $e) {
            Log::error('PayPal Subscription Retrieval Failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process refund
     */
    public function processRefund($captureId, $amount = null)
    {
        $this->authenticate();
        
        try {
            $refundData = [];
            if ($amount !== null) {
                $refundData['amount'] = [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency_code' => 'GBP',
                ];
            }

            $response = $this->client->post("/v2/payments/captures/{$captureId}/refund", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $refundData,
            ]);

            $refund = json_decode($response->getBody(), true);

            // Log refund in database
            PaymentRecord::create([
                'paypal_refund_id' => $refund['id'],
                'paypal_capture_id' => $captureId,
                'amount' => $refund['amount']['value'],
                'currency' => $refund['amount']['currency_code'],
                'status' => $refund['status'],
                'type' => 'refund',
                'refunded_at' => now(),
            ]);

            return [
                'success' => true,
                'refund' => $refund,
            ];
        } catch (\Exception $e) {
            Log::error('PayPal Refund Failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature($headers, $body)
    {
        $this->authenticate();
        
        try {
            $response = $this->client->post('/v1/notifications/verify-webhook-signature', [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'auth_algo' => $headers['PAYPAL-AUTH-ALGO'] ?? '',
                    'cert_url' => $headers['PAYPAL-CERT-URL'] ?? '',
                    'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
                    'transmission_sig' => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
                    'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
                    'webhook_id' => config('services.paypal.webhook_id'),
                    'webhook_event' => json_decode($body, true),
                ],
            ]);

            $result = json_decode($response->getBody(), true);
            return $result['verification_status'] === 'SUCCESS';
        } catch (\Exception $e) {
            Log::error('PayPal Webhook Verification Failed: ' . $e->getMessage());
            return false;
        }
    }
}

