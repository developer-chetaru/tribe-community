<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Motivation Values
        </h2>
    </x-slot>

    <div class="bg-white p-6 rounded shadow">
        <div class="flex justify-between items-center mb-4">
            <div class="flex gap-4 items-center">
                <a href="{{ route('admin.motivation.questions.index') }}" 
                   class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded flex items-center gap-2">
                   <i class="fas fa-list"></i> Questions
                </a>
                <a href="{{ route('admin.motivation.results.index') }}" 
                   class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded flex items-center gap-2">
                   <i class="fas fa-chart-bar"></i> Results
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="mb-4 px-4 py-2 bg-green-100 border border-green-400 text-green-700 rounded">
                {{ session('success') }}
            </div>
        @endif

        <table class="w-full border-collapse border border-gray-200 text-sm">
            <thead>
                <tr class="bg-gray-100">
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Order</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Value Key</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Title</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Description</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Status</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold w-32">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($values as $value)
                    <tr class="border border-gray-300 hover:bg-red-50">
                        <td class="px-4 py-2">{{ $value->order }}</td>
                        <td class="px-4 py-2 font-mono text-xs font-semibold">{{ $value->value_key }}</td>
                        <td class="px-4 py-2 font-semibold">{{ $value->title }}</td>
                        <td class="px-4 py-2">{{ Str::limit($value->description, 80) }}</td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-1 rounded text-xs {{ $value->status == 'Active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $value->status }}
                            </span>
                        </td>
                        <td class="px-4 py-2 flex gap-2 justify-end">
                            <a href="{{ route('admin.motivation.values.edit', $value->id) }}"
                               class="rounded p-1 flex items-center justify-center hover:bg-red-50"
                               title="Edit">
                                <img src="{{ asset('images/edit.svg') }}" alt="Edit" class="h-8 w-8">
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-gray-500">No motivation values found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>

