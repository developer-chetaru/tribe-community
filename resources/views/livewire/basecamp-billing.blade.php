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

            @php
                $user = auth()->user() ?? \App\Models\User::find($userId ?? null);
                $subscription = $subscription ?? ($user ? \App\Models\SubscriptionRecord::where('user_id', $user->id)->where('tier', 'basecamp')->first() : null);
            @endphp

            <!-- Email Verification Banner (Non-blocking) -->
            @if($user && !$user->email_verified_at && in_array($user->status, ['active_unverified', 'active_verified', 'pending_payment']))
                <div class="mb-4 bg-yellow-50 border border-yellow-200 rounded-lg p-4 flex items-center justify-between" role="alert">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-yellow-800">Please verify your email address</p>
                            <p class="text-xs text-yellow-700">Check your inbox for the verification link</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button 
                            wire:click="resendVerificationEmail"
                            class="text-xs text-yellow-800 hover:text-yellow-900 underline">
                            Resend Email
                        </button>
                        <button 
                            wire:click="dismissVerificationBanner"
                            class="text-yellow-600 hover:text-yellow-800">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            @endif

            <!-- Grace Period Banner -->
            @if($user && $user->payment_grace_period_start && $user->status === 'suspended')
                @php
                    $gracePeriodStart = \Carbon\Carbon::parse($user->payment_grace_period_start);
                    $daysInGracePeriod = now()->diffInDays($gracePeriodStart);
                    $daysRemaining = 7 - $daysInGracePeriod;
                    $isCritical = $daysRemaining <= 1;
                @endphp
                <div class="mb-4 {{ $isCritical ? 'bg-red-50 border-red-200' : 'bg-yellow-50 border-yellow-200' }} border rounded-lg p-4" role="alert">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 {{ $isCritical ? 'text-red-600' : 'text-yellow-600' }}" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <p class="text-sm font-medium {{ $isCritical ? 'text-red-800' : 'text-yellow-800' }}">
                                    @if($isCritical)
                                        🚨 Critical: Payment Required Immediately
                                    @else
                                        ⚠️ Payment Failed - Action Required
                                    @endif
                                </p>
                                <p class="text-xs {{ $isCritical ? 'text-red-700' : 'text-yellow-700' }}">
                                    @if($daysRemaining > 0)
                                        Your account will be suspended in {{ $daysRemaining }} {{ $daysRemaining === 1 ? 'day' : 'days' }} if payment is not received.
                                    @else
                                        Your account will be suspended today if payment is not received.
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button 
                                wire:click="openPaymentModal"
                                class="text-xs {{ $isCritical ? 'bg-red-600 text-white' : 'bg-yellow-600 text-white' }} px-3 py-1.5 rounded hover:opacity-90 transition">
                                Pay Now
                            </button>
                        </div>
                    </div>
                    @if($daysRemaining > 0 && !$isCritical)
                        <div class="mt-3 pt-3 border-t {{ $isCritical ? 'border-red-200' : 'border-yellow-200' }}">
                            <div class="flex items-center gap-2 text-xs {{ $isCritical ? 'text-red-700' : 'text-yellow-700' }}">
                                <span>Time remaining:</span>
                                <span class="font-semibold">{{ $daysRemaining }} {{ $daysRemaining === 1 ? 'day' : 'days' }}</span>
                            </div>
                        </div>
                    @endif
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

                @if($isActive && $subscription)
                    <!-- Upcoming Bill Section -->
                    <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                        <h4 class="text-md font-semibold mb-3 text-gray-800">Upcoming Payment</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Amount</p>
                                <p class="text-xl font-bold text-gray-900">${{ number_format($monthlyPrice, 2) }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Billing Date</p>
                                <p class="text-lg font-semibold text-gray-900">
                                    {{ $subscription->next_billing_date ? $subscription->next_billing_date->format('M d, Y') : 'N/A' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Billing Period</p>
                                <p class="text-lg font-semibold text-gray-900">
                                    @if($subscription->current_period_start && $subscription->current_period_end)
                                        {{ $subscription->current_period_start->format('M Y') }} - {{ $subscription->current_period_end->format('M Y') }}
                                    @else
                                        N/A
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Method Display -->
                    @if($paymentMethod)
                    <div class="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-sm text-gray-600">Payment Method</p>
                                <p class="text-lg font-semibold text-gray-900">
                                    {{ ucfirst($paymentMethod['brand'] ?? 'Card') }} •••• {{ $paymentMethod['last4'] ?? '****' }}
                                    @if(isset($paymentMethod['exp_month']) && isset($paymentMethod['exp_year']))
                                        <span class="text-sm text-gray-500">(Expires {{ str_pad($paymentMethod['exp_month'], 2, '0', STR_PAD_LEFT) }}/{{ substr($paymentMethod['exp_year'], -2) }})</span>
                                    @endif
                                </p>
                            </div>
                            <button 
                                wire:click="openUpdatePaymentModal"
                                class="text-sm text-blue-600 hover:text-blue-800 font-semibold">
                                Update Payment Method
                            </button>
                        </div>
                    </div>
                    @endif
                    
                    <!-- Action Buttons -->
                    <div class="mt-6 flex gap-3">
                        @if($subscription->status !== 'canceled')
                        <button 
                            wire:click="openCancelModal"
                            class="text-sm text-red-600 hover:text-red-800 font-semibold border border-red-200 px-4 py-2 rounded-lg hover:bg-red-50 transition">
                            Cancel Subscription
                        </button>
                        @endif
                    </div>
                @endif

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
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Invoices</h3>
                    
                    <!-- Search and Filter -->
                    <div class="flex gap-3">
                        <input 
                            type="text" 
                            wire:model.live.debounce.300ms="searchQuery"
                            placeholder="Search invoices..." 
                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#EB1C24] focus:border-transparent">
                        <select 
                            wire:model.live="statusFilter"
                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#EB1C24] focus:border-transparent">
                            <option value="">All Status</option>
                            <option value="paid">Paid</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                </div>
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
                                            <div class="flex items-center space-x-3">
                                                <button wire:click="openInvoiceModal({{ $invoice->id }})" 
                                                        type="button"
                                                        class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded border border-blue-200 transition-all duration-200">
                                                    View Invoice
                                                </button>
                                                <a href="{{ route('invoices.download', $invoice->id) }}" 
                                                   class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-green-600 hover:text-green-800 hover:bg-green-50 rounded border border-green-200 transition-all duration-200"
                                                   title="Download Invoice">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                                    </svg>
                                                    Download
                                                </a>
                                                <button wire:click="openShareModal({{ $invoice->id }})" 
                                                        type="button"
                                                        class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-purple-600 hover:text-purple-800 hover:bg-purple-50 rounded border border-purple-200 transition-all duration-200">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path>
                                                    </svg>
                                                    Share
                                                </button>
                                                @if($invoice->status === 'unpaid')
                                                    <button 
                                                        wire:click="$set('selectedInvoice', {{ $invoice->id }})"
                                                        wire:click="openPaymentModal"
                                                        class="text-[#EB1C24] hover:text-red-600 font-semibold">
                                                        Pay Now
                                                    </button>
                                                @endif
                                            </div>
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
                            
                            <!-- Terms of Service and Privacy Policy -->
                            <div class="mb-4">
                                <label class="flex items-start">
                                    <input 
                                        type="checkbox" 
                                        id="terms-checkbox"
                                        class="mt-1 mr-2" 
                                        required>
                                    <span class="text-sm text-gray-600">
                                        I agree to the 
                                        <a href="{{ route('terms') ?? '#' }}" target="_blank" class="text-blue-600 hover:underline">Terms of Service</a> 
                                        and 
                                        <a href="{{ route('privacy') ?? '#' }}" target="_blank" class="text-blue-600 hover:underline">Privacy Policy</a>
                                    </span>
                                </label>
                            </div>
                            
                            <!-- Security Badges -->
                            <div class="mb-4 flex items-center justify-center gap-4 text-xs text-gray-500">
                                <div class="flex items-center gap-1">
                                    <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span>SSL Secured</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span>PCI Compliant</span>
                                </div>
                            </div>
                            
                            <button 
                                id="submit-payment"
                                type="button"
                                wire:loading.attr="disabled"
                                wire:target="confirmStripePayment"
                                class="w-full bg-[#EB1C24] text-white font-semibold py-2 px-4 rounded-lg hover:bg-red-600 transition disabled:opacity-50"
                                onclick="if(!document.getElementById('terms-checkbox').checked) { alert('Please accept the Terms of Service and Privacy Policy to continue.'); return false; }">
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
            
            <!-- Cancel Subscription Modal -->
            @if($showCancelModal)
            <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" wire:click.self="closeCancelModal">
                <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                    <h3 class="text-xl font-semibold mb-4">Cancel Subscription</h3>
                    <p class="text-gray-600 mb-6">
                        Are you sure you want to cancel your subscription? You will continue to have access until 
                        {{ $subscription && $subscription->next_billing_date ? $subscription->next_billing_date->format('M d, Y') : 'the end of your billing period' }}.
                    </p>
                    <div class="flex gap-3 justify-end">
                        <button 
                            wire:click="closeCancelModal"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                            Keep Subscription
                        </button>
                        <button 
                            wire:click="cancelSubscription"
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                            Cancel Subscription
                        </button>
                    </div>
                </div>
            </div>
            @endif
            
            <!-- Update Payment Method Modal -->
            @if($showUpdatePaymentModal)
            <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" wire:click.self="closeUpdatePaymentModal">
                <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                    <h3 class="text-xl font-semibold mb-4">Update Payment Method</h3>
                    <p class="text-gray-600 mb-4">
                        To update your payment method, please make a new payment. Your payment method will be updated automatically.
                    </p>
                    
                    @php
                        $user = auth()->user() ?? \App\Models\User::find($userId ?? null);
                        $hasUnpaidInvoice = $user ? \App\Models\Invoice::where('user_id', $user->id)
                            ->where('tier', 'basecamp')
                            ->where('status', 'unpaid')
                            ->exists() : false;
                    @endphp
                    
                    @if($hasUnpaidInvoice)
                        <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <p class="text-sm text-yellow-800">
                                <strong>Note:</strong> After updating your payment method, we will automatically retry payment for any unpaid invoices.
                            </p>
                        </div>
                    @endif
                    
                    <div class="flex gap-3 justify-end">
                        <button 
                            wire:click="closeUpdatePaymentModal"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                            Close
                        </button>
                        <button 
                            wire:click="createInvoice"
                            class="px-4 py-2 bg-[#EB1C24] text-white rounded-lg hover:bg-red-600 transition">
                            Make Payment
                        </button>
                    </div>
                </div>
            </div>
            @endif
            
            <!-- View Invoice Modal -->
            @if($showInvoiceModal && $selectedInvoiceForView)
            <div class="fixed inset-0 bg-black bg-opacity-50 z-[100] flex items-center justify-center p-4 overflow-y-auto" wire:click.self="closeInvoiceModal">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
                    <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center z-10">
                        <h2 class="text-2xl font-bold text-gray-900">Invoice Details</h2>
                        <button wire:click="closeInvoiceModal" type="button" class="text-gray-400 hover:text-gray-600 text-3xl font-bold">&times;</button>
                    </div>
                    
                    <div class="p-6 space-y-6">
                        <!-- Invoice Information -->
                        <div class="bg-gray-50 rounded-lg p-5 border border-gray-200">
                            <h3 class="text-lg font-semibold mb-4 text-gray-900">Invoice Information</h3>
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

                        <!-- Invoice Summary -->
                        <div class="bg-gray-50 rounded-lg p-5 border border-gray-200">
                            <h3 class="text-lg font-semibold mb-4 text-gray-900">Invoice Summary</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between py-2 border-b border-gray-200">
                                    <span class="text-gray-600">Subtotal:</span>
                                    <span class="font-semibold text-gray-900">${{ number_format($selectedInvoiceForView->subtotal ?? ($selectedInvoiceForView->total_amount - ($selectedInvoiceForView->tax_amount ?? 0)), 2) }}</span>
                                </div>
                                @if($selectedInvoiceForView->tax_amount)
                                <div class="flex justify-between py-2 border-b border-gray-200">
                                    <span class="text-gray-600">Tax (VAT):</span>
                                    <span class="font-semibold text-gray-900">${{ number_format($selectedInvoiceForView->tax_amount, 2) }}</span>
                                </div>
                                @endif
                                <div class="flex justify-between py-2 border-t-2 border-gray-300 pt-3">
                                    <span class="text-lg font-semibold text-gray-900">Total Amount:</span>
                                    <span class="text-xl font-bold text-[#EB1C24]">${{ number_format($selectedInvoiceForView->total_amount, 2) }}</span>
                                </div>
                            </div>
                        </div>
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
            <div class="fixed inset-0 bg-black bg-opacity-50 z-[100] flex items-center justify-center p-4" wire:click.self="closeShareModal">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h2 class="text-xl font-bold text-gray-900">Share Invoice</h2>
                        <button wire:click="closeShareModal" type="button" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
                    </div>
                    
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
    </div>
    
    <div wire:ignore>
        <script>
            // Handle copy to clipboard
            document.addEventListener('livewire:init', () => {
                Livewire.on('copy-to-clipboard', (event) => {
                    const text = event.text || event[0]?.text;
                    if (text) {
                        navigator.clipboard.writeText(text).then(() => {
                            alert('Link copied to clipboard!');
                        }).catch(() => {
                            const input = document.getElementById('share-link-input');
                            if (input) {
                                input.select();
                                document.execCommand('copy');
                                alert('Link copied to clipboard!');
                            }
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
            });
        </script>
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
