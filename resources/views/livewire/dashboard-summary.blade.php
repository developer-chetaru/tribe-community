<div>
<script>
    // Maintain scroll position when filters change
    document.addEventListener('livewire:init', () => {
        let scrollPosition = 0;
        
        // Save scroll position before Livewire updates
        Livewire.hook('morph.updating', ({ component, cleanup }) => {
            scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
        });
        
        // Restore scroll position after Livewire updates
        Livewire.hook('morph.updated', ({ component }) => {
            // Use setTimeout to ensure DOM is fully updated
            setTimeout(() => {
                window.scrollTo({
                    top: scrollPosition,
                    behavior: 'instant' // Use 'instant' instead of 'auto' to prevent smooth scroll
                });
            }, 10);
        });
    });
    
    // Listen for HappyIndex debug events
    document.addEventListener('livewire:init', () => {
        Livewire.on('happyindex-debug', (data) => {
            console.group('🔍 HappyIndex Debug - Existing Entry Check');
            console.log('User ID:', data.debugInfo.user_id);
            console.log('Current Timezone:', data.debugInfo.current_timezone);
            console.log('Today Date (Current TZ):', data.debugInfo.today_date_in_current_tz);
            console.log('Total Entries:', data.debugInfo.total_entries);
            console.table(data.debugInfo.entries);
            console.groupEnd();
        });
        
        Livewire.on('happyindex-blocked', (data) => {
            console.group('⚠️ HappyIndex Save Blocked');
            console.log('Reason: Already submitted today');
            console.log('Existing Entry ID:', data.blockInfo.existing_entry_id);
            console.log('Existing Entry Created At (UTC):', data.blockInfo.existing_entry_created_at);
            console.log('Existing Entry Timezone:', data.blockInfo.existing_entry_timezone);
            console.log('Current Timezone:', data.blockInfo.current_timezone);
            console.log('Today Date:', data.blockInfo.today_date);
            console.log('Message:', data.blockInfo.message);
            console.groupEnd();
        });
        
        Livewire.on('happyindex-saving', (data) => {
            console.group('💾 HappyIndex Saving');
            console.log('User ID:', data.saveInfo.user_id);
            console.log('Current Timezone:', data.saveInfo.current_timezone);
            console.log('Today Date:', data.saveInfo.today_date);
            console.log('Mood Value:', data.saveInfo.mood_value);
            console.log('Message:', data.saveInfo.message);
            console.groupEnd();
        });
        
        Livewire.on('happyindex-success', (data) => {
            console.group('✅ HappyIndex Save Success');
            console.log('User ID:', data.successInfo.user_id);
            console.log('Happy Index ID:', data.successInfo.happy_index_id);
            console.log('Mood Value:', data.successInfo.mood_value);
            console.log('Timezone:', data.successInfo.timezone);
            console.log('Created At (UTC):', data.successInfo.created_at_utc);
            console.log('EI Score:', data.successInfo.ei_score);
            console.log('Message:', data.successInfo.message);
            console.groupEnd();
        });
    });
</script>
@if($showSubscriptionExpiredModal)
    <!-- Subscription Expired Modal - Show first, block everything -->
    <div x-data="{ show: @entangle('showSubscriptionExpiredModal') }" x-show="show" x-cloak style="display: block;" class="fixed inset-0 bg-black bg-opacity-50 z-[10000] flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md max-h-[90vh] overflow-y-auto shadow-2xl m-4">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-red-600">⚠️ Subscription Expired</h2>
                <button wire:click="closeSubscriptionExpiredModal" type="button" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
            </div>
            
            <div class="space-y-4">
                <p class="text-gray-700">
                    Your organization's subscription has expired or is not active. Access to all features has been restricted.
                </p>
                
                @if(isset($subscriptionStatus['has_pending_invoice']) && $subscriptionStatus['has_pending_invoice'])
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <p class="text-yellow-800 text-sm">
                            Your organization has a pending invoice. Please contact your directing to make a payment.
                        </p>
                    </div>
                @endif

                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-red-800 text-sm font-medium">
                        ⚠️ You cannot access any features until the subscription is renewed.
                    </p>
                </div>

                <div class="flex justify-end space-x-2">
                    <button wire:click="closeSubscriptionExpiredModal" type="button" class="px-4 py-2 border rounded-md">Close</button>
                    @if(auth()->user()->hasRole('director'))
                        <a href="{{ route('billing') }}" class="px-4 py-2 bg-[#EB1C24] text-white rounded-md">Go to Billing</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
