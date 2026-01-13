<div>
    <div class="flex-1 overflow-auto bg-[#f6f8fa]">
        <div class="mx-auto p-3 sm:p-4 lg:p-6">
            {{-- Filters / Search --}}
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-4 space-y-3 lg:space-y-0 gap-3">

                {{-- Search by Topic --}}
                <div class="relative w-full lg:max-w-[320px]">
                    <span class="absolute top-3.5 left-3 flex items-center text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4">
                            <path fill-rule="evenodd"
                                d="M10.5 3.75a6.75 6.75 0 1 0 0 13.5 6.75 6.75 0 0 0 0-13.5ZM2.25 10.5a8.25 8.25 0 1 1 14.59 5.28l4.69 4.69a.75.75 0 1 1-1.06 1.06l-4.69-4.69A8.25 8.25 0 0 1 2.25 10.5Z"
                                clip-rule="evenodd" />
                        </svg>
                    </span>
                    <input
                        type="text"
                        wire:model.live="searchTopic"
                        placeholder="Search by topic"
                        class="w-full bg-white placeholder:text-slate-400 text-slate-700 text-sm border border-slate-200 rounded-md pl-9 pr-5 py-3 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>

                {{-- Status Filter --}}
                <div class="w-full sm:w-auto flex-1 sm:flex-none">
                    <select wire:model.live="statusFilter"
                        class="bg-white text-sm border border-slate-200 rounded-md pl-3 pr-10 py-2.5 sm:py-3 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 w-full sm:w-auto min-w-[160px]">
                        <option value="">All Status</option>
                        <option value="new">New</option>
                        <option value="inprogress">In Progress</option>
                        <option value="resolved">Resolved</option>
                    </select>
                </div>

                {{-- Organisation & Office Filters --}}
                @role('super_admin')
                <div class="w-full lg:w-auto flex flex-col sm:flex-row items-start sm:items-center gap-3 lg:gap-4">
                    {{-- Organisation Dropdown --}}
                    <div class="w-full sm:w-auto flex-1 sm:flex-none">
                        <select wire:model.live="orgId"
                            class="bg-white text-sm border border-slate-200 rounded-md pl-3 pr-10 py-2.5 sm:py-3 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 w-full sm:w-auto min-w-[180px]">
                            <option value="">All Organisations</option>
                            @forelse($organisationsList as $org)
                                <option value="{{ $org->id }}">{{ $org->name }}</option>
                            @empty
                                <option value="">No organisations found</option>
                            @endforelse
                        </select>
                    </div>

                    {{-- Office Dropdown --}}
                    <div class="w-full sm:w-auto flex-1 sm:flex-none">
                        <select wire:model="officeId"
                            class="bg-white text-sm border border-slate-200 rounded-md pl-3 pr-10 py-2.5 sm:py-3 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 w-full sm:w-auto min-w-[180px]">
                            <option value="">All Offices</option>
                            @forelse($officesList as $office)
                                <option value="{{ $office->id }}">{{ $office->name }}</option>
                            @empty
                                <option value="">No offices found</option>
                            @endforelse
                        </select>
                    </div>
                </div>
                @endrole

                {{-- New Reflection button for organisation_user, basecamp, organisation_admin (team lead), and director --}}
                @hasanyrole('organisation_user|basecamp|organisation_admin|director')
                <div class="w-full lg:w-auto">
                    <a href="{{ route('reflection.create') }}"
                        class="inline-block w-full lg:w-auto text-center px-4 py-2.5 sm:py-2 bg-red-600 text-white rounded hover:bg-red-700 transition-colors duration-200 text-sm sm:text-base font-medium">
                        New Reflection
                    </a>
                </div>
                @endhasanyrole

            </div>

            {{-- Reflection Grid --}}
            <div class="grid gap-3 sm:gap-4 mt-4 sm:mt-6 grid-cols-1 lg:grid-cols-3">

                {{-- Left Column: List of Reflection Boxes --}}
                <div class="bg-white p-3 sm:p-4 border-gray-300 rounded-md border shadow-sm overflow-y-auto block">
                    @forelse($reflectionList as $r)
                        <div 
                            class="reflection-box bg-white border-2 {{ (isset($selectedReflection['id']) && $selectedReflection['id'] == $r['id']) ? 'border-red-500 bg-red-50' : 'border-gray-200 hover:border-gray-300' }} p-4 rounded-lg mb-3 cursor-pointer transition-all hover:shadow-md"
                            wire:click="openChat({{ $r['id'] }})"
                        >
                            <div class="flex items-start justify-between mb-2">
                                <h3 class="text-gray-800 text-[16px] font-semibold flex-1 line-clamp-2">{{ $r['topic'] }}</h3>
                                @role('super_admin')
                                <button 
                                    type="button"
                                    wire:click.stop="confirmDelete('{{ base64_encode($r['id']) }}')" 
                                    class="ml-2 p-1 text-gray-400 hover:text-red-600 transition-colors rounded hover:bg-red-50"
                                    title="Delete reflection"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                                @endrole
                            </div>
                            
                            <div class="space-y-2">
                                <div class="flex items-center gap-2 text-xs text-gray-600">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    <span class="font-medium text-gray-700">{{ $r['userName'] ?? auth()->user()->name }}</span>
                                </div>
                                
                                <div class="flex items-center gap-2 text-xs text-gray-600">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <span>{{ date('d-m-Y', strtotime($r['created_at'])) }}</span>
                                </div>
                                
                                @role('super_admin')
                                <div class="flex items-center gap-2 text-xs text-gray-600">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                    <span>{{ $r['organisation'] ?? 'N/A' }}</span>
                                </div>
                                @endrole
                                
                                <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-medium
                                        {{ $r['status'] == 'new' ? 'bg-red-100 text-red-700' : ($r['status'] == 'inprogress' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                                        {{ $r['status'] == 'inprogress' ? 'In Progress' : ucfirst($r['status']) }}
                                    </span>
                                    @if(!empty($r['message']))
                                        <span class="text-xs text-gray-400 truncate max-w-[150px]">{{ Str::limit($r['message'], 30) }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12">
                            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p class="text-gray-500 text-sm font-medium">No reflections found</p>
                            <p class="text-gray-400 text-xs mt-1">Try adjusting your filters or create a new reflection</p>
                        </div>
                    @endforelse
                </div>


                {{-- Right Column: Chat / Reflection Details --}}
                <div class="reflection-right bg-white border-gray-300 lg:col-span-2 rounded-md border border-[#E5E5E5] flex flex-col h-full min-h-[400px] sm:min-h-[500px] lg:min-h-0 block">
                    @if($showChatModal)

                        {{-- Chat Header --}}
                        <div class="bg-gradient-to-r from-red-50 to-white border-b border-gray-200 p-4 sm:p-5 rounded-t-md">
                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-3 mb-3">
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-[#EB1C24] text-lg sm:text-[22px] font-semibold mb-2 truncate">
                                        {{ $selectedReflection['topic'] ?? 'Team Conflict' }}
                                    </h3>
                                    <div class="flex flex-wrap items-center gap-2 sm:gap-4 text-xs sm:text-sm text-gray-600">
                                        <span class="flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                            </svg>
                                            <span class="font-medium text-gray-800">{{ $selectedReflection['userName'] ?? 'User' }}</span>
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                            <span>{{ date('d-m-Y', strtotime($selectedReflection['created_at'] ?? now())) }}</span>
                                        </span>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium whitespace-nowrap
                                            {{ $selectedReflection['status'] == 'new' ? 'bg-red-100 text-red-700' : ($selectedReflection['status'] == 'inprogress' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                                            {{ $selectedReflection['status'] == 'inprogress' ? 'In Progress' : ucfirst($selectedReflection['status'] ?? 'New') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <label class="text-xs sm:text-sm font-medium text-gray-700 whitespace-nowrap">Status:</label>
                                    <div 
                                        x-data="{ 
                                            open: false,
                                            currentStatus: '{{ strtolower($selectedReflection['status'] ?? 'new') }}',
                                            init() {
                                                // Update status from server data on mount - normalize to lowercase
                                                const serverStatus = '{{ strtolower($selectedReflection['status'] ?? 'new') }}';
                                                this.currentStatus = serverStatus;
                                                
                                                // Listen to Livewire browser events for status updates
                                                window.addEventListener('reflectionStatusUpdated', (e) => {
                                                    // Livewire 3 dispatch format: e.detail contains an array with the parameters
                                                    let status = null;
                                                    if (Array.isArray(e.detail) && e.detail.length > 0) {
                                                        // First element might be the status if passed as named parameter
                                                        status = e.detail[0]?.status ?? e.detail[0];
                                                    } else if (e.detail && e.detail.status) {
                                                        // Direct object format
                                                        status = e.detail.status;
                                                    } else if (typeof e.detail === 'string') {
                                                        status = e.detail;
                                                    }
                                                    
                                                    // Normalize status to lowercase and update
                                                    if (status) {
                                                        status = status.toLowerCase();
                                                        if (status === 'new' || status === 'inprogress' || status === 'resolved') {
                                                            this.currentStatus = status;
                                                        }
                                                    }
                                                });
                                            },
                                            getStatusText() {
                                                if (this.currentStatus === 'new') {
                                                    return 'New';
                                                } else if (this.currentStatus === 'inprogress') {
                                                    return 'In Progress';
                                                } else if (this.currentStatus === 'resolved') {
                                                    return 'Resolved';
                                                }
                                                return 'New';
                                            },
                                            selectStatus(status) {
                                                handleStatusChangeOption(status);
                                                this.open = false;
                                            }
                                        }"
                                        x-on:click.away="open = false"
                                        wire:ignore
                                        class="relative"
                                    >
                                        <button 
                                            @click="open = !open"
                                            type="button"
                                            class="border border-[#EB1C24] rounded-md text-xs sm:text-sm px-3 sm:px-4 py-1.5 sm:py-2 text-gray-700 font-medium bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1 transition-colors duration-150 flex items-center gap-2 min-w-[120px] justify-between"
                                            :class="{
                                                'border-[#EB1C24]': true,
                                                'bg-gray-50': open
                                            }"
                                        >
                                            <span x-text="getStatusText()">In Progress</span>
                                            <svg class="w-4 h-4 text-gray-500 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                        
                                        <!-- Dropdown Menu -->
                                        <div 
                                            x-show="open"
                                            x-transition:enter="transition ease-out duration-100"
                                            x-transition:enter-start="transform opacity-0 scale-95"
                                            x-transition:enter-end="transform opacity-100 scale-100"
                                            x-transition:leave="transition ease-in duration-75"
                                            x-transition:leave-start="transform opacity-100 scale-100"
                                            x-transition:leave-end="transform opacity-0 scale-95"
                                            class="absolute right-0 mt-1 w-full bg-white rounded-md shadow-lg border border-gray-200 z-50 overflow-hidden"
                                            style="display: none;"
                                        >
                                            <button 
                                                type="button"
                                                @click="selectStatus('new')"
                                                class="w-full text-left px-4 py-2 text-xs sm:text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150"
                                                :class="{ 'bg-gray-100 font-medium': currentStatus === 'new' }"
                                            >
                                                New
                                            </button>
                                            <button 
                                                type="button"
                                                @click="selectStatus('inprogress')"
                                                class="w-full text-left px-4 py-2 text-xs sm:text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150"
                                                :class="{ 'bg-gray-100 font-medium': currentStatus === 'inprogress' }"
                                            >
                                                In Progress
                                            </button>
                                            <button 
                                                type="button"
                                                @click="selectStatus('resolved')"
                                                class="w-full text-left px-4 py-2 text-xs sm:text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150"
                                                :class="{ 'bg-gray-100 font-medium': currentStatus === 'resolved' }"
                                            >
                                                Resolved
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3 pt-3 border-t border-gray-200">
                                <p class="text-xs sm:text-sm text-gray-600 mb-1 font-medium">Message:</p>
                                <p class="text-sm sm:text-[15px] text-gray-800 leading-relaxed break-words">
                                    {{ $selectedReflection['message'] ?? 'No message available' }}
                                </p>
                            </div>
                        </div>

                        {{-- Chat Messages --}}
                        <div wire:poll.2s="pollChatMessages" class="bg-gray-50 p-3 sm:p-4 lg:p-6 space-y-3 sm:space-y-4 flex-1 overflow-y-auto min-h-[300px] sm:min-h-[400px]" id="chat-box">
                            @forelse($chatMessages as $msg)
                                <div class="flex {{ $msg['from'] === auth()->id() ? 'justify-end' : 'justify-start' }} w-full">
                                    <div class="flex items-start gap-2 sm:gap-3 max-w-[85%] sm:max-w-[75%] {{ $msg['from'] === auth()->id() ? 'flex-row-reverse' : 'flex-row' }}">
                                        {{-- User Avatar --}}
                                        <div class="flex-shrink-0">
                                            @if(!empty($msg['user_profile_photo']))
                                                <img src="{{ $msg['user_profile_photo'] }}" class="w-7 h-7 sm:w-8 sm:h-8 rounded-full border-2 border-white shadow-sm" alt="User Photo">
                                            @else
                                                <div class="w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center {{ $msg['from'] === auth()->id() ? 'bg-red-500' : 'bg-blue-500' }} text-white font-semibold rounded-full text-xs shadow-sm">
                                                    {{ strtoupper(substr($msg['user_name'] ?? 'U', 0, 1)) }}
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Message Content --}}
                                        <div class="flex flex-col {{ $msg['from'] === auth()->id() ? 'items-end' : 'items-start' }} min-w-0 flex-1">
                                            <div class="bg-white rounded-lg shadow-sm px-3 py-2 sm:px-4 sm:py-3 {{ $msg['from'] === auth()->id() ? 'bg-red-50 border border-red-100' : 'border border-gray-200' }} max-w-full">
                                                @if(!empty($msg['message']))
                                                    <p class="text-gray-800 text-sm sm:text-[15px] leading-relaxed whitespace-pre-wrap break-words">{{ $msg['message'] }}</p>
                                                @endif

                                                {{-- Image/File Preview --}}
                                                @if(!empty($msg['images']) && is_array($msg['images']))
                                                    <div class="mt-2 sm:mt-3 space-y-2">
                                                        @foreach($msg['images'] as $fileUrl)
                                                            <div>
                                                                @if(preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $fileUrl))
                                                                    {{-- Image files --}}
                                                                    <img src="{{ $fileUrl }}" class="max-w-full sm:max-w-[250px] max-h-[200px] sm:max-h-[250px] rounded-lg object-cover border border-gray-200 shadow-sm cursor-pointer hover:opacity-90 transition" onclick="window.open('{{ $fileUrl }}', '_blank')">
                                                                @elseif(preg_match('/\.(mp4|webm|ogg|mov|avi|wmv|flv|mkv|m4v|3gp)$/i', $fileUrl))
                                                                    {{-- Video files --}}
                                                                    <video controls class="max-w-full sm:max-w-[400px] max-h-[300px] rounded-lg border border-gray-200 shadow-sm" preload="metadata">
                                                                        <source src="{{ $fileUrl }}" type="video/mp4">
                                                                        <source src="{{ $fileUrl }}" type="video/webm">
                                                                        <source src="{{ $fileUrl }}" type="video/ogg">
                                                                        Your browser does not support the video tag.
                                                                    </video>
                                                                @else
                                                                    {{-- Other files (PDF, DOC, etc.) --}}
                                                                    <a href="{{ $fileUrl }}" target="_blank" class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-800 text-xs sm:text-sm font-medium break-all">
                                                                        <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                                                        </svg>
                                                                        <span class="truncate">Download Attachment</span>
                                                                    </a>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @elseif(!empty($msg['image']))
                                                    {{-- Backward compatibility: single file --}}
                                                    <div class="mt-2 sm:mt-3">
                                                        @if(preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $msg['image']))
                                                            {{-- Image files --}}
                                                            <img src="{{ $msg['image'] }}" class="max-w-full sm:max-w-[250px] max-h-[200px] sm:max-h-[250px] rounded-lg object-cover border border-gray-200 shadow-sm cursor-pointer hover:opacity-90 transition" onclick="window.open('{{ $msg['image'] }}', '_blank')">
                                                        @elseif(preg_match('/\.(mp4|webm|ogg|mov|avi|wmv|flv|mkv|m4v|3gp)$/i', $msg['image']))
                                                            {{-- Video files --}}
                                                            <video controls class="max-w-full sm:max-w-[400px] max-h-[300px] rounded-lg border border-gray-200 shadow-sm" preload="metadata">
                                                                <source src="{{ $msg['image'] }}" type="video/mp4">
                                                                <source src="{{ $msg['image'] }}" type="video/webm">
                                                                <source src="{{ $msg['image'] }}" type="video/ogg">
                                                                Your browser does not support the video tag.
                                                            </video>
                                                        @else
                                                            {{-- Other files --}}
                                                            <a href="{{ $msg['image'] }}" target="_blank" class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-800 text-xs sm:text-sm font-medium break-all">
                                                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                                                </svg>
                                                                <span class="truncate">Download Attachment</span>
                                                            </a>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-1.5 sm:gap-2 mt-1 px-1">
                                                <span class="text-[10px] sm:text-xs text-gray-500 whitespace-nowrap">{{ $msg['time'] }}</span>
                                                @if($msg['from'] === auth()->id())
                                                    <span class="text-[10px] sm:text-xs text-gray-400">•</span>
                                                    <span class="text-[10px] sm:text-xs font-medium text-gray-600 truncate">{{ $msg['user_name'] ?? 'You' }}</span>
                                                @else
                                                    <span class="text-[10px] sm:text-xs text-gray-400">•</span>
                                                    <span class="text-[10px] sm:text-xs font-medium text-gray-600 truncate">{{ $msg['user_name'] ?? 'User' }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="flex items-center justify-center h-full py-12">
                                    <div class="text-center">
                                        <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                        </svg>
                                        <p class="text-gray-500 text-sm">No messages yet. Start the conversation!</p>
                                    </div>
                                </div>
                            @endforelse
                        </div>

                        {{-- Input Row --}}
                        <div class="border-t border-gray-200 bg-white p-3 sm:p-4 rounded-b-md" 
                             x-data="{ 
                                filesSelected: false,
                                checkFiles() {
                                    const input = document.getElementById('chatFileInput');
                                    if (input && input.files && input.files.length > 0) {
                                        this.filesSelected = true;
                                    } else {
                                        this.filesSelected = false;
                                    }
                                }
                             }"
                             x-init="checkFiles()"
                             x-on:livewire-upload-finish="checkFiles()"
                        >
                            {{-- Alert Message --}}
                            @if($alertMessage)
                                <div class="mb-3 p-3 rounded-lg {{ $alertType === 'error' ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-green-50 border border-green-200 text-green-700' }}">
                                    <div class="flex items-center gap-2">
                                        @if($alertType === 'error')
                                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        @else
                                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        @endif
                                        <p class="text-sm font-medium">{{ $alertMessage }}</p>
                                        <button 
                                            type="button"
                                            wire:click="$set('alertMessage', '')"
                                            class="ml-auto text-gray-400 hover:text-gray-600 transition-colors"
                                            title="Dismiss"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            @endif
                            
                            <form wire:submit.prevent="sendChatMessage" 
                                  class="flex items-end gap-2 sm:gap-3" 
                                  x-data="{ filePreviews: {} }"
                                  x-on:livewire:message-sent.window="$wire.set('alertMessage', ''); $wire.set('alertType', '');"
                                  x-init="
                                    // Store handlePaste function in Alpine data for access
                                    $data.handlePaste = function(event) {
                                        const items = event.clipboardData.items;
                                        if (!items) return;
                                        
                                        for (let i = 0; i < items.length; i++) {
                                            const item = items[i];
                                            
                                            if (item.type.indexOf('image') !== -1) {
                                                event.preventDefault();
                                                
                                                // Clear error message when file is pasted
                                                if (window.Livewire) {
                                                    const wireId = event.target.closest('[wire\\:id]')?.getAttribute('wire:id');
                                                    if (wireId) {
                                                        const component = window.Livewire.find(wireId);
                                                        if (component) {
                                                            component.set('alertMessage', '');
                                                        }
                                                    }
                                                }
                                                
                                                const file = item.getAsFile();
                                                if (!file) continue;
                                                
                                                // Check file size (25MB limit)
                                                const maxSize = 25 * 1024 * 1024;
                                                if (file.size > maxSize) {
                                                    alert('Pasted image is too large (' + (file.size / (1024 * 1024)).toFixed(2) + 'MB). Maximum file size is 25MB.');
                                                    return;
                                                }
                                                
                                                const fileInput = document.getElementById('chatFileInput');
                                                if (!fileInput) return;
                                                
                                                // Check current file count
                                                const currentFiles = fileInput.files ? fileInput.files.length : 0;
                                                const maxFiles = {{ $maxFiles }};
                                                if (currentFiles >= maxFiles) {
                                                    alert('You can attach maximum ' + maxFiles + ' files at once.');
                                                    return;
                                                }
                                                
                                                // Create DataTransfer with existing files + new pasted file
                                                const dataTransfer = new DataTransfer();
                                                
                                                // Add existing files first
                                                if (fileInput.files && fileInput.files.length > 0) {
                                                    for (let j = 0; j < fileInput.files.length; j++) {
                                                        dataTransfer.items.add(fileInput.files[j]);
                                                    }
                                                }
                                                
                                                // Add pasted file
                                                dataTransfer.items.add(file);
                                                
                                                // Update file input
                                                fileInput.files = dataTransfer.files;
                                                
                                                // Trigger change event for Livewire to process
                                                const changeEvent = new Event('change', { bubbles: true, cancelable: true });
                                                fileInput.dispatchEvent(changeEvent);
                                                
                                                // Add to preview immediately
                                                if (!filePreviews) {
                                                    filePreviews = {};
                                                }
                                                const index = dataTransfer.files.length - 1;
                                                if (file.type.startsWith('image/') || file.type.startsWith('video/')) {
                                                    filePreviews[index] = URL.createObjectURL(file);
                                                }
                                                
                                                break; // Only process first image
                                            }
                                        }
                                    };
                                  ">
                                <div class="flex-1 relative min-w-0">
                                    <textarea 
                                        wire:model.defer="newChatMessage" 
                                        placeholder="Write your reply..." 
                                        rows="2"
                                        class="w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-2 sm:py-3 pr-10 sm:pr-12 bg-white text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 resize-none text-sm"
                                        x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.call('sendChatMessage'); }"
                                        x-on:paste="$data.handlePaste($event)"
                                        x-on:input="$wire.set('alertMessage', '')"
                                    ></textarea>
                                    
                                    <input 
                                        type="file" 
                                        id="chatFileInput" 
                                        wire:model="newChatImages" 
                                        class="hidden" 
                                        accept="image/*,video/*,.pdf,.doc,.docx" 
                                        multiple
                                        x-on:change="
                                            handleFileSelection($event, {{ $maxFiles }});
                                            $wire.set('alertMessage', '');
                                        "
                                        x-on:livewire-upload-finish="
                                            $wire.$refresh();
                                            // Update previews after upload completes
                                            setTimeout(() => {
                                                const form = $el.closest('form');
                                                if (form) {
                                                    form.dispatchEvent(new CustomEvent('livewire-upload-finish'));
                                                }
                                            }, 100);
                                        "
                                    >

                                    <button 
                                        type="button"
                                        onclick="document.getElementById('chatFileInput').click()" 
                                        class="absolute right-2 sm:right-3 bottom-2 sm:bottom-3 p-1.5 text-gray-400 hover:text-gray-600 transition-colors rounded hover:bg-gray-100"
                                        title="Attach file"
                                    >
                                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                        </svg>
                                    </button>
                                </div>

                                <button 
                                    type="submit"
                                    wire:loading.attr="disabled"
                                    wire:target="sendChatMessage,newChatImages"
                                    class="bg-[#EB1C24] hover:bg-red-700 text-white px-3 sm:px-5 py-2.5 sm:py-3 rounded-lg flex items-center justify-center gap-1 sm:gap-2 transition-colors shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed flex-shrink-0"
                                    title="Send message"
                                >
                                    <span wire:loading.remove wire:target="sendChatMessage,newChatImages">
                                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                        </svg>
                                    </span>
                                    <span wire:loading wire:target="sendChatMessage,newChatImages" class="flex items-center gap-2">
                                        <svg class="animate-spin h-4 w-4 sm:h-5 sm:w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span class="hidden sm:inline">Uploading...</span>
                                    </span>
                                    <span wire:loading.remove wire:target="sendChatMessage,newChatImages" class="hidden sm:inline font-medium">Send</span>
                                </button>
                            </form>
                            
                            @php
                                $hasFiles = !empty($newChatImages) && is_array($newChatImages);
                                $fileCount = $hasFiles ? count(array_filter($newChatImages, function($f) { return $f !== null && $f !== ''; })) : 0;
                            @endphp
                            @if($hasFiles && $fileCount > 0)
                                <div class="mt-3 space-y-2">
                                    <div class="text-[10px] sm:text-xs text-gray-500 mb-2">
                                        {{ $fileCount }} file(s) attached (max {{ $maxFiles }})
                                    </div>
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 sm:gap-3">
                                        @foreach($newChatImages as $index => $file)
                                            @if($file)
                                                @php
                                                    $fileName = method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : 'File ' . ($index + 1);
                                                    $isImage = false;
                                                    $isVideo = false;
                                                    
                                                    if (method_exists($file, 'getClientOriginalName')) {
                                                        $ext = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
                                                        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
                                                        $isVideo = in_array($ext, ['mp4', 'webm', 'ogg', 'mov', 'avi', 'wmv', 'flv', 'mkv', 'm4v', '3gp']);
                                                    }
                                                    
                                                    $previewUrl = null;
                                                    $serverPreviewUrl = null;
                                                    try {
                                                        // Try to get server-side preview URL after upload
                                                        // This will be available after Livewire uploads the file
                                                        if (method_exists($file, 'temporaryUrl')) {
                                                            try {
                                                                $serverPreviewUrl = $file->temporaryUrl();
                                                            } catch (\Exception $e) {
                                                                // File might still be uploading
                                                                $serverPreviewUrl = null;
                                                            }
                                                        } elseif (method_exists($file, 'getTemporaryUrl')) {
                                                            try {
                                                                $serverPreviewUrl = $file->getTemporaryUrl();
                                                            } catch (\Exception $e) {
                                                                $serverPreviewUrl = null;
                                                            }
                                                        }
                                                        $previewUrl = $serverPreviewUrl;
                                                    } catch (\Exception $e) {
                                                        // Preview URL not available yet - file might still be uploading
                                                        $previewUrl = null;
                                                        $serverPreviewUrl = null;
                                                    }
                                                @endphp
                                                
                                                <div class="relative group bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm" 
                                                     x-data="{ 
                                                        index: {{ $index }},
                                                        previewUrl: null,
                                                        serverPreviewUrl: '{{ $serverPreviewUrl ?? '' }}'
                                                     }"
                                                     x-init="
                                                        const form = $el.closest('form');
                                                        const updatePreview = () => {
                                                            // First check client-side preview (immediate)
                                                            if (form && form._x_dataStack && form._x_dataStack[0]) {
                                                                const filePreviews = form._x_dataStack[0].filePreviews;
                                                                if (filePreviews && filePreviews[index]) {
                                                                    previewUrl = filePreviews[index];
                                                                    return true;
                                                                }
                                                            }
                                                            // Then check server-side preview (after upload)
                                                            if (serverPreviewUrl) {
                                                                previewUrl = serverPreviewUrl;
                                                                return true;
                                                            }
                                                            return false;
                                                        };
                                                        updatePreview();
                                                        // Watch for changes every 100ms for faster preview
                                                        let checkCount = 0;
                                                        const maxChecks = 50; // Stop after 5 seconds (50 * 100ms)
                                                        const interval = setInterval(() => {
                                                            checkCount++;
                                                            if (updatePreview() || checkCount >= maxChecks) {
                                                                clearInterval(interval);
                                                            }
                                                        }, 100);
                                                        // Listen for upload finish to update server preview
                                                        $el.closest('form').addEventListener('livewire-upload-finish', () => {
                                                            // Refresh to get server preview URL
                                                            setTimeout(() => {
                                                                if (serverPreviewUrl) {
                                                                    previewUrl = serverPreviewUrl;
                                                                }
                                                            }, 500);
                                                        });
                                                        // Cleanup on component destroy
                                                        $el.addEventListener('alpine:destroy', () => {
                                                            clearInterval(interval);
                                                        });
                                                     ">
                                                    @if($isImage)
                                                        {{-- Image Preview - show image if available, otherwise just filename --}}
                                                        <div x-show="previewUrl" style="display: none;" class="bg-white">
                                                            <img 
                                                                x-bind:src="previewUrl"
                                                                alt="{{ $fileName }}"
                                                                class="w-full h-24 sm:h-32 object-cover bg-white"
                                                                x-on:error="previewUrl = null"
                                                            >
                                                        </div>
                                                        {{-- Show only filename if preview not available --}}
                                                        <div class="w-full h-24 sm:h-32 flex items-center justify-center bg-white px-2" x-show="!previewUrl">
                                                            <span class="text-[10px] sm:text-xs text-gray-600 text-center break-words">{{ $fileName }}</span>
                                                        </div>
                                                    @elseif($isVideo)
                                                        {{-- Video Preview - show video if available, otherwise just filename --}}
                                                        <div class="relative w-full h-24 sm:h-32 bg-black" x-show="previewUrl" style="display: none;">
                                                            <video 
                                                                x-bind:src="previewUrl"
                                                                class="w-full h-full object-cover bg-black"
                                                                preload="metadata"
                                                                muted
                                                                playsinline
                                                            >
                                                                <source x-bind:src="previewUrl" type="video/mp4">
                                                                <source x-bind:src="previewUrl" type="video/webm">
                                                            </video>
                                                            <div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-30 pointer-events-none">
                                                                <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                                                                    <path d="M8 5v14l11-7z"/>
                                                                </svg>
                                                            </div>
                                                        </div>
                                                        {{-- Show only filename if preview not available --}}
                                                        <div class="w-full h-24 sm:h-32 flex items-center justify-center bg-white px-2" x-show="!previewUrl">
                                                            <span class="text-[10px] sm:text-xs text-gray-600 text-center break-words">{{ $fileName }}</span>
                                                        </div>
                                                    @else
                                                        {{-- Non-image/video files - show only filename --}}
                                                        <div class="w-full h-24 sm:h-32 flex items-center justify-center bg-white px-2">
                                                            <span class="text-[10px] sm:text-xs text-gray-600 text-center break-words">{{ $fileName }}</span>
                                                        </div>
                                                    @endif
                                                    
                                                    {{-- File Name Overlay - only show if preview is available --}}
                                                    <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-60 text-white text-[10px] sm:text-xs px-2 py-1 truncate" x-show="previewUrl" style="display: none;">
                                                        {{ $fileName }}
                                                    </div>
                                                    
                                                    {{-- Remove Button --}}
                                                    <button 
                                                        type="button"
                                                        wire:click="removeFile({{ $index }})"
                                                        class="absolute top-1 right-1 p-1 bg-red-500 hover:bg-red-600 text-white rounded-full transition-colors opacity-0 group-hover:opacity-100"
                                                        title="Remove file"
                                                    >
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                        </svg>
                                                    </button>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @else
                        {{-- Empty State --}}
                        <div class="flex items-center justify-center h-full min-h-[400px] sm:min-h-[500px] p-6 sm:p-8">
                            <div class="text-center max-w-md">
                                <svg class="w-16 h-16 sm:w-20 sm:h-20 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                                <h3 class="text-base sm:text-lg font-semibold text-gray-700 mb-2">Select a Reflection</h3>
                                <p class="text-xs sm:text-sm text-gray-500">Choose a reflection from the list to view details and start a conversation.</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Pagination --}}
            <div class="mt-4 sm:mt-6 overflow-x-auto">
                <div class="min-w-fit">
                    {{ $reflectionListTbl->links() }}
                </div>
            </div>
        </div>
    </div>

    {{-- Delete Confirmation Modal --}}
    @if($showDeleteModal)
        <div class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 p-4">
            <div class="bg-white rounded-md p-4 sm:p-6 w-full max-w-md">
                <h2 class="text-base sm:text-lg font-semibold mb-3 sm:mb-4">Confirm Delete</h2>
                <p class="text-sm sm:text-base mb-4 sm:mb-6">Are you sure you want to delete this reflection?</p>
                <div class="flex flex-col-reverse sm:flex-row justify-end gap-2 sm:gap-3">
                    <button 
                        type="button"
                        wire:click="$set('showDeleteModal', false)" 
                        class="w-full sm:w-auto px-4 py-2.5 sm:py-2 bg-gray-300 hover:bg-gray-400 rounded transition-colors text-sm sm:text-base font-medium"
                    >
                        Cancel
                    </button>
                    <button 
                        type="button"
                        wire:click="deleteReflectionConfirmed" 
                        class="w-full sm:w-auto px-4 py-2.5 sm:py-2 bg-red-500 hover:bg-red-600 text-white rounded transition-colors text-sm sm:text-base font-medium"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>
    @endif

    @push('scripts')
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Handle file selection and validation
        function handleFileSelection(event, maxFiles) {
            const files = event.target.files;
            if (files.length > maxFiles) {
                alert('You can attach maximum ' + maxFiles + ' files at once.');
                event.target.value = '';
                return false;
            }
            // Check file sizes (25MB = 25 * 1024 * 1024 bytes)
            const maxSize = 25 * 1024 * 1024; // 25MB in bytes
            for (let i = 0; i < files.length; i++) {
                if (files[i].size > maxSize) {
                    alert('File "' + files[i].name + '" is too large (' + (files[i].size / (1024 * 1024)).toFixed(2) + 'MB). Maximum file size is 25MB.');
                    event.target.value = '';
                    return false;
                }
            }
            // Create client-side preview URLs for immediate display
            const form = event.target.closest('form');
            if (form) {
                // Access Alpine data directly
                let alpineData = null;
                if (form._x_dataStack && form._x_dataStack[0]) {
                    alpineData = form._x_dataStack[0];
                } else if (window.Alpine) {
                    alpineData = Alpine.$data(form);
                }
                
                if (alpineData) {
                    if (!alpineData.filePreviews) {
                        alpineData.filePreviews = {};
                    }
                    Array.from(files).forEach((file, index) => {
                        if (file.type.startsWith('image/') || file.type.startsWith('video/')) {
                            alpineData.filePreviews[index] = URL.createObjectURL(file);
                        }
                    });
                    // Force Alpine to update
                    if (window.Alpine && form._x_dataStack) {
                        Alpine.nextTick(() => {
                            form.dispatchEvent(new CustomEvent('alpine:update'));
                        });
                    }
                }
            }
        }
        
        // Handle file upload errors
        function handleUploadError(event) {
            const err = event.detail;
            let msg = 'Upload failed. ';
            if (err) {
                if (err.message) {
                    msg += err.message;
                } else if (err.status === 413 || err.statusText === 'Content Too Large') {
                    msg = 'File is too large (413 Content Too Large). Maximum file size is 25MB. Please check your server PHP settings (upload_max_filesize and post_max_size should be at least 30M). See FIX_UPLOAD_LIMITS.md for instructions.';
                } else if (err.status) {
                    msg += 'HTTP ' + err.status + ': ' + (err.statusText || 'Unknown error');
                }
            }
            alert(msg);
            if (window.Livewire) {
                window.Livewire.find(event.target.closest('[wire\\:id]').getAttribute('wire:id')).set('alertType', 'error');
                window.Livewire.find(event.target.closest('[wire\\:id]').getAttribute('wire:id')).set('alertMessage', msg);
            }
        }
        document.addEventListener('livewire:update', () => {
            const chatBox = document.getElementById('chat-box');
            if (chatBox) {
                chatBox.scrollTop = chatBox.scrollHeight;
            }
        });

        window.addEventListener('scrollToBottom', () => {
            const chatBox = document.getElementById('chat-box');
            if (chatBox) {
                chatBox.scrollTop = chatBox.scrollHeight;
            }
        });
        // Function for Alpine.js to handle status changes
        function handleStatusChangeOption(selectedStatus) {
            if(selectedStatus === 'resolved') {
                Swal.fire({
                    title: 'Are you sure?',
                    text: "Do you want to mark this reflection as Resolved?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Confirm',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        @this.updateReflectionStatus('resolved'); // call Livewire method
                    } else {
                        // Reset status if cancelled - handled by Livewire refresh
                        @this.$refresh();
                    }
                });
            } else {
                @this.updateReflectionStatus(selectedStatus);
            }
        }
        
        // Keep old function for backward compatibility (if needed)
        function handleStatusChange(select) {
            var selectedStatus = select.value;
            handleStatusChangeOption(selectedStatus);
        }
        
    </script>
    @endpush
</div>
