<div class="flex flex-wrap border border-gray-100 rounded-md w-full" wire:key="weekly-summary-{{ auth()->id() }}">
    <div class="flex px-3 py-2 bg-white w-full min-h-[65px]">
        <h3 class="text-lg sm:text-2xl text-[#EB1C24] font-bold mb-1 sm:mb-0 flex items-center gap-2">Weekly Summary</h3>
    </div>
    <div class="relative w-full">
        <div class="p-4 pt-1 bg-[#F8F8F8] ">
        <!-- Header -->

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


        <!-- Filters -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 my-4">
            <select id="weekly-month-select"
                    class="border border-gray-200 rounded-md py-2 px-2 bg-white text-[12px] sm:text-[14px] text-[#333] focus:ring-red-500 focus:border-red-500 w-full">
                @foreach($validMonths as $m)
                    <option value="{{ $m['value'] }}" @selected((int)$selectedMonth === (int)$m['value'])>{{ $m['name'] }}</option>
                @endforeach
            </select>

            <select id="weekly-year-select"
                    class="border border-gray-200 rounded-md py-2 px-2 bg-white text-[12px] sm:text-[14px] text-[#333] focus:ring-red-500 focus:border-red-500 w-full">
                @foreach($validYears as $y)
                    <option value="{{ $y }}" @selected((int)$selectedYear === (int)$y)>{{ $y }}</option>
                @endforeach
            </select>
        </div>

        <!-- Summary Section -->
        <div id="weekly-summaries-container">
            @if(count($weeklySummaries) > 0)
                @php $allEmpty = true; @endphp
                @foreach ($weeklySummaries as $index => $summary)
                    <div class="border border-gray-100 p-2 sm:p-4 rounded-lg mb-3 bg-gray-50 hover:bg-gray-100 transition">
                        <h4 class="font-semibold text-red-700 text-[12px] sm:text-sm mb-1" style="line-height: 1.3;">
                            Week {{ $summary['week'] }}
                            @if(!empty($summary['weekLabel']))
                                <span class="text-gray-500">({{ $summary['weekLabel'] }})</span>
                            @endif
                        </h4>
                        <p class="text-gray-700 text-[12px] sm:text-[14px] leading-relaxed" style="line-height: 1.35;">
                            @if(!empty($summary['summary']))
                                {{ $summary['summary'] }}
                                @php $allEmpty = false; @endphp
                            @else
                                <span class="text-gray-500 italic">Summary is not available.</span>
                            @endif
                        </p>
                    </div>
                @endforeach
            @else
                <div class="p-0 sm:p-4 text-center text-gray-500">
                    <p>Summary is not available! <br>Your weekly sentiment summary is generated every Sunday. <br>
                    You'll be able to view your updated summary after that.</p>
                </div>
            @endif
        </div>

        <script>
        (function() {
            const weeklyMonthSelect = document.getElementById('weekly-month-select');
            const weeklyYearSelect = document.getElementById('weekly-year-select');
            const weeklyContainer = document.getElementById('weekly-summaries-container');
            let isLoading = false;

            function loadWeeklySummaries(year, month) {
                if (isLoading) return;
                isLoading = true;
                
                // Show loading
                weeklyContainer.innerHTML = '<div class="p-4 text-center"><div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-red-500"></div><p class="mt-2 text-gray-500">Loading...</p></div>';

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const url = `/api/weekly-summaries?year=${year}&month=${month}`;

                fetch(url, {
                    method: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    isLoading = false;
                    console.log('Weekly summaries loaded:', data);
                    if (data.status && data.data) {
                        const summaries = data.data.weeklySummaries || [];
                        const validMonths = data.data.validMonths || [];
                        
                        // Update month dropdown if needed
                        if (validMonths.length > 0) {
                            const currentMonth = parseInt(weeklyMonthSelect.value);
                            weeklyMonthSelect.innerHTML = '';
                            validMonths.forEach(function(m) {
                                const option = document.createElement('option');
                                option.value = m.value;
                                option.textContent = m.name;
                                if (m.value == currentMonth) {
                                    option.selected = true;
                                }
                                weeklyMonthSelect.appendChild(option);
                            });
                        }
                        
                        // Update summaries
                        if (summaries.length > 0) {
                            let html = '';
                            summaries.forEach(function(summary) {
                                html += '<div class="border border-gray-100 p-2 sm:p-4 rounded-lg mb-3 bg-gray-50 hover:bg-gray-100 transition">';
                                html += '<h4 class="font-semibold text-red-700 text-[12px] sm:text-sm mb-1" style="line-height: 1.3;">';
                                html += 'Week ' + summary.week;
                                if (summary.weekLabel) {
                                    html += ' <span class="text-gray-500">(' + summary.weekLabel + ')</span>';
                                }
                                html += '</h4>';
                                html += '<p class="text-gray-700 text-[12px] sm:text-[14px] leading-relaxed" style="line-height: 1.35;">';
                                if (summary.summary) {
                                    html += summary.summary;
                                } else {
                                    html += '<span class="text-gray-500 italic">Summary is not available.</span>';
                                }
                                html += '</p></div>';
                            });
                            weeklyContainer.innerHTML = html;
                        } else {
                            weeklyContainer.innerHTML = '<div class="p-0 sm:p-4 text-center text-gray-500"><p>Summary is not available! <br>Your weekly sentiment summary is generated every Sunday. <br>You\'ll be able to view your updated summary after that.</p></div>';
                        }
                    }
                })
                .catch(error => {
                    isLoading = false;
                    console.error('Error loading weekly summaries:', error);
                    weeklyContainer.innerHTML = '<div class="p-0 sm:p-4 text-center text-red-500"><p>Error loading summaries. Please try again.</p></div>';
                });
            }

            if (weeklyMonthSelect && weeklyYearSelect) {
                weeklyMonthSelect.addEventListener('change', function() {
                    const year = parseInt(weeklyYearSelect.value);
                    const month = parseInt(this.value);
                    loadWeeklySummaries(year, month);
                });

                weeklyYearSelect.addEventListener('change', function() {
                    const year = parseInt(this.value);
                    const month = parseInt(weeklyMonthSelect.value);
                    loadWeeklySummaries(year, month);
                });
            }
        })();
        </script>
        </div>
    </div>
</div>

