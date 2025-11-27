<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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
        app(CreateNewUser::class)->create($request->all());
        return redirect()->route('login')->with('status', 'Check your email to verify your account. For using Tribe365, account need to be verified. Your account is not activated yet.');
    }
}
