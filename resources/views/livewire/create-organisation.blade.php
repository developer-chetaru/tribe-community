<div 
  x-data="{ 
      organisationId: @entangle('organisationId'), 
      officeId: @entangle('officeId'), 
      activeTab: @entangle('activeTab') 
  }"
  x-init="
      $nextTick(() => {
        
          organisationId = localStorage.getItem('organisation_id') || organisationId;
          officeId       = localStorage.getItem('office_id') || officeId;

          const urlParams = new URLSearchParams(window.location.search);
          const urlTab = urlParams.get('tab');

          if (urlTab) {
              activeTab = urlTab;
              localStorage.setItem('active_tab', urlTab);
          } else {
              activeTab = localStorage.getItem('active_tab') || 'organisation';
          }

        
          if (organisationId) {
              $wire.set('organisationId', organisationId).then(() => $wire.loadOrganisation(organisationId));
          }
          if (officeId) {
              $wire.set('officeId', officeId).then(() => $wire.loadOffice(officeId));
          }
          if (activeTab) {
              $wire.set('activeTab', activeTab);
          }
      });

   
      $watch('organisationId', value => {
          if(value){
              localStorage.setItem('organisation_id', value);
          }
      });
      $watch('officeId', value => {
          if(value){
              localStorage.setItem('office_id', value);
          }
      });
      $watch('activeTab', value => {
          if(value){
              localStorage.setItem('active_tab', value);

              // Saath hi URL param bhi update karo
              const url = new URL(window.location);
              url.searchParams.set('tab', value);
              window.history.replaceState({}, '', url);
          }
      });

  
      window.addEventListener('save-org-tab', e => {
          if(e.detail.tab){
              activeTab = e.detail.tab;
          }
      });
  " class=""
>


  <div class="text-[24px] text-[#EB1C24] font-semibold text-[#EB1C24] mb-6">Add Organisation</div>

    <form wire:submit.prevent="saveOrganisation" class="space-y-6">

        <!-- Logo Upload -->
        <div x-data="{ preview: @entangle('logoPreview') || null }"  class="flex flex-col md:flex-row items-center md:items-stretch max-w-[460px] bg-[#F8F8F8] border border-[#808080] overflow-hidden rounded-[10px] "> 
            <label class="w-32  h-28 flex flex-col items-center justify-center  border-r border-[#808080] border-gray-200 bg-[#faf7fa] cursor-pointer relative overflow-hidden bg-[#FFEFF0] p-2">  
                <template x-if="preview"> 
                    <img :src="preview" alt="Preview" class="w-full ">
                </template>
                <template x-if="!preview">
                    <img src="{{ asset('images/logo-upload-icon.png') }}" alt="Upload Icon" class="w-full opacity-70">
                </template>
                <input type="file" x-ref="fileInput" wire:model="logo" accept="image/*"
                    @change="preview = $event.target.files.length ? URL.createObjectURL($event.target.files[0]) : preview"
                    class="absolute inset-0 opacity-0 cursor-pointer">
            </label>

            <div class="flex-1  flex flex-col items-center justify-center  py-5 cursor-pointer hover:bg-gray-50 transition bg-[#F8F8F8]"
                @click="$refs.fileInput.click()">
                <img src="{{ asset('images/image-icon-logo.svg') }}" alt="Image Icon" class="w-[20px] opacity-70 mb-2">
                <div class="font-medium text-black mb-1 text-[16px] text-[#020202] font-[600]">Upload Logo</div>
                <div class="text-[12px] text-[#808080] text-gray-400">Drag and drop or browse your files</div>
            </div>
        </div>

          @error('logo') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
        <!-- Form Fields -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <input type="text" wire:model.live="organisationName" placeholder="Organisation Name"
                       class="border rounded-[8px] px-3 py-2.5 w-full focus:ring-red-500 focus:border-red-500 placeholder-black">
                @error('organisationName') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
            <div>
                <div
                    class="relative w-full"
                    wire:ignore
                    x-data="phoneInputComponent(@this)"
                    x-init="init()"
                >
                    <input
                        x-ref="phoneInput"
                        type="number"
                        placeholder="Phone Number"
                        class="phone-field border rounded-[8px] px-3 py-2.5 w-full
                            focus:ring-red-500 focus:border-red-500 placeholder-black"
                    >
                </div>

                {{-- validation message OUTSIDE wire:ignore --}}
                @error('phoneNumber')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                @enderror
                @error('country_code')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                @enderror
            </div>


            <div>
              <select wire:model.live="indus" class="border text-black invalid:text-gray-400 rounded-md px-3 py-2.5 w-full focus:ring-red-500 focus:border-red-500">
    			<option value="" disabled selected hidden>Select Industry</option>
    				@foreach($allIndustry as $ind)
        				<option value="{{ $ind->id }}">
            				{{ $ind->name }}
        				</option>
    				@endforeach
				<option value="other">Other</option>
			</select>

		@if($indus === 'other')
    		<input type="text" wire:model="otherIndustry" placeholder="Enter Other Industry" class="mt-2 border rounded-md px-3 py-2.5 w-full focus:ring-red-500 focus:border-red-500">
 @error('otherIndustry')
            <span class="text-red-500 text-sm">{{ $message }}</span>
        @enderror	
	@endif

                @error('industry') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
            <div >
                <select wire:model.live="turnover"  class="border  text-black invalid:text-gray-200  rounded rounded-[8px] px-3 py-2.5 w-full focus:ring-red-500 focus:border-red-500">
                    <option value="">Select Turnover</option>
                    <option  value="<1Cr">&lt; 1 Cr</option>
                    <option value="1-10Cr">1-10 Cr</option>
                    <option value="10+Cr">10+ Cr</option>
                    <option value="50+Cr">50+Cr</option>
                </select>
                @error('turnover') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
          <div>
    <input 
        type="text" 
        wire:model.live="founded_year" 
        placeholder="Year Founded"
        class="border rounded px-3 py-2.5 w-full text-black focus:ring-red-500 rounded-[8px] focus:border-red-500 placeholder-black"
        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4)"
    >
    @error('founded_year') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
