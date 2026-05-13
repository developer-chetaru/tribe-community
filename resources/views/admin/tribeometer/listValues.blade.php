<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Tribeometer Values
        </h2>
    </x-slot>

    <div class="bg-white p-6 rounded shadow">
        <div class="flex justify-between items-center mb-4">
            <div class="flex gap-4">
                <a href="{{ route('admin.tribeometer.index') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded flex items-center gap-2">
                   <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                   </svg>
                   Back to Questions
                </a>
                <a href="{{ route('admin.tribeometer.option.list') }}" 
                   class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded flex items-center gap-2">
                   View Options
                </a>
            </div>
            <button onclick="openAddValueModal()" 
               class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded flex items-center gap-2">
               <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
               </svg>
               Add Value
            </button>
        </div>

        <table class="w-full border-collapse border border-gray-200 text-sm">
            <thead>
                <tr class="bg-gray-100">
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">ID</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Value Key</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Title</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Description</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Questions Count</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Status</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold w-32">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($values as $value)
                    <tr class="border border-gray-300 hover:bg-red-50">
                        <td class="px-4 py-2">{{ $value->id }}</td>
                        <td class="px-4 py-2">
                            <span class="bg-purple-100 text-purple-600 px-2 py-1 rounded text-xs">
                                {{ $value->value_key }}
                            </span>
                        </td>
                        <td class="px-4 py-2 font-semibold">{{ $value->title }}</td>
                        <td class="px-4 py-2">{{ Str::limit($value->description ?? 'N/A', 50) }}</td>
                        <td class="px-4 py-2">{{ $value->questions->count() }}</td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-1 rounded text-xs {{ $value->status == 'Active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $value->status }}
                            </span>
                        </td>
                        <td class="px-4 py-2">
                            <button onclick="editValue({{ $value->id }}, '{{ addslashes($value->title) }}', '{{ addslashes($value->description ?? '') }}')" 
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded text-xs mr-1">
                                Edit
                            </button>
                            <button onclick="deleteValue({{ $value->id }})" 
                                    class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-xs">
                                Delete
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">No values found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Add/Edit Value Modal -->
    <div id="valueModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg w-full max-w-md">
            <h3 class="text-xl font-bold mb-4" id="modalTitle">Add Value</h3>
            <form id="valueForm">
                <input type="hidden" id="valueId" name="valueId">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Value Key (e.g., directed, committed)</label>
                    <input type="text" id="valueKey" name="valueKey" required 
                           class="w-full border border-gray-300 rounded px-3 py-2" 
                           placeholder="directed">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Title</label>
                    <input type="text" id="valueTitle" name="title" required 
                           class="w-full border border-gray-300 rounded px-3 py-2">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Description</label>
                    <textarea id="valueDescription" name="description" rows="3"
                           class="w-full border border-gray-300 rounded px-3 py-2"></textarea>
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
        function openAddValueModal() {
            document.getElementById('modalTitle').textContent = 'Add Value';
            document.getElementById('valueForm').reset();
            document.getElementById('valueId').value = '';
            document.getElementById('valueModal').classList.remove('hidden');
        }

        function editValue(id, title, description) {
            document.getElementById('modalTitle').textContent = 'Edit Value';
            document.getElementById('valueId').value = btoa(id);
            document.getElementById('valueTitle').value = title;
            document.getElementById('valueDescription').value = description || '';
            document.getElementById('valueModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('valueModal').classList.add('hidden');
        }

        function deleteValue(id) {
            if (confirm('Are you sure you want to delete this value? This will set value_id to null for all related questions.')) {
                fetch('{{ route('admin.tribeometer.value.delete') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ valueId: id })
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

        document.getElementById('valueForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const valueId = document.getElementById('valueId').value;
            
            if (valueId) {
                // Update existing value
                fetch('{{ route('admin.tribeometer.value.update') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        valueId: valueId,
                        title: formData.get('title'),
                        description: formData.get('description')
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
                // Add new value
                fetch('{{ route('admin.tribeometer.value.add') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        valueKey: formData.get('valueKey'),
                        title: formData.get('title'),
                        description: formData.get('description')
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

