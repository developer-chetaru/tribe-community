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
    
    protected $listeners = ['refreshPayments' => '$refresh'];
    
    public $subscriptionStatus = [];
    public $daysRemaining = 0;
    public $showRenewModal = false;
    public $renewalPrice = 0;
    public $renewalUserCount = 0;
    public $renewalExpiryDate;
    public $renewalPricePerUser = 0;
    
    // Stripe payment properties
    public $stripeClientSecret = null;
    public $stripePaymentIntentId = null;
    public $isProcessingStripePayment = false;

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
        $invoice = Invoice::with(['subscription', 'payments.paidBy', 'organisation'])
            ->findOrFail($invoiceId);
        
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
        
        try {
            $invoice = Invoice::with('subscription')->findOrFail($invoiceId);
            
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
                        'unit_amount' => $invoice->total_amount * 100, // Convert to pence
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
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
                'amount' => $this->selectedInvoice->total_amount * 100, // Convert to pence
                'currency' => 'gbp',
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

            // Check if payment already exists
            $existingPayment = Payment::where('invoice_id', $this->selectedInvoice->id)
                ->where('status', 'completed')
                ->first();
            
            if ($existingPayment) {
                session()->flash('error', 'Payment already exists for this invoice.');
                $this->isProcessingStripePayment = false;
                return;
            }

            // Use database transaction
            \DB::beginTransaction();
            
            try {
                // Create payment record
                $payment = Payment::create([
                    'invoice_id' => $this->selectedInvoice->id,
                    'organisation_id' => $this->selectedInvoice->organisation_id,
                    'paid_by_user_id' => auth()->id(),
                    'payment_method' => 'card',
                    'amount' => $paymentIntent->amount / 100, // Convert from pence
                    'transaction_id' => $paymentIntent->id,
                    'status' => 'completed',
                    'payment_date' => now()->toDateString(),
                    'payment_notes' => 'Payment processed via Stripe',
                    'approved_by_admin_id' => null,
                    'approved_at' => now(),
                ]);

                // Create payment record for Stripe tracking
                \App\Models\PaymentRecord::create([
                    'organisation_id' => $this->selectedInvoice->organisation_id,
                    'stripe_payment_intent_id' => $paymentIntent->id,
                    'stripe_customer_id' => $paymentIntent->customer,
                    'amount' => $paymentIntent->amount / 100,
                    'currency' => $paymentIntent->currency,
                    'status' => $paymentIntent->status,
                    'type' => 'payment',
                    'paid_at' => now(),
                ]);

                // Update invoice status
                $this->selectedInvoice->update([
                    'status' => 'paid',
                    'paid_date' => now(),
                ]);

                // Activate/renew subscription
                $subscriptionService = new SubscriptionService();
                if ($this->selectedInvoice->subscription) {
                    $userCount = $this->selectedInvoice->user_count;
                    $pricePerUser = $this->selectedInvoice->price_per_user;
                    $subscriptionService->renewSubscription($this->selectedInvoice->subscription, $userCount, $pricePerUser);
                } else {
                    $subscriptionService->activateSubscription($payment->id);
                }

                \DB::commit();
                
                \Log::info("Stripe payment confirmed for invoice {$this->selectedInvoice->id}: {$paymentIntent->id}");

                session()->flash('success', 'Payment processed successfully. Your subscription has been activated.');
                $this->closePaymentPage();
                $this->checkSubscriptionStatus();
                $this->dispatch('payment-successful');
                
            } catch (\Exception $e) {
                \DB::rollBack();
                throw $e;
            }
            
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
        // Get the most recent subscription regardless of status
        return \App\Models\SubscriptionRecord::where('organisation_id', $user->orgId)
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
