<x-slot name="header">
   <h2 class="text-[24px] md:text-[30px] capitalize font-bold text-[#EB1C24]">
        Principles
    </h2>
</x-slot>

<div class="bg-white p-8 shadow rounded-xl" x-data="{ editingId: null }">
    <!-- Add Principle Button -->
    <div class="flex justify-end mb-4">
        <a href="{{ route('principles.add')}}" 
           class="bg-[#EB1C24] hover:bg-red-600 text-[16px] text-white px-4 py-3 rounded-[8px] flex items-center gap-2">
           <i class="fas fa-plus"></i> Add Principle
        </a>
    </div>

    <!-- Session Message -->
    @if (session()->has('message'))
        <div x-data="{ show: true }" 
             x-init="setTimeout(() => show = false, 2000)" 
             x-show="show" x-transition
             class="mb-4 px-4 py-2 rounded-md border bg-red-500 text-white">
            <span>{{ session('message') }}</span>
        </div>
    @endif

    <!-- Principles Table -->
  <div class="overflow-x-auto">

        <table class="w-full text-sm border rounded-lg">

            <thead class="bg-gray-100">

                <tr>

                    <th class="text-left px-4 py-3 font-[600] text-[14px] text-[#020202]">Title</th>

                    <th class="text-left px-4 py-3 font-[600] text-[14px] text-[#020202]">Score</th>

					<th class="text-left px-4 py-3 font-semibold">Priority</th>

                    <th class="text-left px-4 py-3 font-semibold w-[130px]">Actions</th>

                </tr>

            </thead>

            <tbody class="divide-y">
                @foreach ($principles as $item)
                <tr class="bg-white hover:bg-red-50 border-b">
                    <td class="px-4 py-3 font-[400] text-[14px] text-[#808080]">{{ $item['title'] }}</td>
                    <td class="px-4 py-3 font-[400] text-[14px] text-[#808080]">{{ $item['description'] }}</td>
					<td class="px-4 py-3 text-[14px] text-[#808080]">{{ $item['priority'] }}</td>
                    <td class="px-4 py-3 font-[400] text-[14px] text-[#808080] w-[130px]">
                        <div class="flex gap-2 items-center">

                            <!-- Edit Button -->
                            <a href="{{ route('principle.edit', $item['id']) }}"
                               class="rounded p-1 flex items-center justify-center hover:bg-red-50"
                               title="Edit">
                                <img src="{{ asset('images/edit.svg') }}" alt="Edit" class="h-8 w-8">
                            </a>

                            <!-- Delete Button with Modal -->
                            <div x-data="{ open: false }" @keydown.escape.window="open = false">
                                <button @click="open = true"
                                        class="p-1 rounded flex items-center justify-center hover:bg-red-50"
                                        title="Delete">
                                    <img src="{{ asset('images/delete.svg') }}" alt="Delete" class="h-8 w-8">
                                </button>

                                <!-- Modal -->
                                <div x-show="open" x-cloak
                                     class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50"
                                     x-transition>
                                    <div class="bg-white rounded-lg shadow-lg max-w-sm w-full p-6" @click.away="open = false">
                                        <h3 class="text-lg font-semibold mb-4 text-red-600">Delete Principle</h3>
                                        <p class="mb-4 font-[400] text-[16px] text-[#808080]">
                                            Are you sure you want to delete <strong>{{ $item['title'] }}</strong>?
                                        </p>
                                        <div class="flex justify-end gap-2">
                                            <button @click="open = false"
                                                    class="px-4 py-2 bg-gray-300 hover:bg-gray-400 rounded text-sm font-semibold">
                                                Cancel
                                            </button>
                                            <button wire:click="deletePrinciple('{{ $item['id'] }}')" @click="open = false"
                                                    class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded text-white text-sm font-semibold">
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
