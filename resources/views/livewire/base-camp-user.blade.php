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

                {{-- VERIFY BUTTON (only for inactive) --}}
                @if ($activeTab === 'inactive')
                    <button wire:click="sendVerificationEmail({{ $user->id }})"
                        class="mt-6 bg-[#ff2323] text-white px-6 py-2.5 rounded-xl text-base font-bold
                                shadow-md shadow-red-200 hover:bg-red-600 transition duration-200 ease-in-out
                                focus:outline-none focus:ring-2 focus:ring-[#ff2323] focus:ring-offset-2">
                        Send Verify Email
                    </button>
                @endif

            </div>

        @endforeach
    </div>


    {{-- PAGINATION --}}
    <div class="mt-12 flex justify-center">
        {{ $users->links('components.pagination') }} 
    </div>

</div>