<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\Payment;
use App\Models\PaymentRecord;
use App\Services\Billing\StripeService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    /**
     * Download invoice as PDF
     */
    public function download($invoiceId)
    {
        $invoice = Invoice::with(['organisation', 'subscription', 'payments.paidBy'])
            ->findOrFail($invoiceId);

        $user = auth()->user();

        // Check permissions - super_admin can access all, director can access their org's invoices
        if ($user->hasRole('super_admin')) {
            // Super admin can access all invoices
        } elseif ($user->hasRole('director')) {
            // Director can only access invoices from their organisation
            if ($invoice->organisation_id !== $user->orgId) {
                abort(403, 'Unauthorized access. You can only access invoices from your organisation.');
            }
        } else {
            abort(403, 'Only directors and administrators can download invoices.');
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

        // Return HTML view with download headers (can be printed as PDF by browser)
        $html = view('invoices.pdf', [
            'invoice' => $invoice,
            'organisation' => $invoice->organisation,
            'subscription' => $invoice->subscription,
            'payments' => $invoice->payments,
        ])->render();

        return response($html)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', 'attachment; filename="invoice-' . $invoice->invoice_number . '.html"');
    }

    /**
     * View invoice in browser
     */
    public function view($invoiceId)
    {
        $invoice = Invoice::with(['organisation', 'subscription', 'payments.paidBy'])
            ->findOrFail($invoiceId);

        $user = auth()->user();

        // Check permissions - super_admin can access all, director can access their org's invoices
        if ($user->hasRole('super_admin')) {
            // Super admin can access all invoices
        } elseif ($user->hasRole('director')) {
            // Director can only access invoices from their organisation
            if ($invoice->organisation_id !== $user->orgId) {
                abort(403, 'Unauthorized access. You can only access invoices from your organisation.');
            }
        } else {
            abort(403, 'Only directors and administrators can view invoices.');
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

        // Return HTML view
        return view('invoices.pdf', [
            'invoice' => $invoice,
            'organisation' => $invoice->organisation,
            'subscription' => $invoice->subscription,
            'payments' => $invoice->payments,
        ]);
    }

    /**
     * View shared invoice (public access without login)
     */
    public function shared($token, Request $request)
    {
        $invoice = Invoice::where('share_token', $token)->firstOrFail();

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

        // If download is requested, return as download
        if ($request->has('download')) {
            $html = view('invoices.shared', [
                'invoice' => $invoice,
                'organisation' => $invoice->organisation,
                'subscription' => $invoice->subscription,
                'payments' => $invoice->payments,
                'token' => $token,
            ])->render();

            return response($html)
                ->header('Content-Type', 'text/html')
                ->header('Content-Disposition', 'attachment; filename="invoice-' . $invoice->invoice_number . '.html"');
        }

        // Return HTML view (public access, no authentication required)
        return view('invoices.shared', [
            'invoice' => $invoice,
            'organisation' => $invoice->organisation,
            'subscription' => $invoice->subscription,
            'payments' => $invoice->payments,
            'token' => $token,
        ]);
    }

    /**
     * Initiate payment for shared invoice (public access)
     */
    public function initiateSharedPayment($token, Request $request)
    {
        try {
            $invoice = Invoice::where('share_token', $token)->firstOrFail();
            
            // Validate email if provided
            $email = $request->input('email');
            if (!$email) {
                return redirect()->route('invoices.shared', ['token' => $token])
                    ->with('error', 'Please enter your email address to proceed with payment.')
                    ->withInput();
            }
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return redirect()->route('invoices.shared', ['token' => $token])
                    ->with('error', 'Please enter a valid email address.')
                    ->withInput();
            }
            
            // Check if invoice is already paid
            if ($invoice->status === 'paid') {
                return redirect()->route('invoices.shared', ['token' => $token])
                    ->with('error', 'This invoice has already been paid.');
            }
            
            // Check if payment already exists
            $existingPayment = Payment::where('invoice_id', $invoice->id)
                ->where('status', 'completed')
                ->first();
            
            if ($existingPayment) {
                return redirect()->route('invoices.shared', ['token' => $token])
                    ->with('error', 'Payment already exists for this invoice.');
            }
            
            // Create Stripe Checkout Session with email
            $checkoutUrl = $this->createPublicStripeCheckoutSession($invoice, $token, $email);
            
            if ($checkoutUrl) {
                return redirect($checkoutUrl);
            }
            
            return redirect()->route('invoices.shared', ['token' => $token])
                ->with('error', 'Failed to initialize payment. Please try again.')
                ->withInput();
                
        } catch (\Exception $e) {
            Log::error('Failed to initiate shared payment: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->route('invoices.shared', ['token' => $token])
                ->with('error', 'Failed to initialize payment: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Create Stripe Checkout Session for public payment
     */
    private function createPublicStripeCheckoutSession($invoice, $token, $customerEmail = null)
    {
        try {
            Log::info('Creating public Stripe Checkout Session for invoice: ' . $invoice->id);
            
            $organisation = Organisation::findOrFail($invoice->organisation_id);
            $stripeService = new StripeService();
            
            // Use provided email or fallback to organisation email
            if (!$customerEmail) {
                $customerEmail = $organisation->admin_email ?? $organisation->users()->first()?->email;
            }
            
            if (!$customerEmail) {
                throw new \Exception('Please provide a valid email address.');
            }
            
            if (!class_exists(\Stripe\Checkout\Session::class)) {
                throw new \Exception('Stripe PHP package is not installed.');
            }
            
            // For public payments, always use customer_email to pre-fill the email
            // Update customer email in Stripe if customer exists
            if ($organisation->stripe_customer_id) {
                try {
                    \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                    $stripeCustomer = \Stripe\Customer::retrieve($organisation->stripe_customer_id);
                    
                    // Update customer email if different
                    if ($stripeCustomer->email !== $customerEmail) {
                        \Stripe\Customer::update($organisation->stripe_customer_id, [
                            'email' => $customerEmail,
                        ]);
                        Log::info('Updated Stripe customer email for public payment', [
                            'customer_id' => $organisation->stripe_customer_id,
                            'new_email' => $customerEmail
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to update customer email in Stripe: ' . $e->getMessage());
                    // Continue anyway - we'll use customer_email parameter
                }
            }
            
            // Create Checkout Session
            // For public payments, use customer_email to pre-fill email field
            // Don't use customer parameter when using customer_email (Stripe doesn't allow both)
            $checkoutParams = [
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => "Invoice #{$invoice->invoice_number}",
                            'description' => "Payment for {$invoice->user_count} users - {$organisation->name}",
                        ],
                        'unit_amount' => $invoice->total_amount * 100, // Convert to cents
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('invoices.shared.payment.success', ['token' => $token]) . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('invoices.shared', ['token' => $token]) . '?canceled=true',
                'billing_address_collection' => 'auto',
                'customer_email' => $customerEmail, // Always use customer_email for public payments to pre-fill email
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'organisation_id' => $organisation->id,
                    'invoice_number' => $invoice->invoice_number,
                    'share_token' => $token,
                    'is_public_payment' => 'true',
                    'customer_email' => $customerEmail,
                    'organisation_stripe_customer_id' => $organisation->stripe_customer_id, // Store in metadata for reference
                ],
            ];
            
            // Note: We don't use 'customer' parameter when using 'customer_email'
            // Stripe doesn't allow both parameters together
            // The customer will be created/linked automatically by Stripe based on the email
            
            $checkoutSession = \Stripe\Checkout\Session::create($checkoutParams);
            
            Log::info('Public Stripe Checkout Session created', [
                'session_id' => $checkoutSession->id,
                'url' => $checkoutSession->url,
                'invoice_id' => $invoice->id
            ]);
            
            return $checkoutSession->url;
            
        } catch (\Exception $e) {
            Log::error('Failed to create public Stripe Checkout Session: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Handle payment success for shared invoice (public access)
     */
    public function handleSharedPaymentSuccess(Request $request, $token)
    {
        try {
            $sessionId = $request->query('session_id');
            
            if (!$sessionId) {
                return redirect()->route('invoices.shared', ['token' => $token])
                    ->with('error', 'Invalid payment session.');
            }
            
            // Initialize Stripe API key
            $stripeSecretKey = config('services.stripe.secret');
            if (!$stripeSecretKey) {
                throw new \Exception('Stripe API key not configured.');
            }
            
            \Stripe\Stripe::setApiKey($stripeSecretKey);
            
            // Retrieve the checkout session from Stripe
            $session = \Stripe\Checkout\Session::retrieve($sessionId);
            
            if ($session->payment_status !== 'paid') {
                return redirect()->route('invoices.shared', ['token' => $token])
                    ->with('error', 'Payment was not completed.');
            }
            
            $invoice = Invoice::where('share_token', $token)->with('subscription')->firstOrFail();
            
            // Check if payment already exists
            $existingPayment = Payment::where('invoice_id', $invoice->id)
                ->where('status', 'completed')
                ->first();
            
            if ($existingPayment) {
                return redirect()->route('invoices.shared', ['token' => $token])
                    ->with('success', 'Payment already processed successfully.');
            }
            
            DB::beginTransaction();
            
            try {
                // Create payment record (no user_id since it's public payment)
                $payment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'organisation_id' => $invoice->organisation_id,
                    'amount' => $invoice->total_amount,
                    'payment_method' => 'stripe',
                    'status' => 'completed',
                    'transaction_id' => $session->payment_intent,
                    'payment_date' => now(),
                    'payment_notes' => "Payment completed via Public Stripe Checkout - Session: {$sessionId}",
                    'paid_by_user_id' => null, // Public payment, no user
                ]);
                
                // Create payment record entry
                PaymentRecord::create([
                    'organisation_id' => $invoice->organisation_id,
                    'subscription_id' => $invoice->subscription_id,
                    'stripe_payment_intent_id' => $session->payment_intent,
                    'amount' => $invoice->total_amount,
                    'currency' => 'usd',
                    'status' => 'succeeded',
                    'type' => 'one_time_payment',
                    'paid_at' => now(),
                ]);
                
                // Update invoice status
                $invoice->status = 'paid';
                $invoice->paid_date = now();
                $invoice->save();
                
                // Activate or renew subscription
                if ($invoice->subscription) {
                    $subscriptionService = new SubscriptionService();
                    $subscriptionService->activateSubscription($payment->id);
                }
                
                DB::commit();
                
                Log::info("Public Stripe Checkout payment completed for invoice {$invoice->id}: {$session->payment_intent}");
                
                return redirect()->route('invoices.shared', ['token' => $token])
                    ->with('success', 'Payment processed successfully!');
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to handle public payment success: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->route('invoices.shared', ['token' => $token])
                ->with('error', 'Failed to process payment: ' . $e->getMessage());
        }
    }
}
