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
     <script src="{{ asset('js/app.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/@material-tailwind/html@3.0.0-beta.7/dist/material-tailwind.umd.min.js"></script>
      <!-- intl-tel-input JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.21/js/intlTelInput.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.21/js/utils.js"></script>
   <!-- Include Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <!-- Auto-detect and update timezone -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            try {
                // Get browser timezone (may be blank/null)
                const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
                
                // Update timezone via existing APIs for dashboard/profile pages
                const currentPath = window.location.pathname;
                const isDashboardOrProfile = currentPath.includes('/dashboard') || 
                                              currentPath.includes('/user-profile') || 
                                              currentPath.includes('/user/profile');
                
                if (isDashboardOrProfile) {
                    // Wait for Livewire to be ready, then update timezone
                    if (window.Livewire) {
                        // Try immediately if Livewire is already loaded
                        setTimeout(() => {
                            Livewire.all().forEach(component => {
                                if (component.updateTimezone) {
                                    // Pass browser timezone or null to trigger IP detection
                                    component.updateTimezone(browserTimezone || null);
                                }
                            });
                        }, 500);
                        
                        // Also listen for Livewire load event
                        document.addEventListener('livewire:load', function() {
                            setTimeout(() => {
                                Livewire.all().forEach(component => {
                                    if (component.updateTimezone) {
                                        component.updateTimezone(browserTimezone || null);
                                    }
                                });
                            }, 100);
                        });
                    }
                    
                    // Also add timezone header to API calls
                    const originalFetch = window.fetch;
                    window.fetch = function(...args) {
                        const url = args[0];
                        const options = args[1] || {};
                        
                        // Add timezone header to user-profile and dashboard API calls
                        if (typeof url === 'string' && (
                            url.includes('/api/user-profile') || 
                            url.includes('/api/get-free-version-home-details') ||
                            url.includes('/user-profile')
                        )) {
                            options.headers = options.headers || {};
                            if (browserTimezone) {
                                options.headers['X-Timezone'] = browserTimezone;
                            }
                        }
                        
                        return originalFetch.apply(this, args);
                    };
                }
            } catch (error) {
                console.log('Error setting up timezone detection:', error);
            }
        });
    </script>
    
    @stack('scripts')
</body>
</html>
