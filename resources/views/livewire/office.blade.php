<x-slot name="header">
    <h2 class="text-[24px] md:text-[30px]  font-bold capitalize text-[#EB1C24]">
       {{ $organisationName }}
    </h2>
</x-slot>

<div class="flex-1 overflow-auto">
    <div class="max-w-8xl mx-auto p-4">
        <!-- Back + Add Office Buttons -->
        <div class="flex justify-between items-center mb-4">
            <a href="{{ route('organisations.view', ['id' => $organisationId]) }}" 
               class="bg-[#fff] px-4 py-3 rounded-md  hover:bg-[#c71313] text-sm hover:text-white transition inline-flex items-center back-btn">
               <svg width="7" height="11" viewBox="0 0 7 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
            <path d="M6 10.5C6 10.5 1 6.81758 1 5.5C1 4.18233 6 0.5 6 0.5" stroke="#808080" stroke-linecap="round" stroke-linejoin="round"/>
            </svg> Back to    {{ $organisationName }}
            </a>

            <a href="{{ route('office-add', ['id' => $organisationId]) }}" 
               class="bg-[#EB1C24] text-white px-5 py-2 rounded-md shadow text-[16px] font-medium inline-flex items-center">
                <svg width="16" height="16" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
<path d="M8 1.5V15.5" stroke="#F8F8F8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
<path d="M1 8.5H15" stroke="#F8F8F8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
 Add Office
            </a> 
        </div>

        <!-- Office Section -->
        <div class="flex bg-white p-4 border border-gray-200 mt-7 rounded-md flex-wrap">

            <!-- Header -->
            <div class="flex justify-between items-center mb-4 w-full flex-wrap lg:flex-nowrap">
                <h2 class="text-[#EB1C24] text-[24px] font-medium">Office</h2>
                <div class="flex items-center justify-between">
                  
           <div class="w-full min-w-[300px]  mr-3 ">
                <div class="relative">
                    <button class="absolute top-3 left-4 flex items-center" type="button">
                        <svg width="20" height="21" class="mr-2" viewBox="0 0 20 21" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M9.16667 16.3333C12.8486 16.3333 15.8333 13.3486 15.8333 9.66667C15.8333 5.98477 12.8486 3 9.16667 3C5.48477 3 2.5 5.98477 2.5 9.66667C2.5 13.3486 5.48477 16.3333 9.16667 16.3333Z" stroke="#808080" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
<path d="M17.5 18L13.875 14.375" stroke="#808080" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
</svg>

                    </button>  
                    <input    wire:model.live="search"
                        class="w-full bg-[#F8F9FA] focus:ring-red-500 focus:border-red-500 rounded-[8px] border border-[#E5E5E5]  pl-10 pr-5 py-3 text-[14px] font-[500] hover:border-slate-300"
                        placeholder="Search offices" 
                    />
                </div>
            </div>
                    <!-- Filter -->
                    <button wire:click="$toggle('showFilter')" type="button"  class="px-5 py-2.5 flex items-center rounded-[8px] border border-[#E5E5E5]  font-medium hover:bg-[#c71313] hover:text-white transition bg-[#F8F9FA] min-w-[150px] flex items-center justify-center btn-hover">
