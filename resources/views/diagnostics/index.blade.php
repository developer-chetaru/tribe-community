<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Diagnostic Assessment
        </h2>
    </x-slot>

    <div class="bg-white p-6 rounded shadow">
        @if (session('success'))
            <div class="mb-4 px-4 py-2 bg-green-100 border border-green-400 text-green-700 rounded">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 px-4 py-2 bg-red-100 border border-red-400 text-red-700 rounded">
                {{ session('error') }}
            </div>
        @endif

        @if($completionStatus)
            <div class="mb-4 px-4 py-2 bg-blue-100 border border-blue-400 text-blue-700 rounded">
                <p class="font-semibold">You have already completed this assessment.</p>
                <p class="text-sm">Last completed: {{ $completionStatus->date ? $completionStatus->date->format('M d, Y') : 'N/A' }}</p>
                <a href="{{ route('diagnostics.results') }}" class="text-blue-600 underline mt-2 inline-block">
                    View Results
                </a>
            </div>
        @endif

        <div class="mb-4">
            <p class="text-gray-600 mb-4">
                Please read each statement and indicate how much you agree or disagree using the 5-point scale below.
                Be honest and choose based on your natural preferences and behaviors in work situations.
            </p>
            <div class="bg-gray-50 p-4 rounded-lg mb-4">
                <p class="text-sm font-semibold mb-2">Rating Scale:</p>
                <div class="grid grid-cols-5 gap-2 text-sm">
                    @foreach($options as $option)
                        <div class="text-center">
                            <span class="font-semibold 
                                {{ $option->option_rating == 0 ? 'text-red-600' : 
                                   ($option->option_rating == 1 ? 'text-orange-600' : 
                                   ($option->option_rating == 2 ? 'text-yellow-600' : 
                                   ($option->option_rating == 3 ? 'text-blue-600' : 'text-green-600'))) }}">
                                {{ $option->option_rating }}
                            </span>
                            <p class="text-gray-600 text-xs">{{ Str::limit($option->option_name, 15) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <form action="{{ route('diagnostics.submit') }}" method="POST" id="diagnosticForm">
            @csrf
            
            @php
                $questionsByCategory = $questions->groupBy(function($question) {
                    return $question->category->title ?? 'Uncategorized';
                });
            @endphp

            @foreach($questionsByCategory as $categoryTitle => $categoryQuestions)
                <div class="mb-8">
                    <h3 class="text-xl font-bold text-[#EB1C24] mb-4 pb-2 border-b-2 border-red-200">
                        {{ $categoryTitle }}
                    </h3>
                    
                    @foreach($categoryQuestions as $questionIndex => $question)
                        @php
                            $userAnswer = $userAnswers->get($question->id);
                        @endphp
                        <div class="mb-6 p-6 border border-gray-300 rounded-lg bg-white shadow-sm">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="bg-red-100 text-red-600 px-3 py-1 rounded-full text-sm font-semibold">
                                            Question {{ $loop->parent->iteration }}.{{ $loop->iteration }}
                                        </span>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2">
                                        {{ $question->question }}
                                    </h3>
                                    @if($question->measure)
                                        <p class="text-sm text-gray-600 italic">{{ $question->measure }}</p>
                                    @endif
                                </div>
                            </div>
                            
                            <div class="space-y-2">
                                @foreach($options as $option)
                                    <label class="flex items-center p-4 border-2 rounded-lg hover:bg-gray-50 cursor-pointer transition-all
                                        {{ ($userAnswer && $userAnswer->optionId == $option->id) || old("answers.$question->id.optionId") == $option->id ? 'border-red-500 bg-red-50' : 'border-gray-200' }}">
                                        <input type="radio" 
                                               name="answers[{{ $question->id }}][optionId]"
                                               value="{{ $option->id }}"
                                               required
                                               class="w-5 h-5 text-red-600 focus:ring-red-500"
                                               {{ ($userAnswer && $userAnswer->optionId == $option->id) ? 'checked' : '' }}
                                               onchange="this.closest('label').classList.toggle('border-red-500', this.checked); this.closest('label').classList.toggle('bg-red-50', this.checked); updateProgress();">
                                        <div class="ml-4 flex-1">
                                            <div class="flex items-center gap-3">
                                                <span class="font-semibold text-lg 
                                                    {{ $option->option_rating == 0 ? 'text-red-600' : 
                                                       ($option->option_rating == 1 ? 'text-orange-600' : 
                                                       ($option->option_rating == 2 ? 'text-yellow-600' : 
                                                       ($option->option_rating == 3 ? 'text-blue-600' : 'text-green-600'))) }}">
                                                    {{ $option->option_rating }}
                                                </span>
                                                <span class="font-medium text-gray-800 text-base">{{ $option->option_name }}</span>
                                            </div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                            
                            <input type="hidden" 
                                   name="answers[{{ $question->id }}][questionId]" 
                                   value="{{ $question->id }}">
                        </div>
                    @endforeach
                </div>
            @endforeach

            <div class="flex gap-4 mt-6 sticky bottom-0 bg-white p-4 border-t border-gray-200 rounded-t-lg shadow-lg">
                <button type="submit" 
                        class="bg-red-500 hover:bg-red-600 text-white px-8 py-3 rounded-lg font-semibold text-lg transition-colors">
                    Submit Assessment
                </button>
                @if($completionStatus)
                    <a href="{{ route('diagnostics.results') }}" 
                       class="bg-gray-500 hover:bg-gray-600 text-white px-8 py-3 rounded-lg font-semibold text-lg transition-colors">
                        View Results
                    </a>
                @endif
                <div class="ml-auto flex items-center text-sm text-gray-600">
                    <span>Progress: </span>
                    <span id="answeredCount">0</span> / <span>{{ count($questions) }}</span> questions answered
                </div>
            </div>
        </form>
    </div>

    <script>
        // Track progress
        function updateProgress() {
            const totalQuestions = {{ count($questions) }};
            const answered = document.querySelectorAll('input[type="radio"]:checked').length;
            document.getElementById('answeredCount').textContent = answered;
        }

        // Update progress on change
        document.addEventListener('change', function(e) {
            if (e.target.type === 'radio') {
                updateProgress();
            }
        });

        // Initial progress
        updateProgress();
    </script>
</x-app-layout>

