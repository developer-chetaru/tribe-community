<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Verifying Account...</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #F5F5F5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            position: relative;
        }
        .loading-container {
            text-align: center;
            color: #333;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #EB1C24;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .popup-overlay.show {
            display: flex;
        }
        .popup {
            background: white;
            border-radius: 16px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            text-align: center;
            animation: slideUp 0.3s ease-out;
        }
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .popup h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 22px;
        }
        .popup p {
            color: #666;
            margin-bottom: 25px;
            font-size: 15px;
            line-height: 1.5;
        }
        .popup-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .btn {
            padding: 14px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            display: block;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        .btn-primary {
            background: #EB1C24;
            color: white;
        }
        .btn-primary:hover {
            background: #C71313;
        }
        .btn-secondary {
            background: #F5F5F5;
            color: #333;
            border: 1px solid #E0E0E0;
        }
        .btn-secondary:hover {
            background: #E8E8E8;
        }
    </style>
    <script>
        (function() {
            var appOpened = false;
            var fallbackTimer;
            var startTime = Date.now();
            var popupShown = false;
            
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
            
            // Show popup with options
            function showPopup() {
                if (popupShown) return;
                popupShown = true;
                var overlay = document.getElementById('popup-overlay');
                overlay.classList.add('show');
            }
            
            // Redirect to web dashboard
            function continueOnWeb() {
                window.location.href = "{{ $webUrl }}";
            }
            
            // Redirect to app store
            function getApp() {
                window.location.href = "{{ $fallback }}";
            }
            
            // Fallback function - show popup if app didn't open
            function handleAppNotOpened() {
                if (!appOpened && (Date.now() - startTime > 1500)) {
                    showPopup();
                }
            }
            
            @if($platform === 'android')
                // Try to open Android app via Intent
                var intentUrl = "{{ $intentUrl }}";
                window.location.href = intentUrl;
                
                // Check after 2 seconds if app didn't open
                fallbackTimer = setTimeout(handleAppNotOpened, 2000);
            @endif

            @if($platform === 'ios')
                // Try to open iOS app via custom scheme
                var schemeUrl = "{{ $schemeUrl }}";
                window.location.href = schemeUrl;
                
                // Check after 2 seconds if app didn't open
                fallbackTimer = setTimeout(handleAppNotOpened, 2000);
                
                // Additional check: if still visible after 1.5s, likely app didn't open
                setTimeout(function() {
                    if (!appOpened && !document.hidden) {
                        handleAppNotOpened();
                    }
                }, 1500);
            @endif
            
            // If desktop, redirect directly to web
            @if($platform === 'desktop')
                window.location.href = "{{ $webUrl }}";
            @endif
        })();
    </script>
</head>
<body>
    <div class="loading-container">
        <div class="spinner"></div>
        <p>Opening app...</p>
    </div>
    
    <!-- Popup for when app is not installed -->
    <div id="popup-overlay" class="popup-overlay">
        <div class="popup">
            <h2>App Not Found</h2>
            <p>Tribe365 app is not installed on your device. Would you like to continue on web or download the app?</p>
            <div class="popup-buttons">
                <button class="btn btn-primary" onclick="continueOnWeb()">Continue on Web</button>
                <button class="btn btn-secondary" onclick="getApp()">Get App</button>
            </div>
        </div>
    </div>
    
    <script>
        // Make functions globally accessible
        function continueOnWeb() {
            window.location.href = "{{ $webUrl }}";
        }
        function getApp() {
            window.location.href = "{{ $fallback }}";
        }
    </script>
</body>
</html>
