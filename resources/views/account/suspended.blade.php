@php
    $user = auth()->user();
    $isBasecamp = $user && $user->hasRole('basecamp');
    $subscription = null;
    $suspensionDetails = null;
    $isInactive = false;
    
    if ($isBasecamp) {
        $subscription = \App\Models\SubscriptionRecord::where('user_id', $user->id)
            ->where('tier', 'basecamp')
            ->whereIn('status', ['suspended', 'inactive'])
            ->orderBy('id', 'desc')
            ->first();
        if ($subscription && $subscription->status === 'inactive') {
            $isInactive = true;
        }
    } else {
        $subscriptionService = new \App\Services\SubscriptionService();
        $subscriptionStatus = $subscriptionService->getSubscriptionStatus($user->orgId ?? 0);
        if (isset($subscriptionStatus['status']) && in_array($subscriptionStatus['status'], ['suspended', 'inactive'])) {
            $suspensionDetails = $subscriptionStatus;
            if ($subscriptionStatus['status'] === 'inactive') {
                $isInactive = true;
            }
        }
    }
    
    // Get payment status from latest invoice
    $paymentStatus = 'Unknown';
    $latestInvoice = null;
    if ($subscription) {
        $latestInvoice = \App\Models\Invoice::where('subscription_id', $subscription->id)
            ->orWhere(function($q) use ($user, $isBasecamp) {
                if ($isBasecamp) {
                    $q->where('user_id', $user->id)->where('tier', 'basecamp');
                }
            })
            ->orderBy('created_at', 'desc')
            ->first();
        if ($latestInvoice) {
            $paymentStatus = $latestInvoice->status === 'paid' ? 'Paid' : 'Unpaid';
        }
    }
@endphp

<x-guest-layout>
    <div class="min-h-screen flex flex-col md:flex-row bg-[#FFF7F7]">
        {{-- Left side: Suspended message --}}
        <div class="flex flex-col justify-center items-center md:w-1/2 w-full bg-white px-4 py-8 h-full min-h-screen">
            <div class="w-full max-w-md text-center">
                <div class="flex justify-center mb-8">
                    <img src="{{ asset('images/logo-tribe.svg') }}" alt="Tribe365 Logo" class="h-10 w-auto">
                </div>

                {{-- Suspended Icon --}}
                <div class="mb-6 flex justify-center">
                    <div class="w-20 h-20 rounded-full bg-red-100 flex items-center justify-center">
                        <svg class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                </div>

                <h1 class="text-3xl font-bold mb-4 text-gray-900">
                    {{ $isInactive ? 'Account Inactive' : 'Account Suspended' }}
                </h1>

                <p class="text-gray-600 mb-2">
                    {{ $isInactive ? 'Your account subscription is currently inactive.' : 'Your account has been suspended due to payment issues.' }}
                </p>

                <p class="text-gray-600 mb-6">
                    {{ $isInactive ? 'To reactivate your account, please contact support.' : 'To reactivate your account, please update your payment method and settle any outstanding invoices.' }}
                </p>

                @if ($subscription || $suspensionDetails)
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 text-left">
                        <h3 class="font-semibold text-red-800 mb-2">{{ $isInactive ? 'Subscription Details:' : 'Suspension Details:' }}</h3>
                        <ul class="text-sm text-red-700 space-y-2">
                            @if ($subscription)
                                <li><strong>Status:</strong> <span class="uppercase">{{ $subscription->status }}</span></li>
                                @if ($subscription->current_period_end)
                                    <li><strong>Period End Date:</strong> {{ \Carbon\Carbon::parse($subscription->current_period_end)->format('F d, Y') }}</li>
                                @endif
                                @if ($subscription->next_billing_date)
                                    <li><strong>Next Billing Date:</strong> {{ \Carbon\Carbon::parse($subscription->next_billing_date)->format('F d, Y') }}</li>
                                @endif
                                @if ($subscription->suspended_at)
                                    <li><strong>{{ $isInactive ? 'Inactivated' : 'Suspended' }} on:</strong> {{ \Carbon\Carbon::parse($subscription->suspended_at)->format('F d, Y') }}</li>
                                @endif
                                @if ($latestInvoice)
                                    <li><strong>Payment Status:</strong> {{ $paymentStatus }}</li>
                                @endif
                            @elseif ($suspensionDetails)
                                <li><strong>Status:</strong> <span class="uppercase">{{ $suspensionDetails['status'] ?? 'N/A' }}</span></li>
                                @if (isset($suspensionDetails['end_date']))
                                    <li><strong>End Date:</strong> {{ \Carbon\Carbon::parse($suspensionDetails['end_date'])->format('F d, Y') }}</li>
                                @endif
                                @if (isset($suspensionDetails['subscription']) && $suspensionDetails['subscription']->current_period_end)
                                    <li><strong>Period End Date:</strong> {{ \Carbon\Carbon::parse($suspensionDetails['subscription']->current_period_end)->format('F d, Y') }}</li>
                                @endif
                                @if (isset($suspensionDetails['subscription']) && $suspensionDetails['subscription']->next_billing_date)
                                    <li><strong>Next Billing Date:</strong> {{ \Carbon\Carbon::parse($suspensionDetails['subscription']->next_billing_date)->format('F d, Y') }}</li>
                                @endif
                            @endif
                        </ul>
                    </div>
                @endif

                {{-- Action Buttons --}}
                <div class="space-y-3">
                    <!-- <a href="{{ $isBasecamp ? route('basecamp.billing') : route('billing') }}" 
                       class="w-full bg-red-500 hover:bg-red-600 text-white font-semibold py-3 rounded-full transition block text-center">
                        Update Payment Method
                    </a>
                     -->
                    <form method="POST" action="{{ route('logout') }}" class="inline-block w-full">
                        @csrf
                        <button type="submit" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-3 rounded-full transition">
                            Log Out
                        </button>
                    </form>
                </div>

                {{-- Help Text --}}
                <p class="text-xs text-gray-500 mt-6">
                    Need help? Contact our support team at 
                    <a href="mailto:support@tribe365.com" class="text-red-500 hover:underline">support@tribe365.com</a>
                </p>
            </div>
        </div>

        {{-- Right side: Info / Background --}}
        <div class="hidden md:flex md:w-1/2 w-full flex-col px-8 py-8 h-full min-h-screen bg-[url('/images/group.svg')] bg-no-repeat bg-cover bg-center">
            <div class="z-10 w-full pb-[390px] pt-12">
                <h3 class="text-2xl font-bold text-red-500 mb-6 mt-4">
                    Account Access Temporarily Restricted
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="font-semibold text-[#222] text-base">
                            Your subscription payment failed.
                        </p>
                    </div>
                    <div>
                        <p class="font-semibold text-[#222] text-base">
                            Update your payment method to restore access.
                        </p>
                    </div>
                    <div>
                        <p class="font-semibold text-[#222] text-base">
                            All your data is safe and secure.
                        </p>
                    </div>
                    <div>
                        <p class="font-semibold text-[#222] text-base">
                            Once payment is updated, your account will be reactivated immediately.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>


