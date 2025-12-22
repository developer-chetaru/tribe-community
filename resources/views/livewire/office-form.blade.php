<div 
    x-data="{ officeId: @entangle('officeId'), officeName: @entangle('officeName') }"
    x-init="
        $nextTick(() => {
            // --- Restore officeId & officeName from localStorage ---
            let storedId = localStorage.getItem('office_id');
            let storedName = localStorage.getItem('office_name');

            if (storedId) {
                officeId = storedId;
                $wire.set('officeId', officeId).then(() => {
                    if(officeId) {
                        $wire.loadOffice(officeId); // backend se load karo
                    }
                });
            }

            if (storedName) {
                officeName = storedName;
            }

            // --- Watch changes and save to localStorage ---
            $watch('officeId', value => {
                if(value){
                    localStorage.setItem('office_id', value);

                    // Agar officeName already set hai, save karo
                    if(officeName) localStorage.setItem('office_name', officeName);
                } else {
                    localStorage.removeItem('office_id');
                    localStorage.removeItem('office_name');
                }
            });

            $watch('officeName', value => {
                if(value) localStorage.setItem('office_name', value);
            });
        })
    "
>



<form class="" wire:submit.prevent="saveOffice">

    {{-- Main Office --}}
    <div class="flex justify-between items-center">
    <h2 class="text-[24px] text-[#EB1C24] mb-5">Add Office</h2>
    <!-- Skip Button -->
    <button type="button"
            @click="
                skipToEmployee = true;     
                activeTab = 'employee';    
                localStorage.setItem('active_tab', 'employee');
            "
            class=" px-4 py-2 rounded hover:bg-gray-300 mb-4 text-[#808080] text-[16px] rounded-[10px] border border-[#808080] bg-[#F8F9FA]">
        Skip
    </button>

</div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div>
            <input placeholder="Office Name" type="text" wire:model.defer="officeName" class="w-full border rounded px-3 py-2 focus:ring-red-500 focus:border-red-500">
            @error('officeName') <p class="text-red-500 text-sm">{{ $message }}</p> @enderror
        </div>
   <div>
            <input placeholder="Address" type="text" wire:model.defer="officeAddress" id="officeAddress" class="w-full border rounded px-3 py-2 focus:ring-red-500 focus:border-red-500">
            @error('officeAddress') <p class="text-red-500 text-sm">{{ $message }}</p> @enderror
        </div>
 
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mt-4">
        <div>
            <div wire:ignore
                x-data="{}"
                x-init="initTelInput($refs.officePhoneInput, @this, 'officePhone', 'officeCountryCode')"
                style="width: 100% !important;">
                <input 
                    x-ref="officePhoneInput" 
                    type="tel" 
                    placeholder="Phone Number" 
                    class="w-full border rounded px-3 py-2 focus:ring-red-500 focus:border-red-500 placeholder-black"
                >
            </div>

            @error('officePhone') 
                <p class="text-red-500 text-sm">{{ $message }}</p> 
            @enderror
            @error('officeCountryCode') 
                <p class="text-red-500 text-sm">{{ $message }}</p> 
            @enderror
        </div>
 		<div>
            <input placeholder="Zip Code" type="text" wire:model.defer="officeZip" id="officeZip"
                   oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)" class="w-full border rounded px-3 py-2 focus:ring-red-500 focus:border-red-500">
            @error('officeZip') <p class="text-red-500 text-sm">{{ $message }}</p> @enderror
        </div>
     
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mt-4">
        <div>
            <input placeholder="City" type="text" wire:model.defer="officeCity" id="officeCity" class="w-full border rounded px-3 py-2 focus:ring-red-500 focus:border-red-500">
            @error('officeCity') <p class="text-red-500 text-sm">{{ $message }}</p> @enderror
        </div>
        <div>
            <input placeholder="State" type="text" wire:model.defer="officeState" id="officeState" class="w-full border rounded px-3 py-2 focus:ring-red-500 focus:border-red-500">
            @error('officeState') <p class="text-red-500 text-sm">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mt-4">
       
        <div>
            <input placeholder="Country" type="text" wire:model.defer="officeCountry" id="officeCountry" class="w-full border rounded px-3 py-2 focus:ring-red-500 focus:border-red-500">
            @error('officeCountry') <p class="text-red-500 text-sm">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label class="inline-flex items-center mt-4">
            <input type="checkbox" wire:model.defer="isHeadOffice" class="form-checkbox accent-red-500 h-5 w-5 text-red-600 focus:ring-red-500 focus:border-red-500 rounded-[5px]">
            <span class="ml-2 text-sm text-[#020202] text-[16px]">Head Office</span>
        </label>
    </div>
