<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Culture Structure Assessment
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
                <a href="{{ route('supercharging.culture-structure.results') }}" class="text-blue-600 underline mt-2 inline-block">
                    View Results
                </a>
            </div>
        @endif

        <div class="mb-4">
            <p class="text-gray-600 mb-4">
                For each question, select the option that best describes your organization's current culture. 
                Choose the statement that most accurately reflects how things work in your organization.
            </p>
        </div>

        <form action="{{ route('supercharging.culture-structure.submit') }}" method="POST" id="assessmentForm">
            @csrf
            
            @foreach($questions as $questionIndex => $question)
                <div class="mb-8 p-6 border border-gray-300 rounded-lg">
                    <h3 class="text-lg font-semibold mb-4">
                        Question {{ $questionIndex + 1 }}: {{ $question->question }}
                    </h3>
                    
                    <div class="space-y-3">
                        @php
                            $existingAnswer = $userAnswers->get($question->id)?->first();
                            $selectedOptionId = $existingAnswer ? $existingAnswer->option_id : null;
                        @endphp
                        @foreach($question->options->sortBy('order') as $option)
                            <div class="flex items-start gap-3 p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer"
                                 onclick="selectOption({{ $question->id }}, {{ $option->id }})">
                                <input type="radio" 
                                       name="answers[{{ $question->id }}]"
                                       id="option_{{ $option->id }}"
                                       value="{{ $option->id }}"
                                       {{ old("answers.$question->id", $selectedOptionId) == $option->id ? 'checked' : '' }}
                                       required
                                       class="mt-1">
                                <label for="option_{{ $option->id }}" class="flex-1 cursor-pointer">
                                    <span class="font-medium">{{ $option->option_text }}</span>
                                    @if($option->cultureType)
                                        <span class="text-sm text-gray-500 block mt-1">
                                            ({{ $option->cultureType->title }})
                                        </span>
                                    @endif
                                </label>
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
                <a href="{{ route('supercharging.culture-structure.results') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-semibold">
                    View Results
                </a>
            </div>
        </form>
    </div>

    <script>
        function selectOption(questionId, optionId) {
            const radio = document.getElementById('option_' + optionId);
            if (radio) {
                radio.checked = true;
                // Visual feedback
                const container = radio.closest('.border');
                if (container) {
                    // Remove highlight from siblings
                    container.parentElement.querySelectorAll('.border').forEach(el => {
                        el.classList.remove('border-red-500', 'bg-red-50');
                    });
                    // Highlight selected
                    container.classList.add('border-red-500', 'bg-red-50');
                }
            }
        }

        // Initialize selected options on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
                const container = radio.closest('.border');
                if (container) {
                    container.classList.add('border-red-500', 'bg-red-50');
                }
            });
        });
    </script>
</x-app-layout>

