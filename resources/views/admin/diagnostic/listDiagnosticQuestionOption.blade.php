<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Diagnostic Question Options
        </h2>
    </x-slot>

    <div class="bg-white p-6 rounded shadow">
        <div class="flex justify-between items-center mb-4">
            <a href="{{ route('admin.diagnostic.index') }}" 
               class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded flex items-center gap-2">
               <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
               </svg>
               Back to Questions
            </a>
            <button onclick="openAddOptionModal()" 
               class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded flex items-center gap-2">
               <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
               </svg>
               Add Option
            </button>
        </div>

        <table class="w-full border-collapse border border-gray-200 text-sm">
            <thead>
                <tr class="bg-gray-100">
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">ID</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Option Name</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Rating</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Status</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold w-32">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($options as $option)
                    <tr class="border border-gray-300 hover:bg-red-50">
                        <td class="px-4 py-2">{{ $option->id }}</td>
                        <td class="px-4 py-2">{{ $option->option_name }}</td>
                        <td class="px-4 py-2">{{ $option->option_rating }}</td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-1 rounded text-xs {{ $option->status == 'Active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $option->status }}
                            </span>
                        </td>
                        <td class="px-4 py-2">
                            <button onclick="editOption({{ $option->id }}, '{{ addslashes($option->option_name) }}', {{ $option->option_rating }})" 
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded text-xs mr-1">
                                Edit
                            </button>
                            <button onclick="deleteOption({{ $option->id }})" 
                                    class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-xs">
                                Delete
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">No options found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Add/Edit Option Modal -->
    <div id="optionModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg w-full max-w-md">
            <h3 class="text-xl font-bold mb-4" id="modalTitle">Add Option</h3>
            <form id="optionForm">
                <input type="hidden" id="optionId" name="optionId">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Option Name</label>
                    <input type="text" id="optionName" name="optionName" required 
                           class="w-full border border-gray-300 rounded px-3 py-2">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Rating (0-4)</label>
                    <input type="number" id="optionRating" name="optionRating" min="0" max="4" required 
                           class="w-full border border-gray-300 rounded px-3 py-2">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeModal()" 
                            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddOptionModal() {
            document.getElementById('modalTitle').textContent = 'Add Option';
            document.getElementById('optionForm').reset();
            document.getElementById('optionId').value = '';
            document.getElementById('optionModal').classList.remove('hidden');
        }

        function editOption(id, name, rating) {
            document.getElementById('modalTitle').textContent = 'Edit Option';
            document.getElementById('optionId').value = btoa(id);
            document.getElementById('optionName').value = name;
            document.getElementById('optionRating').value = rating;
            document.getElementById('optionModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('optionModal').classList.add('hidden');
        }

        function deleteOption(id) {
            if (confirm('Are you sure you want to delete this option?')) {
                fetch('/admin/delete-diagnostic-option', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ optionId: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }

        document.getElementById('optionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const optionId = document.getElementById('optionId').value;
            
            if (optionId) {
                // Update existing option
                fetch('/admin/update-dia-opt', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        optionId: optionId,
                        option: formData.get('optionName'),
                        optionRating: formData.get('optionRating')
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            } else {
                // Add new option
                fetch('/admin/add-diagnostic-option', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        optionName: formData.get('optionName'),
                        optionRating: formData.get('optionRating')
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        });
    </script>
</x-app-layout>

