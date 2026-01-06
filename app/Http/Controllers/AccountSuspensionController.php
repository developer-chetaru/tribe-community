<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Invoice;
use App\Models\SubscriptionRecord;
use App\Services\Billing\StripeService;
use Illuminate\Support\Facades\Log;

class AccountSuspensionController extends Controller
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Show suspension page
     */
    public function show()
    {
        $userId = session()->get('suspended_user_id');
        
        if (!$userId) {
            return redirect()->route('login')->with('error', 'No suspended account found.');
        }

        $user = User::find($userId);
        if (!$user || $user->status !== 'suspended') {
            return redirect()->route('login')->with('error', 'Account is not suspended.');
        }

        $subscription = SubscriptionRecord::where('user_id', $user->id)
            ->where('tier', 'basecamp')
            ->first();

        $unpaidInvoice = null;
        if ($subscription) {
            $unpaidInvoice = Invoice::where('subscription_id', $subscription->id)
                ->where('status', 'unpaid')
                ->orderBy('due_date', 'asc')
                ->first();
        }

        return view('account.suspended', [
            'user' => $user,
            'subscription' => $subscription,
            'invoice' => $unpaidInvoice,
        ]);
    }

    /**
     * Reactivate account by processing payment
     */
    public function reactivate(Request $request)
    {
        $userId = session()->get('suspended_user_id');
        
        if (!$userId) {
            return back()->with('error', 'No suspended account found.');
        }

        $user = User::find($userId);
        if (!$user || $user->status !== 'suspended') {
            return back()->with('error', 'Account is not suspended.');
        }

        $subscription = SubscriptionRecord::where('user_id', $user->id)
            ->where('tier', 'basecamp')
            ->first();

        if (!$subscription) {
            return back()->with('error', 'No subscription found.');
        }

        $unpaidInvoice = Invoice::where('subscription_id', $subscription->id)
            ->where('status', 'unpaid')
            ->orderBy('due_date', 'asc')
            ->first();

        if (!$unpaidInvoice) {
            return back()->with('error', 'No unpaid invoice found.');
        }

        // Redirect to payment page
        return redirect()->route('basecamp.billing', ['user_id' => $user->id])
            ->with('info', 'Please complete payment to reactivate your account.');
    }
}
