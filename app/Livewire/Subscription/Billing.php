<?php

namespace App\Livewire\Subscription;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\SubscriptionRecord;
use App\Models\Invoice;
use App\Models\Payment;
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
    
    protected $listeners = ['refreshPayments' => '$refresh'];
    
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
        
        // Refresh data if payment was successful
        if (session()->has('refresh_billing')) {
            session()->forget('refresh_billing');
            session()->forget('payment_success');
            // Force refresh of subscription and invoices
            $this->checkSubscriptionStatus();
            $this->resetPage(); // Reset pagination to show updated invoices
            // Force Livewire to refresh
            $this->dispatch('$refresh');
        }
    }
    
    public function refreshBilling()
    {
        $this->checkSubscriptionStatus();
        $this->resetPage();
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
                ->orderBy('created_at', 'desc')
                ->first();
                
            if ($subscription) {
                $endDate = $subscription->current_period_end ?? \Carbon\Carbon::today()->addMonth();
                // Use today() for date-only calculation to avoid time-based decimals
                $today = \Carbon\Carbon::today();
                $endDateOnly = \Carbon\Carbon::parse($endDate)->startOfDay();
                // Calculate whole days remaining (floor to ensure we don't show partial days)
                $daysRemaining = max(0, (int) floor($today->diffInDays($endDateOnly, false)));
                
                $this->subscriptionStatus = [
                    'active' => $subscription->status === 'active',
                    'status' => $subscription->status,
                    'days_remaining' => $daysRemaining,
                    'end_date' => $endDateOnly->format('Y-m-d'),
                ];
                $this->daysRemaining = $daysRemaining;
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
        \Log::info('openRenewModal called - redirecting directly to payment');
        
        // Close the subscription expired modal if it's open
        $this->showSubscriptionExpiredModal = false;
        
        // Directly call renewSubscription to redirect to payment
        $this->renewSubscription();
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
            $this->openPaymentModal($this->renewalInvoice->id);
            
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
                // For external URLs, use JavaScript redirect via Livewire
                $this->dispatch('redirect-to-stripe', url: $checkoutUrl);
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
            return \App\Models\SubscriptionRecord::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->orderBy('created_at', 'desc')
                ->first();
        }
        
        // For directors, get organisation-based subscription
        return \App\Models\SubscriptionRecord::where('organisation_id', $user->orgId)
            ->with('organisation')
            ->orderBy('created_at', 'desc')
            ->first();
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
        
        return [
            'subscription' => $stripeSubscription,
            'payment_method' => $paymentMethod,
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
        if (!$this->subscription || !$this->subscription->stripe_subscription_id) {
            session()->flash('error', 'No active subscription found to cancel.');
            return;
        }

        try {
            $stripeService = new StripeService();
            // Cancel immediately to stop monthly payments
            $result = $stripeService->cancelSubscription($this->subscription->stripe_subscription_id, false);
            
            if ($result['success']) {
                // Refresh subscription status
                $this->checkSubscriptionStatus();
                $this->closeCancelModal();
                session()->flash('success', 'Your subscription has been cancelled successfully. Monthly payments have been stopped.');
            } else {
                session()->flash('error', 'Failed to cancel subscription: ' . ($result['error'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            Log::error('Failed to cancel subscription: ' . $e->getMessage());
            session()->flash('error', 'Failed to cancel subscription. Please try again or contact support.');
        }
    }

    public function render()
    {
        // Fetch Stripe subscription details if subscription exists
        $stripeDetails = null;
        if ($this->subscription && $this->subscription->stripe_subscription_id) {
            $stripeDetails = $this->getStripeSubscriptionDetails();
        }

        return view('livewire.subscription.billing', [
            'subscription' => $this->subscription,
            'invoices' => $this->invoices,
            'stripeDetails' => $stripeDetails,
        ])->layout('layouts.app', ['header' => '<h2 class="text-[24px] md:text-[30px] font-semibold capitalize text-[#EB1C24]">Billing & Invoices</h2>']);
    }
}
