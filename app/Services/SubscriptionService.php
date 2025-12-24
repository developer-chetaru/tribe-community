<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Organisation;
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
        $subscription = Subscription::where('organisation_id', $organisationId)
            ->orderBy('created_at', 'desc')
            ->first();

        // If no subscription exists, create one with default $10 per user
        if (!$subscription) {
            $subscription = $this->createDefaultSubscription($organisationId);
        }

        $today = Carbon::today();
        
        // Check if subscription is expired or if next billing date has passed
        $isExpired = !$subscription->end_date || Carbon::parse($subscription->end_date)->isPast();
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
     * Create default subscription with $10 per user
     */
    public function createDefaultSubscription($organisationId): Subscription
    {
        // Get actual user count
        $userCount = \App\Models\User::where('orgId', $organisationId)
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'))
            ->count();

        $pricePerUser = 10.00;
        $totalAmount = $userCount * $pricePerUser;
        $today = Carbon::today();

        $subscription = Subscription::create([
            'organisation_id' => $organisationId,
            'user_count' => $userCount,
            'price_per_user' => $pricePerUser,
            'total_amount' => $totalAmount,
            'status' => 'suspended', // Start as suspended until payment
            'start_date' => $today,
            'end_date' => $today->copy()->subDay(), // Set as expired to trigger invoice
            'next_billing_date' => $today,
            'billing_cycle' => 'monthly',
        ]);

        Log::info("Default subscription created for organisation {$organisationId} - Users: {$userCount}, Price: {$pricePerUser}");

        return $subscription;
    }

    /**
     * Generate invoice for subscription with auto-calculation
     */
    public function generateInvoice(Subscription $subscription): Invoice
    {
        $invoiceDate = Carbon::today();
        $dueDate = $invoiceDate->copy()->addDays(30); // 30 days to pay

        // Get actual user count from organisation (exclude basecamp users)
        $actualUserCount = \App\Models\User::where('orgId', $subscription->organisation_id)
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'))
            ->count();

        // Use $10 per user as default price
        $pricePerUser = $subscription->price_per_user > 0 ? $subscription->price_per_user : 10.00;
        $subtotal = $actualUserCount * $pricePerUser;
        $taxAmount = 0.00;
        $totalAmount = $subtotal + $taxAmount;

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

        // Update subscription with actual user count and calculated amount
        $subscription->update([
            'user_count' => $actualUserCount,
            'price_per_user' => $pricePerUser,
            'total_amount' => $totalAmount,
        ]);

        Log::info("Invoice generated for subscription {$subscription->id}: {$invoice->invoice_number} - Users: {$actualUserCount}, Amount: {$totalAmount}");

        return $invoice;
    }

    /**
     * Check if subscription is active and paid
     */
    public function isSubscriptionActive($organisationId): bool
    {
        $subscription = Subscription::where('organisation_id', $organisationId)
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            return false;
        }

        // Check if subscription has ended
        if ($subscription->end_date && Carbon::parse($subscription->end_date)->isPast()) {
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
        $subscription = Subscription::where('organisation_id', $organisationId)
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
            $endDate = $subscription->end_date ? Carbon::parse($subscription->end_date) : null;
            $daysRemaining = $endDate ? Carbon::today()->diffInDays($endDate, false) : 0;
            
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

        // For active subscriptions, check expiry and invoices
        $endDate = Carbon::parse($subscription->end_date);
        $today = Carbon::today();
        $daysRemaining = $today->diffInDays($endDate, false);

        // Check for pending/overdue invoices
        $pendingInvoice = Invoice::where('subscription_id', $subscription->id)
            ->where('status', 'pending')
            ->first();

        $overdue = false;
        if ($pendingInvoice && Carbon::parse($pendingInvoice->due_date)->isPast()) {
            $overdue = true;
        }

        return [
            'active' => $daysRemaining > 0 && !$overdue && $subscription->status === 'active',
            'subscription' => $subscription,
            'status' => $subscription->status,
            'days_remaining' => max(0, $daysRemaining),
            'end_date' => $endDate->format('Y-m-d'),
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
        $invoice->update([
            'status' => 'paid',
            'paid_date' => $today,
        ]);

        // Update or create subscription with ACTIVE status
        if ($subscription) {
            // Update existing subscription to ACTIVE
            $subscription->update([
                'status' => 'active',
                'start_date' => $today,
                'end_date' => $today->copy()->addMonth(), // 1 month subscription
                'next_billing_date' => $today->copy()->addMonth(),
                'user_count' => $invoice->user_count, // Update user count from invoice
                'price_per_user' => $invoice->price_per_user,
                'total_amount' => $invoice->total_amount, // Update total amount from invoice
            ]);
        } else {
            // Create new subscription with ACTIVE status
            $subscription = Subscription::create([
                'organisation_id' => $invoice->organisation_id,
                'user_count' => $invoice->user_count,
                'price_per_user' => $invoice->price_per_user,
                'total_amount' => $invoice->total_amount,
                'status' => 'active', // Set as active immediately
                'start_date' => $today,
                'end_date' => $today->copy()->addMonth(),
                'next_billing_date' => $today->copy()->addMonth(),
                'billing_cycle' => 'monthly',
            ]);
        }

        Log::info("Subscription {$subscription->id} activated after payment approval");

        return true;
    }

    /**
     * Renew subscription (extend existing subscription)
     */
    public function renewSubscription(Subscription $subscription, $userCount = null, $pricePerUser = null): Subscription
    {
        $today = Carbon::today();
        
        // Use provided values or get actual user count from organisation
        if ($userCount === null) {
            $userCount = \App\Models\User::where('orgId', $subscription->organisation_id)
                ->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'))
                ->count();
        }
        
        // Use provided price per user or subscription's existing price, default to $10
        if ($pricePerUser === null) {
            $pricePerUser = $subscription->price_per_user > 0 ? $subscription->price_per_user : 10.00;
        }

        // Update subscription with new dates, user count, and price
        $subscription->update([
            'status' => 'active',
            'user_count' => $userCount,
            'price_per_user' => $pricePerUser,
            'total_amount' => $userCount * $pricePerUser,
            'start_date' => $today,
            'end_date' => $today->copy()->addMonth(), // 1 month subscription
            'next_billing_date' => $today->copy()->addMonth(),
        ]);

        Log::info("Subscription {$subscription->id} renewed for organisation {$subscription->organisation_id} - Users: {$userCount}, Price: {$pricePerUser}");

        return $subscription;
    }
}

