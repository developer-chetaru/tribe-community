<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Motivation Assessment
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
                <a href="{{ route('supercharging.motivation.results') }}" class="text-blue-600 underline mt-2 inline-block">
                    View Results
                </a>
            </div>
        @endif

        <div class="mb-4">
            <p class="text-gray-600 mb-4">
                For each question, rate both options on a scale of 0-5 based on how important each statement is to you. 
                You can rate both options independently - they don't need to add up to any specific total.
            </p>
            <div class="bg-gray-50 p-4 rounded-lg mb-4">
                <p class="text-sm font-semibold mb-2">Rating Scale (0-5):</p>
                <div class="grid grid-cols-6 gap-2 text-sm">
                    <div class="text-center">
                        <span class="font-semibold text-gray-600">0</span>
                        <p class="text-gray-600 text-xs">Not Important</p>
                    </div>
                    <div class="text-center">
                        <span class="font-semibold text-gray-600">1</span>
                        <p class="text-gray-600 text-xs">Slightly</p>
                    </div>
                    <div class="text-center">
                        <span class="font-semibold text-gray-600">2</span>
                        <p class="text-gray-600 text-xs">Somewhat</p>
                    </div>
                    <div class="text-center">
                        <span class="font-semibold text-gray-600">3</span>
                        <p class="text-gray-600 text-xs">Moderately</p>
                    </div>
                    <div class="text-center">
                        <span class="font-semibold text-gray-600">4</span>
                        <p class="text-gray-600 text-xs">Very</p>
                    </div>
                    <div class="text-center">
                        <span class="font-semibold text-gray-600">5</span>
                        <p class="text-gray-600 text-xs">Extremely</p>
                    </div>
                </div>
            </div>
        </div>

        <form action="{{ route('supercharging.motivation.submit') }}" method="POST" id="motivationForm">
            @csrf
            
            @foreach($questions as $questionIndex => $question)
                <div class="mb-8 p-6 border border-gray-300 rounded-lg">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="bg-red-100 text-red-600 px-3 py-1 rounded-full text-sm font-semibold">
                                    Question {{ $questionIndex + 1 }}
                                </span>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800">
                                {{ $question->question }}
                            </h3>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        @php
                            $existingAnswers = $userAnswers->get($question->id);
                        @endphp
                        @foreach($question->options->sortBy('order') as $optionIndex => $option)
                            @php
                                $existingAnswer = $existingAnswers ? $existingAnswers->firstWhere('option_id', $option->id) : null;
                                $existingRating = $existingAnswer ? (int)$existingAnswer->rating : 0;
                            @endphp
                            <div class="p-4 border-2 border-gray-200 rounded-lg">
                                <div class="flex items-center justify-between mb-3">
                                    <label class="flex-1">
                                        <span class="font-semibold text-lg">{{ $option->option_label ?? ('Option ' . ($optionIndex === 0 ? 'A' : 'B')) }}:</span>
                                        <span class="text-gray-700 ml-2">{{ $option->option_text }}</span>
                                        @if($option->motivationValue)
                                            <span class="text-sm text-gray-500 block mt-1">
                                                ({{ $option->motivationValue->title }})
                                            </span>
                                        @endif
                                    </label>
                                </div>
                                <div class="flex items-center gap-4">
                                    <span class="text-sm text-gray-600 w-24">Rate (0-5):</span>
                                    <input type="number" 
                                           name="answers[{{ $question->id }}][{{ $option->id }}]"
                                           min="0" 
                                           max="5" 
                                           value="{{ old("answers.$question->id.$option->id", $existingRating) }}"
                                           required
                                           class="w-24 border-2 border-gray-300 rounded px-4 py-2 text-center text-lg font-semibold focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                           onchange="updateRatingDisplay(this)">
                                    <div class="flex-1 flex gap-1">
                                        @for($i = 0; $i <= 5; $i++)
                                            <button type="button"
                                                    onclick="setRating(this, {{ $i }})"
                                                    class="flex-1 h-8 rounded text-xs font-semibold transition-colors
                                                    {{ $existingRating == $i ? 'bg-red-500 text-white' : 'bg-gray-200 text-gray-600 hover:bg-gray-300' }}"
                                                    data-rating="{{ $i }}">
                                                {{ $i }}
                                            </button>
                                        @endfor
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="flex gap-4 mt-6">
                <button type="submit" 
                        class="bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-lg font-semibold">
                    Submit Assessment
                </button>
                <a href="{{ route('supercharging.motivation.results') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-semibold">
                    View Results
                </a>
            </div>
        </form>
    </div>

    <script>
        function setRating(button, rating) {
            const input = button.closest('.p-4').querySelector('input[type="number"]');
            if (input) {
                input.value = rating;
                updateRatingDisplay(input);
                // Update button states
                button.closest('.flex').querySelectorAll('button').forEach(btn => {
                    const btnRating = parseInt(btn.getAttribute('data-rating'));
                    if (btnRating === rating) {
                        btn.classList.add('bg-red-500', 'text-white');
                        btn.classList.remove('bg-gray-200', 'text-gray-600');
                    } else {
                        btn.classList.remove('bg-red-500', 'text-white');
                        btn.classList.add('bg-gray-200', 'text-gray-600');
                    }
                });
            }
        }

        function updateRatingDisplay(input) {
            const rating = parseInt(input.value) || 0;
            const container = input.closest('.p-4');
            if (container) {
                // Update visual feedback
                container.querySelectorAll('button').forEach(btn => {
                    const btnRating = parseInt(btn.getAttribute('data-rating'));
                    if (btnRating === rating) {
                        btn.classList.add('bg-red-500', 'text-white');
                        btn.classList.remove('bg-gray-200', 'text-gray-600');
                    } else {
                        btn.classList.remove('bg-red-500', 'text-white');
                        btn.classList.add('bg-gray-200', 'text-gray-600');
                    }
                });
            }
        }

        // Initialize ratings on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('input[type="number"]').forEach(input => {
                updateRatingDisplay(input);
            });
        });
    </script>
</x-app-layout>

