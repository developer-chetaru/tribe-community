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

        // ANDROID
        if (str_contains($ua, 'android')) {
            // Intent URL format: intent://[path]#Intent;scheme=[scheme];package=[package];end
            $intentUrl = "intent://open#Intent;scheme=tribe365;package={$androidPackage};end";
            
            return response()->view('app-redirect', [
                'platform' => 'android',
                'intentUrl' => $intentUrl,
                'fallback' => $androidStore,
            ]);
        }

        // IOS
        if (
            str_contains($ua, 'iphone') ||
            str_contains($ua, 'ipad') ||
            str_contains($ua, 'ipod')
        ) {
            return response()->view('app-redirect', [
                'platform' => 'ios',
                'schemeUrl' => 'tribe365://open',
                'fallback' => $iosStore,
            ]);
        }

        // DESKTOP
        return redirect($webUrl);
    }
}

