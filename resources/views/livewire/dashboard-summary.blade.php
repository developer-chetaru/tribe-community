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
                            Your organization has a pending invoice. Please contact your director to make a payment.
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
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4" x-data="{ openModal: false, modalData: { day: null, score: null, desc: null } }">
    <div class="flex flex-wrap border border-gray-100 rounded-md w-full">
        <div class="flex px-3 py-4">
            <h3 class="text-[14px] sm:text-[20px] text-[#EB1C24] font-semibold">Sentiment Index</h3>
        </div>
<div
  x-data="{ open: false,  selectedText: '',    selectedImage: '', showDatePicker: false, startDate: '', endDate: '' }"
  x-on:close-leave-modal.window="open = false; showDatePicker = false"
  class="bg-[#F8F8F8] py-3 px-4 w-full"
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
             @click="selectedImage = mood.src; selectedText = mood.text; showDatePicker = false; open = true; $wire.moodStatus = mood.value">
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
            <button @click="open = false" class="absolute top-2 right-2 text-gray-500">&times;</button>
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
                <img :src="selectedImage" class="w-20 h-20" alt="selected mood">
            </div>
            <p class="text-center mb-4" x-text="selectedText"></p>
            <textarea
                wire:model.defer="moodNote"
                class="w-full p-2 border border-gray-300 rounded mb-2
                       focus:ring-red-500 focus:border-red-500"
                placeholder="Write a few words about your day..."
            ></textarea>
            @error('moodNote')
                <p class="text-red-500 text-sm mb-2">{{ $message }}</p>
            @enderror
            <button wire:click="happyIndex" class="w-full bg-red-500 text-white py-2 rounded">SUBMIT</button>
        </div>
    </template>
        </div>
    </div>
  
  
  
  
      <!-- Filters -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
        @hasanyrole('organisation_user')
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
        <select wire:model.live="month" class="border border-gray-100 rounded-sm py-2 px-2 bg-white text-[12px] sm:text-[14px] text-[#808080] focus:ring-red-500 focus:border-red-500">
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
        <select wire:model.live="year" class="border border-gray-100 rounded-sm py-2 px-2 bg-white text-[12px] sm:text-[14px] text-[#808080] focus:ring-red-500 focus:border-red-500">
            @foreach($orgYearList ?? [] as $y)
                <option value="{{ $y }}" {{ $y == ($this->year ?? date('Y')) ? 'selected' : '' }}>{{ $y }}</option>
            @endforeach
        </select>
    </div>
  <div class="w-full overflow-x-auto px-4 pb-4">
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

            $todayDate = \Carbon\Carbon::today('Asia/Kolkata');
        @endphp

        <table class="table-auto border-collapse w-full text-center">
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
                                    $score = $dayData['score'] ?? null;
                                    $desc  = $dayData['description'] ?? null;
                                    $mood  = $dayData['mood_value'] ?? null;

                                    $dayDate = \Carbon\Carbon::createFromDate($year, $month, $day, 'Asia/Kolkata');
                                    $img = null;

                                    // Determine emoji based on date and data
                                    if ($dayDate->lt($todayDate)) {
                                        $img = $dayData ? ($mood === 3 ? 'happy.svg' : ($mood === 2 ? 'avarge.svg' :  'sad.svg')) : 'sad.svg';
                                    } elseif ($dayDate->isSameDay($todayDate)) {
                                        $img = ($onLeaveToday ?? false) ? 'leave-office.svg' : ($dayData ? ($mood === 3 ? 'happy.svg' : ($mood === 2 ? 'sad.svg' : 'avarge.svg')) : 'sad.svg');
                                    } else {
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
                                        $img = $isFutureLeave ? 'leave-office.svg' : null;
                                    }
                                @endphp
                                <td class="p-1" wire:key="day-{{ $day }}">
                                    <div class="flex flex-col items-center justify-center bg-white border w-14 h-14 mx-auto cursor-pointer hover:bg-gray-50 transition rounded w-full"
                                        @if($dayDate->lte($todayDate) || $img === 'leave-office.svg')
                                            @click="openModal = true; modalData = { day: {{ $day }}, score: '{{ $score }}', desc: '{{ $desc }}', img: '{{ $img ?? '-' }}' }"
                                        @endif
                                        wire:ignore
                                    >
                                        <span class="text-gray-600 text-sm font-medium">{{ $day }}</span>
                                        @if($img)
                                            <img src="{{ asset('images/' . $img) }}" class="w-5 h-5 mt-1" alt="mood">
                                        @else
                                            <span class="text-gray-400 text-sm mt-1">-</span>
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
                <img :src="'{{ asset('images') }}/' + modalData.img" class="w-16 h-16 mb-2" alt="mood">
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


