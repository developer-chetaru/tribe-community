    <x-slot name="header">
        <h2 class="text-[14px] sm:text-[24px] md:text-[30px] capitalize font-bold text-[#EB1C24]">
           {{ $name }}
        </h2>
    </x-slot><div class="flex-1 overflow-auto">
  <div class="">
    <div class="flex justify-between items-center mb-4">
  <a href="{{ route('organisations.view', ['id' => $this->organisationId]) }}" 
           class="bg-[#fff] px-5 py-3 rounded-md shadow hover:bg-[#c71313] text-sm hover:text-white transition inline-flex items-center back-btn">
            <svg width="7" height="11" viewBox="0 0 7 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
            <path d="M6 10.5C6 10.5 1 6.81758 1 5.5C1 4.18233 6 0.5 6 0.5" stroke="#808080" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Back to  {{ $name }}
        </a>    </div>

    <div class="flex bg-white p-4 px-6 border border-gray-200 mt-7 rounded-md flex-wrap">
      <div class="flex justify-between items-center mb-4 w-full">
        <h2 class="text-[#EB1C24] text-[14px] sm:text-[24px] font-[500]">Organisation</h2>
      </div>


    <form wire:submit.prevent="update" class="space-y-6 w-full">
<!-- Logo Upload -->
<div x-data="{ preview: null }" class="relative w-32 h-32 mx-auto">
    <!-- Circular Image Preview / Upload -->
    <label class="w-full h-full flex items-center justify-center rounded-full border-2 border-gray-300 bg-gray-50 cursor-pointer relative overflow-hidden ">
        <!-- Preview Image -->
        <template x-if="preview">
            
                <img :src="preview" alt="Preview" class="w-full h-full rounded-full">
           
        </template>

        <!-- Existing Image -->
        <div class="p-[5px]">
        <template x-if="!preview && '{{ $existingImage }}' != ''">
            <img src="{{ asset('storage/' . $existingImage) }}" alt="Existing Logo" class="w-full h-full rounded-full">
        </template>
 </div>
        <!-- Default Upload Icon -->
        <template x-if="!preview && '{{ $existingImage }}' == ''">
            <img src="{{ asset('images/logo-upload-icon.svg') }}" alt="Upload Icon" class="w-12 h-12 opacity-70">
        </template>

        <!-- File Input -->
        <input type="file" x-ref="fileInput" wire:model="image" accept="image/*"
               @change="preview = $event.target.files.length ? URL.createObjectURL($event.target.files[0]) : null"
               class="absolute inset-0 opacity-0 cursor-pointer">
    </label>

    <!-- Pencil/Edit Icon Overlay -->
    <div class="absolute bottom-0 right-0 bg-red-500 w-8 h-8 rounded-full flex items-center justify-center border-2 border-white cursor-pointer"
         @click="$refs.fileInput.click()">
        <img src="{{ asset('images/pencil1.png') }}" alt="Edit" class="w-4 h-4 text-white">
    </div>
</div>

@error('image') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror

        <!-- Form Fields -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <input type="text" wire:model.live="name" placeholder="Organisation Name"
                       class="border rounded-[8px] px-3 py-2.5 w-full focus:ring-red-500 focus:border-red-500 border border-[#808080]">
                @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number <span class="text-red-500">*</span></label>
                <div>
                    <div wire:ignore>
                        <div x-data="{}" 
                            x-init="
                                const phoneInput = $refs.phoneInput;
                                const errorContainer = $el.closest('div').nextElementSibling;
                                
                                initTelInput(
                                    phoneInput,
                                    @this,
                                    'phone', 
                                    'country_code',
                                    @js($phone),
                                    @js($country_code)
                                );
                                
                                // Function to update error border
                                function updateErrorBorder() {
                                    const hasError = errorContainer && errorContainer.querySelector('span.text-red-500') && 
                                                    errorContainer.querySelector('span.text-red-500').textContent.trim() !== '';
                                    if (hasError) {
                                        phoneInput.classList.add('border-red-500');
                                        phoneInput.classList.remove('border-[#80808080]');
                                    } else {
                                        phoneInput.classList.remove('border-red-500');
                                        phoneInput.classList.add('border-[#80808080]');
                                    }
                                }
                                
                                // Watch for changes in error container
                                if (errorContainer) {
                                    const observer = new MutationObserver(updateErrorBorder);
                                    observer.observe(errorContainer, { childList: true, subtree: true, characterData: true });
                                }
                                
                                // Also check on input
                                phoneInput.addEventListener('input', () => {
                                    setTimeout(updateErrorBorder, 100);
                                });
                            " 
                            style="width: 100% !important;">
                            
                            <input
                                x-ref="phoneInput"
                                type="tel"
                                placeholder="Enter phone number"
                                required
                                class="border rounded-[8px] px-3 py-2.5 w-full focus:ring-red-500 focus:border-red-500 border-[#80808080]"
                            >
                        </div>
                    </div>

                    <div>
                        @error('phone') 
                            <span class="text-red-500 text-sm mt-1 block font-medium">{{ $message }}</span> 
                        @enderror
                        @error('country_code') 
                            <span class="text-red-500 text-sm mt-1 block font-medium">{{ $message }}</span> 
                        @enderror
                    </div>
                </div>
            </div>
