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

        <!-- Search and Filters -->
        <div class="mb-4 space-y-3">
            <!-- Search -->
            <div>
                <input type="text" wire:model.live="search" 
                    placeholder="{{ $activeTab === 'organisation' ? 'Search by organisation...' : 'Search by user name or email...' }}" 
                    class="w-full max-w-md px-4 py-2 border border-gray-300 rounded-md">
            </div>
            
            <!-- Filters -->
            <div class="flex flex-wrap gap-3">
                <!-- Account Status Filter -->
                <div class="min-w-[180px] w-full sm:w-[auto]">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Account Status</label>
                    <select wire:model.live="accountStatusFilter" 
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-red-500 focus:border-red-500">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="paused">Paused</option>
                    </select>
                </div>
                
                <!-- Payment Status Filter -->
                <div class="min-w-[180px] w-full sm:w-[auto]">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Payment Status</label>
                    <select wire:model.live="paymentStatusFilter" 
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-red-500 focus:border-red-500">
                        <option value="">All Payment Status</option>
                        <option value="paid">Paid</option>
                        <option value="unpaid">Unpaid</option>
                        <option value="failed">Failed</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
                
                <!-- Next Billing Date Filter -->
                <div class="min-w-[180px] w-full sm:w-[auto]">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Next Billing Date</label>
                    <select wire:model.live="nextBillingDateFilter" 
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-red-500 focus:border-red-500">
                        <option value="">All Dates</option>
                        <option value="today">Today</option>
                        <option value="next_7_days">Next 7 days</option>
                        <option value="next_30_days">Next 30 days</option>
                        <option value="overdue">Overdue</option>
                    </select>
                </div>
                
                <!-- Clear Filters Button -->
                @if($accountStatusFilter || $paymentStatusFilter || $nextBillingDateFilter)
                    <div class="flex items-end">
                        <button wire:click="clearFilters" 
                            type="button"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                            Clear Filters
                        </button>
                    </div>
                @endif
            </div> 
        </div>

        <!-- Subscriptions Table -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto grid">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                <button wire:click="sortBy('name')" class="flex items-center space-x-2 hover:text-[#EB1C24] transition-colors duration-200 group">
                                    <span class="font-semibold">{{ $activeTab === 'organisation' ? 'Organisation' : 'User' }}</span>
                                    @if($sortField === 'name')
                                        <span class="text-[#EB1C24] font-bold">
                                            {{ $sortDirection === 'asc' ? '↑' : '↓' }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 group-hover:text-gray-600">⇅</span>
                                    @endif
                                </button>
                            </th>
                            @if($activeTab === 'organisation')
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Type</th>
                            @endif
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                <button wire:click="sortBy('tier')" class="flex items-center space-x-2 hover:text-[#EB1C24] transition-colors duration-200 group">
                                    <span class="font-semibold">Tier</span>
                                    @if($sortField === 'tier')
                                        <span class="text-[#EB1C24] font-bold">
                                            {{ $sortDirection === 'asc' ? '↑' : '↓' }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 group-hover:text-gray-600">⇅</span>
                                    @endif
                                </button>
                            </th>
                            @if($activeTab === 'organisation')
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">User Count</th>
                            @endif
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Monthly Total</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                <button wire:click="sortBy('status')" class="flex items-center space-x-2 hover:text-[#EB1C24] transition-colors duration-200 group">
                                    <span class="font-semibold">Status</span>
                                    @if($sortField === 'status')
                                        <span class="text-[#EB1C24] font-bold">
                                            {{ $sortDirection === 'asc' ? '↑' : '↓' }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 group-hover:text-gray-600">⇅</span>
                                    @endif
                                </button>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                <button wire:click="sortBy('payment_status')" class="flex items-center space-x-2 hover:text-[#EB1C24] transition-colors duration-200 group">
                                    <span class="font-semibold">Payment Status</span>
                                    @if($sortField === 'payment_status')
                                        <span class="text-[#EB1C24] font-bold">
                                            {{ $sortDirection === 'asc' ? '↑' : '↓' }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 group-hover:text-gray-600">⇅</span>
                                    @endif
                                </button>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                <button wire:click="sortBy('current_period_end')" class="flex items-center space-x-2 hover:text-[#EB1C24] transition-colors duration-200 group">
                                    <span class="font-semibold">Period End</span>
                                    @if($sortField === 'current_period_end')
                                        <span class="text-[#EB1C24] font-bold">
                                            {{ $sortDirection === 'asc' ? '↑' : '↓' }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 group-hover:text-gray-600">⇅</span>
                                    @endif
                                </button>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                <button wire:click="sortBy('next_billing_date')" class="flex items-center space-x-2 hover:text-[#EB1C24] transition-colors duration-200 group">
                                    <span class="font-semibold">Next Billing</span>
                                    @if($sortField === 'next_billing_date')
                                        <span class="text-[#EB1C24] font-bold">
                                            {{ $sortDirection === 'asc' ? '↑' : '↓' }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 group-hover:text-gray-600">⇅</span>
                                    @endif
                                </button>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @forelse($subscriptions as $item)
                            <tr class="hover:bg-gray-50 transition-colors duration-150 border-b border-gray-100">
                                <td class="px-6 py-5 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <div class="font-semibold text-gray-900 text-sm mb-1">{{ $item['name'] }}</div>
                                        @if($activeTab === 'basecamp' && isset($item['email']))
                                            <div class="text-xs text-gray-500 font-normal">{{ $item['email'] }}</div>
                                        @elseif($activeTab === 'organisation' && $item['type'] === 'organisation' && isset($item['user_count']))
                                            <div class="text-xs text-gray-500 font-normal">{{ $item['user_count'] }} user(s)</div>
                                        @endif
                                    </div>
                                </td>
                                @if($activeTab === 'organisation')
                                    <td class="px-6 py-5 whitespace-nowrap">
                                        <span class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full bg-gray-100 text-gray-700 shadow-sm">
                                            Organisation
                                        </span>
                                    </td>
                                @endif
                                <td class="px-6 py-5 whitespace-nowrap">
                                    @if($item['subscription'])
                                        <span class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-full bg-blue-50 text-blue-700 border border-blue-200 shadow-sm">
                                            {{ ucfirst($item['subscription']->tier) }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 text-sm">-</span>
                                    @endif
                                </td>
                                @if($activeTab === 'organisation')
                                    <td class="px-6 py-5 whitespace-nowrap">
                                        @if($item['subscription'])
                                            @php
                                                $actualCount = $item['user_count'] ?? 0;
                                                $storedCount = $item['subscription']->user_count ?? 0;
                                                $countMismatch = $actualCount != $storedCount;
                                            @endphp
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-full border shadow-sm {{ $countMismatch ? 'bg-yellow-50 text-yellow-700 border-yellow-200' : 'bg-red-50 text-red-700 border-red-200' }}" 
                                                      title="{{ $countMismatch ? 'Mismatch: Actual users: ' . $actualCount . ', Stored: ' . $storedCount : 'User count: ' . $actualCount }}">
                                                    {{ $actualCount }}
                                                </span>
                                                @if($countMismatch)
                                                    <button wire:click="syncUserCount({{ $item['subscription']->id }})" 
                                                            class="text-xs font-medium text-blue-600 hover:text-blue-800 hover:underline transition-colors"
                                                            title="Sync subscription user count with actual user count">
                                                        Sync
                                                    </button>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-gray-400 text-sm">-</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="px-6 py-5 whitespace-nowrap">
                                    @if($item['subscription'])
                                        @php
                                            $prices = ['basecamp' => 10, 'spark' => 10, 'momentum' => 20, 'vision' => 30];
                                            $pricePerUser = $prices[$item['subscription']->tier] ?? 0;
                                            // Use actual user count instead of stored subscription user_count
                                            $userCount = $item['type'] === 'basecamp' ? 1 : ($item['user_count'] ?? $item['subscription']->user_count);
                                            $total = $pricePerUser * $userCount;
                                        @endphp
                                        <span class="text-sm font-semibold text-gray-900">£{{ number_format($total, 2) }}</span>
                                    @else
                                        <span class="text-gray-400 text-sm">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-5 whitespace-nowrap">
                                    @if($item['subscription'])
                                        @php
                                            $statusClass = '';
                                            $statusText = ucfirst($item['subscription']->status);
                                            if ($item['subscription']->status === 'active') {
                                                $statusClass = 'bg-green-50 text-green-700 border-green-200';
                                            } elseif (in_array($item['subscription']->status, ['suspended', 'inactive', 'canceled'])) {
                                                $statusClass = 'bg-red-50 text-red-700 border-red-200';
                                            } else {
                                                $statusClass = 'bg-gray-50 text-gray-700 border-gray-200';
                                            }
                                        @endphp
                                        <span class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-full border shadow-sm {{ $statusClass }}">
                                            {{ $statusText }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-full bg-gray-50 text-gray-700 border border-gray-200 shadow-sm">
                                            No Subscription
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-5 whitespace-nowrap">
                                    @if($item['subscription'] && isset($item['payment_status']))
                                        <span class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-full border shadow-sm {{ $item['payment_status'] === 'paid' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-yellow-50 text-yellow-700 border-yellow-200' }}">
                                            {{ $item['payment_status'] === 'paid' ? 'Paid' : 'Unpaid' }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 text-sm">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-5 whitespace-nowrap">
                                    @if($item['subscription'] && $item['subscription']->current_period_end)
                                        <span class="text-sm font-medium {{ \Carbon\Carbon::parse($item['subscription']->current_period_end)->isPast() ? 'text-red-600 font-semibold' : 'text-gray-700' }}">
                                            {{ $item['subscription']->current_period_end->format('M d, Y') }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 text-sm">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-5 whitespace-nowrap">
                                    @if($item['subscription'] && $item['subscription']->next_billing_date)
                                        <span class="text-sm font-medium text-gray-700">
                                            {{ $item['subscription']->next_billing_date->format('M d, Y') }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 text-sm">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-5 whitespace-nowrap">
                                    <div class="flex items-center gap-1.5">
                                        @if($item['subscription'])
                                            <button 
                                                wire:click="openEditModal({{ $item['subscription']->id }})" 
                                                type="button" 
                                                class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:text-white hover:bg-blue-600 rounded-md border border-blue-300 bg-blue-50 shadow-sm transition-all duration-200 cursor-pointer group"
                                                title="Edit Subscription">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </button>
                                            @if($item['subscription']->status === 'active')
                                                <button 
                                                    wire:click="pauseSubscription({{ $item['subscription']->id }})" 
                                                    wire:confirm="Are you sure you want to pause this subscription?"
                                                    type="button" 
                                                    class="inline-flex items-center justify-center w-8 h-8 text-yellow-600 hover:text-white hover:bg-yellow-600 rounded-md border border-yellow-300 bg-yellow-50 shadow-sm transition-all duration-200 cursor-pointer group"
                                                    title="Pause Subscription">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                </button>
                                            @elseif($item['subscription']->status === 'suspended')
                                                <button 
                                                    wire:click="resumeSubscription({{ $item['subscription']->id }})" 
                                                    wire:confirm="Are you sure you want to resume this subscription?"
                                                    type="button" 
                                                    class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-white hover:bg-green-600 rounded-md border border-green-300 bg-green-50 shadow-sm transition-all duration-200 cursor-pointer group"
                                                    title="Resume Subscription">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                </button>
                                            @endif
                                            @if(isset($item['payment_status']) && $item['payment_status'] === 'unpaid')
                                                <button 
                                                    wire:click="deleteUnpaidSubscription({{ $item['subscription']->id }})" 
                                                    wire:confirm="Are you sure you want to delete this unpaid subscription? This will also delete all unpaid invoices. This action cannot be undone."
                                                    type="button" 
                                                    class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-white hover:bg-red-600 rounded-md border border-red-300 bg-red-50 shadow-sm transition-all duration-200 cursor-pointer group"
                                                    title="Delete Subscription">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                        </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $activeTab === 'organisation' ? '10' : '9' }}" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="w-16 h-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                        </svg>
                                        <p class="text-gray-500 text-sm font-medium">No {{ $activeTab === 'organisation' ? 'organisations' : 'basecamp users' }} found</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($subscriptions->hasPages())
            <div class="mt-6">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Showing {{ $subscriptions->firstItem() ?? 0 }} to {{ $subscriptions->lastItem() ?? 0 }} of {{ $subscriptions->total() }} results
                    </div>
                    <div>
                        {{ $subscriptions->links() }}
                    </div>
                </div>
            </div>
        @endif
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
                        <option value="inactive">Inactive</option>
                        <option value="past_due">Past Due</option>
                        <option value="suspended">Suspended</option>
                        <option value="canceled">Canceled</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Payment Status</label>
                    <select wire:model="payment_status" class="mt-1 block w-full border-gray-300 rounded-md">
                        <option value="paid">Paid</option>
                        <option value="unpaid">Unpaid</option>
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
<div x-data="{ show: @entangle('showEditModal') }" x-show="show" x-cloak style="display: none;" class="fixed inset-0 bg-black bg-opacity-60 z-[9999] flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-2xl font-bold text-gray-900">Edit Subscription</h2>
            <button wire:click="closeModal" type="button" class="text-gray-400 hover:text-gray-600 hover:bg-gray-200 rounded-full p-2 transition-colors duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="flex-1 overflow-y-auto px-6 py-5">
            <form wire:submit.prevent="updateSubscription">
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Organisation</label>
                        @if($selectedSubscription && $selectedSubscription->tier === 'basecamp')
                            <input type="text" value="Basecamp User (Individual)" disabled class="block w-full px-4 py-2.5 border border-gray-300 rounded-lg bg-gray-50 text-gray-600 cursor-not-allowed">
                            <p class="mt-2 text-xs text-gray-500 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                This is a basecamp user subscription (individual user, not organisation).
                            </p>
                        @else
                            <select wire:model="organisation_id" class="block w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all duration-200">
                                <option value="">Select Organisation</option>
                                @foreach($organisations as $org)
                                    <option value="{{ $org->id }}">{{ $org->name }}</option>
                                @endforeach
                            </select>
                            @error('organisation_id') 
                                <span class="text-red-500 text-xs mt-1 block flex items-center">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                    {{ $message }}
                                </span> 
                            @enderror
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Tier</label>
                        <select wire:model="tier" class="block w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all duration-200">
                            <option value="spark">Spark</option>
                            <option value="momentum">Momentum</option>
                            <option value="vision">Vision</option>
                            <option value="basecamp">Basecamp</option>
                        </select>
                        @error('tier') 
                            <span class="text-red-500 text-xs mt-1 block flex items-center">
                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                </svg>
                                {{ $message }}
                            </span> 
                        @enderror
                    </div>

                    @if(!$selectedSubscription || $selectedSubscription->tier !== 'basecamp')
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">User Count</label>
                            <input type="number" wire:model.live="user_count" min="1" class="block w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all duration-200">
                            @error('user_count') 
                                <span class="text-red-500 text-xs mt-1 block flex items-center">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                    {{ $message }}
                                </span> 
                            @enderror
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Period Start <span class="text-red-500">*</span>
                            </label>
                            <input type="date" 
                                   wire:model.live.debounce.300ms="current_period_start"
                                   min="{{ date('Y-m-d') }}"
                                   class="block w-full px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all duration-200 @error('current_period_start') border-red-500 @enderror">
                            @error('current_period_start') 
                                <span class="text-red-500 text-xs mt-1 block flex items-center">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                    {{ $message }}
                                </span> 
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Period End <span class="text-red-500">*</span>
                            </label>
                            <input type="date" 
                                   wire:model.live.debounce.300ms="current_period_end"
                                   @if($current_period_start) 
                                       min="{{ date('Y-m-d', strtotime($current_period_start . ' +1 day')) }}" 
                                   @else
                                       min="{{ date('Y-m-d', strtotime('+1 day')) }}"
                                   @endif
                                   class="block w-full px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all duration-200 @error('current_period_end') border-red-500 @enderror">
                            @error('current_period_end') 
                                <span class="text-red-500 text-xs mt-1 block flex items-center">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                    {{ $message }}
                                </span> 
                            @enderror
                            <p class="text-xs text-gray-500 mt-1.5 flex items-center">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Must be after Period Start
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Next Billing Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" 
                               wire:model="next_billing_date"
                               disabled
                               readonly
                               @if($current_period_start) 
                                   min="{{ $current_period_start }}" 
                               @else
                                   min="{{ date('Y-m-d') }}"
                               @endif
                               class="block w-full px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm bg-gray-50 cursor-not-allowed text-gray-600 @error('next_billing_date') border-red-500 @enderror">
                        @error('next_billing_date') 
                            <span class="text-red-500 text-xs mt-1 block flex items-center">
                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                </svg>
                                {{ $message }}
                            </span> 
                        @enderror
                        <p class="text-xs text-gray-500 mt-1.5 flex items-center">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Auto-filled from Period End (cannot be changed)
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                            <select wire:model="status" class="block w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all duration-200">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="past_due">Past Due</option>
                                <option value="suspended">Suspended</option>
                                <option value="canceled">Canceled</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Status</label>
                            <select wire:model="payment_status" class="block w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all duration-200">
                                <option value="paid">Paid</option>
                                <option value="unpaid">Unpaid</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="mt-6 pt-5 border-t border-gray-200 flex justify-end space-x-3">
                    <button type="button" wire:click="closeModal" class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-[#EB1C24] text-white rounded-lg font-semibold hover:bg-[#d11820] shadow-sm transition-all duration-200">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
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
            
            // Re-initialize date inputs after Livewire updates
            setTimeout(() => {
                const dateInputs = document.querySelectorAll('input[type="date"]');
                dateInputs.forEach(input => {
                    // Force calendar to show properly by triggering focus/blur
                    if (input.value) {
                        input.setAttribute('value', input.value);
                    }
                });
            }, 100);
        });
        
        Livewire.hook('message.failed', (message, component) => {
            console.error('❌ Livewire message failed:', message);
        });
        
        Livewire.hook('morph.updated', ({ el, component }) => {
            console.log('✅ Livewire DOM updated');
            
            // Ensure date inputs are properly initialized after DOM update
            setTimeout(() => {
                const dateInputs = el.querySelectorAll ? el.querySelectorAll('input[type="date"]') : [];
                dateInputs.forEach(input => {
                    if (input.value) {
                        input.setAttribute('value', input.value);
                    }
                });
            }, 50);
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
    
    // Fix date input calendar on modal open
    document.addEventListener('livewire:init', () => {
        Livewire.on('modal-opened', (data) => {
            setTimeout(() => {
                const dateInputs = document.querySelectorAll('input[type="date"]');
                dateInputs.forEach(input => {
                    if (input.value) {
                        input.setAttribute('value', input.value);
                        // Trigger a small change to refresh the calendar
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                });
            }, 200);
        });
    });
});
</script>
</div>
