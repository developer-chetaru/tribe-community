<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            My Team Role Map Results
        </h2>
    </x-slot>

    <div class="bg-white p-6 rounded shadow">
        <div class="mb-4 flex flex-wrap gap-4">
            <a href="{{ route('connecting.team-role-map') }}" 
               class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg font-semibold transition-colors inline-flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Retake Assessment
            </a>
            <a href="{{ route('connecting.personality-type') }}" 
               class="bg-purple-500 hover:bg-purple-600 text-white px-6 py-2 rounded-lg font-semibold transition-colors inline-flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                </svg>
                Personality Type Assessment
            </a>
        </div>

        @if($latestDateCarbon)
            <p class="text-sm text-gray-600 mb-4">Assessment Date: {{ $latestDateCarbon->format('F d, Y') }}</p>
        @endif

        @if($results->isEmpty())
            <div class="text-center py-8">
                <p class="text-gray-500 mb-4">You haven't completed the Team Role Map assessment yet.</p>
                <a href="{{ route('connecting.team-role-map') }}" 
                   class="bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-lg font-semibold inline-block">
                    Take Assessment Now
                </a>
            </div>
        @else
            <!-- Top 5 Preferences -->
            @php
                $rankStyles = [
                    1 => ['border' => 'border-yellow-400', 'bg' => 'bg-yellow-50', 'badge' => 'bg-yellow-400 text-white', 'rank_color' => 'text-yellow-600', 'label' => '🥇'],
                    2 => ['border' => 'border-gray-400',   'bg' => 'bg-gray-50',   'badge' => 'bg-gray-400 text-white',   'rank_color' => 'text-gray-500',  'label' => '🥈'],
                    3 => ['border' => 'border-orange-400', 'bg' => 'bg-orange-50', 'badge' => 'bg-orange-400 text-white', 'rank_color' => 'text-orange-500','label' => '🥉'],
                    4 => ['border' => 'border-blue-300',   'bg' => 'bg-blue-50',   'badge' => 'bg-blue-300 text-white',   'rank_color' => 'text-blue-500',  'label' => '#4'],
                    5 => ['border' => 'border-purple-300', 'bg' => 'bg-purple-50', 'badge' => 'bg-purple-300 text-white', 'rank_color' => 'text-purple-500','label' => '#5'],
                ];
            @endphp
            <div class="mb-6">
                <h3 class="text-xl font-semibold mb-4">Your Top 5 Team Role Preferences</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($results->where('preference_rank', '<=', 5) as $result)
                        @php $style = $rankStyles[$result->preference_rank] ?? ['border' => 'border-gray-300', 'bg' => 'bg-gray-50', 'badge' => 'bg-gray-300 text-white', 'rank_color' => 'text-gray-600', 'label' => '#' . $result->preference_rank]; @endphp
                        <div class="p-4 border-2 rounded-lg {{ $style['border'] }} {{ $style['bg'] }}">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold {{ $style['badge'] }}">
                                            {{ $result->preference_rank <= 3 ? $result->preference_rank : '#' . $result->preference_rank }}
                                        </span>
                                        <span class="text-xs font-semibold {{ $style['rank_color'] }} uppercase tracking-wide">
                                            Rank {{ $result->preference_rank }}
                                        </span>
                                    </div>
                                    <h4 class="text-lg font-semibold">
                                        {{ $result->roleDescription->title ?? ucfirst(str_replace('_', ' ', $result->role_key ?? 'Unknown')) }}
                                    </h4>
                                    <p class="text-sm text-gray-600">
                                        {{ $result->roleDescription->value_focus ?? '' }}
                                    </p>
                                </div>
                                <span class="text-lg font-bold text-gray-700 ml-2">{{ $result->score }} pts</span>
                            </div>
                            @if($result->roleDescription)
                                <p class="text-sm text-gray-700 mt-2">
                                    {{ Str::limit($result->roleDescription->description ?? '', 120) }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- All Results Table -->
            <div class="mt-8">
                <h3 class="text-xl font-semibold mb-4">Complete Results</h3>
                <table class="w-full border-collapse border border-gray-200">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 px-4 py-2 text-left">Rank</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Role</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Value Focus</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($results as $result)
                            <tr class="border border-gray-300 hover:bg-gray-50">
                                <td class="border border-gray-300 px-4 py-2">
                                    <span class="font-bold">{{ $result->preference_rank }}</span>
                                </td>
                                <td class="border border-gray-300 px-4 py-2 font-semibold">
                                    {{ $result->roleDescription->title ?? ucfirst(str_replace('_', ' ', $result->role_key ?? 'Unknown')) }}
                                </td>
                                <td class="border border-gray-300 px-4 py-2">
                                    {{ $result->roleDescription->value_focus ?? '-' }}
                                </td>
                                <td class="border border-gray-300 px-4 py-2">
                                    {{ $result->score ?? 0 }} points
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Role Descriptions -->
            <div class="mt-8">
                <h3 class="text-xl font-semibold mb-4">Understanding Your Roles</h3>
                <div class="space-y-4">
                    @foreach($roleDescriptions as $role)
                        <div class="p-4 border border-gray-300 rounded-lg">
                            <h4 class="font-semibold text-lg">{{ $role->title }}</h4>
                            <p class="text-sm text-gray-600 mb-2"><strong>Value Focus:</strong> {{ $role->value_focus }}</p>
                            @if($role->description)
                                <p class="text-gray-700 mb-2">{{ $role->description }}</p>
                            @endif
                            @if($role->focus)
                                <p class="text-sm text-gray-600"><strong>Focus:</strong> {{ $role->focus }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-app-layout>

