<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\StripeService;
use App\Models\Organisation;
use App\Models\SubscriptionRecord;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class StripeSubscriptionController extends Controller
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Create new subscription
     */
    public function createSubscription(Request $request)
    {
        $validated = $request->validate([
            'organisation_id' => 'required|exists:organisations,id',
            'tier' => 'required|in:spark,momentum,vision',
            'user_count' => 'required|integer|min:1',
            'payment_method_id' => 'required|string',
        ]);

        $organisation = Organisation::findOrFail($validated['organisation_id']);

        // Get price based on tier
        $priceId = $this->getPriceIdForTier($validated['tier']);

        // Attach payment method to customer
        $this->attachPaymentMethod(
            $organisation->stripe_customer_id,
            $validated['payment_method_id']
        );

        // Create subscription
        $result = $this->stripeService->createSubscription(
            $organisation,
            $priceId,
            $validated['user_count']
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Subscription created successfully',
                'subscription' => $result['subscription'],
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'],
        ], 400);
    }

    /**
     * Add user to subscription (pro-rata)
     */
    public function addUser(Request $request)
    {
        $validated = $request->validate([
            'subscription_id' => 'required|string',
            'organisation_id' => 'required|exists:organisations,id',
        ]);

        $subscription = SubscriptionRecord::where('stripe_subscription_id', $validated['subscription_id'])
            ->where('organisation_id', $validated['organisation_id'])
            ->firstOrFail();

        // Calculate pro-rata
        $now = Carbon::now();
        $periodEnd = Carbon::parse($subscription->current_period_end);
        $daysRemaining = $now->diffInDays($periodEnd);
        $daysInMonth = $now->daysInMonth;

        // Get tier pricing
        $monthlyPrice = $this->getTierPrice($subscription->tier);
        $proRata = $this->stripeService->calculateProRataAddition(
            $monthlyPrice,
            $daysRemaining,
            $daysInMonth
        );

        // Update subscription quantity
        $newUserCount = $subscription->user_count + 1;
        $result = $this->stripeService->updateSubscriptionQuantity(
            $subscription->stripe_subscription_id,
            $newUserCount,
            $now
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'User added successfully',
                'pro_rata_charge' => $proRata['pro_rata_amount'],
                'new_user_count' => $newUserCount,
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'],
        ], 400);
    }

    /**
     * Remove user from subscription (pro-rata credit)
     */
    public function removeUser(Request $request)
    {
        $validated = $request->validate([
            'subscription_id' => 'required|string',
            'organisation_id' => 'required|exists:organisations,id',
        ]);

        $subscription = SubscriptionRecord::where('stripe_subscription_id', $validated['subscription_id'])
            ->where('organisation_id', $validated['organisation_id'])
            ->firstOrFail();

        // Calculate pro-rata credit
        $now = Carbon::now();
        $periodStart = Carbon::parse($subscription->current_period_start);
        $daysActive = $periodStart->diffInDays($now);
        $daysInMonth = $now->daysInMonth;

        // Get tier pricing
        $monthlyPrice = $this->getTierPrice($subscription->tier);
        $proRata = $this->stripeService->calculateProRataRemoval(
            $monthlyPrice,
            $daysActive,
            $daysInMonth
        );

        // Update subscription quantity
        $newUserCount = max(1, $subscription->user_count - 1); // Minimum 1 user
        $result = $this->stripeService->updateSubscriptionQuantity(
            $subscription->stripe_subscription_id,
            $newUserCount,
            $now
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'User removed successfully',
                'pro_rata_credit' => $proRata['credit_amount'],
                'new_user_count' => $newUserCount,
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'],
        ], 400);
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(Request $request)
    {
        $validated = $request->validate([
            'subscription_id' => 'required|string',
            'cancel_at_period_end' => 'boolean',
        ]);

        $result = $this->stripeService->cancelSubscription(
            $validated['subscription_id'],
            $validated['cancel_at_period_end'] ?? true
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Subscription canceled successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'],
        ], 400);
    }

    /**
     * Helper: Get price ID for tier
     */
    private function getPriceIdForTier($tier)
    {
        $priceIds = [
            'spark' => env('STRIPE_PRICE_SPARK'),
            'momentum' => env('STRIPE_PRICE_MOMENTUM'),
            'vision' => env('STRIPE_PRICE_VISION'),
        ];

        return $priceIds[$tier] ?? null;
    }

    /**
     * Helper: Get tier pricing
     */
    private function getTierPrice($tier)
    {
        $prices = [
            'spark' => 10, // £10 per user/month
            'momentum' => 20, // £20 per user/month
            'vision' => 30, // £30 per user/month
        ];

        return $prices[$tier] ?? 0;
    }

    /**
     * Helper: Attach payment method to customer
     */
    private function attachPaymentMethod($customerId, $paymentMethodId)
    {
        try {
            if (!class_exists(\Stripe\PaymentMethod::class)) {
                throw new \Exception('Stripe PHP package is not installed.');
            }

            $paymentMethod = \Stripe\PaymentMethod::retrieve($paymentMethodId);
            $paymentMethod->attach(['customer' => $customerId]);

            // Set as default payment method
            \Stripe\Customer::update($customerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Payment Method Attachment Failed: ' . $e->getMessage());
            return false;
        }
    }
}

