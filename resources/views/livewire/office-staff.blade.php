<div>
    <x-slot name="header">
       <h2 class="text-[24px] md:text-[30px] font-semibold capitalize text-[#EB1C24]">
           {{ $organisation->name }}
       </h2>
    </x-slot>

    <div class="flex-1 overflow-auto">
        <div class="max-w-8xl mx-auto p-4">
            <!-- Back + Add Staff -->
            <div class="flex justify-between items-center mb-4 flex-wrap lg:flex-nowrap">
                <a href="{{ route('office-list', ['id' => $organisation->id]) }}" class="bg-white px-4 py-3 rounded-md hover:bg-[#c71313] text-sm hover:text-white transition inline-flex items-center">
                    <svg width="7" height="11" viewBox="0 0 7 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2 stroke-gray-500 group-hover:stroke-white">
                        <path d="M6 10.5C6 10.5 1 6.81758 1 5.5C1 4.18233 6 0.5 6 0.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg> Back to Office
                </a>
            </div>

            <!-- White Card Container -->
            <div class="bg-white p-6 rounded-md shadow border">
                <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6">
                    <div>
                        <h2 class="text-[#EB1C24] font-medium text-[22px]">Staff</h2>
                    </div>

                    <div class="w-full md:w-auto flex items-center justify-end space-x-4">

                        <a href="{{ route('staff-add', ['id' => $organisation]) }}" 
                        class="bg-[#EB1C24] hover:bg-red-700 text-white px-5 py-2.5 rounded-xl shadow-lg font-bold 
                                text-sm whitespace-nowrap transition duration-200 ease-in-out
                                flex items-center focus:outline-none focus:ring-2 focus:ring-[#EB1C24] focus:ring-offset-2">
                            
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
                                <path d="M10 4.1665V15.8332" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M15.8334 10H4.16675" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg> 
                            Add Staff
                        </a>

                        <div class="relative w-full max-w-xs md:max-w-sm">
                            <input 
                                wire:model.live="search"
                                class="w-full bg-white focus:ring-red-500 focus:border-red-500 rounded-xl 
                                    border border-gray-300 pl-10 pr-4 py-2.5 text-sm placeholder-gray-500
                                    transition duration-150 shadow-sm"
                                placeholder="Search staff..." />
                            
                            <svg width="20" height="20" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M9.16667 15.8333C12.8486 15.8333 15.8333 12.8486 15.8333 9.16667C15.8333 5.48477 12.8486 2.5 9.16667 2.5C5.48477 2.5 2.5 5.48477 2.5 9.16667C2.5 12.8486 5.48477 15.8333 9.16667 15.8333Z" stroke="#9CA3AF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M17.5 17.5L13.875 13.875" stroke="#9CA3AF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                    </div>

                </div>

                <!-- Staff Cards -->
                <div x-data="{ activeStaffId: null }" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @forelse($staffList as $staff)
                        @php
                            $userRole = $staff->roles->first()?->name;
                        @endphp
                        <div class="bg-[#F8F9FA] p-4 rounded-md shadow flex flex-col">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start">
                                    <!-- Profile -->
                                    <div class="rounded-full w-[45px] h-[45px] overflow-hidden mr-3">
                                        @if($staff->profile_photo_path)
                                            <img src="{{ asset('storage/' . $staff->profile_photo_path) }}" alt="{{ $staff->first_name }}" class="w-full h-full object-cover rounded-full">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center bg-gray-300 text-white font-bold text-xl rounded-full">
                                                {{ strtoupper(substr($staff->first_name, 0, 1)) }}
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Info -->
                                    <div class="flex-1">
                                        <h4 class="text-[16px] text-[#020202] font-semibold capitalize">
                                            {{ $staff->first_name }} {{ $staff->last_name }}
                                        </h4>
                                        <p class="text-[14px] text-[#808080]">{{ $staff->email }}</p>

                                        @php
                                            $roleMapping = [
                                                'organisation_user' => 'Staff',
                                                'organisation_admin' => 'Team Lead',
                                                'director' => 'Director',
                                            ];
                                            $displayRole = $roleMapping[$userRole] ?? ($userRole ? ucfirst($userRole) : '-');
                                        @endphp

                                        <span class="text-[14px] text-[#020202]">
                                            Role:
                                            <span class="text-[#EB1C24] pl-2">
                                                {{ $displayRole }}
                                            </span>
                                        </span>

                                        <!-- View/Hide button -->
                                        <button @click="activeStaffId = activeStaffId === {{ $staff->id }} ? null : {{ $staff->id }}" 
                                            class="font-regular underline text-[14px] text-[#808080] ml-3 font-[300]">
                                            <span x-text="activeStaffId === {{ $staff->id }} ? 'View Less' : 'View More'"></span>
                                        </button>

                                        <!-- Expandable section -->
                                        <div x-show="activeStaffId === {{ $staff->id }}" x-collapse class="flex flex-col space-y-1 mt-3">
                                            @if(!empty($staff->office?->name))
                                                <span class="text-[14px] text-[#020202] font-[400]">
                                                    Office:
                                                    <span class="text-[#808080] pl-3 font-[300] capitalize">{{ $staff->office->name }}</span>
                                                </span>
                                            @endif

                                            @if(!empty(optional($staff->department?->allDepartment)->name))
                                                <span class="text-[14px] text-[#020202] font-[400]">
                                                    Department:
                                                    <span class="text-[#808080] pl-3 font-[300]">{{ optional($staff->department?->allDepartment)->name }}</span>
                                                </span>
                                            @endif

                                            @if(!empty($staff->phone))
                                                <span class="text-[14px] text-[#020202] font-[400]">
                                                    Mobile Number:
                                                    <span class="text-[#808080] pl-3 font-[300]">{{ $staff->phone }}</span>
                                                </span>
                                            @endif

                                            @if(!empty($staff->EIScore))
                                                <span class="text-[14px] text-[#020202] font-[400]">
                                                    Today's EI Score:
                                                    <span class="text-[#0C7B24] pl-3 font-[300]">{{ $staff->EIScore }}</span>
                                                </span>
                                            @endif

                                            @if(!empty($staff->current_month_ei_score))
                                                <span class="text-[14px] text-[#020202] font-[400]">
                                                    Current Month's EI Score:
                                                    <span class="text-[#0C7B24] pl-3 font-[300]">{{ $staff->current_month_ei_score }}</span>
                                                </span>
                                            @endif

                                            @if(!empty($staff->first_login_at))
                                                <span class="text-[14px] text-[#020202] font-[400]">
                                                    First Login:
                                                    <span class="text-[#808080] pl-3 font-[300]">{{ $staff->first_login_at }}</span>
                                                </span>
                                            @endif

                                            @if(!empty($staff->last_login_at))
                                                <span class="text-[14px] text-[#020202] font-[400]">
                                                    Last Interaction Date:
                                                    <span class="text-[#808080] pl-3 font-[300]">{{ $staff->last_login_at }}</span>
                                                </span>
                                            @endif

                                            @if($staff->time_spent_on_app_formatted && $staff->time_spent_on_app_formatted !== '-')
                                                <span class="text-[14px] text-[#020202] font-[400]">
                                                    Time Spent:
                                                    <span class="text-[#808080] pl-3 font-[300]">{{ $staff->time_spent_on_app_formatted }}</span>
                                                </span>
                                            @endif
                                        </div>
                                        
                                    </div>
                                </div>

                                <!-- Staff Edit Buttons -->
                                <div class="staff-edit-btn flex flex-wrap gap-1 w-[110px] justify-end ml-2">
                                    @if($staff->office && $userRole === 'organisation_user')        
                                        <x-tooltip tooltipText="Make User As A Lead">
                                            <button wire:click="openTeamLeadModal({{ $staff->id }})"
                                                class="flex justify-center items-center py-1 px-2 bg-[#ffeaec] border rounded-md border-[#FF9AA0]">
                                                <img src="{{ asset('images/user-star.svg') }}" alt="star">
                                            </button>
                                        </x-tooltip>
                                    @endif

                                    @if($staff->office && ($userRole === 'organisation_user' || $userRole === 'organisation_admin'))        
                                        <x-tooltip tooltipText="Make User As Director">
                                            <button wire:click="openDirectorModal({{ $staff->id }})"
                                                class="flex justify-center items-center py-1 px-2 bg-[#ffeaec] border rounded-md border-[#FF9AA0]">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                                                </svg>
                                            </button>
                                        </x-tooltip>
                                    @endif

                                    <x-tooltip tooltipText="Send Report">   
                                        <button class="flex justify-center items-center py-1 px-2 bg-[#ffeaec] border rounded-md border-[#FF9AA0]">
                                            <img src="{{ asset('images/sent.svg') }}" alt="send">
                                        </button>
                                    </x-tooltip>

                                    <x-tooltip tooltipText="Edit User">
                                        <a href="{{ route('staff-update', ['id' => $staff->id]) }}"
                                            class="flex justify-center items-center py-1 px-2 bg-[#ffeaec] border rounded-md border-[#FF9AA0]">
                                            <img src="{{ asset('images/edit-pencil.svg') }}" alt="edit" class="h-5 w-5">
                                        </a>
                                    </x-tooltip>

                                    <div x-data="{ showConfirm: false, deleteId: null }">
                                        <!-- Delete Button -->
                                        <x-tooltip tooltipText="Delete User">
                                            <button @click="showConfirm = true; deleteId = {{ $staff->id }}"  
                                                class="flex justify-center items-center py-1 px-2 bg-[#ffeaec] border rounded-md border-[#FF9AA0]">
                                                <img src="{{ asset('images/delete-office.svg') }}" alt="delete" class="h-5 w-5">
                                            </button>              
                                        </x-tooltip>

                                        <!-- Delete Confirmation Modal -->
                                        <div x-show="showConfirm" x-cloak
                                            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                                            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                                                <h2 class="text-lg font-semibold text-red-600 mb-4">Confirm Deletion</h2>
                                                <p class="text-gray-700 mb-6">Are you sure you want to delete this staff member?</p>
                                                <div class="flex justify-end gap-3">
                                                    <button @click="showConfirm = false" 
                                                            class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">
                                                        Cancel
                                                    </button>
                                                    <button @click="$wire.call('delete', deleteId); showConfirm = false" 
                                                            class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                                                        Delete
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div x-data="{ open: false, confirmModal: false }" x-cloak
                                        x-on:close-leave-modal.window="open = false"
                                        class="bg-[#F8F8F8]">
                                        @if($staff->onLeave)
                                            <x-tooltip tooltipText="Disable leave"> 
                                                <button @click="confirmModal = true; $wire.set('selectedStaffId', {{ $staff->id }})"
                                                    class="flex justify-center items-center py-1 px-2 bg-red-500 border rounded-md border-[#FF9AA0]">
                                                    <img src="{{ asset('images/active-leave.svg') }}" alt="leave" class="h-5 w-5">
                                                </button>
                                            </x-tooltip>
                                        @else
                                            <x-tooltip tooltipText="Add Leave">
                                                <button @click="open = true; $wire.set('selectedStaffId', {{ $staff->id }})"
                                                    class="flex justify-center items-center py-1 px-2 bg-[#ffeaec] border rounded-md border-[#FF9AA0]">
                                                    <img src="{{ asset('images/leave.svg') }}" alt="leave" class="h-5 w-5">
                                                </button>
                                            </x-tooltip>
                                        @endif

                                        <!-- Apply Leave Modal -->
                                        <div x-show="open" x-transition.opacity 
                                            class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
                                            <div class="bg-white rounded-lg p-6 w-96 relative">
                                                <button @click="open = false" class="absolute top-2 right-2 text-gray-500">&times;</button>

                                                @if($selectedStaff)
                                                    <h2 class="text-lg font-bold mb-2 text-center">
                                                        Apply Leave for <span class="text-red-500 capitalize">{{ $selectedStaff->first_name }} {{ $selectedStaff->last_name }}</span>
                                                    </h2>
                                                    <p class="text-sm text-gray-600 mb-4 text-center capitalize">
                                                        Office: {{ $selectedStaff->office->name ?? 'N/A' }} <br> 
                                                        Department: {{ $selectedStaff->department->allDepartment->name ?? 'N/A' }}
                                                    </p>
                                                    <div class="mb-4">
                                                        <label class="block text-gray-700 mb-1">From:</label>
                                                        <input type="date"
                                                            wire:model.defer="leaveStartDate"
                                                            min="{{ \Carbon\Carbon::today()->toDateString() }}"
                                                            class="w-full p-2 border border-gray-300 rounded">
                                                        @error('leaveStartDate') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                                                    </div>
                                                    <div class="mb-4">
                                                        <label class="block text-gray-700 mb-1">To:</label>
                                                        <input type="date"
                                                            wire:model.defer="leaveEndDate"
                                                            min="{{ $leaveStartDate ? \Carbon\Carbon::parse($leaveStartDate)->toDateString() : today()->toDateString() }}"
                                                            class="w-full p-2 border border-gray-300 rounded">
                                                        @error('leaveEndDate') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                                                    </div>
                                                    <button wire:click="applyLeave" class="w-full bg-red-500 text-white py-2 rounded">
                                                        Submit
                                                    </button>
                                                @endif
                                            </div>
                                        </div>

                                        <!-- Resume Work Modal -->
                                        <div x-show="confirmModal" x-transition.opacity x-cloak
                                            class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
                                            <div class="bg-white rounded-lg p-6 w-80 relative">
                                                <button @click="confirmModal = false"
                                                        class="absolute top-2 right-2 text-gray-500">&times;</button>
                                                <p class="text-center mb-4">Ready to resume work?</p>
                                                <button wire:click="changeLeaveStatus({{ $selectedStaffId }})"
                                                        @click="confirmModal = false"
                                                        class="w-full bg-red-500 text-white py-2 rounded">
                                                    DISABLE
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-center col-span-full">No staff found for this office.</p>
                    @endforelse
                </div>

                <!-- Pagination -->
                <div class="mt-6 flex justify-center">
                    {{ $staffList->links('components.pagination') }}
                </div>

            </div>
        </div>

        @if($showDirectorModal && $selectedStaff)
        <div class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
            <div class="bg-white rounded-lg shadow-lg p-6 w-[400px]">
                <h2 class="text-lg font-bold mb-4">Confirm Director</h2>

                <p>
                    Are you sure you want to assign 
                    <span class="font-semibold text-red-600">{{ $selectedStaff->first_name }} {{ $selectedStaff->last_name }}</span> 
                    as the Director?
                </p>

                <div class="mt-6 flex justify-end space-x-2">
                    <button wire:click="closeDirectorModal" 
                        class="px-4 py-2 border rounded-md">Cancel</button>
                    
                    <button wire:click="makeDirector" 
                        class="px-4 py-2 bg-red-500 text-white rounded-md">Confirm</button>
                </div>
            </div>
        </div>
        @endif

        @if($showTeamLeadModal)
            <div class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
                <div class="bg-white rounded-lg shadow-lg p-6 w-[400px]">
                    <h2 class="text-lg font-bold mb-4">Confirm Team Lead</h2>
                    <p>
                        Are you sure you want to assign 
                        <span class="font-semibold text-red-600">
                            {{ $selectedStaff->first_name }} {{ $selectedStaff->last_name }}
                        </span> 
                        as the <strong>Team Lead</strong> for the office 
                        <span class="font-semibold text-red-600">
                            {{ $selectedStaff->office->name ?? 'No Office Assigned' }}
                        </span>?
                    </p>

                    <div class="mt-6 flex justify-end space-x-2">
                        <button wire:click="closeTeamLeadModal" 
                            class="px-4 py-2 border rounded-md">Cancel</button>
                        
                        <button wire:click="makeTeamLead" 
                            class="px-4 py-2 bg-red-500 text-white rounded-md">Confirm </button>
                    </div>
                </div>
            </div>
        @endif
      
      	@if(session()->has('teamLeadMessage'))
            <div id="simplePopup"
                class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
                <div class="bg-white rounded-lg p-6 shadow-lg max-w-sm w-full text-center relative">
                    <h2 class="text-lg font-bold mb-2 text-green-600">Success!</h2>
                    <p class="text-gray-600">{!! session('teamLeadMessage') !!}</p>

                    <div class="mt-4">
                        <button type="button"
                                onclick="document.getElementById('simplePopup')?.remove()"
                                class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                            Close
                        </button>
                    </div>
                </div>
            </div>
      	@endif

      	@if(session()->has('directorMessage'))
            <div id="directorPopup"
                class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
                <div class="bg-white rounded-lg p-6 shadow-lg max-w-sm w-full text-center relative">
                    <h2 class="text-lg font-bold mb-2 text-green-600">Success!</h2>
                    <p class="text-gray-600">{!! session('directorMessage') !!}</p>

                    <div class="mt-4">
                        <button type="button"
                                onclick="document.getElementById('directorPopup')?.remove()"
                                class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                            Close
                        </button>
                    </div>
                </div>
            </div>
      	@endif

            @push('scripts')
                <script>
                    document.addEventListener('show-team-lead-message', function () {
                        let popup = document.getElementById('simplePopup');
                        if (popup) {
                            setTimeout(() => {
                                popup.remove();
                            }, 2000);
                        }
                    });
                </script>
            @endpush
        @endif

    </div>
</div>

