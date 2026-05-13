<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class AdminLoginController extends Controller
{
    public function improvementChkPass(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = Auth::user();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid password',
            ], 401);
        }

        Session::put('improvementPassword', 'done');

        return response()->json([
            'status' => true,
            'message' => 'Password verified successfully',
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'currentPassword' => 'required|string',
            'newPassword' => 'required|string|min:6',
        ]);

        $user = Auth::user();

        if (!$user || !Hash::check($request->currentPassword, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Current password is incorrect',
            ], 401);
        }

        $user->password = Hash::make($request->newPassword);
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Password changed successfully',
        ]);
    }
}

