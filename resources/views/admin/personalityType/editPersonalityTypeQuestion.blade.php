<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Edit Personality Type Question
        </h2>
    </x-slot>

    <div class="bg-white p-6 rounded shadow">
        <div class="mb-4">
            <a href="{{ route('admin.personality-type.questions.index') }}" 
               class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded flex items-center gap-2 inline-flex">
               <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
               </svg>
               Back to Questions
            </a>
        </div>

        @if ($errors->any())
            <div class="mb-4 px-4 py-2 bg-red-100 border border-red-400 text-red-700 rounded">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('admin.personality-type.questions.update', $question->id) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Question <span class="text-red-500">*</span></label>
                <textarea name="question" rows="3" required
                          class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500"
                          placeholder="Enter the question statement...">{{ old('question', $question->question) }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <select name="category"
                            class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                        <option value="">-- Select Category --</option>
                        <option value="Int" {{ old('category', $question->category) == 'Int' ? 'selected' : '' }}>Int (Introverted)</option>
                        <option value="Ext" {{ old('category', $question->category) == 'Ext' ? 'selected' : '' }}>Ext (Extroverted)</option>
                        <option value="Innov" {{ old('category', $question->category) == 'Innov' ? 'selected' : '' }}>Innov (Innovative)</option>
                        <option value="Lgc" {{ old('category', $question->category) == 'Lgc' ? 'selected' : '' }}>Lgc (Logical)</option>
                        <option value="Ppl" {{ old('category', $question->category) == 'Ppl' ? 'selected' : '' }}>Ppl (People-Focused)</option>
                        <option value="Tsk" {{ old('category', $question->category) == 'Tsk' ? 'selected' : '' }}>Tsk (Task-Focused)</option>
                        <option value="Stru" {{ old('category', $question->category) == 'Stru' ? 'selected' : '' }}>Stru (Structured)</option>
                        <option value="Flex" {{ old('category', $question->category) == 'Flex' ? 'selected' : '' }}>Flex (Flexible)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Personality Dimension</label>
                    <select name="personality_type_value_id"
                            class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                        <option value="">-- Select Dimension --</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('personality_type_value_id', $question->personality_type_value_id) == $category->id ? 'selected' : '' }}>
                                {{ strtoupper($category->dimension_key) }} - {{ $category->title }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Summary Trait</label>
                    <input type="text" name="summary_trait" value="{{ old('summary_trait', $question->summary_trait) }}"
                           placeholder="e.g., Thinker, Solitary, Observant"
                           class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Order</label>
                    <input type="number" name="order" value="{{ old('order', $question->order) }}"
                           class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Status <span class="text-red-500">*</span></label>
                <select name="status" required
                        class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    <option value="Active" {{ old('status', $question->status) == 'Active' ? 'selected' : '' }}>Active</option>
                    <option value="Inactive" {{ old('status', $question->status) == 'Inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Options (5-Point Likert Scale) <span class="text-red-500">*</span></label>
                <p class="text-sm text-gray-500 mb-4">Each question must have exactly 5 options matching the Likert scale.</p>
                
                <div id="options-container" class="space-y-4">
                    @foreach($question->options->sortBy('order') as $index => $option)
                        <div class="border border-gray-300 rounded p-4">
                            <div class="flex items-center gap-4">
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Option {{ $index + 1 }}</label>
                                    <input type="text" name="options[{{ $index }}][text]" required
                                           value="{{ old("options.$index.text", $option->option_text) }}"
                                           class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                                    <input type="hidden" name="options[{{ $index }}][id]" value="{{ $option->id }}">
                                </div>
                                <div class="w-32">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Score Value</label>
                                    <input type="number" name="options[{{ $index }}][score_value]" required
                                           value="{{ old("options.$index.score_value", $option->score_value) }}"
                                           min="1" max="5"
                                           class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                                </div>
                            </div>
                            <input type="hidden" name="options[{{ $index }}][personality_type_value_id]" value="">
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex gap-4">
                <button type="submit" 
                        class="bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Update Question
                </button>
                <a href="{{ route('admin.personality-type.questions.index') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded flex items-center gap-2">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</x-app-layout>

