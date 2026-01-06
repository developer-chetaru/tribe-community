<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\User;
use App\Models\SubscriptionRecord;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\StripeService;
use App\Services\OneSignalService;
use App\Mail\PaymentConfirmationMail;
use App\Mail\AccountReactivatedMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class BasecampBilling extends Component
{
    public $showPaymentModal = false;
    public $selectedInvoice = null;
    public $showPaymentPage = false;
    public $stripeClientSecret = null;
    public $stripePaymentIntentId = null;
    public $isProcessingStripePayment = false;
    public $subscription = null;
    public $monthlyPrice = 10; // $10 per month for basecamp users
    
    public $userId = null;
    public $invoiceId = null;
    
    // Filter/search properties
    public $searchQuery = '';
    public $statusFilter = '';
    public $showCancelModal = false;
    public $showUpdatePaymentModal = false;
    public $paymentMethod = null;
    public $showInvoiceModal = false;
    public $selectedInvoiceForView = null;
    public $showShareModal = false;
    public $selectedInvoiceForShare = null;
    public $shareLink = '';
    
    public function mount($user_id = null)
    {
        Log::info('=== BasecampBilling MOUNT START ===');
        
        // Get user_id from parameter, request query, session, or auth
        $this->userId = $user_id ?? request()->query('user_id') ?? session('basecamp_user_id') ?? auth()->id();
        
        Log::info('BasecampBilling - User ID: ' . ($this->userId ?? 'NULL'));
        Log::info('BasecampBilling - Request user_id: ' . (request()->query('user_id') ?? 'NULL'));
        
        if (!$this->userId) {
            Log::info('BasecampBilling - No user ID, showing empty page');
            // Don't redirect, just show empty billing page
            return;
        }
        
        // Get user by ID
        $user = \App\Models\User::find($this->userId);
        
        if (!$user) {
            Log::info('BasecampBilling - User not found, showing empty page');
            // Don't redirect, just show empty billing page
            return;
        }
        
        // Refresh user to ensure role is loaded
        $user->refresh();
        $user->load('roles');
        
        Log::info('BasecampBilling - User roles: ' . $user->roles->pluck('name')->implode(', '));
        Log::info('BasecampBilling - hasRole(basecamp): ' . ($user->hasRole('basecamp') ? 'TRUE' : 'FALSE'));
        
        // Check if user is basecamp
        if (!$user->hasRole('basecamp')) {
            Log::info('BasecampBilling - User is NOT basecamp, aborting 403');
            abort(403, 'Only basecamp users can access this billing page.');
        }
        
        Log::info('BasecampBilling - User is basecamp, loading data');
        
        // Load subscription
        $this->loadSubscriptionForUser($user);
        
        // Get existing invoice
        $existingInvoice = Invoice::where('user_id', $user->id)
            ->where('tier', 'basecamp')
            ->where('status', 'unpaid')
            ->latest()
            ->first();
            
        if ($existingInvoice) {
            $this->invoiceId = $existingInvoice->id;
            $this->selectedInvoice = $existingInvoice;
        }
        
        Log::info('=== BasecampBilling MOUNT END ===');
    }
    
    public function loadSubscriptionForUser($user)
    {
        // For basecamp users, find subscription by user_id
        $this->subscription = SubscriptionRecord::where('user_id', $user->id)
            ->where('tier', 'basecamp')
            ->first();
            
        if (!$this->subscription) {
            // Create subscription if it doesn't exist
            $this->subscription = SubscriptionRecord::create([
                'user_id' => $user->id,
                'organisation_id' => null,
                'tier' => 'basecamp',
                'user_count' => 1,
                'status' => 'inactive',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                'next_billing_date' => now()->addMonth(),
            ]);
        }
    }
    
    public function loadSubscription()
    {
        $user = auth()->user();
        if ($user) {
            $this->loadSubscriptionForUser($user);
        }
    }
    
    public function createInvoice()
    {
        $user = \App\Models\User::find($this->userId);
        
        if (!$user) {
            session()->flash('error', 'User not found.');
            return;
        }
        
        // Load or create subscription first
        if (!$this->subscription) {
            $this->loadSubscriptionForUser($user);
        }
        
        // Use database transaction with lock to prevent race conditions
        // Check for ANY invoice (paid or unpaid) created today for this user to prevent duplicates
        $today = now()->toDateString();
        $existingInvoice = DB::transaction(function () use ($today) {
            // Lock the row to prevent concurrent invoice creation
            // Check for invoices created today (same date) regardless of status
            return Invoice::where('user_id', $this->userId)
                ->where('tier', 'basecamp')
                ->whereDate('invoice_date', $today)
                ->where('subscription_id', $this->subscription->id)
                ->lockForUpdate()
                ->first();
        });
            
        if ($existingInvoice) {
            Log::info('Existing invoice found for today - ID: ' . $existingInvoice->id . ', Status: ' . $existingInvoice->status);
            // If unpaid, use it for payment
            if ($existingInvoice->status === 'unpaid') {
                $this->selectedInvoice = $existingInvoice;
                $this->invoiceId = $existingInvoice->id;
                session()->put('basecamp_invoice_id', $existingInvoice->id);
                $this->openPaymentModal();
            } else {
                // If already paid, show message
                session()->flash('info', 'Invoice for today already exists and is paid.');
            }
            return;
        }
        
        // Double-check again to prevent duplicates (extra safety)
        $finalCheck = Invoice::where('user_id', $this->userId)
            ->where('tier', 'basecamp')
            ->whereDate('invoice_date', $today)
            ->where('subscription_id', $this->subscription->id)
            ->first();
            
        if ($finalCheck) {
            Log::info('Invoice already exists for today (double-check) - ID: ' . $finalCheck->id);
            if ($finalCheck->status === 'unpaid') {
                $this->selectedInvoice = $finalCheck;
                $this->invoiceId = $finalCheck->id;
                session()->put('basecamp_invoice_id', $finalCheck->id);
                $this->openPaymentModal();
            } else {
                session()->flash('info', 'Invoice for today already exists and is paid.');
            }
            return;
        }
        
        // Calculate due date: 7 days from invoice date
        $dueDate = now()->addDays(7);
        
        // If subscription exists and has an end date, set due date to subscription end date or 7 days from now, whichever is earlier
        if ($this->subscription && $this->subscription->current_period_end) {
            $subscriptionEndDate = Carbon::parse($this->subscription->current_period_end);
            // Due date should be 7 days from invoice date, but not later than subscription end date
            $dueDate = min($dueDate, $subscriptionEndDate);
        }
        
        // Create new invoice within transaction
        $invoice = DB::transaction(function () use ($dueDate) {
            return Invoice::create([
            'user_id' => $this->userId,
            'organisation_id' => null, // Null for basecamp users
            'subscription_id' => $this->subscription->id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'tier' => 'basecamp',
            'user_count' => 1,
            'price_per_user' => $this->monthlyPrice,
            'subtotal' => $this->monthlyPrice,
            'tax_amount' => 0,
            'total_amount' => $this->monthlyPrice,
            'status' => 'unpaid',
            'due_date' => $dueDate,
            'invoice_date' => now(),
            ]);
        });
        
        Log::info('New invoice created - ID: ' . $invoice->id . ', Invoice Number: ' . $invoice->invoice_number);
        
        $this->selectedInvoice = $invoice;
        $this->invoiceId = $invoice->id;
        session()->put('basecamp_invoice_id', $invoice->id);
        $this->openPaymentModal();
    }
    
    public function openPaymentModal()
    {
        if (!$this->selectedInvoice) {
            session()->flash('error', 'No invoice selected.');
            return;
        }
        
        // Create Stripe Checkout Session and redirect to Stripe website
        $checkoutUrl = $this->createStripeCheckoutSession();
        
        if ($checkoutUrl) {
            // Redirect to Stripe checkout
            return redirect($checkoutUrl);
        }
    }
    
    public function createStripeCheckoutSession()
    {
        try {
            if (!$this->selectedInvoice) {
                Log::warning('createStripeCheckoutSession called but no invoice selected');
                session()->flash('error', 'No invoice selected.');
                return null;
            }
            
            Log::info('Creating Stripe Checkout Session for basecamp invoice: ' . $this->selectedInvoice->id);
            
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            
            $user = \App\Models\User::find($this->userId);
            
            if (!$user) {
                session()->flash('error', 'User not found.');
                return null;
            }
            
            // Create Checkout Session
            $checkoutParams = [
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => 'Basecamp Subscription',
                            'description' => 'Monthly subscription for Basecamp tier',
                        ],
                        'unit_amount' => $this->monthlyPrice * 100, // Convert to cents
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('basecamp.billing.payment.success') . '?session_id={CHECKOUT_SESSION_ID}&invoice_id=' . $this->selectedInvoice->id . '&user_id=' . $this->userId,
                'cancel_url' => route('basecamp.billing') . '?user_id=' . $this->userId,
                'customer_email' => $user->email,
                'metadata' => [
                    'invoice_id' => $this->selectedInvoice->id,
                    'user_id' => $this->userId,
                    'tier' => 'basecamp',
                ],
            ];
            
            $checkoutSession = \Stripe\Checkout\Session::create($checkoutParams);
            
            Log::info('Stripe Checkout Session created', [
                'session_id' => $checkoutSession->id,
                'url' => $checkoutSession->url,
                'invoice_id' => $this->selectedInvoice->id,
            ]);
            
            return $checkoutSession->url;
            
        } catch (\Exception $e) {
            Log::error('Failed to create Stripe Checkout Session: ' . $e->getMessage(), [
                'invoice_id' => $this->selectedInvoice->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            session()->flash('error', 'Failed to initialize payment: ' . $e->getMessage());
            return null;
        }
    }
    
    public function createStripePaymentIntent()
    {
        try {
            if (!$this->selectedInvoice) {
                Log::warning('createStripePaymentIntent called but no invoice selected');
                return;
            }
            
            Log::info('Creating Stripe payment intent for basecamp invoice: ' . $this->selectedInvoice->id);
            
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            
            $user = \App\Models\User::find($this->userId);
            
            if (!$user) {
                session()->flash('error', 'User not found.');
                return;
            }
            
            // Create payment intent
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $this->monthlyPrice * 100, // Convert to cents
                'currency' => 'usd',
                'payment_method_types' => ['card'],
                'metadata' => [
                    'invoice_id' => $this->selectedInvoice->id,
                    'user_id' => $this->userId,
                    'tier' => 'basecamp',
                    'description' => "Basecamp subscription - $10/month",
                ],
                'description' => "Basecamp Subscription - {$user->first_name} {$user->last_name}",
            ]);
            
            $this->stripeClientSecret = $paymentIntent->client_secret;
            $this->stripePaymentIntentId = $paymentIntent->id;
            
            Log::info('Stripe payment intent created successfully', [
                'payment_intent_id' => $this->stripePaymentIntentId,
                'invoice_id' => $this->selectedInvoice->id,
            ]);
            
            $this->dispatch('stripe-payment-intent-created', [
                'clientSecret' => $this->stripeClientSecret,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to create Stripe payment intent: ' . $e->getMessage(), [
                'invoice_id' => $this->selectedInvoice->id ?? null,
                'trace' => $e->getTraceAsString(),
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
        
        // Check if terms checkbox is checked (if present in request)
        $termsAccepted = request()->input('terms_accepted', false);
        if (!$termsAccepted) {
            session()->flash('error', 'Please accept the Terms of Service and Privacy Policy to continue.');
            $this->isProcessingStripePayment = false;
            return;
        }
        
        $this->isProcessingStripePayment = true;
        
        try {
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            
            $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);
            
            if ($paymentIntent->status !== 'succeeded') {
                session()->flash('error', 'Payment not completed. Status: ' . $paymentIntent->status);
                $this->isProcessingStripePayment = false;
                return;
            }
            
            // Validate payment amount matches invoice amount
            $paymentAmount = $paymentIntent->amount / 100; // Convert from cents
            if (abs($paymentAmount - $this->selectedInvoice->total_amount) > 0.01) {
                session()->flash('error', 'Payment amount does not match invoice amount.');
                $this->isProcessingStripePayment = false;
                return;
            }
            
            // Check if payment already exists
            $existingPayment = Payment::where('invoice_id', $this->selectedInvoice->id)
                ->where('transaction_id', $paymentIntentId)
                ->first();
                
            if ($existingPayment) {
                session()->flash('error', 'Payment already exists for this invoice.');
                $this->isProcessingStripePayment = false;
                return;
            }
            
            // Get user
            $user = \App\Models\User::find($this->userId);
            
            if (!$user) {
                session()->flash('error', 'User not found.');
                $this->isProcessingStripePayment = false;
                return;
            }
            
            // Create payment record - use validated payment amount from Stripe
            $payment = Payment::create([
                'invoice_id' => $this->selectedInvoice->id,
                'user_id' => $this->userId,
                'payment_method' => 'card',
                'amount' => $paymentAmount, // Use validated amount from Stripe
                'transaction_id' => $paymentIntentId,
                'payment_date' => now()->toDateString(),
                'payment_notes' => 'Basecamp subscription payment via Stripe',
            ]);
            
            // Update invoice status
            $this->selectedInvoice->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
            
            // Check if account was suspended
            $wasSuspended = $user->status === 'suspended';
            
            // Update user status
            $user->update([
                'status' => $user->email_verified_at ? 'active_verified' : 'active_unverified',
                'payment_grace_period_start' => null,
                'last_payment_failure_date' => null,
                'suspension_date' => null,
            ]);
            
            // Activate subscription
            $this->subscription->update([
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                'next_billing_date' => now()->addMonth(),
                'payment_failed_count' => 0, // Reset failure count
            ]);
            
            // Send payment confirmation email
            try {
                if ($wasSuspended) {
                    Mail::to($user->email)->send(new AccountReactivatedMail($user, $this->selectedInvoice));
                } else {
                    Mail::to($user->email)->send(new PaymentConfirmationMail($user, $this->selectedInvoice));
                }
            } catch (\Exception $e) {
                Log::error("Failed to send payment confirmation email: " . $e->getMessage());
            }
            
            // Send activation email after payment is completed (if not already verified)
            if (!$user->email_verified_at) {
                $this->sendActivationEmail($user);
            }
            
            // If payment method was updated, check for other unpaid invoices and retry
            $this->retryUnpaidInvoicesAfterPaymentMethodUpdate($user);
            
            Log::info("Stripe payment confirmed for basecamp invoice {$this->selectedInvoice->id}: {$paymentIntentId}");
            
            // Clear session data
            session()->forget('basecamp_user_id');
            session()->forget('basecamp_invoice_id');
            
            session()->flash('status', 'Payment processed successfully! Please check your email to activate your account. You can now login.');
            
            return $this->redirect(route('login'));
            
        } catch (\Exception $e) {
            Log::error('Failed to confirm Stripe payment: ' . $e->getMessage());
            session()->flash('error', 'Payment confirmation failed: ' . $e->getMessage());
        }
        
        $this->isProcessingStripePayment = false;
    }
    
    public function closePaymentModal()
    {
        $this->showPaymentModal = false;
        $this->showPaymentPage = false;
        $this->selectedInvoice = null;
        $this->stripeClientSecret = null;
        $this->stripePaymentIntentId = null;
        $this->isProcessingStripePayment = false;
    }
    
    public function closePaymentPage()
    {
        $this->showPaymentPage = false;
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
            if (!$this->subscription) {
                session()->flash('error', 'No subscription found.');
                return;
            }
            
            $user = \App\Models\User::find($this->userId);
            if (!$user) {
                session()->flash('error', 'User not found.');
                return;
            }
            
            // Update subscription status to canceled
            $this->subscription->update([
                'status' => 'canceled',
                'canceled_at' => now(),
            ]);
            
            // If Stripe subscription exists, cancel it
            if ($this->subscription->stripe_subscription_id) {
                $stripeService = new StripeService();
                $stripeService->cancelSubscription($this->subscription->stripe_subscription_id, true);
            }
            
            session()->flash('success', 'Subscription has been canceled. You will continue to have access until the end of your billing period.');
            $this->closeCancelModal();
            $this->loadSubscriptionForUser($user);
            
        } catch (\Exception $e) {
            Log::error('Failed to cancel subscription: ' . $e->getMessage());
            session()->flash('error', 'Failed to cancel subscription: ' . $e->getMessage());
        }
    }
    
    public function openUpdatePaymentModal()
    {
        $this->loadPaymentMethod();
        $this->showUpdatePaymentModal = true;
    }
    
    public function closeUpdatePaymentModal()
    {
        $this->showUpdatePaymentModal = false;
    }
    
    public function loadPaymentMethod()
    {
        if (!$this->subscription || !$this->userId) {
            return;
        }
        
        // Get the last successful payment for this user
        $lastPayment = Payment::where('user_id', $this->userId)
            ->where('payment_method', 'card')
            ->whereNotNull('transaction_id')
            ->latest()
            ->first();
            
        if ($lastPayment && $lastPayment->transaction_id) {
            try {
                \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                $paymentIntent = \Stripe\PaymentIntent::retrieve($lastPayment->transaction_id);
                
                if ($paymentIntent->payment_method) {
                    $paymentMethod = \Stripe\PaymentMethod::retrieve($paymentIntent->payment_method);
                    $this->paymentMethod = [
                        'brand' => $paymentMethod->card->brand ?? 'Card',
                        'last4' => $paymentMethod->card->last4 ?? '****',
                        'exp_month' => $paymentMethod->card->exp_month ?? null,
                        'exp_year' => $paymentMethod->card->exp_year ?? null,
                    ];
                }
            } catch (\Exception $e) {
                Log::warning('Failed to load payment method: ' . $e->getMessage());
            }
        }
    }
    
    public function openInvoiceModal($invoiceId)
    {
        $user = \App\Models\User::find($this->userId);
        if (!$user) {
            session()->flash('error', 'User not found.');
            return;
        }
        
        $invoice = Invoice::with(['subscription', 'payments.paidBy'])
            ->findOrFail($invoiceId);
        
        // Check if invoice belongs to this user
        if ($invoice->user_id !== $user->id) {
            session()->flash('error', 'You can only view your own invoices.');
            return;
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
                    Log::warning('Failed to retrieve Stripe payment method: ' . $e->getMessage());
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
        $user = \App\Models\User::find($this->userId);
        if (!$user) {
            session()->flash('error', 'User not found.');
            return;
        }
        
        $invoice = Invoice::findOrFail($invoiceId);
        
        // Check if invoice belongs to this user
        if ($invoice->user_id !== $user->id) {
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
    
    public function render()
    {
        // Get user - if no user, show empty billing page
        $user = $this->userId ? \App\Models\User::find($this->userId) : null;
        
        if (!$user) {
            return view('livewire.basecamp-billing', [
                'invoices' => collect(),
                'isActive' => false,
                'user' => null,
            ])->layout('layouts.app', [
                'title' => 'Basecamp Billing',
            ]);
        }
        
        // Load subscription if not loaded
        if (!$this->subscription) {
            $this->loadSubscriptionForUser($user);
        }
        
        // Get invoices for this user with filters
        $invoicesQuery = Invoice::where('user_id', $this->userId)
            ->where('tier', 'basecamp');
            
        // Apply search filter
        if ($this->searchQuery) {
            $invoicesQuery->where(function($q) {
                $q->where('invoice_number', 'like', '%' . $this->searchQuery . '%')
                  ->orWhere('total_amount', 'like', '%' . $this->searchQuery . '%');
            });
        }
        
        // Apply status filter
        if ($this->statusFilter) {
            $invoicesQuery->where('status', $this->statusFilter);
        }
        
        $invoices = $invoicesQuery->orderBy('created_at', 'desc')->get();
        
        // Load payment method for display
        $this->loadPaymentMethod();
            
        $isActive = $this->subscription && 
                   $this->subscription->status === 'active' && 
                   $this->subscription->current_period_end > now();
        
        return view('livewire.basecamp-billing', [
            'invoices' => $invoices,
            'isActive' => $isActive,
            'user' => $user,
        ])->layout('layouts.app', [
            'title' => 'Basecamp Billing',
        ]);
    }
}

