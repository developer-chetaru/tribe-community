<x-slot name="header">
    <h2 class="text-2xl font-bold capitalize text-[#ff2323]">Send Notification List</h2>
</x-slot>

<div>
@hasanyrole('super_admin')

<main class="p-3">
    <div class="min-h-screen">

        <!-- Back Button -->
        <a href="{{ route('admin.send-notification') }}">
            <button
                class="flex min-w-[84px] gap-3 items-center justify-center text-[#808080] mb-6 font-medium leading-[15px] border border-[#E5E5E5] rounded-lg px-3 py-[15px] bg-[#FFFFFF] hover:bg-gray-100">
                <img src="{{ asset('images/left-ar.svg') }}" alt="" class="h-3"> Back
            </button>
        </a>

        <div class="bg-white border border-[#E5E5E5] rounded-lg p-6">

            <div class="mb-4 flex items-center gap-2">
                <input
                    type="text"
                    wire:model="searchInput"
                    wire:keydown.enter="runSearch"
                    placeholder="Search by title, description, organisation, or office ..."
                    class="w-full md:w-1/3 px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-blue-200"
                />
                <button
                    wire:click="runSearch"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                >
                    Search
                </button>
                <button
                    wire:click="clearSearch"
                    class="px-4 py-2 bg-gray-100 rounded-md hover:bg-gray-200"
                >
                    Clear
                </button>
                <!-- <select wire:model="sortBy" class="px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
                    <option value="new">Newest First</option>
                    <option value="old">Oldest First</option>
                </select> -->
            </div>


            <!-- Flash Message -->
            @if (session('success'))
                <div class="mb-4 p-3 bg-green-100 text-green-800 rounded-md">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Table -->
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full border-collapse">
                    <thead>
                        <tr class="bg-[#F8F9FA] text-left text-gray-600 text-sm">
                            <th class="px-4 py-6">Organisation</th>
                            <th class="px-4 py-6">Office</th>
                            <th class="px-4 py-6">Department</th>
                            <th class="px-4 py-6">Title</th>
                            <th class="px-4 py-6 w-[23%]">Description</th>
                            <th class="px-4 py-6">Links</th>
                            <th class="px-4 py-6">Date Sent</th>
                            <th class="px-4 py-6">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="text-[#808080] text-sm">
                        @forelse ($notifications as $n)
                            <tr class="even:bg-[#F8F9FA]">

                                <td class="px-4 py-[14px]">{{ $n->organisation->name ?? '-' }}</td>
                                <td class="px-4 py-[14px]">{{ $n->office->name ?? '-' }}</td>
                                <td class="px-4 py-[14px]">{{ $n->allDepartment->name ?? '-' }}</td>
                                <td class="px-4 py-[14px]">{{ $n->title }}</td>

                                <td class="px-4 py-[14px]">
                                    {{ \Illuminate\Support\Str::limit($n->description, 80) }}
                                </td>

                                <td class="px-4 py-[14px] text-blue-600">
                                    @if($n->links)
                                        <button
                                            onclick="toggleLinkMenu(event, '{{ $n->links }}')"
                                            class="underline hover:text-blue-800">
                                            Options
                                        </button>
                                    @else
                                        -
                                    @endif
                                </td>

                                <td class="px-4 py-[14px]">
                                    {{ $n->created_at->format('d-m-Y, h:i A') }}
                                </td>

                                <!-- Actions Column -->
                                <td class="px-4 py-3">
                                    <!-- DELETE ONLY (No Edit) -->
                                    <button
                                        wire:click="confirmDelete({{ $n->id }})"
                                        class="hover:opacity-80">
                                        <img src="{{ asset('images/del-btn.svg') }}" alt="Delete" class="w-9 h-9">
                                    </button>
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-6 text-center text-gray-500">
                                    No notifications found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-6">
                <!-- FIGMA Pagination -->
                <div class="flex items-center justify-between mt-6">

                    <!-- Items per page -->
                    <select wire:model="perPage"
                        class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-[#808080] focus:outline-none"
                    >
                        <option value="8">8</option>
                        <option value="16">16</option>
                        <option value="24">24</option>
                    </select>

                    <!-- Pagination Buttons -->
                    <div class="flex items-center gap-2 select-none">
                        <button wire:click="gotoPage(1)" @disabled($notifications->onFirstPage())
                            class="w-[38px] h-[40px] border rounded-lg flex items-center justify-center
                            hover:bg-[#EB1C24] hover:text-white text-[#808080]"
                        >  «
                        </button>

                        <!-- Prev -->
                        <button wire:click="gotoPage({{ $notifications->currentPage() - 1 }})" @disabled($notifications->onFirstPage())
                            class="w-[38px] h-[40px] border rounded-lg flex items-center justify-center
                            hover:bg-[#EB1C24] hover:text-white text-[#808080]"
                        ><img src="{{ asset('images/right-arr.svg') }}" class="rotate-180">
                        </button>

                        <!-- Page Numbers -->
                        @for ($i = 1; $i <= $notifications->lastPage(); $i++)
                            <button wire:click="gotoPage({{ $i }})" class="min-w-[38px] px-[14px] py-[6px] border rounded-lg text-sm transition
                                {{ $notifications->currentPage() == $i
                                    ? 'bg-[#EB1C24] text-white border-[#EB1C24]'
                                    : 'bg-white text-[#808080] hover:bg-[#EB1C24] hover:text-white'
                                }}">
                                {{ $i }}
                            </button>
                        @endfor

                        <!-- Next -->
                        <button wire:click="gotoPage({{ $notifications->currentPage() + 1 }})" @disabled($notifications->currentPage() == $notifications->lastPage())
                            class="w-[38px] h-[40px] border rounded-lg flex items-center justify-center
                            hover:bg-[#EB1C24] hover:text-white text-[#808080]"
                        >
                            <img src="{{ asset('images/right-arr.svg') }}">
                        </button>

                        <!-- Last Page -->
                        <button wire:click="gotoPage({{ $notifications->lastPage() }})" @disabled($notifications->currentPage() == $notifications->lastPage())
                            class="w-[38px] h-[40px] border rounded-lg flex items-center justify-center
                            hover:bg-[#EB1C24] hover:text-white text-[#808080]"
                        > »
                        </button>

                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- DELETE CONFIRMATION -->
@if ($confirmingDelete)
    <div class="fixed inset-0 flex items-center justify-center bg-black/40 z-50">
        <div class="bg-white rounded-lg shadow-lg p-6 w-96">
            <h3 class="text-lg font-semibold mb-3">Delete Notification</h3>
            <p class="text-gray-600 mb-4">Are you sure you want to delete this notification?</p>

            <div class="flex justify-end gap-2">
                <button wire:click="closeDeleteModal"
                    class="px-4 py-2 bg-gray-200 rounded-md hover:bg-gray-300">
                    Cancel
                </button>

                <button wire:click="deleteNotification"
                    class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                    Delete
                </button>
            </div>

        </div>
    </div>
@endif


@push('scripts')
<script>
    let activeMenu = null;

    function toggleLinkMenu(event, link) {
        event.stopPropagation();
        if (activeMenu) activeMenu.remove();

        const rect = event.target.getBoundingClientRect();
        const menu = document.createElement('div');
        menu.className = "fixed bg-white border border-gray-200 rounded-md shadow-lg z-[9999] w-36";
        menu.style.top = rect.bottom + "px";
        menu.style.left = rect.left + "px";

        menu.innerHTML = `
            <button onclick="copyToClipboard('${link}')"
                class="w-full text-left px-3 py-2 text-sm hover:bg-blue-50">
                📋 Copy Link
            </button>
            <a href="${link}" target="_blank"
                class="block px-3 py-2 text-sm hover:bg-blue-50">
                🔗 Open Link
            </a>
        `;
        document.body.appendChild(menu);
        activeMenu = menu;
    }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text);
        if (activeMenu) {
            activeMenu.remove();
            activeMenu = null;
        }
    }

    document.addEventListener('click', () => {
        if (activeMenu) {
            activeMenu.remove();
            activeMenu = null;
        }
    });
</script>
@endpush

@else
<!-- Unauthorized View -->
<div class="flex items-center justify-center h-[80vh]">
    <div class="text-center">
        <h2 class="text-2xl font-semibold text-gray-800">You are not authorized</h2>
        <p class="text-gray-500 mt-2">You do not have permission to view this page.</p>
    </div>
</div>
@endhasanyrole
</div>
