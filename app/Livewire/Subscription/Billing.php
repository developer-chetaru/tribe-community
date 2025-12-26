<?php

namespace App\Livewire\Subscription;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\SubscriptionRecord;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class Billing extends Component
{
    use WithPagination, WithFileUploads;

    protected $paginationTheme = 'tailwind';

    public $showPaymentModal = false;
    public $showSubscriptionExpiredModal = false;
    public $selectedInvoice = null;
    
    protected $listeners = ['refreshPayments' => '$refresh'];
    public $payment_method = 'card';
    public $transaction_id;
    public $payment_notes;
    public $payment_proof;
    public $payment_amount;
    public $showPaymentGatewayModal = false;
    
    public $subscriptionStatus = [];
    public $daysRemaining = 0;
    public $showRenewModal = false;
    public $renewalPrice = 0;
    public $renewalUserCount = 0;
    public $renewalExpiryDate;
    public $renewalPricePerUser = 0;

    public function mount()
    {
        $user = auth()->user();
        
        // Check if user is director
        if (!$user->hasRole('director')) {
            abort(403, 'Only directors can access billing.');
        }

        // Check if user's organisation has a subscription
        if (!$user->orgId) {
            abort(403, 'You must be associated with an organisation.');
        }

        // Check subscription status
        $this->checkSubscriptionStatus();
    }

    public function checkSubscriptionStatus()
    {
        $subscriptionService = new SubscriptionService();
        $this->subscriptionStatus = $subscriptionService->getSubscriptionStatus(auth()->user()->orgId);
        $this->daysRemaining = $this->subscriptionStatus['days_remaining'] ?? 0;

        // Show expired modal only if subscription is expired (not if it's just paused)
        // Directors should be able to access billing page even if paused
        $status = $this->subscriptionStatus['status'] ?? 'none';
        if (!$this->subscriptionStatus['active'] && $status !== 'suspended') {
            $this->showSubscriptionExpiredModal = true;
        }
    }

    public function closeSubscriptionExpiredModal()
    {
        $this->showSubscriptionExpiredModal = false;
    }

    public function openRenewModal()
    {
        \Log::info('openRenewModal called');
        $user = auth()->user();
        
        // Close the subscription expired modal if it's open
        $this->showSubscriptionExpiredModal = false;
        
        // Get current user count
        $this->renewalUserCount = \App\Models\User::where('orgId', $user->orgId)
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'))
            ->count();
        
        // Default price per user is $10
        $this->renewalPricePerUser = 10.00;
        $this->renewalPrice = $this->renewalUserCount * $this->renewalPricePerUser;
        $this->renewalExpiryDate = now()->addMonth()->format('M d, Y');
        
        // Ensure subscription exists - create default if not
        $subscription = \App\Models\SubscriptionRecord::where('organisation_id', $user->orgId)
            ->orderBy('created_at', 'desc')
            ->first();
        
        if (!$subscription) {
            // Auto-create subscription if it doesn't exist
            $subscription = \App\Models\SubscriptionRecord::create([
                'organisation_id' => $user->orgId,
                'tier' => 'spark',
                'user_count' => $this->renewalUserCount,
                'status' => 'active',
                'next_billing_date' => now()->addMonth(),
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                'activated_at' => now(),
            ]);
            \Log::info('Auto-created subscription for organisation: ' . $user->orgId);
        }
        
        $this->showRenewModal = true;
        
        \Log::info('Renew modal opened', [
            'user_count' => $this->renewalUserCount,
            'price_per_user' => $this->renewalPricePerUser,
            'price' => $this->renewalPrice,
            'expiry' => $this->renewalExpiryDate,
            'subscription_id' => $subscription->id ?? null
        ]);
    }

    public function closeRenewModal()
    {
        $this->showRenewModal = false;
        $this->renewalPrice = 0;
        $this->renewalUserCount = 0;
        $this->renewalExpiryDate = null;
        $this->renewalPricePerUser = 0;
    }

