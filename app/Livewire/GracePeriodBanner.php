<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\SubscriptionRecord;
use App\Models\PaymentRecord;
use App\Models\Invoice;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class GracePeriodBanner extends Component
{
    public $showBanner = false;
    public $daysRemaining = 0;
    public $invoice = null;
    public $subscription = null;
    public $isBasecamp = false;

    public function mount()
    {
        $this->checkGracePeriod();
    }

    public function poll()
    {
        // Refresh grace period status every 30 seconds
        $this->checkGracePeriod();
    }

    public function checkGracePeriod()
    {
        $user = auth()->user();
        
        if (!$user) {
            $this->showBanner = false;
            return;
        }

        // Skip for super admin
        if ($user->hasRole('super_admin')) {
            $this->showBanner = false;
            return;
        }

        // Check for basecamp users
        if ($user->hasRole('basecamp')) {
            $this->isBasecamp = true;
            $this->subscription = SubscriptionRecord::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->orderBy('id', 'desc')
                ->first();
        } else {
            // Check for organization users
            if ($user->orgId) {
                $subscriptionService = new SubscriptionService();
                $subscriptionStatus = $subscriptionService->getSubscriptionStatus($user->orgId);
                
                if (isset($subscriptionStatus['subscription'])) {
                    $this->subscription = $subscriptionStatus['subscription'];
                }
            }
        }

        // Grace period banner should show when:
        // 1. Subscription status is past_due
        // 2. payment_failed_count >= 3 (after 3 payment failures) OR manually set past_due with unpaid invoice
        // 3. Last failed payment exists OR unpaid invoice exists (for manual testing)
        // 4. Within 7 days of last payment failure OR period end
        
        // Normalize status for comparison (handle both "past_due" and "Past Due")
        $subscriptionStatus = $this->subscription ? strtolower(str_replace(' ', '_', $this->subscription->status)) : null;
        
        if ($this->subscription && $subscriptionStatus === 'past_due') {
            // Get payment failed count
            $paymentFailedCount = $this->subscription->payment_failed_count ?? 0;
            
            // Get unpaid invoice first to check if we should show banner
            $this->invoice = null;
            if ($this->subscription->id) {
                $this->invoice = Invoice::where('subscription_id', $this->subscription->id)
                    ->where('status', 'unpaid')
                    ->latest()
                    ->first();
            }
            
            // If no invoice by subscription_id, check by user_id for basecamp
            if (!$this->invoice && $this->isBasecamp && $user) {
                $this->invoice = Invoice::where('user_id', $user->id)
                    ->where('tier', 'basecamp')
                    ->where('status', 'unpaid')
                    ->latest()
                    ->first();
            }
            
            // Also check by organisation_id for organisation users
            if (!$this->invoice && !$this->isBasecamp && $this->subscription->organisation_id) {
                $this->invoice = Invoice::where('organisation_id', $this->subscription->organisation_id)
                    ->where('status', 'unpaid')
                    ->latest()
                    ->first();
            }
            
            // Show grace period banner if:
            // - payment_failed_count >= 3 with failed payment record (normal flow)
            // - OR manually set past_due with unpaid invoice (for testing/admin override)
            $shouldShowGracePeriod = false;
            
            if ($paymentFailedCount >= 3) {
                // Normal flow: 3+ payment failures
                $shouldShowGracePeriod = true;
            } elseif ($this->invoice) {
                // Manual override: past_due with unpaid invoice (for testing)
                $shouldShowGracePeriod = true;
            }
            
            if ($shouldShowGracePeriod) {
                // Get last failed payment
                $lastPayment = null;
                if ($this->subscription->id) {
                    $lastPayment = PaymentRecord::where('subscription_id', $this->subscription->id)
                        ->where('status', 'failed')
                        ->latest()
                        ->first();
                }
                
                // Calculate grace period
                if ($lastPayment && $paymentFailedCount >= 3) {
                    // Normal flow: Calculate from last payment failure date (7 days grace period)
                    try {
                        // Calculate grace period from last payment failure date (7 days grace period)
                        $failureDate = Carbon::parse($lastPayment->created_at);
                        $suspensionDate = $failureDate->copy()->addDays(7);
                        $now = now();
                        
                        // Calculate days remaining until suspension
                        $this->daysRemaining = max(0, $now->diffInDays($suspensionDate, false));
                    } catch (\Exception $e) {
                        Log::warning('Error calculating grace period from payment failure: ' . $e->getMessage());
                        // Fallback to period end calculation
                        $this->daysRemaining = 7;
                    }
                } elseif ($this->invoice) {
                    // Manual override: Calculate from period end or invoice date
                    try {
                        if ($this->subscription->current_period_end) {
                            // Use period end + 7 days
                            $periodEnd = Carbon::parse($this->subscription->current_period_end);
                            $suspensionDate = $periodEnd->copy()->addDays(7);
                            $now = now();
                            $this->daysRemaining = max(0, $now->diffInDays($suspensionDate, false));
                        } elseif ($this->invoice->due_date) {
                            // Use invoice due date + 7 days
                            $dueDate = Carbon::parse($this->invoice->due_date);
                            $suspensionDate = $dueDate->copy()->addDays(7);
                            $now = now();
                            $this->daysRemaining = max(0, $now->diffInDays($suspensionDate, false));
                        } elseif ($this->invoice->created_at) {
                            // Use invoice created_at + 7 days
                            $invoiceDate = Carbon::parse($this->invoice->created_at);
                            $suspensionDate = $invoiceDate->copy()->addDays(7);
                            $now = now();
                            $this->daysRemaining = max(0, $now->diffInDays($suspensionDate, false));
                        } else {
                            // Default: 7 days from now
                            $this->daysRemaining = 7;
                        }
                    } catch (\Exception $e) {
                        Log::warning('Error calculating grace period from invoice: ' . $e->getMessage());
                        $this->daysRemaining = 7;
                    }
                } else {
                    // No failed payment and no invoice, can't calculate grace period
                    $this->showBanner = false;
                    return;
                }
                
                // Show banner only if still within grace period (days remaining > 0)
                if ($this->daysRemaining > 0) {
                    $this->showBanner = true;
                } else {
                    // Grace period expired, account should be suspended
                    $this->showBanner = false;
                }
            } else {
                // No unpaid invoice and payment_failed_count < 3, not in grace period
                $this->showBanner = false;
            }
        } else {
            // Subscription doesn't exist or status is not past_due
            $this->showBanner = false;
        }
    }

    public function goToBilling()
    {
        $user = auth()->user();
        
        if (!$user) {
            session()->flash('error', 'User not authenticated.');
            return redirect()->route('login');
        }
        
        // Get unpaid invoice if not already set
        if (!$this->invoice) {
            if ($this->isBasecamp) {
                $this->invoice = Invoice::where('user_id', $user->id)
                    ->where('tier', 'basecamp')
                    ->where('status', 'unpaid')
                    ->latest()
                    ->first();
            } elseif ($this->subscription && $this->subscription->organisation_id) {
                $this->invoice = Invoice::where('organisation_id', $this->subscription->organisation_id)
                    ->where('status', 'unpaid')
                    ->latest()
                    ->first();
            }
        }
        
        if (!$this->invoice) {
            // No unpaid invoice, redirect to billing page
            if ($this->isBasecamp) {
                return redirect()->route('basecamp.billing');
            } else {
                return redirect()->route('billing');
            }
        }
        
        try {
            // Create Stripe checkout session
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            
            if ($this->isBasecamp) {
                // Basecamp user - create checkout session
                $monthlyPriceInCents = round($this->invoice->total_amount * 100);
                
                $session = \Stripe\Checkout\Session::create([
                    'payment_method_types' => ['card'],
                    'customer_email' => $user->email,
                    'line_items' => [[
                        'price_data' => [
                            'currency' => 'gbp',
                            'product_data' => [
                                'name' => 'Basecamp Subscription',
                                'description' => 'Monthly subscription for Tribe365 Basecamp',
                            ],
                            'unit_amount' => $monthlyPriceInCents,
                            'recurring' => [
                                'interval' => 'month',
                            ],
                        ],
                        'quantity' => 1,
                    ]],
                    'mode' => 'subscription',
                    'success_url' => route('basecamp.billing.payment.success') . '?session_id={CHECKOUT_SESSION_ID}&invoice_id=' . $this->invoice->id . '&user_id=' . $user->id,
                    'cancel_url' => route('basecamp.billing') . '?user_id=' . $user->id,
                    'metadata' => [
                        'invoice_id' => $this->invoice->id,
                        'user_id' => $user->id,
                        'tier' => 'basecamp',
                        'billing_interval' => 'monthly',
                    ],
                ]);
                
                Log::info('Grace period banner: Basecamp checkout session created', [
                    'session_id' => $session->id,
                    'invoice_id' => $this->invoice->id,
                    'user_id' => $user->id,
                ]);
                
                // Redirect directly to Stripe
                return redirect()->away($session->url);
            } else {
                // Organisation user - create checkout session
                $organisation = \App\Models\Organisation::find($this->subscription->organisation_id);
                if (!$organisation) {
                    return redirect()->route('billing')->with('error', 'Organisation not found.');
                }
                
                $customerEmail = $user->email ?? $organisation->admin_email ?? $organisation->users()->first()?->email;
                
                $checkoutParams = [
                    'payment_method_types' => ['card'],
                    'line_items' => [[
                        'price_data' => [
                            'currency' => 'gbp',
                            'product_data' => [
                                'name' => "Invoice #{$this->invoice->invoice_number}",
                                'description' => "Payment for {$this->invoice->user_count} users - {$organisation->name}",
                            ],
                            'unit_amount' => $this->invoice->total_amount * 100,
                            'recurring' => [
                                'interval' => 'month',
                            ],
                        ],
                        'quantity' => 1,
                    ]],
                    'mode' => 'subscription',
                    'success_url' => route('billing.payment.success') . '?session_id={CHECKOUT_SESSION_ID}&invoice_id=' . $this->invoice->id,
                    'cancel_url' => route('billing') . '?canceled=true',
                    'billing_address_collection' => 'auto',
                    'metadata' => [
                        'invoice_id' => $this->invoice->id,
                        'organisation_id' => $organisation->id,
                        'invoice_number' => $this->invoice->invoice_number,
                        'user_id' => $user->id,
                        'user_email' => $customerEmail,
                    ],
                ];
                
                if ($organisation->stripe_customer_id) {
                    $checkoutParams['customer'] = $organisation->stripe_customer_id;
                } else {
                    $checkoutParams['customer_email'] = $customerEmail;
                }
                
                $session = Session::create($checkoutParams);
                
                Log::info('Grace period banner: Organisation checkout session created', [
                    'session_id' => $session->id,
                    'invoice_id' => $this->invoice->id,
                    'organisation_id' => $organisation->id,
                ]);
                
                // Redirect directly to Stripe
                return redirect()->away($session->url);
            }
        } catch (\Exception $e) {
            Log::error('Grace period banner: Failed to create Stripe checkout', [
                'error' => $e->getMessage(),
                'invoice_id' => $this->invoice->id ?? null,
                'user_id' => $user->id,
            ]);
            
            // Fallback to billing page on error
            if ($this->isBasecamp) {
                return redirect()->route('basecamp.billing')->with('error', 'Failed to create payment session. Please try again.');
            } else {
                return redirect()->route('billing')->with('error', 'Failed to create payment session. Please try again.');
            }
        }
    }

    public function render()
    {
        return view('livewire.grace-period-banner');
    }
}
