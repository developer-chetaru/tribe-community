<x-slot name="header">
    <h2 class="text-2xl font-bold capitalize text-[#ff2323]">Send Notification to Basecamp Users</h2>
</x-slot>

<div>
@hasanyrole('super_admin')
@section('title','Send Notification to Basecamp Users')

<main class="p-6 flex-1 overflow-y-auto min-h-screen bg-gray-50">

    <!-- Top Navigation -->
    <div class="mb-6 flex justify-between items-center">
        <a href="{{ route('admin.send-notification-list') }}">
            <button class="flex items-center gap-2 bg-[#EB1C24] text-white px-6 py-3 min-w-[200px] rounded-md hover:bg-red-600">
                <img class="w-4 h-[14px]" src="{{ asset('images/notification-icon.svg') }}"> Notification List
            </button>   
        </a>
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

            <!-- Basecamp Users -->
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2">Basecamp Users</label>

                <div class="relative">
                    <input type="text" readonly
                           placeholder="Basecamp Users Included"
                           class="w-full border border-[#808080] rounded-md px-3 py-3 pl-[24px] bg-white"
                           value="{{ count($selectBasecampUsers) ? count($selectBasecampUsers).' Selected' : '' }}">

                    <button type="button"
                            wire:click="$dispatch('open-basecamp-users-modal')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 border items-center flex gap-2 text-white bg-[#808080] px-3 py-[10px] rounded-lg text-sm hover:bg-gray-500">
                        Expand
                        <img src="{{ asset('images/expand-ar.svg') }}">
                    </button>
                </div>
                @if(count($selectBasecampUsers) == 0)
                    <p class="text-xs text-red-500 mt-1">Please select at least one Basecamp user</p>
                @endif
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
                <button type="button" wire:click="resetForm" class="text-[#808080] hover:underline">Reset All</button>
            </div>

        </form>
    </div>
</main>


<!-- ================= BASECAMP USERS MODAL ================= -->
<div id="basecampUsersModal" wire:ignore.self class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/40"></div>

    <div class="fixed inset-0 flex items-start justify-center p-6">
        <div class="relative w-full max-w-4xl bg-white rounded-lg shadow-lg overflow-hidden">

            <div class="flex items-center justify-between p-6 border-b">
                <h3 class="text-xl font-semibold text-[#EB1C24]">Basecamp Users</h3>
                <button id="closeBasecampUsersModal" class="text-gray-600 hover:text-gray-900 text-2xl">&times;</button>
            </div>

            <div class="p-6">
                <div class="relative mb-4">
                    <input id="searchBasecampUsersInput" type="text" placeholder="Search by name or email" class="w-full border rounded-md px-4 py-3">

                    <button id="clearBasecampSearchBtn" class="absolute right-3 top-1/2 -translate-y-1/2 text-xl text-gray-400 hidden">
                        &times;
                    </button>
                </div>

                <div class="mb-3 flex items-center gap-3">
                    <label class="flex items-center gap-2">
                        <input id="selectAllBasecampUsers" type="checkbox" class="h-4 w-4">
                        <span>Select All</span>
                    </label>
                    <span class="ml-auto text-sm text-gray-500">
                        Showing <span id="visibleBasecampUsersCount">0</span> users
                    </span>
                </div>

                <div class="max-h-[60vh] overflow-y-auto wire:ignore">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="px-6 py-3"></th>
                                <th class="px-6 py-3 text-left">User</th>
                                <th class="px-6 py-3 text-left">Email</th>
                            </tr>
                        </thead>

                        <tbody id="basecampUsersModalTableBody" class="bg-white divide-y divide-gray-100">
                            @foreach($basecampUsers as $u)
                                <tr class="basecamp-user-row"
                                    data-id="{{ $u['id'] }}"
                                    data-name="{{ strtolower($u['first_name'] . ' ' . ($u['last_name'] ?? '')) }}"
                                    data-email="{{ strtolower($u['email']) }}">

                                    <!-- Checkbox -->
                                    <td class="px-6 py-4">
                                        <input type="checkbox"
                                            class="basecamp-user-check h-4 w-4"
                                            value="{{ $u['id'] }}"
                                            wire:click.stop="toggleBasecampUserSelection({{ $u['id'] }})"
                                            @checked(in_array($u['id'], $selectBasecampUsers))>
                                    </td>

                                    <!-- User -->
                                    <td class="px-6 py-4 flex items-center gap-3">
                                        @if($u['profile_photo_path'])
                                            <img src="{{ asset('storage/'.$u['profile_photo_path']) }}"
                                                class="w-10 h-10 rounded-full object-cover">
                                        @else
                                            <div class="w-10 h-10 rounded-full bg-gray-300 flex items-center justify-center">
                                                {{ strtoupper(substr($u['first_name'],0,1)) }}{{ strtoupper(substr($u['last_name'] ?? '',0,1)) }}
                                            </div>
                                        @endif

                                        <div>
                                            <div class="font-medium">{{ $u['first_name'] }} {{ $u['last_name'] ?? '' }}</div>
                                        </div>
                                    </td>

                                    <!-- Email -->
                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        {{ $u['email'] }}
                                    </td>

                                </tr>
                            @endforeach
                        </tbody>

                    </table>
                </div>

                <div class="flex justify-end gap-3 mt-5">
                    <button id="closeBasecampUsersModal2"
                            class="bg-[#EB1C24] text-white px-5 py-2 rounded-md">Done</button>
                </div>
            </div>

        </div>
    </div>
