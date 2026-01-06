<!DOCTYPE html>
<html>
<head>
    <title>Redirecting to Stripe...</title>
    <meta http-equiv="refresh" content="0;url={{ $url }}">
    <script>
        // Immediate redirect - multiple methods for compatibility
        (function() {
            var url = {!! json_encode($url) !!};
            
            // Method 1: window.location.replace (preferred - doesn't add to history)
            if (window.location.replace) {
                window.location.replace(url);
            }
            // Method 2: window.location.href (fallback)
            else if (window.location.href) {
                window.location.href = url;
            }
            // Method 3: window.location (last resort)
            else {
                window.location = url;
            }
        })();
    </script>
</head>
<body>
    <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
        <h2>Redirecting to payment page...</h2>
        <p>If you are not redirected automatically, <a href="{{ $url }}" id="manual-link">click here</a>.</p>
        <script>
            // Fallback: manual link click after 1 second
            setTimeout(function() {
                document.getElementById('manual-link').click();
            }, 1000);
        </script>
    </div>
</body>
</html>

