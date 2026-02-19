<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tribe365</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="icon" type="image/png" href="{{ asset('images/favicon-icon.png') }}">
    
    <!-- Custom Styles (no build required) -->
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: "Figtree", sans-serif !important; }
    </style>

    @livewireStyles
</head>
<body class="bg-gray-100">
    <!-- Session Expiry Countdown - Hidden on guest/login pages (only useful for authenticated pages) -->
    {{-- Countdown removed from guest layout - it's not needed on login/register pages --}}
    
    <div class="">
        {{ $slot }}
    </div>
 
    @livewireScripts
    <script src="{{ asset('js/app.js') }}"></script>
    
    <!-- CSRF Token Refresh Script for Guest Layout -->
    <script>
        // Session lifetime in minutes (from config, default 4 for testing)
        const SESSION_LIFETIME_MINUTES = 4; // Change this to match your .env SESSION_LIFETIME
        const SESSION_LIFETIME_MS = SESSION_LIFETIME_MINUTES * 60 * 1000;
        
        // Refresh interval - refresh token before session expires (refresh at 80% of lifetime)
        const REFRESH_INTERVAL = Math.floor(SESSION_LIFETIME_MS * 0.8); // Refresh at 80% of session lifetime
        
        // Note: Countdown timer removed from guest layout - not needed on login/register pages
        // Session tracking is more relevant for authenticated pages
        
        // Refresh CSRF token periodically to prevent expiration
        setInterval(function() {
            console.log('🔄 Refreshing CSRF token...');
            fetch('/refresh-csrf-token', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.token) {
                    const metaTag = document.querySelector('meta[name="csrf-token"]');
                    if (metaTag) {
                        metaTag.setAttribute('content', data.token);
                        console.log('✅ CSRF token refreshed successfully');
                    }
                }
            })
            .catch(err => {
                console.log('❌ CSRF token refresh failed:', err);
            });
        }, REFRESH_INTERVAL);
        
        // Handle 419 errors - Auto refresh token and reload
        window.addEventListener('livewire:error', async (event) => {
            if (event.detail && event.detail.status === 419) {
                event.preventDefault();
                const response = await fetch('/refresh-csrf-token', {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin'
                });
                const data = await response.json();
                if (data.token) {
                    const metaTag = document.querySelector('meta[name="csrf-token"]');
                    if (metaTag) {
                        metaTag.setAttribute('content', data.token);
                    }
                    setTimeout(() => {
                        window.location.reload();
                    }, 100);
                } else {
                    window.location.reload();
                }
            }
        });
    </script>
</body>
</html>
