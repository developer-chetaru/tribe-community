<div>
    <div class="flex-1 overflow-auto bg-[#f6f8fa]">
        <div class="mx-auto p-4">
            {{-- Filters / Search --}}
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 space-y-3 md:space-y-0">

                {{-- Search by Topic --}}
                <div class="relative w-full max-w-[320px]">
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

                {{-- Organisation & Office Filters --}}
                @role('super_admin')
                <div class="w-full sm:w-[auto] flex flex-col sm:flex-row items-start sm:items-center space-y-3 sm:space-y-0 sm:space-x-4 mt-2 md:mt-0">
                    {{-- Organisation Dropdown --}}
                    <div class="w-full sm:w-[auto]">
                        <select wire:model.live="orgId"
                            class="bg-white text-sm border border-slate-200 rounded-md pl-3 pr-10 py-3 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 w-full sm:w-[auto]">
                            <option value="">All Organisations</option>
                            @forelse($organisationsList as $org)
                                <option value="{{ $org->id }}">{{ $org->name }}</option>
                            @empty
                                <option value="">No organisations found</option>
                            @endforelse
                        </select>
                    </div>

                    {{-- Office Dropdown --}}
                    <div class="w-full sm:w-[auto]">
                        <select wire:model="officeId"
                            class="bg-white text-sm border border-slate-200 rounded-md pl-3 pr-10 py-3 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 w-full sm:w-[auto]">
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

                {{-- New Reflection button for organisation_user --}}
                @role('organisation_user')
                <div class="mt-2 sm:mt-0">
                    <a href="{{ route('reflection.create') }}"
                        class="inline-block px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition-colors duration-200">
                        New Reflection
                    </a>
                </div>
                @endrole

            </div>

            {{-- Reflection Grid --}}
            <div class="grid gap-4  mt-6 grid-cols-1 sm:grid-cols-3">

                {{-- Left Column: List of Reflection Boxes --}}
                <div class="bg-white p-4 border-gray-300 rounded-md border">
                    @forelse($reflectionList as $r)
                        <div 
                            class="reflection-box bg-[#F8F9FA] border border-[#E5E5E5] p-3 rounded-md mb-4 cursor-pointer hover:bg-[#F0F0F0]"
                            wire:click="openChat({{ $r['id'] }})"
                        >
                            <h3 class="text-[#020202] text-[18px] font-medium mb-2">{{ $r['topic'] }}</h3>
                            <div class="reflection-info">
                                <ul class="grid grid-cols-2">
                                    <li class="text-[14px] font-light text-[#808080] mb-1">User: 
                                        <span>{{ $r['userName'] ?? auth()->user()->name }}</span>
                                    </li>
                                    @role('super_admin')
                                    <li class="text-[14px] font-light text-[#808080] mb-1">Organization: 
                                        <span>{{ $r['organisation'] }}</span>
                                    </li>
                                    <li class="text-[14px] font-light text-[#808080] mb-1">Department: 
                                        <span>{{ $r['department'] }}</span>
                                    </li>
                                    <li class="text-[14px] font-light text-[#808080] mb-1">Office: 
                                        <span>{{ $r['office'] }}</span>
                                    </li>
                                    @endrole
                                    <li class="text-[14px] font-light text-[#808080] mb-1">Date: 
                                        <span>{{ date('d-m-Y', strtotime($r['created_at'])) }}</span>
                                    </li>
                                    <li class="text-[14px] font-light mb-1">
                                        Status: 
                                        <span class="status font-medium
                                            {{ $r['status'] == 'new' ? 'text-red-600' : ($r['status'] == 'inprogress' ? 'text-yellow-500' : 'text-green-600') }}">
                                            {{ $r['status'] == 'inprogress' ? 'In Progress' : ucfirst($r['status']) }}
                                        </span>
                                    </li>
                                </ul>
                            </div>

                            {{-- Delete Button for Admin --}}
                            @role('super_admin')
                            <div class="flex justify-end mt-2">
                                <button wire:click.stop="confirmDelete('{{ base64_encode($r['id']) }}')" class="flex justify-center items-center p-1">
                                    <img src="{{ asset('images/delete.svg') }}" alt="Delete">
                                </button>
                            </div>
                            @endrole
                        </div>
                    @empty
                        <div class="text-center text-[#808080] text-sm py-4">No reflections found.</div>
                    @endforelse
                </div>


                {{-- Right Column: Chat / Reflection Details --}}
                <div class="reflection-right bg-white border-gray-300 col-span-2 rounded-md border border-[#E5E5E5]">
                    @if($showChatModal)
                        {{-- Chat Header --}}
                        <div class="reflection-box bg-white p-4 rounded-md mb-4 flex justify-between items-start">
                            <div class="flex-1 pr-4">
                                <h3 class="text-[#EB1C24] text-[24px] font-medium mb-2">
                                    {{ $selectedReflection['topic'] ?? 'Team Conflict' }}
                                </h3>
                                <p class="text-[14px] font-light text-[#808080]">
                                    <strong class="text-[#020202] font-semibold">Message:</strong>
                                    {{ $selectedReflection['message'] ?? 'discussion' }}
                                </p>
                            </div>
                            <div class="flex flex-col items-end gap-2">
                                <div class="flex items-center">
                                    <label class="text-[16px] font-normal text-[#020202] mr-2">Status:</label>
                                    <select 
                                        id="reflectionStatusSelect"
                                        class="border rounded-sm border-[#808080] text-[14px] p-2 text-[#EB1C24] mr-2 w-[120px]" 
                                        onchange="handleStatusChange(this)"
                                    >
                                        <!-- <option value="new" {{ $selectedReflection['status'] == 'new' ? 'selected' : '' }}>New</option> -->
                                        <option value="inprogress" {{ $selectedReflection['status'] == 'inprogress' ? 'selected' : '' }}>In Progress</option>
                                        <option value="resolved" {{ $selectedReflection['status'] == 'resolved' ? 'selected' : '' }}>Resolved</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        {{-- Chat Messages --}}
                        <div wire:poll.2s="pollChatMessages" class="bg-[#fdecec] p-5 space-y-4 min-h-[400px] relative overflow-y-auto" id="chat-box">
                            @foreach($chatMessages as $msg)
                                <div class="flex {{ $msg['from'] === auth()->id() ? 'justify-end' : 'justify-start' }} w-full">
                                    <div class="bg-white rounded-xl p-2 shadow-sm max-w-[400px] w-full flex items-start gap-2">
                                        {{-- User Avatar --}}
                                        @if(!empty($msg['user_profile_photo']))
                                            <img src="{{ $msg['user_profile_photo'] }}" class="w-6 h-6 rounded-full" alt="User Photo">
                                        @else
                                            <div class="w-6 h-6 flex items-center justify-center bg-blue-100 text-blue-600 font-medium rounded-full text-xs">
                                                {{ strtoupper(substr($msg['user_name'] ?? 'U', 0, 1)) }}
                                            </div>
                                        @endif

                                        {{-- Message Content --}}
                                        <div class="flex-1">
                                            @if(!empty($msg['message']))
                                                <div class="text-[#020202] text-[16px] font-[400]">{{ $msg['message'] }}</div>
                                            @endif

                                            {{-- Image/File Preview --}}
                                            @if(!empty($msg['image']))
                                                <div class="mt-2">
                                                    @if(preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $msg['image']))
                                                        <img src="{{ $msg['image'] }}" class="w-40 h-40 rounded-lg object-cover border">
                                                    @else
                                                        <a href="{{ $msg['image'] }}" target="_blank" class="text-blue-500 underline text-sm">Download Attachment</a>
                                                    @endif
                                                </div>
                                            @endif

                                            <div class="text-xs text-gray-500 mt-1 text-right">{{ $msg['time'] }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Input Row --}}
                        <div class="flex items-center border-t border-gray-200 p-3 bg-white">
                            <div class="relative w-[90%] flex items-center">
                                <input type="text" wire:model.defer="newChatMessage" placeholder="Write your reply..." class="flex-1 w-full border border-gray-300 rounded-sm px-4 py-2 bg-[#E5E5E5]">

                                <input type="file" id="chatFileInput" wire:model="newChatImage" class="hidden">

                                <span class="absolute right-4 top-2 cursor-pointer" onclick="document.getElementById('chatFileInput').click()">
                                    <img src="{{ asset('images/attachment.svg') }}" alt="Attach">
                                </span>
                            </div>

                            <button wire:click="sendChatMessage" class="ml-3 bg-red-500 hover:bg-red-600 text-white p-2 px-3 rounded-sm flex items-center justify-center">
                                <img src="{{ asset('images/sent.svg') }}" alt="Send">
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Pagination --}}
            <div class="mt-6">
                {{ $reflectionListTbl->links() }}
            </div>
        </div>
    </div>

    {{-- Delete Confirmation Modal --}}
    @if($showDeleteModal)
        <div class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
            <div class="bg-white rounded-md p-6 w-96">
                <h2 class="text-lg font-semibold mb-4">Confirm Delete</h2>
                <p class="mb-4">Are you sure you want to delete this reflection?</p>
                <div class="flex justify-end gap-3">
                    <button wire:click="$set('showDeleteModal', false)" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
                    <button wire:click="deleteReflectionConfirmed" class="px-4 py-2 bg-red-500 text-white rounded">Delete</button>
                </div>
            </div>
        </div>
    @endif

    @push('scripts')
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
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
        function handleStatusChange(select) {
            var selectedStatus = select.value;

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
                        // reset dropdown to previous value
                        select.value = @json($selectedReflection['status_original'] ?? 'inprogress');
                    }
                });
            } else {
                @this.updateReflectionStatus(selectedStatus);
            }
        }
    </script>
    @endpush
</div>