@if(count($branches) > 0)
    {{-- Branch Offices --}}
    <div class="border  rounded border-gray-300 mt-6">
        <h2 class="text-[24px] text-[#EB1C24] mb-5 px-4 pt-6">Branch Offices</h2>

        <table class="table-auto border-collapse border border-gray-300 w-full mb-4">
            <thead>
                <tr class="bg-[#F8F9FA]">
                    <th class="border border-gray-300 p-3 text-[14px] md:text-[16px] text-[#808080] border-l-0">Branch</th>
                    <th class="border border-gray-300 p-3 text-[14px] md:text-[16px] text-[#808080]">Address</th>
                    <th class="border border-gray-300 p-3 text-[14px] md:text-[16px] text-[#808080]">Zip Code</th>
                    <th class="border border-gray-300 p-3 text-[14px] md:text-[16px] text-[#808080]">City</th>
                    <th class="border border-gray-300 p-3 text-[14px] md:text-[16px] text-[#808080]">State</th>
                    <th class="border border-gray-300 p-3 text-[14px] md:text-[16px] text-[#808080]">Country</th>
                    <th class="border border-gray-300 p-3 text-[14px] md:text-[16px] text-[#808080] border-r-0">Actions</th>
                </tr>
            </thead>
          <tbody>
@foreach($branches as $index => $branch)
<tr
    x-data="branchRow({ 
        index: {{ $index }},
        branchId: @entangle('branches.' . $index . '.id'),
        branchName: @entangle('branches.' . $index . '.name'),
        branchAddress: @entangle('branches.' . $index . '.address'),
        branchCity: @entangle('branches.' . $index . '.city'),
        branchState: @entangle('branches.' . $index . '.state'),
        branchZip: @entangle('branches.' . $index . '.zip'),
        branchCountry: @entangle('branches.' . $index . '.country')
    })"
    x-init="init(); $nextTick(() => { 
        // Agar ye last added branch hai, uske name field pe focus
        if($wire.lastAddedBranchIndex === {{ $index }}) { 
            $refs.name?.focus();
            $wire.lastAddedBranchIndex = null; // reset
        }
    })"
>
     <td class="border border-gray-300 p-3 border-l-0">
        <input type="text" x-model="branchName" x-ref="name" placeholder="Branch Name"
               class="w-full  rounded px-2 py-1 focus:ring-red-500 focus:border-red-500"/>
        <span class="text-red-500 text-xs mt-1 block min-h-[1.25rem]">
            @error('branches.' . $index . '.name') {{ $message }} @enderror
        </span>
    </td>

   <td class="border border-gray-300 p-3">
        <input type="text" x-model="branchAddress" x-ref="address" placeholder="Address"
               class="w-full  rounded px-2 py-1 focus:ring-red-500 focus:border-red-500"/>
        <span class="text-red-500 text-xs mt-1 block min-h-[1.25rem]">
            @error('branches.' . $index . '.address') {{ $message }} @enderror
        </span>
    </td>

    <td class="border border-gray-300 p-3">
