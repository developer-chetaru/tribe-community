    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold  text-[#EB1C24]">
           {{ $organisation->name }}
        </h2>
    </x-slot>
    <div class="flex h-screen">
        <div class="flex-1 overflow-auto">
            <div class="">
                <div class="flex justify-between items-center mb-4">
                    
                <a href="{{ route('organisations.index')}}" 
   class="bg-[#fff] px-5 py-3 rounded-md shadow hover:bg-[#c71313] text-sm hover:text-white transition inline-flex items-center back-btn">
    <svg width="7" height="11" viewBox="0 0 7 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
            <path d="M6 10.5C6 10.5 1 6.81758 1 5.5C1 4.18233 6 0.5 6 0.5" stroke="#808080" stroke-linecap="round" stroke-linejoin="round"/>
            </svg> Back to Organisations
</a>

                </div>
                    <div class="grid grid-cols-1 sm:grid-cols-1 lg:grid-cols-2 gap-8">

                    <div  
   class="block">
    <div class="bg-white rounded-xl p-4 flex relative cursor-pointer border border-gray-200" title="Edit Organisation">  
        <div class="flex flex-wrap justify-start w-[100px] flex-col items-center">
          
          <div class="w-[100px] h-[100px] rounded-md bg-gray-100 flex items-center justify-center mb-3 overflow-hidden p-1">
                <img src="{{ $organisation->image ? asset('storage/' . $organisation->image) : 'https://via.placeholder.com/150' }}"
                     alt="{{ $organisation->name }}"
                     class="">
           
            
             <div class="absolute top-4 right-3 inline-flex gap-2" x-data="{ showOrgDeleteConfirm: false }">
                                {{-- Edit --}}
                                <x-tooltip tooltipText="Edit Organisation">
                                    <a href="{{ route('update-organisation', ['id' => $organisation->id]) }}"  
                                       class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 rounded-md text-[12px] flex items-center gap-1">
                                        <i class="fas fa-pencil-alt text-gray-600 text-[12px]"></i>
                                        Edit
                                    </a>
                                </x-tooltip>

                                {{-- Delete --}}
                                <x-tooltip tooltipText="Delete Organisation">
                                    <button @click="showOrgDeleteConfirm = true"  
                                            class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 rounded-md text-[12px] flex items-center gap-1">
                                        <i class="fas fa-trash text-gray-600 text-[12px]"></i>
                                        Delete
                                    </button>
                                </x-tooltip>

                                {{-- Delete Modal --}}
                                <div x-show="showOrgDeleteConfirm" x-cloak
                                     class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                                    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                                        <h2 class="text-lg font-semibold text-red-600 mb-4">Confirm Deletion</h2>
                                        <p class="text-gray-700 mb-6">
                                           An organisation cannot be deleted until all its offices and staff members are deleted.
                                        </p>


                                        <div class="flex justify-end gap-3">
                                            <button @click="showOrgDeleteConfirm = false" 
                                                    class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">
                                                Cancel
                                            </button>
                                          @if ($organisation->users->count() === 0 
                && ($organisation->offices->count() === 0 
                    || ($organisation->offices->count() === 11)))
                <button @click="$wire.call('deleteOrganisation', {{ $organisation->id }}); showOrgDeleteConfirm = false" 
                        class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                    Delete
                </button>
            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                     

            </div>
          
            <div class="bg-[#ffdede] text-[12px] rounded-lg flex items-center px-2 py-1 text-[#EB1C24] inline-flex" style="line-height: normal;">
                <!-- <span class="h-[8px] w-[8px] flex bg-[#EB1C24] rounded-full mr-1"></span> -->
                @if($organisation->profile_visibility == '1')
                    Public
                @elseif($organisation->profile_visibility == '0')
                    Private
                @else
                    {{ $organisation->profile_visibility }}
                @endif
            </div>
        </div>


        <div class="w-[calc(100%-120px)] text-center mb-1 pl-3">
            <h3 class="text-[24px] w-full capitalize font-semibold text-[#EB1C24] mb-1 text-left truncate">{{ $organisation->name }}</h3>
            <span class="text-[12px] md:text-[16px] text-gray-600 flex">Total Offices: <span class="ml-2"> {{ $officeCount }}</span></span>
            <span class="text-[12px] md:text-[16px] text-gray-600 flex">Total Staff:  <span class="ml-2"> {{ $staffCount }}</span></span>
            <span class="text-[12px] md:text-[16px] text-gray-600 flex">Total Department:  <span class="ml-2"> {{ $officeCount }}</span></span>
            <span class="text-[12px] md:text-[16px] text-gray-600 flex">Industry:  <span class="ml-2"> {{ $organisation->indus?->name ?? 'N/A' }}</span></span>
            <span class="text-[12px] md:text-[16px] text-gray-600 flex">Working Days:  
     @php
    // Define the correct week order
    $weekOrder = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    // Get working days from database
    $workingDays = is_array($organisation->working_days) 
                   ? $organisation->working_days 
                   : explode(',', $organisation->working_days); 

    // Sort working days according to $weekOrder
    usort($workingDays, function($a, $b) use ($weekOrder) {
        return array_search($a, $weekOrder) - array_search($b, $weekOrder);
    });
@endphp

<span class="ml-2">{{ implode(', ', $workingDays) }}</span>
       </span>
        </div>
    </div>
</div>

                        <div class="flex bg-[#ffffff] p-4 rounded-xl  border border-gray-200 flex-wrap items-start flex-col ">
                            <h3 class="border-b border-gray-200 mb-3 font-[500] w-full pb-3 text-[24px] text-[#020202] fonn-[500] "> Manage Company </h3>

                            <div class="">
                    <a  href="{{ route('office-list', $organisation->id) }}" 
   class="bg-[#EB1C24] px-5 py-2 text-[16] rounded-md  hover:bg-[#c71313] transition text-white inline-block mr-4">
   Office
</a>
                              

<a  href="{{ route('staff-list', $organisation->id) }}" 
   class="bg-[#EB1C24] px-5 py-2 text-[16px] rounded-md  hover:bg-[#c71313] transition text-white inline-block mr-4">
   Staff
</a>           
<!--  <button type="button" class="bg-[#FFEFF0] text-[#020202]  px-5 py-2 rounded-md border border-[#FF9AA0]  hover:text-white  hover:bg-[#c71313] text-[16px] fonn-[500] transition "> View Reports </button> -->
                            </div>
                        </div>


                    </div>
<!-- 
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 bg-[#ffffff] p-4 border border-gray-200 mt-7 rounded-md">
                        <div class="flex items-center justify-between bg-[#f6f8fa] border border-gray-100 rounded-md p-5 h-30">
                            <span class="text-[20px] flex justify-center w-full text-gray-800 ">Directing</span>
                        </div>

                        <div class="flex items-center justify-between bg-[#f6f8fa] border border-gray-100 rounded-md p-5 h-30">
                            <span class="text-[20px] flex justify-center w-full text-gray-800 ">Connecting</span>
                        </div>

                        <div class="flex items-center justify-between bg-[#f6f8fa] border border-gray-100 rounded-md p-5 h-30">
                            <span class="text-[20px] flex justify-center w-full text-gray-800 ">Supercharging</span>
                        </div>

                        <div class="flex items-center justify-between bg-[#f6f8fa] border border-gray-100 rounded-md p-5 h-30">
                            <span class="text-[20px] flex justify-center w-full text-gray-800 ">Improving</span>
                        </div>

                        <div class="flex items-center justify-between bg-[#f6f8fa] border border-gray-100 rounded-md p-5 h-30">
                            <span class="text-[20px] flex justify-center w-full text-gray-800 ">Actions</span>
                        </div>

                        <div class="flex items-center justify-between bg-[#f6f8fa] border border-gray-100 rounded-md p-5 h-30">
                            <span class="text-[20px] flex justify-center w-full text-gray-800 ">Add User Leave</span>
                        </div>
                    </div>
              --> 
                </div>
            </div>
    </div>

</div>
