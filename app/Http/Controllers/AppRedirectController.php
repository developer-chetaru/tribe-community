<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AppRedirectController extends Controller
{
    public function redirect(Request $request)
    {
        $ua = strtolower($request->header('User-Agent', ''));
        $androidPackage = 'com.chetaru.tribe365_new';
        $androidStore = "https://play.google.com/store/apps/details?id={$androidPackage}";
        $iosStore = "https://apps.apple.com/app/id1435273330";
        $webUrl = "https://community.tribe365.co/dashboard";

        // Get payment success parameters
        $sessionId = $request->query('session_id');
        $invoiceId = $request->query('invoice_id');
        $userId = $request->query('user_id');
        $paymentSuccess = $request->query('payment_success');
        $alreadyProcessed = $request->query('already_processed');
        $error = $request->query('error');

        // Build deep link URL with parameters
        $deepLinkPath = 'payment-success';
        if ($sessionId && $invoiceId && $userId) {
            $deepLinkPath = "payment-success?session_id={$sessionId}&invoice_id={$invoiceId}&user_id={$userId}";
        } elseif ($paymentSuccess === 'false' || $error) {
            $deepLinkPath = 'payment-cancel';
            if ($error) {
                $deepLinkPath .= '?error=' . urlencode($error);
            }
        }

        // Log for debugging
        \Log::info('AppRedirect accessed', [
            'user_agent' => $ua,
            'ip' => $request->ip(),
            'payment_success' => $paymentSuccess,
            'session_id' => $sessionId,
            'invoice_id' => $invoiceId,
        ]);

        // ANDROID - detect any android device
        if (str_contains($ua, 'android')) {
            \Log::info('AppRedirect: Detected Android device');
            
            // Intent URL format: intent://[path]#Intent;scheme=[scheme];package=[package];end
            $intentUrl = "intent://{$deepLinkPath}#Intent;scheme=tribe365;package={$androidPackage};end";
            
            return response()->view('app-redirect', [
                'platform' => 'android',
                'intentUrl' => $intentUrl,
                'fallback' => $androidStore,
            ]);
        }

        // IOS - detect iPhone, iPad, iPod
        if (
            str_contains($ua, 'iphone') ||
            str_contains($ua, 'ipad') ||
            str_contains($ua, 'ipod')
        ) {
            \Log::info('AppRedirect: Detected iOS device');
            
            return response()->view('app-redirect', [
                'platform' => 'ios',
                'schemeUrl' => "tribe365://{$deepLinkPath}",
                'fallback' => $iosStore,
            ]);
        }

        // DESKTOP or unknown device - redirect to web dashboard
        \Log::info('AppRedirect: Desktop or unknown device, redirecting to web');
        return redirect($webUrl);
    }
}