<input type="text"
       wire:model.lazy="branches.{{ $index }}.zip"
       placeholder="Zip code"
       maxlength="10"
       class="w-full border  px-2 py-1 focus:ring-red-500 focus:border-red-500"/>
   <span class="text-red-500 text-xs mt-1 block min-h-[1.25rem]">  
			@error('branches.' . $index . '.zip') {{ $message }} @enderror 
		</span> 
    
      
      
    </td>

    <td class="border border-gray-300 p-3">
        <input type="text" x-model="branchCity" x-ref="city" placeholder="City"
               class="w-full  rounded px-2 py-1 focus:ring-red-500 focus:border-red-500"/>
        <span class="text-red-500 text-xs mt-1 block min-h-[1.25rem]">
            @error('branches.' . $index . '.city') {{ $message }} @enderror
        </span>
    </td>

    <td class="border border-gray-300 p-3">
        <input type="text" x-model="branchState" x-ref="state" placeholder="State"
               class="w-full  rounded px-2 py-1 focus:ring-red-500 focus:border-red-500"/>
        <span class="text-red-500 text-xs mt-1 block min-h-[1.25rem]">
            @error('branches.' . $index . '.state') {{ $message }} @enderror
        </span>
    </td>

    <td class="border border-gray-300 p-3">
        <input type="text" x-model="branchCountry" x-ref="country" placeholder="Country"
               class="w-full rounded px-2 py-1 focus:ring-red-500 focus:border-red-500"/>
        <span class="text-red-500 text-xs mt-1 block min-h-[1.25rem] ">
            @error('branches.' . $index . '.country') {{ $message }} @enderror
        </span>
    </td>

    <td class=" p-3 text-center block   border-r-0">
        <button type="button" wire:click="removeBranch({{ $index }})" class="text-red-600 hover:text-red-800 font-bold ">Remove</button>
    </td>
</tr>
@endforeach
</tbody>


        </table>

    </div>
@endif


    {{-- Form Actions --}}
    <div class="flex gap-4 justify-start mt-6">
        <button type="button" wire:click="addBranch" class="border border-[#EB1C24] text-white px-6 py-3 rounded-[8px] flex items-center  hover:bg-red-700 text-[#EB1C24] font-[500] btn-hover hover:text-[#fff]" style="line-height: normal;">
            <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
                <path d="M8 1.5V15.5" stroke="#EB1C24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M1 8.5H15" stroke="#EB1C24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                 Add Branch Office
        </button>
        <button type="submit" class="bg-[#EB1C24] text-white px-6 py-3 rounded shadow hover:bg-red-700 btn-hover rounded-[8px] hover:text-[#fff]">Save</button>   
        <button type="button" wire:click="resetOfficeForm" class="text-[16px] text-[#808080] px-2">Reset All</button>
    </div>

