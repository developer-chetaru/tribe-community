<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Personality Type Results Dashboard
        </h2>
    </x-slot>

    <div class="bg-white p-6 rounded shadow">
        <div class="mb-4 flex gap-4">
            <a href="{{ route('admin.personality-type.questions.index') }}" 
               class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded flex items-center gap-2">
               <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
               </svg>
               Questions
            </a>
            <a href="{{ route('admin.personality-type.values.index') }}" 
               class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded flex items-center gap-2">
               <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
               </svg>
               Dimensions
            </a>
            <a href="{{ route('admin.personality-type.results.index', array_merge(request()->all(), ['export' => 1])) }}" 
               class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded flex items-center gap-2">
               <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
               </svg>
               Export CSV
            </a>
        </div>

        <!-- Filters -->
        <form method="GET" action="{{ route('admin.personality-type.results.index') }}" class="mb-6 bg-gray-50 p-4 rounded">
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
                                {{ $user->first_name }} {{ $user->last_name }} ({{ $user->email }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                    Filter
                </button>
                <a href="{{ route('admin.personality-type.results.index') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                    Clear
                </a>
            </div>
        </form>

        @if($results->isEmpty())
            <div class="text-center py-8">
                <p class="text-gray-500">No results found matching your filters.</p>
            </div>
        @else
            <!-- Results Display -->
            <div class="space-y-6">
                @foreach($results as $userId => $userResults)
                    @php
                        $firstResult = $userResults->first();
                        $user = $firstResult->user ?? null;
                        $latestDate = $firstResult->assessment_date ?? null;
                    @endphp
                    @if($user)
                        <div class="border border-gray-300 rounded-lg p-6">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold">
                                        {{ $user->first_name ?? '' }} {{ $user->last_name ?? '' }}
                                    </h3>
                                    <p class="text-sm text-gray-600">{{ $user->email ?? 'N/A' }}</p>
                                    @if($latestDate)
                                        <p class="text-xs text-gray-500 mt-1">Assessment Date: {{ \Carbon\Carbon::parse($latestDate)->format('F d, Y') }}</p>
                                    @endif
                                </div>
                            </div>

                            <!-- Results Graph -->
                            <div class="space-y-3">
                                @foreach($userResults->sortByDesc('percentage') as $result)
                                    <div>
                                        <div class="flex justify-between items-center mb-1">
                                            <div class="flex items-center gap-2">
                                                <span class="font-semibold text-sm text-gray-700 min-w-[60px]">
                                                    {{ strtoupper($result->dimension_key ?? 'N/A') }}
                                                </span>
                                                <span class="text-sm text-gray-600">
                                                    {{ $result->personalityTypeValue->title ?? 'Unknown' }}
                                                </span>
                                            </div>
                                            <span class="text-lg font-bold text-red-600">{{ number_format($result->percentage ?? 0, 2) }}%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-6 overflow-hidden border border-gray-300">
                                            <div class="bg-red-500 h-6 rounded-full transition-all duration-300 flex items-center justify-end pr-2" 
                                                 style="width: {{ min($result->percentage ?? 0, 100) }}%">
                                                @if(($result->percentage ?? 0) > 15)
                                                    <span class="text-white text-xs font-semibold">{{ number_format($result->percentage ?? 0, 1) }}%</span>
                                                @endif
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">Score: {{ $result->score ?? 0 }} points</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>

