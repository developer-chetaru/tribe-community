<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Redirecting...</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        (function() {
            var appOpened = false;
            var fallbackTimer;
            var startTime = Date.now();
            
            // Detect if app opened (page becomes hidden/blurred)
            function checkAppOpened() {
                if (document.hidden || document.webkitHidden) {
                    appOpened = true;
                    clearTimeout(fallbackTimer);
                }
            }
            
            // Listen for page visibility changes
            document.addEventListener('visibilitychange', checkAppOpened);
            document.addEventListener('webkitvisibilitychange', checkAppOpened);
            window.addEventListener('blur', function() {
                appOpened = true;
                clearTimeout(fallbackTimer);
            });
            
            // Fallback function - redirect to store if app didn't open
            function redirectToStore() {
                if (!appOpened && (Date.now() - startTime > 1000)) {
                    window.location.href = "{{ $fallback }}";
                }
            }
            
            @if($platform === 'android')
                // Try to open Android app via Intent
                var intentUrl = "{{ $intentUrl }}";
                window.location.href = intentUrl;
                
                // Fallback after 2 seconds if app didn't open
                fallbackTimer = setTimeout(redirectToStore, 2000);
            @endif

            @if($platform === 'ios')
                // Try to open iOS app via custom scheme
                var schemeUrl = "{{ $schemeUrl }}";
                window.location.href = schemeUrl;
                
                // Fallback after 2 seconds if app didn't open
                fallbackTimer = setTimeout(redirectToStore, 2000);
                
                // Additional check: if still visible after 1.5s, likely app didn't open
                setTimeout(function() {
                    if (!appOpened && !document.hidden) {
                        redirectToStore();
                    }
                }, 1500);
            @endif
        })();
    </script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #F5F5F5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        p {
            color: #333;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <p>Redirecting to app…</p>
</body>
</html>