@else
<div class="grid items-start grid-cols-1 md:grid-cols-2 gap-3 mt-3" x-data="{ openModal: false, modalData: { day: null, score: null, desc: null } }">
    <div class="flex flex-wrap border border-gray-100 rounded-md w-full">
        <div class="flex px-3 py-2 bg-white w-full min-h-[74px]">
            <h3 class="text-lg sm:text-2xl text-[#EB1C24] font-bold mb-1 sm:mb-0 flex items-center gap-2">Sentiment Index</h3>
        </div>
<div
  x-data="{ open: false,  selectedText: '',    selectedImage: '', showDatePicker: false, startDate: '', endDate: '' }"
  x-on:close-leave-modal.window="open = false; showDatePicker = false"
  class="bg-[#F8F8F8] py-2 px-3 w-full"
>
@if($onLeaveToday)
    <div x-data="{ confirmModal: false }" x-init="@this.on('openConfirmModal', () => { confirmModal = true })">
    <button id="openModalBtn" class="bg-red-500 rounded-lg p-3 sm:p-6 w-full mb-4 text-center text-white">
        Out of Office Mode Enabled
    </button>
    

    <!-- <div x-show="confirmModal" x-transition.opacity
         class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-lg p-6 w-80 relative">
            <button @click="confirmModal = false"
                    class="absolute top-2 right-2 text-gray-500">&times;</button>
            <p class="text-center mb-4">Ready to resume work?</p>
            <button wire:click="changeLeaveStatus"
                    @click="confirmModal = false"
                    class="w-full bg-red-500 text-white py-2 rounded">
                DISABLE
            </button>
        </div>
    </div> -->
    <!-- Modal -->
    <div id="confirmModal" class="hidden fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-lg p-6 w-80 relative">
            <button id="closeModalBtn"
                class="absolute top-2 right-2 text-gray-500">&times;</button>
            <p class="text-center mb-4">Ready to resume work?</p>
            <button id="disableBtn"
                class="w-full bg-red-500 text-white py-2 rounded">
                DISABLE
            </button>
        </div>
    </div>
</div>

@elseif($showHappyIndex && !$userGivenFeedback && !$onLeaveToday)
    <div class="bg-red-500 rounded-lg p-3 sm:p-6 w-full mb-4 text-center text-white">
        <h2 class="text-[14px] font-medium mb-4 sm:ext-lg" style="line-height: 1.3;">How's things at work today?</h2>
        <div class="flex justify-center space-x-4 mb-4 text-2xl">
         <template x-for="(mood, index) in [
  
  {src: '{{ asset("images/happy-user.svg") }}', value: 3, text: 'That\'s great to hear! Want to share what made your day good?'},
    {src: '{{ asset("images/avarege-user.svg") }}', value: 2, text: 'Okay day... Want to share more?'},
    {src: '{{ asset("images/sad-user.svg") }}', value: 1, text: 'Sorry to hear that! Want to tell us why?'}
]" :key="index">
    <span>
        <img :src="mood.src"
             class="w-12 h-12 mb-2 cursor-pointer"
             alt=""
             @click="selectedImage = mood.src; selectedText = mood.text; showDatePicker = false; $wire.set('moodStatus', mood.value); $wire.moodStatus = mood.value; setTimeout(() => { open = true; }, 100);">
    </span>
</template>
  </div>
        <a href="#"
           @click.prevent="showDatePicker = true; open = true"
           class="underline text-white font-medium text-[12px] sm:text-[16px]" style="display: inline-block; line-height: 1.3;">
           I'm not in work today!
        </a>
    </div>
