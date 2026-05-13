<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Personality Type Assessment
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
                <p class="text-sm">Last completed: {{ $completionStatus->date }}</p>
                <a href="{{ route('connecting.personality-type.results') }}" class="text-blue-600 underline mt-2 inline-block">
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
                    <div class="text-center">
                        <span class="font-semibold text-red-600">1</span>
                        <p class="text-gray-600">Disagree</p>
                    </div>
                    <div class="text-center">
                        <span class="font-semibold text-orange-600">2</span>
                        <p class="text-gray-600">Mostly Disagree</p>
                    </div>
                    <div class="text-center">
                        <span class="font-semibold text-yellow-600">3</span>
                        <p class="text-gray-600">Neutral</p>
                    </div>
                    <div class="text-center">
                        <span class="font-semibold text-blue-600">4</span>
                        <p class="text-gray-600">Mostly Agree</p>
                    </div>
                    <div class="text-center">
                        <span class="font-semibold text-green-600">5</span>
                        <p class="text-gray-600">Agree</p>
                    </div>
                </div>
            </div>
        </div>

        <form action="{{ route('connecting.personality-type.submit') }}" method="POST" id="personalityForm">
            @csrf
            
            @foreach($questions as $questionIndex => $question)
                <div class="mb-8 p-6 border border-gray-300 rounded-lg bg-white shadow-sm">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="bg-red-100 text-red-600 px-3 py-1 rounded-full text-sm font-semibold">
                                    Question {{ $questionIndex + 1 }}
                                </span>
                                @if($question->category)
                                    <span class="bg-blue-100 text-blue-600 px-3 py-1 rounded-full text-xs font-medium">
                                        {{ $question->category }}
                                    </span>
                                @endif
                                @if($question->summary_trait)
                                    <span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-xs">
                                        {{ $question->summary_trait }}
                                    </span>
                                @endif
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800">
                                {{ $question->question }}
                            </h3>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        @foreach($question->options->sortBy('order') as $option)
                            <label class="flex items-center p-4 border-2 rounded-lg hover:bg-gray-50 cursor-pointer transition-all
                                {{ old("answers.$questionIndex.optionId") == $option->id ? 'border-red-500 bg-red-50' : 'border-gray-200' }}">
                                <input type="radio" 
                                       name="answers[{{ $questionIndex }}][optionId]"
                                       value="{{ $option->id }}"
                                       required
                                       class="w-5 h-5 text-red-600 focus:ring-red-500"
                                       onchange="this.closest('label').classList.toggle('border-red-500', this.checked); this.closest('label').classList.toggle('bg-red-50', this.checked);">
                                <div class="ml-4 flex-1">
                                    <div class="flex items-center gap-3">
                                        <span class="font-semibold text-lg 
                                            {{ $option->score_value == 1 ? 'text-red-600' : 
                                               ($option->score_value == 2 ? 'text-orange-600' : 
                                               ($option->score_value == 3 ? 'text-yellow-600' : 
                                               ($option->score_value == 4 ? 'text-blue-600' : 'text-green-600'))) }}">
                                            {{ $option->score_value }}
                                        </span>
                                        <span class="font-medium text-gray-800 text-base">{{ $option->option_text }}</span>
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    
                    <input type="hidden" 
                           name="answers[{{ $questionIndex }}][questionId]" 
                           value="{{ $question->id }}">
                </div>
            @endforeach

            <div class="flex gap-4 mt-6 sticky bottom-0 bg-white p-4 border-t border-gray-200 rounded-t-lg shadow-lg">
                <button type="submit" 
                        class="bg-red-500 hover:bg-red-600 text-white px-8 py-3 rounded-lg font-semibold text-lg transition-colors">
                    Submit Assessment
                </button>
                <a href="{{ route('connecting.personality-type.results') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-8 py-3 rounded-lg font-semibold text-lg transition-colors">
                    View Results
                </a>
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
            
            const progressBar = document.querySelector('.progress-bar');
            if (progressBar) {
                const percentage = (answered / totalQuestions) * 100;
                progressBar.style.width = percentage + '%';
            }
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

