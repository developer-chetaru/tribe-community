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
                                          const endDate = new Date('{{ $subscriptionStatus['end_date'] ?? $subscription->end_date }}');
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
                            <p class="text-sm text-gray-600">Price per User</p>
                            <p class="text-lg font-semibold">${{ number_format($subscription->price_per_user, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Monthly Total</p>
                            <p class="text-lg font-semibold">${{ number_format($subscription->total_amount, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Subscription End</p>
                            <p class="text-lg font-semibold {{ $daysRemaining <= 7 ? 'text-red-600' : '' }}">
                                {{ $subscription->end_date ? $subscription->end_date->format('M d, Y') : 'N/A' }}
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
                                        <a href="{{ route('invoices.view', $invoice->id) }}" 
                                           target="_blank" 
                                           class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded border border-blue-200 transition-all duration-200">
                                            View Invoice
                                        </a>
                                        <a href="{{ route('invoices.download', $invoice->id) }}" 
                                           download 
                                           class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-green-600 hover:text-green-800 hover:bg-green-50 rounded border border-green-200 transition-all duration-200">
                                            Download
                                        </a>
                                        @if($invoice->status === 'pending')
                                            <button wire:click="openPaymentModal({{ $invoice->id }})" 
                                                    type="button" 
                                                    class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-[#EB1C24] hover:text-red-800 hover:bg-red-50 rounded border border-red-200 transition-all duration-200 cursor-pointer">
                                                Pay
                                            </button>
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

    <!-- Payment Modal -->
    @if($selectedInvoice)
    <div x-data="{ show: @entangle('showPaymentModal') }" 
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

            <form wire:submit.prevent="submitPayment">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                        <select wire:model="payment_method" class="mt-1 block w-full border-gray-300 rounded-md focus:ring-[#EB1C24] focus:border-[#EB1C24]">
                            <option value="card">Credit/Debit Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="paypal">PayPal</option>
                        </select>
                        @error('payment_method') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <p class="text-blue-800 text-sm">
                            <strong>Note:</strong> Payment will be processed automatically. Your subscription will be activated immediately after successful payment.
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-2">
                    <button type="button" wire:click="closePaymentModal" class="px-4 py-2 border rounded-md hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-[#EB1C24] text-white rounded-md hover:bg-red-700">
                        <span wire:loading.remove wire:target="submitPayment">Pay ${{ number_format($selectedInvoice->total_amount, 2) }}</span>
                        <span wire:loading wire:target="submitPayment">Processing...</span>
                    </button>
                </div>
            </form>
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
        <div class="fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-[10001]">
            {{ session('success') }}
        </div>
    @endif
</div>

