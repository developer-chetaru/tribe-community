<div x-data="{ 
        activeTab: @entangle('activeTab'),
        officeId: localStorage.getItem('office_id'),
        skipToEmployee: false   
     }"
     x-init="
        $nextTick(() => {
            let storedTab = localStorage.getItem('active_tab');
            if(skipToEmployee){
                activeTab = 'employee';
            } else if(officeId){
                activeTab = 'organisation';
            } else if(storedTab){
                activeTab = storedTab;
            }

            $watch('activeTab', value => {
                if(value){
                    localStorage.setItem('active_tab', value);
                }
            });
        })
     "
>
    <div class="mb-6">  
        <a href="{{ route('organisations.index')}}" 
        class="bg-[#fff] px-4 py-3 rounded-md  hover:bg-[#c71313] text-sm hover:text-white transition inline-flex items-center back-btn">
           <svg width="7" height="11" viewBox="0 0 7 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
            <path d="M6 10.5C6 10.5 1 6.81758 1 5.5C1 4.18233 6 0.5 6 0.5" stroke="#808080" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Back
        </a>
    </div>
    <x-slot name="header">
        <h2 class="text-[16px] md:text-[30px] font-semibold text-[#EB1C24] ms:text-[18px]">
           Organisations
        </h2>
    </x-slot>  
    <!-- Tabs -->
    <div class="flex p-0.5 relative items-center mb-6 flex-wrap sm:flex-nowrap">
        <!-- Organisation Tab -->
        <a href="#"
           @click.prevent="activeTab = 'organisation'"
           :class="activeTab === 'organisation' ? 'bg-[#FFEFF0] text-[#EB1C24] border-[#EB1C24]' : 'text-black'"
           class="tab-link text-[14px] h-14 w-full sm:w-[260px] flex items-center justify-center rounded-lg border font-medium  border border-[#808080] bg-[#F8F9FA] sm:text-[18px] text-[#020202] mb-2 sm:mb-0">
           Organization Info
        </a>
        <div class="h-[2px] w-10 bg-[#d7d7d7] hidden sm:block"></div>

        <!-- Office Tab -->
        <a href="#"
           @click.prevent="if(officeId || skipToEmployee) activeTab = 'office'"
           :class="activeTab === 'office' ? 'bg-[#FFEFF0] text-[#EB1C24] border-[#EB1C24]' : 'text-black'"
           :class="{ 'opacity-50 cursor-not-allowed': !officeId && !skipToEmployee }"
           class="tab-link text-[14px] h-14 w-full sm:w-[260px] flex items-center justify-center rounded-lg border font-medium  border border-[#808080] bg-[#F8F9FA] sm:text-[18px] text-[#020202] mb-2 sm:mb-0">
           Add Office
        </a>
        <div class="h-[2px] w-10 bg-[#d7d7d7] hidden sm:block"></div>

        <!-- Employee Tab -->
        <a href="#"
           @click.prevent="if(officeId || skipToEmployee) activeTab = 'employee'"
           :class="activeTab === 'employee' ? 'bg-[#FFEFF0] text-[#EB1C24] border-[#EB1C24]' : 'text-black'"
           :class="{ 'opacity-50 cursor-not-allowed': !officeId && !skipToEmployee }"
           class="tab-link text-[14px]  h-14 w-full sm:w-[260px] flex items-center justify-center rounded-lg border border-[#808080] bg-[#F8F9FA] sm:text-[18px] text-[#020202] font-medium mr-1 mb-2 sm:mb-0">
           Add Employee
        </a>
    </div>
    <!-- Tab Content -->
    <div class="mt-4 bg-white p-6 border border-[#E5E5E5] rounded-md ">
        <div x-show="activeTab === 'organisation'">
            @include('livewire.create-organisation')
        </div>
        <div x-show="activeTab === 'office'">
            @include('livewire.office-form')
        </div>
        <div x-show="activeTab === 'employee'">
            @include('livewire.employee-form')
        </div>
    </div>
</div>
