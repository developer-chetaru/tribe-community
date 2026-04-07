<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use App\Services\OneSignalService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

class ForgotController extends Controller
{
  
    /**
    * Send a password reset link to the user's email.
    *
    * @param  \Illuminate\Http\Request $request
    * @return \Illuminate\Http\JsonResponse|\Illuminate\Validation\ValidationException
    */
   public function sendResetLink(Request $request)
    {
        // Trim email to remove leading/trailing spaces
        $email = trim($request->email);
        $request->merge(['email' => $email]);
        
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                "status" => false,
                "message" => "Email not found"
            ], 400);
        }

        // Generate token manually
        $token = Str::random(60);

        // Save token
        DB::table('password_resets')->updateOrInsert(
            ['email' => $request->email],
            [
                'email'      => $request->email,
                'token'      => $token,
                'created_at' => Carbon::now(),
            ]
        );

        // Build reset URL
        $resetUrl = url('/reset-password?token=' . $token . '&email=' . $request->email);

        // Render email HTML
        $htmlEmail = view('emails.custom-reset-password', [
            'user'         => $user,
            'orgName'      => $user->organisation->name ?? '',
            'resetUrl'     => $resetUrl,
            'userFullName' => $user->first_name . ' ' . $user->last_name,
            'inviterName'  => null,
        ])->render();

        // Prepare request to OneSignal
        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . env('ONESIGNAL_REST_API_KEY'),
            'Content-Type'  => 'application/json',
        ])->post(env('ONESIGNAL_API_URL'), [
            "app_id"               => env('ONESIGNAL_APP_ID'),
            "include_email_tokens" => [$user->email],
            "email_subject"        => "Reset your Tribe365 password",
            "email_body"           => $htmlEmail
        ]);

        // Log for debugging
        \Log::info("OneSignal Email Response:", $response->json());

        // If OneSignal failed
        if ($response->failed()) {
            return response()->json([
                "status"  => false,
                "message" => "Failed to send email",
                "error"   => $response->json()
            ], 500);
        }

        // SUCCESS RESPONSE
        return response()->json([
            "status"  => true,
            "message" => "Password reset email sent successfully",
        ], 200);
    }


	public function reset(Request $request)
    {
        // ----------------------------
        // VALIDATION
        // ----------------------------
        // Trim email to remove leading/trailing spaces
        if ($request->has('email')) {
            $request->merge(['email' => trim($request->email)]);
        }
        
        $request->validate([
            'token' => 'required|string',

            // Email MUST exist in password_resets AND users table
            'email' => [
                'required',
                'email',
                'exists:users,email',
                'exists:password_resets,email',
            ],

            'password' => [
                'required',
                'confirmed',
                Rules\Password::min(4)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        // ----------------------------
        // CHECK TOKEN & EMAIL MATCH
        // ----------------------------
        $record = DB::table('password_resets')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$record) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid or expired reset token.',
            ], 400);
        }

        // ----------------------------
        // OPTIONAL: TOKEN EXPIRY CHECK (60 MINUTES)
        // ----------------------------
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            return response()->json([
                'status' => false,
                'message' => 'Reset token has expired.',
            ], 400);
        }

        // ----------------------------
        // FIND USER
        // ----------------------------
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found.',
            ], 404);
        }

        // ----------------------------
        // RESET PASSWORD
        // ----------------------------
        $user->password = Hash::make($request->password);
        $user->save();

        // ----------------------------
        // DELETE TOKEN AFTER USE
        // ----------------------------
        DB::table('password_resets')
            ->where('email', $request->email)
            ->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Password successfully reset.',
        ], 200);
    }




	
    /**
    * Change the authenticated user's password.
    *
    * @param  \Illuminate\Http\Request $request
    * @return \Illuminate\Http\JsonResponse
    */
 	public function changePassword(Request $request)
    {
        // Validation
        $validator = \Validator::make($request->all(), [
            'current_password' => 'required',

            'new_password' => [
                'required',
                'string',
                'min:4',
                'regex:/[A-Z]/',             // uppercase
                'regex:/[a-z]/',             // lowercase
                'regex:/[0-9]/',             // number
                'regex:/[@$!%*#?&^._-]/',     // special char
                'confirmed'
            ],

            'new_password_confirmation' => 'required'
        ], [

            // Custom messages
            'current_password.required' => 'Please enter your current password.',

            'new_password.required' => 'Please enter a new password.',
            'new_password.min' => 'New password must be at least 8 characters long.',
            'new_password.regex' => 'New password must contain uppercase, lowercase, number and special character.',
            'new_password.confirmed' => 'New password and confirm password do not match.',

            'new_password_confirmation.required' => 'Please confirm your new password.',
        ]);

        // Return VALIDATION errors
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 400); // IMPORTANT: validation errors must return 422
        }

        // Get logged-in user
        $user = auth()->user(); // works for Laravel Auth, Sanctum, JWT

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not authenticated.'
            ], 400);
        }

        // Check current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Current password is incorrect'
            ], 400);
        }

        // Check if new password is same as current password
        if (Hash::check($request->new_password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'The new password must be different from your current password.'
            ], 400);
        }

    	$user->update([
        	'password' => Hash::make($request->new_password),
    	]);

    	return response()->json([
        	'status' => true,
        	'message' => 'Password changed successfully'
    	]);
	}
}
