<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Add Team Role Map Question
        </h2>
    </x-slot>

    <div class="bg-white p-6 rounded shadow">
        <div class="mb-4">
            <a href="{{ route('admin.cot.questions.index') }}" 
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

        <form action="{{ route('admin.cot.questions.store') }}" method="POST">
            @csrf
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Question <span class="text-red-500">*</span></label>
                <textarea name="question" rows="3" required
                          class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500"
                          placeholder="Enter the question text...">{{ old('question') }}</textarea>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Order</label>
                <input type="number" name="order" value="{{ old('order', 0) }}"
                       class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Status <span class="text-red-500">*</span></label>
                <select name="status" required
                        class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    <option value="Active" {{ old('status') == 'Active' ? 'selected' : '' }}>Active</option>
                    <option value="Inactive" {{ old('status') == 'Inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Options (8 required) <span class="text-red-500">*</span></label>
                <p class="text-sm text-gray-500 mb-4">Each option must be assigned to one of the 8 team roles.</p>
                
                <div id="options-container" class="space-y-4">
                    @for($i = 0; $i < 8; $i++)
                        <div class="border border-gray-300 rounded p-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Option {{ $i + 1 }} <span class="text-red-500">*</span></label>
                            <input type="text" name="options[{{ $i }}][text]" required
                                   value="{{ old("options.$i.text") }}"
                                   placeholder="Enter option text..."
                                   class="w-full border border-gray-300 rounded px-4 py-2 mb-2 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                            
                            <label class="block text-sm font-medium text-gray-700 mb-2">Assign to Role <span class="text-red-500">*</span></label>
                            <select name="options[{{ $i }}][role_key]" required
                                    class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                                <option value="">-- Select Role --</option>
                                @foreach($roleDescriptions as $role)
                                    <option value="{{ $role->role_key }}" 
                                            {{ old("options.$i.role_key") == $role->role_key ? 'selected' : '' }}>
                                        {{ $role->title }} ({{ $role->value_focus }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endfor
                </div>
            </div>

            <div class="flex gap-4">
                <button type="submit" 
                        class="bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded flex items-center gap-2">
                    <i class="fas fa-save"></i> Save Question
                </button>
                <a href="{{ route('admin.cot.questions.index') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded flex items-center gap-2">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</x-app-layout>

