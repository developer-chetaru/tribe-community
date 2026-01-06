<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Suspended - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div class="bg-white rounded-lg shadow-lg p-8">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                        <svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Account Suspended</h2>
                    <p class="text-gray-600 mb-6">
                        Your account has been suspended due to payment failure. Please complete payment to reactivate your account.
                    </p>
                </div>

                @if($invoice)
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <h3 class="text-sm font-semibold text-yellow-800 mb-2">Outstanding Invoice</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-yellow-700">Invoice #:</span>
                            <span class="font-semibold text-yellow-900">{{ $invoice->invoice_number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-yellow-700">Amount Due:</span>
                            <span class="font-semibold text-yellow-900">${{ number_format($invoice->total_amount, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-yellow-700">Due Date:</span>
                            <span class="font-semibold text-yellow-900">{{ $invoice->due_date->format('M d, Y') }}</span>
                        </div>
                    </div>
                </div>
                @endif

                @if($user->suspension_date)
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <p class="text-sm text-gray-600">
                        <strong>Suspended since:</strong> {{ $user->suspension_date->format('M d, Y') }}
                    </p>
                    <p class="text-xs text-gray-500 mt-2">
                        Your account will be permanently deleted after 37 days of suspension if payment is not received.
                    </p>
                </div>
                @endif

                <form action="{{ route('account.reactivate') }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-[#EB1C24] hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#EB1C24] transition">
                        Reactivate Account - Pay Now
                    </button>
                </form>

                <div class="mt-4 text-center">
                    <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-gray-900">
                        Back to Login
                    </a>
                </div>
            </div>

            <div class="text-center">
                <p class="text-xs text-gray-500">
                    Need help? Contact support at <a href="mailto:support@tribe365.com" class="text-[#EB1C24] hover:underline">support@tribe365.com</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>

