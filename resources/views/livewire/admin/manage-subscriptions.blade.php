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

        <!-- Tabs -->
        <div class="mb-4 border-b border-gray-200">
            <nav class="-mb-px flex space-x-8">
                <button 
                    wire:click="$set('activeTab', 'organisation')"
                    type="button"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'organisation' ? 'border-[#EB1C24] text-[#EB1C24]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    Organisation
                </button>
                <button 
                    wire:click="$set('activeTab', 'basecamp')"
                    type="button"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'basecamp' ? 'border-[#EB1C24] text-[#EB1C24]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    Basecamp
                </button>
            </nav>
        </div>

        <!-- Search -->
        <div class="mb-4">
            <input type="text" wire:model.live="search" 
                placeholder="{{ $activeTab === 'organisation' ? 'Search by organisation...' : 'Search by user name or email...' }}" 
                class="w-full max-w-md px-4 py-2 border border-gray-300 rounded-md">
        </div>

        <!-- Subscriptions Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            {{ $activeTab === 'organisation' ? 'Organisation' : 'User' }}
                        </th>
                        @if($activeTab === 'organisation')
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        @endif
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tier</th>
                        @if($activeTab === 'organisation')
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User Count</th>
                        @endif
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Monthly Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period End</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Next Billing</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($subscriptions as $item)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <div class="font-medium text-gray-900">{{ $item['name'] }}</div>
                                    @if($activeTab === 'organisation' && $item['type'] === 'organisation' && isset($item['user_count']))
                                        <div class="text-sm text-gray-500">{{ $item['user_count'] }} user(s)</div>
                                    @elseif($activeTab === 'basecamp' && $item['type'] === 'basecamp' && isset($item['user']))
                                        <div class="text-sm text-gray-500">{{ $item['user']->email ?? '' }}</div>
                                    @endif
                                </div>
                            </td>
                            @if($activeTab === 'organisation')
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                                        Organisation
                                    </span>
                                </td>
                            @endif
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($item['subscription'])
                                    <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                        {{ ucfirst($item['subscription']->tier) }}
                                    </span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            @if($activeTab === 'organisation')
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($item['subscription'])
                                        {{ $item['subscription']->user_count }}
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                            @endif
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($item['subscription'])
                                    @php
                                        $prices = ['basecamp' => 10, 'spark' => 10, 'momentum' => 20, 'vision' => 30];
                                        $pricePerUser = $prices[$item['subscription']->tier] ?? 0;
                                        $userCount = $item['type'] === 'basecamp' ? 1 : $item['subscription']->user_count;
                                        $total = $pricePerUser * $userCount;
                                    @endphp
                                    £{{ number_format($total, 2) }}
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($item['subscription'])
                                    <span class="px-2 py-1 text-xs rounded-full {{ $item['subscription']->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ ucfirst($item['subscription']->status) }}
                                    </span>
                                @else
                                    <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                                        No Subscription
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($item['subscription'] && isset($item['payment_status']))
                                    <span class="px-2 py-1 text-xs rounded-full {{ $item['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                        {{ $item['payment_status'] === 'paid' ? 'Paid' : 'Unpaid' }}
                                    </span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($item['subscription'] && $item['subscription']->current_period_end)
                                    <span class="{{ \Carbon\Carbon::parse($item['subscription']->current_period_end)->isPast() ? 'text-red-600 font-semibold' : '' }}">
                                        {{ $item['subscription']->current_period_end->format('M d, Y') }}
                                    </span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($item['subscription'] && $item['subscription']->next_billing_date)
                                    {{ $item['subscription']->next_billing_date->format('M d, Y') }}
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2 flex-wrap">
                                    @if($item['subscription'])
                                        <button 
                                            wire:click="openEditModal({{ $item['subscription']->id }})" 
                                            type="button" 
                                            class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded border border-blue-200 transition-all duration-200 cursor-pointer">
                                            Edit
                                        </button>
                                        @if($item['subscription']->status === 'active')
                                            <button 
                                                wire:click="pauseSubscription({{ $item['subscription']->id }})" 
                                                wire:confirm="Are you sure you want to pause this subscription?"
                                                type="button" 
                                                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-yellow-600 hover:text-yellow-800 hover:bg-yellow-50 rounded border border-yellow-200 transition-all duration-200 cursor-pointer">
                                                Pause
                                            </button>
                                        @elseif($item['subscription']->status === 'suspended')
                                            <button 
                                                wire:click="resumeSubscription({{ $item['subscription']->id }})" 
                                                wire:confirm="Are you sure you want to resume this subscription?"
                                                type="button" 
                                                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-green-600 hover:text-green-800 hover:bg-green-50 rounded border border-green-200 transition-all duration-200 cursor-pointer">
                                                Resume
                                            </button>
                                        @endif
                                        @if(isset($item['payment_status']) && $item['payment_status'] === 'unpaid')
                                            <button 
                                                wire:click="deleteUnpaidSubscription({{ $item['subscription']->id }})" 
                                                wire:confirm="Are you sure you want to delete this unpaid subscription? This will also delete all unpaid invoices. This action cannot be undone."
                                                type="button" 
                                                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-800 hover:bg-red-50 rounded border border-red-200 transition-all duration-200 cursor-pointer">
                                                Delete
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $activeTab === 'organisation' ? '10' : '9' }}" class="px-6 py-4 text-center text-gray-500">
                                No {{ $activeTab === 'organisation' ? 'organisations' : 'basecamp users' }} found
                            </td>
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

                <div>
                    <label class="block text-sm font-medium text-gray-700">Tier</label>
                    <select wire:model="tier" class="mt-1 block w-full border-gray-300 rounded-md">
                        <option value="spark">Spark</option>
                        <option value="momentum">Momentum</option>
                        <option value="vision">Vision</option>
                        <option value="basecamp">Basecamp</option>
                    </select>
                    @error('tier') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">User Count</label>
                    <input type="number" wire:model.live="user_count" min="1" class="mt-1 block w-full border-gray-300 rounded-md">
                    @error('user_count') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Period Start</label>
                        <input type="date" wire:model="current_period_start" class="mt-1 block w-full border-gray-300 rounded-md">
                        @error('current_period_start') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Period End</label>
                        <input type="date" wire:model="current_period_end" class="mt-1 block w-full border-gray-300 rounded-md">
                        @error('current_period_end') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Next Billing Date</label>
                    <input type="date" wire:model="next_billing_date" class="mt-1 block w-full border-gray-300 rounded-md">
                    @error('next_billing_date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <select wire:model="status" class="mt-1 block w-full border-gray-300 rounded-md">
                        <option value="active">Active</option>
                        <option value="past_due">Past Due</option>
                        <option value="suspended">Suspended</option>
                        <option value="canceled">Canceled</option>
                    </select>
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
                    @if($selectedSubscription && $selectedSubscription->tier === 'basecamp')
                        <input type="text" value="Basecamp User (Individual)" disabled class="mt-1 block w-full border-gray-300 rounded-md bg-gray-100">
                        <p class="mt-1 text-xs text-gray-500">This is a basecamp user subscription (individual user, not organisation).</p>
                    @else
                        <select wire:model="organisation_id" class="mt-1 block w-full border-gray-300 rounded-md">
                            <option value="">Select Organisation</option>
                            @foreach($organisations as $org)
                                <option value="{{ $org->id }}">{{ $org->name }}</option>
                            @endforeach
                        </select>
                        @error('organisation_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    @endif
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Tier</label>
                    <select wire:model="tier" class="mt-1 block w-full border-gray-300 rounded-md">
                        <option value="spark">Spark</option>
                        <option value="momentum">Momentum</option>
                        <option value="vision">Vision</option>
                        <option value="basecamp">Basecamp</option>
                    </select>
                    @error('tier') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">User Count</label>
                    <input type="number" wire:model.live="user_count" min="1" class="mt-1 block w-full border-gray-300 rounded-md">
                    @error('user_count') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Period Start</label>
                        <input type="date" wire:model="current_period_start" class="mt-1 block w-full border-gray-300 rounded-md">
                        @error('current_period_start') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Period End</label>
                        <input type="date" wire:model="current_period_end" class="mt-1 block w-full border-gray-300 rounded-md">
                        @error('current_period_end') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Next Billing Date</label>
                    <input type="date" wire:model="next_billing_date" class="mt-1 block w-full border-gray-300 rounded-md">
                    @error('next_billing_date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <select wire:model="status" class="mt-1 block w-full border-gray-300 rounded-md">
                        <option value="active">Active</option>
                        <option value="past_due">Past Due</option>
                        <option value="suspended">Suspended</option>
                        <option value="canceled">Canceled</option>
                    </select>
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
