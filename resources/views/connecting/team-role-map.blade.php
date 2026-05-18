<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Team Role Map Assessment
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
                <a href="{{ route('connecting.team-role-map.results') }}" class="text-blue-600 underline mt-2 inline-block">
                    View Results
                </a>
            </div>
        @endif

        <div class="mb-4">
            <p class="text-gray-600 mb-4">
                For each question, distribute 10 points across the 8 options. You can give all 10 points to one option, 
                or distribute them across multiple options. The total must equal 10 points per question.
            </p>
        </div>

        <form action="{{ route('connecting.team-role-map.submit') }}" method="POST" id="assessmentForm">
            @csrf
            
            @foreach($questions as $questionIndex => $question)
                <div class="mb-8 p-6 border border-gray-300 rounded-lg">
                    <h3 class="text-lg font-semibold mb-4">
                        Question {{ $questionIndex + 1 }}: {{ $question->question }}
                    </h3>
                    
                    <div class="space-y-3">
                        @foreach($question->roleMapOptions as $optionIndex => $option)
                            @php
                                $existingAnswer = $userAnswers->get($question->id)?->firstWhere('optionId', $option->id);
                                $existingPoints = $existingAnswer ? (int)$existingAnswer->answer : 0;
                            @endphp
                            <div class="flex items-center gap-4 p-3 bg-gray-50 rounded">
                                <label class="flex-1">
                                    <span class="font-medium">{{ $option->maper }}</span>
                                    @if($option->roleDescription)
                                        <span class="text-sm text-gray-500">({{ $option->roleDescription->title }})</span>
                                    @endif
                                </label>
                                <input type="number" 
                                       name="answers[{{ $questionIndex }}][options][{{ $optionIndex }}][points]"
                                       data-question="{{ $questionIndex }}"
                                       min="0" 
                                       max="10" 
                                       value="{{ old("answers.$questionIndex.options.$optionIndex.points", $existingPoints) }}"
                                       class="w-20 border border-gray-300 rounded px-3 py-2 text-center points-input"
                                       onchange="updateTotal({{ $questionIndex }})">
                                <input type="hidden" 
                                       name="answers[{{ $questionIndex }}][questionId]" 
                                       value="{{ $question->id }}">
                                <input type="hidden" 
                                       name="answers[{{ $questionIndex }}][options][{{ $optionIndex }}][optionId]" 
                                       value="{{ $option->id }}">
                            </div>
                        @endforeach
                    </div>
                    
                    <div class="mt-4 flex justify-between items-center">
                        <span class="text-sm text-gray-600">Total points for this question:</span>
                        <span id="total-{{ $questionIndex }}" class="font-bold text-lg text-gray-600">
                            0
                        </span>
                        <span class="text-xs text-gray-500">(Must equal 10)</span>
                    </div>
                </div>
            @endforeach

            <div id="formErrorBanner" class="hidden mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg flex items-start gap-3">
                <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span id="formErrorText"></span>
            </div>

            <div class="flex gap-4 mt-6">
                <button type="submit" 
                        class="bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-lg font-semibold">
                    Submit Assessment
                </button>
                <a href="{{ route('connecting.team-role-map.results') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-semibold">
                    View Results
                </a>
            </div>
        </form>
    </div>

    <script>
        function updateTotal(questionIndex) {
            const inputs = document.querySelectorAll(`input[data-question="${questionIndex}"].points-input`);
            let total = 0;
            inputs.forEach(input => {
                const value = parseInt(input.value) || 0;
                total += value;
            });
            
            const totalElement = document.getElementById(`total-${questionIndex}`);
            if (totalElement) {
                totalElement.textContent = total;
                
                if (total === 10) {
                    totalElement.classList.remove('text-red-600');
                    totalElement.classList.add('text-green-600');
                } else if (total > 10) {
                    totalElement.classList.remove('text-green-600');
                    totalElement.classList.add('text-red-600');
                } else {
                    totalElement.classList.remove('text-green-600');
                    totalElement.classList.add('text-gray-600');
                }
            }
        }

        // Initialize totals on page load (with small delay to ensure DOM is ready)
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                @foreach($questions as $questionIndex => $question)
                    updateTotal({{ $questionIndex }});
                @endforeach
            }, 100);
        });

        // Also update on input event for real-time feedback
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('points-input')) {
                const questionIndex = e.target.getAttribute('data-question');
                if (questionIndex !== null) {
                    updateTotal(parseInt(questionIndex));
                }
            }
        });

        // Validate form before submit
        document.getElementById('assessmentForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Always prevent first to validate
            
            let allValid = true;
            let firstInvalidQuestion = null;
            
            @foreach($questions as $questionIndex => $question)
                // Recalculate total to ensure it's current
                updateTotal({{ $questionIndex }});
                const total{{ $questionIndex }} = parseInt(document.getElementById('total-{{ $questionIndex }}').textContent) || 0;
                if (total{{ $questionIndex }} !== 10) {
                    allValid = false;
                    if (!firstInvalidQuestion) {
                        firstInvalidQuestion = {{ $questionIndex + 1 }};
                    }
                }
            @endforeach
            
            if (!allValid) {
                const banner = document.getElementById('formErrorBanner');
                const bannerText = document.getElementById('formErrorText');
                bannerText.textContent = 'Please ensure all questions total exactly 10 points. Question ' + firstInvalidQuestion + ' and possibly others need adjustment.';
                banner.classList.remove('hidden');
                banner.scrollIntoView({ behavior: 'smooth', block: 'center' });

                // Highlight invalid question
                const firstInvalidElement = document.querySelector(`input[data-question="${firstInvalidQuestion - 1}"].points-input`);
                if (firstInvalidElement) {
                    firstInvalidElement.closest('.mb-8').scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return false;
            }

            // Hide error banner on valid submit
            document.getElementById('formErrorBanner').classList.add('hidden');
            
            // If all valid, submit the form
            this.submit();
        });
    </script>
</x-app-layout>

