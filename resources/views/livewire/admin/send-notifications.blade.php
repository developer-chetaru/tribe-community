<x-slot name="header">
    <h2 class="text-2xl font-bold capitalize text-[#ff2323]">Send Notification</h2>
</x-slot>

<div>
@hasanyrole('super_admin')
@section('title','Send Notification')

<main class="p-6 flex-1 overflow-y-auto min-h-screen bg-gray-50">

    <!-- Top Navigation -->
    <div class="mb-6 flex justify-between items-center">
        <a href="{{ route('admin.send-notification-list') }}">
            <button class="flex items-center gap-2 bg-[#EB1C24] text-white px-6 py-3 min-w-[200px] rounded-md hover:bg-red-600">
                <img class="w-4 h-[14px]" src="{{ asset('images/notification-icon.svg') }}"> Notification List
            </button>   
        </a>

        <!-- <button type="button" id="openFilterBtn"
            class="flex items-center gap-2 border border-[#E5E5E5] px-4 py-[11px] min-w-[150px] bg-[#F8F9FA] rounded-md hover:bg-gray-100">
            <img src="{{ asset('images/filter-icon.svg') }}"> Filter
        </button>  -->
    </div>

    <!-- Flash Messages -->
    <div class="text-center mb-6" id="flash-messages">
        @if(session('message'))
            <div id="flash-success" class="bg-green-100 text-green-700 border border-green-300 rounded-lg p-3">
                {{ session('message') }}
            </div>
        @endif

        @if(session('error'))
            <div id="flash-error" class="bg-red-100 text-red-700 border border-red-300 rounded-lg p-3">
                {{ session('error') }}
            </div>
        @endif
    </div>

    <!-- FORM -->
    <div class="bg-white shadow-sm rounded-lg p-6 pb-16 border border-[#E5E5E5]">
        <form wire:submit.prevent="sendNotification" class="max-w-[1000px]">

            <!-- Org -->
            <div class="mb-5">
                <label class="block text-sm font-medium mb-2">Organisation</label>
                <select wire:model="orgId"
                        wire:change="loadOffices($event.target.value)"
                        class="w-full border text-[#808080] border-[#808080] rounded-md px-3 py-3 pl-[24px]">
                    <option value="">Select Organisation</option>
                    @foreach($organisations as $org)
                        <option value="{{ $org['id'] }}">{{ $org['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Office & Department -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">

                <div>
                    <label class="block text-sm font-medium mb-2">Office</label>
                    <select wire:model="officeId"
                            wire:change="loadDepartments"
                            class="w-full border text-[#808080] border-[#808080] rounded-md px-3 py-3 pl-[24px]">
                        <option value="">Select Office</option>
                        @foreach($offices as $office)
                            <option value="{{ $office['id'] }}">{{ $office['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Department</label>
                    <select wire:model="departmentId"
                            wire:change="loadUsersByDepartment($event.target.value)"
                            class="w-full border text-[#808080] border-[#808080] rounded-md px-3 py-3 pl-[24px]">
                        <option value="">Select Department</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept['all_department_id'] }}">{{ $dept['department'] }}</option>
                        @endforeach
                    </select>
                </div>

            </div>

            <!-- USERS -->
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2">All Users</label>

                <div class="relative">
                    <input type="text" readonly
                           placeholder="Users Included"
                           class="w-full border border-[#808080] rounded-md px-3 py-3 pl-[24px] bg-white"
                           value="{{ count($selectStaff) ? count($selectStaff).' Selected' : '' }}">

                    <button type="button"
                            wire:click="$dispatch('open-users-modal')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 border items-center flex gap-2 text-white bg-[#808080] px-3 py-[10px] rounded-lg text-sm hover:bg-gray-500">
                        Expand
                        <img src="{{ asset('images/expand-ar.svg') }}">
                    </button>
                </div>
            </div>

            <!-- CONTENT -->
            <div class="bg-red-50 border border-red-100 rounded-md p-5 mb-6">
                <h3 class="font-semibold text-gray-700 mb-3">Notification Content</h3>

                <label class="block text-sm font-medium mb-2">Title</label>
                <input wire:model.defer="title" type="text"
                       class="w-full border border-[#808080] rounded-md px-3 py-3 pl-[24px] mb-4"/>
                @error('title') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror

                <label class="block text-sm font-medium mb-2">Message</label>
                <textarea wire:model.defer="description"
                          class="w-full border border-[#808080] rounded-md px-3 py-3 pl-[24px] h-[100px]"></textarea>
                @error('description') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror

                <label class="block text-sm font-medium mb-2 mt-4">Links (Optional)</label>
                <input wire:model.defer="links" type="text"
                       class="w-full border border-[#808080] rounded-md px-3 py-3 pl-[24px]"/>
            </div>

            <!-- Buttons -->
            <div class="flex items-center gap-6">
                <button type="submit" class="bg-red-500 text-white px-8 py-3 rounded-md hover:bg-red-600">Submit</button>
                <button type="button" wire:click="clearFilters" class="text-[#808080] hover:underline">Reset All</button>
            </div>

        </form>
    </div>
</main>


<!-- ================= USERS MODAL (Works 100%) ================= -->
<div id="usersModal" wire:ignore.self class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/40"></div>

    <div class="fixed inset-0 flex items-start justify-center p-6">
        <div class="relative w-full max-w-4xl bg-white rounded-lg shadow-lg overflow-hidden">

            <div class="flex items-center justify-between p-6 border-b">
                <h3 class="text-xl font-semibold text-[#EB1C24]">Users Included</h3>
                <button id="closeUsersModal" class="text-gray-600 hover:text-gray-900 text-2xl">&times;</button>
            </div>

            <div class="p-6">
                <div class="relative mb-4">
                    <input id="searchUsersInput" type="text" placeholder="Search by name or email" class="w-full border rounded-md px-4 py-3">

                    <button id="clearSearchBtn" class="absolute right-3 top-1/2 -translate-y-1/2 text-xl text-gray-400 hidden">
                        &times;
                    </button>
                </div>

                <div class="mb-3 flex items-center gap-3">
                    <label class="flex items-center gap-2">
                        <input id="selectAllUsers" type="checkbox" class="h-4 w-4">
                        <span>Select All</span>
                    </label>
                    <span class="ml-auto text-sm text-gray-500">
                        Showing <span id="visibleUsersCount">0</span> users
                    </span>
                </div>

                <div class="max-h-[60vh] overflow-y-auto wire:ignore">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="px-6 py-3"></th>
                                <th class="px-6 py-3 text-left">Staff</th>
                                <th class="px-6 py-3 text-left">Department</th>
                                <th class="px-6 py-3 text-left">Office</th>
                            </tr>
                        </thead>

                        <tbody id="usersModalTableBody" class="bg-white divide-y divide-gray-100">
                            @foreach($staffOptions as $u)
                                <tr class="user-row"
                                    data-id="{{ $u['id'] }}"
                                    data-name="{{ strtolower($u['first_name']) }}"
                                    data-email="{{ strtolower($u['email']) }}">

                                    <!-- Checkbox -->
                                    <td class="px-6 py-4">
                                        <input type="checkbox"
                                            class="user-check h-4 w-4"
                                            value="{{ $u['id'] }}"
                                            wire:click.stop="toggleUserSelection({{ $u['id'] }})"
                                            @checked(in_array($u['id'], $selectStaff))>
                                    </td>

                                    <!-- Staff -->
                                    <td class="px-6 py-4 flex items-center gap-3">
                                        @if($u['profile_photo_path'])
                                            <img src="{{ asset('storage/'.$u['profile_photo_path']) }}"
                                                class="w-10 h-10 rounded-full object-cover">
                                        @else
                                            <div class="w-10 h-10 rounded-full bg-gray-300 flex items-center justify-center">
                                                {{ strtoupper(substr($u['first_name'],0,1)) }}
                                            </div>
                                        @endif

                                        <div>
                                            <div class="font-medium">{{ $u['first_name'] }}</div>
                                            <div class="text-xs text-gray-400">{{ $u['email'] }}</div>
                                        </div>
                                    </td>

                                    <!-- Department -->
                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        {{ $u['department'] }}
                                    </td>

                                    <!-- Office -->
                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        {{ $u['office'] }}
                                    </td>

                                </tr>
                            @endforeach
                        </tbody>

                    </table>
                </div>

                <div class="flex justify-end gap-3 mt-5">
                    <button id="closeUsersModal2"
                            class="bg-[#EB1C24] text-white px-5 py-2 rounded-md">Done</button>
                </div>
            </div>

        </div>
    </div>
</div>


<!-- ================= FILTER MODAL ================= -->
<div id="filterModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/40"></div>

    <div class="fixed inset-0 flex items-center justify-center p-6">
        <div class="relative w-full max-w-xl bg-white rounded-lg shadow-lg overflow-hidden">

            <div class="flex items-center justify-between p-6 border-b">
                <h3 class="text-lg font-semibold">Filter Users</h3>
                <button id="closeFilterModal" class="text-gray-600 text-2xl">&times;</button>
            </div>

            <div class="p-6 space-y-4">
                <div>
                    <label class="text-sm font-medium">Organisation</label>
                    <select wire:model="orgId" class="w-full border rounded-md px-3 py-2">
                        <option value="">All Organisations</option>
                        @foreach($organisations as $org)
                            <option value="{{ $org['id'] }}">{{ $org['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium">Office</label>
                    <select wire:model="officeId" class="w-full border rounded-md px-3 py-2">
                        <option value="">All Offices</option>
                        @foreach($offices as $office)
                            <option value="{{ $office['id'] }}">{{ $office['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium">Department</label>
                    <select wire:model="departmentId" class="w-full border rounded-md px-3 py-2">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept['all_department_id'] }}">{{ $dept['department'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex justify-end gap-3">
                    <button wire:click="applyFilters"
                            class="bg-[#EB1C24] text-white px-5 py-2 rounded-md">Apply</button>
                </div>

            </div>

        </div>
    </div>
</div>

@endhasanyrole


@push('scripts')
<script>

// ---------- HELPERS ----------
function getUserRows() {
    return document.querySelectorAll('#usersModalTableBody .user-row');
}

function updateVisibleUsers() {
    let visible = [...getUserRows()].filter(r => r.style.display !== 'none').length;
    document.getElementById('visibleUsersCount').textContent = visible;
}

function resetSearch() {
    let input = document.getElementById('searchUsersInput');
    input.value = "";
    getUserRows().forEach(r => r.style.display = '');
    updateVisibleUsers();
    document.getElementById('clearSearchBtn').classList.add('hidden');
}


// ---------- OPEN USERS POPUP ----------
window.addEventListener('open-users-modal', () => {
    document.getElementById('usersModal').classList.remove('hidden');
    resetSearch();
    updateVisibleUsers();
});


// ---------- CLOSE USERS POPUP ----------
function closeUsersPopup() {
    document.getElementById('usersModal').classList.add('hidden');
    resetSearch();
    document.getElementById('selectAllUsers').checked = false;
}

document.getElementById('closeUsersModal')?.addEventListener('click', closeUsersPopup);
document.getElementById('closeUsersModal2')?.addEventListener('click', closeUsersPopup);


// ---------- SEARCH ----------
document.getElementById('searchUsersInput')?.addEventListener('input', function () {
    let q = this.value.toLowerCase();
    let clearBtn = document.getElementById('clearSearchBtn');

    if (q.length > 0) clearBtn.classList.remove('hidden');
    else clearBtn.classList.add('hidden');

    getUserRows().forEach(r => {
        let name = r.dataset.name;
        let email = r.dataset.email;
        r.style.display = (name.includes(q) || email.includes(q)) ? '' : 'none';
    });

    updateVisibleUsers();
});

// clear search
document.getElementById('clearSearchBtn')?.addEventListener('click', resetSearch);


// ---------- SELECT USER ----------
// document.getElementById('usersModalTableBody')?.addEventListener('change', e => {
//     if (e.target.classList.contains('user-check')) {
//         let id = e.target.dataset.id;
//         Livewire.call('toggleUserSelection', id);
//     }
// });


// ---------- SELECT ALL ----------
document.getElementById('selectAllUsers')?.addEventListener('change', function() {
    let visible = [...getUserRows()].filter(r => r.style.display !== 'none');
    let ids = visible.map(r => parseInt(r.dataset.id));

    if (this.checked) {
        visible.forEach(r => r.querySelector('.user-check').checked = true);
        Livewire.dispatch('selectAllUsers', { ids: ids });
    } else {
        visible.forEach(r => r.querySelector('.user-check').checked = false);
        Livewire.dispatch('deselectAllUsers', { ids: ids });
    }
});


// ---------- FILTER MODAL ----------
document.getElementById('openFilterBtn')?.addEventListener('click', () =>
    document.getElementById('filterModal').classList.remove('hidden')
);

document.getElementById('closeFilterModal')?.addEventListener('click', () =>
    document.getElementById('filterModal').classList.add('hidden')
);

window.addEventListener('closeFilterModal', () =>
    document.getElementById('filterModal').classList.add('hidden')
);


// ---------- FLASH DISMISS ----------
setTimeout(() => {
    document.getElementById('flash-success')?.remove();
    document.getElementById('flash-error')?.remove();
}, 3000);


// ---------- LIVEWIRE: Re-sync DOM after refresh ----------
Livewire.hook('message.processed', () => {
    let selected = @json($selectStaff);

    document.querySelectorAll('.user-check').forEach(cb => {
        cb.checked = selected.includes(parseInt(cb.value));
    });

    updateVisibleUsers();
});


</script>
@endpush

