    <x-slot name="header">
        <h2 class="text-[18px] md:text-[30px] font-semibold text-[#EB1C24] sm:text-[18px]">
           Organisations
        </h2>
    </x-slot>
<div x-data="{ activeTab: @entangle('activeTab') }">

<div class="max-w-8xl mx-auto p-0 sm:p-4">
  <!-- Header -->
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
    <!-- Left: Create Button + Search -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full md:w-auto">
<a href="{{ route('organisations.create') }}"
   onclick="
       // Organisation / Office keys
       localStorage.removeItem('organisation_id');
       localStorage.removeItem('office_id');
       localStorage.removeItem('office_name');
       localStorage.removeItem('active_tab');

       // Branch keys remove
       Object.keys(localStorage).forEach(key => {
           if(key.startsWith('branch_')) localStorage.removeItem(key);
       });
   "
   class="bg-[#EB1C24] text-white px-5 py-3 rounded-md text-[16px] shadow font-medium hover:bg-[#c71313] transition w-full sm:w-auto flex items-center" style=" line-height: normal;">
   <svg width="14" height="14" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
<path d="M8 1.5V15.5" stroke="#F8F8F8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
<path d="M1 8.5H15" stroke="#F8F8F8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
 Create new organisations
</a>


      <!-- Search -->
   <div class="relative w-full sm:w-[300px]">
  <span class="absolute top-3.5 left-3 text-gray-500">
    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
      <path fill-rule="evenodd"
        d="M10.5 3.75a6.75 6.75 0 1 0 0 13.5 6.75 6.75 0 0 0 0-13.5ZM2.25 10.5a8.25 8.25 0 1 1 14.59 5.28l4.69 4.69a.75.75 0 1 1-1.06 1.06l-4.69-4.69A8.25 8.25 0 0 1 2.25 10.5Z"
        clip-rule="evenodd" />
    </svg>
  </span>

  <!-- Livewire binding here -->
  <input type="text" 
         placeholder="Search organisations"
         wire:model.live="search"
         class="w-full focus:ring-red-500 focus:border-red-500 bg-[#F8F9FA] placeholder:text-gray-400 text-gray-700 text-sm border border-gray-300 rounded-md pl-9 pr-4 py-3  hover:border-gray-400 shadow-sm" />

</div>
      
    </div>

<button wire:click="$toggle('showFilter')"
    class="px-5 py-3 flex items-center rounded-[8px] border border-[#E5E5E5]  font-medium hover:bg-[#c71313] hover:text-white transition bg-[#F8F9FA] min-w-[150px] flex items-center justify-center btn-hover">
<svg width="21" height="21" viewBox="0 0 21 21" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
<path d="M5.5 10.4995H15.5" stroke="#020202" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
<path d="M7.5 15.4995H14.5" stroke="#020202" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
<path d="M3.5 5.49951H17.5" stroke="#020202" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
</svg>

    Filter
    @php
        $filterCount = count($industry ?? []) + count($turnover ?? []) + count($visibility ?? []);
    @endphp

    @if($filterCount > 0)
        <span class="ml-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">
            {{ $filterCount }}
        </span>
    @endif
</button>

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
                        <button wire:click="$set('activeTab','industry')"
                            class="w-full text-left px-4 py-3.5 transition text-[16px] {{ $activeTab==='industry' ? 'text-[16px] text-[#EB1C24] font-[400] bg-[#FFEFF0]' : 'hover:bg-gray-100' }}">
                            Industry
                        </button>
                    </li>
                    <li class="border-b border-[#D3D3D3]">
                        <button wire:click="$set('activeTab','turnover')"
                            class="w-full text-left px-4 py-3.5 transition text-[16px] {{ $activeTab==='turnover' ? 'text-[16px] text-[#EB1C24] font-[400] bg-red-100 bg-[#FFEFF0]' : 'hover:bg-gray-100' }}">
                            Turnover
                        </button>
                    </li>
                    <li>
                        <button wire:click="$set('activeTab','visibility')"
                            class="w-full text-left px-4 py-3.5 transition text-[16px] {{ $activeTab==='visibility' ? 'text-[16px] text-[#EB1C24] font-[400] bg-[#FFEFF0]' : 'hover:bg-gray-100' }}">
                            Profile Visibility
                        </button>
                    </li>
    </ul>
