<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            My Personality Type Results
        </h2>
    </x-slot>

    <div class="bg-white p-6 rounded shadow">
        <div class="mb-4 flex flex-wrap gap-4">
            <a href="{{ route('connecting.personality-type') }}" 
               class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg font-semibold transition-colors inline-flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Retake Assessment
            </a>
            <a href="{{ route('connecting.team-role-map') }}" 
               class="bg-purple-500 hover:bg-purple-600 text-white px-6 py-2 rounded-lg font-semibold transition-colors inline-flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                Team Role Map Assessment
            </a>
        </div>

        @if($latestDateCarbon)
            <p class="text-sm text-gray-600 mb-4">Assessment Date: {{ $latestDateCarbon->format('F d, Y') }}</p>
        @endif

        @if($results->isEmpty())
            <div class="text-center py-8">
                <p class="text-gray-500 mb-4">You haven't completed the Personality Type assessment yet.</p>
                <a href="{{ route('connecting.personality-type') }}" 
                   class="bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-lg font-semibold inline-block">
                    Take Assessment Now
                </a>
            </div>
        @else
            <!-- Results Graph/Chart -->
            <div class="mb-8">
                <h3 class="text-xl font-semibold mb-6">Your Personality Profile</h3>
                
                <!-- Bar Graph Visualization -->
                <div class="bg-white border border-gray-200 rounded-lg p-6 mb-6">
                    <div class="space-y-5">
                        @foreach($results->sortByDesc('percentage') as $result)
                            <div class="relative">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-3">
                                        <span class="font-semibold text-sm text-gray-700 min-w-[80px]">
                                            {{ strtoupper($result->dimension_key ?? 'N/A') }}
                                        </span>
                                        <span class="text-sm text-gray-600">
                                            {{ $result->personalityTypeValue->title ?? ucfirst(str_replace('_', ' ', $result->dimension_key ?? 'Unknown')) }}
                                        </span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-2xl font-bold text-red-600">{{ number_format($result->percentage ?? 0, 2) }}%</span>
                                    </div>
                                </div>
                                <!-- Progress Bar with Gridlines -->
                                <div class="relative w-full bg-gray-200 rounded-full h-8 overflow-hidden border border-gray-300">
                                    <!-- Gridlines every 10% -->
                                    <div class="absolute inset-0 flex">
                                        @for($i = 0; $i <= 10; $i++)
                                            <div class="flex-1 border-l border-gray-300 {{ $i == 0 ? 'border-l-0' : '' }}"></div>
                                        @endfor
                                    </div>
                                    <!-- Progress Fill -->
                                    <div class="bg-red-500 h-full rounded-full transition-all duration-500 relative z-10 flex items-center justify-end pr-2" 
                                         style="width: {{ min($result->percentage ?? 0, 100) }}%">
                                        @if(($result->percentage ?? 0) > 15)
                                            <span class="text-white text-xs font-semibold">{{ number_format($result->percentage ?? 0, 1) }}%</span>
                                        @endif
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 mt-1 ml-[83px]">Score: {{ $result->score ?? 0 }} points</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Detailed Breakdown (Text Format) -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-6">
                    <h4 class="font-semibold text-lg mb-4">Detailed Breakdown</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        @foreach($results->sortByDesc('percentage') as $result)
                            <div class="flex justify-between items-center p-2 bg-white rounded border border-gray-200">
                                <span class="font-medium text-gray-700">{{ strtoupper($result->dimension_key ?? 'N/A') }}</span>
                                <span class="font-bold text-red-600">{{ number_format($result->percentage ?? 0, 2) }}%</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Detailed Descriptions -->
            <div class="mt-8">
                <h3 class="text-xl font-semibold mb-1">Understanding Your Personality Dimensions</h3>
                <p class="text-sm text-gray-500 mb-4">Percentages show your <strong>relative strength</strong> in each dimension — they sum to 100% across all dimensions. Highlighted cards are your top traits.</p>
                @php
                    $topPercentage = $results->max('percentage');
                @endphp
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($dimensions as $dimension)
                        @php
                            $userResult = $results->firstWhere(function($result) use ($dimension) {
                                return $result->dimension_key === $dimension->dimension_key || 
                                       $result->personality_type_value_id === $dimension->id;
                            });
                            $isTopDimension = $userResult && $userResult->percentage >= ($topPercentage * 0.8);
                        @endphp
                        <div class="p-4 border rounded-lg
                            {{ $isTopDimension ? 'bg-blue-50 border-blue-300' : 'border-gray-300' }}">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-semibold text-lg">{{ $dimension->title }}</h4>
                                @if($isTopDimension)
                                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full font-medium">Top Trait</span>
                                @endif
                            </div>
                            @if($userResult)
                                <p class="text-sm text-gray-600 mb-2">
                                    Relative weight: <strong>{{ number_format($userResult->percentage ?? 0, 1) }}%</strong>
                                    <span class="text-gray-400 text-xs">({{ $userResult->score }} raw points)</span>
                                </p>
                            @else
                                <p class="text-sm text-gray-500 mb-2">Not assessed yet</p>
                            @endif
                            @if($dimension->description)
                                <p class="text-gray-700 text-sm mb-2">{{ $dimension->description }}</p>
                            @endif
                            @if($dimension->characteristics)
                                <p class="text-sm text-gray-600"><strong>Characteristics:</strong> {{ $dimension->characteristics }}</p>
                            @endif
                            @if($dimension->team_collaboration_tips)
                                <p class="text-sm text-gray-600 mt-2">
                                    <strong>Team Tips:</strong> {{ $dimension->team_collaboration_tips }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-app-layout>

