<x-slot name="header">
   <h2 class="text-[24px] md:text-[30px]  font-semibold capitalize text-[#EB1C24]">
       {{ $this->organisationName }}
    </h2>
</x-slot>
<div class="flex-1 overflow-auto">
    <div class="max-w-8xl mx-auto p-4">
        <div class="flex justify-between items-center mb-4 flex-wrap lg:flex-nowrap">
           <a href="{{ route('organisations.view', ['id' => $organisationId]) }}" 
               class="bg-[#fff] px-4 py-3 rounded-md  hover:bg-[#c71313] text-sm hover:text-white transition inline-flex items-center">
           <svg width="7" height="11" viewBox="0 0 7 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2 stroke-gray-500 group-hover:stroke-white">
        <path d="M6 10.5C6 10.5 1 6.81758 1 5.5C1 4.18233 6 0.5 6 0.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    Back to {{ $organisationName }}
</a>

    <div class="flex items-center justify-end">
        <a href="{{ route('staff-add', ['id' => $organisationId]) }}" class="bg-[#EB1C24] text-white px-5 py-2 rounded-md shadow font-medium flex items-center">
            <svg width="20" height="21" viewBox="0 0 20 21" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
                <path d="M10.002 4.6665V16.3348" stroke="white" stroke-width="1.17" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M15.8344 10.5015H4.16602" stroke="white" stroke-width="1.17" stroke-linecap="round" stroke-linejoin="round"/>
            </svg> Add Staff
        </a>
            @if($staffList->count() > 0)
            <button type="button" wire:click="exportStaff" class="bg-[#F8F9FA] ml-3 border text-black px-5 py-2 rounded-md shadow font-medium flex items-center"><svg class="mr-2" width="21" height="21" viewBox="0 0 21 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10.6686 4.19352L10.6686 12.5269M10.6686 4.19352C11.2521 4.19352 12.3423 5.85544 12.752 6.27686M10.6686 4.19352C10.0851 4.19352 8.99487 5.85544 8.58529 6.27686" stroke="#141B34" stroke-width="1.17" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M17.3353 12.9434C17.3353 15.0117 16.9038 16.6934 14.8355 16.6934H6.50215C4.43381 16.6934 4.00195 15.0117 4.00195 12.9434" stroke="#141B34" stroke-width="1.17" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Export Details 
            </button>
            @endif
        </div>          
    </div>
    <div class="flex bg-[#ffffff] p-4 border border-gray-200 mt-7 rounded-md flex-wrap">
    <div class="flex justify-between items-center mb-4 w-full flex-wrap lg:flex-nowrap">
        <h2 class="text-[#EB1C24] font-medium text-[24px]">Staff</h2>
        <div class="flex items-center justify-between ">
            <div class="w-full min-w-[300px]  mr-3 ">
                <div class="relative">
                    <button class="absolute top-3 left-4 flex items-center" type="button">
                       <svg width="20" height="21" class="mr-2" viewBox="0 0 20 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9.16667 16.3333C12.8486 16.3333 15.8333 13.3486 15.8333 9.66667C15.8333 5.98477 12.8486 3 9.16667 3C5.48477 3 2.5 5.98477 2.5 9.66667C2.5 13.3486 5.48477 16.3333 9.16667 16.3333Z" stroke="#808080" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M17.5 18L13.875 14.375" stroke="#808080" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button> 
                    <input wire:model.live="search"
                        class="w-full bg-[#F8F9FA] focus:ring-red-500 focus:border-red-500 rounded-[8px] border border-[#E5E5E5]  pl-3 pr-5 py-3 text-[14px] font-[500] hover:border-slate-300  pl-10"
                        placeholder="Search staff..." 
                    />
                </div> 
            </div>
            <button wire:click="$toggle('showFilter')" type="button" class="px-5 py-2.5 flex items-center rounded-[8px] border border-[#E5E5E5]  font-medium hover:bg-[#c71313] hover:text-white transition bg-[#F8F9FA] min-w-[150px] flex items-center justify-center btn-hover">
                <svg width="21" height="21" viewBox="0 0 21 21" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
                    <path d="M5.5 10.4995H15.5" stroke="#020202" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M7.5 15.4995H14.5" stroke="#020202" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M3.5 5.49951H17.5" stroke="#020202" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg> Filter
           @php
                $filterCount = count($filterOffice ?? []) + count($filterDepartment ?? []);
            @endphp

        @if($filterCount > 0)
            <span class="ml-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                {{ $filterCount }}
            </span>
        @endif
           </button>
        </div>
    </div>
    <div x-data="{ activeStaffId: null }" class="grid grid-cols-1 lg:grid-cols-2 flex-wrap gap-4 w-full items-start">
        @forelse($staffList as $staff)
            <div class="w-full  flex flex-col bg-[#F8F9FA] p-4 rounded-md self-start h-[100%]">
                <!-- Top row: staff info -->
                <div class="flex items-start">
                <!-- Profile image -->
                <div class="staff-img rounded-full w-[40px] h-[40px] overflow-hidden mr-2.5">
            @if($staff->profile_photo_path)
        <img src="{{ asset('storage/' . $staff->profile_photo_path) }}" 
             alt="{{ $staff->first_name }}" 
            class="w-full h-full object-cover rounded-full">
    @else
        <div class="w-full h-full flex items-center justify-center bg-gray-300 text-white font-bold text-xl rounded-full">
            {{ strtoupper(substr($staff->first_name, 0, 1)) }}
        </div>
    @endif
    </div>
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
    $userRole = $staff->roles->first()?->name;
    @endphp

    <span class="text-[14px] text-[#020202] font-[400]">
        Role:
        <span class="text-[#EB1C24] pl-2 font-[300]">
            {{ $roleMapping[$userRole] ?? $userRole ?? '-' }}
        </span>
    </span>
            <button 
                        @click="activeStaffId = activeStaffId === {{ $staff->id }} ? null : {{ $staff->id }}" 
                        class="font-regular underline text-[14px] text-[#808080] ml-3 font-[300]"
                    >
                        <span x-text="activeStaffId === {{ $staff->id }} ? 'View Less' : 'View More'"></span>
                    </button>
            <!-- Expandable section -->
            <div 
                x-show="activeStaffId === {{ $staff->id }}" 
                x-collapse 
                class="flex flex-col space-y-1"
            >
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
                <span class="text-[#808080] pl-3 font-[300]">
                  {{ $staff->country_code ? $staff->country_code . ' ' . $staff->phone : $staff->phone }}
                </span>
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
        <span class="text-[#808080] pl-3 font-[300]">
            {{ $staff->first_login_at }}
        </span>
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

                <!-- Action buttons -->
                <div class="staff-edit-btn flex flex-wrap gap-1 w-[110px] justify-end ml-2">
                @if($staff->office && $userRole === 'organisation_user')        
                <x-tooltip tooltipText="Make User As A Lead">
                    <button 
                        wire:click="openTeamLeadModal({{ $staff->id }})"
                        class="flex justify-center items-center py-1 px-2 bg-[#ffeaec] border rounded-md border-[#FF9AA0]">
                        <img src="{{ asset('images/user-star.svg') }}" alt="star">
                    </button>
                </x-tooltip>
                @endif

                @if($staff->office && ($userRole === 'organisation_user' || $userRole === 'organisation_admin'))        
                <x-tooltip tooltipText="Make User As Director">
                    <button 
                        wire:click="openDirectorModal({{ $staff->id }})"
                        class="flex justify-center items-center py-1 px-2 bg-[#ffeaec] border rounded-md border-[#FF9AA0]">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                        </svg>
                    </button>
                </x-tooltip>
                @endif

                    
                    <x-tooltip tooltipText="Edit User">
                        <a href="{{ route('staff-update', ['id' => $staff->id]) }}"
                            class="flex justify-center items-center py-1 px-2 bg-[#ffeaec] border rounded-md border-[#FF9AA0]">
                            <img src="{{ asset('images/edit-pencil.svg') }}" alt="edit" class="h-5 w-5">
                        </a>
                 </x-tooltip>
               <div x-data="{ showConfirm: false, deleteId: null }">

    {{-- Delete Confirmation Modal --}}
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
                {{-- Delete button --}}
            <button @click="$wire.call('delete', deleteId); showConfirm = false" 
                        class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                    Delete
                </button>
            </div>
        </div>
    </div>

    {{-- Delete Button --}}
    <x-tooltip tooltipText="Delete User">
        <button @click="showConfirm = true; deleteId = {{ $staff->id }}"  
                class="flex justify-center items-center py-1 px-2 bg-[#ffeaec] border rounded-md border-[#FF9AA0]">
            <img src="{{ asset('images/delete-office.svg') }}" alt="delete" class="h-5 w-5">
        </button>              
    </x-tooltip>
