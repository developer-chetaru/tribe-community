<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Motivation Results
        </h2>
    </x-slot>

    <div class="bg-white p-6 rounded shadow">
        <div class="mb-4 flex gap-4">
            <a href="{{ route('admin.motivation.questions.index') }}" 
               class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded flex items-center gap-2">
               <i class="fas fa-list"></i> Questions
            </a>
            <a href="{{ route('admin.motivation.values.index') }}" 
               class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded flex items-center gap-2">
               <i class="fas fa-book"></i> Values
            </a>
        </div>

        <!-- Filters -->
        <form method="GET" action="{{ route('admin.motivation.results.index') }}" class="mb-6 bg-gray-50 p-4 rounded">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Organisation ID</label>
                    <input type="number" name="orgId" value="{{ request('orgId') }}" 
                           placeholder="Enter organisation ID..."
                           class="w-full border border-gray-300 rounded px-4 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">User ID</label>
                    <input type="number" name="userId" value="{{ request('userId') }}" 
                           placeholder="Enter user ID..."
                           class="w-full border border-gray-300 rounded px-4 py-2">
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="{{ route('admin.motivation.results.index') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                    Clear
                </a>
            </div>
        </form>

        <table class="w-full border-collapse border border-gray-200 text-sm">
            <thead>
                <tr class="bg-gray-100">
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">User</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Motivation Value</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Score</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Rank</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Assessment Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($results as $result)
                    <tr class="border border-gray-300 hover:bg-red-50">
                        <td class="px-4 py-2">
                            {{ $result->user->first_name ?? 'N/A' }} {{ $result->user->last_name ?? '' }}
                        </td>
                        <td class="px-4 py-2 font-semibold">
                            {{ $result->motivationValue->title ?? $result->value_key }}
                        </td>
                        <td class="px-4 py-2">
                            <span class="font-semibold">{{ number_format($result->score, 2) }}</span>
                        </td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-1 rounded text-xs bg-blue-100 text-blue-800">
                                #{{ $result->rank }}
                            </span>
                        </td>
                        <td class="px-4 py-2">{{ $result->assessment_date }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-gray-500">No results found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="mt-4">
            {{ $results->links() }}
        </div>
    </div>
</x-app-layout>

