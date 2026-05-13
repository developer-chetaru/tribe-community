<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Edit Tribeometer Question
        </h2>
    </x-slot>

    <div class="bg-white p-6 rounded shadow">
        <div class="mb-4">
            <a href="{{ route('admin.tribeometer.index') }}" 
               class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded flex items-center gap-2 inline-block">
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

        <form method="POST" action="{{ route('admin.tribeometer.question.update', base64_encode($question->id)) }}">
            @csrf
            
            <div class="mb-4">
                <label class="block text-sm font-medium mb-2">Question</label>
                <textarea name="question" required rows="4"
                       class="w-full border border-gray-300 rounded px-3 py-2">{{ old('question', $question->question ?? '') }}</textarea>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium mb-2">Measure</label>
                <input type="text" name="measure" 
                       class="w-full border border-gray-300 rounded px-3 py-2"
                       value="{{ old('measure', $question->measure ?? '') }}">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium mb-2">Value</label>
                <select name="valueId" required 
                        class="w-full border border-gray-300 rounded px-3 py-2">
                    <option value="">Select Value</option>
                    @foreach(\App\Models\TribeometerValue::where('status', 'Active')->get() as $value)
                        <option value="{{ $value->id }}" 
                                {{ old('valueId', $question->value_id ?? '') == $value->id ? 'selected' : '' }}>
                            {{ $value->title }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            <div class="flex justify-end gap-2">
                <a href="{{ route('admin.tribeometer.index') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                    Cancel
                </a>
                <button type="submit" 
                        class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded">
                    Update Question
                </button>
            </div>
        </form>
    </div>
</x-app-layout>

