<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Invoice;
use App\Models\SubscriptionRecord;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;
use App\Services\OneSignalService;

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
        
        Log::info('User is basecamp - Sending verification email and redirecting to login');
        
        // Send verification email immediately for basecamp users
        $this->sendVerificationEmail($user);
        
        Log::info('Verification email sent to basecamp user');
        Log::info('=== REGISTRATION END ===');
        
        return redirect()->route('login')->with('status', 'Check your email to verify your account. After verification, you can login and complete payment.');
    }
    
    private function sendVerificationEmail($user)
    {
        try {
            $expires = Carbon::now()->addMinutes(1440);
            $verificationUrl = URL::temporarySignedRoute(
                'user.verify', $expires, ['id' => $user->id]
            );
            
            $emailBody = view('emails.verify-user-inline', [
                'user' => $user,
                'verificationUrl' => $verificationUrl,
            ])->render();
            
            $oneSignalService = new OneSignalService();
            $oneSignalService->registerEmailUserFallback($user->email, $user->id, [
                'subject' => 'Activate Your Tribe365 Account',
                'body' => $emailBody,
            ]);
            
            Log::info('Verification email sent to basecamp user', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send verification email: ' . $e->getMessage(), [
                'user_id' => $user->id,
            ]);
        }
    }
}