<svg width="21" height="21" viewBox="0 0 21 21" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
<path d="M5.5 10.4995H15.5" stroke="#020202" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
<path d="M7.5 15.4995H14.5" stroke="#020202" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
<path d="M3.5 5.49951H17.5" stroke="#020202" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
    
    
                Filter
           @php
        $filterCount = count($country ?? []) + count($city ?? []);
    @endphp

    @if($filterCount > 0)
        <span class="ml-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">
            {{ $filterCount }}
        </span>
    @endif
                    </button>
                </div>
            </div>

            {{-- Head Office --}}
       @if($headOffice)
    <div class="bg-white rounded-xl p-4 flex relative cursor-pointer border border-gray-200 w-full mb-6">
        <div class="flex flex-wrap justify-center w-[100px]">
            <div class="w-[100px] h-[100px] rounded-[10px]  p-2 bg-gray-100 flex items-center justify-center mb-3 overflow-hidden">
              <img src="{{ $organisationImage ? asset('storage/' . $organisationImage) : 'https://via.placeholder.com/150' }}"
                     alt="{{ $organisationImage }}"
                     class="object-cover max-w-full max-h-full">
            </div>
        </div>

        <div class="w-full text-center mb-1 pl-4 flex items-center justify-between">
            <div class="ofice-text pr-3 text-left">
                <h3 class="text-[20px] font-semibold text-[#EB1C24] mb-2 flex items-center capitalize">
                    {{ $headOffice->name }}
                    <span class="ml-3 flex items-center rounded-xl px-3 py-2 bg-[#EB1C24] font-[500] text-white text-[14px] rounded-[10px]" style="line-height: normal;">
                        Head Office
                    </span>
                </h3>

                <span class="text-[12px] lg:text-[16px] font-[400] text-[#808080] flex mb-2 items-center max-w-[800px]">
                    <img src="{{ asset('images/location.svg') }}" class="mr-3"> {{ $headOffice->address }}
{{ $headOffice->zip_code }} 
                  
                </span>
                <span class="text-[12px] lg:text-[16px] font-[400] text-[#808080] flex mb-2 items-center">
                    <img src="{{ asset('images/calling.svg') }}" class="mr-3"> 
                    <a href="tel:{{ $headOffice->country_code ? $headOffice->country_code.$headOffice->phone : $headOffice->phone }}">
                      {{ $headOffice->country_code ? $headOffice->country_code . ' ' . $headOffice->phone : $headOffice->phone }}
                    </a>
                </span>

			
  			<span class="text-[12px] lg:text-[16px] font-[400] text-[#808080] flex mb-3 items-center capitalize">
    <img src="{{ asset('images/team-lead-icon.svg') }}" class="mr-3"> 
    {{ $teamLeads[$headOffice->id]->name ?? 'Not assigned' }}
   
</span>


            </div>

            <div class="btns-box flex items-center">
              
              <!-- View Staff Button -->
              <x-tooltip tooltipText="View Staff">    
                <a href="{{ route('office.staff', ['officeId' => $headOffice->id]) }}" 
                   class="flex justify-center items-center py-2 px-3 bg-[#ffeaec] border rounded-md border-[#FF9AA0] ml-3">
                  <img src="{{ asset('images/user-star.svg') }}" alt="View Staff" class="h-5 w-5">
                </a>
              </x-tooltip>
              
              <x-tooltip tooltipText="Edit Office">
                            <a href="{{ route('office-update', ['id' => $headOffice->id]) }}" 
   class="flex justify-center items-center py-2 px-3 bg-[#ffeaec] border rounded-md border-[#FF9AA0] ml-3">
       <img src="{{ asset('images/edit-pencil.svg') }}" alt="Edit Office" class="h-5 w-5">
		</a>
			</x-tooltip>
@if($totalOffices == 1)
<x-tooltip tooltipText="Delete Office">    
    <button 
       wire:click="confirmDeleteHeadOffice({{ $headOffice->id }})"
       class="flex justify-center items-center py-2 px-3 bg-[#ffeaec] border rounded-md border-[#FF9AA0] ml-3">
        <img src="{{ asset('images/delete-office.svg') }}" alt="Delete Office" class="h-5 w-5">
    </button>
</x-tooltip>
@endif

{{-- Delete Confirmation Modal --}}
<div 
    x-data="{ showDeleteHeadOfficeModal: false }"
    x-on:show-delete-head-office-modal.window="showDeleteHeadOfficeModal = true"
    x-on:close-delete-head-office-modal.window="showDeleteHeadOfficeModal = false"
    x-show="showDeleteHeadOfficeModal"
    x-cloak
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">

    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
        <h2 class="text-lg font-semibold text-red-600 mb-4">Confirm Deletion</h2>

        <p class="text-gray-700 mb-6">Are you sure you want to delete this office?</p>

        <div class="flex justify-end gap-3">
            <button @click="showDeleteHeadOfficeModal = false" 
                    class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">
                Cancel
            </button>

            <button wire:click="deleteOffice" 
                    class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                Delete
            </button>
        </div>
    </div>
</div>



            </div>
        </div>
    </div>
@endif


            {{-- Branch Offices --}}
            <div class="w-full mb-4 gap-5 grid lg:grid-cols-2 grid-cols-1">
         @foreach($offices as $office)
    <div class="bg-white rounded-xl p-4 flex relative cursor-pointer border border-gray-200">
  
        <!-- Office Details -->
        <div class="w-full text-center mb-1 pl-0 flex items-center justify-between">
            <div class="ofice-text pr-3 text-left">
                <h3 class="text-[20px] font-[500] text-[#020202] mb-1 flex items-center capitalize">
                    {{ $office->name }}
                </h3>
   
        
                <span class="text-[12px] lg:text-[16px] font-[400] text-[#808080] flex mb-2 items-center max-w-[800px]">
                    <img src="{{ asset('images/location.svg') }}" class="mr-3"> {{ $office->address }}
                </span>

                <span class="text-[12px] lg:text-[16px]  font-[400] text-[#808080] flex mb-2 items-center">
                    <img src="{{ asset('images/calling.svg') }}" class="mr-3"> 
                    <a href="tel:{{ $office->phone }}">{{ $office->phone }}</a>
                </span>
  			<span class="text-[12px] lg:text-[16px]  font-[400] text-[#808080] flex mb-3 items-center capitalize">
    <img src="{{ asset('images/team-lead-icon.svg') }}" class="mr-3"> 
    {{ $teamLeads[$office->id]->name ?? 'Not assigned' }}
   
