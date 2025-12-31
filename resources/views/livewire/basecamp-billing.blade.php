<div>
    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <!-- Subscription Info -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">Basecamp Subscription</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-sm text-gray-600">Monthly Price</p>
                        <p class="text-2xl font-bold text-[#EB1C24]">${{ number_format($monthlyPrice, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Status</p>
                        <p class="text-lg font-semibold {{ $isActive ? 'text-green-600' : 'text-red-600' }}">
                            {{ $isActive ? 'Active' : 'Inactive' }}
                        </p>
                    </div>
                    @if($isActive && $subscription)
                        <div>
                            <p class="text-sm text-gray-600">Next Billing Date</p>
                            <p class="text-lg font-semibold">
                                {{ $subscription->next_billing_date ? $subscription->next_billing_date->format('M d, Y') : 'N/A' }}
                            </p>
                        </div>
                    @endif
                </div>

                @if(!$isActive)
                    <div class="mt-6">
                        <button 
                            wire:click="createInvoice"
                            class="bg-[#EB1C24] text-white font-semibold py-2 px-6 rounded-lg hover:bg-red-600 transition">
                            Subscribe Now - ${{ number_format($monthlyPrice, 2) }}/month
                        </button>
                    </div>
                @endif
            </div>

            <!-- Invoices -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Invoices</h3>
                @if($invoices->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($invoices as $invoice)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ $invoice->invoice_number }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $invoice->invoice_date ? $invoice->invoice_date->format('M d, Y') : 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            ${{ number_format($invoice->total_amount, 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ ucfirst($invoice->status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            @if($invoice->status === 'unpaid')
                                                <button 
                                                    wire:click="$set('selectedInvoice', {{ $invoice->id }})"
                                                    wire:click="openPaymentModal"
                                                    class="text-[#EB1C24] hover:text-red-600 font-semibold">
                                                    Pay Now
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-gray-500">No invoices yet.</p>
                @endif
            </div>

            <!-- Payment Modal -->
            @if($showPaymentPage && $selectedInvoice)
                <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" wire:click.self="closePaymentPage">
                    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-xl font-semibold">Complete Payment</h3>
                            <button wire:click="closePaymentPage" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <div class="mb-4">
                            <p class="text-sm text-gray-600">Amount to Pay</p>
                            <p class="text-2xl font-bold text-[#EB1C24]">${{ number_format($selectedInvoice->total_amount, 2) }}</p>
                        </div>

                        @if($stripeClientSecret)
                            <div id="stripe-payment-element" class="mb-4" style="min-height: 60px;"></div>
                            <button 
                                id="submit-payment"
                                type="button"
                                wire:loading.attr="disabled"
                                wire:target="confirmStripePayment"
                                class="w-full bg-[#EB1C24] text-white font-semibold py-2 px-4 rounded-lg hover:bg-red-600 transition disabled:opacity-50">
                                <span wire:loading.remove wire:target="confirmStripePayment">Pay ${{ number_format($selectedInvoice->total_amount, 2) }}</span>
                                <span wire:loading wire:target="confirmStripePayment">Processing...</span>
                            </button>
                        @else
                            <div class="text-center py-4">
                                <p class="text-gray-500">Initializing payment...</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
    
    <div wire:ignore>
        <script src="https://js.stripe.com/v3/"></script>
        <script>
            let stripe, elements, paymentElement;
            let paymentButtonHandler = null;
            
            document.addEventListener('livewire:init', () => {
                Livewire.on('stripe-payment-intent-created', (event) => {
                    console.log('Stripe payment intent created event received', event);
                    const clientSecret = event[0].clientSecret;
                    
                    if (!stripe) {
                        const stripeKey = '{{ config("services.stripe.public") }}';
                        if (!stripeKey || stripeKey === '') {
                            console.error('Stripe publishable key is not configured');
                            alert('Payment system is not configured. Please contact support.');
                            return;
                        }
                        stripe = Stripe(stripeKey);
                        console.log('Stripe initialized with key:', stripeKey.substring(0, 10) + '...');
                    }
                    
                    // Remove old payment element if exists
                    const existingElement = document.getElementById('stripe-payment-element');
                    if (existingElement && paymentElement) {
                        paymentElement.unmount();
                    }
                    
                    // Create new elements instance
                    elements = stripe.elements({ clientSecret });
                    paymentElement = elements.create('payment');
                    paymentElement.mount('#stripe-payment-element');
                    
                    console.log('Stripe payment element mounted');
                    
                    // Remove old event listener if exists
                    const submitBtn = document.getElementById('submit-payment');
                    if (submitBtn && paymentButtonHandler) {
                        submitBtn.removeEventListener('click', paymentButtonHandler);
                    }
                    
                    // Add new event listener
                    if (submitBtn) {
                        paymentButtonHandler = async (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            console.log('Payment button clicked');
                            
                            // Disable button
                            submitBtn.disabled = true;
                            submitBtn.textContent = 'Processing...';
                            
                            try {
                                const { error, paymentIntent } = await stripe.confirmPayment({
                                    elements,
                                    confirmParams: {
                                        return_url: window.location.href,
                                    },
                                    redirect: 'if_required'
                                });
                                
                                if (error) {
                                    console.error('Payment error:', error);
                                    alert('Payment failed: ' + error.message);
                                    submitBtn.disabled = false;
                                    submitBtn.innerHTML = '<span>Pay ${{ number_format($selectedInvoice->total_amount ?? 10, 2) }}</span>';
                                } else if (paymentIntent && paymentIntent.status === 'succeeded') {
                                    console.log('Payment succeeded:', paymentIntent.id);
                                    @this.confirmStripePayment(paymentIntent.id);
                                } else {
                                    console.log('Payment status:', paymentIntent?.status);
                                    submitBtn.disabled = false;
                                    submitBtn.innerHTML = '<span>Pay ${{ number_format($selectedInvoice->total_amount ?? 10, 2) }}</span>';
                                }
                            } catch (err) {
                                console.error('Payment confirmation error:', err);
                                alert('Payment error: ' + err.message);
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = '<span>Pay ${{ number_format($selectedInvoice->total_amount ?? 10, 2) }}</span>';
                            }
                        };
                        
                        submitBtn.addEventListener('click', paymentButtonHandler);
                        console.log('Payment button event listener attached');
                    } else {
                        console.error('Submit payment button not found');
                    }
                });
            });
        </script>
    </div>
</div>
