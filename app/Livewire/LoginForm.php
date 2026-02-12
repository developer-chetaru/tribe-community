<?php

namespace App\Livewire;


use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Invoice;
use App\Models\SubscriptionRecord;
use App\Services\SessionManagementService;
use Illuminate\Support\Facades\Log;

class LoginForm extends Component
{
    public $email = '';
    public $password = '';
    public $remember = false;

    protected $rules = [
        'email' => 'required|email',
        'password' => 'required|min:6',
    ];
    
    public function login()
    {
        // Trim email before validation
        $this->email = trim($this->email);
        
        $this->validate();

        // Check if email exists
        $user = User::where('email', $this->email)->first();
        
        if (!$user) {
            $this->addError('email', 'The provided email address is not registered.');
            \Log::warning('Login failed - email not found: ' . $this->email);
            return;
        }

        // Check if password is correct
        if (!Hash::check($this->password, $user->password)) {
            $this->addError('password', 'The Entered password is incorrect.');
            \Log::warning('Login failed - incorrect password for: ' . $this->email);
            return;
        }

        // For basecamp users, check if payment has been completed
        if ($user->hasRole('basecamp')) {
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
            
            if (!$hasActiveSubscription && !$hasPaidInvoice) {
                // User needs to complete payment first
                $this->addError('email', 'Please complete your payment first. You will be redirected to the payment page after login.');
                \Log::warning('Login failed - basecamp user has not paid: ' . $this->email);
                
                // Auto-login temporarily to redirect to billing page
                Auth::login($user);
                session()->regenerate();
                
                return redirect()->route('basecamp.billing')->with('error', 'Please complete your payment of £12.00 to continue.');
            }
        }
        
        // Check if account is activated (for all users including basecamp after payment)
        // Check if user status is not active
        if (!in_array($user->status, ['active_verified', 'active_unverified'])) {
            $this->addError('email', 'Your account is not activated yet, please check your email and follow the instruction to verify your account.');
            \Log::warning('Login failed - account not activated: ' . $this->email);
            return;
        }

        // Attempt login
        if (Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            \Log::info('Login successful for: ' . $this->email);
            
            // ✅ Invalidate ALL previous sessions BEFORE regenerating
            // This ensures that even if user has multiple tabs open, all will be logged out
            try {
                $sessionService = new SessionManagementService();
                // Delete all sessions for this user
                $sessionService->invalidateAllSessions($user);
                Log::info("All sessions invalidated for user {$user->id} before new login");
            } catch (\Exception $e) {
                Log::warning('Failed to invalidate previous sessions on web login', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
            
            // Regenerate session to create new session ID
            session()->regenerate();
            
            // ✅ Immediately store the new session ID to avoid timing issues
            // This ensures ValidateWebSession middleware can find it right away
            try {
                $sessionService = new SessionManagementService();
                $newSessionId = session()->getId();
                $sessionService->storeActiveSession($user, $newSessionId);
                Log::info("New session stored immediately after regeneration for user {$user->id}", [
                    'session_id' => $newSessionId,
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to store session immediately after regeneration', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                // Don't block login if session storage fails - just log it
            }
            
            // Use Livewire's redirect method for proper redirect handling
            return $this->redirect('/dashboard');
        }

        // Fallback error
        \Log::warning('Login failed for: ' . $this->email);
        $this->addError('email', 'These credentials do not match our records.');
    }


  public function render()
{
    return view('livewire.login-form')->layout('layouts.guest');
}

}
