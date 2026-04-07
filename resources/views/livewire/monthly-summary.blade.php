<div class="flex flex-wrap border border-gray-100 rounded-md w-full" wire:key="monthly-summary-{{ auth()->id() }}">
    <div class="flex px-3 py-2 bg-white w-full min-h-[65px]">
        <h3 class="text-lg sm:text-2xl text-[#EB1C24] font-bold mb-1 sm:mb-0 flex items-center gap-2">Monthly Summary</h3>
    </div>
    <div class="relative w-full">
        <div class="p-4 pt-1 bg-[#F8F8F8] ">
      
            <!-- Filters -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 my-4">
                <select id="monthly-month-select"
                        class="border border-gray-200 rounded-md py-2 px-2 bg-white text-[12px] sm:text-[14px] text-[#333] focus:ring-red-500 focus:border-red-500 w-full">
                    @foreach($validMonths as $m)
                        <option value="{{ $m['value'] }}" @selected((int)$selectedMonth === (int)$m['value'])>{{ $m['name'] }}</option>
                    @endforeach
                </select>

                <select id="monthly-year-select"
                        class="border border-gray-200 rounded-md py-2 px-2 bg-white text-[12px] sm:text-[14px] text-[#333] focus:ring-red-500 focus:border-red-500 w-full">
                    @foreach($validYears as $y)
                        <option value="{{ $y }}" @selected((int)$selectedYear === (int)$y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Summary Section -->
            <div id="monthly-summaries-container">
                @if(count($monthlySummaries) > 0)
                    @foreach ($monthlySummaries as $index => $summary)
                        <div class="border border-gray-100 p-2 sm:p-4 rounded-lg mb-3 bg-gray-50 hover:bg-gray-100 transition">
                            <p class="text-gray-700 text-[12px] sm:text-[14px] leading-relaxed" style="line-height: 1.35;">
                                @if(!empty($summary['summary']))
                                    {{ $summary['summary'] }}
                                @else
                                    <span class="text-gray-500 italic">Summary is not available.</span>
                                @endif
                            </p>
                        </div>
                    @endforeach
                @else
                    <div class="p-0 sm:p-4 text-center text-gray-500">
                        <p>Your monthly sentiment summary is generated at the end of each month. <br>
                        You'll be able to view your updated summary after that.</p>
                    </div>
                @endif
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
</div>

@push('scripts')
<script>
    (function() {
        const monthlyMonthSelect = document.getElementById('monthly-month-select');
        const monthlyYearSelect = document.getElementById('monthly-year-select');
        const monthlyContainer = document.getElementById('monthly-summaries-container');
        let isLoading = false;

        function loadMonthlySummaries(year, month) {
            if (isLoading) return;
            isLoading = true;
            
            // Show loading
            if (monthlyContainer) {
                monthlyContainer.innerHTML = '<div class="p-4 text-center"><div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-red-500"></div><p class="mt-2 text-gray-500">Loading...</p></div>';

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const url = `/api/monthly-summary?year=${year}&month=${month}`;

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
                    console.log('Monthly summaries loaded:', data);
                    if (data.status && data.data) {
                        const summaries = data.data.monthlySummaries || [];
                        const validMonths = data.data.validMonths || [];
                        
                        // Update month dropdown if needed
                        if (validMonths.length > 0 && monthlyMonthSelect) {
                            const currentMonth = parseInt(monthlyMonthSelect.value);
                            monthlyMonthSelect.innerHTML = '';
                            validMonths.forEach(function(m) {
                                const option = document.createElement('option');
                                option.value = m.value;
                                option.textContent = m.name;
                                if (m.value == currentMonth) {
                                    option.selected = true;
                                }
                                monthlyMonthSelect.appendChild(option);
                            });
                        }
                        
                        // Update summaries
                        if (summaries.length > 0) {
                            let html = '';
                            summaries.forEach(function(summary) {
                                html += '<div class="border border-gray-100 p-2 sm:p-4 rounded-lg mb-3 bg-gray-50 hover:bg-gray-100 transition">';
                                html += '<p class="text-gray-700 text-[12px] sm:text-[14px] leading-relaxed" style="line-height: 1.35;">';
                                if (summary.summary) {
                                    html += summary.summary;
                                } else {
                                    html += '<span class="text-gray-500 italic">Summary is not available.</span>';
                                }
                                html += '</p></div>';
                            });
                            monthlyContainer.innerHTML = html;
                        } else {
                            monthlyContainer.innerHTML = '<div class="p-0 sm:p-4 text-center text-gray-500"><p>Your monthly sentiment summary is generated at the end of each month. <br>You\'ll be able to view your updated summary after that.</p></div>';
                        }
                    }
                })
                .catch(error => {
                    isLoading = false;
                    console.error('Error loading monthly summaries:', error);
                    if (monthlyContainer) {
                        monthlyContainer.innerHTML = '<div class="p-0 sm:p-4 text-center text-red-500"><p>Error loading summaries. Please try again.</p></div>';
                    }
                });
            }
        }

        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                if (monthlyMonthSelect && monthlyYearSelect) {
                    monthlyMonthSelect.addEventListener('change', function() {
                        const year = parseInt(monthlyYearSelect.value);
                        const month = parseInt(this.value);
                        loadMonthlySummaries(year, month);
                    });

                    monthlyYearSelect.addEventListener('change', function() {
                        const year = parseInt(this.value);
                        const month = parseInt(monthlyMonthSelect.value);
                        loadMonthlySummaries(year, month);
                    });
                }
            });
        } else {
            if (monthlyMonthSelect && monthlyYearSelect) {
                monthlyMonthSelect.addEventListener('change', function() {
                    const year = parseInt(monthlyYearSelect.value);
                    const month = parseInt(this.value);
                    loadMonthlySummaries(year, month);
                });

                monthlyYearSelect.addEventListener('change', function() {
                    const year = parseInt(this.value);
                    const month = parseInt(monthlyMonthSelect.value);
                    loadMonthlySummaries(year, month);
                });
            }
        }
    })();
</script>
@endpush