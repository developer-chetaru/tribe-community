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
        .success-message {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            max-width: 400px;
            margin: 0 auto;
        }
        .success-message h2 {
            color: #16A34A;
            margin-bottom: 15px;
            font-size: 24px;
        }
        .success-message.warning h2 {
            color: #F59E0B;
        }
        .success-message p {
            color: #666;
            font-size: 16px;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #EB1C24;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        .countdown {
            color: #EB1C24;
            font-weight: 600;
            font-size: 18px;
            margin-top: 15px;
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
            var popupTimer;
            var startTime = Date.now();
            var popupShown = false;
            var triedToOpenApp = false;
            var messageShown = false;
            var countdown = 5;
            var messageType = "{{ $message ?? 'account_activated' }}";
            
            // Show success message first
            function showSuccessMessage() {
                if (messageShown) return;
                messageShown = true;
                var loadingContainer = document.querySelector('.loading-container');
                var messageText = messageType === 'already_activated' 
                    ? '<h2>⚠️ Account Already Activated</h2><p>This account is already active. Opening app...</p>'
                    : '<h2>✅ Account Activated Successfully!</h2><p>Your account has been activated. Opening app...</p>';
                
                loadingContainer.innerHTML = '<div class="success-message ' + (messageType === 'already_activated' ? 'warning' : '') + '">' + messageText + '<div class="spinner"></div><div class="countdown" id="countdown">Opening app in <span id="count">5</span> seconds...</div></div>';
            }
            
            // Update countdown
            function updateCountdown() {
                var countElement = document.getElementById('count');
                if (countElement && countdown >= 0) {
                    countElement.textContent = countdown;
                    if (countdown > 0) {
                        countdown--;
                        setTimeout(updateCountdown, 1000);
                    } else {
                        var countdownEl = document.getElementById('countdown');
                        if (countdownEl) {
                            countdownEl.textContent = 'Opening app...';
                        }
                    }
                }
            }
            
            // Detect if app opened (page becomes hidden/blurred)
            function checkAppOpened() {
                if (document.hidden || document.webkitHidden) {
                    appOpened = true;
                    clearTimeout(popupTimer);
                }
            }
            
            // Listen for page visibility changes
            document.addEventListener('visibilitychange', checkAppOpened);
            document.addEventListener('webkitvisibilitychange', checkAppOpened);
            window.addEventListener('blur', function() {
                appOpened = true;
                clearTimeout(popupTimer);
            });
            
            // Show popup with options
            function showPopup() {
                if (popupShown) return;
                popupShown = true;
                clearTimeout(popupTimer);
                var overlay = document.getElementById('popup-overlay');
                if (overlay) {
                    overlay.classList.add('show');
                }
            }
            
            // Redirect to web dashboard
            function continueOnWeb() {
                window.location.href = "{{ $webUrl }}";
            }
            
            // Redirect to app store
            function getApp() {
                window.location.href = "{{ $fallback }}";
            }
            
            // Check if app opened - show popup if not
            function checkAndShowPopup() {
                // Wait a bit more to ensure detection
                var elapsed = Date.now() - startTime;
                if (!appOpened && elapsed > 2000) {
                    showPopup();
                } else if (!appOpened) {
                    // Check again after a short delay
                    popupTimer = setTimeout(function() {
                        if (!appOpened) {
                            showPopup();
                        }
                    }, 500);
                }
            }
            
            // Show message when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    showSuccessMessage();
                    updateCountdown();
                });
            } else {
                showSuccessMessage();
                updateCountdown();
            }
            
            @if($platform === 'android')
                // For Android, try opening app using custom scheme (no automatic redirect to Play Store)
                // Wait 5 seconds before trying to open app (to show message)
                setTimeout(function() {
                    triedToOpenApp = true;
                    
                    var schemeUrl = "{{ $intentUrl }}"; // This is now tribe365://dashboard
                    
                    // Method 1: Use hidden iframe to try opening app (doesn't trigger page navigation)
                    var iframe = document.createElement('iframe');
                    iframe.style.display = 'none';
                    iframe.style.width = '1px';
                    iframe.style.height = '1px';
                    iframe.style.position = 'absolute';
                    iframe.style.left = '-9999px';
                    iframe.src = schemeUrl;
                    document.body.appendChild(iframe);
                    
                    // Method 2: Also try direct navigation after a small delay
                    setTimeout(function() {
                        if (!appOpened) {
                            try {
                                window.location.href = schemeUrl;
                            } catch(e) {
                                // Ignore errors
                            }
                        }
                    }, 300);
                    
                    // Check after 2.5 seconds if app didn't open - show popup
                    popupTimer = setTimeout(function() {
                        if (!appOpened) {
                            showPopup();
                        }
                    }, 2500);
                }, 5000); // Wait 5 seconds before trying to open app
                
                // Also check on page focus/blur (if user comes back, app didn't open)
                var wasHidden = false;
                document.addEventListener('visibilitychange', function() {
                    if (document.hidden) {
                        wasHidden = true;
                    } else if (wasHidden && !appOpened) {
                        // Page became visible again - app probably didn't open
                        setTimeout(function() {
                            if (!appOpened && !popupShown) {
                                showPopup();
                            }
                        }, 500);
                    }
                });
            @endif

            @if($platform === 'ios')
                // Wait 5 seconds before trying to open app (to show message)
                setTimeout(function() {
                    triedToOpenApp = true;
                    
                    // Try to open iOS app via custom scheme
                    var schemeUrl = "{{ $schemeUrl }}";
                    
                    // Use iframe method first
                    var iframe = document.createElement('iframe');
                    iframe.style.display = 'none';
                    iframe.style.width = '1px';
                    iframe.style.height = '1px';
                    iframe.src = schemeUrl;
                    document.body.appendChild(iframe);
                    
                    // Also try direct navigation
                    setTimeout(function() {
                        if (!appOpened) {
                            window.location.href = schemeUrl;
                        }
                    }, 100);
                    
                    // Check after 2.5 seconds if app didn't open
                    popupTimer = setTimeout(function() {
                        checkAndShowPopup();
                    }, 2500);
                    
                    // Additional check: if still visible after 2s, likely app didn't open
                    setTimeout(function() {
                        if (!appOpened && !document.hidden) {
                            checkAndShowPopup();
                        }
                    }, 2000);
                }, 5000); // Wait 5 seconds before trying to open app
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
        <p>Loading...</p>
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
