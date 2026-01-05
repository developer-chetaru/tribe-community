<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\StripeService;
use App\Models\Organisation;
use App\Models\SubscriptionRecord;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Billing - Stripe Subscriptions",
 *     description="Stripe subscription management endpoints for creating, updating, and canceling subscriptions"
 * )
 */
class StripeSubscriptionController extends Controller
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * @OA\Post(
     *     path="/billing/stripe/subscription/create",
     *     tags={"Billing - Stripe Subscriptions"},
     *     summary="Create new Stripe subscription",
     *     description="Creates a new subscription for an organisation with the specified tier and user count. Requires director or super_admin role.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"organisation_id", "tier", "user_count", "payment_method_id"},
     *             @OA\Property(property="organisation_id", type="integer", example=1, description="ID of the organisation"),
     *             @OA\Property(property="tier", type="string", enum={"spark", "momentum", "vision"}, example="spark", description="Subscription tier"),
     *             @OA\Property(property="user_count", type="integer", minimum=1, example=5, description="Number of users in the subscription"),
     *             @OA\Property(property="payment_method_id", type="string", example="pm_1234567890", description="Stripe payment method ID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subscription created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Subscription created successfully"),
     *             @OA\Property(
     *                 property="subscription",
     *                 type="object",
     *                 description="Stripe subscription object"
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad request - Failed to create subscription"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Organisation not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
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
     * @OA\Post(
     *     path="/billing/stripe/subscription/add-user",
     *     tags={"Billing - Stripe Subscriptions"},
     *     summary="Add user to subscription (pro-rata charge)",
     *     description="Adds a user to an existing subscription with pro-rata billing. Charges are calculated based on remaining days in the billing period. Requires director or super_admin role.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"subscription_id", "organisation_id"},
     *             @OA\Property(property="subscription_id", type="string", example="sub_1234567890", description="Stripe subscription ID"),
     *             @OA\Property(property="organisation_id", type="integer", example=1, description="ID of the organisation")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User added successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User added successfully"),
     *             @OA\Property(property="pro_rata_charge", type="number", format="float", example=3.33, description="Pro-rata charge amount for the remaining days"),
     *             @OA\Property(property="new_user_count", type="integer", example=6, description="Updated user count")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad request - Failed to add user"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Subscription not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
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
     * @OA\Post(
     *     path="/billing/stripe/subscription/remove-user",
     *     tags={"Billing - Stripe Subscriptions"},
     *     summary="Remove user from subscription (pro-rata credit)",
     *     description="Removes a user from an existing subscription with pro-rata credit. Credits are calculated based on active days in the billing period. Minimum 1 user must remain. Requires director or super_admin role.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"subscription_id", "organisation_id"},
     *             @OA\Property(property="subscription_id", type="string", example="sub_1234567890", description="Stripe subscription ID"),
     *             @OA\Property(property="organisation_id", type="integer", example=1, description="ID of the organisation")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User removed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User removed successfully"),
     *             @OA\Property(property="pro_rata_credit", type="number", format="float", example=6.67, description="Pro-rata credit amount for the active days"),
     *             @OA\Property(property="new_user_count", type="integer", example=4, description="Updated user count (minimum 1)")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad request - Failed to remove user or minimum user count reached"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Subscription not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
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
     * @OA\Post(
     *     path="/billing/stripe/subscription/cancel",
     *     tags={"Billing - Stripe Subscriptions"},
     *     summary="Cancel subscription",
     *     description="Cancels a Stripe subscription. By default, cancellation occurs at the end of the current billing period. Requires director or super_admin role.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"subscription_id"},
     *             @OA\Property(property="subscription_id", type="string", example="sub_1234567890", description="Stripe subscription ID"),
     *             @OA\Property(property="cancel_at_period_end", type="boolean", example=true, description="Cancel at end of billing period (default: true). If false, cancels immediately.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subscription canceled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Subscription canceled successfully")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad request - Failed to cancel subscription"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Subscription not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
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

