<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-lg p-8">
            <h1 class="text-3xl font-bold mb-6">Privacy Policy</h1>
            <div class="prose max-w-none">
                <p class="text-gray-600 mb-4">Last updated: {{ date('F d, Y') }}</p>
                
                <h2 class="text-2xl font-semibold mt-6 mb-4">1. Information We Collect</h2>
                <p class="text-gray-700 mb-4">
                    We collect information you provide directly to us, including name, email address, and payment information.
                </p>

                <h2 class="text-2xl font-semibold mt-6 mb-4">2. How We Use Your Information</h2>
                <p class="text-gray-700 mb-4">
                    We use your information to provide, maintain, and improve our services, process payments, and communicate with you.
                </p>

                <h2 class="text-2xl font-semibold mt-6 mb-4">3. Payment Information</h2>
                <p class="text-gray-700 mb-4">
                    Payment information is processed securely through Stripe. We do not store your full payment card details on our servers.
                </p>

                <h2 class="text-2xl font-semibold mt-6 mb-4">4. Data Security</h2>
                <p class="text-gray-700 mb-4">
                    We implement appropriate security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction.
                </p>

                <h2 class="text-2xl font-semibold mt-6 mb-4">5. Your Rights</h2>
                <p class="text-gray-700 mb-4">
                    You have the right to access, update, or delete your personal information at any time.
                </p>

                <div class="mt-8 pt-6 border-t border-gray-200">
                    <a href="{{ route('login') }}" class="text-blue-600 hover:underline">Back to Login</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

