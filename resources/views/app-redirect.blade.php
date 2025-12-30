<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting...</title>
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
    <script>
        (function() {
            const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
            
            if (!isMobile) {
                // Desktop → Website
                window.location.href = "https://community.tribe365.co";
            } else {
                // Mobile
                const deepLink = "https://community.tribe365.co/open";
                const androidStore = "https://play.google.com/store/apps/details?id=com.chetaru.tribe365_new";
                const iosStore = "https://apps.apple.com/app/id1435273330";
                
                // Try opening app
                window.location.href = deepLink;
                
                // Fallback to store (only if app doesn't open)
                setTimeout(() => {
                    if (/Android/i.test(navigator.userAgent)) {
                        window.location.href = androidStore;
                    } else {
                        window.location.href = iosStore;
                    }
                }, 2000);
            }
        })();
    </script>
</head>
<body>
    <div class="container">
        <div class="spinner"></div>
        <div class="message">Redirecting...</div>
    </div>
</body>
</html>

