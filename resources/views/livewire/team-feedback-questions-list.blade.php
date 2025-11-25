<x-slot name="header">
 <h2 class="text-2xl font-bold capitalize text-[#ff2323]">
   Team Feedback Question
  </h2>
</x-slot>
<div class="bg-white p-6 rounded shadow" x-data="{ showConfirm: false, deptId: null }">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold text-red-600"></h2>
        <a href="{{ route('team-feedback.add') }}" 
           class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded flex items-center gap-2">
           <i class="fas fa-plus"></i> Add Question
        </a>
    </div>

    @if (session()->has('message'))
        <div x-data="{ show: true }" 
             x-init="setTimeout(() => show = false, 2000)" 
             x-show="show" x-transition
             class="gap-2 items-center mb-4 px-4 py-2 rounded-lg border {{ session('type') === 'error' ? 'bg-red-500 border-red-400 text-white' : 'bg-red-500 border-red-400 text-white' }}">
            <span>{{ session('message') }}</span>
        </div>
    @endif

    <table class="w-full border-collapse border border-gray-200 text-sm">
        <thead>
            <tr class="bg-gray-100">
                <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Question</th>
                <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Principle</th>
                <th class="border border-gray-300 px-4 py-2 text-left font-semibold w-32">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($questions as $q)
                <tr class="border border-gray-300 hover:bg-red-50">
                    <td class="px-4 py-2">{{ $q->question }}</td>
                    <td class="px-4 py-2">{{ $q->principle->title ?? '-' }}</td>
                    <td class="px-4 py-2 flex gap-2 justify-end">
                        
    <a href="{{ route('team-feedback.edit', $q->id) }}"
                               class="rounded p-1 flex items-center justify-center hover:bg-red-50"
                               title="Edit">
                                <img src="{{ asset('images/edit.svg') }}" alt="Edit" class="h-8 w-8">
                            </a>
                        {{-- Delete --}}
                     
    							<button @click="showConfirm = true; deptId = {{ $q->id }}" 
                                        class="p-1 rounded flex items-center justify-center hover:bg-red-50"
                                        title="Delete">
                                    <img src="{{ asset('images/delete.svg') }}" alt="Delete" class="h-8 w-8">
                                </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center py-4">No questions found.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Delete Confirmation Modal --}}
    <div x-show="showConfirm" x-cloak
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
            <h2 class="text-lg font-semibold text-red-600 mb-4">Confirm Deletion</h2>
            <p class="text-gray-700 mb-6">Are you sure you want to delete this question?</p>

            <div class="flex justify-end gap-3">
                <button @click="showConfirm = false" 
                        class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">
                    Cancel
                </button>
                <button @click="$wire.delete(deptId); showConfirm = false" 
                        class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>