</div>

@endhasanyrole


@push('scripts')
<script>

// ---------- HELPERS ----------
function getBasecampUserRows() {
    return document.querySelectorAll('#basecampUsersModalTableBody .basecamp-user-row');
}

function updateVisibleBasecampUsers() {
    let visible = [...getBasecampUserRows()].filter(r => r.style.display !== 'none').length;
    let countEl = document.getElementById('visibleBasecampUsersCount');
    if (countEl) countEl.textContent = visible;
}

function resetBasecampSearch() {
    let input = document.getElementById('searchBasecampUsersInput');
    if (input) {
        input.value = "";
        getBasecampUserRows().forEach(r => r.style.display = '');
        updateVisibleBasecampUsers();
        document.getElementById('clearBasecampSearchBtn').classList.add('hidden');
    }
}

// ---------- OPEN BASECAMP USERS POPUP ----------
window.addEventListener('open-basecamp-users-modal', () => {
    document.getElementById('basecampUsersModal').classList.remove('hidden');
    resetBasecampSearch();
    updateVisibleBasecampUsers();
});

// ---------- CLOSE BASECAMP USERS POPUP ----------
function closeBasecampUsersPopup() {
    document.getElementById('basecampUsersModal').classList.add('hidden');
    resetBasecampSearch();
    let selectAll = document.getElementById('selectAllBasecampUsers');
    if (selectAll) selectAll.checked = false;
}

document.getElementById('closeBasecampUsersModal')?.addEventListener('click', closeBasecampUsersPopup);
document.getElementById('closeBasecampUsersModal2')?.addEventListener('click', closeBasecampUsersPopup);

// ---------- BASECAMP USERS SEARCH ----------
document.getElementById('searchBasecampUsersInput')?.addEventListener('input', function () {
    let q = this.value.toLowerCase();
    let clearBtn = document.getElementById('clearBasecampSearchBtn');

    if (q.length > 0) clearBtn.classList.remove('hidden');
    else clearBtn.classList.add('hidden');

    getBasecampUserRows().forEach(r => {
        let name = r.dataset.name;
        let email = r.dataset.email;
        r.style.display = (name.includes(q) || email.includes(q)) ? '' : 'none';
    });

    updateVisibleBasecampUsers();
});

// clear basecamp search
document.getElementById('clearBasecampSearchBtn')?.addEventListener('click', resetBasecampSearch);

// ---------- SELECT ALL BASECAMP USERS ----------
document.getElementById('selectAllBasecampUsers')?.addEventListener('change', function() {
    let visible = [...getBasecampUserRows()].filter(r => r.style.display !== 'none');
    let ids = visible.map(r => parseInt(r.dataset.id));

    if (this.checked) {
        visible.forEach(r => r.querySelector('.basecamp-user-check').checked = true);
        Livewire.dispatch('selectAllBasecampUsers', { ids: ids });
    } else {
        visible.forEach(r => r.querySelector('.basecamp-user-check').checked = false);
        Livewire.dispatch('deselectAllBasecampUsers', { ids: ids });
    }
});

// ---------- FLASH DISMISS ----------
setTimeout(() => {
    document.getElementById('flash-success')?.remove();
    document.getElementById('flash-error')?.remove();
}, 3000);

// ---------- LIVEWIRE: Re-sync DOM after refresh ----------
Livewire.hook('message.processed', () => {
    let selected = @json($selectBasecampUsers);

    document.querySelectorAll('.basecamp-user-check').forEach(cb => {
        cb.checked = selected.includes(parseInt(cb.value));
    });

    updateVisibleBasecampUsers();
});


</script>
@endpush
