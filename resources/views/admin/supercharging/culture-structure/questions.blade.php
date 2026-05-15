<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Culture Structure Questions
        </h2>
    </x-slot>

    <div class="bg-white p-6 rounded shadow">
        <div class="flex justify-between items-center mb-4">
            <div class="flex gap-4 items-center">
                <a href="{{ route('admin.culture-structure.types.index') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded flex items-center gap-2">
                   <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
            <a href="{{ route('admin.culture-structure.questions.create') }}" 
               class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded flex items-center gap-2">
               <i class="fas fa-plus"></i> Add Question
            </a>
        </div>

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

        <!-- Search and Filter -->
        <form method="GET" action="{{ route('admin.culture-structure.questions.index') }}" class="mb-4">
            <div class="flex gap-4">
                <input type="text" name="search" value="{{ request('search') }}" 
                       placeholder="Search questions..." 
                       class="flex-1 border border-gray-300 rounded px-4 py-2">
                <select name="status" class="border border-gray-300 rounded px-4 py-2">
                    <option value="">All Status</option>
                    <option value="Active" {{ request('status') == 'Active' ? 'selected' : '' }}>Active</option>
                    <option value="Inactive" {{ request('status') == 'Inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                    <i class="fas fa-search"></i> Search
                </button>
                <a href="{{ route('admin.culture-structure.questions.index') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded flex items-center">
                    <i class="fas fa-times"></i> Clear
                </a>
            </div>
        </form>

        <table class="w-full border-collapse border border-gray-200 text-sm">
            <thead>
                <tr class="bg-gray-100">
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Order</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Question</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Options</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Status</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold w-32">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($questions as $question)
                    <tr class="border border-gray-300 hover:bg-red-50">
                        <td class="px-4 py-2">{{ $question->order }}</td>
                        <td class="px-4 py-2">{{ Str::limit($question->question, 100) }}</td>
                        <td class="px-4 py-2">
                            <span class="text-xs text-gray-600">
                                {{ $question->options->count() }} options
                            </span>
                        </td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-1 rounded text-xs {{ $question->status == 'Active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $question->status }}
                            </span>
                        </td>
                        <td class="px-4 py-2 flex gap-2 justify-end">
                            <a href="{{ route('admin.culture-structure.questions.edit', $question->id) }}"
                               class="rounded p-1 flex items-center justify-center hover:bg-red-50"
                               title="Edit">
                                <img src="{{ asset('images/edit.svg') }}" alt="Edit" class="h-8 w-8">
                            </a>
                            <form action="{{ route('admin.culture-structure.questions.destroy', $question->id) }}" 
                                  method="POST" 
                                  onsubmit="return confirm('Are you sure you want to delete this question?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" 
                                        class="p-1 rounded flex items-center justify-center hover:bg-red-50"
                                        title="Delete">
                                    <img src="{{ asset('images/delete.svg') }}" alt="Delete" class="h-8 w-8">
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-gray-500">No questions found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="mt-4">
            {{ $questions->links() }}
        </div>
    </div>
</x-app-layout>

