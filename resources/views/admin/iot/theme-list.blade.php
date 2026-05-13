<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Risk Themes
        </h2>
    </x-slot>

    <div class="flex-1 overflow-auto">
        <div class="max-w-8xl mx-auto p-4">
            <div class="mb-4 flex justify-between items-center">
                <a href="{{ route('organisations.view', ['id' => $orgId]) }}"
                   class="bg-[#fff] px-4 py-3 rounded-md shadow hover:bg-[#c71313] text-sm hover:text-white transition inline-flex items-center">
                    <svg width="7" height="11" viewBox="0 0 7 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
                        <path d="M6 10.5C6 10.5 1 6.81758 1 5.5C1 4.18233 6 0.5 6 0.5" stroke="#808080" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Back to Organisation
                </a>

                <a href="{{ route('admin.add-theme', ['orgId' => $orgId]) }}"
                   class="bg-[#EB1C24] text-white px-5 py-3 rounded-md shadow text-sm font-medium inline-flex items-center hover:bg-[#c71313] transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add New Theme
                </a>
            </div>

            @if (session('success'))
                <div class="mb-4 p-3 bg-green-100 text-green-800 rounded-lg border border-green-300">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Risk Rating</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Opened</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($themes as $theme)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ $theme->title }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-500 max-w-md truncate">
                                            {{ \Illuminate\Support\Str::limit($theme->description, 80) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @php
                                            $riskRating = $theme->currentRiskRating ?? $theme->initialRiskRating ?? 0;
                                            $riskColor = $riskRating <= 5 ? 'bg-green-100 text-green-800' :
                                                        ($riskRating <= 12 ? 'bg-yellow-100 text-yellow-800' :
                                                        ($riskRating <= 20 ? 'bg-orange-100 text-orange-800' : 'bg-red-100 text-red-800'));
                                        @endphp
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $riskColor }}">
                                            {{ $riskRating }}
                                        </span>
                                        <div class="text-xs text-gray-500 mt-1">
                                            L: {{ $theme->currentLikelihood ?? $theme->initialLikelihood ?? '-' }}
                                            C: {{ $theme->currentConsequence ?? $theme->initialConsequence ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $theme->themeStatus == 'Open' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ $theme->themeStatus }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $theme->dateOpened ? \Carbon\Carbon::parse($theme->dateOpened)->format('d M Y') : '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="{{ route('admin.edit-theme', ['themeId' => $theme->id]) }}"
                                           class="text-[#EB1C24] hover:text-[#c71313]">
                                            <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                            Edit
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                        No themes found. <a href="{{ route('admin.add-theme', ['orgId' => $orgId]) }}" class="text-[#EB1C24] hover:underline">Create one</a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

