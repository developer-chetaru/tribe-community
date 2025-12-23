<x-slot name="header">
    <h2 class="text-[14px] sm:text-[24px] font-medium tracking-tight capitalize text-[#EB1C24]">
        Basecamp Users
    </h2>
</x-slot>

<div class="max-w-7xl mx-auto">

    {{-- FLASH MESSAGE --}}
    @if (session()->has('message'))
        <div 
            x-data="{ show: true }" 
            x-init="setTimeout(() => show = false, 3000)" 
            x-show="show"
            x-transition.duration.500ms
            class="mb-8 px-6 py-4 text-white text-base font-medium rounded-xl shadow-lg
            {{ session('type') === 'success' ? 'bg-green-600' : 'bg-red-600' }}">
            
            {{ session('message') }}
        </div>
    @endif


    {{-- BIG BEAUTIFUL TABS --}}
    <div class="flex gap-10 mb-10 border-b-2 border-gray-200">

        {{-- ACTIVE --}}
        <button wire:click="switchTab('active')"
            class="pb-4 text-[14px] sm:text-[20px] font-bold relative transition-all duration-300
            {{ $activeTab === 'active' ? 'text-[#EB1C24]' : 'text-gray-500 hover:text-gray-900' }}">
            Verify Users

            @if ($activeTab === 'active')
                <span class="absolute left-0 right-0 -bottom-[2px] h-1 bg-[#ff2323] rounded-full"></span>
            @endif
        </button>

        {{-- INACTIVE --}}
        <button wire:click="switchTab('inactive')"
            class="pb-4 text-[14px] sm:text-[20px] font-bold relative transition-all duration-300
            {{ $activeTab === 'inactive' ? 'text-[#EB1C24]' : 'text-gray-500 hover:text-gray-900' }}">
            Unverify Users

            @if ($activeTab === 'inactive')
                <span class="absolute left-0 right-0 -bottom-[2px] h-1 bg-[#EB1C24] rounded-full"></span>
            @endif
        </button>

    </div>


    {{-- USERS GRID --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8 xl:gap-10">

        @foreach($users as $user)

            <div class="bg-white rounded-3xl shadow-xl p-8 border border-gray-100
                         flex flex-col items-center text-center
                         hover:shadow-2xl hover:-translate-y-1.5 transition duration-300 ease-in-out">

                {{-- PROFILE PHOTO / INITIALS --}}
                <div class="w-24 h-24 rounded-full bg-red-50 flex items-center justify-center 
                             text-[16px] sm:text-[20px] font-bold mb-5 text-[#ff2323] overflow-hidden shadow-md ring-4 ring-red-100">

                    @if ($user->profile_photo_path && file_exists(public_path($user->profile_photo_path)))
                        <img src="{{ url($user->profile_photo_path) }}"
                             class="w-full h-full object-cover rounded-full">
                    @else
                        @php
                            $first = strtoupper(substr($user->first_name, 0, 1));
                            $last  = strtoupper(substr($user->last_name, 0, 1));
                        @endphp
                        <span>{{ $first }}{{ $last }}</span>
                    @endif
                </div>

                {{-- NAME --}}
                <h3 class="text-[16px] sm:text-[20px] font-extrabold text-gray-900 leading-snug truncate max-w-full">
                    {{ $user->first_name }} {{ $user->last_name }}
                </h3>

                {{-- EMAIL --}}
                <p class="text-gray-500 text-sm mt-1 mb-1 truncate max-w-full">
                    {{ $user->email }}
                </p>

                {{-- PHONE --}}
                <p class="text-gray-500 text-sm">
                    {{ $user->phone ?? '' }}
                </p>

                {{-- ACTION BUTTONS --}}
                <div class="mt-6 flex flex-col gap-2 w-full">
                    {{-- VERIFY BUTTON (only for inactive) --}}
                    @if ($activeTab === 'inactive')
                        <button type="button" 
                                wire:click.prevent="sendVerificationEmail({{ $user->id }})"
                                wire:loading.attr="disabled"
                                class="bg-[#ff2323] text-white px-4 py-2 rounded-xl text-sm font-bold
                                        shadow-md shadow-red-200 hover:bg-red-600 transition duration-200 ease-in-out
                                        focus:outline-none focus:ring-2 focus:ring-[#ff2323] focus:ring-offset-2
                                        disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer">
                            <span wire:loading.remove wire:target="sendVerificationEmail({{ $user->id }})">Send Verify Email</span>
                            <span wire:loading wire:target="sendVerificationEmail({{ $user->id }})">Sending...</span>
                        </button>
                    @endif

                    {{-- VIEW, EDIT, DELETE BUTTONS --}}
                    <div class="flex gap-2 justify-center">
                        {{-- VIEW BUTTON --}}
                        <button type="button" 
                                wire:click.prevent="viewUser({{ $user->id }})"
                                wire:loading.attr="disabled"
                                class="bg-blue-500 text-white px-4 py-2 rounded-lg text-sm font-medium
                                        hover:bg-blue-600 transition duration-200 ease-in-out
                                        focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2
                                        disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                                title="View User">
                            <svg class="w-4 h-4 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </button>

                        {{-- EDIT BUTTON --}}
                        <button type="button" 
                                wire:click.prevent="editUser({{ $user->id }})"
                                wire:loading.attr="disabled"
                                class="bg-green-500 text-white px-4 py-2 rounded-lg text-sm font-medium
                                        hover:bg-green-600 transition duration-200 ease-in-out
                                        focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2
                                        disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                                title="Edit User">
                            <svg class="w-4 h-4 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </button>

                        {{-- DELETE BUTTON --}}
                        <button type="button" 
                                wire:click.prevent="confirmDelete({{ $user->id }})"
                                wire:loading.attr="disabled"
                                class="bg-red-500 text-white px-4 py-2 rounded-lg text-sm font-medium
                                        hover:bg-red-600 transition duration-200 ease-in-out
                                        focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2
                                        disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                                title="Delete User">
                            <svg class="w-4 h-4 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>

            </div>

        @endforeach
    </div>


    {{-- PAGINATION --}}
    <div class="mt-12 flex justify-center">
        {{ $users->links('components.pagination') }} 
    </div>

    {{-- VIEW USER MODAL --}}
    @if($showViewModal && $viewingUser)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
         x-data="{ show: @entangle('showViewModal') }"
         x-show="show"
         x-cloak
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="bg-white rounded-xl shadow-2xl p-8 max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto"
             x-show="show"
             x-cloak
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             @click.away="$wire.closeViewModal()">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold text-gray-900">User Details</h3>
                <button type="button" 
                        wire:click.prevent="closeViewModal"
                        class="text-gray-400 hover:text-gray-600 transition cursor-pointer">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="space-y-4">
                {{-- Profile Photo --}}
                <div class="flex justify-center mb-6">
                    <div class="w-32 h-32 rounded-full bg-red-50 flex items-center justify-center 
                                 text-2xl font-bold text-[#ff2323] overflow-hidden shadow-md ring-4 ring-red-100">
                        @if ($viewingUser->profile_photo_path && file_exists(public_path($viewingUser->profile_photo_path)))
                            <img src="{{ url($viewingUser->profile_photo_path) }}"
                                 class="w-full h-full object-cover rounded-full">
                        @else
                            @php
                                $first = strtoupper(substr($viewingUser->first_name, 0, 1));
                                $last  = strtoupper(substr($viewingUser->last_name, 0, 1));
                            @endphp
                            <span>{{ $first }}{{ $last }}</span>
                        @endif
                    </div>
                </div>

                {{-- User Information --}}
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Name</label>
                        <p class="text-gray-900 font-medium">{{ $viewingUser->first_name }} {{ $viewingUser->last_name }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Email</label>
                        <p class="text-gray-900">{{ $viewingUser->email }}</p>
                    </div>
                    @if($viewingUser->phone)
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Phone</label>
                        <p class="text-gray-900">{{ $viewingUser->country_code ?? '' }}{{ $viewingUser->phone }}</p>
                    </div>
                    @endif
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Status</label>
                        <p class="text-gray-900">
                            <span class="px-3 py-1 rounded-full text-sm font-medium {{ $viewingUser->status == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $viewingUser->status == 1 ? 'Verified' : 'Unverified' }}
                            </span>
                        </p>
                    </div>
                    @if($viewingUser->created_at)
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Joined</label>
                        <p class="text-gray-900">{{ $viewingUser->created_at->format('M d, Y') }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="button" 
                        wire:click.prevent="closeViewModal"
                        class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition duration-200 cursor-pointer">
                    Close
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- DELETE CONFIRMATION MODAL --}}
    @if($showDeleteModal)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
         x-data="{ show: @entangle('showDeleteModal') }"
         x-show="show"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="bg-white rounded-xl shadow-2xl p-8 max-w-md w-full mx-4"
             x-show="show"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             @click.away="show = false">
            <h3 class="text-2xl font-bold text-gray-900 mb-4">Delete User</h3>
            <p class="text-gray-600 mb-6">
                Are you sure you want to delete <strong>{{ $deleteUserName ?? '' }}</strong>? This action cannot be undone.
            </p>
            <div class="flex gap-4 justify-end">
                <button type="button" 
                        wire:click.prevent="cancelDelete"
                        wire:loading.attr="disabled"
                        class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition duration-200 disabled:opacity-50 cursor-pointer">
                    Cancel
                </button>
                <button type="button" 
                        wire:click.prevent="deleteUser"
                        wire:loading.attr="disabled"
                        class="px-6 py-2 bg-red-500 text-white rounded-lg font-medium hover:bg-red-600 transition duration-200 disabled:opacity-50 cursor-pointer">
                    <span wire:loading.remove wire:target="deleteUser">Delete</span>
                    <span wire:loading wire:target="deleteUser">Deleting...</span>
                </button>
            </div>
        </div>
    </div>
    @endif

</div>