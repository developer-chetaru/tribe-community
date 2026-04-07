<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payment Successful</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: #f5f5f5;
        }
        .container {
            text-align: center;
            padding: 20px;
        }
        .success-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .message {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
        }
    </style>
    <script>
        (function() {
            var appOpened = false;
            var userAgent = navigator.userAgent.toLowerCase();
            var isAndroid = userAgent.indexOf('android') > -1;
            var isIOS = /iphone|ipad|ipod/.test(userAgent);
            
            // Detect if app opened
            function checkAppOpened() {
                if (document.hidden || document.webkitHidden) {
                    appOpened = true;
                }
            }
            
            document.addEventListener('visibilitychange', checkAppOpened);
            document.addEventListener('webkitvisibilitychange', checkAppOpened);
            window.addEventListener('blur', function() {
                appOpened = true;
            });
            
            // Try to open app
            function openApp() {
                var sessionId = '{{ $sessionId ?? "" }}';
                var invoiceId = '{{ $invoiceId ?? "" }}';
                var userId = '{{ $userId ?? "" }}';
                var schemeUrl = 'tribe365://payment-success?session_id=' + sessionId + '&invoice_id=' + invoiceId + '&user_id=' + userId;
                
                if (isAndroid) {
                    // Try Intent URL first
                    var intentUrl = 'intent://payment-success#Intent;scheme=tribe365;package=com.chetaru.tribe365_new;S.session_id=' + sessionId + ';S.invoice_id=' + invoiceId + ';S.user_id=' + userId + ';end';
                    
                    // Try custom scheme
                    window.location.href = schemeUrl;
                    
                    // Fallback to Intent after delay
                    setTimeout(function() {
                        if (!appOpened) {
                            window.location.href = intentUrl;
                        }
                    }, 500);
                } else if (isIOS) {
                    // iOS - use custom scheme
                    window.location.href = schemeUrl;
                }
                
                // Fallback to web after 2 seconds if app didn't open
                setTimeout(function() {
                    if (!appOpened) {
                        window.location.href = '{{ url("/dashboard") }}';
                    }
                }, 2000);
            }
            
            // Wait a moment then try to open app
            setTimeout(openApp, 500);
        })();
    </script>
</head>
<body>
    <div class="container">
        <div class="success-icon">✅</div>
        <div class="message">Payment successful! Redirecting to app...</div>
    </div>
</body>
</html>
