    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-bold capitalize text-[#EB1C24]">
           {{ $this->organisationName }}
        </h2>
    </x-slot>
<div class="flex-1 overflow-auto">
  <div class="">
    <div class="flex justify-between items-center mb-4">
    <a href="{{ route('office-list', ['id' => $this->organisationId]) }}" 
    class="bg-[#fff] px-5 py-3 rounded-md shadow hover:bg-[#c71313] text-sm hover:text-white transition inline-flex items-center back-btn">
   <svg width="7" height="11" viewBox="0 0 7 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
            <path d="M6 10.5C6 10.5 1 6.81758 1 5.5C1 4.18233 6 0.5 6 0.5" stroke="#808080" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
    Back to   Office
</a>

    </div>

    <div class="flex bg-white p-4 px-6 border border-gray-200 mt-7 rounded-md flex-wrap">
      <div class="flex justify-between items-center mb-4 w-full">
        <h2 class="text-[#EB1C24] font-medium text-[24px]">Office Details</h2>
      </div>
<form wire:submit.prevent="saveOffice">
<div class="grid grid-cols-2 gap-4 w-full">
    <!-- Office Name -->
    <div>
        <input type="text" wire:model="officeName"
               placeholder="Office Name"
               id="officeName"
               class="border border-gray-300 rounded-md px-3 py-2 w-full focus:ring-red-500 focus:border-red-500">
        @error('officeName') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <!-- Address (Google Places Autocomplete) -->
    <div>
        <input type="text" wire:model="officeAddress"
               placeholder="Address"
               id="officeAddress"
               class="border border-gray-300 rounded-md px-3 py-2 w-full focus:ring-red-500 focus:border-red-500">
        @error('officeAddress') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

  	<!-- Phone Number -->
    <div>
        <div wire:ignore>
            <div x-data="{}"
                x-init="
                    initTelInput(
                        $refs.officePhoneInput,
                        @this,
                        'officePhone',
                        'officeCountryCode',
                        @js($officePhone ?? ''),
                        @js($officeCountryCode ?? '+91')
                    )
                "
                style="width: 100% !important;">

                <input
                    x-ref="officePhoneInput"
                    type="tel"
                    placeholder="Phone Number"
                    class="border border-gray-300 rounded-md px-3 py-2 w-full focus:ring-red-500 focus:border-red-500"
                >
            </div>

            @error('officePhone')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
            @error('officeCountryCode')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <!-- Zip Code -->
    <div>
        <input type="text" wire:model="officeZip"
               placeholder="Zip Code"
               id="officeZip"  oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 8)"
               class="border border-gray-300 rounded-md px-3 py-2 w-full focus:ring-red-500 focus:border-red-500">
        @error('officeZip') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <!-- City -->
    <div>
        <input type="text" wire:model="officeCity"
               placeholder="City"
               id="officeCity"
               class="border border-gray-300 rounded-md px-3 py-2 w-full focus:ring-red-500 focus:border-red-500">
        @error('officeCity') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <!-- State -->
    <div>
        <input type="text" wire:model="officeState"
               placeholder="State"
               id="officeState"
               class="border border-gray-300 rounded-md px-3 py-2 w-full focus:ring-red-500 focus:border-red-500">
        @error('officeState') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <!-- Country -->
    <div>
        <input type="text" wire:model="officeCountry"
               placeholder="Country"
               id="officeCountry"
               class="border border-gray-300 rounded-md px-3 py-2 w-full focus:ring-red-500 focus:border-red-500">
        @error('officeCountry') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
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
<div class="flex items-center mt-4 w-full">
    <button type="submit"
            class="bg-[#EB1C24] text-white text-[16px] px-5 py-3 rounded-[8px]  font-[400] hover:bg-[#EB1C24]">
        Add Office
    </button>
    <button type="button" wire:click="resetOfficeForm"
            class="ml-3 text-[#808080] text-[16px] font-[400]">
        Reset All
    </button>
</div>
</form>
</div>


{{-- JavaScript for Google Places Autocomplete (Livewire 3 compatible) --}}
<script 
    src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDZyg761r13rvaPI2-LvSyRNUwfpdvsbK0&libraries=places&callback=initGoogleMaps" 
    async defer>
</script>

<script>
function initGoogleMaps() {
    const officeInput = document.getElementById('officeAddress');
    if (officeInput) {
        const autocomplete = new google.maps.places.Autocomplete(officeInput, { types: ['establishment'] });
       autocomplete.addListener('place_changed', () => {
    const place = autocomplete.getPlace();
    if (!place.address_components) return;

    // Fill the main address input with full formatted address
    officeInput.value = place.formatted_address || '';

    let city='', state='', country='', zip='';
    (place.address_components || []).forEach(c => {
        if(c.types.includes('locality')) city=c.long_name;
        if(c.types.includes('administrative_area_level_1')) state=c.long_name;
        if(c.types.includes('country')) country=c.long_name;
        if(c.types.includes('postal_code')) zip=c.long_name;
    });

    // Fill sub-fields
    document.getElementById('officeCity').value = city;
    document.getElementById('officeState').value = state;
    document.getElementById('officeCountry').value = country;
    document.getElementById('officeZip').value = zip;

    // Trigger Livewire bindings
    ['officeAddress','officeCity','officeState','officeCountry','officeZip'].forEach(id => {
        let input = document.getElementById(id);
        if(input){
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });

    // Update Livewire props
    if(window.$wire){
        $wire.set('officeAddress', place.formatted_address || '');
        $wire.set('officeCity', city);
        $wire.set('officeState', state);
        $wire.set('officeCountry', country);
        $wire.set('officeZip', zip);
    }
});
}

    initBranchAutocomplete();
}

function waitForGooglePlaces() {
    return new Promise((resolve) => {
        if (window.google?.maps?.places) return resolve();
        const iv = setInterval(() => {
            if (window.google?.maps?.places) { clearInterval(iv); resolve(); }
        }, 50);
    });
}
  
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





