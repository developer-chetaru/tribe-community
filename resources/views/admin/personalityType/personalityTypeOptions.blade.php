<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Personality Type Options
        </h2>
    </x-slot>

    <div class="bg-white p-6 rounded shadow">
        <div class="mb-4">
            <a href="{{ route('admin.personality-type.questions.index') }}" 
               class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded flex items-center gap-2 inline-flex">
               <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
               </svg>
               Back to Questions
            </a>
        </div>

        @if (session('success'))
            <div class="mb-4 px-4 py-2 bg-green-100 border border-green-400 text-green-700 rounded">
                {{ session('success') }}
            </div>
        @endif

        <!-- Filter by Question -->
        <form method="GET" action="{{ route('admin.personality-type.options.index') }}" class="mb-4">
            <div class="flex gap-4">
                <select name="question_id" class="border border-gray-300 rounded px-4 py-2">
                    <option value="">All Questions</option>
                    @foreach($questions as $question)
                        <option value="{{ $question->id }}" {{ request('question_id') == $question->id ? 'selected' : '' }}>
                            {{ Str::limit($question->question, 60) }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                    Filter
                </button>
                <a href="{{ route('admin.personality-type.options.index') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded flex items-center">
                    Clear
                </a>
            </div>
        </form>

        <table class="w-full border-collapse border border-gray-200 text-sm">
            <thead>
                <tr class="bg-gray-100">
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Question</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Option Text</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Score Value</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Order</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($options as $option)
                    <tr class="border border-gray-300 hover:bg-red-50">
                        <td class="px-4 py-2">
                            <span class="text-xs text-gray-500">Q{{ $option->question->id }}:</span>
                            {{ Str::limit($option->question->question, 50) }}
                        </td>
                        <td class="px-4 py-2 font-medium">{{ $option->option_text }}</td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-1 rounded text-xs font-semibold
                                {{ $option->score_value == 1 ? 'bg-red-100 text-red-800' : 
                                   ($option->score_value == 2 ? 'bg-orange-100 text-orange-800' : 
                                   ($option->score_value == 3 ? 'bg-yellow-100 text-yellow-800' : 
                                   ($option->score_value == 4 ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'))) }}">
                                {{ $option->score_value }}
                            </span>
                        </td>
                        <td class="px-4 py-2">{{ $option->order }}</td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-1 rounded text-xs {{ $option->status == 'Active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $option->status }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-gray-500">No options found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Pagination -->
        <div class="mt-4">
            {{ $options->links() }}
        </div>

        <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded">
            <p class="text-sm text-blue-800">
                <strong>Note:</strong> Options are automatically created when you add or edit questions. 
                Each question should have exactly 5 options matching the Likert scale (Disagree=1, Mostly Disagree=2, Neutral=3, Mostly Agree=4, Agree=5).
            </p>
        </div>
    </div>
</x-app-layout>

