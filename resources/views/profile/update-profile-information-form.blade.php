    <x-slot name="header">
        <h2 class="text-2xl font-bold capitalize text-[#ff2323]">
           profile
        </h2>
    </x-slot>

<x-form-section submit="updateProfileInformation">
    <x-slot name="title">
        Account Information
    </x-slot>

    <x-slot name="description">
        {{-- <hr> --}}
    </x-slot>

    <x-slot name="form">

      <!-- Profile Photo -->
        {{-- Profile Photo Upload --}}
        @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
        <div class="flex justify-center mb-6 col-span-6 sm:col-span-6">
          <div x-data="{ photoPreview: null }" class="relative">

            {{-- Current Profile Photo / New Preview --}}
            <template x-if="photoPreview">
              <img :src="photoPreview" class="h-24 w-24 rounded-full object-cover" alt="New Profile Photo Preview">
            </template>
            <template x-if="!photoPreview">
              <img class="h-24 w-24 rounded-full object-cover" src="{{ $this->user->profile_photo_url }}" alt="{{ $this->user->name }}">
            </template>

            {{-- Upload Button --}}
      <label for="photo"
       class="absolute bottom-0 right-0 bg-red-600 p-1 rounded-full cursor-pointer hover:bg-red-700 transition duration-200">
    <img src="{{ asset('images/pencil1.png') }}" alt="Edit" class="w-4 h-4">
