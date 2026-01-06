<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Http;
use App\Mail\ActivationSuccessMail;
use App\Mail\VerifyUserMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Services\OneSignalService;

class VerificationController extends Controller
{
   /**
 	* Send verification email with a temporary signed URL.
 	*
 	* @param int $id
 	* @return \Illuminate\Http\JsonResponse
 	*/
    public function sendVerificationEmail($id)
    {
        $user = User::findOrFail($id);
	    $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHours(24),
            ['id' => $user->id]
        );

        Mail::to($user->email)->send(new VerifyUserMail($user, $verificationUrl));

        return response()->json(['message' => 'Verification email sent.']);
    }

  /**
 	* Verify user account using signed URL.
 	*
 	* @param \Illuminate\Http\Request $request
 	* @param int $id
 	* @return \Illuminate\Http\Response|string
 	*/
    public function verify(Request $request, $id)
    {
        // Check if request is from mobile app (expects JSON) or web (expects HTML)
        $isMobileApp = $request->wantsJson() || 
                      $request->header('Accept') === 'application/json' ||
                      str_contains(strtolower($request->header('User-Agent', '')), 'mobile') ||
                      str_contains(strtolower($request->header('User-Agent', '')), 'android') ||
                      str_contains(strtolower($request->header('User-Agent', '')), 'iphone') ||
                      str_contains(strtolower($request->header('User-Agent', '')), 'ipad');
        
        if (! $request->hasValidSignature()) {
            if ($isMobileApp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Link expired or invalid. Please request a new activation link.',
                ], 403);
            }
            return response("
                <html>
                <head><title>Link Expired</title></head>
                <body style='text-align:center; padding:50px; font-family:sans-serif;'>
                    <h2 style='color:red;'>:x: Link expired or invalid.</h2>
                    <p>Please request a new activation link.</p>
                </body>
                </html>
            ", 403);
        }
        $user = User::findOrFail($id);
        
        // Check if email is already verified
        if ($user->email_verified_at) {
            if ($isMobileApp) {
                return response()->json([
                    'success' => true,
                    'message' => 'Account is already activated.',
                    'already_verified' => true,
                ]);
            }
            // Already verified - show message and redirect
            return response("
                <html>
                <head>
                    <title>Already Activated</title>
                </head>
                <body style='text-align:center; padding:50px; font-family:sans-serif;'>
                    <h2 style='color:orange;'>Warning: This account is already active.</h2>
                    <p>You will be redirected in 5 seconds...</p>
                    <script>
                        setTimeout(function() {
                            window.location.href = '".url('/login')."';
                        }, 5000);
                    </script>
                </body>
                </html>
            ");
        }
        
        // Email not verified yet - verify now
        $user->email_verified_at = now();
        
        // Update status - activate user after email verification
        // Status is boolean: true = active, false = inactive
        if (!$user->status) {
            $user->status = true;
        }
        $user->save();
        
        Log::info('User email verified', [
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => $user->status,
        ]);
        
        // Return JSON response for mobile apps
        if ($isMobileApp) {
            return response()->json([
                'success' => true,
                'message' => 'Account activated successfully!',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'status' => $user->status,
                ],
            ]);
        }
        
        // Web response (HTML)
        // Check if this is a basecamp user who needs to set up billing
        $isBasecamp = $user->hasRole('basecamp');
        $redirectUrl = url('/login'); // Always redirect to login after verification
            // --------------------------------------
            // Create HTML email body from your template
            // --------------------------------------
            $htmlBody = '
            <!DOCTYPE html>
            <html>
            <head>
                <title>Account Activation</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        text-align: center;
                        padding: 80px;
                        background-color: #F9FAFB;
                    }
                    .message {
                        background: #FFFFFF;
                        display: inline-block;
                        padding: 40px 50px;
                        border-radius: 12px;
                        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
                        max-width: 500px;
                    }
                    h2 {
                        margin-bottom: 20px;
                    }
                    p {
                        font-size: 16px;
                        color: #555;
                    }
                    .success {
                        color: #16A34A;
                    }
                    .btn {
                        display: inline-block;
                        padding: 12px 24px;
                        font-size: 16px;
                        font-weight: bold;
                        border-radius: 8px;
                        text-decoration: none;
                        transition: all 0.3s ease;
                        margin-top: 20px;
                    }
                    .btn-login {
                        background-color: #DC2626;
                        color: #fff !important;
                    }
                    .btn-login:hover {
                        background-color: #B91C1C;
                    }
                </style>
            </head>
            <body>
                <div class="message">
                    <h2 class="success">Your account has been activated successfully!</h2>
                    <p>We\'re setting things up for you. Please proceed to login to complete your account setup.</p>
                    <a href="' . $redirectUrl . '" class="btn btn-login">Go to Login</a>
                </div>
                <script>
                    setTimeout(function() {
                        window.location.href = "' . $redirectUrl . '";
                    }, 5000);
                </script>
            </body>
            </html>';
            
            // Send activation confirmation email via OneSignal
            try {
                $oneSignalService = new OneSignalService();
                $oneSignalService->registerEmailUserFallback($user->email, $user->id, [
                    'subject' => 'Your Account Has Been Activated',
                    'body' => $htmlBody,
                ]);
                Log::info('Activation confirmation email sent via OneSignal', [
                    'email' => $user->email,
                    'user_id' => $user->id,
                ]);
            } catch (\Throwable $e) {
                Log::error('OneSignal activation email error', [
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
            // Return success page
            $redirectUrl = url('/login');
            return response('
                <html>
                <head>
                    <title>Account Activated</title>
                </head>
                <body style="text-align:center; padding:50px; font-family:sans-serif;">
                    <h2 style="color:green;">Your account has been activated successfully!</h2>
                    <p>You will be redirected in 5 seconds...</p>
                    <script>
                        setTimeout(function() {
                            window.location.href = "' . $redirectUrl . '";
                        }, 5000);
                    </script>
                </body>
                </html>
            ');
    }
}