</div>

    <div  
    x-data="{ open: false, confirmModal: false }"     x-cloak
    x-on:close-leave-modal.window="open = false"
    class="bg-[#F8F8F8]"
>
            @if($staff->onLeave)
            
        <x-tooltip tooltipText="Disable leave"> 
                <button 
                    @click="confirmModal = true; $wire.set('selectedStaffId', {{ $staff->id }})"
                    class="flex justify-center items-center py-1 px-2 bg-red-500 border rounded-md border-[#FF9AA0]">
                    <img src="{{ asset('images/active-leave.svg') }}" alt="leave" class="h-5 w-5">
                </button>
        </x-tooltip>
            @else
           
        <x-tooltip tooltipText="Add Leave">
                <button 
                    @click="open = true; $wire.set('selectedStaffId', {{ $staff->id }})"
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

                <!-- Start Date -->
                <div class="mb-4">
                    <label class="block text-gray-700 mb-1">From:</label>
                    <input type="date"
                           wire:model.defer="leaveStartDate"
                           min="{{ \Carbon\Carbon::today()->toDateString() }}"
                           class="w-full p-2 border border-gray-300 rounded">
                    @error('leaveStartDate') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- End Date -->
                <div class="mb-4">
                    <label class="block text-gray-700 mb-1">To:</label>
                    <input type="date"
                           wire:model.defer="leaveEndDate"
                           min="{{ $leaveStartDate ? \Carbon\Carbon::parse($leaveStartDate)->toDateString() : today()->toDateString() }}"
                           class="w-full p-2 border border-gray-300 rounded">
                    @error('leaveEndDate') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Submit -->
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
        <p class="text-center w-full">No staff found</p>
    @endforelse
