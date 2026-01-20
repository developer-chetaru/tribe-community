<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Http;
use App\Mail\ActivationSuccessMail;
use App\Mail\VerifyUserMail;
use App\Models\User;
use App\Models\Invoice;
use App\Models\SubscriptionRecord;
use Illuminate\Support\Facades\DB;
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
        // Only check Accept header - mobile apps should send Accept: application/json
        // Web browsers (desktop and mobile) will get HTML response
        $isMobileApp = $request->wantsJson() || 
                      $request->header('Accept') === 'application/json';
        
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
            
            // Detect if user is on mobile browser
            $userAgent = strtolower($request->header('User-Agent', ''));
            $isMobileBrowser = str_contains($userAgent, 'mobile') || 
                              str_contains($userAgent, 'android') || 
                              str_contains($userAgent, 'iphone') || 
                              str_contains($userAgent, 'ipad');
            
            // For mobile devices, use verification redirect view with app detection and popup
            if ($isMobileBrowser) {
                $androidPackage = 'com.chetaru.tribe365_new';
                $androidStore = "https://play.google.com/store/apps/details?id={$androidPackage}";
                $iosStore = "https://apps.apple.com/app/id1435273330";
                $webUrl = url('/login');
                
                // Determine platform
                $platform = 'desktop';
                $intentUrl = null;
                $schemeUrl = null;
                $fallback = null;
                
                if (str_contains($userAgent, 'android')) {
                    $platform = 'android';
                    $intentUrl = "intent://dashboard#Intent;scheme=tribe365;package={$androidPackage};end";
                    $fallback = $androidStore;
                } elseif (str_contains($userAgent, 'iphone') || str_contains($userAgent, 'ipad') || str_contains($userAgent, 'ipod')) {
                    $platform = 'ios';
                    $schemeUrl = 'tribe365://dashboard';
                    $fallback = $iosStore;
                }
                
                return response()->view('verification-redirect', [
                    'platform' => $platform,
                    'intentUrl' => $intentUrl,
                    'schemeUrl' => $schemeUrl,
                    'fallback' => $fallback,
                    'webUrl' => $webUrl,
                ]);
            }
            
            $redirectUrl = url('/login');
            
            // Already verified - show message and redirect
            return response("
                <html>
                <head>
                    <title>Already Activated</title>
                </head>
                <body style='text-align:center; padding:50px; font-family:sans-serif;'>
                    <h2 style='color:orange;'>Warning: This account is already active.</h2>
                    <p>You will be redirected in 3 seconds...</p>
                    <script>
                        setTimeout(function() {
                            window.location.href = '{$redirectUrl}';
                        }, 3000);
                    </script>
                </body>
                </html>
            ");
        }
        
        // Email not verified yet - verify now
        $user->email_verified_at = now();
        
        // Update status - activate user after email verification
        // Status should be 'active_verified' after email verification
        // Handle both old boolean status and new string status for backward compatibility
        if (!in_array($user->status, ['active_verified', 'active_unverified'])) {
            $user->status = 'active_verified';
        } elseif ($user->status === true || $user->status === '1' || $user->status === 1) {
            // Convert old boolean/string status to new string status
            $user->status = 'active_verified';
        }
        $user->save();
        
        Log::info('User email verified', [
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => $user->status,
        ]);
        
        // Check if this is a basecamp user who needs automatic invoice generation
        $isBasecamp = $user->hasRole('basecamp');
        $invoiceGenerated = false;
        $invoiceError = null;
        
        // Generate invoice automatically for basecamp users after email verification
        // This is non-blocking - if it fails, verification still succeeds
        if ($isBasecamp) {
            try {
                $this->generateInvoiceForBasecampUser($user);
                $invoiceGenerated = true;
                Log::info('Invoice generated successfully for basecamp user after email verification', [
                    'user_id' => $user->id,
                ]);
            } catch (\Exception $e) {
                $invoiceError = $e->getMessage();
                Log::error('Failed to generate invoice for basecamp user after email verification', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Don't fail verification if invoice generation fails
            }
        }
        
        // Return JSON response for mobile apps
        // IMPORTANT: Do NOT return token - user must login separately after verification
        if ($isMobileApp) {
            return response()->json([
                'success' => true,
                'message' => 'Account activated successfully! Please login to continue.',
                'data' => [
                    'email' => $user->email,
                    'email_verified' => true,
                    'invoice_generated' => $invoiceGenerated,
                    'invoice_error' => $invoiceError,
                ],
                // NO TOKEN - user must login separately
            ]);
        }
        
        // Web response (HTML)
        // Check if this is a basecamp user who needs to set up billing
        $isBasecamp = $user->hasRole('basecamp');
        
        // Detect if user is on mobile browser (for app deep link redirect)
        $userAgent = strtolower($request->header('User-Agent', ''));
        $isMobileBrowser = str_contains($userAgent, 'mobile') || 
                          str_contains($userAgent, 'android') || 
                          str_contains($userAgent, 'iphone') || 
                          str_contains($userAgent, 'ipad');
        
        // For mobile devices, use verification redirect view with app detection and popup
        if ($isMobileBrowser) {
            $androidPackage = 'com.chetaru.tribe365_new';
            $androidStore = "https://play.google.com/store/apps/details?id={$androidPackage}";
            $iosStore = "https://apps.apple.com/app/id1435273330";
            $webUrl = url('/dashboard');
            
            // Determine platform
            $platform = 'desktop';
            $intentUrl = null;
            $schemeUrl = null;
            $fallback = null;
            
            if (str_contains($userAgent, 'android')) {
                $platform = 'android';
                $intentUrl = "intent://dashboard#Intent;scheme=tribe365;package={$androidPackage};end";
                $fallback = $androidStore;
            } elseif (str_contains($userAgent, 'iphone') || str_contains($userAgent, 'ipad') || str_contains($userAgent, 'ipod')) {
                $platform = 'ios';
                $schemeUrl = 'tribe365://dashboard';
                $fallback = $iosStore;
            }
            
            return response()->view('verification-redirect', [
                'platform' => $platform,
                'intentUrl' => $intentUrl,
                'schemeUrl' => $schemeUrl,
                'fallback' => $fallback,
                'webUrl' => $webUrl,
            ]);
        }
        
        // Desktop: redirect to login
        $redirectUrl = url('/login');
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
            // Return success page with redirect (already set above based on mobile/desktop)
            return response('
                <html>
                <head>
                    <title>Account Activated</title>
                </head>
                <body style="text-align:center; padding:50px; font-family:sans-serif;">
                    <h2 style="color:green;">Your account has been activated successfully!</h2>
                    <p>You will be redirected in 3 seconds...</p>
                    <script>
                        setTimeout(function() {
                            window.location.href = "' . $redirectUrl . '";
                        }, 3000);
                    </script>
                </body>
                </html>
            ');
    }
    
    /**
     * Generate invoice for basecamp user after email verification
     *
     * @param User $user
     * @return void
     */
    private function generateInvoiceForBasecampUser(User $user)
    {
        // Create or get subscription for basecamp user
        $subscription = SubscriptionRecord::firstOrCreate(
            [
                'user_id' => $user->id,
                'tier' => 'basecamp',
            ],
            [
                'organisation_id' => null,
                'status' => 'inactive',
                'user_count' => 1,
            ]
        );
        
        // Check if invoice already exists for today (to avoid duplicates)
        $existingInvoice = Invoice::where('user_id', $user->id)
            ->where('tier', 'basecamp')
            ->whereDate('invoice_date', now()->toDateString())
            ->where('status', '!=', 'cancelled')
            ->first();
            
        if ($existingInvoice) {
            Log::info('Invoice already exists for basecamp user after email verification', [
                'user_id' => $user->id,
                'invoice_id' => $existingInvoice->id,
                'invoice_number' => $existingInvoice->invoice_number,
            ]);
            return;
        }
        
        // Generate invoice
        $monthlyPrice = 10.00; // £10 per month for basecamp users
        $dueDate = now()->addDays(7);
        
        // Calculate VAT (20% of subtotal)
        $subtotal = $monthlyPrice; // £10.00
        $taxAmount = $subtotal * 0.20; // 20% VAT = £2.00
        $totalAmount = $subtotal + $taxAmount; // £12.00
        
        $invoice = DB::transaction(function () use ($user, $subscription, $monthlyPrice, $dueDate, $subtotal, $taxAmount, $totalAmount) {
            return Invoice::create([
                'user_id' => $user->id,
                'organisation_id' => null, // Null for basecamp users
                'subscription_id' => $subscription->id,
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'tier' => 'basecamp',
                'user_count' => 1,
                'price_per_user' => $monthlyPrice,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'status' => 'unpaid',
                'due_date' => $dueDate,
                'invoice_date' => now(),
            ]);
        });
        
        Log::info('Invoice generated automatically for basecamp user after email verification', [
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'total_amount' => $invoice->total_amount,
        ]);
    }
}
