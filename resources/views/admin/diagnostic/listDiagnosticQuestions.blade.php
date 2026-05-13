<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Diagnostic Questions
        </h2>
    </x-slot>

    <div class="bg-white p-6 rounded shadow">
        <div class="flex justify-between items-center mb-4">
            <a href="{{ route('admin.diagnostic.category.list') }}" 
               class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded flex items-center gap-2">
               <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
               </svg>
               Back to Categories
            </a>
            <button onclick="openAddQuestionModal()" 
               class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded flex items-center gap-2">
               <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
               </svg>
               Add Question
            </button>
        </div>

        @if (session('success'))
            <div class="mb-4 px-4 py-2 bg-green-100 border border-green-400 text-green-700 rounded">
                {{ session('success') }}
            </div>
        @endif

        <table class="w-full border-collapse border border-gray-200 text-sm">
            <thead>
                <tr class="bg-gray-100">
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">ID</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Question</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Category</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Measure</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Status</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold w-32">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($questions as $question)
                    <tr class="border border-gray-300 hover:bg-red-50">
                        <td class="px-4 py-2">{{ $question->id }}</td>
                        <td class="px-4 py-2">{{ Str::limit($question->question, 80) }}</td>
                        <td class="px-4 py-2">
                            @if($question->category)
                                <span class="bg-blue-100 text-blue-600 px-2 py-1 rounded text-xs">
                                    {{ $question->category->title }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ Str::limit($question->measure ?? 'N/A', 50) }}</td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-1 rounded text-xs {{ $question->status == 'Active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $question->status }}
                            </span>
                        </td>
                        <td class="px-4 py-2">
                            <button onclick="editQuestion({{ $question->id }}, '{{ addslashes($question->question) }}', '{{ addslashes($question->measure ?? '') }}', {{ $question->category_id ?? 'null' }})" 
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded text-xs mr-1">
                                Edit
                            </button>
                            <button onclick="deleteQuestion({{ $question->id }})" 
                                    class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-xs">
                                Delete
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">No questions found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="mt-4">
            {{ $questions->links() }}
        </div>
    </div>

    <!-- Add/Edit Question Modal -->
    <div id="questionModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg w-full max-w-md">
            <h3 class="text-xl font-bold mb-4" id="modalTitle">Add Question</h3>
            <form id="questionForm">
                <input type="hidden" id="questionId" name="questionId">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Question</label>
                    <input type="text" id="questionText" name="question" required 
                           class="w-full border border-gray-300 rounded px-3 py-2">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Measure</label>
                    <input type="text" id="measureText" name="measure" 
                           class="w-full border border-gray-300 rounded px-3 py-2">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Category</label>
                    <select id="categorySelect" name="categoryId" required 
                            class="w-full border border-gray-300 rounded px-3 py-2">
                        <option value="">Select Category</option>
                        @foreach(\App\Models\DiagnosticQuestionsCategory::all() as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->title }}</option>
                        @endforeach
                    </select>
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
        function openAddQuestionModal() {
            document.getElementById('modalTitle').textContent = 'Add Question';
            document.getElementById('questionForm').reset();
            document.getElementById('questionId').value = '';
            document.getElementById('questionModal').classList.remove('hidden');
        }

        function editQuestion(id, question, measure, categoryId) {
            document.getElementById('modalTitle').textContent = 'Edit Question';
            document.getElementById('questionId').value = id;
            document.getElementById('questionText').value = question;
            document.getElementById('measureText').value = measure || '';
            document.getElementById('categorySelect').value = categoryId || '';
            document.getElementById('questionModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('questionModal').classList.add('hidden');
        }

        function deleteQuestion(id) {
            if (confirm('Are you sure you want to delete this question?')) {
                fetch('/admin/delete-diagnostic-question', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ questionId: id })
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

        document.getElementById('questionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const questionId = document.getElementById('questionId').value;
            
            if (questionId) {
                // Update existing question
                fetch('/admin/diagnostic/' + btoa(questionId), {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        question: formData.get('question'),
                        measure: formData.get('measure'),
                        categoryId: formData.get('categoryId')
                    })
                })
                .then(response => response.redirect())
                .then(() => location.reload());
            } else {
                // Add new question
                fetch('/admin/add-diagnostic-question', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        question: formData.get('question'),
                        measure: formData.get('measure'),
                        categoryId: formData.get('categoryId')
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

