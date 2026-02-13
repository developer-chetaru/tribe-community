<x-slot name="header">
    <h2 class="text-2xl font-bold capitalize text-[#ff2323]">Activity Log</h2>
</x-slot>

<div>
@hasanyrole('super_admin')

<main class="p-6 flex-1 overflow-y-auto min-h-screen bg-gray-50">
    
    <!-- Filters Section -->
    <div class="bg-white shadow-sm rounded-lg p-6 mb-6 border border-[#E5E5E5]">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Filters</h3>
            <button wire:click="resetFilters" 
                    class="text-sm text-gray-600 hover:text-gray-800 underline">
                Reset All Filters
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- Module Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Module</label>
                <select wire:model.live="selectedModule" 
                        class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                    <option value="">All Modules</option>
                    @foreach($modules as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Action Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Action</label>
                <select wire:model.live="selectedAction" 
                        class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                    <option value="">All Actions</option>
                    @foreach($actions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <!-- User Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">User</label>
                <select wire:model.live="selectedUserId" 
                        class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                    <option value="">All Users</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->first_name }} {{ $user->last_name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
            </div>

            <!-- Search -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                <input type="text" 
                       wire:model.live.debounce.300ms="search" 
                       placeholder="Search description, user name, or email..."
                       class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
            </div>

            <!-- Date From -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                <input type="date" 
                       wire:model.live="dateFrom" 
                       class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
            </div>

            <!-- Date To -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                <input type="date" 
                       wire:model.live="dateTo" 
                       class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
            </div>
        </div>
    </div>

    <!-- Activity Logs Table -->
    <div class="bg-white shadow-sm rounded-lg border border-[#E5E5E5] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-[#F8F9FA]">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Date & Time</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Module</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Action</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">User</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">IP Address</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Details</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($activityLogs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ $log->created_at->format('d M Y, h:i A') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                    {{ $modules[$log->module] ?? $log->module }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                    {{ $actions[$log->action] ?? $log->action }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <div>
                                    <div class="font-medium">{{ $log->user_name ?? 'System' }}</div>
                                    @if($log->user_email)
                                        <div class="text-xs text-gray-500">{{ $log->user_email }}</div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ $log->description }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $log->ip_address ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($log->old_values || $log->new_values)
                                    <button 
                                        onclick="showDetails({{ $log->id }})"
                                        class="text-blue-600 hover:text-blue-800 underline">
                                        View Details
                                    </button>
                                    
                                    <!-- Details Modal -->
                                    <div id="details-{{ $log->id }}" 
                                         class="hidden fixed inset-0 z-50 overflow-y-auto">
                                        <div class="flex items-center justify-center min-h-screen px-4">
                                            <div class="fixed inset-0 bg-black opacity-50" onclick="closeDetails({{ $log->id }})"></div>
                                            <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full p-6">
                                                <div class="flex justify-between items-center mb-4">
                                                    <h3 class="text-lg font-semibold text-gray-800">Activity Details</h3>
                                                    <button onclick="closeDetails({{ $log->id }})" 
                                                            class="text-gray-400 hover:text-gray-600">
                                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                        </svg>
                                                    </button>
                                                </div>
                                                
                                                <div class="space-y-4">
                                                    <div>
                                                        <h4 class="font-medium text-gray-700 mb-2">Description</h4>
                                                        <p class="text-sm text-gray-600">{{ $log->description }}</p>
                                                    </div>
                                                    
                                                    @if($log->old_values)
                                                        <div>
                                                            <h4 class="font-medium text-red-700 mb-2">Old Values</h4>
                                                            <pre class="bg-red-50 p-3 rounded text-xs overflow-auto">{{ json_encode($log->old_values, JSON_PRETTY_PRINT) }}</pre>
                                                        </div>
                                                    @endif
                                                    
                                                    @if($log->new_values)
                                                        <div>
                                                            <h4 class="font-medium text-green-700 mb-2">New Values</h4>
                                                            <pre class="bg-green-50 p-3 rounded text-xs overflow-auto">{{ json_encode($log->new_values, JSON_PRETTY_PRINT) }}</pre>
                                                        </div>
                                                    @endif
                                                    
                                                    @if($log->user_agent)
                                                        <div>
                                                            <h4 class="font-medium text-gray-700 mb-2">User Agent</h4>
                                                            <p class="text-sm text-gray-600">{{ $log->user_agent }}</p>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                No activity logs found matching your filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $activityLogs->links() }}
        </div>
    </div>

</main>

@else
<div class="p-6">
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        <p class="font-bold">Access Denied</p>
        <p>You do not have permission to view activity logs.</p>
    </div>
</div>
@endhasanyrole
</div>

<script>
    function showDetails(logId) {
        document.getElementById('details-' + logId).classList.remove('hidden');
    }

    function closeDetails(logId) {
        document.getElementById('details-' + logId).classList.add('hidden');
    }
</script>
