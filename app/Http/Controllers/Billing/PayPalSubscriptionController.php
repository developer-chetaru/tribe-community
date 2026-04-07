<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\PayPalService;
use App\Models\Organisation;
use App\Models\SubscriptionRecord;
use Illuminate\Http\Request;

class PayPalSubscriptionController extends Controller
{
    protected $paypalService;

    public function __construct(PayPalService $paypalService)
    {
        $this->paypalService = $paypalService;
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
        ]);

        $organisation = Organisation::findOrFail($validated['organisation_id']);

        // Get plan ID based on tier
        $planId = $this->getPlanIdForTier($validated['tier']);

        // Create subscription
        $result = $this->paypalService->createSubscription(
            $organisation,
            $planId,
            $validated['user_count']
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Subscription created successfully',
                'approval_url' => $result['approval_url'],
                'subscription_id' => $result['subscription']['id'],
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'],
        ], 400);
    }

    /**
     * Handle successful subscription approval
     */
    public function handleSuccess(Request $request)
    {
        $subscriptionId = $request->query('subscription_id');
        if (!$subscriptionId) {
            return redirect()->route('billing.error')
                ->with('error', 'Invalid subscription');
        }

        // Get subscription details
        $result = $this->paypalService->getSubscriptionDetails($subscriptionId);
        if ($result['success']) {
            $subscription = $result['subscription'];

            // Update subscription status in database
            SubscriptionRecord::where('paypal_subscription_id', $subscriptionId)
                ->update([
                    'status' => $subscription['status'],
                    'paypal_subscriber_id' => $subscription['subscriber']['payer_id'] ?? null,
                    'activated_at' => now(),
                ]);

            // Activate organisation
            $organisationId = $subscription['custom_id'] ?? null;
            if ($organisationId) {
                Organisation::where('id', $organisationId)
                    ->update(['status' => 'active']);
            }

            return redirect()->route('billing')
                ->with('success', 'Subscription activated successfully!');
        }

        return redirect()->route('billing.error')
            ->with('error', 'Failed to verify subscription');
    }

    /**
     * Handle subscription cancellation
     */
    public function handleCancel(Request $request)
    {
        return redirect()->route('billing')
            ->with('info', 'Subscription setup was cancelled');
    }

    /**
     * Add user to subscription
     */
    public function addUser(Request $request)
    {
        $validated = $request->validate([
            'subscription_id' => 'required|string',
        ]);

        $subscription = SubscriptionRecord::where('paypal_subscription_id', $validated['subscription_id'])
            ->firstOrFail();

        $newUserCount = $subscription->user_count + 1;
        $result = $this->paypalService->updateSubscriptionQuantity(
            $subscription->paypal_subscription_id,
            $newUserCount
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'User added successfully',
                'new_user_count' => $newUserCount,
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'],
        ], 400);
    }

    /**
     * Remove user from subscription
     */
    public function removeUser(Request $request)
    {
        $validated = $request->validate([
            'subscription_id' => 'required|string',
        ]);

        $subscription = SubscriptionRecord::where('paypal_subscription_id', $validated['subscription_id'])
            ->firstOrFail();

        $newUserCount = max(1, $subscription->user_count - 1);
        $result = $this->paypalService->updateSubscriptionQuantity(
            $subscription->paypal_subscription_id,
            $newUserCount
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'User removed successfully',
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
            'reason' => 'nullable|string',
        ]);

        $result = $this->paypalService->cancelSubscription(
            $validated['subscription_id'],
            $validated['reason'] ?? 'Customer request'
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
     * Helper: Get plan ID for tier
     */
    private function getPlanIdForTier($tier)
    {
        $planIds = [
            'spark' => env('PAYPAL_PLAN_SPARK'),
            'momentum' => env('PAYPAL_PLAN_MOMENTUM'),
            'vision' => env('PAYPAL_PLAN_VISION'),
        ];

        return $planIds[$tier] ?? null;
    }
}

