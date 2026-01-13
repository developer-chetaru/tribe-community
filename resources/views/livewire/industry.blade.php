<x-slot name="header">
    <h2 class="text-[24px] md:text-[30px] capitalize font-bold text-[#EB1C24]">
        Industry
    </h2>
</x-slot>

<div class="bg-white p-6 rounded shadow" x-data="{ showConfirm: false, valueId: null }">

    <div class="flex justify-between items-center mb-4 pb-2">
        <h2 class="text-lg font-bold text-red-600"></h2>
        <div class="flex justify-end mb-3">
            <a wire:navigate href="{{ route('industries.add') }}" 
               class="bg-[#EB1C24] text-[16px] text-white hover:bg-red-600 px-4 py-3 rounded-[8px] flex items-center gap-2 transition">
                <i class="fas fa-plus"></i> Add
            </a>
        </div>
    </div>

    {{-- Flash Message --}}
    @if (session()->has('message'))
        <div x-data="{ show: true }" 
             x-init="setTimeout(() => show = false, 2000)" 
             x-show="show" x-transition
             class="gap-2 items-center mb-4 px-4 py-2 rounded-lg border bg-red-500 border-red-400 text-white">
            <span>{{ session('message') }}</span>
        </div>
    @endif

    {{-- Table --}}
    <div class="w-full rounded-[8px] border border-[#E5E5E5] overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-100">
                    <th class="text-left px-4 py-2 font-semibold">
                        <button wire:click="sort('name')" class="flex items-center gap-2 hover:text-red-600 transition">
                            Name
                            @if($sortBy === 'name')
                                @if($sortDirection === 'asc')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                    </svg>
                                @else
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                @endif
                            @else
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                                </svg>
                            @endif
                        </button>
                    </th>
                    <th class="text-left px-4 py-2 font-semibold">
                        <button wire:click="sort('status')" class="flex items-center gap-2 hover:text-red-600 transition">
                            Active
                            @if($sortBy === 'status')
                                @if($sortDirection === 'asc')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                    </svg>
                                @else
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                @endif
                            @else
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                                </svg>
                            @endif
                        </button>
                    </th>
                  
                    <th class="w-32"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($values as $val)
                    <tr class="border-b last:border-b-0 hover:bg-gray-50 border-[#E5E5E5]">
                        <td class="px-4 py-3">{{ $val->name }}</td>
 <td class="px-4 py-3">
                    @if($val->status == 1)
                        <span class="inline-block px-2 py-1 text-white bg-green-500 rounded text-sm">Active</span>
                    @else
                        <span class="inline-block px-2 py-1 text-white bg-red-500 rounded text-sm">Inactive</span>
                    @endif
                </td>

                        <td class="px-4 py-3 flex gap-2 justify-end">
                            {{-- Edit --}}
                            <a href="{{ route('industries.edit', $val->id) }}"  
                               class="rounded p-1 flex items-center justify-center hover:bg-red-50"
                               title="Edit">
                                <img src="{{ asset('images/edit.svg') }}" alt="Edit" class="h-8 w-8">
                            </a>
                            {{-- Delete --}}
                            <button @click="showConfirm = true; valueId = '{{ $val->id }}'" 
                                    class="p-1 rounded flex items-center justify-center hover:bg-red-50"
                                    title="Delete">
                                <img src="{{ asset('images/delete.svg') }}" alt="Delete" class="h-8 w-8">
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="text-center px-4 py-3">No Industry Values Found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination Links --}}
    <div class="mt-4 flex justify-center">
        {{ $values->links('components.pagination') }}
    </div>

    {{-- Delete Confirmation Modal --}}
    <div x-show="showConfirm" x-cloak
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
            <h2 class="text-lg font-semibold text-red-600 mb-4">Confirm Deletion</h2>
            <p class="text-gray-700 mb-6">Are you sure you want to delete this value?</p>

            <div class="flex justify-end gap-3">
                <button @click="showConfirm = false" 
                        class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">
                    Cancel
                </button>
                <button @click="$wire.call('delete', valueId); showConfirm = false" 
                        class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                    Delete
                </button>
            </div>
        </div>
    </div>

</div>
