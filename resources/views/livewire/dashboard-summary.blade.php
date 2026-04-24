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
<div class="mt-3 grid grid-cols-1 items-start gap-3 md:grid-cols-2" x-data="{ openModal: false, modalData: { day: null, score: null, desc: null, moodLabel: '', img: '-' } }">
    <div class="flex min-w-0 w-full flex-wrap overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="flex w-full flex-col gap-2 border-b border-gray-100 bg-white px-3 py-3 sm:flex-row sm:items-center sm:justify-between sm:py-2.5 sm:pl-4 sm:pr-4">
            <h3 class="text-lg font-bold text-[#EB1C24] sm:text-2xl">Sentiment Index</h3>
            @if(auth()->user()->orgId)
                @hasanyrole('organisation_user|director')
                    <div class="inline-flex w-full max-w-md rounded-lg border border-gray-200 bg-gray-100 p-0.5 sm:w-auto" role="tablist" aria-label="Sentiment calendar view">
                        <button
                            type="button"
                            wire:click="$set('sentimentCalendarScope', 'my')"
                            wire:loading.attr="disabled"
                            class="flex-1 rounded-md px-3 py-2 text-center text-xs font-semibold transition sm:min-w-[8.5rem] sm:text-sm {{ $sentimentCalendarScope === 'my' ? 'bg-[#EB1C24] text-white shadow-sm' : 'text-gray-600 hover:text-gray-900' }}"
                        >
                            My Sentiment
                        </button>
                        <button
                            type="button"
                            wire:click="$set('sentimentCalendarScope', 'org')"
                            wire:loading.attr="disabled"
                            class="flex-1 rounded-md px-3 py-2 text-center text-xs font-semibold transition sm:min-w-[8.5rem] sm:text-sm {{ $sentimentCalendarScope === 'org' ? 'bg-[#EB1C24] text-white shadow-sm' : 'text-gray-600 hover:text-gray-900' }}"
                        >
                            Organisation
                        </button>
                    </div>
                @endhasanyrole
            @endif
        </div>
<div
  x-data="{ open: false,  selectedText: '',    selectedImage: '', showDatePicker: false, startDate: '', endDate: '' }"
  x-on:close-leave-modal.window="open = false; showDatePicker = false"
  class="w-full bg-white px-3 py-2 sm:px-3"
