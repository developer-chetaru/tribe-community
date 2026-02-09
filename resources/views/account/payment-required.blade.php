<x-guest-layout>
    <div class="min-h-screen flex items-center justify-center bg-gray-100 p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-8">
            {{-- Logo --}}
            <div class="flex justify-center mb-6">
                <img src="{{ asset('images/logo-tribe.svg') }}" alt="Tribe365 Logo" class="h-10 w-auto">
            </div>

            {{-- Title --}}
            <div class="text-center mb-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Payment Required</h2>
                <p class="text-gray-600">
                    Please complete your payment of <strong>£{{ number_format($amount, 2) }}</strong> 
                    @if($isBasecamp ?? false)
                        (incl. VAT)
                    @endif
                    to activate your account.
                </p>
            </div>

            {{-- Amount Display --}}
            <div class="mb-6 text-center">
                <p class="text-sm text-gray-600 mb-2">Amount to Pay</p>
                <p class="text-4xl font-bold text-red-600">£{{ number_format($amount, 2) }}</p>
                @if($isBasecamp ?? false)
                    @php
                        $subtotal = round($amount / 1.2, 2);
                        $vat = $amount - $subtotal;
                    @endphp
                    <p class="text-sm text-gray-500 mt-1">(£{{ number_format($subtotal, 2) }} + 20% VAT)</p>
                @endif
            </div>

            {{-- Pay Now Button --}}
            <div class="mb-6">
                @php
                    $user = auth()->user();
                    $isBasecampUser = $isBasecamp ?? ($user && $user->hasRole('basecamp'));
                    
                    // Get unpaid invoice for this user
                    $unpaidInvoice = null;
                    if ($isBasecampUser) {
                        $unpaidInvoice = \App\Models\Invoice::where('user_id', $user->id)
                            ->where('tier', 'basecamp')
                            ->whereIn('status', ['unpaid', 'pending', 'failed'])
                            ->orderBy('created_at', 'desc')
                            ->first();
                    } else {
                        $unpaidInvoice = \App\Models\Invoice::where('organisation_id', $user->orgId)
                            ->whereIn('status', ['unpaid', 'pending', 'failed'])
                            ->orderBy('created_at', 'desc')
                            ->first();
                    }
                @endphp
                
                @if($isBasecampUser && $unpaidInvoice)
                    {{-- Direct Stripe checkout form for basecamp users --}}
                    <form id="paymentForm" action="{{ route('basecamp.checkout.create') }}" method="POST">
                        @csrf
                        <input type="hidden" name="invoice_id" value="{{ $unpaidInvoice->id }}">
                        <input type="hidden" name="user_id" value="{{ $user->id }}">
                        <button type="submit" 
                                class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-150 ease-in-out">
                            Pay Now
                        </button>
                    </form>
                @else
                    {{-- Fallback to billing page --}}
                    <a href="{{ $isBasecampUser ? route('basecamp.billing') : route('billing') }}" 
                       class="w-full block text-center bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-150 ease-in-out">
                        Pay Now
                    </a>
                @endif
            </div>

            {{-- Help Text --}}
            <div class="text-center text-sm text-gray-600 mb-4">
                <p>Need help? <a href="mailto:support@tribe365.com" class="text-red-600 hover:underline">Contact Support</a></p>
            </div>

            {{-- Logout Button --}}
            <div class="mt-4 pt-4 border-t border-gray-200">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" 
                            class="w-full bg-white hover:bg-gray-50 text-gray-700 font-semibold py-3 px-6 rounded-lg transition duration-150 ease-in-out border-2 border-gray-300 hover:border-gray-400">
                        <i class="fas fa-sign-out-alt mr-2"></i>Log Out
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-guest-layout>
