<x-slot name="header">
    <h2 class="text-[24px] md:text-[30px] font-semibold capitalize text-[#EB1C24]">
        Billing & Invoices
    </h2>
</x-slot>

<div>
    <div class="flex-1 overflow-auto">
        <div class="max-w-8xl mx-auto p-4">
            @if($subscription)
                <!-- Subscription Info -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Current Subscription</h3>
                        @if($subscriptionStatus['active'] && $daysRemaining > 0)
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-600">Days Remaining:</span>
                                <span class="text-2xl font-bold text-green-600" 
                                      x-data="{ days: {{ $daysRemaining }} }"
                                      x-text="days"
                                      x-init="setInterval(() => { 
                                          const endDate = new Date('{{ $subscriptionStatus['end_date'] ?? ($subscription->current_period_end ? $subscription->current_period_end->format('Y-m-d') : '') }}');
                                          const now = new Date();
                                          const diff = Math.ceil((endDate - now) / (1000 * 60 * 60 * 24));
                                          days = Math.max(0, diff);
                                      }, 1000)">
                                    {{ $daysRemaining }}
                                </span>
                            </div>
                        @endif
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">User Count</p>
                            <p class="text-lg font-semibold">{{ $subscription->user_count }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Tier</p>
                            <p class="text-lg font-semibold capitalize">{{ $subscription->tier ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Price per User</p>
                            <p class="text-lg font-semibold">£{{ number_format(($subscription->tier === 'spark' ? 10 : ($subscription->tier === 'momentum' ? 20 : 30)), 2) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Monthly Total</p>
                            <p class="text-lg font-semibold">£{{ number_format(($subscription->tier === 'spark' ? 10 : ($subscription->tier === 'momentum' ? 20 : 30)) * $subscription->user_count, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Subscription End</p>
                            <p class="text-lg font-semibold {{ $daysRemaining <= 7 ? 'text-red-600' : '' }}">
                                {{ $subscription->current_period_end ? $subscription->current_period_end->format('M d, Y') : 'N/A' }}
                            </p>
                        </div>
                    </div>
                    @if(isset($subscriptionStatus['status']) && $subscriptionStatus['status'] === 'suspended')
                        <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <p class="text-yellow-800 text-sm font-medium mb-2">
                                ⚠️ Your subscription is currently paused. Please contact your administrator or renew to activate it.
                            </p>
                            <button wire:click="openRenewModal" type="button" class="mt-2 px-4 py-2 bg-[#EB1C24] text-white rounded-md hover:bg-red-700">
                                Renew Subscription
                            </button>
                        </div>
                    @elseif(!$subscriptionStatus['active'])
                        <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                            <p class="text-red-800 text-sm font-medium mb-2">
                                ⚠️ Your subscription has expired or is inactive. Please renew to continue using the service.
                            </p>
                            <button wire:click="openRenewModal" type="button" class="mt-2 px-4 py-2 bg-[#EB1C24] text-white rounded-md hover:bg-red-700">
                                Renew Subscription
                            </button>
                        </div>
                    @elseif($daysRemaining <= 7)
                        <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <p class="text-yellow-800 text-sm font-medium mb-2">
                                ⚠️ Your subscription is expiring soon ({{ $daysRemaining }} days remaining).
                            </p>
                            <button wire:click="openRenewModal" type="button" class="mt-2 px-4 py-2 bg-[#EB1C24] text-white rounded-md hover:bg-red-700">
                                Renew Now
                            </button>
                        </div>
                    @endif
                </div>
            @else
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <p class="text-yellow-800">No active subscription found for your organisation.</p>
                </div>
            @endif

            <!-- Invoices -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="p-4 border-b">
                    <h3 class="text-lg font-semibold">Invoices</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($invoices as $invoice)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $invoice->invoice_number }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $invoice->invoice_date->format('M d, Y') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $invoice->due_date->format('M d, Y') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">${{ number_format($invoice->total_amount, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs rounded-full 
                                        {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-800' : 
                                           ($invoice->status === 'overdue' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                        {{ ucfirst($invoice->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-3">
                                        <button wire:click="openInvoiceModal({{ $invoice->id }})" 
                                                type="button"
                                                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded border border-blue-200 transition-all duration-200">
                                            View Invoice
                                        </button>
                                        <a href="{{ route('invoices.download', $invoice->id) }}" 
                                           download 
                                           class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-green-600 hover:text-green-800 hover:bg-green-50 rounded border border-green-200 transition-all duration-200">
                                            Download
                                        </a>
                                        
                                        <!-- Share Invoice Button -->
                                        <button wire:click="openShareModal({{ $invoice->id }})" 
                                                type="button"
                                                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-purple-600 hover:text-purple-800 hover:bg-purple-50 rounded border border-purple-200 transition-all duration-200">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path>
                                            </svg>
                                            Share
                                        </button>
                                        
                                        @if($invoice->status === 'pending')
                                            @php
                                                $hasPayment = \App\Models\Payment::where('invoice_id', $invoice->id)
                                                    ->where('status', 'completed')
                                                    ->exists();
                                            @endphp
                                            @if(!$hasPayment)
                                                <button wire:click="openPaymentModal({{ $invoice->id }})" 
                                                        type="button" 
                                                        wire:loading.attr="disabled"
                                                        class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-[#EB1C24] hover:text-red-800 hover:bg-red-50 rounded border border-red-200 transition-all duration-200 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                                    <span wire:loading.remove wire:target="openPaymentModal({{ $invoice->id }})">Pay</span>
                                                    <span wire:loading wire:target="openPaymentModal({{ $invoice->id }})">Loading...</span>
                                                </button>
                                            @else
                                                <span class="text-xs text-gray-500">Payment Processing</span>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">No invoices found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $invoices->links() }}
            </div>
        </div>
    </div>

    <!-- Payment Page Section -->
    @if($showPaymentPage && $selectedInvoice)
    <div class="fixed inset-0 bg-gray-50 z-[9999] overflow-y-auto">
        <div class="max-w-2xl mx-auto py-8 px-4">
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">Pay Invoice</h2>
                    <button wire:click="closePaymentPage" type="button" class="text-gray-400 hover:text-gray-600 text-3xl font-bold">&times;</button>
                </div>
                
                <div class="space-y-6 mb-6">
                    <div class="bg-gray-50 rounded-lg p-5 border border-gray-200">
                        <h3 class="text-lg font-semibold mb-4 text-gray-900">Invoice Details</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="text-sm text-gray-600">Invoice Number:</span>
                                <p class="font-semibold text-gray-900">{{ $selectedInvoice->invoice_number }}</p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-600">User Count:</span>
                                <p class="font-semibold text-gray-900">{{ $selectedInvoice->user_count }}</p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-600">Price per User:</span>
                                <p class="font-semibold text-gray-900">£{{ number_format($selectedInvoice->price_per_user, 2) }}</p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-600">Total Amount:</span>
                                <p class="font-bold text-xl text-[#EB1C24]">£{{ number_format($selectedInvoice->total_amount, 2) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <form id="payment-form" x-data="{ 
                    isLoading: false,
                    init() {
                        // Format card number with spaces
                        const cardNumberInput = document.getElementById('card-number');
                        if (cardNumberInput) {
                            cardNumberInput.addEventListener('input', function(e) {
                                let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
                                let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
                                e.target.value = formattedValue;
                            });
                        }
                        
                        // Format expiry date
                        const expiryInput = document.getElementById('card-expiry');
                        if (expiryInput) {
                            expiryInput.addEventListener('input', function(e) {
                                let value = e.target.value.replace(/\D/g, '');
                                if (value.length >= 2) {
                                    value = value.substring(0, 2) + '/' + value.substring(2, 4);
                                }
                                e.target.value = value;
                            });
                        }
                        
                        // Only allow numbers for CVC
                        const cvcInput = document.getElementById('card-cvc');
                        if (cvcInput) {
                            cvcInput.addEventListener('input', function(e) {
                                e.target.value = e.target.value.replace(/\D/g, '');
                            });
                        }
                    }
                }">
                    <div class="space-y-6">
                        <!-- Payment Method Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <span class="flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                    </svg>
                                    Payment Method
                                </span>
                            </label>
                            <div class="p-4 border-2 border-gray-200 rounded-lg bg-gradient-to-r from-gray-50 to-gray-100 hover:border-gray-300 transition-colors">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center shadow-sm">
                                            <svg class="w-6 h-6 text-[#EB1C24]" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.31-8.86c-1.77-.45-2.34-.94-2.34-1.67 0-.84.79-1.43 2.1-1.43 1.38 0 1.9.66 1.94 1.64h1.71c-.05-1.34-.87-2.57-2.49-2.97V5H10.9v1.69c-1.51.32-2.72 1.3-2.72 2.81 0 1.79 1.49 2.69 3.66 3.21 1.95.46 2.34 1.15 2.34 1.87 0 .53-.39 1.39-2.1 1.39-1.6 0-2.23-.72-2.32-1.64H8.04c.1 1.7 1.36 2.66 2.86 2.97V19h2.34v-1.67c1.52-.29 2.72-1.16 2.73-2.77-.01-2.2-1.9-2.96-3.66-3.21z"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <span class="text-gray-900 font-semibold">Credit/Debit Card</span>
                                            <p class="text-xs text-gray-500 mt-0.5">Powered by Stripe</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/visa.svg" alt="Visa" class="h-6 w-auto opacity-60">
                                        <img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/mastercard.svg" alt="Mastercard" class="h-6 w-auto opacity-60">
                                        <img src="https://cdn.jsdelivr.net/npm/simple-icons@v9/icons/americanexpress.svg" alt="Amex" class="h-6 w-auto opacity-60">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Details Form -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <span class="flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                    </svg>
                                    Card Details
                                </span>
                            </label>
                            
                            <div class="space-y-4">
                                <!-- Card Number -->
                                <div>
                                    <label for="card-number" class="block text-sm font-medium text-gray-700 mb-1.5">Card Number</label>
                                    <input type="text" 
                                           id="card-number" 
                                           name="card_number"
                                           placeholder="1234 5678 9012 3456"
                                           maxlength="19"
                                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-[#EB1C24] focus:ring-2 focus:ring-red-100 outline-none transition-all"
                                           required>
                                    <p class="text-xs text-gray-500 mt-1">Enter your 16-digit card number</p>
                                </div>
                                
                                <!-- Expiry and CVC Row -->
                                <div class="grid grid-cols-2 gap-4">
                                    <!-- Expiry Date -->
                                    <div>
                                        <label for="card-expiry" class="block text-sm font-medium text-gray-700 mb-1.5">Expiry Date</label>
                                        <input type="text" 
                                               id="card-expiry" 
                                               name="card_expiry"
                                               placeholder="MM/YY"
                                               maxlength="5"
                                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-[#EB1C24] focus:ring-2 focus:ring-red-100 outline-none transition-all"
                                               required>
                                        <p class="text-xs text-gray-500 mt-1">MM/YY format</p>
                                    </div>
                                    
                                    <!-- CVC -->
                                    <div>
                                        <label for="card-cvc" class="block text-sm font-medium text-gray-700 mb-1.5">CVC</label>
                                        <input type="text" 
                                               id="card-cvc" 
                                               name="card_cvc"
                                               placeholder="123"
                                               maxlength="4"
                                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-[#EB1C24] focus:ring-2 focus:ring-red-100 outline-none transition-all"
                                               required>
                                        <p class="text-xs text-gray-500 mt-1">3 or 4 digits</p>
                                    </div>
                                </div>
                                
                                <!-- Cardholder Name -->
                                <div>
                                    <label for="card-name" class="block text-sm font-medium text-gray-700 mb-1.5">Cardholder Name</label>
                                    <input type="text" 
                                           id="card-name" 
                                           name="card_name"
                                           placeholder="John Doe"
                                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-[#EB1C24] focus:ring-2 focus:ring-red-100 outline-none transition-all"
                                           required>
                                    <p class="text-xs text-gray-500 mt-1">Name as it appears on card</p>
                                </div>
                            </div>
                            
                            <!-- Error Display -->
                            <div id="stripe-card-errors" 
                                 role="alert" 
                                 class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm hidden">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                    </svg>
                                    <span id="stripe-error-message"></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Security Notice -->
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-blue-600 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <div>
                                    <p class="text-blue-900 font-semibold text-sm mb-1">Secure Payment Processing</p>
                                    <p class="text-blue-800 text-xs">
                                        Your card details are encrypted and processed securely by Stripe. We never store or have access to your full card information.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="mt-8 pt-6 border-t border-gray-200">
                        <div class="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0 sm:space-x-4">
                            <button type="button" 
                                    wire:click="closePaymentPage" 
                                    class="w-full sm:w-auto px-6 py-3 border-2 border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 font-medium text-gray-700 transition-all">
                                Cancel Payment
                            </button>
                            <button type="button" 
                                    id="stripe-submit-button" 
                                    class="w-full sm:w-auto px-8 py-3 bg-gradient-to-r from-[#EB1C24] to-red-600 text-white rounded-lg hover:from-red-600 hover:to-red-700 disabled:opacity-50 disabled:cursor-not-allowed font-semibold shadow-lg hover:shadow-xl transition-all transform hover:scale-105 disabled:transform-none" 
                                    disabled>
                                <span wire:loading.remove wire:target="confirmStripePayment,createStripePaymentIntent" class="flex items-center justify-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                    </svg>
                                    Pay £{{ number_format($selectedInvoice->total_amount, 2) }}
                                </span>
                                <span wire:loading wire:target="confirmStripePayment,createStripePaymentIntent" class="flex items-center justify-center">
                                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Processing Payment...
                                </span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Custom Styles for Stripe Elements -->
    <style>
        .stripe-card-container {
            position: relative;
        }
        .stripe-card-container:focus-within {
            border-color: #EB1C24 !important;
            box-shadow: 0 0 0 3px rgba(235, 28, 36, 0.1) !important;
        }
        .stripe-card-container iframe {
            pointer-events: auto !important;
            opacity: 1 !important;
        }
        /* Ensure Stripe Elements are interactive */
        #stripe-card-element * {
            pointer-events: auto !important;
        }
        #stripe-card-element input,
        #stripe-card-element iframe {
            pointer-events: auto !important;
            cursor: text !important;
        }
    </style>
    
    <!-- Stripe.js Script for Payment Page -->
    <script src="https://js.stripe.com/v3/"></script>
    <script>
        @if(config('services.stripe.public'))
        (function() {
            const stripe = Stripe('{{ config('services.stripe.public') }}');
            let currentClientSecret = null;

            // Get form elements
            const cardNumberInput = document.getElementById('card-number');
            const cardExpiryInput = document.getElementById('card-expiry');
            const cardCvcInput = document.getElementById('card-cvc');
            const cardNameInput = document.getElementById('card-name');
            const submitBtn = document.getElementById('stripe-submit-button');
            const errorDiv = document.getElementById('stripe-card-errors');
            const errorMessage = document.getElementById('stripe-error-message');
            
            // Validate form and enable/disable submit button
            function validateForm() {
                if (!cardNumberInput || !cardExpiryInput || !cardCvcInput || !cardNameInput || !submitBtn) {
                    return;
                }
                
                const cardNumber = cardNumberInput.value.replace(/\s+/g, '');
                const expiry = cardExpiryInput.value;
                const cvc = cardCvcInput.value;
                const name = cardNameInput.value.trim();
                
                // Basic validation
                const isValid = cardNumber.length >= 13 && 
                                expiry.length === 5 && 
                                cvc.length >= 3 && 
                                name.length > 0 &&
                                currentClientSecret;
                
                submitBtn.disabled = !isValid;
                
                // Clear errors when form is valid
                if (isValid && errorDiv) {
                    errorDiv.classList.add('hidden');
                    errorDiv.classList.remove('block');
                }
            }
            
            // Add event listeners for validation
            if (cardNumberInput) {
                cardNumberInput.addEventListener('input', validateForm);
                cardNumberInput.addEventListener('blur', validateForm);
            }
            if (cardExpiryInput) {
                cardExpiryInput.addEventListener('input', validateForm);
                cardExpiryInput.addEventListener('blur', validateForm);
            }
            if (cardCvcInput) {
                cardCvcInput.addEventListener('input', validateForm);
                cardCvcInput.addEventListener('blur', validateForm);
            }
            if (cardNameInput) {
                cardNameInput.addEventListener('input', validateForm);
                cardNameInput.addEventListener('blur', validateForm);
            }
            
            // Handle form submission
            if (submitBtn) {
                submitBtn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    
                    if (!currentClientSecret) {
                        showError('Payment form not ready. Please wait...');
                        return;
                    }
                    
                    const cardNumber = cardNumberInput.value.replace(/\s+/g, '');
                    const expiry = cardExpiryInput.value.split('/');
                    const cvc = cardCvcInput.value;
                    const name = cardNameInput.value.trim();
                    
                    // Validate inputs
                    if (cardNumber.length < 13) {
                        showError('Please enter a valid card number');
                        return;
                    }
                    
                    if (expiry.length !== 2 || expiry[0].length !== 2 || expiry[1].length !== 2) {
                        showError('Please enter a valid expiry date (MM/YY)');
                        return;
                    }
                    
                    if (cvc.length < 3) {
                        showError('Please enter a valid CVC');
                        return;
                    }
                    
                    if (name.length === 0) {
                        showError('Please enter cardholder name');
                        return;
                    }
                    
                    // Disable button and show processing
                    submitBtn.disabled = true;
                    const originalHtml = submitBtn.innerHTML;
                    submitBtn.innerHTML = `
                        <span class="flex items-center justify-center">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Processing Payment...
                        </span>
                    `;
                    
                    try {
                        // Create payment method from card details
                        const {paymentMethod, error: pmError} = await stripe.createPaymentMethod({
                            type: 'card',
                            card: {
                                number: cardNumber,
                                exp_month: parseInt(expiry[0]),
                                exp_year: 2000 + parseInt(expiry[1]), // Convert YY to YYYY
                                cvc: cvc,
                            },
                            billing_details: {
                                name: name,
                            }
                        });
                        
                        if (pmError) {
                            showError(pmError.message);
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalHtml;
                            validateForm();
                            return;
                        }
                        
                        // Confirm payment with payment method
                        const {error, paymentIntent} = await stripe.confirmCardPayment(
                            currentClientSecret,
                            {
                                payment_method: paymentMethod.id
                            }
                        );
                        
                        if (error) {
                            showError(error.message);
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalHtml;
                            validateForm();
                        } else if (paymentIntent && paymentIntent.status === 'succeeded') {
                            @this.confirmStripePayment(paymentIntent.id);
                        }
                    } catch (err) {
                        console.error('Payment error:', err);
                        showError('Payment failed. Please try again.');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalHtml;
                        validateForm();
                    }
                });
            }
            
            function showError(message) {
                if (errorDiv) {
                    errorDiv.classList.remove('hidden');
                    errorDiv.classList.add('block');
                }
                if (errorMessage) {
                    errorMessage.textContent = message;
                }
            }
            
            // Listen for Livewire events to get client secret
            document.addEventListener('livewire:init', () => {
                Livewire.on('stripe-payment-intent-created', (data) => {
                    const secret = typeof data === 'string' ? data : (data?.clientSecret || data?.client_secret || data?.[0]);
                    if (secret && secret.includes('_secret_')) {
                        console.log('Payment intent created, client secret received');
                        currentClientSecret = secret;
                        validateForm(); // Re-validate form now that we have client secret
                    }
                });
            });
            
            // Set client secret if already available
            @if($showPaymentPage && $stripeClientSecret)
                currentClientSecret = '{{ $stripeClientSecret }}';
                setTimeout(validateForm, 500);
            @endif
        })();
        @endif
    </script>
    @endif

    <!-- Payment Modal (Hidden - keeping for backward compatibility) -->
    @if($selectedInvoice && $showPaymentModal)
    <div x-data="{ 
            show: @entangle('showPaymentModal'),
            clientSecret: @entangle('stripeClientSecret'),
            init() {
                const initStripe = () => {
                    if (this.show && this.clientSecret && window.initStripeNow) {
                        console.log('Initializing Stripe with client secret...');
                        setTimeout(() => {
                            window.initStripeNow(this.clientSecret);
                        }, 1000);
                    }
                };
                
                this.$watch('show', (value) => {
                    if (value) {
                        setTimeout(initStripe, 800);
                    }
                });
                
                this.$watch('clientSecret', (value) => {
                    if (value && this.show) {
                        setTimeout(initStripe, 800);
                    }
                });
                
                if (this.show && this.clientSecret) {
                    setTimeout(initStripe, 1200);
                }
            }
        }" 
         x-show="show" 
         @click.self="$wire.closePaymentModal()"
         class="fixed inset-0 bg-black bg-opacity-50 z-[9999] flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md max-h-[90vh] overflow-y-auto shadow-2xl m-4">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold">Pay Invoice</h2>
                <button wire:click="closePaymentModal" type="button" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
            </div>
            
            <div class="space-y-4 mb-4">
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-600">Invoice Number:</span>
                        <span class="font-semibold">{{ $selectedInvoice->invoice_number }}</span>
                    </div>
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-600">User Count:</span>
                        <span class="font-semibold">{{ $selectedInvoice->user_count }}</span>
                    </div>
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-600">Price per User:</span>
                        <span class="font-semibold">${{ number_format($selectedInvoice->price_per_user, 2) }}</span>
                    </div>
                    <div class="flex justify-between border-t pt-2">
                        <span class="text-gray-700 font-semibold">Total Amount:</span>
                        <span class="font-bold text-lg text-[#EB1C24]">${{ number_format($selectedInvoice->total_amount, 2) }}</span>
                    </div>
                </div>
            </div>

            <form id="payment-form">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                        <div class="mt-1 block w-full p-3 border border-gray-300 rounded-md bg-gray-50">
                            <span class="text-gray-700 font-medium">Credit/Debit Card (Stripe)</span>
                        </div>
                    </div>

                    <!-- Stripe Card Element -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Card Details</label>
                        <div wire:ignore id="stripe-card-element" style="min-height: 60px; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; background: white;">
                            <!-- Stripe Elements will create form elements here -->
                        </div>
                        <div id="stripe-card-errors" role="alert" class="text-red-500 text-sm mt-2"></div>
                    </div>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <p class="text-blue-800 text-sm">
                            <strong>Secure Payment:</strong> Your card details are processed securely by Stripe. We never store your card information.
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-2">
                    <button type="button" wire:click="closePaymentModal" class="px-4 py-2 border rounded-md hover:bg-gray-50">Cancel</button>
                    <button type="button" id="stripe-submit-button" class="px-4 py-2 bg-[#EB1C24] text-white rounded-md hover:bg-red-700 disabled:opacity-50" disabled>
                        <span wire:loading.remove wire:target="confirmStripePayment,createStripePaymentIntent">Pay £{{ number_format($selectedInvoice->total_amount, 2) }}</span>
                        <span wire:loading wire:target="confirmStripePayment,createStripePaymentIntent">Processing...</span>
                    </button>
                </div>
            </form>

            <!-- Stripe.js Script -->
            <script src="https://js.stripe.com/v3/"></script>
            <script>
                @if(config('services.stripe.public'))
                (function() {
                    const stripe = Stripe('{{ config('services.stripe.public') }}');
                    let cardElement = null;
                    let elements = null;
                    let currentClientSecret = null;
                    let isInitialized = false;

                    function initializeStripe(clientSecret) {
                        if (isInitialized) {
                            console.log('Already initialized');
                            return;
                        }
                        
                        if (!clientSecret || !clientSecret.includes('_secret_')) {
                            console.error('Invalid client secret');
                            return;
                        }
                        
                        const container = document.getElementById('stripe-card-element');
                        if (!container) {
                            console.log('Container not found, retrying...');
                            setTimeout(() => initializeStripe(clientSecret), 300);
                            return;
                        }
                        
                        console.log('Initializing Stripe Elements...');
                        isInitialized = true;
                        currentClientSecret = clientSecret;
                        
                        // Clean up
                        if (cardElement) {
                            try { cardElement.unmount(); } catch(e) {}
                        }
                        if (elements) {
                            elements = null;
                        }
                        
                        container.innerHTML = '';
                        
                        try {
                            // Create Elements
                            elements = stripe.elements({
                                clientSecret: clientSecret,
                                appearance: {
                                    theme: 'stripe',
                                }
                            });
                            
                            // Create and mount payment element
                            cardElement = elements.create('payment');
                            cardElement.mount('#stripe-card-element');
                            
                            console.log('Stripe Elements mounted');
                            
                            // Handle card changes
                            cardElement.on('change', (event) => {
                                const errorDiv = document.getElementById('stripe-card-errors');
                                const submitBtn = document.getElementById('stripe-submit-button');
                                
                                if (errorDiv) {
                                    errorDiv.textContent = event.error ? event.error.message : '';
                                }
                                
                                if (submitBtn) {
                                    submitBtn.disabled = !event.complete || !!event.error;
                                }
                            });
                            
                            // Setup submit button
                            const submitBtn = document.getElementById('stripe-submit-button');
                            if (submitBtn) {
                                const newBtn = submitBtn.cloneNode(true);
                                submitBtn.parentNode.replaceChild(newBtn, submitBtn);
                                
                                newBtn.addEventListener('click', async (e) => {
                                    e.preventDefault();
                                    
                                    if (!cardElement || !currentClientSecret) {
                                        alert('Payment form not ready');
                                        return;
                                    }
                                    
                                    newBtn.disabled = true;
                                    newBtn.innerHTML = '<span>Processing...</span>';
                                    
                                    try {
                                        const {error, paymentIntent} = await stripe.confirmCardPayment(
                                            currentClientSecret,
                                            {
                                                payment_method: {
                                                    card: cardElement,
                                                }
                                            }
                                        );
                                        
                                        if (error) {
                                            const errorDiv = document.getElementById('stripe-card-errors');
                                            if (errorDiv) errorDiv.textContent = error.message;
                                            newBtn.disabled = false;
                                            newBtn.innerHTML = '<span>Pay £{{ number_format($selectedInvoice->total_amount ?? 0, 2) }}</span>';
                                        } else if (paymentIntent && paymentIntent.status === 'succeeded') {
                                            @this.confirmStripePayment(paymentIntent.id);
                                        }
                                    } catch (err) {
                                        console.error('Payment error:', err);
                                        const errorDiv = document.getElementById('stripe-card-errors');
                                        if (errorDiv) errorDiv.textContent = 'Payment failed. Please try again.';
                                        newBtn.disabled = false;
                                        newBtn.innerHTML = '<span>Pay £{{ number_format($selectedInvoice->total_amount ?? 0, 2) }}</span>';
                                    }
                                });
                            }
                            
                        } catch (err) {
                            console.error('Stripe initialization error:', err);
                            isInitialized = false;
                            const errorDiv = document.getElementById('stripe-card-errors');
                            if (errorDiv) {
                                errorDiv.textContent = 'Failed to load payment form. Please refresh the page.';
                            }
                        }
                    }

                    // Expose function
                    window.initStripeNow = initializeStripe;
                            
                            const submitBtn = document.getElementById('stripe-submit-button');
                            if (submitBtn) {
                                const newBtn = submitBtn.cloneNode(true);
                                submitBtn.parentNode.replaceChild(newBtn, submitBtn);
                                
                                newBtn.onclick = async (e) => {
                                    e.preventDefault();
                                    newBtn.disabled = true;
                                    newBtn.innerHTML = '<span>Processing...</span>';
                                    
                                    try {
                                        const {error, paymentIntent} = await stripe.confirmCardPayment(
                                            currentClientSecret,
                                            { payment_method: { card: cardElement } }
                                        );
                                        
                                        if (error) {
                                            document.getElementById('stripe-card-errors').textContent = error.message;
                                            newBtn.disabled = false;
                                            newBtn.innerHTML = '<span>Pay £{{ number_format($selectedInvoice->total_amount ?? 0, 2) }}</span>';
                                        } else if (paymentIntent?.status === 'succeeded') {
                                            @this.confirmStripePayment(paymentIntent.id);
                                        }
                                    } catch (err) {
                                        document.getElementById('stripe-card-errors').textContent = 'Payment failed. Please try again.';
                                        newBtn.disabled = false;
                                        newBtn.innerHTML = '<span>Pay £{{ number_format($selectedInvoice->total_amount ?? 0, 2) }}</span>';
                                    }
                                };
                            }
                        } catch (err) {
                            console.error('Stripe error:', err);
                            window.stripeInitialized = false;
                        }
                    };

                    // Listen for Livewire events
                    document.addEventListener('livewire:init', () => {
                        Livewire.on('stripe-payment-intent-created', (data) => {
                            const secret = typeof data === 'string' ? data : (data?.clientSecret || data?.client_secret || data?.[0]);
                            if (secret && secret.includes('_secret_')) {
                                setTimeout(() => initializeStripe(secret), 1000);
                            }
                        });

                        Livewire.on('stripe-payment-modal-closed', () => {
                            if (cardElement) {
                                try { cardElement.unmount(); } catch(e) {}
                                cardElement = null;
                            }
                            elements = null;
                            isInitialized = false;
                            currentClientSecret = null;
                            
                            const container = document.getElementById('stripe-card-element');
                            if (container) {
                                container.innerHTML = '';
                            }
                        });
                    });
                })();
                @endif
            </script>
        </div>
    </div>
    @endif

    <!-- Renew Subscription Modal -->
    @if($showRenewModal)
    <div x-data="{ show: true }" 
         x-show="show" 
         @click.self="$wire.closeRenewModal()"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black bg-opacity-50 z-[10000] flex items-center justify-center">
        <div x-show="show"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="bg-white rounded-lg p-6 w-full max-w-md max-h-[90vh] overflow-y-auto shadow-2xl m-4">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold">Renew Subscription</h2>
                <button wire:click="closeRenewModal" type="button" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
            </div>
            
            <div class="space-y-4">
                @if(session()->has('error'))
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <p class="text-red-800 text-sm font-medium">{{ session('error') }}</p>
                    </div>
                @endif
                
                @if($renewalUserCount > 0)
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h3 class="font-semibold text-blue-900 mb-2">Renewal Details</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Number of Users:</span>
                                <span class="font-semibold">{{ $renewalUserCount }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Price per User:</span>
                                <span class="font-semibold">${{ number_format($renewalPricePerUser, 2) }}</span>
                            </div>
                            <div class="flex justify-between border-t pt-2">
                                <span class="text-gray-600 font-semibold">Total Amount:</span>
                                <span class="font-bold text-lg text-[#EB1C24]">${{ number_format($renewalPrice, 2) }}</span>
                            </div>
                            <div class="flex justify-between mt-2">
                                <span class="text-gray-600">Subscription Expiry:</span>
                                <span class="font-semibold">{{ $renewalExpiryDate ?? 'N/A' }}</span>
                            </div>
                        </div>
                    </div>

                    <p class="text-sm text-gray-600">
                        By clicking "Proceed to Payment", you will be redirected to submit your payment. Your subscription will be activated immediately after successful payment.
                    </p>

                    <div class="flex justify-end space-x-2">
                        <button wire:click="closeRenewModal" type="button" class="px-4 py-2 border rounded-md hover:bg-gray-50">Cancel</button>
                        <button wire:click="renewSubscription" type="button" class="px-4 py-2 bg-[#EB1C24] text-white rounded-md hover:bg-red-700">
                            <span wire:loading.remove wire:target="renewSubscription">Proceed to Payment</span>
                            <span wire:loading wire:target="renewSubscription">Processing...</span>
                        </button>
                    </div>
                @else
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <p class="text-yellow-800 text-sm font-medium">
                            No users found in your organisation. Please contact your administrator to add users first.
                        </p>
                    </div>
                    <div class="flex justify-end">
                        <button wire:click="closeRenewModal" type="button" class="px-4 py-2 border rounded-md hover:bg-gray-50">Close</button>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- Subscription Expired Modal -->
    <div x-data="{ show: @entangle('showSubscriptionExpiredModal') }" x-show="show" x-cloak style="display: none;" class="fixed inset-0 bg-black bg-opacity-50 z-[10000] flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md max-h-[90vh] overflow-y-auto shadow-2xl m-4">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-red-600">⚠️ Subscription Expired</h2>
                <button wire:click="closeSubscriptionExpiredModal" type="button" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
            </div>
            
            <div class="space-y-4">
                <p class="text-gray-700">
                    Your subscription has expired or is not active. To continue using the service, please renew your subscription.
                </p>
                
                @if(isset($subscriptionStatus['has_pending_invoice']) && $subscriptionStatus['has_pending_invoice'])
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <p class="text-yellow-800 text-sm">
                            You have a pending invoice. Please make a payment to activate your subscription.
                        </p>
                    </div>
                @endif

                <div class="flex justify-end space-x-2">
                    <button wire:click="closeSubscriptionExpiredModal" type="button" class="px-4 py-2 border rounded-md">Close</button>
                    @if(isset($subscriptionStatus['has_pending_invoice']) && $subscriptionStatus['has_pending_invoice'])
                        <a href="{{ route('billing') }}" class="px-4 py-2 bg-[#EB1C24] text-white rounded-md">Go to Billing</a>
                    @else
                        <button wire:click="openRenewModal" type="button" class="px-4 py-2 bg-[#EB1C24] text-white rounded-md">Renew Subscription</button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if(session()->has('success'))
        <div class="fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-[10001]" 
             x-data="{ show: true }" 
             x-show="show"
             x-init="setTimeout(() => { show = false; }, 5000)">
            {{ session('success') }}
        </div>
    @endif

    <!-- Invoice Details Modal -->
    @if($showInvoiceModal && $selectedInvoiceForView)
    <div x-data="{ show: @entangle('showInvoiceModal') }" 
         x-show="show" 
         @click.self="$wire.closeInvoiceModal()"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black bg-opacity-50 z-[10000] flex items-center justify-center p-4 overflow-y-auto">
        <div x-show="show"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            
            <!-- Modal Header -->
            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center z-10">
                <h2 class="text-2xl font-bold text-gray-900">Invoice Details</h2>
                <button wire:click="closeInvoiceModal" type="button" class="text-gray-400 hover:text-gray-600 text-3xl font-bold">&times;</button>
            </div>
            
            <!-- Modal Content -->
            <div class="p-6 space-y-6">
                <!-- Invoice Information -->
                <div class="bg-gray-50 rounded-lg p-5 border border-gray-200">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Invoice Information
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div>
                            <span class="text-sm text-gray-600">Invoice Number:</span>
                            <p class="font-semibold text-gray-900">{{ $selectedInvoiceForView->invoice_number }}</p>
                        </div>
                        <div>
                            <span class="text-sm text-gray-600">Invoice Date:</span>
                            <p class="font-semibold text-gray-900">{{ $selectedInvoiceForView->invoice_date->format('M d, Y') }}</p>
                        </div>
                        <div>
                            <span class="text-sm text-gray-600">Due Date:</span>
                            <p class="font-semibold text-gray-900">{{ $selectedInvoiceForView->due_date->format('M d, Y') }}</p>
                        </div>
                        <div>
                            <span class="text-sm text-gray-600">Status:</span>
                            <p>
                                <span class="px-3 py-1 text-xs rounded-full font-semibold
                                    {{ $selectedInvoiceForView->status === 'paid' ? 'bg-green-100 text-green-800' : 
                                       ($selectedInvoiceForView->status === 'overdue' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                    {{ ucfirst($selectedInvoiceForView->status) }}
                                </span>
                            </p>
                        </div>
                        @if($selectedInvoiceForView->paid_date)
                        <div>
                            <span class="text-sm text-gray-600">Paid Date:</span>
                            <p class="font-semibold text-gray-900">{{ $selectedInvoiceForView->paid_date->format('M d, Y') }}</p>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Subscription Details -->
                @if($selectedInvoiceForView->subscription)
                <div class="bg-blue-50 rounded-lg p-5 border border-blue-200">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                        Subscription Details
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <span class="text-sm text-gray-600">Tier:</span>
                            <p class="font-semibold text-gray-900 capitalize">{{ $selectedInvoiceForView->subscription->tier ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <span class="text-sm text-gray-600">User Count:</span>
                            <p class="font-semibold text-gray-900">{{ $selectedInvoiceForView->user_count }}</p>
                        </div>
                        <div>
                            <span class="text-sm text-gray-600">Price per User:</span>
                            <p class="font-semibold text-gray-900">£{{ number_format($selectedInvoiceForView->price_per_user, 2) }}</p>
                        </div>
                        <div>
                            <span class="text-sm text-gray-600">Subscription Status:</span>
                            <p>
                                <span class="px-3 py-1 text-xs rounded-full font-semibold
                                    {{ $selectedInvoiceForView->subscription->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ ucfirst($selectedInvoiceForView->subscription->status ?? 'N/A') }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Payment Details -->
                @php
                    $payments = $selectedInvoiceForView->payments()->where('status', 'completed')->get();
                @endphp
                @if($payments->count() > 0)
                <div class="bg-green-50 rounded-lg p-5 border border-green-200">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        Payment Details
                    </h3>
                    <div class="space-y-4">
                        @foreach($payments as $payment)
                        <div class="bg-white rounded-lg p-4 border border-green-300">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div>
                                    <span class="text-sm text-gray-600">Payment Method:</span>
                                    <p class="font-semibold text-gray-900 capitalize">{{ $payment->payment_method ?? 'Stripe' }}</p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-600">Amount:</span>
                                    <p class="font-semibold text-gray-900">£{{ number_format($payment->amount, 2) }}</p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-600">Transaction ID:</span>
                                    <p class="font-semibold text-gray-900 text-xs break-all">{{ $payment->transaction_id ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-600">Payment Date:</span>
                                    <p class="font-semibold text-gray-900">{{ $payment->payment_date ? $payment->payment_date->format('M d, Y') : ($payment->created_at->format('M d, Y')) }}</p>
                                </div>
                                @if($payment->paidBy)
                                <div>
                                    <span class="text-sm text-gray-600">Paid By:</span>
                                    <p class="font-semibold text-gray-900">{{ $payment->paidBy->name ?? $payment->paidBy->email }}</p>
                                </div>
                                @endif
                            </div>
                            
                            <!-- Card Details (if Stripe payment) -->
                            @if($payment->payment_method === 'stripe' && (isset($payment->stripe_card_brand) || isset($payment->stripe_card_last4)))
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                    </svg>
                                    Card Details
                                </h4>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    @if(isset($payment->stripe_card_brand))
                                    <div>
                                        <span class="text-sm text-gray-600">Card Brand:</span>
                                        <p class="font-semibold text-gray-900 capitalize">{{ $payment->stripe_card_brand }}</p>
                                    </div>
                                    @endif
                                    @if(isset($payment->stripe_card_last4))
                                    <div>
                                        <span class="text-sm text-gray-600">Card Number:</span>
                                        <p class="font-semibold text-gray-900">**** **** **** {{ $payment->stripe_card_last4 }}</p>
                                    </div>
                                    @endif
                                    @if(isset($payment->stripe_card_exp_month) && isset($payment->stripe_card_exp_year))
                                    <div>
                                        <span class="text-sm text-gray-600">Expiry Date:</span>
                                        <p class="font-semibold text-gray-900">{{ str_pad($payment->stripe_card_exp_month, 2, '0', STR_PAD_LEFT) }}/{{ $payment->stripe_card_exp_year }}</p>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            @endif
                            
                            @if($payment->payment_notes)
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <span class="text-sm text-gray-600">Notes:</span>
                                <p class="font-semibold text-gray-900 text-sm mt-1">{{ $payment->payment_notes }}</p>
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @else
                <div class="bg-yellow-50 rounded-lg p-5 border border-yellow-200">
                    <p class="text-yellow-800 text-sm font-medium">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        No payment records found for this invoice.
                    </p>
                </div>
                @endif

                <!-- Invoice Summary -->
                <div class="bg-gray-50 rounded-lg p-5 border border-gray-200">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        Invoice Summary
                    </h3>
                    <div class="space-y-3">
                        <div class="flex justify-between py-2 border-b border-gray-200">
                            <span class="text-gray-600">Subtotal:</span>
                            <span class="font-semibold text-gray-900">£{{ number_format($selectedInvoiceForView->subtotal ?? ($selectedInvoiceForView->total_amount - ($selectedInvoiceForView->tax_amount ?? 0)), 2) }}</span>
                        </div>
                        @if($selectedInvoiceForView->tax_amount)
                        <div class="flex justify-between py-2 border-b border-gray-200">
                            <span class="text-gray-600">Tax (VAT):</span>
                            <span class="font-semibold text-gray-900">£{{ number_format($selectedInvoiceForView->tax_amount, 2) }}</span>
                        </div>
                        @endif
                        <div class="flex justify-between py-2 border-t-2 border-gray-300 pt-3">
                            <span class="text-lg font-semibold text-gray-900">Total Amount:</span>
                            <span class="text-xl font-bold text-[#EB1C24]">£{{ number_format($selectedInvoiceForView->total_amount, 2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Organisation Details -->
                @if($selectedInvoiceForView->organisation)
                <div class="bg-gray-50 rounded-lg p-5 border border-gray-200">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        Organisation Details
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div>
                            <span class="text-sm text-gray-600">Organisation Name:</span>
                            <p class="font-semibold text-gray-900">{{ $selectedInvoiceForView->organisation->name }}</p>
                        </div>
                        @if($selectedInvoiceForView->organisation->admin_email)
                        <div>
                            <span class="text-sm text-gray-600">Admin Email:</span>
                            <p class="font-semibold text-gray-900">{{ $selectedInvoiceForView->organisation->admin_email }}</p>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>

            <!-- Modal Footer -->
            <div class="sticky bottom-0 bg-gray-50 border-t border-gray-200 px-6 py-4 flex justify-end space-x-3">
                <button wire:click="closeInvoiceModal" type="button" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 font-medium text-gray-700">
                    Close
                </button>
                <a href="{{ route('invoices.download', $selectedInvoiceForView->id) }}" 
                   download
                   class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium">
                    Download Invoice
                </a>
            </div>
        </div>
    </div>
    @endif

    <!-- Share Invoice Modal -->
    @if($showShareModal && $selectedInvoiceForShare)
    <div x-data="{ show: @entangle('showShareModal') }" 
         x-show="show" 
         @click.self="$wire.closeShareModal()"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black bg-opacity-50 z-[10000] flex items-center justify-center p-4">
        <div x-show="show"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="bg-white rounded-lg shadow-xl w-full max-w-md">
            
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-900">Share Invoice</h2>
                <button wire:click="closeShareModal" type="button" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
            </div>
            
            <!-- Modal Content -->
            <div class="p-6 space-y-4">
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Shareable Link</label>
                    <div class="flex items-center space-x-2">
                        <input type="text" 
                               value="{{ $shareLink }}" 
                               readonly
                               id="share-link-input"
                               class="flex-1 px-3 py-2 border border-gray-300 rounded-md bg-white text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <button wire:click="copyShareLink" 
                                type="button"
                                class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors text-sm font-medium">
                            Copy
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Anyone with this link can view the invoice without logging in.</p>
                </div>

                <div class="space-y-2">
                    <button wire:click="shareViaWhatsApp" 
                            type="button"
                            class="w-full px-4 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors flex items-center justify-center space-x-2">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                        </svg>
                        <span>Share via WhatsApp</span>
                    </button>

                    <button wire:click="shareViaEmail" 
                            type="button"
                            class="w-full px-4 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors flex items-center justify-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        <span>Share via Email</span>
                    </button>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end">
                <button wire:click="closeShareModal" 
                        type="button" 
                        class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50 font-medium text-gray-700">
                    Close
                </button>
            </div>
        </div>
    </div>
    @endif
</div>

<script>
    // Handle redirect to Stripe Checkout
    document.addEventListener('livewire:init', () => {
        Livewire.on('redirect-to-stripe', (event) => {
            const url = event.url || event[0]?.url;
            if (url) {
                window.location.href = url;
            }
        });
        
        // Handle copy to clipboard
        Livewire.on('copy-to-clipboard', (event) => {
            const text = event.text || event[0]?.text;
            if (text) {
                navigator.clipboard.writeText(text).then(() => {
                    // Show success message
                    const successMsg = document.createElement('div');
                    successMsg.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-[10001]';
                    successMsg.textContent = 'Link copied to clipboard!';
                    document.body.appendChild(successMsg);
                    setTimeout(() => successMsg.remove(), 3000);
                }).catch(err => {
                    console.error('Failed to copy:', err);
                });
            }
        });
        
        // Handle open window
        Livewire.on('open-window', (event) => {
            const url = event.url || event[0]?.url;
            if (url) {
                window.open(url, '_blank');
            }
        });
        
        // Refresh billing page after payment success
        @if(session()->has('refresh_billing'))
            setTimeout(() => {
                @this.call('refreshBilling');
            }, 500);
        @endif
    });
    
    // Also refresh on page load if payment was successful
    @if(session()->has('refresh_billing'))
        // Force full page reload to ensure all data is fresh
        window.addEventListener('load', () => {
            setTimeout(() => {
                window.location.reload();
            }, 500);
        });
    @endif
</script>

