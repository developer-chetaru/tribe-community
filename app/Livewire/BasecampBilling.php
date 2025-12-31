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
    
    public function mount($user_id = null)
    {
        Log::info('=== BasecampBilling MOUNT START ===');
        
        // Get user_id from parameter, session, or auth
        $this->userId = $user_id ?? session('basecamp_user_id') ?? auth()->id();
        
        Log::info('BasecampBilling - User ID: ' . ($this->userId ?? 'NULL'));
        
        if (!$this->userId) {
            Log::info('BasecampBilling - No user ID, redirecting to login');
            return $this->redirect(route('login'));
        }
        
        // Get user by ID (no auth required)
        $user = \App\Models\User::find($this->userId);
        
        if (!$user) {
            Log::info('BasecampBilling - User not found, redirecting to login');
            return $this->redirect(route('login'));
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
        
        Log::info('BasecampBilling - User is basecamp, loading subscription');
        
        // Get invoice ID from session or find unpaid invoice
        $this->invoiceId = session('basecamp_invoice_id');
        if (!$this->invoiceId) {
            $unpaidInvoice = Invoice::where('user_id', $this->userId)
                ->where('tier', 'basecamp')
                ->where('status', 'unpaid')
                ->latest()
                ->first();
            if ($unpaidInvoice) {
                $this->invoiceId = $unpaidInvoice->id;
                $this->selectedInvoice = $unpaidInvoice;
                // Auto-open payment modal if unpaid invoice exists
                $this->showPaymentPage = true;
            }
        } else {
            $this->selectedInvoice = Invoice::find($this->invoiceId);
            // Don't auto-open payment - user will click "Pay Now" button
        }
        
        // Don't auto-open payment modal - user will click "Pay Now" button
        // This allows them to see the invoice first
        
        // Load or create subscription
        $this->loadSubscriptionForUser($user);
        
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
        
        // Check if there's already an unpaid invoice
        $existingInvoice = Invoice::where('user_id', $this->userId)
            ->where('status', 'unpaid')
            ->where('tier', 'basecamp')
            ->first();
            
        if ($existingInvoice) {
            $this->selectedInvoice = $existingInvoice;
            $this->invoiceId = $existingInvoice->id;
            $this->openPaymentModal();
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
        
        // Create new invoice
        $invoice = Invoice::create([
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
        
        $this->isProcessingStripePayment = true;
        
        try {
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            
            $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);
            
            if ($paymentIntent->status !== 'succeeded') {
                session()->flash('error', 'Payment not completed. Status: ' . $paymentIntent->status);
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
            
            // Create payment record
            $payment = Payment::create([
                'invoice_id' => $this->selectedInvoice->id,
                'user_id' => $this->userId,
                'payment_method' => 'card',
                'amount' => $this->monthlyPrice,
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
        $user = $this->userId ? \App\Models\User::find($this->userId) : null;
        
        if (!$user) {
            return view('livewire.basecamp-billing', [
                'invoices' => collect(),
                'isActive' => false,
            ])->layout('layouts.app', [
                'title' => 'Basecamp Billing',
            ]);
        }
        
        // Load subscription first
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
        ])->layout('layouts.app', [
            'title' => 'Basecamp Billing',
        ]);
    }
}

