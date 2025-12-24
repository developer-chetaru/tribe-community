<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon-icon.png') }}">
        <!-- Include Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.21/css/intlTelInput.css" />
    <!-- Styles -->
    @livewireStyles
<script src="https://cdn.tailwindcss.com/3.4.1"></script>
<link href="https://fonts.googleapis.com/css2?family=Figtree:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">

    <!-- Vite -->
    @vite(['resources/css/app.css'])

</head>
<body class="font-sans antialiased" x-data="{ sidebarOpen: true }">

    <x-banner />

    <div class="flex min-h-screen bg-gray-100">

        {{-- Sidebar --}}
          
         @include('livewire.sidebar-menu')
            

        {{-- Content --}}
         <div :class="sidebarOpen ? 'ml-0' : 'ml-2'" class="flex-1 transition-all duration-300">
               <div class="sticky top-0 z-50 bg-white shadow">
                    @include('navigation-menu', ['header' => $header ?? null])
               </div>



             <main class="p-6 flex-1 overflow-y-auto">
               {{ $slot }}
             </main>
        </div>
    </div>
	
    @stack('modals')
    @livewireScripts
     @if(file_exists(public_path('js/app.js')))
     <script src="{{ asset('js/app.js') }}"></script>
     @endif
     
     <!-- CSRF Token Refresh Script -->
     <script>
         // Refresh CSRF token every 30 minutes to prevent expiration
         setInterval(function() {
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
                     // Update meta tag
                     const metaTag = document.querySelector('meta[name="csrf-token"]');
                     if (metaTag) {
                         metaTag.setAttribute('content', data.token);
                     }
                     
                     // Update Livewire token if available
                     if (window.Livewire && window.Livewire.find) {
                         window.Livewire.all().forEach(component => {
                             if (component.__instance && component.__instance.csrf) {
                                 component.__instance.csrf = data.token;
                             }
                         });
                     }
                 }
             })
             .catch(err => {
                 console.log('CSRF token refresh failed:', err);
             });
         }, 30 * 60 * 1000); // 30 minutes
         
         // Handle Livewire 419 errors (Page Expired)
         document.addEventListener('livewire:error', (event) => {
             if (event.detail.status === 419) {
                 // Refresh the page to get a new CSRF token
                 window.location.reload();
             }
         });
         
         // Listen for Livewire network errors
         window.addEventListener('livewire:error', (event) => {
             if (event.detail.status === 419) {
                 alert('Your session has expired. The page will reload.');
                 window.location.reload();
             }
         });
     </script>
    <script src="https://cdn.jsdelivr.net/npm/@material-tailwind/html@3.0.0-beta.7/dist/material-tailwind.umd.min.js"></script>
      <!-- intl-tel-input JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.21/js/intlTelInput.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.21/js/utils.js"></script>
   <!-- Include Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <!-- COMMENTED OUT: Automatic timezone detection from geolocation -->
    <!-- Timezone should be set from user profile instead -->
    <!-- Get timezone from current location using browser geolocation -->
    <!-- <script>
        (function() {
            'use strict';
            
            const currentPath = window.location.pathname;
            const isDashboardOrProfile = currentPath.includes('/dashboard') || 
                                          currentPath.includes('/user-profile') || 
                                          currentPath.includes('/user/profile');
            
            if (!isDashboardOrProfile) {
                return;
            }
            
            // Check if geolocation is supported
            if (!navigator.geolocation) {
                console.log('Geolocation is not supported by this browser');
                return;
            }
            
            // Function to get timezone from location
            function getTimezoneFromLocation(latitude, longitude) {
                const formData = new FormData();
                formData.append('latitude', latitude);
                formData.append('longitude', longitude);
                
                fetch('/get-timezone-from-location', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status && data.timezone) {
                        console.log('✅ Timezone detected from location:', data.timezone);
                        
                        // Update Livewire components
                        if (window.Livewire) {
                            setTimeout(() => {
                                Livewire.all().forEach(component => {
                                    if (component.updateTimezoneFromLocation) {
                                        component.updateTimezoneFromLocation(data.timezone);
                                    }
                                });
                            }, 100);
                        }
                    } else {
                        console.log('❌ Failed to get timezone from location');
                    }
                })
                .catch(err => {
                    console.log('Error getting timezone from location:', err);
                });
            }
            
            // Function to show permission popup and request location
            function requestLocationAndGetTimezone() {
                // Show permission request (browser will show native popup)
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const latitude = position.coords.latitude;
                        const longitude = position.coords.longitude;
                        console.log('📍 Location obtained:', latitude, longitude);
                        getTimezoneFromLocation(latitude, longitude);
                    },
                    function(error) {
                        let errorMessage = 'Unable to get your location. ';
                        if (error.code === error.PERMISSION_DENIED) {
                            errorMessage += 'Please allow location access to automatically set your timezone.';
                            console.log('❌ User denied location permission');
                        } else if (error.code === error.POSITION_UNAVAILABLE) {
                            errorMessage += 'Location information is unavailable.';
                            console.log('❌ Location information unavailable');
                        } else if (error.code === error.TIMEOUT) {
                            errorMessage += 'Location request timed out.';
                            console.log('❌ Location request timeout');
                        } else {
                            errorMessage += 'An unknown error occurred.';
                            console.log('❌ Error getting location:', error.message);
                        }
                        
                        // Optional: Show user-friendly message
                        // You can uncomment this to show an alert
                        // alert(errorMessage);
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 15000,
                        maximumAge: 0
                    }
                );
            }
            
            // Try to get location on page load
            document.addEventListener('DOMContentLoaded', function() {
                // Always request location to get current timezone
                requestLocationAndGetTimezone();
            });
            
            // Also try when Livewire is ready
            if (window.Livewire) {
                document.addEventListener('livewire:load', function() {
                    setTimeout(() => {
                        requestLocationAndGetTimezone();
                    }, 500);
                });
            }
        })();
    </script> -->
    
    @stack('scripts')
</body>
</html>
