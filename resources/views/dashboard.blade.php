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
            <h3 class="text-xl font-semibold">Payment Required</h3>
        </div>

        <div class="mb-4">
            <p class="text-gray-700">{{ $paymentMessage ?? 'Please complete your payment to access all features.' }}</p>
        </div>

        <div class="mb-4">
            <p class="text-sm text-gray-600">Amount to Pay</p>
            <p class="text-2xl font-bold text-[#EB1C24]">$10.00</p>
        </div>

            <div class="flex gap-3">
            <button type="button" 
                    onclick="handleDashboardPayment({{ $user->id ?? 0 }})"
                    class="w-full bg-[#EB1C24] text-white font-semibold py-2 px-4 rounded-lg hover:bg-red-600 transition">
                Pay Now
            </button>
        </div>
        
        <script>
            function handleDashboardPayment(userId) {
                if (!userId) {
                    alert('User ID is missing. Please refresh the page.');
                    return;
                }
                
                console.log('Dashboard payment initiated', { userId });
                
                // First, get or create invoice
                fetch('{{ route("basecamp.checkout.create") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        user_id: userId,
                        tier: 'basecamp',
                        amount: 1000
                    })
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    if (response.redirected) {
                        window.location.href = response.url;
                        return;
                    }
                    return response.text();
                })
                .then(html => {
                    if (html) {
                        // If HTML is returned (redirect page), replace current page
                        document.open();
                        document.write(html);
                        document.close();
                    }
                })
                .catch(error => {
                    console.error('Payment error:', error);
                    alert('Failed to process payment. Please try again.');
                });
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
            <div class="flex gap-3">
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
            </div>
        </div>
        @livewire('dashboard-summary')
        @livewire('weekly-summary')
        @livewire('monthly-summary')
    </div>
@endif
</x-app-layout>
