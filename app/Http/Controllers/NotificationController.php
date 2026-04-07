<?php

namespace App\Http\Controllers;

use App\Models\User; 
use App\Services\OneSignalService;

class NotificationController extends Controller
{
  
  	/**
 	* Send a test notification to all users with a valid fcmToken.
 	*
 	* @param \App\Services\OneSignalService $oneSignal
 	* @return \Illuminate\Http\JsonResponse
 	*/
    public function sendTest(OneSignalService $oneSignal)
    {
      $playerIds = User::whereNotNull('fcmToken')
    ->where('fcmToken', '!=', '')
    ->pluck('fcmToken')
    ->filter(function ($token) {
        return preg_match('/^[a-z0-9-]{8,}$/i', $token); // crude UUID/OneSignal check
    })
    ->unique()
    ->values()
    ->toArray();

        if (empty($playerIds)) {
            return response()->json([
                'message' => 'No users with fcmToken found.'
            ], 404);
        }

        $response = $oneSignal->sendNotification(
            'Sentiment Index',
            'Share your sentiment index today and help us build a better understanding of your mood journey',
            $playerIds
        );

        return response()->json($response);
    }
}