</span>


    
            </div>

            <div class="btns-box flex items-start justify-end flex-wrap w-[110px]">
                {{-- Only non-head offices get "Make Head" button --}}
          <div 
    x-data="{ showConfirm: false, officeId: null, officeName: '' }" 
    class="flex justify-center mb-1"
>
    <!-- Normal Button -->
            <!-- View Staff Button -->
            <x-tooltip tooltipText="View Staff">
                <a href="{{ route('office.staff', ['officeId' => $office->id]) }}"
                    class="flex justify-center items-center py-2 px-3 bg-[#ffeaec] border rounded-md border-[#FF9AA0] ml-3">
                    <img src="{{ asset('images/user-star.svg') }}" alt="View Staff" class="h-5 w-5">
                </a>
            </x-tooltip>
<x-tooltip tooltipText="Mark as head office">	
    <button 
        @click="
            officeId = {{ $office->id }}; 
            officeName = '{{ $office->name }}'; 
            showConfirm = true
        "
        class="flex justify-center items-center py-2 px-3 bg-[#ffeaec] border rounded-md border-[#FF9AA0] text-sm mb-1"
    >
       <img src="{{ asset('images/office.svg') }}" alt="Edit Office" class="h-5 w-5">
    </button>
</x-tooltip>

    <!-- Custom Confirm Modal --> 
    <div 
        x-show="showConfirm" 
        x-cloak
        class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50"
    >
        <div class="bg-white rounded-lg shadow-xl w-96 p-6">
            <h2 class="text-lg font-semibold mb-4 text-gray-800">Confirm</h2>
            <p class="text-sm text-gray-600 mb-6">
                Do you want to set 
                <span class="font-semibold text-red-500 capitalize" x-text="officeName"></span> 
                as your Head Office?
            </p>
            
            <div class="flex justify-end space-x-3">
                <!-- Cancel -->
                <button 
                    @click="showConfirm = false" 
                    class="px-4 py-2 text-sm rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100"
                >
                    Cancel
                </button>

                <!-- Confirm -->
                <button 
                    @click="showConfirm = false" 
                    wire:click="toggleHeadOffice(officeId)"
                    class="px-4 py-2 text-sm rounded-lg bg-red-500 text-white hover:bg-red-600"
                >
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>
              
              
 
<x-tooltip tooltipText="Edit Office">	
    <a href="{{ route('office-update', ['id' => $office->id]) }}" 
   class="flex justify-center items-center py-2 px-3 bg-[#ffeaec] border rounded-md border-[#FF9AA0] ml-3">
    <img src="{{ asset('images/edit-pencil.svg') }}" alt="Edit Office" class="h-5 w-5">
</a>
</x-tooltip>

<div>
    {{-- Office List --}}

<x-tooltip tooltipText="Delete Office">	
    <button 
       wire:click="confirmDelete({{ $office->id }})"
        class="flex justify-center items-center py-2 px-3 bg-[#ffeaec] border rounded-md border-[#FF9AA0] ml-3">
      <img src="{{ asset('images/delete-office.svg') }}" alt="Edit Office" class="h-5 w-5">
   
    </button>