</div>

<div class="mt-4 flex justify-center w-full">
  
  {{ $staffList->links('components.pagination') }}

</div>
@if($showFilter)
<div class="fixed inset-0 flex items-center justify-end bg-black bg-opacity-50 z-50">
    <div class="bg-[#F8F9FA] rounded-[8px] max-w-[660px] w-full max-h-[90vh] overflow-y-auto  mr-8 p-5 ">
        
        <!-- Header -->
        <div class="flex justify-between items-center mb-3">
            <h2 class=" font-[500] text-[20px] text-[#020202]"> Filter</h2>
            <button wire:click="resetFilters" class="hover:underline text-[#808080] text-[16px] font-[400]">Reset All</button>
        </div>
        <div class="flex flex-col md:flex-row bg-white border border-[#D3D3D3]">
            
            <!-- Left Tabs -->
<div class="md:w-1/3 ">
    <ul class="">
              <li class="border-b border-[#D3D3D3]">
                        <button wire:click="$set('activeTab','office')"
                            class="w-full text-left px-4 py-3.5 transition text-[16px] {{ $activeTab==='office' ? 'text-[16px] text-[#EB1C24] font-[400] bg-[#FFEFF0]' : 'hover:bg-gray-100' }}">
                            Office
                        </button>
                    </li>
                    <li class="border-b border-[#D3D3D3]">
                        <button wire:click="$set('activeTab','department')"
                            class="w-full text-left px-4 py-3.5 transition text-[16px] {{ $activeTab==='department' ? 'text-[16px] text-[#EB1C24] font-[400] bg-[#FFEFF0]' : 'hover:bg-gray-100' }}">
                            Department
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Right Options -->
            <div class="md:w-2/3 px-6 py-2 border-l  border-[#D3D3D3]">
                @if($activeTab==='office')
                    @foreach($offices as $office)
                        <label class="block mb-3 text-[#808080] text-[16px] md:text-[16px] font-[400]">
                            <input type="checkbox"
                                   class="form-checkbox accent-red-500 mr-2 h-5 w-5 text-red-600 focus:ring-red-500 focus:border-red-500 border-[#808080] border rounded-[4px]"
                                   wire:model="filterOffice"
                                   value="{{ $office->id }}">
                           {{ ucfirst($office->name) }}

                        </label>
                    @endforeach
                @endif

            @if($activeTab==='department')
                @php
                    // Group departments by all_department_id to show unique department names
                    $uniqueDepartments = $departments->filter(function($item) {
                        return $item->allDepartment !== null;
                    })->groupBy(function($item) {
                        return $item->allDepartment->id;
                    })->map(function($group) {
                        // Return the first department from each group
                        return $group->first();
                    });
                @endphp
                @foreach($uniqueDepartments as $department)
                    @php
                        // Get all department IDs with the same all_department_id
                        $allDepartmentIds = \App\Models\Department::where('all_department_id', $department->allDepartment->id)
                            ->pluck('id')
                            ->toArray();
                    @endphp
                    <label class="block mb-3 text-[#808080] text-[16px] md:text-[16px] font-[400]">
                        <input type="checkbox" 
                                class="form-checkbox accent-red-500 mr-2 h-5 w-5 text-red-600 focus:ring-red-500 focus:border-red-500 border-[#808080] border rounded-[4px]"
                                wire:model="filterDepartment"
                                value="{{ $department->allDepartment->id }}">
                        {{ ucfirst($department->allDepartment->name ?? 'N/A') }}
                    </label>
                @endforeach
            @endif
            </div>
        </div>

        <!-- Footer -->
        <div class="flex justify-end space-x-2 py-3 mt-4">
            <button wire:click="closeFilter" class="px-6 py-3 bg-[#F8F9FA] rounded-[8px] border-[#E5E5E5] text-[#020202] text-[16px] font-[500] border  hover:bg-gray-300">Close</button>
            <button wire:click="saveFilters" class="px-6 py-3 bg-[#EB1C24] rounded-[8px] border-[#EB1C24] text-[#fff] text-[16px] font-[500] border  hover:bg-gray-300">Apply</button>
        </div>
    </div>
</div>
@endif
@if($showTeamLeadModal && $selectedStaff)
<div class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
    <div class="bg-white rounded-lg shadow-lg p-6 w-[400px]">
        <h2 class="text-lg font-bold mb-4">Confirm Team Lead</h2>

        <p>
            Are you sure you want to assign 
            <span class="font-semibold text-red-600">{{ $selectedStaff->first_name }} {{ $selectedStaff->last_name }}</span> 
            as the Team Lead for 
            <span class="font-semibold text-red-600">{{ $selectedStaff->office->name ?? 'No Office Assigned' }} </span>?
        </p>

        <div class="mt-6 flex justify-end space-x-2">
            <button wire:click="closeTeamLeadModal" 
                class="px-4 py-2 border rounded-md">Cancel</button>
            
            <button wire:click="makeTeamLead" 
                class="px-4 py-2 bg-red-500 text-white rounded-md">Confirm</button>
        </div>
    </div>
</div>
@endif
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
      

    </div>
</div>
