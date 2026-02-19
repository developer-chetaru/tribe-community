<x-slot name="header">
    <h2 class="text-2xl font-bold capitalize text-[#ff2323]">Login Sessions</h2>
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
            <!-- Platform Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Platform</label>
                <select wire:model.live="selectedPlatform" 
                        class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                    <option value="">All Platforms</option>
                    @foreach($platforms as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Device Type Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Device Type</label>
                <select wire:model.live="selectedDeviceType" 
                        class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                    <option value="">All Device Types</option>
                    @foreach($deviceTypes as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Status Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select wire:model.live="selectedStatus" 
                        class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $key => $label)
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
                       placeholder="Search user, device, IP, location..."
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

    <!-- Login Sessions Table -->
    <div class="bg-white shadow-sm rounded-lg border border-[#E5E5E5] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-[#F8F9FA]">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Login Time</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">User</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Platform</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Device</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">OS / Browser</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Location</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Session Duration</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">IP Address</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($loginSessions as $session)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $session->login_at->format('d M Y, h:i A') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $session->user->first_name ?? '' }} {{ $session->user->last_name ?? '' }}
                                </div>
                                <div class="text-sm text-gray-500">{{ $session->user->email ?? 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    {{ $session->platform === 'mobile' ? 'bg-blue-100 text-blue-800' : 
                                       ($session->platform === 'web' ? 'bg-green-100 text-green-800' : 'bg-purple-100 text-purple-800') }}">
                                    {{ ucfirst($session->platform) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div>{{ $session->device_name ?? $session->device_type ?? 'N/A' }}</div>
                                @if($session->device_id)
                                    <div class="text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($session->device_id, 20) }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                @if($session->os_name)
                                    <div>{{ $session->os_name }} @if($session->os_version){{ $session->os_version }}@endif</div>
                                @endif
                                @if($session->browser_name)
                                    <div class="text-xs text-gray-500">{{ $session->browser_name }} @if($session->browser_version){{ $session->browser_version }}@endif</div>
                                @endif
                                @if(!$session->os_name && !$session->browser_name)
                                    <span class="text-gray-400">N/A</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                @if($session->city || $session->country)
                                    <div>{{ $session->city ?? 'N/A' }}{{ $session->city && $session->country ? ', ' : '' }}{{ $session->country ?? '' }}</div>
                                @else
                                    <span class="text-gray-400">N/A</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                @if($session->logout_at)
                                    <div class="font-medium text-gray-900">{{ $session->formatted_duration }}</div>
                                    <div class="text-xs text-gray-500">Logout: {{ $session->logout_at->format('d M Y, h:i A') }}</div>
                                @elseif($session->status === 'active')
                                    <div class="font-medium text-green-600">{{ $session->formatted_duration }}</div>
                                    <div class="text-xs text-gray-500">Still active</div>
                                @else
                                    <span class="text-gray-400">N/A</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    {{ $session->status === 'active' ? 'bg-green-100 text-green-800' : 
                                       ($session->status === 'logged_out' ? 'bg-gray-100 text-gray-800' : 'bg-yellow-100 text-yellow-800') }}">
                                    {{ ucfirst(str_replace('_', ' ', $session->status)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $session->ip_address ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <button 
                                    onclick="showSessionDetails({{ $session->id }})"
                                    class="text-blue-600 hover:text-blue-800 underline font-medium">
                                    View Details
                                </button>
                                
                                <!-- Session Details Modal -->
                                <div id="session-details-{{ $session->id }}" 
                                     class="hidden fixed inset-0 z-50 overflow-y-auto">
                                    <div class="flex items-center justify-center min-h-screen px-4">
                                        <div class="fixed inset-0 bg-black opacity-50" onclick="closeSessionDetails({{ $session->id }})"></div>
                                        <div class="relative bg-white rounded-lg shadow-xl max-w-3xl w-full p-6 max-h-[90vh] overflow-y-auto">
                                            <div class="flex justify-between items-center mb-4 border-b pb-3">
                                                <h3 class="text-xl font-semibold text-gray-800">Session Details</h3>
                                                <button onclick="closeSessionDetails({{ $session->id }})" 
                                                        class="text-gray-400 hover:text-gray-600">
                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                            
                                            <div class="space-y-4">
                                                <!-- User Information -->
                                                <div class="bg-gray-50 p-4 rounded-lg">
                                                    <h4 class="font-semibold text-gray-700 mb-3">User Information</h4>
                                                    <div class="grid grid-cols-2 gap-3 text-sm">
                                                        <div>
                                                            <span class="text-gray-600">Name:</span>
                                                            <span class="ml-2 font-medium">{{ $session->user->first_name ?? '' }} {{ $session->user->last_name ?? '' }}</span>
                                                        </div>
                                                        <div>
                                                            <span class="text-gray-600">Email:</span>
                                                            <span class="ml-2 font-medium">{{ $session->user->email ?? 'N/A' }}</span>
                                                        </div>
                                                        <div>
                                                            <span class="text-gray-600">User ID:</span>
                                                            <span class="ml-2 font-medium">#{{ $session->user_id }}</span>
                                                        </div>
                                                        <div>
                                                            <span class="text-gray-600">Role:</span>
                                                            <span class="ml-2 font-medium">{{ $session->user->getRoleNames()->first() ?? 'N/A' }}</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Session Timing -->
                                                <div class="bg-gray-50 p-4 rounded-lg">
                                                    <h4 class="font-semibold text-gray-700 mb-3">Session Timing</h4>
                                                    <div class="grid grid-cols-2 gap-3 text-sm">
                                                        <div>
                                                            <span class="text-gray-600">Login Time:</span>
                                                            <span class="ml-2 font-medium">{{ $session->login_at->format('d M Y, h:i:s A') }}</span>
                                                        </div>
                                                        <div>
                                                            <span class="text-gray-600">Logout Time:</span>
                                                            <span class="ml-2 font-medium">{{ $session->logout_at ? $session->logout_at->format('d M Y, h:i:s A') : 'Still Active' }}</span>
                                                        </div>
                                                        <div>
                                                            <span class="text-gray-600">Duration:</span>
                                                            <span class="ml-2 font-medium text-green-600">{{ $session->formatted_duration }}</span>
                                                        </div>
                                                        <div>
                                                            <span class="text-gray-600">Status:</span>
                                                            <span class="ml-2">
                                                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                                                    {{ $session->status === 'active' ? 'bg-green-100 text-green-800' : 
                                                                       ($session->status === 'logged_out' ? 'bg-gray-100 text-gray-800' : 'bg-yellow-100 text-yellow-800') }}">
                                                                    {{ ucfirst(str_replace('_', ' ', $session->status)) }}
                                                                </span>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Platform & Device -->
                                                <div class="bg-gray-50 p-4 rounded-lg">
                                                    <h4 class="font-semibold text-gray-700 mb-3">Platform & Device</h4>
                                                    <div class="grid grid-cols-2 gap-3 text-sm">
                                                        <div>
                                                            <span class="text-gray-600">Platform:</span>
                                                            <span class="ml-2 font-medium">{{ ucfirst($session->platform) }}</span>
                                                        </div>
                                                        <div>
                                                            <span class="text-gray-600">Device Type:</span>
                                                            <span class="ml-2 font-medium">{{ ucfirst($session->device_type ?? 'N/A') }}</span>
                                                        </div>
                                                        <div>
                                                            <span class="text-gray-600">Device Name:</span>
                                                            <span class="ml-2 font-medium">{{ $session->device_name ?? 'N/A' }}</span>
                                                        </div>
                                                        <div>
                                                            <span class="text-gray-600">Device ID:</span>
                                                            <span class="ml-2 font-medium text-xs break-all">{{ $session->device_id ?? 'N/A' }}</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Operating System & Browser -->
                                                <div class="bg-gray-50 p-4 rounded-lg">
                                                    <h4 class="font-semibold text-gray-700 mb-3">Operating System & Browser</h4>
                                                    <div class="grid grid-cols-2 gap-3 text-sm">
                                                        <div>
                                                            <span class="text-gray-600">OS:</span>
                                                            <span class="ml-2 font-medium">{{ $session->os_name ?? 'N/A' }} {{ $session->os_version ?? '' }}</span>
                                                        </div>
                                                        <div>
                                                            <span class="text-gray-600">Browser:</span>
                                                            <span class="ml-2 font-medium">{{ $session->browser_name ?? 'N/A' }} {{ $session->browser_version ?? '' }}</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Network & Location -->
                                                <div class="bg-gray-50 p-4 rounded-lg">
                                                    <h4 class="font-semibold text-gray-700 mb-3">Network & Location</h4>
                                                    <div class="grid grid-cols-2 gap-3 text-sm">
                                                        <div>
                                                            <span class="text-gray-600">IP Address:</span>
                                                            <span class="ml-2 font-medium">{{ $session->ip_address ?? 'N/A' }}</span>
                                                        </div>
                                                        <div>
                                                            <span class="text-gray-600">Timezone:</span>
                                                            <span class="ml-2 font-medium">{{ $session->timezone ?? 'N/A' }}</span>
                                                        </div>
                                                        <div>
                                                            <span class="text-gray-600">Country:</span>
                                                            <span class="ml-2 font-medium">{{ $session->country ?? 'N/A' }}</span>
                                                        </div>
                                                        <div>
                                                            <span class="text-gray-600">City:</span>
                                                            <span class="ml-2 font-medium">{{ $session->city ?? 'N/A' }}</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Additional Information -->
                                                @if($session->session_id || $session->token_id || $session->fcm_token || $session->additional_data)
                                                <div class="bg-gray-50 p-4 rounded-lg">
                                                    <h4 class="font-semibold text-gray-700 mb-3">Additional Information</h4>
                                                    <div class="space-y-2 text-sm">
                                                        @if($session->session_id)
                                                        <div>
                                                            <span class="text-gray-600">Session ID:</span>
                                                            <span class="ml-2 font-mono text-xs break-all">{{ $session->session_id }}</span>
                                                        </div>
                                                        @endif
                                                        @if($session->token_id)
                                                        <div>
                                                            <span class="text-gray-600">Token ID:</span>
                                                            <span class="ml-2 font-mono text-xs break-all">{{ $session->token_id }}</span>
                                                        </div>
                                                        @endif
                                                        @if($session->fcm_token)
                                                        <div>
                                                            <span class="text-gray-600">FCM Token:</span>
                                                            <span class="ml-2 font-mono text-xs break-all">{{ \Illuminate\Support\Str::limit($session->fcm_token, 50) }}</span>
                                                        </div>
                                                        @endif
                                                        @if($session->user_agent)
                                                        <div>
                                                            <span class="text-gray-600">User Agent:</span>
                                                            <span class="ml-2 text-xs break-all">{{ $session->user_agent }}</span>
                                                        </div>
                                                        @endif
                                                        @if($session->additional_data)
                                                        <div>
                                                            <span class="text-gray-600">Additional Data:</span>
                                                            <pre class="mt-2 bg-white p-2 rounded text-xs overflow-auto border">{{ json_encode($session->additional_data, JSON_PRETTY_PRINT) }}</pre>
                                                        </div>
                                                        @endif
                                                    </div>
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-4 text-center text-sm text-gray-500">
                                No login sessions found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $loginSessions->links() }}
        </div>
    </div>
</main>

@else
<div class="p-6">
    <p class="text-red-600">You do not have permission to access this page.</p>
</div>
@endhasanyrole
</div>

<script>
    function showSessionDetails(sessionId) {
        document.getElementById('session-details-' + sessionId).classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeSessionDetails(sessionId) {
        document.getElementById('session-details-' + sessionId).classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    // Close modal on Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modals = document.querySelectorAll('[id^="session-details-"]');
            modals.forEach(modal => {
                if (!modal.classList.contains('hidden')) {
                    const sessionId = modal.id.replace('session-details-', '');
                    closeSessionDetails(sessionId);
                }
            });
        }
    });
</script>