</x-tooltip>
           
     
   

    {{-- Delete Confirmation Modal --}}
    <div 
        x-data="{ showConfirm: false }"
        x-on:show-confirm-modal.window="showConfirm = true"
        x-show="showConfirm"
        x-cloak
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">

        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
            <h2 class="text-lg font-semibold text-red-600 mb-4">Confirm Deletion</h2>

            {{-- Agar staff hai --}}
     @if($officeStaff && count($officeStaff) > 0)
    <p class="text-gray-700 mb-3">This office still has staff members:</p>
    <ul class="mb-4 list-disc list-inside text-sm text-gray-600 space-y-1 capitalize">
        @foreach($officeStaff as $staff)
            <li>{{ $staff->name }}</li>
        @endforeach
    </ul>

    <p class="text-gray-700 mb-3">Select office(s) to transfer staff:</p>
  <ul class="mb-4 space-y-2 ml-20">
    @foreach($officesList as $o)
        <li class="flex items-center mt-2">
            <input type="checkbox" 
                   class="form-checkbox accent-red-500 h-5 w-5 text-red-600 focus:ring-red-500 focus:border-red-500 rounded-[5px]" 
                   value="{{ $o->id }}" 
                   wire:model="transferOfficeIds">
            <span class="ml-2 text-sm text-[#020202] text-[16px] capitalize">{{ $o->name }}</span>
        </li>
    @endforeach
</ul>

    <div class="flex justify-end gap-3">
        <button @click="showConfirm = false"
                class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">
            Cancel
        </button>
      <div x-data="{ selected: @entangle('transferOfficeIds') }">
    <button wire:click="transferStaff"
            :disabled="selected.length === 0"
            class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 disabled:opacity-50">
        Transfer
    </button>
        
        <button wire:click="deleteOffice" 
                class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
            Delete
        </button>
</div>


    </div>
@else
    <p class="text-gray-700 mb-6">Are you sure you want to delete this office?</p>
    <div class="flex justify-end gap-3">
        <button @click="showConfirm = false" 
                class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">
            Cancel
        </button>
        <button wire:click="deleteOffice" 
                class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
            Delete
        </button>
    </div>
@endif

        </div>
    </div>
</div>

            </div>
        </div>
    </div>
@endforeach
<script>
    window.addEventListener('close-and-refresh', () => {
        // Close all modals
        document.querySelectorAll('[x-data]').forEach(el => {
            if (el.__x) {
                el.__x.$data.showConfirm = false;
            }
        });
        // Hard refresh
        location.reload();
    });
</script>


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
                        <button wire:click="$set('activeTab','country')"
                            class="w-full text-left px-4 py-3.5 transition text-[16px] transition {{ $activeTab==='country' ? 'text-[16px] text-[#EB1C24] font-[400] bg-[#FFEFF0]' : 'hover:bg-gray-100' }}">
                            Country
                        </button>
                    </li>
                    <li>
                        <button wire:click="$set('activeTab','city')"
                            class="w-full text-left px-4 py-3.5 transition text-[16px] {{ $activeTab==='city' ? 'text-[16px] text-[#EB1C24] font-[400] bg-[#FFEFF0]' : 'hover:bg-gray-100' }}">
                            City
                        </button>
                    </li>
               
                </ul>
            </div>

            <!-- Right Options -->
            <div class="md:w-2/3 px-6 py-2 border-l  border-[#D3D3D3]">
      @if($activeTab==='country')
    @foreach($countries as $option)
        <label class="block mb-3 text-[#808080] text-[16px] md:text-[16px] font-[400] capitalize">
            <input 
                type="checkbox" 
                class="form-checkbox accent-red-500 mr-2 h-5 w-5 text-red-600 focus:ring-red-500 focus:border-red-500 border-[#808080] border rounded-[4px]" 
                wire:model="country" 
                value="{{ $option }}">
            {{ $option }}
        </label>
    @endforeach
@endif

@if($activeTab==='city')
    @foreach($cities as $city)
        <label class="block mb-3 text-[#808080] text-[16px] md:text-[16px] font-[400] capitalize">
            <input type="checkbox" 
                   class="form-checkbox accent-red-500 mr-2 h-5 w-5 text-red-600 focus:ring-red-500 focus:border-red-500 border-[#808080] border rounded-[4px]" 
                   wire:model="city" 
                   value="{{ $city }}">
            {{ $city }}
        </label>
    @endforeach
@endif

            </div>
        </div>

        <!-- Footer -->
        <div class="flex justify-end space-x-2 py-3 mt-4">
            <button wire:click="closeFilter" class="px-6 py-3 bg-[#F8F9FA] rounded-[8px] border-[#E5E5E5] text-[#020202] text-[16px] font-[500] border  hover:bg-gray-300">Close</button>
            <button wire:click="saveFilters" class="px-6 py-3 bg-[#EB1C24] rounded-[8px] border-[#EB1C24] text-[#fff] text-[16px] font-[500] border  hover:bg-gray-300">Save</button>
        </div>
    </div>
</div>
@endif


            </div>

<div class="mt-4 flex justify-center w-full">
  
  {{ $offices->links('components.pagination') }}

</div>



        </div>
    </div>
</div>