    public function renewSubscription()
    {
        $user = auth()->user();
        
        // Check if we have valid renewal data
        if ($this->renewalPricePerUser <= 0 || $this->renewalPrice <= 0 || $this->renewalUserCount <= 0) {
            session()->flash('error', 'Invalid renewal data. Please contact admin.');
            $this->closeRenewModal();
            return;
        }
        
        // Get or create subscription
        $subscription = \App\Models\SubscriptionRecord::where('organisation_id', $user->orgId)
            ->orderBy('created_at', 'desc')
            ->first();
        
        // If no subscription exists, create one
        if (!$subscription) {
            $subscription = \App\Models\SubscriptionRecord::create([
                'organisation_id' => $user->orgId,
                'tier' => 'spark',
                'user_count' => $this->renewalUserCount,
                'status' => 'suspended',
                'current_period_start' => now(),
                'current_period_end' => now()->subDay(),
                'next_billing_date' => now(),
            ]);
        }

        // Create invoice for renewal
        $invoice = Invoice::create([
            'subscription_id' => $subscription->id,
            'organisation_id' => $user->orgId,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'user_count' => $this->renewalUserCount,
            'price_per_user' => $this->renewalPricePerUser,
            'subtotal' => $this->renewalPrice,
            'tax_amount' => 0.00,
            'total_amount' => $this->renewalPrice,
            'status' => 'pending',
        ]);

        // Open payment modal for this invoice
        $this->selectedInvoice = $invoice;
        $this->payment_amount = $this->renewalPrice;
        $this->showRenewModal = false;
        $this->showPaymentModal = true;
        
        \Log::info('Renewal invoice created', [
            'invoice_id' => $invoice->id,
            'amount' => $this->renewalPrice,
            'users' => $this->renewalUserCount
        ]);
    }

    public function openPaymentModal($invoiceId)
    {
        \Log::info('openPaymentModal called with ID: ' . $invoiceId);
        $this->selectedInvoice = Invoice::with('subscription')->findOrFail($invoiceId);
        $this->payment_amount = $this->selectedInvoice->total_amount;
        $this->showPaymentModal = true;
        \Log::info('showPaymentModal set to: ' . ($this->showPaymentModal ? 'true' : 'false'));
    }

    public function closePaymentModal()
    {
        $this->showPaymentModal = false;
        $this->selectedInvoice = null;
        $this->payment_method = 'bank_transfer';
        $this->transaction_id = null;
        $this->payment_notes = null;
        $this->payment_proof = null;
        $this->payment_amount = null;
    }

    public function submitPayment()
    {
        $this->validate([
            'payment_method' => 'required|string|in:card,bank_transfer,paypal',
            'payment_amount' => 'required|numeric|min:0',
        ]);

        $user = auth()->user();
        $invoice = $this->selectedInvoice;

        if (!$invoice) {
            session()->flash('error', 'No invoice selected.');
            return;
        }

        // Process payment directly (no HTTP request)
        try {
            // Generate transaction ID
            $transactionId = 'TXN-' . time() . '-' . rand(1000, 9999);
            
            // Create payment record with completed status
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'organisation_id' => $invoice->organisation_id,
                'paid_by_user_id' => $user->id,
                'payment_method' => $this->payment_method,
                'amount' => $invoice->total_amount,
                'transaction_id' => $transactionId,
                'status' => 'completed',
                'payment_date' => now()->toDateString(),
                'payment_notes' => 'Payment processed successfully',
                'approved_by_admin_id' => null,
                'approved_at' => now(),
            ]);

            // Update invoice status
            $invoice->update([
                'status' => 'paid',
                'paid_date' => now(),
            ]);

            // Activate/renew subscription immediately
            $subscriptionService = new SubscriptionService();
            if ($invoice->subscription) {
                // Renew existing subscription with updated user count and price
                $userCount = $invoice->user_count;
                $pricePerUser = $invoice->price_per_user;
                $subscriptionService->renewSubscription($invoice->subscription, $userCount, $pricePerUser);
            } else {
                // Create and activate new subscription
                $subscriptionService->activateSubscription($payment->id);
            }

            \Log::info("Payment processed and subscription activated for invoice {$invoice->id}: {$transactionId}");

            session()->flash('success', 'Payment processed successfully. Your subscription has been activated.');
            $this->closePaymentModal();
            $this->checkSubscriptionStatus(); // Refresh subscription status
        } catch (\Exception $e) {
            \Log::error('Payment processing error: ' . $e->getMessage());
            session()->flash('error', 'Payment processing failed. Please try again or contact support.');
        }
    }

    public function getSubscriptionProperty()
    {
        $user = auth()->user();
        return \App\Models\SubscriptionRecord::where('organisation_id', $user->orgId)
            ->whereIn('status', ['active', 'suspended'])
            ->with('organisation')
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function getInvoicesProperty()
    {
        $user = auth()->user();
        return Invoice::where('organisation_id', $user->orgId)
            ->with(['subscription', 'payments'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);
    }

    public function render()
    {
        return view('livewire.subscription.billing', [
            'subscription' => $this->subscription,
            'invoices' => $this->invoices,
        ])->layout('layouts.app');
    }
}
