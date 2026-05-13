<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Edit Motivation Value - {{ $value->title }}
        </h2>
    </x-slot>

    <div class="bg-white p-6 rounded shadow">
        <div class="mb-4">
            <a href="{{ route('admin.supercharging.motivation.values.index') }}" 
               class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded flex items-center gap-2 inline-flex">
               <i class="fas fa-arrow-left"></i> Back to Values
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

        <form action="{{ route('admin.supercharging.motivation.values.update', $value->id) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Value Key</label>
                <input type="text" value="{{ $value->value_key }}" disabled
                       class="w-full border border-gray-300 rounded px-4 py-2 bg-gray-100 font-mono">
                <p class="text-xs text-gray-500 mt-1">Value key cannot be changed</p>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Title <span class="text-red-500">*</span></label>
                <input type="text" name="title" value="{{ old('title', $value->title) }}" required
                       class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <textarea name="description" rows="4"
                          class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500"
                          placeholder="Enter detailed description...">{{ old('description', $value->description) }}</textarea>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Characteristics</label>
                <textarea name="characteristics" rows="3"
                          class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500"
                          placeholder="Enter key characteristics (comma-separated)...">{{ old('characteristics', $value->characteristics) }}</textarea>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Management Strategy</label>
                <textarea name="management_strategy" rows="3"
                          class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500"
                          placeholder="Enter management recommendations...">{{ old('management_strategy', $value->management_strategy) }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Order</label>
                    <input type="number" name="order" value="{{ old('order', $value->order) }}"
                           class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status <span class="text-red-500">*</span></label>
                    <select name="status" required
                            class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                        <option value="Active" {{ old('status', $value->status) == 'Active' ? 'selected' : '' }}>Active</option>
                        <option value="Inactive" {{ old('status', $value->status) == 'Inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="flex gap-4">
                <button type="submit" 
                        class="bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded flex items-center gap-2">
                    <i class="fas fa-save"></i> Update Value
                </button>
                <a href="{{ route('admin.supercharging.motivation.values.index') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded flex items-center gap-2">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</x-app-layout>