</div>

            <!-- Right Options -->
            <div class="md:w-2/3 px-6 py-2 border-l  border-[#D3D3D3]">
                @if($activeTab==='industry')
                    @foreach(['IT','Finance','Healthcare'] as $option)
                        <label class="block mb-3 text-[#808080] text-[16px] md:text-[16px] font-[400]">
                            <input type="checkbox" class="form-checkbox accent-red-500 mr-2 h-5 w-5 text-red-600 focus:ring-red-500 focus:border-red-500 border-[#808080] border rounded-[4px]" wire:model="industry" value="{{ $option }}">
                            {{ $option }}
                        </label>
                    @endforeach
                @endif

                @if($activeTab==='turnover')
                    @foreach(['<1Cr','1-10Cr','10-50Cr','50+Cr'] as $option)
                        <label class="block mb-3 text-[#808080] text-[16px] md:text-[16px] font-[400]">
                            <input type="checkbox" class="form-checkbox accent-red-500 mr-2 h-5 w-5 text-red-600 focus:ring-red-500 focus:border-red-500 border-[#808080] border rounded-[4px]" wire:model="turnover" value="{{ $option }}">
                            {{ $option }}
                        </label>
                    @endforeach
                @endif

                @if($activeTab==='visibility')
                    <label class="block mb-3 text-[#808080] text-[16px] md:text-[16px] font-[400]">
                        <input type="checkbox" class="form-checkbox accent-red-500 mr-2 h-5 w-5 text-red-600 focus:ring-red-500 focus:border-red-500 border-[#808080] border rounded-[4px]" wire:model="visibility" value="1"> Public
                    </label>
                    <label class="block mb-3 text-[#808080] text-[16px] md:text-[16px] font-[400]">
                        <input type="checkbox" class="form-checkbox accent-red-500 mr-2 h-5 w-5 text-red-600 focus:ring-red-500 focus:border-red-500 border-[#808080] border rounded-[4px]" wire:model="visibility" value="0"> Private
                    </label>
                @endif
            </div>
        </div>

        <!-- Footer -->
        <div class="flex justify-state space-x-2 py-3 mt-4">
             <button wire:click="closeFilter"
                class="px-6 py-3 bg-[#F8F9FA] rounded-[8px] border-[#E5E5E5] text-[#020202] text-[16px] font-[500] border  hover:bg-gray-300">
                Close
            </button>
           <button wire:click="$set('showFilter', false)" class="px-6 py-3 bg-[#EB1C24] rounded-[8px] border-[#EB1C24] text-[#fff] text-[16px] font-[500] border  hover:bg-gray-300">Save</button>
        </div>
    </div>
</div>

@endif

  </div>

  <!-- Organisations Grid -->
  
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
    @forelse($organisations as $organisation)
        <a href="{{ route('organisations.view', $organisation->id) }}"
           class="bg-white rounded-[12px] border border-[#E5E5E5] p-4 flex gap-4 items-start   cursor-pointer flex-wrap"
           title="View Organisation Details">
            <!-- Logo -->
            <div class="flex flex-col items-center w-[100px]">
                <div class="w-[100px] h-[100px] rounded-md bg-gray-100 flex items-center justify-center mb-3 px-2 overflow-hidden">
                    <img src="{{ $organisation->image ? asset('storage/' . $organisation->image) : 'https://via.placeholder.com/150' }}"
                         alt="{{ $organisation->name }}"
                         >
                </div>
     
            <div class="bg-[#ffdede] text-[12px] rounded-[8px] flex items-center px-2 py-1 text-[#EB1C24] inline-flex" style="line-height: normal;">
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

            <!-- Details -->
            <div class="flex-1">
                <h3 class="text-[20px] md:text-[24px] font-[500] text-[#EB1C24] mb-1 truncate capitalize">{{ $organisation->name }}</h3>
              
              <!--  <p class="text-sm text-gray-600">Industry: <span>{{ $organisation->industry }}</span></p> -->
              
              <p class="text-[16px] text-[#808080] font-[400]">Revenue: <span>{{ $organisation->turnover }}</span></p>
                <p class="text-[16px] text-[#808080] font-[400]">Working Days:
                 @php
    // Define the correct week order
    $weekOrder = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    // Get working days from database
    $workingDays = is_array($organisation->working_days) 
                   ? $organisation->working_days 
                   : explode(',', $organisation->working_days); // in case it's a comma-separated string

    // Sort working days according to $weekOrder
    usort($workingDays, function($a, $b) use ($weekOrder) {
        return array_search($a, $weekOrder) - array_search($b, $weekOrder);
    });
@endphp

<span class="ml-2">{{ implode(', ', $workingDays) }}</span>

                </p>
            {{-- <p class="text-[16px] text-[#808080] font-[400]">Phone: <span>{{ $organisation->phone }}</span></p>
         --}}

            </div>
        </a>


    @empty
        <div class="col-span-full min-h-1000px]">
            <div class="bg-white rounded-xl p-6 text-center text-gray-600 border border-gray-200 shadow-sm">
                No organisations found.
            </div>
        </div>
    @endforelse
</div>
<div class="mt-4 flex justify-center">  
  {{ $organisations->links('components.pagination') }}
</div>



  </div>
</div>

