<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Tribeometer Assessment Results
        </h2>
    </x-slot>

    <div class="bg-white p-6 rounded shadow">
        @if (session('success'))
            <div class="mb-4 px-4 py-2 bg-green-100 border border-green-400 text-green-700 rounded">
                {{ session('success') }}
            </div>
        @endif

        <div class="mb-6">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <p class="text-sm text-blue-800">
                    <strong>Assessment Date:</strong> {{ $completionStatus->date ? $completionStatus->date->format('F d, Y') : 'N/A' }}
                </p>
            </div>
            
            <a href="{{ route('tribeometer.index') }}" 
               class="inline-block bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded-lg font-semibold transition-colors mb-4">
                Retake Assessment
            </a>
        </div>

        <div class="space-y-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Results by Value</h3>
            
            @php
                $rank = 1;
            @endphp
            
            @foreach($valueScores as $valueTitle => $scoreData)
                <div class="border border-gray-300 rounded-lg p-6 bg-white shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <span class="bg-red-100 text-red-600 px-4 py-2 rounded-full text-lg font-bold">
                                #{{ $rank }}
                            </span>
                            <h4 class="text-lg font-semibold text-gray-800">{{ $valueTitle }}</h4>
                        </div>
                        <span class="text-2xl font-bold 
                            {{ $scoreData['score'] >= 75 ? 'text-green-600' : 
                               ($scoreData['score'] >= 50 ? 'text-blue-600' : 
                               ($scoreData['score'] >= 25 ? 'text-yellow-600' : 'text-red-600')) }}">
                            {{ $scoreData['score'] }}%
                        </span>
                    </div>
                    
                    @if(!empty($scoreData['description']))
                        <p class="text-sm text-gray-600 mb-3">{{ $scoreData['description'] }}</p>
                    @endif
                    
                    <div class="w-full bg-gray-200 rounded-full h-4 mb-2">
                        <div class="bg-red-500 h-4 rounded-full transition-all duration-500" 
                             style="width: {{ $scoreData['score'] }}%"></div>
                    </div>
                    
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Average Score: {{ $scoreData['average_score'] }}/3.0</span>
                        <span>{{ $scoreData['total_responses'] }} responses</span>
                    </div>
                </div>
                @php
                    $rank++;
                @endphp
            @endforeach
        </div>

        <div class="mt-8 p-6 bg-gray-50 rounded-lg">
            <h4 class="text-lg font-semibold text-gray-800 mb-4">Understanding Your Results</h4>
            <ul class="space-y-2 text-sm text-gray-700">
                <li><strong>75-100%:</strong> Strong Alignment - You strongly embody this organizational value</li>
                <li><strong>50-74%:</strong> Moderate Alignment - Generally aligned but room for improvement</li>
                <li><strong>25-49%:</strong> Weak Alignment - Significant gap between your perception and the value</li>
                <li><strong>0-24%:</strong> Misalignment - Critical disconnect requiring attention</li>
            </ul>
        </div>
    </div>
</x-app-layout>

