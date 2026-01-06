<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-lg p-8">
            <h1 class="text-3xl font-bold mb-6">Terms of Service</h1>
            <div class="prose max-w-none">
                <p class="text-gray-600 mb-4">Last updated: {{ date('F d, Y') }}</p>
                
                <h2 class="text-2xl font-semibold mt-6 mb-4">1. Acceptance of Terms</h2>
                <p class="text-gray-700 mb-4">
                    By accessing and using {{ config('app.name') }}, you accept and agree to be bound by the terms and provision of this agreement.
                </p>

                <h2 class="text-2xl font-semibold mt-6 mb-4">2. Subscription Terms</h2>
                <p class="text-gray-700 mb-4">
                    Basecamp users agree to a monthly subscription fee of $10.00 USD. Subscriptions are billed monthly and will automatically renew unless cancelled.
                </p>

                <h2 class="text-2xl font-semibold mt-6 mb-4">3. Payment Terms</h2>
                <p class="text-gray-700 mb-4">
                    Payment is required before account activation. Failed payments will result in account suspension after a 7-day grace period.
                </p>

                <h2 class="text-2xl font-semibold mt-6 mb-4">4. Account Suspension</h2>
                <p class="text-gray-700 mb-4">
                    Accounts may be suspended for non-payment. Suspended accounts will be permanently deleted after 37 days if payment is not received.
                </p>

                <h2 class="text-2xl font-semibold mt-6 mb-4">5. Cancellation</h2>
                <p class="text-gray-700 mb-4">
                    You may cancel your subscription at any time. Cancellation will take effect at the end of your current billing period.
                </p>

                <div class="mt-8 pt-6 border-t border-gray-200">
                    <a href="{{ route('login') }}" class="text-blue-600 hover:underline">Back to Login</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