@endif
    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-lg p-6 w-80 relative" style="margin: 0 auto;">
            <button @click="open = false; selectedImage = ''; selectedText = ''; $wire.moodStatus = null; $wire.moodNote = null;" class="absolute top-2 right-2 text-gray-500">&times;</button>
<template x-if="showDatePicker">
  <div>
    <p class="text-red-500 mb-4 text-center">Please select the dates you aim to be back at work</p>
    <div class="mb-4">
        <label class="block text-gray-700 mb-1">From:</label>
        <input
            type="date"
            wire:model.defer="leaveStartDate"
            class="w-full p-2 border border-gray-300 rounded bg-gray-100 cursor-not-allowed"
            readonly
        >
        @error('leaveStartDate') 
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p> 
        @enderror
    </div>
    <div class="mb-4">
      <label class="block text-gray-700 mb-1">To:</label>
    <input
    type="date"
    wire:model.defer="leaveEndDate"
    min="{{ $leaveStartDate ? \Carbon\Carbon::parse($leaveStartDate)->toDateString() : today()->toDateString() }}"
    class="w-full p-2 border border-gray-300 rounded">
      @error('leaveEndDate') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
    <button wire:click="applyLeave" class="w-full bg-red-500 text-white py-2 rounded">SUBMIT</button>
  </div>
