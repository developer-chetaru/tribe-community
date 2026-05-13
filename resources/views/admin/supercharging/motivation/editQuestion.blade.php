<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Edit Motivation Question
        </h2>
    </x-slot>

    <div class="bg-white p-6 rounded shadow">
        <div class="mb-4">
            <a href="{{ route('admin.supercharging.motivation.questions.index') }}" 
               class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded flex items-center gap-2 inline-flex">
               <i class="fas fa-arrow-left"></i> Back to Questions
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

        <form action="{{ route('admin.supercharging.motivation.questions.update', $question->id) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Question <span class="text-red-500">*</span></label>
                <textarea name="question" rows="3" required
                          class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500">{{ old('question', $question->question) }}</textarea>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Order</label>
                <input type="number" name="order" value="{{ old('order', $question->order) }}"
                       class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500">
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
                <label class="block text-sm font-medium text-gray-700 mb-2">Options (2 required - Option A and Option B) <span class="text-red-500">*</span></label>
                <p class="text-sm text-gray-500 mb-4">Each option must be assigned to a motivation value. Users will rate each option on a 0-5 scale.</p>
                
                <div id="options-container" class="space-y-4">
                    @foreach($question->options->sortBy('order') as $index => $option)
                        <div class="border border-gray-300 rounded p-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ $option->option_label ?? ('Option ' . ($index === 0 ? 'A' : 'B')) }} <span class="text-red-500">*</span>
                            </label>
                            <input type="hidden" name="options[{{ $index }}][id]" value="{{ $option->id }}">
                            <input type="text" name="options[{{ $index }}][text]" required
                                   value="{{ old("options.$index.text", $option->option_text) }}"
                                   placeholder="Enter option text..."
                                   class="w-full border border-gray-300 rounded px-4 py-2 mb-2 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                            
                            <label class="block text-sm font-medium text-gray-700 mb-2">Assign to Motivation Value <span class="text-red-500">*</span></label>
                            <select name="options[{{ $index }}][motivation_value_id]" required
                                    class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                                <option value="">-- Select Motivation Value --</option>
                                @foreach($motivationValues as $value)
                                    <option value="{{ $value->id }}" 
                                            {{ old("options.$index.motivation_value_id", $option->motivation_value_id) == $value->id ? 'selected' : '' }}>
                                        {{ $value->title }} ({{ $value->value_key }})
                                    </option>
                                @endforeach
                            </select>
                            
                            <input type="hidden" name="options[{{ $index }}][label]" value="{{ $option->option_label ?? ('Option ' . ($index === 0 ? 'A' : 'B')) }}">
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex gap-4">
                <button type="submit" 
                        class="bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded flex items-center gap-2">
                    <i class="fas fa-save"></i> Update Question
                </button>
                <a href="{{ route('admin.supercharging.motivation.questions.index') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded flex items-center gap-2">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</x-app-layout>

