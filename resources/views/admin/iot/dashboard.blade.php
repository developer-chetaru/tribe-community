<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Offloading Dashboard - {{ $org->name }}
        </h2>
    </x-slot>

    <div class="flex-1 overflow-auto">
        <div class="max-w-8xl mx-auto p-4">
            <div class="mb-4">
                <a href="{{ route('organisations.view', ['id' => $org->id]) }}"
                   class="bg-[#fff] px-4 py-3 rounded-md shadow hover:bg-[#c71313] text-sm hover:text-white transition inline-flex items-center">
                    <svg width="7" height="11" viewBox="0 0 7 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
                        <path d="M6 10.5C6 10.5 1 6.81758 1 5.5C1 4.18233 6 0.5 6 0.5" stroke="#808080" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Back to Organisation
                </a>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Office:</label>
                <select id="officeFilter" class="bg-white text-sm border border-gray-300 rounded-md px-4 py-2 focus:ring-1 focus:ring-blue-500">
                    <option value="">All Offices</option>
                    @foreach($offices as $office)
                        <option value="{{ $office->id }}">{{ $office->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <a href="{{ route('admin.iot.feedback-list', ['orgId' => $org->id, 'status' => 'new', 'officeId' => '']) }}"
                   class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition cursor-pointer border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm font-medium">New</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2">{{ $newCount }}</p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                        </div>
                    </div>
                </a>

                <a href="{{ route('admin.iot.feedback-list', ['orgId' => $org->id, 'status' => 'on_hold', 'officeId' => '']) }}"
                   class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition cursor-pointer border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm font-medium">On Hold</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2">{{ $onHoldCount }}</p>
                        </div>
                        <div class="bg-yellow-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </a>

                <a href="{{ route('admin.iot.feedback-list', ['orgId' => $org->id, 'status' => 'completed', 'officeId' => '']) }}"
                   class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition cursor-pointer border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm font-medium">Completed</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2">{{ $completedCount }}</p>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                    </div>
                </a>

                <a href="{{ route('admin.iot.feedback-list', ['orgId' => $org->id, 'status' => 'awaiting', 'officeId' => '']) }}"
                   class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition cursor-pointer border-l-4 border-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm font-medium">Awaiting Response</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2">{{ $awaitingCount }}</p>
                        </div>
                        <div class="bg-red-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </a>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                <div class="flex flex-wrap gap-4">
                    <a href="{{ route('admin.theme-list', ['orgId' => $org->id]) }}"
                       class="bg-[#EB1C24] text-white px-5 py-2 rounded-md shadow text-sm font-medium inline-flex items-center hover:bg-[#c71313] transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                        </svg>
                        Manage Themes
                    </a>
                    <a href="{{ route('admin.add-theme', ['orgId' => $org->id]) }}"
                       class="bg-blue-600 text-white px-5 py-2 rounded-md shadow text-sm font-medium inline-flex items-center hover:bg-blue-700 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Add New Theme
                    </a>
                </div>
            </div>
        </div>
    </div>


    @push('scripts')
    <script>
        document.getElementById('officeFilter').addEventListener('change', function() {
            const officeId = this.value;
            const baseUrl = "{{ route('admin.iot.feedback-list', ['orgId' => $org->id, 'status' => 'new', 'officeId' => '']) }}";
            if (officeId) {
                window.location.href = baseUrl.replace('/new/', '/new/' + officeId);
            } else {
                window.location.href = baseUrl;
            }
        });

    </script>
    @endpush
</x-app-layout>