</template>

      <template x-if="!showDatePicker">
        <div>
            <div class="flex justify-center mb-4">
                <template x-if="selectedImage && selectedImage !== 'undefined'">
                    <img :src="selectedImage" class="w-20 h-20" alt="selected mood">
                </template>
                <template x-if="!selectedImage || selectedImage === 'undefined'">
                    <span class="text-4xl">😊</span>
                </template>
            </div>
            <p class="text-center mb-4" x-text="selectedText"></p>
            {{-- Hidden input to ensure Livewire tracks moodStatus --}}
            <input type="hidden" wire:model.live="moodStatus" />
            <textarea
                wire:model.defer="moodNote"
                class="w-full p-2 border border-gray-300 rounded mb-2
                       focus:ring-red-500 focus:border-red-500"
                placeholder="Write a few words about your day..."
            ></textarea>
            @if(session()->has('error'))
                <div class="mb-2 p-2 bg-red-100 border border-red-400 text-red-700 rounded text-sm">
                    {{ session('error') }}
                </div>
            @endif
            @if(session()->has('success'))
                <div class="mb-2 p-2 bg-green-100 border border-green-400 text-green-700 rounded text-sm">
                    {{ session('success') }}
                </div>
            @endif
            @error('moodNote')
                <p class="text-red-500 text-sm mb-2">{{ $message }}</p>
            @enderror
            @error('moodStatus')
                <p class="text-red-500 text-sm mb-2">{{ $message }}</p>
            @enderror
            <button 
                type="button"
                wire:click="happyIndex" 
                wire:loading.attr="disabled"
                wire:target="happyIndex"
                class="w-full bg-red-500 text-white py-2 rounded disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="happyIndex">SUBMIT</span>
                <span wire:loading wire:target="happyIndex">Saving...</span>
            </button>
        </div>
    </template>
        </div>
    </div>
  
  
  
  
      <!-- Filters -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-2">
        @hasanyrole('organisation_user|director')
        @if(auth()->user()->orgId)
        <!-- Offices -->
        <select wire:model.live="selectedOffice" class="border capitalize border-gray-100 rounded-sm py-2 px-2 bg-white text-[12px] sm:text-[14px] text-[#808080] focus:ring-red-500 focus:border-red-500">
            <option value="">All Offices</option>
            @foreach($offices['offices'] ?? [] as $office)
                <option value="{{ $office['officeId'] }}">{{ $office['office'] }}</option>
            @endforeach
        </select>

        <!-- Departments -->
        <select wire:model="selectedDepartment" class="border border-gray-100 rounded-sm py-2 px-2 bg-white text-[12px] sm:text-[14px] text-[#808080] focus:ring-red-500 focus:border-red-500">
            <option value="">All Departments</option>
            @foreach($departments ?? [] as $dep)
                <option value="{{ $dep['id'] }}">{{ $dep['department'] }}</option>
            @endforeach
        </select>
        @endif
        @endhasanyrole

        <!-- Month -->
        <select wire:model.live="month" wire:loading.class="opacity-50" class="border border-gray-100 rounded-sm py-2 px-2 bg-white text-[12px] sm:text-[14px] text-[#808080] focus:ring-red-500 focus:border-red-500">
            @php
                $currentYear = now()->year;
                $currentMonth = now()->month;

                // Only show months up to current month if the selected year is current year
                $maxMonth = ($this->year ?? $currentYear) == $currentYear ? $currentMonth : 12;
            @endphp

            @foreach(range(1, $maxMonth) as $m)
                <option value="{{ $m }}" {{ $m == ($this->month ?? $currentMonth) ? 'selected' : '' }}>
                    {{ date('F', mktime(0,0,0,$m,1)) }}
                </option>
            @endforeach
        </select>


        <!-- Year -->
        <select wire:model.live="year" wire:loading.class="opacity-50" class="border border-gray-100 rounded-sm py-2 px-2 bg-white text-[12px] sm:text-[14px] text-[#808080] focus:ring-red-500 focus:border-red-500">
            @foreach($orgYearList ?? [] as $y)
                <option value="{{ $y }}" {{ $y == ($this->year ?? date('Y')) ? 'selected' : '' }}>{{ $y }}</option>
            @endforeach
        </select>
    </div>
  <div class="w-full overflow-x-auto sm:px-3 pb-3" wire:key="calendar-{{ $month }}-{{ $year }}-{{ auth()->user()->timezone ?? 'default' }}">
        @php
            $year = $this->year ?? now()->year;
            $month = $this->month ?? now()->month;

            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $firstDayOfMonth = date('w', strtotime("$year-$month-01"));

            $calendarDays = [];

            // Add blank cells for first week
            for ($i = 0; $i < $firstDayOfMonth; $i++) {
                $calendarDays[] = null;
            }

            // Add all days of the month
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $calendarDays[] = $day;
            }

            // Pad remaining cells to complete weeks
            $remaining = 7 - (count($calendarDays) % 7);
            if ($remaining < 7) {
                for ($i = 0; $i < $remaining; $i++) {
                    $calendarDays[] = null;
                }
            }

            // Get user's timezone for today's date comparison
            $userTimezone = auth()->user()->timezone ?? 'Asia/Kolkata';
            if (!in_array($userTimezone, timezone_identifiers_list())) {
                $userTimezone = 'Asia/Kolkata';
            }
            $todayDate = \Carbon\Carbon::today($userTimezone);
            
            // Get user's registration date (join date)
            $authUser = auth()->user();
            $userRegistrationDate = $authUser ? \Carbon\Carbon::parse($authUser->created_at)->setTimezone($userTimezone)->startOfDay() : null;
            
            // Organisation working days (for org users) - used to mark off-days on calendar
            $isOrgUser = false;
            $orgWorkingDays = ["Mon","Tue","Wed","Thu","Fri"];
            if ($authUser && $authUser->orgId) {
                $isOrgUser = true;
                $organisation = \App\Models\Organisation::find($authUser->orgId);
                if ($organisation && $organisation->working_days) {
                    $wdRaw = $organisation->working_days;
                    if (is_string($wdRaw)) {
                        $decoded = json_decode($wdRaw, true);
                        if (is_array($decoded)) $orgWorkingDays = $decoded;
                    } elseif (is_array($wdRaw)) {
                        $orgWorkingDays = $wdRaw;
                    }
                }
            }
        @endphp

        <table class="table-auto border-collapse w-full text-center calendar-table"> 
            <thead>
                <tr>
                    <th class="p-2 text-red-600">Sun</th>
                    <th class="p-2">Mon</th>
                    <th class="p-2">Tue</th>
                    <th class="p-2">Wed</th>
                    <th class="p-2">Thu</th>
                    <th class="p-2">Fri</th>
                    <th class="p-2 text-red-600">Sat</th>
                </tr>
            </thead>
            <tbody>
                @foreach(array_chunk($calendarDays, 7) as $week)
                    <tr>
                        @foreach($week as $day)
                            @if($day)
                                @php
                                    $dayData = $happyIndexMonthly[$day-1] ?? null;
                                    $dayDate = \Carbon\Carbon::createFromDate($year, $month, $day, $userTimezone)->startOfDay();
                                    
                                    // For today, use todayMoodData if available, otherwise use dayData
                                    if ($dayDate->isSameDay($todayDate) && $todayMoodData) {
                                        $score = $todayMoodData['score'] ?? null;
                                        $desc  = $todayMoodData['description'] ?? null;
                                        $mood  = $todayMoodData['mood_value'] ?? null;
                                    } else {
                                        $score = $dayData['score'] ?? null;
                                        $desc  = $dayData['description'] ?? null;
                                        $mood  = $dayData['mood_value'] ?? null;
                                    }

                                    $img = null;

                                    // Check if date is before user's registration date - show "-" for those dates
                                    $isBeforeRegistration = false;
                                    if ($userRegistrationDate && $dayDate->lt($userRegistrationDate)) {
                                        $isBeforeRegistration = true;
                                        $img = null; // Will show "-" in the view
                                    }

                                    // Determine emoji based on date and data
                                    // If org user and this date is a non-working day for the org, show off-day emoji
                                    $isOrgOffDay = false;
                                    if (!$isBeforeRegistration && $isOrgUser) {
                                        $dayShort = $dayDate->format('D'); // Mon, Tue, etc.
                                        if (!in_array($dayShort, $orgWorkingDays)) {
                                            $isOrgOffDay = true;
                                        }
                                    }
                                    // Check if this is a leave day (from DashboardService)
                                    $isLeaveDay = isset($dayData['is_leave']) && $dayData['is_leave'] === true;
                                    
                                    // Using score-based logic similar to app code:
                                    // null or 0-50: red (sad), 51-80: yellow (avarge), 81+: green (happy)
                                    if (!$isBeforeRegistration && $dayDate->lt($todayDate)) {
                                        // Past dates: check leave first, then show mood if data exists, otherwise show red
                                        if ($isLeaveDay) {
                                            // User was on leave - show leave emoji
                                            $img = 'leave-office.svg';
                                        } elseif ($isOrgOffDay) {
                                            // Org non-working past day - use off-day marker
                                            $img = null;
                                        } elseif ($dayData && $mood !== null && $score !== null) {
                                            // Use score-based logic (matching app code)
                                            if ($score >= 0 && $score <= 50) {
                                                $img = 'sad.svg';
                                            } else if ($score >= 51 && $score <= 80) {
                                                $img = 'avarge.svg';
                                            } else if ($score > 80) {
                                                $img = 'happy.svg';
                                            } else {
                                                $img = 'sad.svg';
                                            }
                                        } else {
                                            // No data for past date - show red
                                            $img = 'sad.svg';
                                        }
                                    } elseif (!$isBeforeRegistration && $dayDate->isSameDay($todayDate)) {
                                        // Today: check leave status first, then check if data exists
                                        if ($isOrgOffDay) {
                                            $img = null;
                                        } elseif ($onLeaveToday ?? false) {
                                            $img = 'leave-office.svg';
                                        } elseif ($todayMoodData && isset($todayMoodData['mood_value']) && isset($todayMoodData['score'])) {
                                            // Use today's actual mood data from database
                                            $todayScore = $todayMoodData['score'];
                                            // Use score-based logic (matching app code)
                                            if ($todayScore >= 0 && $todayScore <= 50) {
                                                $img = 'sad.svg';
                                            } else if ($todayScore >= 51 && $todayScore <= 80) {
                                                $img = 'avarge.svg';
                                            } else if ($todayScore > 80) {
                                                $img = 'happy.svg';
                                            } else {
                                                $img = 'sad.svg';
                                            }
                                        } else {
                                            // Nothing filled today - show nothing (null)
                                            $img = null;
                                        }
                                    } elseif (!$isBeforeRegistration) {
                                        // Future dates: only show leave icon if applicable
                                        $isFutureLeave = false;
                                        if (!empty($userLeaves)) {
                                            foreach ($userLeaves as $leave) {
                                                $start = \Carbon\Carbon::parse($leave['start_date'], 'Asia/Kolkata');
                                                $end   = \Carbon\Carbon::parse($leave['end_date'], 'Asia/Kolkata');
                                                if ($dayDate->between($start, $end)) {
                                                    $isFutureLeave = true;
                                                    break;
                                                }
                                            }
                                        }
                                        if ($isOrgOffDay) {
                                            $img = null;
                                        } else {
                                            $img = $isFutureLeave ? 'leave-office.svg' : null;
                                        }
                                    }
                                @endphp
                                <td class="p-1" wire:key="day-{{ $day }}">
                                    <div class="flex flex-col items-center justify-center bg-white border w-14 h-14 mx-auto cursor-pointer hover:bg-gray-50 transition rounded w-full"
                                        @if($dayDate->lte($todayDate) || $img === 'leave-office.svg')
                                            x-on:click="openModal = true; modalData.day = {{ $day }}; modalData.score = {{ $score !== null ? $score : 'null' }}; modalData.desc = {{ json_encode($desc ?? 'No description available') }}; modalData.img = {{ json_encode($img ?? '-') }};"
                                        @else
                                            style="cursor: default;"
                                        @endif
                                        wire:ignore
                                    >
                                        <span class="text-gray-600 text-sm font-medium">{{ $day }}</span>
                                        @if(isset($isBeforeRegistration) && $isBeforeRegistration)
                                            {{-- Date before user registration - show dash --}}
                                            <span class="text-gray-400 text-sm mt-1">-</span>
                                        @elseif(isset($isOrgOffDay) && $isOrgOffDay)
                                            {{-- Organization non-working day - show no emoji (placeholder dash) --}}
                                            <span class="text-gray-400 text-sm mt-1">-</span>
                                        @else
                                            @if($img)
                                                <img src="{{ asset('images/' . $img) }}" class="w-5 h-5 mt-1" alt="mood">
                                            @else
                                                <span class="text-gray-400 text-sm mt-1">-</span>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            @else
                                <td class="p-2"></td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    </div>
    <div x-cloak x-show="openModal" x-transition.opacity class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-xl shadow-2xl p-6 w-11/12 max-w-md mx-auto transform transition-all duration-300 scale-95"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
        >
            <div class="flex flex-col items-center mb-4">
                <h2 class="text-2xl font-semibold text-gray-800 mb-2">Day <span x-text="modalData.day"></span> Sentiment</h2>
                <p class="text-lg font-medium text-gray-700 mb-2">Score: <span x-text="modalData.score"></span></p>
                <template x-if="modalData.img && modalData.img !== '-' && modalData.img !== 'undefined'">
                    <img :src="'{{ asset('images') }}/' + modalData.img" class="w-16 h-16 mb-2" alt="mood">
                </template>
                <template x-if="!modalData.img || modalData.img === '-' || modalData.img === 'undefined'">
                    <span class="text-gray-400 text-sm mb-2">-</span>
                </template>
                <p class="text-gray-600 text-center px-4">
                    <span x-text="modalData.desc || 'No description available'"></span>
                </p>
            </div>
            <div class="flex justify-center mt-4">
                <button @click="openModal = false" class="px-6 py-2 bg-red-500 text-white font-medium rounded-lg hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-400">
                    Close
                </button>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const openModalBtn = document.getElementById('openModalBtn');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const confirmModal = document.getElementById('confirmModal');
    const disableBtn = document.getElementById('disableBtn');

    if (openModalBtn) {
        openModalBtn.addEventListener('click', function () {
            confirmModal.classList.remove('hidden');
        });
    }

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', function () {
            confirmModal.classList.add('hidden');
        });
    }

    if (disableBtn) {
        disableBtn.addEventListener('click', function () {
            confirmModal.classList.add('hidden');

            // Trigger Livewire action
            @this.call('changeLeaveStatus');
        });
    }
});
</script>

@livewire('summary')
@endif

</div>

