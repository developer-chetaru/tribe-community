<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Tribeometer Results Dashboard
        </h2>
    </x-slot>

    <div class="bg-white p-6 rounded shadow">
        <div class="mb-4 flex gap-4">
            <a href="{{ route('admin.tribeometer.index') }}" 
               class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded flex items-center gap-2">
               <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
               </svg>
               Questions
            </a>
            <a href="{{ route('admin.tribeometer.value.list') }}" 
               class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded flex items-center gap-2">
               <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
               </svg>
               Values
            </a>
        </div>

        <!-- Filters -->
        <form method="GET" action="{{ route('admin.tribeometer.results.index') }}" class="mb-6 bg-gray-50 p-4 rounded">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Organisation</label>
                    <select name="org_id" class="w-full border border-gray-300 rounded px-4 py-2">
                        <option value="">All Organisations</option>
                        @foreach($organisations as $org)
                            <option value="{{ $org->id }}" {{ request('org_id') == $org->id ? 'selected' : '' }}>
                                {{ $org->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">User</label>
                    <select name="user_id" class="w-full border border-gray-300 rounded px-4 py-2">
                        <option value="">All Users</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                {{ $user->first_name ?? '' }} {{ $user->last_name ?? '' }} ({{ $user->email ?? 'N/A' }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                    Filter
                </button>
                <a href="{{ route('admin.tribeometer.results.index') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                    Clear
                </a>
            </div>
        </form>

        @if(empty($results))
            <div class="text-center py-8">
                <p class="text-gray-500">No results found matching your filters.</p>
            </div>
        @else
            <!-- Results Display -->
            <div class="space-y-6">
                @foreach($results as $resultData)
                    @php
                        $user = $resultData['user'];
                        $status = $resultData['status'];
                        $valueScores = $resultData['valueScores'];
                    @endphp
                    <div class="border border-gray-300 rounded-lg p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-lg font-semibold">
                                    {{ $user->first_name ?? '' }} {{ $user->last_name ?? '' }}
                                </h3>
                                <p class="text-sm text-gray-600">{{ $user->email ?? 'N/A' }}</p>
                                @if($status->organisation)
                                    <p class="text-sm text-gray-600">Organisation: {{ $status->organisation->name }}</p>
                                @else
                                    <p class="text-sm text-gray-600">Organisation: Basecamp User</p>
                                @endif
                                @if($status->date)
                                    <p class="text-xs text-gray-500 mt-1">Assessment Date: {{ $status->date->format('F d, Y') }}</p>
                                @endif
                            </div>
                        </div>

                        <!-- Value Scores -->
                        <div class="space-y-4">
                            <h4 class="font-semibold text-gray-700 mb-3">Results by Value:</h4>
                            @foreach($valueScores as $valueTitle => $scoreData)
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <div class="flex justify-between items-center mb-2">
                                        <h5 class="font-medium text-gray-800">{{ $valueTitle }}</h5>
                                        <span class="text-xl font-bold 
                                            {{ $scoreData['score'] >= 75 ? 'text-green-600' : 
                                               ($scoreData['score'] >= 50 ? 'text-blue-600' : 
                                               ($scoreData['score'] >= 25 ? 'text-yellow-600' : 'text-red-600')) }}">
                                            {{ $scoreData['score'] }}%
                                        </span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-3 mb-2">
                                        <div class="bg-red-500 h-3 rounded-full transition-all duration-500" 
                                             style="width: {{ $scoreData['score'] }}%"></div>
                                    </div>
                                    <div class="flex justify-between text-sm text-gray-600">
                                        <span>Average Score: {{ $scoreData['average_score'] }}/3.0</span>
                                        <span>{{ $scoreData['total_responses'] }} responses</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>

