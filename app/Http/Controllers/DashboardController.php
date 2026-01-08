<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Organisation;
use App\Models\Invoice;
use App\Models\SubscriptionRecord;
use App\Services\DashboardService;
use App\Services\OneSignalService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

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
    	
    	// Check if subscription has expired (from session first, then direct check)
    	if (session('subscription_expired')) {
    	    $needsPayment = true;
    	    $subscriptionStatus = session('subscription_status', []);
    	    $isBasecamp = $user && $user->hasRole('basecamp');
    	    
    	    if ($isBasecamp) {
    	        $paymentMessage = 'Please complete your payment of £12.00 (incl. VAT) to activate your account.';
    	    } else {
    	        $userCount = $user && $user->orgId ? \App\Models\User::where('orgId', $user->orgId)
                    ->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'))
                    ->count() : 0;
    	        $totalAmount = $userCount * 10.00;
    	        $paymentMessage = "Please complete your payment of £" . number_format($totalAmount, 2) . " to activate your account.";
    	    }
    	} elseif ($user && !$user->hasRole('super_admin')) {
    	    $isExpired = false;
    	    
    	    // Check for basecamp users
    	    if ($user->hasRole('basecamp')) {
    	        // Get LATEST subscription to ensure we check the most recent payment
    	        $subscription = SubscriptionRecord::where('user_id', $user->id)
                    ->where('tier', 'basecamp')
                    ->orderBy('id', 'desc') // Use ID for better ordering - gets latest payment
                    ->first();
                    
                if ($subscription) {
                    $endDate = $subscription->current_period_end ? Carbon::parse($subscription->current_period_end)->startOfDay() : null;
                    $today = Carbon::today();
                    
                    // Check if expired
                    // Important: Cancelled subscriptions are still active until end date passes
                    if ($endDate && $today->greaterThan($endDate)) {
                        // End date has passed - subscription is expired regardless of status
                        $isExpired = true;
                    } elseif ($endDate && $endDate->isFuture()) {
                        // End date is in future - subscription is active (even if cancelled)
                        // User can access until the end date
                        $isExpired = false;
                    } elseif (!$endDate) {
                        // No end date - check status
                        if ($subscription->status !== 'active') {
                            $isExpired = true;
                        } else {
                            $isExpired = false;
                        }
                    } else {
                        $isExpired = false;
                    }
                } else {
                    // No subscription = expired
                    $isExpired = true;
                }
                
                if ($isExpired) {
                    $needsPayment = true;
                    $paymentMessage = 'Please complete your payment of £12.00 (incl. VAT) to activate your account.';
                } else {
                    // Subscription is active - clear any expired flags
                    session()->forget('subscription_expired');
                    session()->forget('subscription_status');
                }
            }
            
            // Check for organization users
            if ($user->orgId) {
                $subscriptionService = new SubscriptionService();
                $subscriptionStatus = $subscriptionService->getSubscriptionStatus($user->orgId);
                
                // Check if subscription has expired (end_date is in the past)
                // Important: Cancelled subscriptions are still active until end date passes
                if (isset($subscriptionStatus['end_date']) && $subscriptionStatus['end_date']) {
                    $endDate = Carbon::parse($subscriptionStatus['end_date'])->startOfDay();
                    $today = Carbon::today();
                    // Only expired if end date has passed
                    $isExpired = $today->greaterThan($endDate);
                } elseif (!($subscriptionStatus['active'] ?? false)) {
                    // Only mark as expired if there's no end date or it's truly inactive
                    // Check if we have end date in subscription object
                    if (isset($subscriptionStatus['subscription']) && $subscriptionStatus['subscription']->current_period_end) {
                        $endDate = Carbon::parse($subscriptionStatus['subscription']->current_period_end)->startOfDay();
                        $today = Carbon::today();
                        $isExpired = $today->greaterThan($endDate);
                    } else {
                        $isExpired = true;
                    }
                }
                
                // Only show payment popup if expired AND not suspended (suspended has different handling)
                if ($isExpired && $subscriptionStatus['status'] !== 'suspended') {
                    $needsPayment = true;
                    
                    // Calculate amount based on user count
                    $userCount = \App\Models\User::where('orgId', $user->orgId)
                        ->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'))
                        ->count();
                    $totalAmount = $userCount * 10.00;
                    
                    $paymentMessage = "Please complete your payment of £" . number_format($totalAmount, 2) . " to activate your account.";
                } else {
                    // Subscription is active - clear any expired flags
                    session()->forget('subscription_expired');
                    session()->forget('subscription_status');
                }
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
