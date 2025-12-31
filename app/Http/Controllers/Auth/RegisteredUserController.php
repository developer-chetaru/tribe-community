<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Invoice;
use App\Models\SubscriptionRecord;

class RegisteredUserController extends Controller
{
    /**
    * Handle an incoming registration request.
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\RedirectResponse
    */
    public function store(Request $request)
    {
        Log::info('=== REGISTRATION START ===');
        Log::info('Registration request data: ' . json_encode($request->except(['password', 'password_confirmation'])));
        
        $user = app(CreateNewUser::class)->create($request->all());
        
        Log::info('User created - ID: ' . $user->id . ', Email: ' . $user->email);
        
        // Refresh user to ensure role is loaded from database
        $user->refresh();
        $user->load('roles');
        
        Log::info('User roles after refresh: ' . $user->roles->pluck('name')->implode(', '));
        Log::info('hasRole(basecamp) check: ' . ($user->hasRole('basecamp') ? 'TRUE' : 'FALSE'));
        
        // For basecamp users, create invoice and redirect to billing page (no login required)
        if ($user->hasRole('basecamp')) {
            Log::info('User is basecamp - Creating invoice and redirecting to billing');
            
            // Create subscription record for basecamp user
            $subscription = SubscriptionRecord::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'tier' => 'basecamp',
                ],
                [
                    'status' => 'inactive',
                    'user_count' => 1,
                ]
            );
            
            // Create invoice for basecamp user
            $invoice = Invoice::create([
                'user_id' => $user->id,
                'organisation_id' => null,
                'subscription_id' => $subscription->id,
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'tier' => 'basecamp',
                'user_count' => 1,
                'price_per_user' => 10,
                'subtotal' => 10,
                'tax_amount' => 0,
                'total_amount' => 10,
                'status' => 'unpaid',
                'due_date' => now()->addDays(7),
                'invoice_date' => now(),
            ]);
            
            Log::info('Invoice created - ID: ' . $invoice->id . ', Invoice Number: ' . $invoice->invoice_number);
            
            // Store user_id in session for payment page access
            $request->session()->put('basecamp_user_id', $user->id);
            $request->session()->put('basecamp_invoice_id', $invoice->id);
            
            // Redirect to billing page with user_id
            $billingUrl = route('basecamp.billing', ['user_id' => $user->id]);
            Log::info('Billing URL: ' . $billingUrl);
            
            return redirect($billingUrl)->with('status', 'Please complete your payment of $10 to activate your account. After payment, you will receive an activation email.');
        }
        
        Log::info('User is NOT basecamp - Redirecting to login');
        Log::info('=== REGISTRATION END ===');
        
        return redirect()->route('login')->with('status', 'Check your email to verify your account. For using Tribe365, account need to be verified. Your account is not activated yet.');
    }
}
