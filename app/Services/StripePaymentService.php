<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\StripeClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StripePaymentService
{
    /**
     * Get enabled payment methods from Stripe API
     * Caches the result for 1 hour to avoid excessive API calls
     * 
     * @return array Array of enabled payment method types
     */
    public static function getEnabledPaymentMethods(): array
    {
        return Cache::remember('stripe_enabled_payment_methods', 3600, function () {
            try {
                Stripe::setApiKey(config('services.stripe.secret'));
                $stripe = new StripeClient(config('services.stripe.secret'));
                
                // Get account to check capabilities
                $account = $stripe->accounts->retrieve();
                
                $enabledMethods = [];
                
                // Always include card as it's the base payment method
                $enabledMethods[] = 'card';
                
                // List of payment methods that can be checked via capabilities
                // Note: Only include payment methods that are valid for Stripe Checkout Sessions
                // cashapp and us_bank_account are NOT valid for Checkout Sessions
                $paymentMethodCapabilities = [
                    'paypal' => 'paypal',
                    'link' => 'link_payments', // Link capability key
                ];
                
                // Check each payment method capability
                if (isset($account->capabilities)) {
                    foreach ($paymentMethodCapabilities as $method => $capabilityKey) {
                        // Handle different capability key formats
                        $actualCapabilityKey = $capabilityKey;
                        if ($method === 'link' && !isset($account->capabilities->link_payments)) {
                            // Try alternative key
                            $actualCapabilityKey = 'link';
                        }
                        
                        if (isset($account->capabilities->{$actualCapabilityKey})) {
                            $capability = $account->capabilities->{$actualCapabilityKey};
                            // For link_payments, it's a string status, not an object
                            if (is_string($capability)) {
                                if (in_array($capability, ['active', 'pending'])) {
                                    if (!in_array($method, $enabledMethods)) {
                                        $enabledMethods[] = $method;
                                    }
                                }
                            } elseif (is_object($capability) && isset($capability->status)) {
                                // Check if capability is active or pending
                                if (in_array($capability->status, ['active', 'pending'])) {
                                    if (!in_array($method, $enabledMethods)) {
                                        $enabledMethods[] = $method;
                                    }
                                }
                            }
                        }
                    }
                }
                
                // Include all valid payment method types for Stripe Checkout
                // Note: Wallet payment methods (Apple Pay, Google Pay, etc.) are automatically
                // shown by Stripe based on device support and account settings
                // We include valid payment_method_types here, and Stripe handles wallet methods automatically
                
                // Valid payment method types for Stripe Checkout Sessions
                // Note: Only include methods that are actually supported by Stripe Checkout
                // Some methods like cashapp and us_bank_account may not be available in all regions
                $validCheckoutPaymentMethods = [
                    'paypal',           // PayPal - widely supported
                    'link',             // Link - Stripe's payment method
                ];
                
                // Add valid payment methods that Stripe Checkout supports
                // Stripe will automatically filter unavailable ones based on:
                // 1. Account settings (enabled in dashboard)
                // 2. Device/browser support (for wallet methods)
                // 3. Region availability
                foreach ($validCheckoutPaymentMethods as $method) {
                    if (!in_array($method, $enabledMethods)) {
                        // Always include PayPal and Link as they're commonly enabled
                        $enabledMethods[] = $method;
                    }
                }
                
                // Note: Wallet payment methods like Apple Pay, Google Pay, Amazon Pay, etc.
                // are automatically shown by Stripe Checkout if:
                // 1. Enabled in Stripe Dashboard (Settings > Payment methods)
                // 2. Supported by the user's device/browser
                // 3. Available in the user's region
                // They don't need to be explicitly added to payment_method_types
                
                // Remove duplicates
                $enabledMethods = array_unique($enabledMethods);
                
                // Ensure card is always first
                $enabledMethods = array_values(array_filter($enabledMethods, function($method) {
                    return $method !== 'card';
                }));
                array_unshift($enabledMethods, 'card');
                
                Log::info('Fetched enabled payment methods from Stripe API', [
                    'methods' => $enabledMethods,
                    'account_id' => $account->id ?? null,
                    'capabilities_checked' => true,
                ]);
                
                return $enabledMethods;
                
            } catch (\Exception $e) {
                Log::error('Failed to fetch enabled payment methods from Stripe API: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
                
                // Fallback to default payment methods
                return ['card', 'paypal'];
            }
        });
    }
    
    /**
     * Clear the cached payment methods
     * Call this when payment methods are updated in Stripe dashboard
     */
    public static function clearCache(): void
    {
        Cache::forget('stripe_enabled_payment_methods');
    }
    
    /**
     * Get payment methods with retry logic
     * 
     * @param int $maxRetries Maximum number of retries
     * @return array
     */
    public static function getEnabledPaymentMethodsWithRetry(int $maxRetries = 3): array
    {
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            try {
                return self::getEnabledPaymentMethods();
            } catch (\Exception $e) {
                $attempt++;
                if ($attempt >= $maxRetries) {
                    Log::error('Failed to fetch payment methods after retries', [
                        'attempts' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                    // Return default fallback
                    return ['card', 'paypal'];
                }
                // Wait before retry (exponential backoff)
                usleep(100000 * $attempt); // 100ms, 200ms, 300ms
            }
        }
        
        return ['card', 'paypal'];
    }
}