</label>

            {{-- File Input --}}
            <input id="photo" type="file" class="hidden" wire:model="photo"
                   x-on:change="
                                const file = $event.target.files[0];
                                if (file) {
                                const reader = new FileReader();
                                reader.onload = (e) => { photoPreview = e.target.result; };
                                reader.readAsDataURL(file);
                                }
                                ">

          </div>

          {{-- Error --}}
          @error('photo') <span class="text-red-600 text-sm mt-1">{{ $message }}</span> @enderror
        </div>
        @endif

  <div class="col-span-6 sm:col-span-6">
            <x-action-message on="saved" type="success">
                Profile updated successfully!
            </x-action-message>
            <x-action-message on="error" type="error">
                Something went wrong.
            </x-action-message>
        </div>
        <!-- First Name -->
        <div class="col-span-6 sm:col-span-3">
            <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
            <input type="text" id="first_name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500" wire:model.defer="state.first_name" autocomplete="name">
            <x-input-error for="first_name" class="mt-2" />
        </div>

        <!-- Last Name -->
        <div class="col-span-6 sm:col-span-3">
            <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
            <input type="text" id="last_name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500" wire:model.defer="state.last_name" autocomplete="last_name">
            <x-input-error for="last_name" class="mt-2" />
        </div>

		<!-- Phone Number -->
        <div class="col-span-6 sm:col-span-3">
            <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>

            <div wire:ignore>
                <div x-data="{}" 
                    x-init="
                        setTimeout(() => {
                            initTelInput(
                                $refs.phoneInput,
                                @this,
                                'state.phone',
                                'state.country_code',
                                @js($state['phone'] ?? ''),
                                @js($state['country_code'] ?? '+91')
                            );
                        }, 100);
                    "
                    style="width: 100% !important;">
                    
                    <input
                        x-ref="phoneInput"
                        id="phone"
                        type="tel"
                        placeholder="Phone Number"
                        class="border rounded-[8px] px-3 py-2.5 w-full focus:ring-red-500 focus:border-red-500 border border-[#80808080]"
                    >
                </div>

                <!-- Hidden field for country_code -->
                <input type="hidden" wire:model.defer="state.country_code">

                @error('phone') 
                    <span class="text-red-500 text-sm">{{ $message }}</span> 
                @enderror
                @error('country_code') 
                    <span class="text-red-500 text-sm">{{ $message }}</span> 
                @enderror
            </div>
        </div>

        <!-- Email -->
        <div class="col-span-6 sm:col-span-3">
            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
            <input type="email" id="email" class="mt-1 block w-full bg-gray-100 border-gray-300 rounded-md shadow-sm cursor-not-allowed" wire:model.defer="state.email" disabled />
            <x-input-error for="email" class="mt-2" />
        </div>

        <!-- Timezone -->
        @php
            $tzToCountry = [
                'Asia/Kolkata' => 'IN', 'Asia/Dubai' => 'AE', 'Asia/Singapore' => 'SG',
                'Asia/Tokyo' => 'JP', 'Asia/Shanghai' => 'CN', 'Asia/Hong_Kong' => 'HK',
                'Asia/Bangkok' => 'TH', 'Asia/Jakarta' => 'ID', 'Asia/Manila' => 'PH',
                'Asia/Seoul' => 'KR', 'Asia/Kuala_Lumpur' => 'MY',
                'Europe/London' => 'GB', 'Europe/Paris' => 'FR', 'Europe/Berlin' => 'DE',
                'Europe/Rome' => 'IT', 'Europe/Madrid' => 'ES', 'Europe/Amsterdam' => 'NL',
                'Europe/Brussels' => 'BE', 'Europe/Vienna' => 'AT', 'Europe/Zurich' => 'CH',
                'Europe/Stockholm' => 'SE', 'Europe/Oslo' => 'NO', 'Europe/Copenhagen' => 'DK',
                'Europe/Helsinki' => 'FI', 'Europe/Warsaw' => 'PL', 'Europe/Prague' => 'CZ',
                'Europe/Budapest' => 'HU', 'Europe/Athens' => 'GR', 'Europe/Lisbon' => 'PT',
                'Europe/Dublin' => 'IE',
                'America/New_York' => 'US', 'America/Chicago' => 'US', 'America/Denver' => 'US',
                'America/Los_Angeles' => 'US', 'America/Toronto' => 'CA', 'America/Vancouver' => 'CA',
                'America/Mexico_City' => 'MX', 'America/Sao_Paulo' => 'BR', 'America/Buenos_Aires' => 'AR',
                'America/Santiago' => 'CL', 'America/Lima' => 'PE', 'America/Bogota' => 'CO',
                'Australia/Sydney' => 'AU', 'Australia/Melbourne' => 'AU', 'Australia/Brisbane' => 'AU',
                'Australia/Perth' => 'AU', 'Australia/Adelaide' => 'AU',
                'Pacific/Auckland' => 'NZ',
                'Africa/Cairo' => 'EG', 'Africa/Johannesburg' => 'ZA', 'Africa/Lagos' => 'NG',
                'Africa/Nairobi' => 'KE',
            ];
            $timezones = collect(timezone_identifiers_list())->map(function($tz) use ($tzToCountry) {
                $countryCode = $tzToCountry[$tz] ?? '';
                $parts = explode('/', $tz);
                $city = str_replace('_', ' ', end($parts));
                $display = $countryCode ? $countryCode . ' - ' . $city . ' (' . $tz . ')' : $tz;
                return ['value' => $tz, 'display' => $display, 'search' => strtolower($tz . ' ' . $city . ' ' . ($countryCode ?? ''))];
            })->values()->all();
            $currentTz = $this->user->timezone ?? '';
            $currentDisplay = collect($timezones)->firstWhere('value', $currentTz)['display'] ?? $currentTz;
        @endphp
        <div class="col-span-6 sm:col-span-3" x-data="{
            search: '',
            showSuggestions: false,
            selectedTimezone: @js($currentTz),
            timezones: @js($timezones),
            get filteredTimezones() {
                if (!this.search || this.search.length < 1) return [];
                const query = this.search.toLowerCase();
                return this.timezones.filter(tz => tz.search.includes(query)).slice(0, 15);
            },
            get displayValue() {
                if (!this.selectedTimezone) return '';
                const tz = this.timezones.find(t => t.value === this.selectedTimezone);
                return tz ? tz.display : this.selectedTimezone;
            },
            selectTimezone(tz) {
                this.selectedTimezone = tz.value;
                this.search = '';
                this.showSuggestions = false;
                @this.set('state.timezone', tz.value);
            },
            clearTimezone() {
                this.selectedTimezone = '';
                this.search = '';
                this.showSuggestions = false;
                @this.set('state.timezone', '');
            },
            init() {
                // Update Livewire when timezone changes
                this.$watch('selectedTimezone', value => {
                    @this.set('state.timezone', value);
                });
            }
        }">
            <label for="timezone" class="block text-sm font-medium text-gray-700">Timezone</label>
            <div class="relative mt-1">
                <div class="relative">
                    <input 
                        type="text"
                        :value="search || displayValue"
                        @input="search = $event.target.value; showSuggestions = search.length > 0"
                        @focus="if(!search) { search = ''; } showSuggestions = search.length > 0"
                        @click.away="showSuggestions = false; if(!search) search = '';"
                        @keydown.escape="showSuggestions = false; search = '';"
                        placeholder="Type to search timezone..."
                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 pr-8"
                    />
                    <button 
                        type="button"
                        x-show="selectedTimezone || search"
                        @click="clearTimezone()"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none"
                        title="Clear timezone"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <input type="hidden" wire:model.defer="state.timezone" x-model="selectedTimezone">
                
                <div x-show="showSuggestions && filteredTimezones.length > 0" 
                     x-cloak
                     x-transition
                     class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto">
                    <template x-for="tz in filteredTimezones" :key="tz.value">
                        <div @click="selectTimezone(tz)" 
                             class="px-4 py-2 hover:bg-gray-100 cursor-pointer border-b border-gray-100 last:border-b-0"
                             :class="{'bg-red-50': selectedTimezone === tz.value}">
                            <span x-text="tz.display"></span>
                        </div>
                    </template>
                </div>
                <div x-show="showSuggestions && search.length > 0 && filteredTimezones.length === 0" 
                     x-cloak
                     class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg p-4 text-center text-gray-500">
                    No timezones found
                </div>
            </div>
            <x-input-error for="timezone" class="mt-2" />
        </div>

		