>
@if(!$showHappyIndex && !$onLeaveToday && !$userGivenFeedback)
    @if($nextWindowTime && $checkInOpenAtIso)
        {{-- REMINDER: pre-window (mockup) --}}
        <div class="mb-4 w-full rounded-lg border border-[#EB1C24]/50 bg-[#FEEBEE] px-3 py-3 sm:px-4 sm:py-3.5"
             x-data="{
                openAt: new Date(@js($checkInOpenAtIso)).getTime(),
                label: '',
                tick() {
                    const ms = Math.max(0, this.openAt - Date.now());
                    const h = Math.floor(ms / 3600000);
                    const m = Math.floor((ms % 3600000) / 60000);
                    this.label = h > 0 ? (h + 'h ' + m + 'm') : (m + 'm');
                },
                init() { this.tick(); setInterval(() => this.tick(), 60000); }
             }">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 flex-1 items-start gap-3">
                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 border-[#EB1C24] bg-white text-sm font-bold text-[#EB1C24]" aria-hidden="true">!</span>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wide text-[#EB1C24]">Reminder</p>
                        <p class="mt-0.5 text-sm font-semibold text-gray-900 sm:text-base">Daily sentiment window opens at {{ $nextWindowTime }}</p>
                    </div>
                </div>
                <div class="shrink-0 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-center shadow-sm sm:min-w-[7rem]">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-600">Opens in</p>
                    <p class="text-xl font-bold tabular-nums text-gray-900 sm:text-2xl" x-text="label"></p>
                </div>
            </div>
        </div>
    @elseif($sentimentEligibleToday)
        <div class="mb-4 w-full rounded-lg border border-[#EB1C24]/40 bg-[#FEEBEE] px-3 py-3 sm:px-4">
            <p class="text-sm font-bold text-gray-900 sm:text-base">Today's check-in window has ended</p>
            <p class="mt-1 text-xs text-gray-600 sm:text-sm">See you tomorrow when the window opens again.</p>
        </div>
    @else
        <div class="mb-4 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-3 text-center text-gray-500 sm:px-4">
            <p class="text-sm">No check-in window today (non-working day).</p>
        </div>
    @endif
@endif

@if($onLeaveToday)
<div x-data="{ confirmOpen: false }">
    <button
        type="button"
        @click="confirmOpen = true"
        class="bg-red-500 rounded-lg p-3 sm:p-6 w-full mb-4 text-center text-white">
        Out of Office Mode Enabled — Click to Disable
    </button>

    <div x-show="confirmOpen" x-cloak x-transition.opacity
         class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-lg p-6 w-80 max-w-[90vw] relative" @click.away="confirmOpen = false">
            <button type="button" @click="confirmOpen = false"
                    class="absolute top-2 right-2 text-gray-500 text-xl leading-none">&times;</button>
            <p class="text-center mb-4 text-gray-700">Ready to resume work?</p>
            <button
                type="button"
                wire:click="changeLeaveStatus"
                @click="confirmOpen = false"
                class="w-full bg-red-500 text-white py-2 rounded hover:bg-red-600">
                DISABLE OUT OF OFFICE
            </button>
        </div>
    </div>
</div>

@elseif($showHappyIndex && !$userGivenFeedback && !$onLeaveToday)
    @php
        $sentimentMoodPicker = [
            ['src' => asset('images/happy-user.svg'), 'value' => 3, 'text' => "That's great to hear! Want to share what made your day good?"],
            ['src' => asset('images/avarege-user.svg'), 'value' => 2, 'text' => 'Okay day... Want to share more?'],
            ['src' => asset('images/sad-user.svg'), 'value' => 1, 'text' => 'Sorry to hear that! Want to tell us why?'],
        ];
    @endphp
    <div class="mb-4 w-full overflow-hidden rounded-2xl bg-[#EB1C24] text-white shadow-md">
        <div class="flex flex-col gap-5 p-4 sm:p-6 lg:flex-row lg:items-stretch lg:justify-between lg:gap-8">
            <div class="flex-1 text-center lg:text-left">
                <h2 class="text-base font-semibold sm:text-lg">How are you feeling today??</h2>
                <div class="mt-4 flex flex-wrap justify-center gap-3 lg:justify-start">
                    <template x-for="(mood, index) in @js($sentimentMoodPicker)" :key="index">
                        <button type="button"
                                class="rounded-xl bg-white/15 px-3 py-2 ring-1 ring-white/25 transition hover:bg-white/25 focus:outline-none focus:ring-2 focus:ring-white/60"
                                @click="selectedImage = mood.src; selectedText = mood.text; showDatePicker = false; $wire.set('moodStatus', mood.value); $wire.moodStatus = mood.value; setTimeout(() => { open = true; }, 100);">
                            <img :src="mood.src" class="mx-auto h-12 w-12" alt="">
                        </button>
                    </template>
                </div>
                <a href="#"
                   @click.prevent="showDatePicker = true; open = true"
                   class="mt-4 inline-block text-sm font-medium text-white underline decoration-white/80 underline-offset-2 sm:text-base">
                    I'm on a break!
                </a>
            </div>
            @if($checkInCloseAtIso)
            <div class="flex shrink-0 flex-col justify-center rounded-xl border border-white/35 bg-white/10 px-4 py-4 sm:px-5 sm:py-5 lg:max-w-xs"
                 x-data="{
                    closeAt: new Date(@js($checkInCloseAtIso)).getTime(),
                    label: '',
                    tick() {
                        const ms = Math.max(0, this.closeAt - Date.now());
                        const h = Math.floor(ms / 3600000);
                        const m = Math.floor((ms % 3600000) / 60000);
                        this.label = h > 0 ? (h + 'h ' + m + 'm') : (m + 'm');
                    },
                    init() { this.tick(); setInterval(() => this.tick(), 60000); }
                 }">
                <p class="text-center text-sm font-semibold sm:text-left">
                    <span class="mr-1" aria-hidden="true">⏳</span>
                    <span>Time left today: <span x-text="label" class="font-bold"></span></span>
                </p>
                <p class="mt-2 text-center text-xs leading-snug text-white/90 sm:text-left">Complete your check-in before the day ends</p>
            </div>
            @endif
        </div>
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
            <div x-data="{ noteLength: 0 }">
                <textarea
                    wire:model.defer="moodNote"
                    @input="noteLength = $event.target.value.length"
                    class="w-full p-2 border border-gray-300 rounded mb-1 focus:ring-red-500 focus:border-red-500"
                    placeholder="Write a few words about your day... (optional for happy days)"
                    maxlength="500"
                    rows="3"
                ></textarea>
                <div class="flex justify-between items-center mb-2">
                    @error('moodNote')
                        <p class="text-red-500 text-sm">{{ $message }}</p>
                    @else
                        <p class="text-gray-400 text-xs">
                            {{ (int) $moodStatus !== 3 ? 'Required for this mood' : 'Optional' }}
                        </p>
                    @enderror
                    <span class="text-gray-400 text-xs" x-text="noteLength + '/500'"></span>
                </div>
            </div>
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
  
        @if($currentStreak > 0)
        <div class="mb-3 flex items-center gap-2 rounded-lg border border-red-100 bg-red-50 px-3 py-2">
            <span class="text-2xl" aria-hidden="true">🔥</span>
            <div>
                <p class="text-sm font-semibold text-red-600">{{ $currentStreak }}-day streak!</p>
                <p class="text-xs text-gray-500">Keep it going — log your sentiment today.</p>
            </div>
        </div>
        @endif

        <div class="mb-2 grid grid-cols-2 gap-1.5 sm:mb-3 sm:grid-cols-5 sm:gap-2" aria-label="Monthly sentiment summary">
            <div class="flex items-center gap-1.5 rounded border border-gray-200 bg-gray-50 px-2 py-1.5 sm:flex-col sm:items-center sm:justify-center sm:py-2">
                <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-500" aria-hidden="true"></span>
                <div class="min-w-0 sm:text-center">
                    <p class="text-[10px] font-medium uppercase tracking-wide text-gray-500 sm:text-xs">Positive</p>
                    <p class="text-base font-bold text-gray-900 sm:text-lg">{{ $sentimentMonthStats['positive'] ?? 0 }}</p>
                </div>
            </div>
            <div class="flex items-center gap-1.5 rounded border border-gray-200 bg-gray-50 px-2 py-1.5 sm:flex-col sm:items-center sm:justify-center sm:py-2">
                <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-amber-400" aria-hidden="true"></span>
                <div class="min-w-0 sm:text-center">
                    <p class="text-[10px] font-medium uppercase tracking-wide text-gray-500 sm:text-xs">Neutral</p>
                    <p class="text-base font-bold text-gray-900 sm:text-lg">{{ $sentimentMonthStats['neutral'] ?? 0 }}</p>
                </div>
            </div>
            <div class="flex items-center gap-1.5 rounded border border-gray-200 bg-gray-50 px-2 py-1.5 sm:flex-col sm:items-center sm:justify-center sm:py-2">
                <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-[#EB1C24]" aria-hidden="true"></span>
                <div class="min-w-0 sm:text-center">
                    <p class="text-[10px] font-medium uppercase tracking-wide text-gray-500 sm:text-xs">Low</p>
                    <p class="text-base font-bold text-gray-900 sm:text-lg">{{ $sentimentMonthStats['low'] ?? 0 }}</p>
                </div>
            </div>
            <div class="flex items-center gap-1.5 rounded border border-gray-200 bg-gray-50 px-2 py-1.5 sm:flex-col sm:items-center sm:justify-center sm:py-2">
                <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-gray-400" aria-hidden="true"></span>
                <div class="min-w-0 sm:text-center">
                    <p class="text-[10px] font-medium uppercase tracking-wide text-gray-500 sm:text-xs">Out of office</p>
                    <p class="text-base font-bold text-gray-900 sm:text-lg">{{ $sentimentMonthStats['out_of_office'] ?? 0 }}</p>
                </div>
            </div>
            <div class="col-span-2 flex items-center gap-1.5 rounded border border-gray-200 bg-gray-50 px-2 py-1.5 sm:col-span-1 sm:flex-col sm:items-center sm:justify-center sm:py-2">
                <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-gray-400 ring-1 ring-gray-300" aria-hidden="true"></span>
                <div class="min-w-0 sm:text-center">
                    <p class="text-[10px] font-medium uppercase tracking-wide text-gray-500 sm:text-xs">Missed</p>
                    <p class="text-base font-bold text-gray-900 sm:text-lg">{{ $sentimentMonthStats['missed'] ?? 0 }}</p>
                </div>
            </div>
        </div>

      <!-- Filters: org office/dept, then calendar period (‹ year | month ›) -->
    <div class="mb-3 space-y-2">
        @hasanyrole('organisation_user|director')
        @if(auth()->user()->orgId)
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
        <!-- Offices -->
        <select wire:model.live="selectedOffice" class="w-full cursor-pointer rounded-lg border border-gray-200 bg-white py-2.5 pl-3 pr-8 text-[12px] capitalize text-gray-700 shadow-sm focus:border-[#EB1C24] focus:outline-none focus:ring-1 focus:ring-[#EB1C24] sm:text-sm">
            <option value="">All Offices</option>
            @foreach($offices['offices'] ?? [] as $office)
                <option value="{{ $office['officeId'] }}">{{ $office['office'] }}</option>
            @endforeach
        </select>

        <!-- Departments -->
        <select wire:model.live="selectedDepartment" class="w-full cursor-pointer rounded-lg border border-gray-200 bg-white py-2.5 pl-3 pr-8 text-[12px] text-gray-700 shadow-sm focus:border-[#EB1C24] focus:outline-none focus:ring-1 focus:ring-[#EB1C24] sm:text-sm">
            <option value="">All Departments</option>
            @foreach($departments ?? [] as $dep)
                <option value="{{ $dep['id'] }}">{{ $dep['department'] }}</option>
            @endforeach
        </select>
        </div>
        @endif
        @endhasanyrole

        <div
            class="flex w-full min-w-0 items-center justify-between gap-2 sm:gap-3"
            role="group"
            aria-label="Calendar month and year"
        >
            <button
                type="button"
                wire:click="goToPreviousCalendarMonth"
                wire:loading.attr="disabled"
                @disabled(! $this->canGoToPreviousCalendarMonth)
                class="inline-flex h-10 w-10 shrink-0 items-center justify-center self-center rounded-lg border border-gray-200 bg-white text-lg font-medium text-gray-600 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#EB1C24] focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-40"
                title="Previous month"
                aria-label="Previous month"
            >&lsaquo;</button>

            <div class="flex min-w-0 flex-1 flex-wrap items-center justify-center gap-2 sm:gap-2.5">

            <select
                wire:model.live="month"
                wire:loading.class="opacity-50"
                class="w-auto min-w-[7.5rem] shrink-0 cursor-pointer rounded-lg border border-gray-200 bg-white py-2.5 pl-3 pr-8 text-center text-[12px] text-gray-700 shadow-sm focus:border-[#EB1C24] focus:outline-none focus:ring-1 focus:ring-[#EB1C24] sm:min-w-[8.5rem] sm:text-sm"
                aria-label="Month"
            >
            @php
                $currentYear = now()->year;
                $currentMonth = now()->month;
                $maxMonth = ($this->year ?? $currentYear) == $currentYear ? $currentMonth : 12;
            @endphp
            @foreach(range(1, $maxMonth) as $m)
                <option value="{{ $m }}" {{ $m == ($this->month ?? $currentMonth) ? 'selected' : '' }}>
                    {{ date('F', mktime(0,0,0,$m,1)) }}
                </option>
            @endforeach
            </select>
            <select
                wire:model.live="year"
                wire:loading.class="opacity-50"
                class="w-auto min-w-[5.25rem] shrink-0 cursor-pointer rounded-lg border border-gray-200 bg-white py-2.5 pl-3 pr-8 text-center text-[12px] text-gray-700 shadow-sm focus:border-[#EB1C24] focus:outline-none focus:ring-1 focus:ring-[#EB1C24] sm:min-w-[6.5rem] sm:text-sm"
                aria-label="Year"
            >
                @forelse($orgYearList ?? [] as $y)
                    <option value="{{ $y }}" {{ (int) $y === (int) ($this->year ?? now()->year) ? 'selected' : '' }}>{{ $y }}</option>
                @empty
                    <option value="{{ now()->year }}">{{ now()->year }}</option>
                @endforelse
            </select>
            </div>

            <button
                type="button"
                wire:click="goToNextCalendarMonth"
                wire:loading.attr="disabled"
                @disabled(! $this->canGoToNextCalendarMonth)
                class="inline-flex h-10 w-10 shrink-0 items-center justify-center self-center rounded-lg border border-gray-200 bg-white text-lg font-medium text-gray-600 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#EB1C24] focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-40"
                title="Next month"
                aria-label="Next month"
            >&rsaquo;</button>
        </div>
    </div>

  <div class="w-full overflow-x-auto pb-2 sm:pb-3" wire:key="calendar-{{ $month }}-{{ $year }}-{{ $sentimentCalendarScope }}-{{ auth()->user()->timezone ?? 'default' }}">
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
            $todayDate = \App\Helpers\TimezoneHelper::carbon(null, $userTimezone)->startOfDay();
            
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
                                    // Non-working days: org schedule, or Sat/Sun when user has no org (matches month stats)
                                    $isCalendarNonWorkingDay = false;
                                    if (! $isBeforeRegistration) {
                                        $dayShort = $dayDate->format('D');
                                        if ($isOrgUser) {
                                            if (! in_array($dayShort, $orgWorkingDays, true)) {
                                                $isCalendarNonWorkingDay = true;
                                            }
                                        } elseif (in_array($dayShort, ['Sat', 'Sun'], true)) {
                                            $isCalendarNonWorkingDay = true;
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
                                        } elseif ($isCalendarNonWorkingDay) {
                                            // Non-working day — blank (dash), not "missed"
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
                                            // Past working day, no submission — missed (branded asset)
                                            $img = 'sentiment-missed.svg';
                                        }
                                    } elseif (!$isBeforeRegistration && $dayDate->isSameDay($todayDate)) {
                                        // Today: check leave status first, then check if data exists
                                        if ($isCalendarNonWorkingDay) {
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
                                        if ($isCalendarNonWorkingDay) {
                                            $img = null;
                                        } else {
                                            $img = $isFutureLeave ? 'leave-office.svg' : null;
                                        }
                                    }

                                    $moodLabel = 'No entry';
                                    if (($isLeaveDay ?? false) || ($img === 'leave-office.svg')) {
                                        $moodLabel = 'Out of office';
                                    } elseif ($img === 'sentiment-missed.svg') {
                                        $moodLabel = 'Missed';
                                    } elseif ($mood !== null && $score !== null) {
                                        if ($score > 80) {
                                            $moodLabel = 'Great';
                                        } elseif ($score >= 51) {
                                            $moodLabel = 'Okay';
                                        } elseif ($score >= 0) {
                                            $moodLabel = 'Low';
                                        }
                                    }

                                    $modalPayload = [
                                        'day' => $day,
                                        'score' => $score,
                                        'moodLabel' => $moodLabel,
                                        'desc' => $desc ?? '',
                                        'img' => $img ?? '-',
                                    ];
                                @endphp
                                <td class="p-1" wire:key="cal-{{ $year }}-{{ $month }}-{{ str_pad((string) $day, 2, '0', STR_PAD_LEFT) }}-{{ $sentimentCalendarScope }}">
                                    <div class="mx-auto flex h-14 w-14 flex-col items-center justify-center rounded border transition {{ $dayDate->isSameDay($todayDate) ? 'border-[#EB1C24] bg-[#FEEBEE] ring-1 ring-[#EB1C24]' : 'border-gray-200 bg-white hover:bg-gray-50' }} {{ ($dayDate->lte($todayDate) || $img === 'leave-office.svg' || $img === 'sentiment-missed.svg') ? 'cursor-pointer' : 'cursor-default' }}"
                                        @if($dayDate->lte($todayDate) || $img === 'leave-office.svg' || $img === 'sentiment-missed.svg')
                                            @click='openModal = true; modalData = @json($modalPayload)'
                                        @endif
                                    >
                                        <span class="text-sm font-medium text-gray-600">{{ $day }}</span>
                                        @if(isset($isBeforeRegistration) && $isBeforeRegistration)
                                            <span class="mt-1 text-sm text-gray-400">-</span>
                                        @elseif($isCalendarNonWorkingDay)
                                            <span class="mt-1 text-sm text-gray-400">-</span>
                                        @else
                                            @if($img)
                                                <img src="{{ asset('images/' . $img) }}" class="mt-1 h-5 w-5" alt="">
                                            @else
                                                <span class="mt-1 text-xs text-gray-300">&mdash;</span>
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
        <div class="mt-2 flex flex-wrap items-center justify-center gap-x-2 gap-y-1 border-t border-gray-100 pt-2 text-[10px] text-gray-600 sm:justify-start sm:text-xs" aria-label="Calendar legend">
            <span class="inline-flex items-center gap-1"><img src="{{ asset('images/happy.svg') }}" alt="" class="h-3.5 w-3.5"> Great</span>
            <span class="inline-flex items-center gap-1"><img src="{{ asset('images/avarge.svg') }}" alt="" class="h-3.5 w-3.5"> Okay</span>
            <span class="inline-flex items-center gap-1"><img src="{{ asset('images/sad.svg') }}" alt="" class="h-3.5 w-3.5"> Low</span>
            <span class="inline-flex items-center gap-1"><span class="inline-block h-3.5 w-3.5 rounded border border-[#EB1C24] bg-[#FEEBEE]" aria-hidden="true"></span> Today</span>
            <span class="inline-flex items-center gap-1"><img src="{{ asset('images/leave-office.svg') }}" alt="" class="h-3.5 w-3.5"> Out of office</span>
            <span class="inline-flex items-center gap-1"><img src="{{ asset('images/sentiment-missed.svg') }}" alt="" class="h-3.5 w-3.5"> Missed</span>
            <span class="inline-flex items-center gap-1"><span class="font-medium text-gray-400">&mdash;</span> Blank</span>
        </div>
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
                <p class="text-lg font-medium text-gray-700 mb-2">Mood: <span x-text="modalData.moodLabel || 'No entry'" class="font-semibold"></span></p>
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

    <div class="min-w-0">
        @livewire('summary')
    </div>

</div>

@endif

</div>

