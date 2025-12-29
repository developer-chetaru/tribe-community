<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AppRedirectController extends Controller
{
    /**
     * Redirect to app or dashboard based on device type
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirectToApp(Request $request)
    {
        $userAgent = $request->header('User-Agent', '');
        
        // Android app package
        $androidPackage = 'com.chetaru.tribe365_new';
        $androidPlayStoreUrl = 'https://play.google.com/store/apps/details?id=' . $androidPackage . '&hl=en_IN';
        
        // iOS app
        $iosAppStoreUrl = 'https://apps.apple.com/us/app/tribe365/id1435273330';
        
        // Dashboard URL (fallback for desktop)
        $dashboardUrl = 'https://community.tribe365.co/dashboard';
        
        // Detect Android
        if (preg_match('/android/i', $userAgent)) {
            // Deep link URL - direct app scheme
            $deepLinkUrl = "tribe365://dashboard";
            
            // Intent URL format for Android
            // Format: intent://[path]#Intent;scheme=[scheme];package=[package];S.browser_fallback_url=[fallback];end
            $intentUrl = "intent://dashboard#Intent;scheme=tribe365;package={$androidPackage};S.browser_fallback_url=" . urlencode($androidPlayStoreUrl) . ";end";
            
            // Alternative intent without fallback (for better app opening)
            $intentUrlDirect = "intent://dashboard#Intent;scheme=tribe365;package={$androidPackage};end";
            
            // Return HTML page that tries to open app, then redirects to Play Store if app not installed
            return response()->view('app-redirect', [
                'intentUrl' => $intentUrl,
                'intentUrlDirect' => $intentUrlDirect,
                'deepLinkUrl' => $deepLinkUrl,
                'fallbackUrl' => $androidPlayStoreUrl,
                'dashboardUrl' => $dashboardUrl,
                'platform' => 'android'
            ]);
        }
        
        // Detect iOS
        if (preg_match('/iphone|ipad|ipod/i', $userAgent)) {
            // Try custom URL scheme first, then App Store
            $customScheme = 'tribe365://dashboard';
            
            // Return HTML page that tries to open app, then redirects to App Store if app not installed
            return response()->view('app-redirect', [
                'customScheme' => $customScheme,
                'fallbackUrl' => $iosAppStoreUrl,
                'dashboardUrl' => $dashboardUrl,
                'platform' => 'ios'
            ]);
        }
        
        // Desktop or unknown device - redirect to dashboard
        return redirect($dashboardUrl);
    }
}

