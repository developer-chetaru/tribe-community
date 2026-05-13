<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            My Culture Structure Results
        </h2>
    </x-slot>

    <div class="bg-white p-6 rounded shadow">
        <div class="mb-4 flex flex-wrap gap-4">
            <a href="{{ route('supercharging.culture-structure') }}" 
               class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg font-semibold transition-colors inline-flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Retake Assessment
            </a>
            <a href="{{ route('supercharging.motivation') }}" 
               class="bg-purple-500 hover:bg-purple-600 text-white px-6 py-2 rounded-lg font-semibold transition-colors inline-flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                Motivation Assessment
            </a>
        </div>

        @if($latestDateCarbon)
            <p class="text-sm text-gray-600 mb-4">Assessment Date: {{ $latestDateCarbon->format('F d, Y') }}</p>
        @endif

        @if($results->isEmpty())
            <div class="text-center py-8">
                <p class="text-gray-500 mb-4">You haven't completed the Culture Structure assessment yet.</p>
                <a href="{{ route('supercharging.culture-structure') }}" 
                   class="bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-lg font-semibold inline-block">
                    Take Assessment Now
                </a>
            </div>
        @else
            <!-- Culture Distribution Chart -->
            <div class="mb-6">
                <h3 class="text-xl font-semibold mb-4">Your Organization Culture Structure</h3>
                <div class="space-y-4">
                    @foreach($results->sortByDesc('percentage') as $result)
                        <div class="border border-gray-300 rounded-lg p-4">
                            <div class="flex justify-between items-center mb-2">
                                <div>
                                    <h4 class="text-lg font-semibold">
                                        {{ $result->cultureType->title ?? ucfirst(str_replace('_', ' ', $result->type_key ?? 'Unknown')) }}
                                    </h4>
                                    <p class="text-sm text-gray-600">
                                        {{ $result->cultureType->summary ?? '' }}
                                    </p>
                                </div>
                                <span class="text-2xl font-bold text-gray-700">{{ number_format($result->percentage, 2) }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-4 mb-2">
                                <div class="bg-red-500 h-4 rounded-full" style="width: {{ $result->percentage }}%"></div>
                            </div>
                            <div class="text-sm text-gray-600">
                                Score: {{ $result->score }} out of {{ $results->sum('score') }} total
                            </div>
                            @if($result->cultureType && $result->cultureType->description)
                                <p class="text-sm text-gray-700 mt-2">
                                    {{ Str::limit($result->cultureType->description, 150) }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- All Results Table -->
            <div class="mt-8">
                <h3 class="text-xl font-semibold mb-4">Complete Results</h3>
                <table class="w-full border-collapse border border-gray-200">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 px-4 py-2 text-left">Culture Type</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Summary</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Percentage</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($results->sortByDesc('percentage') as $result)
                            <tr class="border border-gray-300 hover:bg-gray-50">
                                <td class="border border-gray-300 px-4 py-2 font-semibold">
                                    {{ $result->cultureType->title ?? ucfirst(str_replace('_', ' ', $result->type_key ?? 'Unknown')) }}
                                </td>
                                <td class="border border-gray-300 px-4 py-2">
                                    {{ $result->cultureType->summary ?? '-' }}
                                </td>
                                <td class="border border-gray-300 px-4 py-2">
                                    <span class="font-semibold">{{ number_format($result->percentage, 2) }}%</span>
                                </td>
                                <td class="border border-gray-300 px-4 py-2">
                                    {{ $result->score ?? 0 }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Culture Type Descriptions -->
            @if($cultureTypes && $cultureTypes->isNotEmpty())
                <div class="mt-8">
                    <h3 class="text-xl font-semibold mb-4">Understanding Culture Types</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($cultureTypes as $type)
                            <div class="border border-gray-300 rounded-lg p-4">
                                <h4 class="font-semibold text-lg mb-2">{{ $type->title }}</h4>
                                <p class="text-sm text-gray-600 mb-2">{{ $type->summary }}</p>
                                @if($type->description)
                                    <p class="text-sm text-gray-700">{{ Str::limit($type->description, 200) }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-app-layout>

