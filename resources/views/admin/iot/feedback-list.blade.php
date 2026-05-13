<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Offloadings - {{ ucfirst(str_replace('_', ' ', $status)) }}
        </h2>
    </x-slot>

    <div class="flex-1 overflow-auto">
        <div class="max-w-8xl mx-auto p-4">
            <div class="mb-4">
                <a href="{{ route('admin.iot.dashboard', ['orgId' => $orgId]) }}"
                   class="bg-[#fff] px-4 py-3 rounded-md shadow hover:bg-[#c71313] text-sm hover:text-white transition inline-flex items-center">
                    <svg width="7" height="11" viewBox="0 0 7 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
                        <path d="M6 10.5C6 10.5 1 6.81758 1 5.5C1 4.18233 6 0.5 6 0.5" stroke="#808080" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Back to Dashboard
                </a>
            </div>

            @if (session('success'))
                <div class="mb-4 p-3 bg-green-100 text-green-800 rounded-lg border border-green-300">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 p-3 bg-red-100 text-red-800 rounded-lg border border-red-300">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Office</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($feedbacks as $feedback)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $feedback->first_name ?? '' }} {{ $feedback->last_name ?? '' }}
                                        </div>
                                        <div class="text-sm text-gray-500">{{ $feedback->email ?? '' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $feedback->office_name ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 max-w-md truncate">
                                            {{ \Illuminate\Support\Str::limit($feedback->message, 100) }}
                                        </div>
                                        @if($feedback->image)
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mt-1">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                                Has Attachment
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @php
                                            $statusColors = [
                                                '1' => 'bg-blue-100 text-blue-800',
                                                '2' => 'bg-gray-100 text-gray-800',
                                                '3' => 'bg-yellow-100 text-yellow-800',
                                                '4' => 'bg-orange-100 text-orange-800',
                                                '5' => 'bg-purple-100 text-purple-800',
                                                '6' => 'bg-green-100 text-green-800',
                                            ];
                                            $statusColor = $statusColors[$feedback->feedbackStatus] ?? 'bg-gray-100 text-gray-800';
                                            $statusLabels = [
                                                '1' => 'Open',
                                                '2' => 'Close',
                                                '3' => 'Open awaiting R/A',
                                                '4' => 'Closed awaiting R/A',
                                                '5' => 'Need Info',
                                                '6' => 'No Further Action',
                                            ];
                                            $statusLabel = $statusLabels[$feedback->feedbackStatus] ?? 'Open';
                                        @endphp
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusColor }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ \Carbon\Carbon::parse($feedback->created_at)->format('d M Y, h:i A') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="{{ route('admin.iot.chatbox', ['feedbackId' => $feedback->id]) }}"
                                           class="text-[#EB1C24] hover:text-[#c71313] mr-3">
                                            <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                            </svg>
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                        No offloadings found.
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

