<?php

namespace App\Services;

use App\Models\SubscriptionRecord;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Organisation;
use App\Services\ActivityLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    /**
     * Check subscription status and auto-generate invoice if needed
     * Called on director login - Auto-generates invoice if subscription expired or due
     */
    public function checkAndGenerateInvoice($organisationId): ?Invoice
    {
        // Get latest subscription (regardless of status)
        $subscription = SubscriptionRecord::where('organisation_id', $organisationId)
            ->orderBy('created_at', 'desc')
            ->first();

        // If no subscription exists, create one with default Spark tier
        if (!$subscription) {
            $subscription = $this->createDefaultSubscription($organisationId);
        }

        $today = Carbon::today();
        
        // Check if subscription is expired or if next billing date has passed
        $isExpired = !$subscription->current_period_end || Carbon::parse($subscription->current_period_end)->isPast();
        $isBillingDue = $subscription->next_billing_date && Carbon::parse($subscription->next_billing_date)->isPast();

        if ($isExpired || $isBillingDue) {
            // Check if invoice already exists for this period (within last 7 days)
            $existingInvoice = Invoice::where('subscription_id', $subscription->id)
                ->where('invoice_date', '>=', $today->copy()->subDays(7))
                ->where('status', 'pending')
                ->first();

            if (!$existingInvoice) {
                return $this->generateInvoice($subscription);
            }
            
            return $existingInvoice;
        }

        return null;
    }

    /**
     * Create default subscription with Spark tier
     */
    public function createDefaultSubscription($organisationId): SubscriptionRecord
    {
        // Get actual user count
        $userCount = \App\Models\User::where('orgId', $organisationId)
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'))
            ->count();

        $today = Carbon::today();

        $subscription = SubscriptionRecord::create([
            'organisation_id' => $organisationId,
            'tier' => 'spark',
            'user_count' => $userCount,
            'status' => 'suspended', // Start as suspended until payment
            'current_period_start' => $today->copy()->subDay(),
            'current_period_end' => $today->copy()->subDay(), // Set as expired to trigger invoice
            'next_billing_date' => $today,
        ]);

        Log::info("Default subscription created for organisation {$organisationId} - Users: {$userCount}, Tier: spark");

        return $subscription;
    }

    /**
     * Generate invoice for subscription with auto-calculation
     */
    public function generateInvoice(SubscriptionRecord $subscription): Invoice
    {
        $invoiceDate = Carbon::today();
        $dueDate = $invoiceDate->copy()->addDays(30); // 30 days to pay

        // Get actual user count from organisation (exclude basecamp users)
        $actualUserCount = \App\Models\User::where('orgId', $subscription->organisation_id)
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'))
            ->count();

        // Get tier pricing
        $prices = ['spark' => 10, 'momentum' => 20, 'vision' => 30];
        $pricePerUser = $prices[$subscription->tier] ?? 10.00;
        $subtotal = $actualUserCount * $pricePerUser;
        $taxAmount = $subtotal * 0.20; // UK VAT 20%
        $totalAmount = $subtotal + $taxAmount;

        // Check if invoice already exists for this billing period to prevent duplicates
        $existingInvoice = Invoice::where('subscription_id', $subscription->id)
            ->where('invoice_date', '>=', $invoiceDate->copy()->startOfMonth())
            ->where('invoice_date', '<=', $invoiceDate->copy()->endOfMonth())
            ->where('status', '!=', 'cancelled')
            ->first();
            
        if ($existingInvoice) {
            Log::info("Invoice already exists for subscription {$subscription->id} this month - Invoice ID: {$existingInvoice->id}");
            return $existingInvoice;
        }

        $invoice = Invoice::create([
            'subscription_id' => $subscription->id,
            'organisation_id' => $subscription->organisation_id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'user_count' => $actualUserCount,
            'price_per_user' => $pricePerUser,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'status' => 'pending',
        ]);

        // Update subscription with actual user count
        $subscription->update([
            'user_count' => $actualUserCount,
        ]);

        Log::info("Invoice generated for subscription {$subscription->id}: {$invoice->invoice_number} - Users: {$actualUserCount}, Amount: {$totalAmount}");

        // Log activity
        try {
            ActivityLogService::logInvoiceCreated($invoice);
        } catch (\Exception $e) {
            Log::warning('Failed to log invoice creation activity: ' . $e->getMessage());
        }

        return $invoice;
    }

    /**
     * Check if subscription is active and paid
     */
    public function isSubscriptionActive($organisationId): bool
    {
        $subscription = SubscriptionRecord::where('organisation_id', $organisationId)
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            return false;
        }

        // Check if subscription has ended
        if ($subscription->current_period_end && Carbon::parse($subscription->current_period_end)->isPast()) {
            return false;
        }

        // Check if there's a pending invoice that's overdue
        $overdueInvoice = Invoice::where('subscription_id', $subscription->id)
            ->where('status', 'pending')
            ->where('due_date', '<', Carbon::today())
            ->first();

        if ($overdueInvoice) {
            // Check if there's a pending payment
            $pendingPayment = Payment::where('invoice_id', $overdueInvoice->id)
                ->where('status', 'pending')
                ->first();

            // If no pending payment, subscription is not active
            if (!$pendingPayment) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get subscription status details
     */
    public function getSubscriptionStatus($organisationId): array
    {
        // Get latest subscription regardless of status (for directors to see paused subscriptions)
        $subscription = SubscriptionRecord::where('organisation_id', $organisationId)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$subscription) {
            return [
                'active' => false,
                'message' => 'No subscription found',
                'days_remaining' => 0,
                'status' => 'none',
            ];
        }

        // Check if subscription is paused/suspended
        if ($subscription->status === 'suspended') {
            $endDate = $subscription->current_period_end ? Carbon::parse($subscription->current_period_end)->startOfDay() : null;
            $today = Carbon::today();
            // Calculate whole days remaining (floor to ensure we don't show partial days)
            $daysRemaining = $endDate ? max(0, (int) floor($today->diffInDays($endDate, false))) : 0;
            
            return [
                'active' => false,
                'message' => 'Subscription is paused',
                'days_remaining' => max(0, $daysRemaining),
                'status' => 'suspended',
                'subscription' => $subscription,
                'has_pending_invoice' => false,
                'is_overdue' => false,
                'pending_invoice' => null,
                'end_date' => $endDate ? $endDate->format('Y-m-d') : null,
            ];
        }

        // For all subscriptions (including cancelled), check expiry and invoices
        $endDate = $subscription->current_period_end ? Carbon::parse($subscription->current_period_end)->startOfDay() : null;
        $today = Carbon::today();
        // Calculate whole days remaining (floor to ensure we don't show partial days)
        $daysRemaining = $endDate ? max(0, (int) floor($today->diffInDays($endDate, false))) : 0;

        // Check for pending/overdue invoices
        $pendingInvoice = Invoice::where('subscription_id', $subscription->id)
            ->where('status', 'pending')
            ->first();

        $overdue = false;
        if ($pendingInvoice && Carbon::parse($pendingInvoice->due_date)->isPast()) {
            $overdue = true;
        }

        // Important: Cancelled subscriptions are still active until end date passes
        // If end date is in future, subscription is active (even if cancelled)
        $isActive = false;
        if ($endDate && $endDate->isFuture()) {
            // End date is in future - subscription is active (even if cancelled)
            // Only mark as inactive if there's an overdue invoice
            $isActive = !$overdue;
        } elseif ($endDate && $today->greaterThan($endDate)) {
            // End date has passed - subscription is expired
            $isActive = false;
        } elseif (!$endDate) {
            // No end date - check status
            $isActive = $subscription->status === 'active' && !$overdue;
        }

        return [
            'active' => $isActive,
            'subscription' => $subscription,
            'status' => $subscription->status,
            'days_remaining' => max(0, $daysRemaining),
            'end_date' => $endDate ? $endDate->format('Y-m-d') : null,
            'has_pending_invoice' => $pendingInvoice !== null,
            'is_overdue' => $overdue,
            'pending_invoice' => $pendingInvoice,
        ];
    }

    /**
     * Activate or renew subscription after payment approval
     */
    public function activateSubscription($paymentId): bool
    {
        $payment = Payment::with(['invoice.subscription'])->findOrFail($paymentId);

        if ($payment->status !== 'completed') {
            return false;
        }

        $invoice = $payment->invoice;
        $subscription = $invoice->subscription;
        $today = Carbon::today();

        // Mark invoice as paid
        $invoice->status = 'paid';
        $invoice->paid_date = $today;
        $invoice->save();
        $invoice->refresh();
        
        Log::info("Invoice {$invoice->id} marked as paid in activateSubscription", [
            'invoice_status' => $invoice->status,
            'paid_date' => $invoice->paid_date
        ]);

        // Update or create subscription with ACTIVE status
        if ($subscription) {
            // For renewal payments, always start from today (payment date)
            // This ensures the subscription period starts from when payment was made
            $startDate = $today;
            
            // Calculate end date: start date + 1 month
            $endDate = $startDate->copy()->addMonth();
            
            // Update existing subscription to ACTIVE and extend period
            // Remove cancellation status when renewing
            $subscription->update([
                'status' => 'active',
                'current_period_start' => $startDate,
                'current_period_end' => $endDate,
                'next_billing_date' => $endDate,
                'user_count' => $invoice->user_count, // Update user count from invoice
                'last_payment_date' => $today,
                'canceled_at' => null, // Remove cancellation timestamp when renewing
            ]);
            
            Log::info("Subscription renewed via activateSubscription", [
                'subscription_id' => $subscription->id,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'status' => 'active',
                'payment_date' => $today->format('Y-m-d')
            ]);
            
            Log::info("Subscription {$subscription->id} renewed - Period: {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}, Status: active, Cancellation removed");
        } else {
            // Get tier from organisation or default to spark
            $org = Organisation::find($invoice->organisation_id);
            $tier = $org->subscription_tier ?? 'spark';
            
            // Create new subscription with ACTIVE status
            $subscription = SubscriptionRecord::create([
                'organisation_id' => $invoice->organisation_id,
                'tier' => $tier,
                'user_count' => $invoice->user_count,
                'status' => 'active', // Set as active immediately
                'current_period_start' => $today,
                'current_period_end' => $today->copy()->addMonth(),
                'next_billing_date' => $today->copy()->addMonth(),
                'activated_at' => $today,
            ]);
        }

        Log::info("Subscription {$subscription->id} activated after payment approval");

        return true;
    }

    /**
     * Renew subscription (extend existing subscription)
     */
    public function renewSubscription(SubscriptionRecord $subscription, $userCount = null, $pricePerUser = null): SubscriptionRecord
    {
        $today = Carbon::today();
        
        // Use provided values or get actual user count from organisation
        if ($userCount === null) {
            $userCount = \App\Models\User::where('orgId', $subscription->organisation_id)
                ->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'))
                ->count();
        }

        // Update subscription with new dates and user count
        $subscription->update([
            'status' => 'active',
            'user_count' => $userCount,
            'current_period_start' => $today,
            'current_period_end' => $today->copy()->addMonth(), // 1 month subscription
            'next_billing_date' => $today->copy()->addMonth(),
        ]);

        Log::info("Subscription {$subscription->id} renewed for organisation {$subscription->organisation_id} - Users: {$userCount}, Tier: {$subscription->tier}");

        return $subscription;
    }
}

