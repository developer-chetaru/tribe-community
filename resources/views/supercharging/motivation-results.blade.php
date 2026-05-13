<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            My Motivation Results
        </h2>
    </x-slot>

    <div class="bg-white p-6 rounded shadow">
        <div class="mb-4 flex flex-wrap gap-4">
            <a href="{{ route('supercharging.motivation') }}" 
               class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg font-semibold transition-colors inline-flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Retake Assessment
            </a>
            <a href="{{ route('supercharging.culture-structure') }}" 
               class="bg-purple-500 hover:bg-purple-600 text-white px-6 py-2 rounded-lg font-semibold transition-colors inline-flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                Culture Structure Assessment
            </a>
        </div>

        @if($latestDateCarbon)
            <p class="text-sm text-gray-600 mb-4">Assessment Date: {{ $latestDateCarbon->format('F d, Y') }}</p>
        @endif

        @if($results->isEmpty())
            <div class="text-center py-8">
                <p class="text-gray-500 mb-4">You haven't completed the Motivation assessment yet.</p>
                <a href="{{ route('supercharging.motivation') }}" 
                   class="bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-lg font-semibold inline-block">
                    Take Assessment Now
                </a>
            </div>
        @else
            @php
                $maxScore = $results->max('score') ?? 1;
            @endphp

            <!-- Top 3 Motivators -->
            <div class="mb-6">
                <h3 class="text-xl font-semibold mb-4">Your Top 3 Motivators</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($results->where('rank', '<=', 3) as $result)
                        <div class="p-4 border-2 rounded-lg 
                            {{ $result->rank == 1 ? 'border-red-500 bg-red-50' : 
                               ($result->rank == 2 ? 'border-orange-500 bg-orange-50' : 
                               'border-yellow-500 bg-yellow-50') }}">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <span class="text-2xl font-bold text-gray-600">#{{ $result->rank }}</span>
                                    <h4 class="text-lg font-semibold mt-1">
                                        {{ $result->motivationValue->title ?? ucfirst(str_replace('_', ' ', $result->value_key ?? 'Unknown')) }}
                                    </h4>
                                </div>
                                <span class="text-lg font-bold text-gray-700">{{ number_format($result->score, 1) }}</span>
                            </div>
                            @if($result->motivationValue && $result->motivationValue->description)
                                <p class="text-sm text-gray-700 mt-2">
                                    {{ Str::limit($result->motivationValue->description, 100) }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Motivation Profile Chart -->
            <div class="mb-8">
                <h3 class="text-xl font-semibold mb-6">Your Motivation Profile</h3>
                
                <!-- Bar Graph Visualization -->
                <div class="bg-white border border-gray-200 rounded-lg p-6 mb-6">
                    <div class="space-y-5">
                        @foreach($results->sortBy('rank') as $result)
                            <div class="relative">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-3">
                                        <span class="font-semibold text-sm text-gray-700 min-w-[40px]">
                                            #{{ $result->rank }}
                                        </span>
                                        <span class="font-semibold text-sm text-gray-700 min-w-[200px]">
                                            {{ $result->motivationValue->title ?? ucfirst(str_replace('_', ' ', $result->value_key ?? 'Unknown')) }}
                                        </span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xl font-bold text-red-600">{{ number_format($result->score, 1) }}</span>
                                    </div>
                                </div>
                                <!-- Progress Bar -->
                                <div class="relative w-full bg-gray-200 rounded-full h-8 overflow-hidden border border-gray-300">
                                    <!-- Progress Fill -->
                                    @php
                                        $percentage = $maxScore > 0 ? ($result->score / $maxScore) * 100 : 0;
                                    @endphp
                                    <div class="bg-red-500 h-full rounded-full transition-all duration-500 relative z-10 flex items-center justify-end pr-2" 
                                         style="width: {{ min($percentage, 100) }}%">
                                        @if($percentage > 15)
                                            <span class="text-white text-xs font-semibold">{{ number_format($result->score, 1) }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- All Results Table -->
            <div class="mt-8">
                <h3 class="text-xl font-semibold mb-4">Complete Results</h3>
                <table class="w-full border-collapse border border-gray-200">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 px-4 py-2 text-left">Rank</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Motivation Value</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Description</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($results->sortBy('rank') as $result)
                            <tr class="border border-gray-300 hover:bg-gray-50">
                                <td class="border border-gray-300 px-4 py-2">
                                    <span class="font-bold">{{ $result->rank }}</span>
                                </td>
                                <td class="border border-gray-300 px-4 py-2 font-semibold">
                                    {{ $result->motivationValue->title ?? ucfirst(str_replace('_', ' ', $result->value_key ?? 'Unknown')) }}
                                </td>
                                <td class="border border-gray-300 px-4 py-2">
                                    {{ Str::limit($result->motivationValue->description ?? '-', 100) }}
                                </td>
                                <td class="border border-gray-300 px-4 py-2">
                                    <span class="font-semibold">{{ number_format($result->score, 1) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Motivation Value Descriptions -->
            @if($motivationValues && $motivationValues->isNotEmpty())
                <div class="mt-8">
                    <h3 class="text-xl font-semibold mb-4">Understanding Motivation Values</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($motivationValues as $value)
                            <div class="border border-gray-300 rounded-lg p-4">
                                <h4 class="font-semibold text-lg mb-2">{{ $value->title }}</h4>
                                <p class="text-sm text-gray-600 mb-2">{{ $value->value_key }}</p>
                                @if($value->description)
                                    <p class="text-sm text-gray-700">{{ Str::limit($value->description, 200) }}</p>
                                @endif
                                @if($value->management_strategy)
                                    <p class="text-xs text-gray-500 mt-2 italic">
                                        Management Strategy: {{ Str::limit($value->management_strategy, 150) }}
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-app-layout>

