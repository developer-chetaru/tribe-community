<x-slot name="header">
    <h2 class="text-2xl font-bold capitalize text-[#ff2323]">Notifications</h2>
</x-slot>

<div>
    @hasanyrole('organisation_user|basecamp|organisation_admin')
    <main class="p-6 flex-1">
        <div class="bg-white shadow-sm rounded-lg p-6 border border-[#E5E5E5]">

            {{-- Header --}}
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-800">Notifications</h1>
                    <p class="text-gray-500 text-sm mt-1">Manage and review your recent updates.</p>
                </div>
            </div>

            <div class="flex items-center justify-between mb-5">

                <div class="flex gap-3">
                    <button
                        wire:click="switchTab('active')"
                        class="px-8 py-2 rounded-md font-medium mr-1
                            {{ $tab === 'active'
                                ? 'bg-red-500 text-white'
                                : 'border border-[#020202] bg-[#F8F9FA] text-gray-700 hover:bg-gray-100' }}">
                        All
                    </button>

                    <button
                        wire:click="switchTab('archive')"
                        class="border border-[#020202] bg-[#F8F9FA] flex gap-2 leading-none items-center justify-center px-4 py-3 rounded-md hover:bg-gray-100 font-medium
                            {{ $tab === 'archive' ? 'ring-1 ring-red-400' : '' }}">
                        <img src="{{ asset('images/unarchive-03.svg') }}" class="w-4 h-4">
                        Archived
                    </button>
                </div>

                {{-- Right: Archive All Button --}}
                @if($tab === 'active' && count($notifications) > 0)
                    <button
                        wire:click="archiveAll"
                        class="bg-[#FFEFF0] text-red-600 border border-red-300 px-4 py-[6px]
                            rounded-md text-sm hover:bg-red-500 hover:text-white transition">
                        Archive All
                    </button>
                @endif

            </div>

            {{-- Main Layout: Left list + Right panel --}}
            <div class="grid grid-cols-12 gap-5 h-[calc(100vh-280px)] overflow-hidden">
                
                {{-- LEFT: Notification list --}}
                <div class="{{ $selectedNotification ? 'col-span-5' : 'col-span-12' }} space-y-3 overflow-y-auto pr-2" style="max-height: calc(100vh - 280px);">

                    @forelse($notifications as $note)
                        @php $isSelected = $selectedNotification && $selectedNotification->id === $note->id; @endphp

                        <div 
                            @if($tab === 'active')
                                wire:click="openNotification({{ $note->id }})"
                                class="cursor-pointer flex items-start gap-3 p-4 border rounded-lg transition-all duration-200 
                                {{ $isSelected ? 'bg-[#FFEFF0] border-red-300 shadow-md animate-slideLeftActive'
                                                : 'bg-white border-gray-200 hover:bg-[#FFF5F5] hover:border-red-200 hover:shadow-sm' }}"
                            @else
                                class="flex items-start gap-3 p-4 border rounded-lg transition-all duration-200 
                                bg-gray-50 border-gray-300 opacity-75 cursor-not-allowed"
                            @endif
                        >

                            {{-- Icon --}}
                            <div class="mt-1 bg-red-100 p-2 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-red-500"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-5-5.917V4a1 1 0 10-2 0v1.083A6.002 6.002 0 006 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                            </div>

                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-semibold {{ $isSelected ? 'text-red-600' : 'text-gray-900' }}">
                                        {{ $note->title ?? 'No Title' }}
                                    </h3>

                                    @if($tab === 'active' && !$note->archive)
                                    <span class="text-[10px] bg-red-200 text-red-700 font-medium px-2 py-0.5 rounded-full">
                                        NEW
                                    </span>
                                    @endif
                                </div>

                                <p class="text-gray-600 text-xs mt-1 line-clamp-2">
                                    {{ $note->description ?? '' }}
                                </p>

                                <span class="text-[11px] text-gray-400 flex items-center gap-1 mt-2">
                                    🕒 {{ $note->created_at->format('d M Y, h:i A') }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-gray-500 py-16">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-5-5.917V4a1 1 0 10-2 0v1.083A6.002 6.002 0 006 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <p class="text-sm font-medium">No {{ $tab === 'active' ? 'active' : 'archived' }} notifications found.</p>
                        </div>
                    @endforelse

                </div>


                {{-- RIGHT: Detail Panel -- Only show when notification is selected --}}
                @if($selectedNotification)
                <div class="col-span-7 bg-[#F8F9FA] rounded-xl border border-[#E5E5E5] overflow-hidden flex flex-col" style="max-height: calc(100vh - 280px);">
                    {{-- Header --}}
                    <div class="flex justify-between items-start p-5 bg-white border-b border-[#E5E5E5]">
                        <div class="flex-1">
                            <h2 class="text-lg font-semibold text-red-600 mb-1">
                                {{ $selectedNotification->title }}
                            </h2>
                            <span class="text-xs text-gray-500">
                                {{ $selectedNotification->created_at->format('d M Y, h:i A') }}
                            </span>
                        </div>

                        @if($tab === 'active')
                        <div class="flex gap-2 ml-4">
                            {{-- Single Archive --}}
                            <button wire:click="moveToArchive({{ $selectedNotification->id }})"
                                class="bg-red-500 text-white px-4 py-2 rounded-md text-sm hover:bg-red-600 transition-colors">
                                Archive
                            </button>
                        </div>
                        @endif
                    </div>

                    {{-- Body --}}
                    <div class="px-5 py-6 text-sm text-gray-800 leading-relaxed overflow-y-auto flex-1">
                        <div class="whitespace-pre-wrap">{{ $selectedNotification->description }}</div>
                    </div>
                </div>
                @endif


            </div>
        </div>
    </main>
    @else
        <!-- Unauthorized Message -->
        <div class="flex items-center justify-center h-[80vh]">
            <div class="text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-20 h-20 mx-auto text-gray-400 mb-4" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M18.364 5.636A9 9 0 015.636 18.364M15 9l-6 6M9 9l6 6" />
                </svg>
                <h2 class="text-2xl font-semibold text-gray-800">You are not authorized</h2>
                <p class="text-gray-500 mt-2">You do not have permission to view this page.</p>
            </div>
        </div>
    @endhasanyrole

    {{-- 🔥 Animation for left selected item --}}
<style>
    @keyframes slideLeft {
        from { transform: translateX(-8px); }
        to { transform: translateX(0); }
    }
    .animate-slideLeftActive { animation: slideLeft 0.25s ease-out; }
</style>
</div>
