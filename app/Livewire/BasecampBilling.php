<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\User;
use App\Models\SubscriptionRecord;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\StripeService;
use App\Services\OneSignalService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;
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
    public $monthlyPrice = 10; // £10 per month for basecamp users
    
    public $userId = null;
    public $invoiceId = null;
    public $stripeCheckoutUrl = null;
    
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
        
        // Calculate VAT (20% of subtotal)
        $subtotal = $this->monthlyPrice;
        $taxAmount = $subtotal * 0.20; // 20% VAT
        $totalAmount = $subtotal + $taxAmount;
        
        // Create new invoice within transaction
        $invoice = DB::transaction(function () use ($dueDate, $subtotal, $taxAmount, $totalAmount) {
            return Invoice::create([
            'user_id' => $this->userId,
            'organisation_id' => null, // Null for basecamp users
            'subscription_id' => $this->subscription->id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'tier' => 'basecamp',
            'user_count' => 1,
            'price_per_user' => $this->monthlyPrice,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
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
    
    public function openPaymentModal($invoiceId = null)
    {
        // If invoice ID is provided, set it first
        if ($invoiceId) {
            $this->selectedInvoice = Invoice::find($invoiceId);
            $this->invoiceId = $invoiceId;
            session()->put('basecamp_invoice_id', $invoiceId);
        }
        
        if (!$this->selectedInvoice) {
            session()->flash('error', 'No invoice selected.');
            Log::error('openPaymentModal: No invoice selected', [
                'invoice_id' => $invoiceId,
                'selected_invoice' => $this->selectedInvoice?->id,
            ]);
            return;
        }
        
        Log::info('openPaymentModal: Creating checkout session', [
            'invoice_id' => $this->selectedInvoice->id,
            'user_id' => $this->userId,
        ]);
        
        // Create Stripe Checkout Session and redirect to Stripe website
        $checkoutUrl = $this->createStripeCheckoutSession();
        
        if ($checkoutUrl) {
            // Store URL in session for redirect route
            session()->put('stripe_checkout_redirect', $checkoutUrl);
            // Use Livewire redirect to go to redirect route
            return $this->redirect(route('basecamp.checkout.redirect'), navigate: false);
        } else {
            session()->flash('error', 'Failed to create payment session. Please try again.');
            Log::error('openPaymentModal: Failed to create checkout session');
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
                        'currency' => 'gbp',
                        'product_data' => [
                            'name' => 'Basecamp Subscription',
                            'description' => 'Monthly subscription for Basecamp tier',
                        ],
                        'unit_amount' => ($this->selectedInvoice->total_amount ?? $this->monthlyPrice) * 100, // Convert to cents
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
            
            // Create payment intent - use total_amount (includes VAT) from invoice
            $amountToCharge = $this->selectedInvoice->total_amount ?? ($this->monthlyPrice * 1.20); // Include VAT
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => (int)($amountToCharge * 100), // Convert to cents
                'currency' => 'gbp',
                'payment_method_types' => ['card'],
                'metadata' => [
                    'invoice_id' => $this->selectedInvoice->id,
                    'user_id' => $this->userId,
                    'tier' => 'basecamp',
                    'description' => "Basecamp subscription - £10/month + VAT",
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
            
            // Activate subscription
            $this->subscription->update([
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                'next_billing_date' => now()->addMonth(),
            ]);
            
            // Send activation email after payment is completed
            $this->sendActivationEmail($user);
            
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
        
        // Get invoices for this user
        $invoices = Invoice::where('user_id', $this->userId)
            ->where('tier', 'basecamp')
            ->orderBy('created_at', 'desc')
            ->get();
            
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

