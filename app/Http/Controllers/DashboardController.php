<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Organisation;
use App\Models\Invoice;
use App\Models\SubscriptionRecord;
use App\Services\DashboardService;
use App\Services\OneSignalService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
  
   /**
    * Display dashboard with organisation cards data.
    *
    * @param  \Illuminate\Http\Request        $request
    * @param  \App\Services\DashboardService  $service
    * @return \Illuminate\Contracts\View\View
    */
	public function index(Request $request, DashboardService $service)
	{
    	$user = Auth::user();
    	
    	// Show Coming Soon page for super admin users
    	if ($user && $user->hasRole('super_admin')) {
    	    return view('dashboard-coming-soon');
    	}
    	
    	$needsPayment = false;
    	$paymentMessage = '';
    	
    	// Check if basecamp user needs to complete payment or email verification
    	if ($user && $user->hasRole('basecamp')) {
    	    // Check if user has active subscription or paid invoice
            $hasActiveSubscription = SubscriptionRecord::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->where('status', 'active')
                ->where('current_period_end', '>', now())
                ->exists();
                
            $hasPaidInvoice = Invoice::where('user_id', $user->id)
                ->where('tier', 'basecamp')
                ->where('status', 'paid')
                ->exists();
            
            // If no payment completed, show payment popup
            if (!$hasActiveSubscription && !$hasPaidInvoice) {
                $needsPayment = true;
                $paymentMessage = 'Please complete your payment of £12.00 (incl. VAT) to activate your account.';
            }
            
            // If payment completed but account not activated, show verification message
            if (!$user->status) {
                $needsPayment = true;
                $paymentMessage = 'Payment completed! Please check your email to activate your account.';
            }
    	}
    	
    	$organisations = Organisation::with(['users.department', 'users.office'])->get();
		$cards = $organisations->map(function ($org) {
        return [
            	'name'       => $org->name,
            	'culture'    => $org->culture ?? 0,
            	'engagement' => $org->engagement ?? 0,
            	'goodDay'    => $org->good_day ?? 0,
            	'badDay'     => $org->bad_day ?? 0,
            	'hptm'       => $org->hptm ?? 0,
        	];
    	});

    	// ✅ Update OneSignal tags when user accesses dashboard
    	if ($user) {
    	    try {
    	        $oneSignal = new OneSignalService();
    	        $oneSignal->setUserTagsOnLogin($user);
    	        Log::info('OneSignal tags updated on dashboard access', [
    	            'user_id' => $user->id,
    	        ]);
    	    } catch (\Throwable $e) {
    	        Log::warning('OneSignal tag update failed on dashboard access', [
    	            'user_id' => $user->id,
    	            'error' => $e->getMessage(),
    	        ]);
    	    }
    	}

    	return view('dashboard', [
        	'cards'             => $cards,
        	'needsPayment'      => $needsPayment,
        	'paymentMessage'    => $paymentMessage,
        	'user'              => $user ?? null,
    	]);
	}
}
