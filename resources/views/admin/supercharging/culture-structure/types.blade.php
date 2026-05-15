<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Culture Structure Types
        </h2>
    </x-slot>

    <div class="bg-white p-6 rounded shadow">
        <div class="flex justify-between items-center mb-4">
            <div class="flex gap-4 items-center">
                <a href="{{ route('admin.culture-structure.questions.index') }}" 
                   class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded flex items-center gap-2">
                   <i class="fas fa-list"></i> Questions
                </a>
                <a href="{{ route('admin.culture-structure.results.index') }}" 
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
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Type Key</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Title</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Summary</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Status</th>
                    <th class="border border-gray-300 px-4 py-2 text-left font-semibold w-32">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($types as $type)
                    <tr class="border border-gray-300 hover:bg-red-50">
                        <td class="px-4 py-2">{{ $type->order }}</td>
                        <td class="px-4 py-2 font-mono text-xs">{{ $type->type_key }}</td>
                        <td class="px-4 py-2 font-semibold">{{ $type->title }}</td>
                        <td class="px-4 py-2">{{ Str::limit($type->summary, 50) }}</td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-1 rounded text-xs {{ $type->status == 'Active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $type->status }}
                            </span>
                        </td>
                        <td class="px-4 py-2 flex gap-2 justify-end">
                            <a href="{{ route('admin.culture-structure.types.edit', $type->id) }}"
                               class="rounded p-1 flex items-center justify-center hover:bg-red-50"
                               title="Edit">
                                <img src="{{ asset('images/edit.svg') }}" alt="Edit" class="h-8 w-8">
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-gray-500">No culture types found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>

