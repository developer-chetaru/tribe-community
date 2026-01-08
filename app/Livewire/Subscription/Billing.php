<?php

namespace App\Livewire\Subscription;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\SubscriptionRecord;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentRecord;
use App\Models\Organisation;
use App\Services\SubscriptionService;
use App\Services\Billing\StripeService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class Billing extends Component
{
    use WithPagination, WithFileUploads;

    protected $paginationTheme = 'tailwind';

    public $showPaymentModal = false;
    public $showSubscriptionExpiredModal = false;
    public $selectedInvoice = null;
    public $showPaymentPage = false;
    public $showInvoiceModal = false;
    public $selectedInvoiceForView = null;
    public $showShareModal = false;
    public $selectedInvoiceForShare = null;
    public $shareLink = '';
    public $showStripeInvoiceModal = false;
    public $selectedStripeInvoice = null;
    public $stripeInvoiceData = null;
    public $stripeInvoiceError = null;
    
    protected $listeners = [
        'refreshPayments' => '$refresh',
        'subscription-activated' => 'refreshBilling'
    ];
    
    public $subscriptionStatus = [];
    public $daysRemaining = 0;
    public $showRenewModal = false;
    public $renewalPrice = 0;
    public $renewalUserCount = 0;
    public $renewalExpiryDate;
    public $renewalPricePerUser = 0;
    public $renewalInvoice = null;
    
    // Stripe payment properties
    public $stripeClientSecret = null;
    public $stripePaymentIntentId = null;
    public $isProcessingStripePayment = false;
    
    // Stripe subscription details
    public $stripeSubscriptionDetails = null;
    public $stripePaymentMethod = null;
    public $showCancelModal = false;
    public $stripeUpcomingInvoice = null;

    public function mount()
    {
        $user = auth()->user();
        
        // Check if user is director or basecamp user
        if (!$user->hasRole('director') && !$user->hasRole('basecamp')) {
            abort(403, 'Only directors and basecamp users can access billing.');
        }

        // For directors, check if user's organisation has a subscription
        if ($user->hasRole('director') && !$user->orgId) {
            abort(403, 'You must be associated with an organisation.');
        }
        
        // For basecamp users, check if they have completed payment
        if ($user->hasRole('basecamp')) {
            // Check if user has active subscription or paid invoice
            $hasActiveSubscription = \App\Models\SubscriptionRecord::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->where('status', 'active')
                ->exists();
                
            $hasPaidInvoice = \App\Models\Invoice::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->where('status', 'paid')
                ->exists();
                
            if (!$hasActiveSubscription && !$hasPaidInvoice) {
                // Redirect to basecamp billing for payment
                return redirect()->route('basecamp.billing', ['user_id' => $user->id])
                    ->with('error', 'Please complete your payment to access billing.');
            }
        }

        // Check subscription status
        $this->checkSubscriptionStatus();
        
        // Auto-open modal if subscription expired
        $endDate = $this->subscriptionStatus['end_date'] ?? null;
        if ($endDate) {
            $endDateCarbon = Carbon::parse($endDate)->startOfDay();
            $today = Carbon::today();
            if ($today->greaterThan($endDateCarbon)) {
                $this->openRenewModal();
            }
        }
        
        // Refresh data if payment was successful or subscription was reactivated
        if (session()->has('refresh_billing') || session()->has('payment_completed')) {
            // Clear session flags first
            session()->forget('refresh_billing');
            session()->forget('payment_success');
            session()->forget('payment_completed');
            
            // Force refresh of subscription status (this will reload subscription data)
            $this->checkSubscriptionStatus();
            
            // Force reload subscription property by clearing it
            if (property_exists($this, 'subscription')) {
                unset($this->subscription);
            }
            
            // Trigger subscription property to be fetched fresh
            try {
                $subscription = $this->subscription; // This will trigger getSubscriptionProperty()
                Log::info('Subscription refreshed in mount after payment/reactivation', [
                    'subscription_id' => $subscription?->id,
                    'status' => $subscription?->status
                ]);
            } catch (\Exception $e) {
                Log::warning('Error accessing subscription in mount: ' . $e->getMessage());
            }
            
            // Reset pagination to show updated invoices
            $this->resetPage();
        }
    }
    
    public function refreshBilling()
    {
        // Clear cached subscription to force fresh fetch
        if (property_exists($this, 'subscription')) {
            unset($this->subscription);
        }
        
        // Clear subscriptionStatus to force recalculation
        $this->subscriptionStatus = [];
        
        // Re-check subscription status (this will fetch fresh data)
        $this->checkSubscriptionStatus();
        
        // Reset page for pagination
        $this->resetPage();
        
        // Clear session flags
        session()->forget('subscription_expired');
        session()->forget('subscription_status');
        
        // Ensure subscription property is available after refresh
        // Access it to trigger computed property
        try {
            $subscription = $this->subscription;
            Log::info('Subscription refreshed in refreshBilling', [
                'subscription_id' => $subscription?->id,
                'status' => $subscription?->status
            ]);
        } catch (\Exception $e) {
            Log::warning('Error accessing subscription in refreshBilling: ' . $e->getMessage());
        }
        
        // Force Livewire to re-render
        $this->dispatch('$refresh');
    }
    
    public function openInvoiceModal($invoiceId)
    {
        $user = auth()->user();
        
        // For basecamp users, load user relationship instead of organisation
        if ($user->hasRole('basecamp')) {
            $invoice = Invoice::with(['subscription', 'payments.paidBy'])
                ->findOrFail($invoiceId);
            
            // Check if invoice belongs to this user
            if ($invoice->user_id !== $user->id) {
                session()->flash('error', 'You can only view your own invoices.');
                return;
            }
        } else {
            $invoice = Invoice::with(['subscription', 'payments.paidBy', 'organisation'])
                ->findOrFail($invoiceId);
        }
        
        // Load Stripe payment method details for each payment
        foreach ($invoice->payments as $payment) {
            if ($payment->transaction_id && $payment->payment_method === 'stripe') {
                try {
                    \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                    $paymentIntent = \Stripe\PaymentIntent::retrieve($payment->transaction_id);
                    
                    if ($paymentIntent->payment_method) {
                        $paymentMethod = \Stripe\PaymentMethod::retrieve($paymentIntent->payment_method);
                        $payment->stripe_card_brand = $paymentMethod->card->brand ?? null;
                        $payment->stripe_card_last4 = $paymentMethod->card->last4 ?? null;
                        $payment->stripe_card_exp_month = $paymentMethod->card->exp_month ?? null;
                        $payment->stripe_card_exp_year = $paymentMethod->card->exp_year ?? null;
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to retrieve Stripe payment method: ' . $e->getMessage());
                }
            }
        }
        
        $this->selectedInvoiceForView = $invoice;
        $this->showInvoiceModal = true;
    }
    
    public function closeInvoiceModal()
    {
        $this->showInvoiceModal = false;
        $this->selectedInvoiceForView = null;
    }

    public function redirectToStripeInvoice($invoiceId)
    {
        try {
            $invoice = Invoice::findOrFail($invoiceId);
            
            // Check permissions
            $user = auth()->user();
            if (!$user->hasRole('super_admin') && !$user->hasRole('director') && !$user->hasRole('basecamp')) {
                session()->flash('error', 'You do not have permission to view Stripe invoices.');
                return;
            }
            
            // For basecamp users, check if invoice belongs to them
            if ($user->hasRole('basecamp') && $invoice->user_id !== $user->id) {
                session()->flash('error', 'You can only view your own invoices.');
                return;
            }
            
            // For directors, check if invoice belongs to their organisation
            if ($user->hasRole('director') && $invoice->organisation_id !== $user->orgId) {
                session()->flash('error', 'You can only view invoices from your organisation.');
                return;
            }
            
            $stripeInvoiceId = null;
            $hostedInvoiceUrl = null;
            
            // Find PaymentRecord with stripe_invoice_id
            $paymentRecord = \App\Models\PaymentRecord::where('subscription_id', $invoice->subscription_id)
                ->whereNotNull('stripe_invoice_id')
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($paymentRecord && $paymentRecord->stripe_invoice_id) {
                $stripeInvoiceId = $paymentRecord->stripe_invoice_id;
            } else {
                // Check Payment model for stripe payment intent/invoice
                $payment = Payment::where('invoice_id', $invoice->id)
                    ->where('payment_method', 'stripe')
                    ->whereNotNull('transaction_id')
                    ->first();
                
                // Try to get invoice from subscription if available
                $subscription = $invoice->subscription;
                if ($subscription && $subscription->stripe_subscription_id) {
                    try {
                        if (!class_exists(\Stripe\Stripe::class)) {
                            session()->flash('error', 'Stripe PHP package is not installed.');
                            return;
                        }
                        
                        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                        $stripeInvoices = \Stripe\Invoice::all([
                            'subscription' => $subscription->stripe_subscription_id,
                            'limit' => 100,
                        ]);
                        
                        // Find matching invoice by amount and date
                        foreach ($stripeInvoices->data as $stripeInvoice) {
                            $stripeAmount = $stripeInvoice->amount_due / 100; // Convert from cents
                            $invoiceDate = \Carbon\Carbon::parse($invoice->invoice_date)->startOfDay();
                            $stripeInvoiceDate = \Carbon\Carbon::createFromTimestamp($stripeInvoice->created)->startOfDay();
                            
                            if (abs($stripeAmount - $invoice->total_amount) < 0.01 && 
                                abs($invoiceDate->diffInDays($stripeInvoiceDate)) <= 1) {
                                $stripeInvoiceId = $stripeInvoice->id;
                                // If we found it, check if it has hosted URL
                                if (isset($stripeInvoice->hosted_invoice_url)) {
                                    $hostedInvoiceUrl = $stripeInvoice->hosted_invoice_url;
                                }
                                break;
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to find Stripe invoice: ' . $e->getMessage());
                    }
                }
                
                // If still not found, try to get from payment intent if available
                if (!$stripeInvoiceId && $payment && $payment->transaction_id) {
                    try {
                        if (class_exists(\Stripe\Stripe::class)) {
                            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                            $paymentIntent = \Stripe\PaymentIntent::retrieve($payment->transaction_id);
                            if ($paymentIntent->invoice) {
                                $stripeInvoiceId = $paymentIntent->invoice;
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to get invoice from payment intent: ' . $e->getMessage());
                    }
                }
                
                if (!$stripeInvoiceId) {
                    session()->flash('error', 'No Stripe invoice found for this invoice. This invoice may not have been paid through Stripe or the Stripe invoice ID is not available.');
                    return;
                }
            }
            
            // If we already have the hosted URL, dispatch to frontend to open in new tab
            if ($hostedInvoiceUrl) {
                $this->dispatch('open-stripe-invoice', ['url' => $hostedInvoiceUrl]);
                return;
            }
            
            // Retrieve invoice from Stripe to get hosted URL
            $stripeService = new StripeService();
            $result = $stripeService->retrieveInvoice($stripeInvoiceId);
            
            if (!$result['success']) {
                session()->flash('error', 'Failed to retrieve Stripe invoice: ' . ($result['error'] ?? 'Unknown error'));
                return;
            }
            
            $stripeInvoice = $result['invoice'];
            
            // Get hosted invoice URL and dispatch to frontend to open in new tab
            if (isset($stripeInvoice->hosted_invoice_url) && $stripeInvoice->hosted_invoice_url) {
                $this->dispatch('open-stripe-invoice', ['url' => $stripeInvoice->hosted_invoice_url]);
                return;
            }
            
            // If hosted URL is not available, try to construct it
            // Stripe hosted invoice URL format: https://invoice.stripe.com/i/acct_xxx/xxx
            // We can construct it using the invoice ID if needed
            // But better to use the actual hosted_invoice_url from API
            
            session()->flash('error', 'Stripe hosted invoice URL is not available for this invoice.');
            
        } catch (\Exception $e) {
            Log::error('Failed to redirect to Stripe invoice: ' . $e->getMessage(), [
                'invoice_id' => $invoiceId,
                'trace' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'Failed to load Stripe invoice: ' . $e->getMessage());
        }
    }
    
    public function openShareModal($invoiceId)
    {
        $invoice = Invoice::findOrFail($invoiceId);
        
        // Check permissions
        $user = auth()->user();
        if (!$user->hasRole('super_admin') && !$user->hasRole('director') && !$user->hasRole('basecamp')) {
            session()->flash('error', 'You do not have permission to share invoices.');
            return;
        }
        
        if ($user->hasRole('director') && $invoice->organisation_id !== $user->orgId) {
            session()->flash('error', 'You can only share invoices from your organisation.');
            return;
        }
        
        if ($user->hasRole('basecamp') && $invoice->user_id !== $user->id) {
            session()->flash('error', 'You can only share your own invoices.');
            return;
        }
        
        $this->selectedInvoiceForShare = $invoice;
        $this->shareLink = $invoice->getShareableUrl();
        $this->showShareModal = true;
    }

    public function closeShareModal()
    {
        $this->showShareModal = false;
        $this->selectedInvoiceForShare = null;
        $this->shareLink = '';
    }

    public function copyShareLink()
    {
        if ($this->shareLink) {
            $this->dispatch('copy-to-clipboard', text: $this->shareLink);
        }
    }

    public function shareViaWhatsApp()
    {
        if ($this->shareLink && $this->selectedInvoiceForShare) {
            $message = urlencode("Please find the invoice link for Invoice {$this->selectedInvoiceForShare->invoice_number}:\n\n{$this->shareLink}");
            $whatsappUrl = "https://wa.me/?text={$message}";
            $this->dispatch('open-window', url: $whatsappUrl);
        }
    }

    public function shareViaEmail()
    {
        if ($this->shareLink && $this->selectedInvoiceForShare) {
            $subject = urlencode("Invoice {$this->selectedInvoiceForShare->invoice_number}");
            $body = urlencode("Please find the invoice link:\n\n{$this->shareLink}");
            $mailtoUrl = "mailto:?subject={$subject}&body={$body}";
            $this->dispatch('open-window', url: $mailtoUrl);
        }
    }

    public function checkSubscriptionStatus()
    {
        $user = auth()->user();
        $subscriptionService = new SubscriptionService();
        
        // For basecamp users, check user-based subscription
        if ($user->hasRole('basecamp')) {
            $subscription = \App\Models\SubscriptionRecord::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->orderBy('id', 'desc') // Use ID for better ordering
                ->first();
                
            if ($subscription) {
                // Refresh subscription FIRST to get latest status from database
                $subscription->refresh();
                
                $endDate = $subscription->current_period_end ?? \Carbon\Carbon::today()->addMonth();
                // Use today() for date-only calculation to avoid time-based decimals
                $today = \Carbon\Carbon::today();
                $endDateOnly = \Carbon\Carbon::parse($endDate)->startOfDay();
                // Calculate whole days remaining (floor to ensure we don't show partial days)
                $daysRemaining = max(0, (int) floor($today->diffInDays($endDateOnly, false)));
                
                $this->subscriptionStatus = [
                    'active' => $subscription->status === 'active' || ($endDate && $endDateOnly->isFuture()), // Active if status is active OR end date is future
                    'status' => $subscription->status, // This should be 'active' after activation
                    'days_remaining' => $daysRemaining,
                    'end_date' => $endDateOnly->format('Y-m-d'),
                ];
                $this->daysRemaining = $daysRemaining;
                
                // Store subscription in a way that view can access fresh data
                // Force clear any cached subscription property
                if (property_exists($this, 'subscription') || isset($this->subscription)) {
                    unset($this->subscription);
                }
            } else {
                $this->subscriptionStatus = [
                    'active' => false,
                    'status' => 'none',
                    'days_remaining' => 0,
                ];
                $this->daysRemaining = 0;
            }
        } else {
            // For directors, use organisation-based subscription
            $this->subscriptionStatus = $subscriptionService->getSubscriptionStatus($user->orgId);
            $this->daysRemaining = $this->subscriptionStatus['days_remaining'] ?? 0;
        }

        // Show expired modal only if subscription is expired (not if it's just paused or cancelled)
        // Directors should be able to access billing page even if paused
        $status = $this->subscriptionStatus['status'] ?? 'none';
        $isCancelled = in_array(strtolower($status), ['canceled', 'cancelled']);
        
        // Also check Stripe subscription status if available
        if (!$isCancelled) {
            $subscription = $this->subscription;
            if ($subscription && $subscription->stripe_subscription_id) {
                try {
                    \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                    $stripeSubscription = \Stripe\Subscription::retrieve($subscription->stripe_subscription_id);
                    $isCancelled = in_array(strtolower($stripeSubscription->status ?? ''), ['canceled', 'cancelled']);
                } catch (\Exception $e) {
                    // If we can't check Stripe, continue with database status
                    Log::debug('Could not check Stripe subscription status: ' . $e->getMessage());
                }
            }
        }
        
        // Check if subscription has expired (end_date is in the past)
        $endDate = $this->subscriptionStatus['end_date'] ?? null;
        $isExpired = false;
        if ($endDate) {
            try {
                $endDateCarbon = Carbon::parse($endDate)->startOfDay();
                $today = Carbon::today();
                $isExpired = $today->greaterThan($endDateCarbon);
            } catch (\Exception $e) {
                Log::warning('Error parsing end date: ' . $e->getMessage());
            }
        }
        
        // If expired and not suspended/cancelled, show payment modal
        if ($isExpired && $status !== 'suspended' && !$isCancelled) {
            $this->showRenewModal = true;
            // Set renewal data
            if ($user->hasRole('basecamp')) {
                $this->renewalUserCount = 1;
                $this->renewalPricePerUser = 10.00;
                $this->renewalPrice = 10.00;
            } else {
                $this->renewalUserCount = \App\Models\User::where('orgId', $user->orgId)
                    ->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'))
                    ->count();
                $this->renewalPricePerUser = 10.00;
                $this->renewalPrice = $this->renewalUserCount * $this->renewalPricePerUser;
            }
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
        
        // Calculate renewal data
        if ($user->hasRole('basecamp')) {
            $this->renewalUserCount = 1;
            $this->renewalPricePerUser = 10.00; // £10 excluding VAT
            $this->renewalPrice = 10.00; // £10 excluding VAT
        } else {
            // For directors, get current user count
            $this->renewalUserCount = \App\Models\User::where('orgId', $user->orgId)
                ->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'))
                ->count();
            
            $this->renewalPricePerUser = 10.00;
            $this->renewalPrice = $this->renewalUserCount * $this->renewalPricePerUser;
        }
        
        // Show the modal
        $this->showRenewModal = true;
    }

    public function closeRenewModal()
    {
        // Don't allow closing if subscription is expired
        $endDate = $this->subscriptionStatus['end_date'] ?? null;
        if ($endDate) {
            $endDateCarbon = Carbon::parse($endDate)->startOfDay();
            $today = Carbon::today();
            if ($today->greaterThan($endDateCarbon)) {
                // Subscription expired - don't allow closing
                return;
            }
        }
        
        $this->showRenewModal = false;
        $this->renewalPrice = 0;
        $this->renewalUserCount = 0;
        $this->renewalExpiryDate = null;
        $this->renewalPricePerUser = 0;
    }

    public function renewSubscription()
    {
        $user = auth()->user();
        
        // Auto-calculate renewal data if not set
        if ($user->hasRole('basecamp')) {
            $this->renewalUserCount = 1;
            $this->renewalPricePerUser = 10.00; // £10 excluding VAT
            $this->renewalPrice = 10.00; // £10 excluding VAT
        } else {
            // For directors, get current user count
            $this->renewalUserCount = \App\Models\User::where('orgId', $user->orgId)
                ->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'))
                ->count();
            
            $this->renewalPricePerUser = 10.00;
            $this->renewalPrice = $this->renewalUserCount * $this->renewalPricePerUser;
        }
        
        // Check if we have valid renewal data
        if ($this->renewalPricePerUser <= 0 || $this->renewalPrice <= 0 || $this->renewalUserCount <= 0) {
            session()->flash('error', 'Invalid renewal data. Please contact admin.');
            return;
        }
        
        // Wrap all operations in transaction to ensure data consistency
        try {
            \DB::transaction(function() use ($user) {
                // For basecamp users
                if ($user->hasRole('basecamp')) {
                    // Get or create subscription
                    $subscription = \App\Models\SubscriptionRecord::where('user_id', $user->id)
                        ->where('tier', 'basecamp')
                        ->orderBy('created_at', 'desc')
                        ->first();
                    
                    // If no subscription exists, create one
                    if (!$subscription) {
                        $subscription = \App\Models\SubscriptionRecord::create([
                            'user_id' => $user->id,
                            'organisation_id' => null,
                            'tier' => 'basecamp',
                            'user_count' => 1,
                            'status' => 'suspended',
                            'current_period_start' => now(),
                            'current_period_end' => now()->subDay(),
                            'next_billing_date' => now(),
                        ]);
                    }

                    // Check if invoice already exists for this renewal period to prevent duplicates
                    $existingInvoice = Invoice::where('subscription_id', $subscription->id)
                        ->where('user_id', $user->id)
                        ->where('tier', 'basecamp')
                        ->where('status', 'pending')
                        ->where('invoice_date', '>=', now()->startOfMonth())
                        ->where('invoice_date', '<=', now()->endOfMonth())
                        ->first();
                    
                    if ($existingInvoice) {
                        $invoice = $existingInvoice;
                    } else {
                        // Calculate VAT (20% of subtotal) for basecamp renewal
                        $subtotal = $this->renewalPrice; // £10.00
                        $taxAmount = $subtotal * 0.20; // 20% VAT = £2.00
                        $totalAmount = $subtotal + $taxAmount; // £12.00
                        
                        // Create invoice for renewal
                        $invoice = Invoice::create([
                            'subscription_id' => $subscription->id,
                            'user_id' => $user->id,
                            'organisation_id' => null,
                            'tier' => 'basecamp',
                            'invoice_number' => Invoice::generateInvoiceNumber(),
                            'invoice_date' => now()->toDateString(),
                            'due_date' => now()->addDays(7)->toDateString(), // Due date is 7 days from invoice date
                            'user_count' => 1,
                            'price_per_user' => $this->renewalPricePerUser,
                            'subtotal' => $subtotal,
                            'tax_amount' => $taxAmount,
                            'total_amount' => $totalAmount,
                            'status' => 'pending',
                        ]);
                    }
                } else {
                    // For directors
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

                    // Check if invoice already exists for this renewal period to prevent duplicates
                    $existingInvoice = Invoice::where('subscription_id', $subscription->id)
                        ->where('organisation_id', $user->orgId)
                        ->where('status', 'pending')
                        ->where('invoice_date', '>=', now()->startOfMonth())
                        ->where('invoice_date', '<=', now()->endOfMonth())
                        ->first();
                    
                    if ($existingInvoice) {
                        $invoice = $existingInvoice;
                    } else {
                        // Create invoice for renewal
                        $invoice = Invoice::create([
                            'subscription_id' => $subscription->id,
                            'organisation_id' => $user->orgId,
                            'invoice_number' => Invoice::generateInvoiceNumber(),
                            'invoice_date' => now()->toDateString(),
                            'due_date' => now()->addDays(7)->toDateString(), // Due date is 7 days from invoice date
                            'user_count' => $this->renewalUserCount,
                            'price_per_user' => $this->renewalPricePerUser,
                            'subtotal' => $this->renewalPrice,
                            'tax_amount' => 0.00,
                            'total_amount' => $this->renewalPrice,
                            'status' => 'pending',
                        ]);
                    }
                }
                
                // Store invoice for use outside transaction
                $this->renewalInvoice = $invoice;
                
                \Log::info('Renewal invoice created', [
                    'invoice_id' => $invoice->id,
                    'amount' => $this->renewalPrice,
                    'users' => $this->renewalUserCount
                ]);
            });
            
            // Redirect directly to Stripe payment (no modal)
            return $this->openPaymentModal($this->renewalInvoice->id);
            
        } catch (\Exception $e) {
            \Log::error('Error creating renewal invoice: ' . $e->getMessage());
            session()->flash('error', 'Failed to create renewal invoice. Please try again.');
        }
    }

    public function openPaymentModal($invoiceId)
    {
        \Log::info('openPaymentModal called with ID: ' . $invoiceId);
        
        try {
            $invoice = Invoice::with('subscription')->findOrFail($invoiceId);
            
            // Check permissions - user can only pay their own invoices
            $user = auth()->user();
            if ($user->hasRole('basecamp')) {
                if ($invoice->user_id !== $user->id) {
                    session()->flash('error', 'You can only pay your own invoices.');
                    return;
                }
            } elseif ($user->hasRole('director')) {
                if ($invoice->organisation_id !== $user->orgId) {
                    session()->flash('error', 'You can only pay invoices from your organisation.');
                    return;
                }
            }
            
            // Check if invoice is already paid
            if ($invoice->status === 'paid') {
                session()->flash('error', 'This invoice has already been paid.');
                return;
            }
            
            // Check if payment already exists
            $existingPayment = \App\Models\Payment::where('invoice_id', $invoiceId)
                ->where('status', 'completed')
                ->first();
            
            if ($existingPayment) {
                session()->flash('error', 'Payment already exists for this invoice.');
                return;
            }
            
            // Create Stripe Checkout Session and redirect
            $checkoutUrl = $this->createStripeCheckoutSession($invoice);
            
            if ($checkoutUrl) {
                \Log::info('Stripe checkout URL created, dispatching redirect event', [
                    'checkout_url' => $checkoutUrl,
                    'invoice_id' => $invoiceId
                ]);
                
                // Dispatch JavaScript event with direct Stripe URL for immediate redirect
                // This works better with Livewire AJAX requests
                $this->dispatch('redirect-to-stripe-checkout', url: $checkoutUrl);
                return;
            }
            
        } catch (\Exception $e) {
            \Log::error('Failed to open payment: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'Failed to initialize payment. Please try again.');
        }
    }
    
    public function createStripeCheckoutSession($invoice)
    {
        try {
            \Log::info('Creating Stripe Checkout Session for invoice: ' . $invoice->id);
            
            $user = \Illuminate\Support\Facades\Auth::user();
            
            // For basecamp users, handle differently
            if ($user->hasRole('basecamp')) {
                // Create Checkout Session for basecamp user
                $checkoutParams = [
                    'payment_method_types' => ['card'],
                    'line_items' => [[
                        'price_data' => [
                            'currency' => 'gbp',
                            'product_data' => [
                                'name' => 'Basecamp Subscription',
                                'description' => 'Monthly subscription for Basecamp tier - £10/month',
                            ],
                            'unit_amount' => $invoice->total_amount * 100, // Convert to cents
                            'recurring' => [
                                'interval' => 'month',
                            ],
                        ],
                        'quantity' => 1,
                    ]],
                    'mode' => 'subscription',
                    'success_url' => route('billing.payment.success') . '?session_id={CHECKOUT_SESSION_ID}&invoice_id=' . $invoice->id,
                    'cancel_url' => route('billing') . '?canceled=true',
                    'customer_email' => $user->email,
                    'metadata' => [
                        'invoice_id' => $invoice->id,
                        'user_id' => $user->id,
                        'tier' => 'basecamp',
                    ],
                ];
                
                \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                $checkoutSession = \Stripe\Checkout\Session::create($checkoutParams);
                
                \Log::info('Stripe Checkout Session created for basecamp', [
                    'session_id' => $checkoutSession->id,
                    'url' => $checkoutSession->url,
                    'invoice_id' => $invoice->id,
                ]);
                
                return $checkoutSession->url;
            }
            
            // For directors, use organisation-based flow
            $organisation = Organisation::findOrFail($invoice->organisation_id);
            $stripeService = new StripeService();
            
            // Get user email - prefer logged in user's email
            $customerEmail = $user->email ?? $organisation->admin_email ?? $organisation->users()->first()?->email;
            
            if (!$customerEmail) {
                session()->flash('error', 'Unable to determine customer email. Please contact support.');
                return;
            }
            
            // Ensure customer exists
            if (!$organisation->stripe_customer_id) {
                $customerResult = $stripeService->createCustomer($organisation);
                if (!$customerResult['success']) {
                    \Log::error('Failed to create Stripe customer: ' . ($customerResult['error'] ?? 'Unknown error'));
                    session()->flash('error', 'Failed to create customer: ' . ($customerResult['error'] ?? 'Please try again.'));
                    return;
                }
            }
            
            // Update customer email if different
            if ($organisation->stripe_customer_id) {
                try {
                    $stripeCustomer = \Stripe\Customer::retrieve($organisation->stripe_customer_id);
                    if ($stripeCustomer->email !== $customerEmail) {
                        \Stripe\Customer::update($organisation->stripe_customer_id, [
                            'email' => $customerEmail,
                        ]);
                        \Log::info('Updated Stripe customer email', [
                            'customer_id' => $organisation->stripe_customer_id,
                            'new_email' => $customerEmail
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::warning('Failed to update customer email in Stripe', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            if (!class_exists(\Stripe\Checkout\Session::class)) {
                throw new \Exception('Stripe PHP package is not installed.');
            }
            
            // Get user's name for billing details
            $customerName = $user->name ?? $organisation->name ?? 'Customer';
            
            // Create Checkout Session
            // Note: We can't use both 'customer' and 'customer_email' - use only 'customer' if customer exists
            $checkoutParams = [
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'gbp',
                        'product_data' => [
                            'name' => "Invoice #{$invoice->invoice_number}",
                            'description' => "Payment for {$invoice->user_count} users - {$organisation->name}",
                        ],
                        'unit_amount' => $invoice->total_amount * 100, // Convert to cents
                        'recurring' => [
                            'interval' => 'month',
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => route('billing.payment.success') . '?session_id={CHECKOUT_SESSION_ID}&invoice_id=' . $invoice->id,
                'cancel_url' => route('billing') . '?canceled=true',
                'billing_address_collection' => 'auto', // Collect billing address
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'organisation_id' => $organisation->id,
                    'invoice_number' => $invoice->invoice_number,
                    'user_id' => $user->id,
                    'user_email' => $customerEmail,
                ],
            ];
            
            // Add customer if exists, otherwise use customer_email
            if ($organisation->stripe_customer_id) {
                $checkoutParams['customer'] = $organisation->stripe_customer_id;
            } else {
                $checkoutParams['customer_email'] = $customerEmail;
            }
            
            $checkoutSession = \Stripe\Checkout\Session::create($checkoutParams);
            
            \Log::info('Stripe Checkout Session created', [
                'session_id' => $checkoutSession->id,
                'url' => $checkoutSession->url,
                'customer_email' => $customerEmail,
                'customer_name' => $customerName
            ]);
            
            // Return checkout URL for redirect
            return $checkoutSession->url;
            
        } catch (\Exception $e) {
            \Log::error('Failed to create Stripe Checkout Session: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'Failed to initialize payment: ' . $e->getMessage());
            return null;
        }
    }
    
    public function createStripePaymentIntent()
    {
        if (!$this->selectedInvoice) {
            \Log::warning('createStripePaymentIntent called but no invoice selected');
            return;
        }
        
        try {
            \Log::info('Creating Stripe payment intent for invoice: ' . $this->selectedInvoice->id);
            
            $organisation = Organisation::findOrFail($this->selectedInvoice->organisation_id);
            $stripeService = new StripeService();
            
            // Ensure customer exists
            if (!$organisation->stripe_customer_id) {
                $customerResult = $stripeService->createCustomer($organisation);
                if (!$customerResult['success']) {
                    \Log::error('Failed to create Stripe customer: ' . ($customerResult['error'] ?? 'Unknown error'));
                    // Try to continue anyway - customer might be created but ID not saved
                    if (!isset($customerResult['customer'])) {
                        session()->flash('error', 'Failed to create customer: ' . ($customerResult['error'] ?? 'Please try again.'));
                        return;
                    }
                }
            }
            
            // Create payment intent directly
            if (!class_exists(\Stripe\PaymentIntent::class)) {
                throw new \Exception('Stripe PHP package is not installed.');
            }
            
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $this->selectedInvoice->total_amount * 100, // Convert to cents
                'currency' => 'usd',
                'customer' => $organisation->stripe_customer_id,
                'metadata' => [
                    'invoice_id' => $this->selectedInvoice->id,
                    'organisation_id' => $organisation->id,
                    'invoice_number' => $this->selectedInvoice->invoice_number,
                ],
                'description' => "Payment for Invoice #{$this->selectedInvoice->invoice_number}",
            ]);
            
            $this->stripeClientSecret = $paymentIntent->client_secret;
            $this->stripePaymentIntentId = $paymentIntent->id;
            
            \Log::info('Stripe payment intent created successfully', [
                'payment_intent_id' => $this->stripePaymentIntentId,
                'has_client_secret' => !empty($this->stripeClientSecret),
                'client_secret_preview' => substr($this->stripeClientSecret, 0, 20) . '...'
            ]);
            
            // Dispatch event to initialize Stripe Elements
            // Pass client secret as array for better compatibility
            $this->dispatch('stripe-payment-intent-created', [
                'clientSecret' => $this->stripeClientSecret
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Failed to create Stripe payment intent: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'Failed to initialize payment: ' . $e->getMessage());
        }
    }
    
    public function confirmStripePayment($paymentIntentId)
    {
        if (!$this->selectedInvoice || !$paymentIntentId) {
            session()->flash('error', 'Invalid payment data.');
            return;
        }
        
        $this->isProcessingStripePayment = true;
        
        try {
            // Retrieve payment intent from Stripe
            if (!class_exists(\Stripe\PaymentIntent::class)) {
                throw new \Exception('Stripe PHP package is not installed.');
            }

            $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

            if ($paymentIntent->status !== 'succeeded') {
                session()->flash('error', 'Payment not completed. Status: ' . $paymentIntent->status);
                $this->isProcessingStripePayment = false;
                return;
            }

            // Use database transaction with lock to prevent race conditions
            \DB::transaction(function() use ($paymentIntent, $paymentIntentId) {
                // Lock invoice row to prevent concurrent payments
                $invoice = Invoice::lockForUpdate()->find($this->selectedInvoice->id);
                
                if (!$invoice) {
                    throw new \Exception('Invoice not found.');
                }
                
                // Check if payment already exists (with lock)
                $existingPayment = Payment::lockForUpdate()
                    ->where('invoice_id', $invoice->id)
                    ->where('status', 'completed')
                    ->first();
                
                if ($existingPayment) {
                    throw new \Exception('Payment already exists for this invoice.');
                }
                
                // Validate payment amount matches invoice amount
                $paymentAmount = $paymentIntent->amount / 100; // Convert from cents
                if (abs($paymentAmount - $invoice->total_amount) > 0.01) {
                    throw new \Exception('Payment amount does not match invoice amount.');
                }
                
                // Create payment record
                $payment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'organisation_id' => $invoice->organisation_id,
                    'user_id' => $invoice->user_id,
                    'paid_by_user_id' => auth()->id(),
                    'payment_method' => 'card',
                    'amount' => $paymentAmount,
                    'transaction_id' => $paymentIntent->id,
                    'status' => 'completed',
                    'payment_date' => now()->toDateString(),
                    'payment_notes' => 'Payment processed via Stripe',
                    'approved_by_admin_id' => null,
                    'approved_at' => now(),
                ]);

                // Create payment record for Stripe tracking
                \App\Models\PaymentRecord::create([
                    'organisation_id' => $invoice->organisation_id,
                    'stripe_payment_intent_id' => $paymentIntent->id,
                    'stripe_customer_id' => $paymentIntent->customer,
                    'amount' => $paymentAmount,
                    'currency' => $paymentIntent->currency,
                    'status' => $paymentIntent->status,
                    'type' => 'payment',
                    'paid_at' => now(),
                ]);

                // Update invoice status
                $invoice->update([
                    'status' => 'paid',
                    'paid_date' => now(),
                ]);

                // Activate/renew subscription
                $subscriptionService = new SubscriptionService();
                if ($invoice->subscription) {
                    $userCount = $invoice->user_count;
                    $pricePerUser = $invoice->price_per_user;
                    $subscriptionService->renewSubscription($invoice->subscription, $userCount, $pricePerUser);
                } else {
                    $subscriptionService->activateSubscription($payment->id);
                }
                
                \Log::info("Stripe payment confirmed for invoice {$invoice->id}: {$paymentIntent->id}");
                
                // Update component state after successful transaction
                $this->selectedInvoice = $invoice->fresh();
            });
            
            // Success - transaction committed
            session()->flash('success', 'Payment processed successfully. Your subscription has been activated.');
            $this->closePaymentPage();
            $this->checkSubscriptionStatus();
            $this->dispatch('payment-successful');
            
        } catch (\Exception $e) {
            \Log::error('Failed to confirm Stripe payment: ' . $e->getMessage());
            session()->flash('error', 'Payment confirmation failed: ' . $e->getMessage());
        } finally {
            $this->isProcessingStripePayment = false;
        }
    }

    public function closePaymentModal()
    {
        $this->showPaymentModal = false;
        $this->selectedInvoice = null;
        $this->stripeClientSecret = null;
        $this->stripePaymentIntentId = null;
        $this->isProcessingStripePayment = false;
        $this->dispatch('stripe-payment-modal-closed');
    }


    public function getSubscriptionProperty()
    {
        $user = auth()->user();
        
        // For basecamp users, get user-based subscription
        if ($user->hasRole('basecamp')) {
            $subscription = \App\Models\SubscriptionRecord::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->orderBy('id', 'desc') // Use ID for better ordering - gets latest
                ->first();
        } else {
            // For directors, get organisation-based subscription
            $subscription = \App\Models\SubscriptionRecord::where('organisation_id', $user->orgId)
                ->with('organisation')
                ->orderBy('id', 'desc') // Use ID for better ordering - gets latest
                ->first();
        }
        
        // Refresh subscription to get latest status from database
        if ($subscription) {
            $subscription->refresh();
            Log::info('Subscription property fetched', [
                'subscription_id' => $subscription->id,
                'status' => $subscription->status,
                'user_id' => $user->id,
                'is_basecamp' => $user->hasRole('basecamp')
            ]);
        } else {
            Log::info('No subscription found in getSubscriptionProperty', [
                'user_id' => $user->id,
                'org_id' => $user->orgId ?? null,
                'is_basecamp' => $user->hasRole('basecamp')
            ]);
        }
        
        return $subscription;
    }

    public function getInvoicesProperty()
    {
        $user = auth()->user();
        
        // For basecamp users, filter by user_id
        if ($user->hasRole('basecamp')) {
            return Invoice::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->with(['subscription', 'payments'])
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        }
        
        // For directors, filter by organisation_id
        return Invoice::where('organisation_id', $user->orgId)
            ->with(['subscription', 'payments'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);
    }

    public function getStripeSubscriptionDetails()
    {
        if (!$this->subscription) {
            Log::info('No subscription found in getStripeSubscriptionDetails');
            return null;
        }

        $stripeSubscription = null;
        $paymentMethod = null;
        $upcomingInvoice = null;

        // If subscription doesn't have stripe_subscription_id, try to find it from Stripe customer
        if (!$this->subscription->stripe_subscription_id) {
            Log::info('Subscription found but no stripe_subscription_id', [
                'subscription_id' => $this->subscription->id,
                'user_id' => $this->subscription->user_id,
                'organisation_id' => $this->subscription->organisation_id
            ]);
            
            // Try to find subscription from Stripe customer if we have customer ID
            $customerId = null;
            if ($this->subscription->organisation_id) {
                $organisation = Organisation::find($this->subscription->organisation_id);
                $customerId = $organisation->stripe_customer_id ?? null;
            } elseif ($this->subscription->user_id) {
                $user = \App\Models\User::find($this->subscription->user_id);
                // For basecamp users, check if subscription has customer ID
                $customerId = $this->subscription->stripe_customer_id ?? null;
            }
            
            if ($customerId) {
                try {
                    \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                    $subscriptions = \Stripe\Subscription::all([
                        'customer' => $customerId,
                        'status' => 'all',
                        'limit' => 10
                    ]);
                    
                    if ($subscriptions->data && count($subscriptions->data) > 0) {
                        // Use the most recent active subscription
                        $stripeSubscription = $subscriptions->data[0];
                        $this->subscription->update([
                            'stripe_subscription_id' => $stripeSubscription->id,
                            'stripe_customer_id' => $customerId,
                        ]);
                        Log::info('Found and saved Stripe subscription ID from customer', [
                            'subscription_id' => $this->subscription->id,
                            'stripe_subscription_id' => $stripeSubscription->id
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to retrieve subscriptions from customer: ' . $e->getMessage());
                }
            }
        } else {
            // We have subscription ID, retrieve it
            try {
                \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                $stripeSubscription = \Stripe\Subscription::retrieve($this->subscription->stripe_subscription_id);
            } catch (\Exception $e) {
                Log::error('Failed to retrieve Stripe subscription: ' . $e->getMessage());
            }
        }

        // Get payment method from subscription or customer
        if ($stripeSubscription) {
            if ($stripeSubscription->default_payment_method) {
                try {
                    $paymentMethod = \Stripe\PaymentMethod::retrieve($stripeSubscription->default_payment_method);
                } catch (\Exception $e) {
                    Log::warning('Failed to retrieve payment method from subscription: ' . $e->getMessage());
                }
            } elseif ($stripeSubscription->customer) {
                // Try to get default payment method from customer
                try {
                    $customer = \Stripe\Customer::retrieve($stripeSubscription->customer);
                    if ($customer->invoice_settings->default_payment_method) {
                        $paymentMethod = \Stripe\PaymentMethod::retrieve($customer->invoice_settings->default_payment_method);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to retrieve customer payment method: ' . $e->getMessage());
                }
            }
        }

        // Fallback: Try to get payment method from latest payment
        if (!$paymentMethod) {
            $user = auth()->user();
            $latestPayment = null;
            
            if ($user->hasRole('basecamp')) {
                $latestPayment = Payment::where('user_id', $user->id)
                    ->where('payment_method', 'stripe')
                    ->whereNotNull('transaction_id')
                    ->orderBy('created_at', 'desc')
                    ->first();
            } else {
                $latestPayment = Payment::where('organisation_id', $user->orgId)
                    ->where('payment_method', 'stripe')
                    ->whereNotNull('transaction_id')
                    ->orderBy('created_at', 'desc')
                    ->first();
            }
            
            if ($latestPayment && $latestPayment->transaction_id) {
                try {
                    \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                    $paymentIntent = \Stripe\PaymentIntent::retrieve($latestPayment->transaction_id);
                    
                    if ($paymentIntent->payment_method) {
                        $paymentMethod = \Stripe\PaymentMethod::retrieve($paymentIntent->payment_method);
                        Log::info('Retrieved payment method from latest payment', [
                            'payment_id' => $latestPayment->id
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to retrieve payment method from payment: ' . $e->getMessage());
                }
            }
        }
        
        // Get upcoming invoice information directly from Stripe API (all data)
        if ($stripeSubscription && $stripeSubscription->id) {
            try {
                \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                
                // Get customer ID from subscription
                $customerId = $stripeSubscription->customer ?? null;
                
                // If no customer ID from subscription, try to get from organisation/user
                if (!$customerId) {
                    $user = auth()->user();
                    if ($user->hasRole('basecamp')) {
                        // For basecamp users, check subscription for customer ID
                        if ($this->subscription && $this->subscription->stripe_customer_id) {
                            $customerId = $this->subscription->stripe_customer_id;
                        }
                    } elseif ($user->orgId) {
                        // For directors, get from organisation
                        $organisation = Organisation::find($user->orgId);
                        if ($organisation && $organisation->stripe_customer_id) {
                            $customerId = $organisation->stripe_customer_id;
                        }
                    }
                }
                
                // Build upcoming invoice data from Stripe subscription object (which contains all the info we need)
                // The subscription object from Stripe already has all the data for the upcoming invoice
                $upcomingInvoiceObj = null;
                
                // Extract upcoming invoice data directly from the subscription object
                // Stripe subscriptions contain all the information needed for the next invoice
                if ($stripeSubscription) {
                    // Calculate amount from subscription items
                    $amountDue = 0;
                    $currency = $stripeSubscription->currency ?? 'gbp';
                    
                    if (isset($stripeSubscription->items->data) && count($stripeSubscription->items->data) > 0) {
                        foreach ($stripeSubscription->items->data as $item) {
                            if (isset($item->price->unit_amount)) {
                                $amountDue += $item->price->unit_amount * ($item->quantity ?? 1);
                            }
                        }
                    }
                    
                    // Get next billing date from subscription
                    // current_period_end IS the next billing date (when the next invoice will be generated)
                    $periodEnd = $stripeSubscription->current_period_end ?? null;
                    $periodStart = $stripeSubscription->current_period_start ?? null;
                    
                    // Fallback: If period_end is not available, try to get from database subscription
                    if (!$periodEnd && $this->subscription && $this->subscription->current_period_end) {
                        $periodEnd = Carbon::parse($this->subscription->current_period_end)->timestamp;
                    }
                    
                    // Create a structured upcoming invoice object from subscription data
                    // This mimics what Stripe's upcoming invoice API would return
                    $upcomingInvoiceObj = (object) [
                        'amount_due' => $amountDue,
                        'amount_paid' => 0,
                        'amount_remaining' => $amountDue,
                        'currency' => $currency,
                        'status' => 'draft',
                        'period_start' => $periodStart, // Current period start
                        'period_end' => $periodEnd, // Next billing date (current_period_end)
                        'due_date' => $periodEnd, // Due date is same as period_end
                        'next_payment_attempt' => $periodEnd, // Next payment attempt is period_end
                        'created' => time(),
                        'customer' => $stripeSubscription->customer ?? $customerId,
                        'subscription' => $stripeSubscription->id,
                        'total' => $amountDue,
                        'subtotal' => $amountDue,
                        'tax' => null,
                        'discount' => null,
                        'lines' => $stripeSubscription->items ?? null,
                        'billing_reason' => 'subscription_cycle',
                        'collection_method' => $stripeSubscription->collection_method ?? 'charge_automatically',
                    ];
                    
                    Log::info('Upcoming invoice data extracted from Stripe subscription', [
                        'amount_due' => $amountDue,
                        'period_end' => $periodEnd,
                        'subscription_id' => $stripeSubscription->id
                    ]);
                }
                
                // If we successfully got the upcoming invoice from Stripe, extract all data
                if ($upcomingInvoiceObj) {
                    // Convert Stripe invoice object to array with all available data
                    $upcomingInvoice = [
                        'id' => $upcomingInvoiceObj->id ?? null,
                        'object' => $upcomingInvoiceObj->object ?? null,
                        'account_country' => $upcomingInvoiceObj->account_country ?? null,
                        'account_name' => $upcomingInvoiceObj->account_name ?? null,
                        'account_tax_ids' => isset($upcomingInvoiceObj->account_tax_ids) ? $upcomingInvoiceObj->account_tax_ids : null,
                        'amount_due' => $upcomingInvoiceObj->amount_due ?? null,
                        'amount_paid' => $upcomingInvoiceObj->amount_paid ?? null,
                        'amount_remaining' => $upcomingInvoiceObj->amount_remaining ?? null,
                        'application' => $upcomingInvoiceObj->application ?? null,
                        'application_fee_amount' => $upcomingInvoiceObj->application_fee_amount ?? null,
                        'attempt_count' => $upcomingInvoiceObj->attempt_count ?? null,
                        'attempted' => $upcomingInvoiceObj->attempted ?? null,
                        'auto_advance' => $upcomingInvoiceObj->auto_advance ?? null,
                        'billing_reason' => $upcomingInvoiceObj->billing_reason ?? null,
                        'charge' => $upcomingInvoiceObj->charge ?? null,
                        'collection_method' => $upcomingInvoiceObj->collection_method ?? null,
                        'created' => $upcomingInvoiceObj->created ?? null,
                        'currency' => $upcomingInvoiceObj->currency ?? null,
                        'custom_fields' => isset($upcomingInvoiceObj->custom_fields) ? $upcomingInvoiceObj->custom_fields : null,
                        'customer' => $upcomingInvoiceObj->customer ?? null,
                        'customer_address' => isset($upcomingInvoiceObj->customer_address) ? $upcomingInvoiceObj->customer_address : null,
                        'customer_email' => $upcomingInvoiceObj->customer_email ?? null,
                        'customer_name' => $upcomingInvoiceObj->customer_name ?? null,
                        'customer_phone' => $upcomingInvoiceObj->customer_phone ?? null,
                        'customer_shipping' => isset($upcomingInvoiceObj->customer_shipping) ? $upcomingInvoiceObj->customer_shipping : null,
                        'customer_tax_exempt' => $upcomingInvoiceObj->customer_tax_exempt ?? null,
                        'customer_tax_ids' => isset($upcomingInvoiceObj->customer_tax_ids) ? $upcomingInvoiceObj->customer_tax_ids : null,
                        'default_payment_method' => $upcomingInvoiceObj->default_payment_method ?? null,
                        'default_source' => $upcomingInvoiceObj->default_source ?? null,
                        'default_tax_rates' => isset($upcomingInvoiceObj->default_tax_rates) ? $upcomingInvoiceObj->default_tax_rates : null,
                        'description' => $upcomingInvoiceObj->description ?? null,
                        'discount' => isset($upcomingInvoiceObj->discount) ? $upcomingInvoiceObj->discount : null,
                        'discounts' => isset($upcomingInvoiceObj->discounts) ? $upcomingInvoiceObj->discounts : null,
                        'due_date' => $upcomingInvoiceObj->due_date ?? null,
                        'ending_balance' => $upcomingInvoiceObj->ending_balance ?? null,
                        'footer' => $upcomingInvoiceObj->footer ?? null,
                        'hosted_invoice_url' => $upcomingInvoiceObj->hosted_invoice_url ?? null,
                        'invoice_pdf' => $upcomingInvoiceObj->invoice_pdf ?? null,
                        'last_finalization_error' => isset($upcomingInvoiceObj->last_finalization_error) ? $upcomingInvoiceObj->last_finalization_error : null,
                        'lines' => isset($upcomingInvoiceObj->lines->data) ? $upcomingInvoiceObj->lines->data : null,
                        'livemode' => $upcomingInvoiceObj->livemode ?? null,
                        'metadata' => isset($upcomingInvoiceObj->metadata) ? $upcomingInvoiceObj->metadata : null,
                        'next_payment_attempt' => $upcomingInvoiceObj->next_payment_attempt ?? null,
                        'number' => $upcomingInvoiceObj->number ?? null,
                        'paid' => $upcomingInvoiceObj->paid ?? null,
                        'paid_out_of_band' => $upcomingInvoiceObj->paid_out_of_band ?? null,
                        'payment_intent' => $upcomingInvoiceObj->payment_intent ?? null,
                        'payment_settings' => isset($upcomingInvoiceObj->payment_settings) ? $upcomingInvoiceObj->payment_settings : null,
                        'period_end' => $upcomingInvoiceObj->period_end ?? null,
                        'period_start' => $upcomingInvoiceObj->period_start ?? null,
                        'post_payment_credit_notes_amount' => $upcomingInvoiceObj->post_payment_credit_notes_amount ?? null,
                        'pre_payment_credit_notes_amount' => $upcomingInvoiceObj->pre_payment_credit_notes_amount ?? null,
                        'receipt_number' => $upcomingInvoiceObj->receipt_number ?? null,
                        'starting_balance' => $upcomingInvoiceObj->starting_balance ?? null,
                        'statement_descriptor' => $upcomingInvoiceObj->statement_descriptor ?? null,
                        'status' => $upcomingInvoiceObj->status ?? null,
                        'status_transitions' => isset($upcomingInvoiceObj->status_transitions) ? $upcomingInvoiceObj->status_transitions : null,
                        'subscription' => $upcomingInvoiceObj->subscription ?? null,
                        'subscription_proration_date' => $upcomingInvoiceObj->subscription_proration_date ?? null,
                        'subtotal' => $upcomingInvoiceObj->subtotal ?? null,
                        'subtotal_excluding_tax' => $upcomingInvoiceObj->subtotal_excluding_tax ?? null,
                        'tax' => $upcomingInvoiceObj->tax ?? null,
                        'total' => $upcomingInvoiceObj->total ?? null,
                        'total_discount_amounts' => isset($upcomingInvoiceObj->total_discount_amounts) ? $upcomingInvoiceObj->total_discount_amounts : null,
                        'total_excluding_tax' => $upcomingInvoiceObj->total_excluding_tax ?? null,
                        'total_tax_amounts' => isset($upcomingInvoiceObj->total_tax_amounts) ? $upcomingInvoiceObj->total_tax_amounts : null,
                        'transfer_data' => isset($upcomingInvoiceObj->transfer_data) ? $upcomingInvoiceObj->transfer_data : null,
                        'webhooks_delivered_at' => $upcomingInvoiceObj->webhooks_delivered_at ?? null,
                    ];
                    
                    Log::info('Upcoming invoice retrieved from Stripe with all data', [
                        'invoice_id' => $upcomingInvoice['id'],
                        'amount_due' => $upcomingInvoice['amount_due'],
                        'period_end' => $upcomingInvoice['period_end'],
                        'due_date' => $upcomingInvoice['due_date'],
                        'subscription_id' => $stripeSubscription->id
                    ]);
                } else {
                    // Fallback: Calculate from subscription data if API call fails
                    Log::warning('Could not retrieve upcoming invoice from Stripe API, falling back to calculation');
                    
                    $amountDue = null;
                    $currency = $stripeSubscription->currency ?? 'gbp';
                    
                    // Calculate amount from subscription items
                    if (isset($stripeSubscription->items->data) && count($stripeSubscription->items->data) > 0) {
                        $totalAmount = 0;
                        foreach ($stripeSubscription->items->data as $item) {
                            if (isset($item->price->unit_amount)) {
                                $totalAmount += $item->price->unit_amount * ($item->quantity ?? 1);
                            }
                        }
                        $amountDue = $totalAmount;
                    }
                    
                    // Get period_end from Stripe subscription (Unix timestamp)
                    $periodEnd = $stripeSubscription->current_period_end ?? null;
                    
                    // Fallback to database subscription if Stripe period_end is not available
                    if (!$periodEnd && $this->subscription && $this->subscription->current_period_end) {
                        $periodEnd = Carbon::parse($this->subscription->current_period_end)->timestamp;
                    }
                    
                    $upcomingInvoice = [
                        'amount_due' => $amountDue,
                        'currency' => $currency,
                        'period_start' => $stripeSubscription->current_period_start ?? null,
                        'period_end' => $periodEnd,
                        'next_payment_attempt' => $periodEnd,
                    ];
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get upcoming invoice from Stripe: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
                $upcomingInvoice = null;
            }
        } else {
            // Fallback: Try to get from database subscription
            if ($this->subscription && $this->subscription->current_period_end) {
                try {
                    $periodEnd = Carbon::parse($this->subscription->current_period_end)->timestamp;
                    $upcomingInvoice = [
                        'amount_due' => null,
                        'currency' => 'gbp',
                        'period_start' => null,
                        'period_end' => $periodEnd,
                        'next_payment_attempt' => $periodEnd,
                    ];
                } catch (\Exception $e) {
                    Log::warning('Failed to get upcoming invoice from database subscription: ' . $e->getMessage());
                    $upcomingInvoice = null;
                }
            } else {
                $upcomingInvoice = null;
            }
        }
        
        return [
            'subscription' => $stripeSubscription,
            'payment_method' => $paymentMethod,
            'upcoming_invoice' => $upcomingInvoice,
        ];
    }

    public function openCancelModal()
    {
        $this->showCancelModal = true;
    }

    public function closeCancelModal()
    {
        $this->showCancelModal = false;
    }

    public function cancelSubscription()
    {
        try {
            // Get fresh subscription from database
            $subscriptionId = null;
            $stripeSubscriptionId = null;
            
            $user = auth()->user();
            if ($user->hasRole('basecamp')) {
                $subscription = \App\Models\SubscriptionRecord::where('user_id', $user->id)
                    ->where('tier', 'basecamp')
                    ->orderBy('id', 'desc')
                    ->first();
            } elseif ($user->orgId) {
                $subscription = \App\Models\SubscriptionRecord::where('organisation_id', $user->orgId)
                    ->orderBy('id', 'desc')
                    ->first();
            } else {
                session()->flash('error', 'No subscription found.');
                $this->closeCancelModal();
                return;
            }

            if (!$subscription) {
                session()->flash('error', 'No active subscription found to cancel.');
                $this->closeCancelModal();
                return;
            }

            $subscriptionId = $subscription->id;
            $stripeSubscriptionId = $subscription->stripe_subscription_id;
            
            // Update database FIRST (priority)
            // Update database status to cancel_at_period_end (not canceled)
            // Subscription will remain active until end date or next invoice date
            $subscription->update([
                'status' => 'cancel_at_period_end',
                'canceled_at' => now(),
            ]);
            
            Log::info('Subscription scheduled for cancellation at period end in database', [
                'subscription_id' => $subscriptionId,
                'stripe_subscription_id' => $stripeSubscriptionId,
                'status' => 'cancel_at_period_end',
                'end_date' => $subscription->current_period_end
            ]);
            
            // Try to cancel in Stripe if subscription ID exists (but don't fail if it doesn't work)
            if ($stripeSubscriptionId) {
                try {
                    $stripeService = new StripeService();
                    // Cancel immediately to stop monthly payments
                    // Always cancel at period end (not immediately)
                    // Subscription will remain active until end date or next invoice date
                    $result = $stripeService->cancelSubscription($stripeSubscriptionId, true);
                    
                    if ($result['success']) {
                        Log::info('Subscription cancelled in both database and Stripe', [
                            'subscription_id' => $subscriptionId,
                            'database_only' => $result['database_only'] ?? false
                        ]);
                    } else {
                        // Stripe cancellation failed, but database is already updated - this is OK
                        Log::info('Subscription cancelled in database. Stripe cancellation skipped (subscription may not exist in Stripe)', [
                            'subscription_id' => $subscriptionId,
                            'stripe_subscription_id' => $stripeSubscriptionId,
                            'error' => $result['error'] ?? 'Unknown error'
                        ]);
                    }
                } catch (\Exception $e) {
                    // Stripe error - but database is already updated, so this is OK
                    Log::info('Subscription cancelled in database. Stripe cancellation failed (this is OK if subscription was already cancelled)', [
                        'subscription_id' => $subscriptionId,
                        'stripe_subscription_id' => $stripeSubscriptionId,
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                Log::info('Subscription cancelled in database (no Stripe subscription ID)', [
                    'subscription_id' => $subscriptionId
                ]);
            }
            
            // Clear cached subscription property to force fresh fetch
            if (property_exists($this, 'subscription')) {
                unset($this->subscription);
            }
            
            // Refresh subscription status
            $this->checkSubscriptionStatus();
            
            // Close modal
            $this->closeCancelModal();
            
            // Flash success message
            session()->flash('success', 'Your subscription will be cancelled at the end of the billing period. You can continue using the service until ' . ($subscription->current_period_end ? $subscription->current_period_end->format('M d, Y') : 'the end date') . '.');
            
            // Dispatch event to refresh component
            $this->dispatch('$refresh');
            
        } catch (\Exception $e) {
            Log::error('Failed to cancel subscription: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            $this->closeCancelModal();
            session()->flash('error', 'Failed to cancel subscription: ' . $e->getMessage());
        }
    }

    public function render()
    {
        // Get subscription (this will use getSubscriptionProperty which refreshes the model)
        $subscription = $this->subscription;
        
        // Fetch Stripe subscription details if subscription exists
        $stripeDetails = null;
        if ($subscription && $subscription->stripe_subscription_id) {
            $stripeDetails = $this->getStripeSubscriptionDetails();
        }

        return view('livewire.subscription.billing', [
            'subscription' => $subscription,
            'invoices' => $this->invoices,
            'stripeDetails' => $stripeDetails,
            'subscriptionStatus' => $this->subscriptionStatus, // Explicitly pass subscriptionStatus
            'daysRemaining' => $this->daysRemaining,
        ])->layout('layouts.app', ['header' => '<h2 class="text-[24px] md:text-[30px] font-semibold capitalize text-[#EB1C24]">Billing & Invoices</h2>']);
    }
}