<div>
    <select wire:model.live="indus" 
        class="border text-black invalid:text-gray-400 rounded-md px-3 py-2.5 w-full focus:ring-red-500 focus:border-red-500">
        <option value="" disabled selected hidden>Select Industry</option>
        @foreach($allIndustry as $ind)
            <option value="{{ $ind->id }}">
                {{ $ind->name }}
            </option>
        @endforeach
        <option value="other">Other</option>
    </select>

    {{-- Industry dropdown error --}}
    @error('indus') 
        <span class="text-red-500 text-sm">{{ $message }}</span> 
    @enderror

    @if($indus === 'other')
        <input type="text" 
            wire:model.live="otherIndustry" 
            placeholder="Enter Other Industry" 
            class="mt-2 border rounded-md px-3 py-2.5 w-full focus:ring-red-500 focus:border-red-500">

        {{-- Other Industry input error --}}
        @error('otherIndustry') 
            <span class="text-red-500 text-sm">{{ $message }}</span> 
        @enderror
    @endif
</div>


            <div>
                <select wire:model.live="turnover" class="border rounded-[8px] px-3 py-2.5 w-full focus:ring-red-500 focus:border-red-500 border border-[#808080]">
                    <option value="">Select Turnover</option>
                    <option value="<1Cr">&lt; 1 Cr</option>
                    <option value="1-10Cr">1-10 Cr</option>
                    <option value="10+Cr">10+ Cr</option>
      				<option value="50+Cr">50+Cr</option>
                </select>
                @error('turnover') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <input type="text" wire:model.live="founded_year" placeholder="Year Founded"
                       class="border rounded-[8px] px-3 py-2.5 w-full focus:ring-red-500 focus:border-red-500 placeholder-black border border-[#808080]"
                       oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4)">
                @error('founded_year') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <input type="text" wire:model.live="url" placeholder="Website URL"
                       class="border rounded-[8px] px-3 py-2.5 w-full focus:ring-red-500 focus:border-red-500 placeholder-black border border-[#808080]">
                @error('url') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
             <div>
                <select wire:model.live="profile_visibility" class="border rounded-[8px] px-3 py-2.5 w-full focus:ring-red-500 focus:border-red-500 border border-[#808080]">
                   <option value="">Select Profile Visibility</option>
                    
                  <option value="1">Public</option>
                    <option value="0">Private</option>
                    <option value="other">Other</option>
                </select>

                @if($profile_visibility === 'other')
                    <input type="text" wire:model="otherProfileVisibility" placeholder="Enter Other Profile Visibility" class="mt-2 border rounded-md px-3 py-2.5 w-full focus:ring-red-500 focus:border-red-500">
                    @error('otherProfileVisibility')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                @endif

                @error('profile_visibility') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

        </div>

        <!-- Working Days -->
        <div>
            <div class="font-medium mb-2">Working Days</div>
            <div class="flex flex-wrap gap-2">
                @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day)
                    <button type="button"
                            wire:click="toggleDay('{{ $day }}')"
                            class="px-4 py-2.5 rounded border transition {{ in_array($day, $working_days) ? 'bg-[#EB1C24] text-white' : '' }}">
                        {{ $day }}
                    </button>
                @endforeach
            </div>
            @error('working_days') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

@if(session()->has('success'))
    <div x-data="{ show: true }" x-show="show" 
         x-init="setTimeout(() => show = false, 3000)"
         class="mb-4 text-red-600 font-medium">
        {{ session('success') }}
    </div>
@endif
        <!-- Action Buttons -->
        <div class="flex items-center gap-4">
            <button type="submit"
                    class="bg-[#EB1C24] text-white rounded-[8px] px-8 py-2.5 font-semibold hover:bg-red-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    wire:loading.attr="disabled">
                <span wire:loading.remove>Update</span>
                <span wire:loading>Updating...</span>
            </button>
        </div>
    </form>
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
        
        // Remove all non-numeric characters
        let cleanPhone = justPhone.replace(/\D/g, '');
        livewire.set(phoneField, cleanPhone); 
        livewire.set(countryField, `+${dialCode}`);
    }
    
    if (fullNumber) {
        iti.setNumber(fullNumber);
    }
    
    // Restrict input to numbers only
    input.addEventListener("input", function(e) {
        // Remove any non-numeric characters
        let value = e.target.value.replace(/[^0-9]/g, '');
        if (e.target.value !== value) {
            e.target.value = value;
            // Update intlTelInput with cleaned value
            iti.setNumber(value);
        }
        updateValues();
    });
    
    input.addEventListener("countrychange", updateValues);
    input.addEventListener("blur", updateValues);

    updateValues(); 
}
</script>

