<?php

namespace App\Livewire;


use Livewire\Component;
use Illuminate\Support\Facades\Auth;

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

    if (Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
        \Log::info('Login successful for: ' . $this->email);
        session()->regenerate();
        return redirect()->intended('/dashboard');
    }

    \Log::warning('Login failed for: ' . $this->email);
    $this->addError('email', 'These credentials do not match our records.');
}


  public function render()
{
    return view('livewire.login-form')->layout('layouts.guest');
}

}
