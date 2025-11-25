<div>
    <div class="relative">
        <div class="p-4 bg-white rounded-lg shadow">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row items-center justify-between px-0 py-0 sm:px-4 sm:py-4 bg-white rounded-t-lg sm:shadow-md border-b border-gray-200">
                <h3 class="text-[14px] sm:text-2xl font-bold text-red-600 tracking-wide">
                    Monthly Summary
                </h3>
            </div>

            <!-- Filters -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 my-4">
                <select wire:model="selectedMonth" wire:change="loadSummariesFromDatabase" class="border border-gray-200 rounded-md py-2 px-2 bg-white text-[12px] sm:text-[14px] text-[#333] w-full">
                    @foreach($validMonths as $m)
                        <option value="{{ $m['value'] }}">{{ $m['name'] }}</option>
                    @endforeach
                </select>

                <select wire:model="selectedYear" wire:change="loadSummariesFromDatabase" class="border border-gray-200 rounded-md py-2 px-2 bg-white text-[12px] sm:text-[14px] text-[#333] w-full">
                    @foreach($validYears as $y)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Summary Section -->
            <div>
                @if(count($monthlySummaries) > 0)
                    @foreach ($monthlySummaries as $summary)
                        <div class="border border-gray-100 p-2 sm:p-4 rounded-lg mb-3 bg-gray-50 hover:bg-gray-100 transition">
                            <p class="text-gray-700 text-[12px] sm:text-[14px] leading-relaxed" style="line-height: 1.35;">
                                @if(!empty($summary['summary']))
                                    {{ $summary['summary'] }}
                                @else
                                    <span class="text-gray-500 italic">Report not generated yet.</span>
                                @endif
                            </p>
                        </div>
                    @endforeach
                @else
                    <div class="p-0 sm:p-4 text-center text-gray-500">
                        <p>Your monthly sentiment summary is generated at the end of each month. <br>
                        You’ll be able to view your updated summary after that.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Loader Overlay -->
    <div wire:loading wire:target="selectedMonth,selectedYear"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="flex flex-col items-center space-y-2 p-4 rounded-lg">
            <svg class="animate-spin h-14 w-14 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <span class="text-white text-lg font-semibold">Refreshing...</span>
        </div>
    </div>
</div>