@if(Auth::user()->organisation)
    <div class="col-span-6 sm:col-span-4">
        <x-label value="{{ __('Organisation') }}" />

        <div class="flex items-center p-4 mt-2">
            @if(Auth::user()->organisation->image)
                <img class="h-14 w-14 rounded-full object-cover border border-gray-300"
                     src="{{ asset('storage/' . Auth::user()->organisation->image) }}"
                     alt="{{ Auth::user()->organisation->name }}">
            @else
                <div class="h-14 w-14 flex items-center justify-center rounded-full bg-gray-200 text-gray-500 text-sm">
                    No Logo
                </div>
            @endif

            <div class="ml-4">
                <p class="text-base font-semibold text-gray-900">
                    {{ Auth::user()->organisation->name }}
                </p>
           
            </div>
        </div>
    </div>

	<!-- Office Name -->
        <div class="col-span-6 sm:col-span-3">
            <label class="block text-sm font-medium text-gray-700">Office Name</label>
            <input type="text" 
                value="{{ $this->user->office?->name ?? 'N/A' }}" 
                class="mt-1 block w-full bg-gray-100 border-gray-300 rounded-md shadow-sm cursor-not-allowed"
                readonly>
        </div>

        <!-- Department Name -->
      <div class="col-span-6 sm:col-span-3">
          <label class="block text-sm font-medium text-gray-700">Department Name</label>
          <input type="text" 
                 value="{{ $this->user->department?->allDepartment?->name ?? 'N/A' }}" 
                 class="mt-1 block w-full bg-gray-100 border-gray-300 rounded-md shadow-sm cursor-not-allowed"
                 readonly>
      </div>
@endif


        

    </x-slot>

    <x-slot name="actions">
        <x-button class="bg-red-600 hover:bg-red-700">
            {{ __('Save') }}
        </x-button>
    </x-slot>
</x-form-section>
<!-- Include intl-tel-input JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.21/js/intlTelInput.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.21/js/utils.js"></script>

<script>
function initTelInput(input, livewire, phoneField, countryField, initialPhone = '', initialCountryCode = null) {
    const initialDialCode = (initialCountryCode || '+91').replace('+', '');
    let initialIsoCode = 'in';
    let useGeoIp = true;

    if (initialCountryCode) {
        useGeoIp = false;
        if (window.intlTelInputGlobals) {
            try {
                const countryDataList = window.intlTelInputGlobals.getCountryData();
                const foundCountry = countryDataList.find(country => country.dialCode === initialDialCode);
                if (foundCountry) {
                    initialIsoCode = foundCountry.iso2;
                }
            } catch (e) {
                console.error('Failed to resolve ISO code for stored dial code', e);
            }
        }
    }

    let iti = window.intlTelInput(input, {
        initialCountry: useGeoIp ? "auto" : initialIsoCode,
        separateDialCode: true,
        geoIpLookup: useGeoIp ? function (callback) {
            fetch("https://ipapi.co/json")
                .then(res => res.json())
                .then(data => callback(data.country_code))
                .catch(() => callback("in"));
        } : undefined,
        utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.21/js/utils.js",
    });

    function updateValues() {
        let phoneNumber = iti.isValidNumber()
            ? iti.getNumber(intlTelInputUtils.numberFormat.E164)
            : input.value;

        const countryData = iti.getSelectedCountryData();
        const dialCode = countryData.dialCode || initialDialCode || '91';
        let justPhone = phoneNumber.startsWith(`+${dialCode}`)
            ? phoneNumber.replace(`+${dialCode}`, '')
            : input.value;

        livewire.set(phoneField, justPhone.replace(/\D/g, ''));
        livewire.set(countryField, `+${dialCode}`);
    }

    if (initialPhone || initialCountryCode) {
        const fullNumber = `${initialCountryCode || '+91'}${initialPhone}`.replace(/\s/g, '');

        setTimeout(() => {
            if (fullNumber.replace('+', '').trim().length) {
                iti.setNumber(fullNumber);
            }
            updateValues();
        }, 50);
    } else {
        updateValues();
    }

    input.addEventListener("input", updateValues);
    input.addEventListener("countrychange", updateValues);
}
</script>

