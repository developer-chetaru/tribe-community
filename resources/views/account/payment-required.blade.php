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
                                id="payNowButton"
                                class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-150 ease-in-out">
                            <span id="payNowText">Pay Now</span>
                            <span id="payNowLoading" class="hidden">Loading...</span>
                        </button>
                    </form>
                    
                    <script>
                        document.getElementById('paymentForm').addEventListener('submit', function(e) {
                            e.preventDefault();
                            
                            const form = this;
                            const button = document.getElementById('payNowButton');
                            const buttonText = document.getElementById('payNowText');
                            const buttonLoading = document.getElementById('payNowLoading');
                            
                            // Disable button and show loading
                            button.disabled = true;
                            buttonText.classList.add('hidden');
                            buttonLoading.classList.remove('hidden');
                            
                            // Submit form via fetch
                            fetch(form.action, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                                },
                                body: new URLSearchParams(new FormData(form))
                            })
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Network response was not ok');
                                }
                                return response.json();
                            })
                            .then(data => {
                                if (data.success && data.redirect_url) {
                                    // Redirect to Stripe checkout
                                    window.location.href = data.redirect_url;
                                } else {
                                    throw new Error(data.error || 'Failed to create checkout session');
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                // Re-enable button
                                button.disabled = false;
                                buttonText.classList.remove('hidden');
                                buttonLoading.classList.add('hidden');
                                
                                // Show error message
                                alert('Failed to create payment session. Please try again.');
                            });
                        });
                    </script>
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
