<nav class="bg-white border-b border-gray-100 flex items-center flex-wrap sm:flex-nowrap justify-between px-2 py-[12px] sm:px-4 sm:py-[18px] relative z-10">

    {{-- Header --}}
    @if (isset($header))
        <header class="text-2xl font-bold text-[#ff2323]">
            <div>
                {!! $header !!}
            </div>
        </header>
    @endif

    {{-- Right Side (HPTM + Profile) --}}
    <div class="flex items-center space-x-2 ml-auto">
 @hasanyrole('basecamp|organisation_user|organisation_admin|director')
		{{-- Notification Badge --}}
        <a href="{{ route('user.notifications') }}"
        class="flex items-center bg-red-50 border border-red-200 text-red-700 font-semibold text-[0px] px-1 py-1 sm:px-4 sm:py-2 rounded-md sm:rounded-full shadow-sm hover:bg-red-100 transition duration-200 sm:text-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 sm:h-4 sm:w-4 sm:mr-2 text-red-600" fill="none" viewBox="0 0 24 24"
                stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14V11a6 6 0 10-12 0v3c0 .386-.149.754-.405 1.029L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            Notifications:
            <span class="ml-1 sm:ml-2 bg-red-600 text-white p-1 w-[13px] h-[13px] sm:w-[18px] sm:h-[18px] rounded-full text-[10px]  sm:text-xs" style="display: flex; align-items: center; justify-content: center; line-height: normal;">
                {{ \App\Models\IotNotification::where('to_bubble_user_id', auth()->id())
                    ->where('archive', false)
                    ->where(function($q) {
                        // Exclude sentiment reminder notifications
                        $q->where(function($subQuery) {
                            $subQuery->where('notificationType', '!=', 'sentiment-reminder')
                                     ->orWhereNull('notificationType');
                        })
                        ->where(function($subQuery) {
                            $subQuery->where('title', '!=', 'Reminder: Please Update Your Sentiment Index')
                                     ->orWhereNull('title');
                        });
                    })
                    ->count() }}
            </span>
        </a>
        {{-- HPTM Button --}}
        <button 
            x-data="{ 
                hptmScore: {{ (Auth::user()->hptmScore ?? 0) + (Auth::user()->hptmEvaluationScore ?? 0) }},
                init() {
                    // Listen to Livewire browser events (dispatched by Userhptm component)
                    const handleScoreUpdate = (e) => {
                        const detail = e.detail || e;
                        let score = null;
                        
                        // Handle different event detail formats
                        if (Array.isArray(detail) && detail.length > 0) {
                            // If detail is array, check first element
                            const first = detail[0];
                            score = first?.hptmScore ?? first;
                        } else if (detail && typeof detail === 'object' && typeof detail.hptmScore !== 'undefined') {
                            // If detail is object with hptmScore property
                            score = detail.hptmScore;
                        } else if (typeof detail === 'number') {
                            // If detail is directly a number
                            score = detail;
                        }
                        
                        if (score !== null && score !== undefined && typeof score === 'number') {
                            this.hptmScore = score;
                        }
                    };
                    
                    // Listen to browser CustomEvent (Livewire dispatches to window)
                    window.addEventListener('score-updated', handleScoreUpdate);
                    
                    // Also listen via Livewire.on if available (for Livewire 2 compatibility)
                    if (typeof Livewire !== 'undefined' && typeof Livewire.on === 'function') {
                        Livewire.on('score-updated', (data) => {
                            handleScoreUpdate({ detail: data });
                        });
                    }
                }
            }"
            class="bg-[#FFEFF0] border border-[#FF9AA0] rounded-md flex items-center py-1 px-2 sm:px-4 sm:py-2 text-[#EB1C24] text-[10px] sm:text-[16px]" 
            style="line-height: normal;">
            HPTM <span class="text-black ml-2" x-text="hptmScore">
                {{ (Auth::user()->hptmScore ?? 0) + (Auth::user()->hptmEvaluationScore ?? 0) }}
            </span>
        </button>
     @endhasanyrole
        {{-- Profile Dropdown --}}
        <div x-data="{ open: false }" class="relative" x-cloak>
            <!-- Trigger -->
            <div @click="open = !open" class="flex items-center space-x-1 cursor-pointer">
                @if(Auth::check() && Laravel\Jetstream\Jetstream::managesProfilePhotos() && Auth::user()->profile_photo_path)
                    <img src="{{ Auth::user()->profile_photo_url }}"
                         alt="{{ Auth::user()->first_name }} {{ Auth::user()->last_name }}"
                         class="w-8 h-8 rounded-full object-cover border">
                @else
                    <svg class="w-4 h-4 sm:w-8 sm:h-8 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 12c2.67 0 8 1.34 8 4v2H4v-2c0-2.66 5.33-4 8-4z"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                @endif
                <span class="text-[12px] sm:-text-[14px] font-medium text-gray-800" style="white-space: nowrap;">
                    {{ Auth::check() ? Auth::user()->first_name . ' ' . Auth::user()->last_name : 'Guest' }}
                </span>
                <svg class="w-2 h-2 sm:w-4 sm:h-4 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                </svg>
            </div>

            <!-- Dropdown -->
            <div x-show="open"
                 @click.away="open = false"
                 x-transition
                 class="absolute right-0 mt-2 w-48 bg-white shadow-lg rounded-md py-2 z-50">

                <!-- Profile -->
                <a href="{{ route('profile.show') }}"
                   class="flex items-center px-4 py-2 hover:bg-gray-100 space-x-2">
                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor"
                         stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M4.5 21a8.25 8.25 0 0115 0"/>
                    </svg>
                    <span class="text-sm text-gray-800">Profile</span>
                </a>

                <!-- Change Password -->
                <a href="{{ route('password.change') }}"
                   class="flex items-center px-1 py-1 sm:px-4 sm:py-2 hover:bg-gray-100 space-x-2">
                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor"
                         stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 15v2m0 4h.01M21 12.3V10a9 9 0 10-18 0v2.3a2.25 2.25 0 01-1.5 2.122V17.25A2.25 2.25 0 003.75 19.5h16.5a2.25 2.25 0 002.25-2.25v-2.828a2.25 2.25 0 01-1.5-2.122z"/>
                    </svg>
                    <span class="text-sm text-gray-800">Change Password</span>
                </a>

                <!-- Logout -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="w-full text-left flex items-center px-4 py-2 hover:bg-gray-100 space-x-2">
                        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor"
                             stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M15.75 9V5.25A2.25 2.25 0 0013.5 3H4.5A2.25 2.25 0 002.25 5.25v13.5A2.25 2.25 0 004.5 21h9a2.25 2.25 0 002.25-2.25V15M18.75 15l3-3m0 0l-3-3m3 3H9"/>
                        </svg>
                        <span class="text-sm text-gray-800">Log Out</span>
                    </button>
                </form>

                <div class="h-[2px] bg-red-500 w-full mt-2"></div>
            </div>
        </div>
    </div>
</nav>
