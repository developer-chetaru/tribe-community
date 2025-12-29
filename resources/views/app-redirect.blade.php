<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opening Tribe365...</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            text-align: center;
            padding: 20px;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #EB1C24;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .message {
            color: #333;
            font-size: 16px;
            margin-bottom: 20px;
        }
        .link {
            color: #EB1C24;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="spinner"></div>
        <div class="message">Opening Tribe365 app...</div>
        <div class="message" style="font-size: 14px; color: #888;">
            If the app doesn't open, <a href="{{ $fallbackUrl }}" class="link">click here</a>
        </div>
    </div>

    <script>
        (function() {
            @if($platform === 'android')
                // Android: Multiple attempts to open app
                var deepLinkUrl = "{{ $deepLinkUrl ?? 'tribe365://dashboard' }}";
                var intentUrlDirect = "{{ $intentUrlDirect ?? $intentUrl }}";
                var intentUrl = "{{ $intentUrl }}";
                var fallbackUrl = "{{ $fallbackUrl }}";
                var dashboardUrl = "{{ $dashboardUrl }}";
                
                var appOpened = false;
                var startTime = Date.now();
                
                // Function to check if app opened
                function checkAppOpened() {
                    // If page lost focus or became hidden, app likely opened
                    if (!document.hasFocus() || document.visibilityState === 'hidden') {
                        appOpened = true;
                        return true;
                    }
                    return false;
                }
                
                // Method 1: Try direct deep link first (tribe365://dashboard)
                try {
                    window.location.href = deepLinkUrl;
                } catch(e) {
                    console.log('Deep link failed');
                }
                
                // Method 2: Try intent URL without fallback (after 500ms)
                setTimeout(function() {
                    if (!checkAppOpened() && !appOpened) {
                        try {
                            window.location.href = intentUrlDirect;
                        } catch(e) {
                            console.log('Intent direct failed');
                        }
                    }
                }, 500);
                
                // Method 3: Try intent URL with iframe (for browsers that block direct navigation)
                setTimeout(function() {
                    if (!appOpened && (document.hasFocus() || document.visibilityState === 'visible')) {
                        try {
                            var iframe = document.createElement('iframe');
                            iframe.style.display = 'none';
                            iframe.style.width = '1px';
                            iframe.style.height = '1px';
                            iframe.src = intentUrlDirect;
                            document.body.appendChild(iframe);
                            
                            // Remove iframe after attempt
                            setTimeout(function() {
                                if (iframe.parentNode) {
                                    iframe.parentNode.removeChild(iframe);
                                }
                            }, 1000);
                        } catch(e) {
                            console.log('Iframe method failed');
                        }
                    }
                }, 1000);
                
                // Fallback: If app doesn't open within 4 seconds, redirect to Play Store
                var checkInterval = setInterval(function() {
                    var elapsed = Date.now() - startTime;
                    
                    // Check if app opened
                    if (checkAppOpened()) {
                        appOpened = true;
                        clearInterval(checkInterval);
                        return;
                    }
                    
                    // If timeout reached and app didn't open
                    if (elapsed > 4000) {
                        clearInterval(checkInterval);
                        // Only redirect if we're sure app didn't open
                        if (document.hasFocus() && document.visibilityState === 'visible') {
                            window.location.href = fallbackUrl;
                        }
                    }
                }, 100);
            @elseif($platform === 'ios')
                // iOS: Try custom URL scheme first
                var customScheme = "{{ $customScheme }}";
                var fallbackUrl = "{{ $fallbackUrl }}";
                var dashboardUrl = "{{ $dashboardUrl }}";
                
                // Try to open app
                window.location.href = customScheme;
                
                // Fallback: If app doesn't open within 1.5 seconds, redirect to App Store
                var startTime = Date.now();
                var checkInterval = setInterval(function() {
                    var elapsed = Date.now() - startTime;
                    if (elapsed > 1500) {
                        clearInterval(checkInterval);
                        // Check if page is still visible (app didn't open)
                        if (document.hasFocus() || document.visibilityState === 'visible') {
                            window.location.href = fallbackUrl;
                        }
                    }
                }, 100);
            @else
                // Desktop fallback
                window.location.href = "{{ $dashboardUrl }}";
            @endif
        })();
    </script>
</body>
</html>

