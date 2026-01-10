<x-slot name="header">
    <h2 class="text-[16px] sm:text-[24px] font-semibold capitalize text-[#EB1C24]">
        My Teammates
    </h2>
</x-slot>

<div class="flex bg-white p-4 border border-gray-200 mt-7 rounded-md flex-wrap">

    {{-- Header: Search + Filter --}}
    <div class="flex justify-between items-center mb-4 w-full flex-wrap sm:flex-nowrap">
        <h2 class="text-[#EB1C24] font-medium text-[14px] sm:text-[24px]">My Teammates</h2>
        
        <div class="flex items-center space-x-3 flex-wrap sm:flex-nowrap">
            {{-- Search Box --}}
            <div class="relative w-full sm:min-w-[300px]">
                <input wire:model.live="search"
                    class="w-full bg-[#F8F9FA] rounded-[8px] border border-[#E5E5E5] pl-10 pr-5 py-3 text-[14px] font-[500] hover:border-slate-300"
                    placeholder="Search My Teammate..." 
                />
                <div class="absolute top-3 left-4 flex items-center">
                    {{-- Search Icon --}}
                    <svg width="20" height="21" class="mr-2" viewBox="0 0 20 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9.16667 16.3333C12.8486 16.3333 15.8333 13.3486 15.8333 9.66667C15.8333 5.98477 12.8486 3 9.16667 3C5.48477 3 2.5 5.98477 2.5 9.66667C2.5 13.3486 5.48477 16.3333 9.16667 16.3333Z" stroke="#808080" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M17.5 18L13.875 14.375" stroke="#808080" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>

            {{-- Filter Button --}}
            <button wire:click="$toggle('showFilter')" type="button"
                class="px-5 py-2.5 flex items-center rounded-[8px] border border-[#E5E5E5] font-medium hover:bg-[#c71313] hover:text-white transition bg-[#F8F9FA] min-w-[150px] w-full sm:w-[auto] mt-2 sm:mt-0  btn-hover justify-center ml-0 sm:ml-2">
                <svg width="21" height="21" class="mr-2" viewBox="0 0 21 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M5.5 10.4995H15.5" stroke="#020202" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M7.5 15.4995H14.5" stroke="#020202" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M3.5 5.49951H17.5" stroke="#020202" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Filter
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

    {{-- Staff Grid --}}
    <div x-data="{ activeStaffId: null }" class="grid grid-cols-1 md:grid-cols-2 gap-4 w-full items-start">
        @forelse($staffList as $staff)
            <div class="w-full flex flex-col bg-[#F8F9FA] p-4 rounded-md">
                
                {{-- Staff Top Row --}}
                <div class="flex items-start">
                    {{-- Profile Image --}}
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

                    {{-- Staff Info --}}
                    <div class="flex-1">
                        <h4 class="text-[14px] sm:text-[16px] text-[#020202] font-semibold capitalize">
                            {{ $staff->first_name }} {{ $staff->last_name }}
                        </h4>
                        <p class="text-[13px] sm:text-[14px] text-[#808080]">{{ $staff->email }}</p>

                        @php
                            $roleMapping = [
                                'organisation_user' => 'Staff',
                                'organisation_admin' => 'Team Lead',
                                'director' => 'Director',
                            ];
                            $userRole = $staff->roles->first()?->name;
                            $displayRole = $roleMapping[$userRole] ?? ($userRole ? ucfirst($userRole) : '-');
                        @endphp
                        <span class="text-[13px] sm:text-[14px] text-[#020202] font-[400]">
                            Role:
                            <span class="text-[#EB1C24] pl-2 font-[300]">
                                {{ $displayRole }}
                            </span>
                        </span>

                        {{-- View More Button --}}
                        <button @click="activeStaffId = activeStaffId === {{ $staff->id }} ? null : {{ $staff->id }}" 
                                class="font-regular underline text-[14px] text-[#808080] ml-3 font-[300]">
                            <span x-text="activeStaffId === {{ $staff->id }} ? 'View Less' : 'View More'"></span>
                        </button>

                        {{-- Expandable Section --}}
                        <div x-show="activeStaffId === {{ $staff->id }}" x-collapse class="flex flex-col space-y-1 mt-2">
                            @if(!empty($staff->office?->name))
                                <span class="text-[14px] text-[#020202] font-[400]">
                                    Office: <span class="text-[#808080] pl-3 font-[300] capitalize">{{ $staff->office->name }}</span>
                                </span>
                            @endif

                            @if(!empty(optional($staff->department?->allDepartment)->name))
                                <span class="text-[14px] text-[#020202] font-[400]">
                                    Department: <span class="text-[#808080] pl-3 font-[300]">{{ optional($staff->department?->allDepartment)->name }}</span>
                                </span>
                            @endif
                          </div>

                    </div>
                </div>

            </div>
        @empty
            <p class="text-center w-full">We couldn’t find any teammates matching your search. Please check the name and try again.</p>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-4 flex justify-center w-full">
        {{ $staffList->links('components.pagination') }}
    </div>
@if($showFilter)
<div class="fixed inset-0 flex items-center justify-end bg-black bg-opacity-50 z-50">
    <div class="bg-[#F8F9FA] rounded-[8px] max-w-[660px] w-full max-h-[90vh] overflow-y-auto  mr-8 p-5 ">
        
        <!-- Header -->
        <div class="flex justify-between items-center mb-3">
            <h2 class=" font-[500] text-[14px] sm:text-[20px] text-[#020202]"> Filter</h2>
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
                        <label class="block mb-3 text-[#808080] text-[14px] md:text-[16px] font-[400]">
                            <input type="checkbox"
                                   class="form-checkbox accent-red-500 mr-2 h-5 w-5 text-red-600 focus:ring-red-500 focus:border-red-500 border-[#808080] border rounded-[4px]"
                                   wire:model="filterOffice"
                                   value="{{ $office->id }}">
                           {{ ucfirst($office->name) }}

                        </label>
                    @endforeach
                @endif

          @if($activeTab==='department')
    @foreach($departments as $department)
        @if($department && $department->allDepartment)
            <label class="block mb-3 text-[#808080] text-[14px] md:text-[16px] font-[400]">
                <input type="checkbox" 
                       class="form-checkbox accent-red-500 mr-2 h-5 w-5 text-red-600 focus:ring-red-500 focus:border-red-500 border-[#808080] border rounded-[4px]"
                       wire:model="filterDepartment"
                       value="{{ $department->id }}">
                {{ ucfirst($department->allDepartment->name) }}
            </label>
        @endif
    @endforeach
@endif

 			</div>
        </div>

        <!-- Footer -->
        <div class="flex justify-end space-x-2 py-3 mt-4">
            <button wire:click="closeFilter" class="px-6 py-3 bg-[#F8F9FA] rounded-[8px] border-[#E5E5E5] text-[#020202] text-[14px] sm:text-[16px] font-[500] border  hover:bg-gray-300">Close</button>
            <button wire:click="saveFilters" class="px-6 py-3 bg-[#EB1C24] rounded-[8px] border-[#EB1C24] text-[#fff] text-[14px] sm:text-[16px] font-[500] border  hover:bg-gray-300">Apply</button>
        </div>
    </div>
</div>
@endif

</div>
