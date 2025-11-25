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
                                @this
                            );
                        }, 100);
                    "
                    style="width: 100% !important;">
                    
                    <input
                        x-ref="phoneInput"
                        id="phone"
                        type="tel"
                        placeholder="Phone Number"
                        value="{{ $state['phone'] ?? '' }}"
                        data-country="{{ str_replace('+', '', $state['country_code'] ?? '91') }}"
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
function initTelInput(input, livewire) {
    let initialCountry = "auto";

    // Use data attribute if available
    if (input.dataset.country) {
        const countryCode = input.dataset.country;
        const countryDataList = window.intlTelInputGlobals.getCountryData();
        const foundCountry = countryDataList.find(c => c.dialCode === countryCode);
        if (foundCountry) {
            initialCountry = foundCountry.iso2;
        }
    }

    let iti = window.intlTelInput(input, {
        initialCountry: initialCountry,
        separateDialCode: true,
        geoIpLookup: function (callback) {
            fetch("https://ipapi.co/json")
                .then(res => res.json())
                .then(data => callback(data.country_code))
                .catch(() => callback("us"));
        },
        utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.21/js/utils.js",
    });

    function updateValues() {
        let phoneNumber = input.value;
        let countryCode = iti.getSelectedCountryData().dialCode;

        livewire.set('state.phone', phoneNumber);
        livewire.set('state.country_code', `+${countryCode}`);
    }

    input.addEventListener("input", updateValues);
    input.addEventListener("countrychange", updateValues);

    updateValues();
}
</script>

