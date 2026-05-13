<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Diagnostic Assessment Results
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
            
            <a href="{{ route('diagnostics.index') }}" 
               class="inline-block bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded-lg font-semibold transition-colors mb-4">
                Retake Assessment
            </a>
        </div>

        <div class="space-y-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Results by Category</h3>
            
            @foreach($categoryScores as $categoryTitle => $scoreData)
                <div class="border border-gray-300 rounded-lg p-6 bg-white shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-lg font-semibold text-gray-800">{{ $categoryTitle }}</h4>
                        <span class="text-2xl font-bold 
                            {{ $scoreData['percentage'] >= 80 ? 'text-green-600' : 
                               ($scoreData['percentage'] >= 60 ? 'text-blue-600' : 
                               ($scoreData['percentage'] >= 40 ? 'text-yellow-600' : 'text-red-600')) }}">
                            {{ $scoreData['percentage'] }}%
                        </span>
                    </div>
                    
                    <div class="w-full bg-gray-200 rounded-full h-4 mb-2">
                        <div class="bg-red-500 h-4 rounded-full transition-all duration-500" 
                             style="width: {{ $scoreData['percentage'] }}%"></div>
                    </div>
                    
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Average Score: {{ $scoreData['score'] }}/4.0</span>
                        <span>{{ $scoreData['questionCount'] }} questions</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-8 p-6 bg-gray-50 rounded-lg">
            <h4 class="text-lg font-semibold text-gray-800 mb-4">Understanding Your Results</h4>
            <ul class="space-y-2 text-sm text-gray-700">
                <li><strong>80-100%:</strong> Excellent - You demonstrate strong capabilities in this area.</li>
                <li><strong>60-79%:</strong> Good - You show solid performance with room for growth.</li>
                <li><strong>40-59%:</strong> Average - There are opportunities for improvement.</li>
                <li><strong>Below 40%:</strong> Needs Development - Focus on building skills in this area.</li>
            </ul>
        </div>
    </div>
</x-app-layout>

