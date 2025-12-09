<?php

namespace App\Livewire;


use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

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
            $this->addError('password', 'The provided password is incorrect.');
            \Log::warning('Login failed - incorrect password for: ' . $this->email);
            return;
        }

        // Check if account is activated
        if (!$user->status) {
            $this->addError('email', 'Your account is not activated yet, please check your email and follow the instruction to verify your account.');
            \Log::warning('Login failed - account not activated: ' . $this->email);
            return;
        }

        // Attempt login
        if (Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            \Log::info('Login successful for: ' . $this->email);
            session()->regenerate();
            return redirect()->intended('/dashboard');
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
