<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Organisation;
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
    	$user = Auth::user();
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
    	]);
	}
}