</div>

            <div>
                <input type="text" wire:model.live="website" placeholder="Website URL"
                       class="border rounded-[8px] px-3 py-2.5 w-full rounded-[8px] focus:ring-red-500 focus:border-red-500 placeholder-black">
                @error('website') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
          
    <div>
           <select wire:model.live="profile_visibility" 
        class="border rounded-[8px] px-3 py-2.5 w-full text-black invalid:text-gray-400  focus:ring-red-500 focus:border-red-500 text-black">
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
                            class="px-4 py-2.5 rounded border border-[#808080] transition rounded-[8px] bg-[#F8F9FA]"
                            :class="{{ in_array($day, $workingDays) ? '\'bg-[#ff2323] text-white\'' : '\'bg-white text-[#191919]\'' }}">
                        {{ $day }}
                    </button>
                @endforeach
            </div>
            @error('workingDays') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-4">
            <button type="submit"
                    class="bg-[#EB1C24] text-white rounded-[8px] px-8 py-2.5 font-[500] text-[16px]  transition">
                Save & Next
            </button>
          <button type="button"
    class=" text-[#808080] text-[14px] font-[400] px-2 py-2.5  hover:bg-gray-100 transition"
    wire:click="resetForm">
    Reset All
</button>

        </div>
    </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.21/js/intlTelInput.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.21/js/utils.js"></script>

<style>
    .iti { 
        width: 100% !important; 
    }

    /* Make room for flag + dial code so placeholder doesn't sit under it */
    .iti--separate-dial-code input.phone-field {
        padding-left: 90px !important; /* adjust if you want more/less */
    }

    .iti--allow-dropdown .iti__selected-flag {
        background-color: #F8F8F8;
        padding-left: 10px !important;
        height: 100%;
    }
</style>
<script>
    function phoneInputComponent(livewire) {
        return {
            iti: null,

            init() {
                this.iti = window.intlTelInput(this.$refs.phoneInput, {
                    initialCountry: "auto",
                    separateDialCode: true,
                    utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js",
                    geoIpLookup: (callback) => {
                        fetch("https://ipapi.co/json")
                            .then(res => res.json())
                            .then(data => callback(data.country_code))
                            .catch(() => callback("us"));
                    },
                });

                const updateValues = () => {
                    const countryData = this.iti.getSelectedCountryData();
                    const dialCode    = countryData.dialCode ? '+' + countryData.dialCode : '';
                    const fullNumber  = this.iti.getNumber(); // e.g. +254712345678

                    // local part only (without +country code)
                    const localNumber = fullNumber
                        .replace(dialCode, '')
                        .replace(/^\+/, '');

                    // send clean values to Livewire
                    livewire.set('country_code', dialCode);
                    livewire.set('phoneNumber', localNumber);
                };

                this.$refs.phoneInput.addEventListener('input', updateValues);
                this.$refs.phoneInput.addEventListener('countrychange', updateValues);

                // initial sync
                updateValues();
            }
        }
    }
</script>

