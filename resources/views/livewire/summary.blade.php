<div class="flex flex-col border border-gray-100 rounded-md summary-info" x-data="{ openFilter: false }">
    <div class="flex px-3 py-4 justify-between w-full flex-wrap sm:flex-nowrap">
        <h3 class="text-[14px] sm:text-[20px] text-[#EB1C24] font-semibold mb-2 sm:mb-0">Daily Summary</h3>

        <div x-data="{ open: false }" x-on:summary-saved.window="open = false">

            <!-- Trigger Button -->
            <button class="border text-[12px] sm:text-[14px] px-2 sm:px-3 py-1 rounded bg-gray-100 hover:bg-gray-200" @click="open = true">
                Filter By Date
            </button>

            <!-- Modal -->
            <div x-show="open" x-cloak
                 class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40 z-50">
                <div class="bg-white w-[450px] rounded-md shadow-lg" 
                     x-data="{ type: @entangle('filterType') }"
                     @click.away="if (!$event.target.closest('.flatpickr-calendar')) open = false">

                    <!-- Header -->
                    <div class="flex justify-between items-center border-b px-4 py-3">
                        <h3 class="font-semibold text-lg">Filter</h3>
                        <button class="text-sm text-red-500" wire:click="resetFilters">Reset All</button>
                    </div>

                    <div class="flex">
                        <!-- Left Tabs -->
                        <div class="w-1/3 border-r">
                            <div class="p-3 text-red-600 bg-red-50 font-medium">
                                Date
                            </div>
                        </div>

                        <!-- Right Content -->
                        <div class="w-2/3 p-4 space-y-2">
                            <template x-for="option in ['all','today','this_week','last_7_days','previous_week','this_month','previous_month','custom']" :key="option">
                                <label class="flex items-center space-x-2">
                                    <input type="radio" :value="option" class="focus:ring-red-500 text-red-600" x-model="type">
                                    <span x-text="option.replace('_',' ').replace(/\b\w/g, l => l.toUpperCase())"></span>
                                </label>
                            </template>

                            <!-- Custom Dates -->
                            <div class="flex space-x-2 mt-2" x-show="type === 'custom'" x-cloak>
                                <div class="flex flex-col w-1/2">
                                    <input
                                        type="text"
                                        wire:model="startDate"
                                        placeholder="dd-mm-yyyy"
                                        x-ref="startDate"
                                        x-init="$nextTick(() => {
                                            flatpickr($refs.startDate, {
                                                dateFormat: 'Y-m-d',
                                                minDate: '{{ auth()->user()->created_at->toDateString() }}',
                                                maxDate: '{{ today()->toDateString() }}',
                                            });
                                        })"
                                        class="border px-2 py-1 rounded focus:ring-red-500 focus:border-red-500"
                                    >
                                    @error('startDate')
                                        <span class="text-red-500 text-sm">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="flex flex-col w-1/2">
                                    <input
                                        type="text"
                                        wire:model="endDate"
                                        placeholder="dd-mm-yyyy"
                                        x-ref="endDate"
                                        x-init="$nextTick(() => {
                                            flatpickr($refs.endDate, {
                                                dateFormat: 'Y-m-d',
                                                minDate: '{{ auth()->user()->created_at->toDateString() }}',
                                                maxDate: '{{ today()->toDateString() }}',
                                            });
                                        })"
                                        class="border px-2 py-1 rounded focus:ring-red-500 focus:border-red-500"
                                    >
                                    @error('endDate')
                                        <span class="text-red-500 text-sm">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex justify-end gap-2 border-t px-4 py-3">
                        <button @click="open = false" class="px-4 py-2 rounded bg-gray-200">
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
                            class="px-4 py-2 rounded bg-red-500 text-white disabled:opacity-50 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="loadSummary">Save</span>
                            <span wire:loading wire:target="loadSummary">Loading...</span>
                        </button>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <!-- Summary Content -->
    <div class="flex flex-wrap overflow-auto bg-[#F8F8F8] p-4 w-full h-[539px]">
        @forelse($summary as $item)
            <div class="flex bg-white p-3 w-full rounded-md mb-3 flex-wrap sm:flex-nowrap">
                <div class="sentiment-view  sm:px-3 sm:py-3">
                    <img src="{{ asset('images/' . $item['image']) }}" class="max-w-[26px] max-h-[26px]">
                </div>
                <div class="summary-content pt-2 sm:pt-0 pl-0 sm:pl-2">
                    @if($item['status'] === 'Present')
                        <span class="date-time text-[12px] sm:text-[14px] text-[#01010180]">
                            {{ $item['date'] }}
                            <span class="ml-1 p-1 bg-[#7A9865] rounded-sm px-2 text-white">
                                {{ $item['status'] }}
                            </span>
                        </span>
                    @elseif($item['status'] === 'Out of office')
                        <span class="date-time text-[12px] sm:text-[14px] text-[#01010180]">
                            {{ $item['date'] }}
                            <span class="ml-1 p-1 bg-blue-500 rounded-sm px-2 text-white">
                                OOO
                            </span>
                        </span>
                    @elseif($item['status'] === 'Missed')
                        <span class="date-time text-[12px] sm:text-[14px] text-[#01010180]">
                            {{ $item['date'] }}
                            <span class="ml-1 p-1 bg-red-500 rounded-sm px-2 text-white">
                                Missed
                            </span>
                        </span>
                    @endif
                    <p class="text-[#010101] text-[12px] sm:text-[14px] lg:text-[16px] pl-0  sm:pl-3 sm:border-l leading-[1.3] mt-2 border-gray-200 first-letter:uppercase">
                        {{ $item['description'] }}
                  
                    </p>
                </div>
            </div>
        @empty
            <p class="text-gray-500">No summary available for this filter.</p>
        @endforelse
    </div>
</div>
