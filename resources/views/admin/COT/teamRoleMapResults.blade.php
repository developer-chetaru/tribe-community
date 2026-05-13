<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Team Role Map Results
        </h2>
    </x-slot>

    <div class="bg-white p-6 rounded shadow">
        <div class="mb-4 flex gap-4">
            <a href="{{ route('admin.cot.questions.index') }}" 
               class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded flex items-center gap-2">
               <i class="fas fa-list"></i> Questions
            </a>
            <a href="{{ route('admin.cot.team-role-descriptions.index') }}" 
               class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded flex items-center gap-2">
               <i class="fas fa-book"></i> Descriptions
            </a>
            <a href="{{ route('admin.cot.team-role-results.export', request()->all()) }}" 
               class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded flex items-center gap-2">
               <i class="fas fa-download"></i> Export CSV
            </a>
        </div>

        <!-- Filters -->
        <form method="GET" action="{{ route('admin.cot.team-role-results.index') }}" class="mb-6 bg-gray-50 p-4 rounded">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
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
                    <label class="block text-sm font-medium text-gray-700 mb-2">Office</label>
                    <select name="office_id" class="w-full border border-gray-300 rounded px-4 py-2">
                        <option value="">All Offices</option>
                        @foreach($offices as $office)
                            <option value="{{ $office->id }}" {{ request('office_id') == $office->id ? 'selected' : '' }}>
                                {{ $office->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                    <select name="department_id" class="w-full border border-gray-300 rounded px-4 py-2">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>
                                {{ $dept->name }}
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
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="{{ route('admin.cot.team-role-results.index') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                    Clear
                </a>
            </div>
        </form>

        <!-- Results Table -->
        <div class="overflow-x-auto">
            <table class="w-full border-collapse border border-gray-200 text-sm">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 px-4 py-2 text-left font-semibold">User</th>
                        <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Organisation</th>
                        <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Top 5 Preferences</th>
                        <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Assessment Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($results as $userId => $userResults)
                        @php
                            $user = $userResults->first()->user;
                            $top5 = $userResults->where('preference_rank', '<=', 5)->sortBy('preference_rank');
                        @endphp
                        <tr class="border border-gray-300 hover:bg-red-50">
                            <td class="px-4 py-2">{{ $user->name ?? 'N/A' }}</td>
                            <td class="px-4 py-2">{{ $userResults->first()->organisation->name ?? 'N/A' }}</td>
                            <td class="px-4 py-2">
                                <div class="flex flex-wrap gap-2">
                                    @foreach($top5 as $result)
                                        <span class="px-2 py-1 rounded text-xs bg-blue-100 text-blue-800">
                                            {{ $result->preference_rank }}. {{ $result->roleDescription->title ?? $result->role_key }} ({{ $result->score }})
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 py-2">{{ $userResults->first()->assessment_date->format('Y-m-d') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-4 text-gray-500">No results found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>

