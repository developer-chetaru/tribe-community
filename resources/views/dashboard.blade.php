@if($needsPayment ?? false)
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon-icon.png') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.21/css/intlTelInput.css" />
    @livewireStyles
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold text-gray-900">Payment Required</h2>
        </div>

        <div class="mb-6">
            <p class="text-gray-600">{{ $paymentMessage ?? 'Please complete your payment to access all features.' }}</p>
        </div>

        <div class="mb-6">
            <p class="text-sm text-gray-600">Amount to Pay</p>
            @php
                $isBasecamp = $user && $user->hasRole('basecamp');
                $totalAmount = 12.00; // Default for basecamp
                $subtotal = 10.00;
                
                if (!$isBasecamp && $user && $user->orgId) {
                    $userCount = \App\Models\User::where('orgId', $user->orgId)
                        ->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'))
                        ->count();
                    $totalAmount = $userCount * 10.00;
                    $subtotal = $totalAmount;
                }
            @endphp
            <p class="text-4xl font-bold text-[#EB1C24] mb-2">£{{ number_format($totalAmount, 2) }}</p>
            @if($isBasecamp)
                <p class="text-sm text-gray-600 mt-1">(£{{ number_format($subtotal, 2) }} + 20% VAT)</p>
            @else
                @php
                    $userCount = $user && $user->orgId ? \App\Models\User::where('orgId', $user->orgId)
                        ->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'))
                        ->count() : 0;
                @endphp
                @if($userCount > 0)
                    <p class="text-sm text-gray-600 mt-1">{{ $userCount }} user(s) × £10.00</p>
                @endif
            @endif
        </div>

        <div class="flex gap-3">
            @if($isBasecamp)
                <button type="button" 
                        onclick="handleDashboardPayment({{ $user->id ?? 0 }})"
                        class="w-full bg-[#EB1C24] text-white font-semibold py-2 px-4 rounded-lg hover:bg-red-600 transition">
                    Pay Now
                </button>
            @else
                <button type="button" 
                        onclick="window.location.href='{{ route('billing') }}'"
                        class="w-full bg-[#EB1C24] text-white font-semibold py-3 px-4 rounded-lg hover:bg-red-600 transition text-lg">
                    Pay Now
                </button>
            @endif
        </div>
        
        <script>
            function handleDashboardPayment(userId) {
                if (!userId) {
                    alert('User ID is missing. Please refresh the page.');
                    return false;
                }
                
                console.log('Dashboard payment initiated', { userId });
                
                // Disable button to prevent multiple clicks
                const btn = event.target;
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Processing...';
                }
                
                // Use FormData instead of JSON for better compatibility
                const formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('user_id', userId);
                formData.append('tier', 'basecamp');
                formData.append('amount', 1200); // £12.00 with VAT (in cents)
                
                // First, get or create invoice
                const checkoutUrl = '{{ route("basecamp.checkout.create") }}';
                console.log('Making request to:', checkoutUrl);
                
                fetch(checkoutUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json', // Explicitly request JSON
                    },
                    redirect: 'manual', // Don't follow redirects automatically - handle manually
                    credentials: 'same-origin' // Include cookies for CSRF
                })
                .then(response => {
                    console.log('Response received', {
                        status: response.status,
                        statusText: response.statusText,
                        redirected: response.redirected,
                        url: response.url,
                        type: response.type,
                        ok: response.ok,
                        headers: Object.fromEntries(response.headers.entries())
                    });
                    
                    // Handle status 0 (network error, CORS issue, etc.)
                    if (response.status === 0) {
                        console.error('Status 0 - Network error or CORS issue');
                        throw new Error('Network error: Request may have been blocked. Check CORS settings or try again.');
                    }
                    
                    // Check for custom redirect header FIRST (before checking response.ok)
                    const redirectHeader = response.headers.get('X-Redirect-URL');
                    if (redirectHeader) {
                        console.log('Found redirect header:', redirectHeader);
                        window.location.replace(redirectHeader);
                        return;
                    }
                    
                    // If response is a redirect (status 301, 302, etc.), don't follow it
                    if (response.type === 'opaqueredirect' || response.redirected) {
                        console.warn('Response is a redirect, but we want JSON. Checking if it redirected to dashboard...');
                        if (response.url && response.url.includes('/dashboard')) {
                            throw new Error('Server redirected to dashboard instead of returning Stripe URL. Check server logs.');
                        }
                    }
                    
                    // IMPORTANT: Check response.ok AFTER checking headers
                    if (!response.ok && response.status !== 200) {
                        // Try to get error message - check if it's JSON first
                        const contentType = response.headers.get('content-type') || '';
                        if (contentType.includes('application/json')) {
                            return response.json().then(data => {
                                console.error('Response error (JSON):', data);
                                throw new Error(data.error || data.message || 'Response not OK: ' + response.status);
                            });
                        } else {
                            return response.text().then(text => {
                                console.error('Response error (text):', text);
                                throw new Error('Response not OK: ' + response.status + ' ' + response.statusText);
                            });
                        }
                    }
                    
                    // Try to get JSON response
                    const contentType = response.headers.get('content-type') || '';
                    console.log('Content-Type:', contentType);
                    
                    if (contentType.includes('application/json')) {
                        return response.json().then(data => {
                            console.log('Received JSON:', data);
                            if (data.redirect_url) {
                                console.log('Redirecting to Stripe:', data.redirect_url);
                                window.location.replace(data.redirect_url);
                            } else {
                                console.error('No redirect_url in JSON:', data);
                                throw new Error('No redirect URL in JSON response');
                            }
                        });
                    }
                    
                    // If HTML, return text
                    return response.text().then(html => {
                        console.log('Received HTML response, length:', html.length);
                        // Try to extract redirect URL from HTML if it's the redirect page
                        // Build regex pattern with literal @ symbol
                        const atSymbol = '@';
                        const jsonPattern = new RegExp('var url = ' + atSymbol + 'json\\([\'"]([^\'"]+)[\'"]\\)');
                        const urlMatch = html.match(jsonPattern);
                        if (urlMatch && urlMatch[1]) {
                            console.log('Extracted URL from HTML:', urlMatch[1]);
                            window.location.replace(urlMatch[1]);
                        } else {
                            // Try alternative patterns
                            const patterns = [
                                /var url = [^;]+['"](https?:\/\/[^'"]+)['"]/,
                                /window\.location\.replace\(['"](https?:\/\/[^'"]+)['"]\)/,
                                /window\.location\.href\s*=\s*['"](https?:\/\/[^'"]+)['"]/,
                            ];
                            
                            let found = false;
                            for (const pattern of patterns) {
                                const match = html.match(pattern);
                                if (match && match[1] && match[1].includes('checkout.stripe.com')) {
                                    console.log('Extracted Stripe URL from HTML:', match[1]);
                                    window.location.replace(match[1]);
                                    found = true;
                                    break;
                                }
                            }
                            
                            if (!found) {
                                // Replace page with HTML - it should redirect automatically
                                document.open();
                                document.write(html);
                                document.close();
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Payment error:', error);
                    alert('Failed to process payment: ' + error.message);
                    // Re-enable button on error
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = 'Pay Now';
                    }
                });
                
                return false; // Prevent any default behavior
            }
        </script>
        </div>
        @livewireScripts
    </body>
    </html>
@else
    <x-app-layout>
        <x-slot name="header">
            <h2 class="text-[14px] sm:text-[24px]  font-[600] text-[#EB1C24]">
               Dashboard
            </h2>
        </x-slot>
        <div>
            @hasanyrole('super_admin')
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {{-- Cards --}}
                </div>
            @endhasanyrole
            <div class="flex items-center w-full">
                @if(Auth::user()->organisation)
                    <div class="flex items-center p-0 mt-0">
                        @if(Auth::user()->organisation->image)
                            <img class="w-[100px] h-[100px] rounded-md bg-gray-100 flex items-center justify-center mb-3 px-2 overflow-hidden object-contain"
                                 src="{{ asset('storage/' . Auth::user()->organisation->image) }}"
                                 alt="{{ Auth::user()->organisation->name }}">
                        @else
                            <div class="h-14 w-14 flex items-center justify-center rounded-full bg-gray-200 text-gray-500 text-sm">
                                No Logo
                            </div>
                        @endif
                    </div>
                @endif
            </div>
            {{-- Buttons --}}
            <!-- <div class="flex gap-3">
                <button type="button" class="bg-[#f6f8fa] border text-black px-5 py-2 rounded-sm border-gray-200 font-medium ml-3 flex items-center">
                    <svg class="mr-2" width="20" height="21" viewBox="0 0 20 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                        {{-- SVG --}}
                    </svg>
                    Performance Data
                </button>
                <button type="button" class="bg-[#f6f8fa] ml-3 border border-gray-200 text-black px-5 py-2 rounded-sm  font-medium flex items-center">
                    <svg class="mr-2" width="21" height="21" viewBox="0 0 21 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                        {{-- SVG --}}
                    </svg>
                    Weekly Report
                </button>
                <button type="button" class="bg-[#f6f8fa] ml-3 border border-gray-200 text-black px-5 py-2 rounded-sm  font-medium flex items-center">
                    <svg class="mr-2" width="21" height="21" viewBox="0 0 21 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                        {{-- SVG --}}
                    </svg>
                    Graphical Data
                </button>
            </div> -->
        </div>
        @livewire('dashboard-summary')
        <div class="grid items-start grid-cols-1 md:grid-cols-2 gap-3 mt-3">
            @livewire('weekly-summary')
            @livewire('monthly-summary')
        </div>
    </div>
@endif
</x-app-layout>
