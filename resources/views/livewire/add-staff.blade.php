    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold capitalize text-[#EB1C24]">
           {{ $this->organisationName }}
        </h2>
    </x-slot>
<div class="flex-1 overflow-auto">
  <div class="">
    
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
      <a href="{{ route('staff-list', ['id' => $this->organisationId]) }}" 
    class="bg-[#fff] px-5 py-3 rounded-md shadow hover:bg-[#c71313] text-sm hover:text-white transition inline-flex items-center back-btn">
   <svg width="7" height="11" viewBox="0 0 7 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
            <path d="M6 10.5C6 10.5 1 6.81758 1 5.5C1 4.18233 6 0.5 6 0.5" stroke="#808080" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
       Back to    {{ $this->organisationName }} Staff
</a>
    </div>

    <!-- Card -->
    <div class="bg-white shadow-md border border-gray-200 rounded-lg p-6">
      
      <!-- Title -->
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-[24px] font-semibold text-[#EB1C24]">Staff Details</h2>
      </div>

 

      <!-- Form Fields -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        
        {{-- First Name --}}
        <div>
          <input type="text" 
                 placeholder="Enter First Name"
                 wire:model="first_name"
                 class="border border-gray-300 focus:ring-red-500 focus:border-red-500 rounded-md px-3 py-2 w-full outline-none">
          @error('first_name') 
            <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> 
          @enderror
        </div>

        {{-- Last Name --}}
        <div>
          <input type="text" 
                 placeholder="Enter Last Name"
                 wire:model="last_name"
                 class="border border-gray-300 focus:ring-red-500 focus:border-red-500 rounded-md px-3 py-2 w-full outline-none">
          @error('last_name') 
            <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> 
          @enderror
        </div>

        {{-- Email --}}
        <div>
          <input type="email" 
                 placeholder="Enter Email Address"
                 wire:model="email"
                 class="border border-gray-300 focus:ring-red-500 focus:border-red-500 rounded-md px-3 py-2 w-full outline-none">
          @error('email') 
            <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> 
          @enderror
        </div>

        {{-- Phone --}}
        <div>
          <div wire:ignore>
            <div x-data="{}"
                x-init="
                    initTelInput(
                        $refs.phoneInput,
                        @this,
                        'phone',
                        'country_code',
                        @js($phone ?? ''),
                        @js($country_code ?? '+91')
                    )
                "
                style="width: 100% !important;">

                <input
                    x-ref="phoneInput"
                    type="tel"
                    placeholder="Enter Phone Number"
                    class="border border-gray-300 rounded-md px-3 py-2 w-full focus:ring-red-500 focus:border-red-500 outline-none"
                >
            </div>

            @error('phone')
                <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
            @enderror
            @error('country_code')
                <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
            @enderror
          </div>
        </div>

        {{-- Office --}}
        <div>
          <select wire:model="office" 
                  class="border border-gray-300 focus:ring-red-500 focus:border-red-500 rounded-md px-3 py-2 w-full outline-none capitalize">
              <option value="">Select Office</option>
              @foreach($offices as $office)
                <option value="{{ $office->id }}">{{ $office->name }}</option>
              @endforeach
          </select>
          @error('office') 
            <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> 
          @enderror
        </div>

        {{-- Department --}}
      <div>
    <select id="department" wire:model="department" class="border focus:ring-red-500 focus:border-red-500 border-gray-300 rounded-md w-full px-3 py-2">
        <option value="">Select Department</option>
        @foreach($allDepartments as $dept)
            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
        @endforeach
    </select>
    @error('department')
        <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
    @enderror
</div>

      </div>
     {{-- Success Message --}}
    @if(session()->has('success'))
    <div x-data="{ show: true }" x-show="show" 
         x-init="setTimeout(() => show = false, 3000)"
         class="m-4 text-red-600 font-medium">
        {{ session('success') }}
    </div>
@endif
      <!-- Buttons -->
      <div class="flex items-center mt-8">
        <button wire:click="saveEmployee"
                class="bg-[#EB1C24] hover:bg-red-700 transition text-white px-6 py-2 rounded-md shadow font-medium">
            Add Staff
        </button>
        <button type="reset" wire:click="resetForm" 
                class="ml-4 text-gray-600 hover:text-gray-800 transition">
            Reset All
        </button>
      </div>

    </div>
  </div>
</div>
<script>
function initTelInput(input, livewire, phoneField, countryField, initialPhone = '', initialCountryCode = '+91') {
    
    const initialDialCode = initialCountryCode.replace('+', '');
    let initialIsoCode = 'us'; 

    if (window.intlTelInputGlobals && initialDialCode) {
        try {
            const countryDataList = window.intlTelInputGlobals.getCountryData();
            const foundCountry = countryDataList.find(country => country.dialCode === initialDialCode);
            if (foundCountry) {
                initialIsoCode = foundCountry.iso2;
            }
        } catch (e) {
            console.error("intlTelInputGlobals not fully ready:", e);
        }
    }
    
    let iti = window.intlTelInput(input, {
        initialCountry: initialIsoCode, 
        separateDialCode: true,
        utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js",
    });

    const fullNumber = (initialCountryCode + initialPhone).replace(/\s/g, '');

    function updateValues() {
        let isValid = iti.isValidNumber();
        let phoneNumber = isValid ? iti.getNumber(intlTelInputUtils.numberFormat.E164) : ''; 
        
        let countryData = iti.getSelectedCountryData();
        let dialCode = countryData.dialCode || '91';
        let justPhone = '';

        if (phoneNumber.startsWith(`+${dialCode}`)) {
            justPhone = phoneNumber.replace(`+${dialCode}`, '');
        } else {
            justPhone = input.value;
        }
        livewire.set(phoneField, justPhone.replace(/\D/g, '')); 
        livewire.set(countryField, `+${dialCode}`);
    }
    
    if (fullNumber) {
        iti.setNumber(fullNumber);
    }
    
    input.addEventListener("input", updateValues);
    input.addEventListener("countrychange", updateValues);

    updateValues(); 
}
</script>