</form>

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
            let city='', state='', country='', zip='';
            (place.address_components || []).forEach(c => {
                if(c.types.includes('locality')) city=c.long_name;
                if(c.types.includes('administrative_area_level_1')) state=c.long_name;
                if(c.types.includes('country')) country=c.long_name;
                if(c.types.includes('postal_code')) zip=c.long_name;
            });

            // Fill inputs
            document.getElementById('officeCity').value = city;
            document.getElementById('officeState').value = state;
            document.getElementById('officeCountry').value = country;
            document.getElementById('officeZip').value = zip;

            // Trigger Livewire bindings
            ['officeCity','officeState','officeCountry','officeZip'].forEach(id => {
                let input = document.getElementById(id);
                if(input){
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            // Force update Livewire props using @this in Alpine context
            // The dispatchEvent above should handle wire:model bindings
            // If needed, use Livewire.find() with component ID
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

function branchRow({ index, branchId, branchName, branchAddress, branchCity, branchState, branchZip, branchCountry }) {
    return {
        index,
        branchId,
        branchName,
        branchAddress,
        branchCity,
        branchState,
        branchZip,
        branchCountry,
        isOpen: true, // <-- add this
        autocomplete: null,
        async init() {
            // Restore values from localStorage
            this.branchId = localStorage.getItem(`branch_${index}_id`) || this.branchId || '';
            this.branchName = localStorage.getItem(`branch_${index}_name`) || this.branchName || '';
            this.branchAddress = localStorage.getItem(`branch_${index}_address`) || this.branchAddress || '';
            this.branchCity = localStorage.getItem(`branch_${index}_city`) || this.branchCity || '';
            this.branchState = localStorage.getItem(`branch_${index}_state`) || this.branchState || '';
            this.branchZip = localStorage.getItem(`branch_${index}_zip`) || this.branchZip || '';
            this.branchCountry = localStorage.getItem(`branch_${index}_country`) || this.branchCountry || '';

            // Agar branch me already data hai, to open rakho
            if(this.branchName || this.branchAddress || this.branchCity || this.branchState || this.branchZip || this.branchCountry){
                this.isOpen = true;
            }

            ['branchId','branchName','branchAddress','branchCity','branchState','branchZip','branchCountry'].forEach(field=>{
                this.$watch(field, value=>{
                    if(value) localStorage.setItem(`branch_${index}_${field.replace('branch','').toLowerCase()}`, value);
                    else localStorage.removeItem(`branch_${index}_${field.replace('branch','').toLowerCase()}`);
                });
            });

            // Google Places autocomplete
            await waitForGooglePlaces();
            const input = this.$refs.address;
            if (!input || input._autocompleteBound) return;
            input._autocompleteBound = true;

            this.autocomplete = new google.maps.places.Autocomplete(input, { types: ['establishment'] });
            this.autocomplete.addListener('place_changed', () => {
                const place = this.autocomplete.getPlace();
                const parts = { city:'', state:'', country:'', zip:'' };
                (place.address_components||[]).forEach(c=>{
                    if(c.types.includes('locality')) parts.city = c.long_name;
                    if(c.types.includes('administrative_area_level_1')) parts.state = c.long_name;
                    if(c.types.includes('country')) parts.country = c.long_name;
                    if(c.types.includes('postal_code')) parts.zip = c.long_name;
                });

                this.branchCity = parts.city;
                this.branchState = parts.state;
                this.branchCountry = parts.country;
                this.branchZip = parts.zip;

                ['branchCity','branchState','branchCountry','branchZip'].forEach(k=>{
                    $wire.set(`branches.${this.index}.${k.replace('branch','').toLowerCase()}`, this[k]);
                });
            });
        }
    }
}

  document.addEventListener("livewire:init", () => {
        Livewire.on("remove-branch-local", (data) => {
            const index = data.index;

            // sabhi keys delete karo jo branch ke index se related hain
            localStorage.removeItem(`branch_${index}_id`);
            localStorage.removeItem(`branch_${index}_name`);
            localStorage.removeItem(`branch_${index}_address`);
            localStorage.removeItem(`branch_${index}_city`);
            localStorage.removeItem(`branch_${index}_state`);
            localStorage.removeItem(`branch_${index}_zip`);
            localStorage.removeItem(`branch_${index}_country`);
        });
    });
  function initTelInput(input, livewire, phoneField, countryField) {
        let iti = window.intlTelInput(input, {
            initialCountry: "auto",
            separateDialCode: true,
            geoIpLookup: function (callback) {
                fetch("https://ipapi.co/json")
                    .then(res => res.json())
                    .then(data => callback(data.country_code))
                    .catch(() => callback("us"));
            },
            utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js",
        });

        function updateValues() {
            let phoneNumber = input.value.replace(/\D/g, '');
            let countryCode = iti.getSelectedCountryData().dialCode;

            livewire.set(phoneField, phoneNumber);
            livewire.set(countryField, `+${countryCode}`);
        }

        input.addEventListener("input", updateValues);
        input.addEventListener("countrychange", updateValues);

        updateValues();
    }
</script>
</div>
