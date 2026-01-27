<div class="flex flex-col border border-gray-100 rounded-md summary-info" x-data="{ openFilter: false }">
    <div class="flex px-4 py-3 sm:px-6 sm:py-4 justify-between items-center w-full flex-wrap sm:flex-nowrap bg-white border-b border-gray-200">
        <h3 class="text-lg sm:text-2xl text-[#EB1C24] font-bold mb-1 sm:mb-0 flex items-center gap-2">
            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            Daily Summary
        </h3>

        <div x-data="{ open: false }" x-on:summary-saved.window="open = false">

            <!-- Trigger Button -->
            <button class="inline-flex items-center gap-2 border border-gray-300 text-sm sm:text-base px-4 py-2 rounded-lg bg-white hover:bg-gray-50 text-gray-700 font-medium transition-colors duration-200 shadow-sm hover:shadow" @click="open = true">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                </svg>
                Filter By Date
            </button>

            <!-- Modal -->
            <div x-show="open" x-cloak
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 backdrop-blur-sm">
                <div class="bg-white w-[90%] max-w-[500px] rounded-xl shadow-2xl transform transition-all"
                     x-data="{ type: @entangle('filterType') }"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     @click.away="if (!$event.target.closest('.flatpickr-calendar')) open = false">

                    <!-- Header -->
                    <div class="flex justify-between items-center border-b border-gray-200 px-6 py-4 bg-gradient-to-r from-gray-50 to-white">
                        <h3 class="font-bold text-xl text-gray-800 flex items-center gap-2">
                            <svg class="w-5 h-5 text-[#EB1C24]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                            </svg>
                            Filter Options
                        </h3>
                        <button class="text-sm font-medium text-[#EB1C24] hover:text-red-700 transition-colors duration-200" wire:click="resetFilters">Reset All</button>
                    </div>

                    <div class="flex min-h-[300px]">
                        <!-- Left Tabs -->
                        <div class="w-1/3 border-r border-gray-200 bg-gray-50">
                            <div class="p-4 text-[#EB1C24] bg-red-50 font-semibold border-b border-red-100">
                                Date Range
                            </div>
                        </div>

                        <!-- Right Content -->
                        <div class="w-2/3 p-6 space-y-3 overflow-y-auto max-h-[400px]">
                            <template x-for="option in ['all','today','this_week','last_7_days','previous_week','this_month','previous_month','custom']" :key="option">
                                <label class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors duration-200 group">
                                    <input type="radio" :value="option" class="w-4 h-4 text-[#EB1C24] focus:ring-[#EB1C24] focus:ring-2 border-gray-300" x-model="type">
                                    <span class="text-sm font-medium text-gray-700 group-hover:text-gray-900" x-text="option.replace('_',' ').replace(/\b\w/g, l => l.toUpperCase())"></span>
                                </label>
                            </template>

                            <!-- Custom Dates -->
                            <div class="flex flex-col sm:flex-row gap-4 mt-4 p-4 bg-gray-50 rounded-lg border border-gray-200" x-show="type === 'custom'" x-cloak>
                                <div class="flex flex-col flex-1">
                                    <label class="text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Start Date</label>
                                    <input
                                        type="text"
                                        wire:model="startDate"
                                        placeholder="Select start date"
                                        x-ref="startDate"
                                        x-init="$nextTick(() => {
                                            flatpickr($refs.startDate, {
                                                dateFormat: 'Y-m-d',
                                                minDate: '{{ auth()->user()->created_at->toDateString() }}',
                                                maxDate: '{{ today()->toDateString() }}',
                                            });
                                        })"
                                        class="border border-gray-300 px-4 py-2.5 rounded-lg focus:ring-2 focus:ring-[#EB1C24] focus:border-[#EB1C24] transition-all duration-200"
                                    >
                                    @error('startDate')
                                        <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="flex flex-col flex-1">
                                    <label class="text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">End Date</label>
                                    <input
                                        type="text"
                                        wire:model="endDate"
                                        placeholder="Select end date"
                                        x-ref="endDate"
                                        x-init="$nextTick(() => {
                                            flatpickr($refs.endDate, {
                                                dateFormat: 'Y-m-d',
                                                minDate: '{{ auth()->user()->created_at->toDateString() }}',
                                                maxDate: '{{ today()->toDateString() }}',
                                            });
                                        })"
                                        class="border border-gray-300 px-4 py-2.5 rounded-lg focus:ring-2 focus:ring-[#EB1C24] focus:border-[#EB1C24] transition-all duration-200"
                                    >
                                    @error('endDate')
                                        <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex justify-end gap-3 border-t border-gray-200 px-6 py-4 bg-gray-50">
                        <button @click="open = false" class="px-5 py-2.5 rounded-lg bg-white border border-gray-300 text-gray-700 font-medium hover:bg-gray-50 transition-colors duration-200">
                            Close
                        </button>
                        <button 
                            @click="
                                $wire.filterType = type;
                                $wire.loadSummary().then(() => {
                                    open = false;
                                });
                            "
                            wire:loading.attr="disabled"
                            wire:target="loadSummary"
                            class="px-5 py-2.5 rounded-lg bg-[#EB1C24] text-white font-medium hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 shadow-sm hover:shadow-md flex items-center gap-2">
                            <span wire:loading.remove wire:target="loadSummary" class="flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Apply Filter
                            </span>
                            <span wire:loading wire:target="loadSummary" class="flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Loading...
                            </span>
                        </button>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <!-- Summary Content -->
    <div class="flex flex-col overflow-auto bg-gradient-to-b from-gray-50 to-gray-100 p-4 sm:p-6 w-full h-[539px] space-y-3">
        @forelse($summary as $item)
            <div class="group flex bg-white rounded-lg shadow-sm hover:shadow-md transition-all duration-200 items-start w-full p-4 border border-gray-100 hover:border-gray-200">
                <!-- Icon Section -->
                <div class="sentiment-view flex-shrink-0 mr-4">
                    @if($item['status'] === 'Missed')
                        <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    @else
                        <div class="w-10 h-10 rounded-full flex items-center justify-center overflow-hidden">
                            <img src="{{ asset('images/' . $item['image']) }}" alt="Sentiment" class="w-full h-full object-cover">
                        </div>
                    @endif
                </div>
                
                <!-- Content Section -->
                <div class="summary-content flex-1 min-w-0">
                    <div class="flex items-center gap-3 flex-wrap mb-2">
                        <!-- Date -->
                        <span class="date-time text-sm sm:text-base font-medium text-gray-700 whitespace-nowrap">
                            {{ $item['date'] }}
                        </span>
                        
                        <!-- Status Badge -->
                        @if($item['status'] === 'Present')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs sm:text-sm font-semibold bg-green-100 text-green-800 border border-green-200">
                                <svg class="w-3 h-3 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                {{ $item['status'] }}
                            </span>
                        @elseif($item['status'] === 'Out of office')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs sm:text-sm font-semibold bg-blue-100 text-blue-800 border border-blue-200">
                                <svg class="w-3 h-3 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                </svg>
                                OOO
                            </span>
                        @elseif($item['status'] === 'Missed')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs sm:text-sm font-semibold bg-red-100 text-red-800 border border-red-200">
                                <svg class="w-3 h-3 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                </svg>
                                Missed
                            </span>
                        @endif
                    </div>
                    
                    <!-- Description -->
                    @if(!empty($item['description']))
                        <p class="text-gray-700 text-sm sm:text-base leading-relaxed first-letter:uppercase mt-1">
                            {{ $item['description'] }}
                        </p>
                    @endif
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center h-full py-12">
                <svg class="w-16 h-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p class="text-gray-500 text-lg font-medium">No summary available for this filter.</p>
                <p class="text-gray-400 text-sm mt-2">Try selecting a different date range.</p>
            </div>
        @endforelse
    </div>
</div>
