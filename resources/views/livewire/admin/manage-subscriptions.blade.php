<x-slot name="header">
    <h2 class="text-[24px] md:text-[30px] font-semibold capitalize text-[#EB1C24]">
        Manage Subscriptions
    </h2>
</x-slot>

<div>
<div class="flex-1 overflow-auto">
    <div class="max-w-8xl mx-auto p-4">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-[#EB1C24] font-medium text-[24px]">Subscriptions</h2>
        </div>

        <!-- Search -->
        <div class="mb-4">
            <input type="text" wire:model.live="search" placeholder="Search by organisation..." 
                class="w-full max-w-md px-4 py-2 border border-gray-300 rounded-md">
        </div>

        <!-- Subscriptions Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Organisation</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User Count</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price/User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">End Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Next Billing</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($subscriptions as $organisation)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <div class="font-medium text-gray-900">{{ $organisation->name }}</div>
                                    @if($organisation->users()->count() > 0)
                                        <div class="text-sm text-gray-500">{{ $organisation->users()->count() }} user(s)</div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($organisation->subscription)
                                    {{ $organisation->subscription->user_count }}
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($organisation->subscription)
                                    ${{ number_format($organisation->subscription->price_per_user, 2) }}
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($organisation->subscription)
                                    ${{ number_format($organisation->subscription->total_amount, 2) }}
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($organisation->subscription)
                                    <span class="px-2 py-1 text-xs rounded-full {{ $organisation->subscription->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ ucfirst($organisation->subscription->status) }}
                                    </span>
                                @else
                                    <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                                        No Subscription
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($organisation->subscription && $organisation->subscription->end_date)
                                    <span class="{{ \Carbon\Carbon::parse($organisation->subscription->end_date)->isPast() ? 'text-red-600 font-semibold' : '' }}">
                                        {{ $organisation->subscription->end_date->format('M d, Y') }}
                                    </span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($organisation->subscription && $organisation->subscription->next_billing_date)
                                    {{ $organisation->subscription->next_billing_date->format('M d, Y') }}
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2 flex-wrap">
                                    @if($organisation->subscription)
                                        <button 
                                            wire:click="openEditModal({{ $organisation->subscription->id }})" 
                                            type="button" 
                                            class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded border border-blue-200 transition-all duration-200 cursor-pointer">
                                            Edit
                                        </button>
                                        @if($organisation->subscription->status === 'active')
                                            <button 
                                                wire:click="pauseSubscription({{ $organisation->subscription->id }})" 
                                                wire:confirm="Are you sure you want to pause this subscription?"
                                                type="button" 
                                                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-yellow-600 hover:text-yellow-800 hover:bg-yellow-50 rounded border border-yellow-200 transition-all duration-200 cursor-pointer">
                                                Pause
                                            </button>
                                        @elseif($organisation->subscription->status === 'suspended')
                                            <button 
                                                wire:click="resumeSubscription({{ $organisation->subscription->id }})" 
                                                wire:confirm="Are you sure you want to resume this subscription?"
                                                type="button" 
                                                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-green-600 hover:text-green-800 hover:bg-green-50 rounded border border-green-200 transition-all duration-200 cursor-pointer">
                                                Resume
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">No organisations found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $subscriptions->links() }}
        </div>
    </div>
</div>

<!-- Create Modal -->
<div x-data="{ show: @entangle('showCreateModal') }" x-show="show" x-cloak style="display: none;" class="fixed inset-0 bg-black bg-opacity-50 z-[9999] flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl m-4">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Create Subscription</h2>
            <button wire:click="closeModal" type="button" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
        </div>
        
        <form wire:submit.prevent="createSubscription">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Organisation</label>
                    <select wire:model="organisation_id" class="mt-1 block w-full border-gray-300 rounded-md">
                        <option value="">Select Organisation</option>
                        @foreach($organisations as $org)
                            <option value="{{ $org->id }}">{{ $org->name }}</option>
                        @endforeach
                    </select>
                    @error('organisation_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">User Count</label>
                        <input type="number" wire:model.live="user_count" min="1" class="mt-1 block w-full border-gray-300 rounded-md">
                        @error('user_count') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Price Per User ($)</label>
                        <input type="number" wire:model.live="price_per_user" step="0.01" min="0" class="mt-1 block w-full border-gray-300 rounded-md">
                        @error('price_per_user') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Total Amount ($)</label>
                    <input type="text" value="${{ number_format($total_amount, 2) }}" readonly class="mt-1 block w-full border-gray-300 rounded-md bg-gray-100">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Start Date</label>
                        <input type="date" wire:model="start_date" class="mt-1 block w-full border-gray-300 rounded-md">
                        @error('start_date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Next Billing Date</label>
                        <input type="date" wire:model="next_billing_date" class="mt-1 block w-full border-gray-300 rounded-md">
                        @error('next_billing_date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Billing Cycle</label>
                        <select wire:model="billing_cycle" class="mt-1 block w-full border-gray-300 rounded-md">
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select wire:model="status" class="mt-1 block w-full border-gray-300 rounded-md">
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea wire:model="notes" rows="3" class="mt-1 block w-full border-gray-300 rounded-md"></textarea>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-2">
                <button type="button" wire:click="closeModal" class="px-4 py-2 border rounded-md">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-[#EB1C24] text-white rounded-md">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div x-data="{ show: @entangle('showEditModal') }" x-show="show" x-cloak style="display: none;" class="fixed inset-0 bg-black bg-opacity-50 z-[9999] flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl m-4">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Edit Subscription</h2>
            <button wire:click="closeModal" type="button" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
        </div>
        
        <form wire:submit.prevent="updateSubscription">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Organisation</label>
                    <select wire:model="organisation_id" class="mt-1 block w-full border-gray-300 rounded-md">
                        <option value="">Select Organisation</option>
                        @foreach($organisations as $org)
                            <option value="{{ $org->id }}">{{ $org->name }}</option>
                        @endforeach
                    </select>
                    @error('organisation_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">User Count</label>
                        <input type="number" wire:model.live="user_count" min="1" class="mt-1 block w-full border-gray-300 rounded-md">
                        @error('user_count') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Price Per User ($)</label>
                        <input type="number" wire:model.live="price_per_user" step="0.01" min="0" class="mt-1 block w-full border-gray-300 rounded-md">
                        @error('price_per_user') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Total Amount ($)</label>
                    <input type="text" value="${{ number_format($total_amount, 2) }}" readonly class="mt-1 block w-full border-gray-300 rounded-md bg-gray-100">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Start Date</label>
                        <input type="date" wire:model="start_date" class="mt-1 block w-full border-gray-300 rounded-md">
                        @error('start_date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Next Billing Date</label>
                        <input type="date" wire:model="next_billing_date" class="mt-1 block w-full border-gray-300 rounded-md">
                        @error('next_billing_date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Billing Cycle</label>
                        <select wire:model="billing_cycle" class="mt-1 block w-full border-gray-300 rounded-md">
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select wire:model="status" class="mt-1 block w-full border-gray-300 rounded-md">
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea wire:model="notes" rows="3" class="mt-1 block w-full border-gray-300 rounded-md"></textarea>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-2">
                <button type="button" wire:click="closeModal" class="px-4 py-2 border rounded-md">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-[#EB1C24] text-white rounded-md">Save</button>
            </div>
        </form>
    </div>
</div>


@if(session()->has('success'))
    <div class="fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-[10000]">
        {{ session('success') }}
    </div>
@endif

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Debug: Check if Livewire is loaded
    if (typeof Livewire === 'undefined') {
        console.error('❌ Livewire is not loaded!');
    } else {
        console.log('✅ Livewire is loaded');
        
        // Listen for Livewire events
        Livewire.hook('message.processed', (message, component) => {
            console.log('✅ Livewire message processed:', message);
        });
        
        Livewire.hook('message.failed', (message, component) => {
            console.error('❌ Livewire message failed:', message);
        });
        
        Livewire.hook('morph.updated', ({ el, component }) => {
            console.log('✅ Livewire DOM updated');
        });
    }
    
    // Monitor network requests
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
        if (args[0] && args[0].includes('livewire')) {
            console.log('🌐 Livewire request:', args[0]);
        }
        return originalFetch.apply(this, args);
    };
});
</script>
</div>
