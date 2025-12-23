<x-slot name="header">
    <h2 class="text-[24px] md:text-[30px] font-[600] capitalize text-[#EB1C24]">
        Edit Basecamp User
    </h2>
</x-slot>

<div class="flex-1 overflow-auto">
    <div class="">

        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <a href="{{ route('basecampuser') }}" 
                class="bg-[#fff] px-5 py-3 rounded-md shadow hover:bg-[#c71313] text-sm hover:text-white transition inline-flex items-center back-btn">
                <svg width="7" height="11" viewBox="0 0 7 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
                    <path d="M6 10.5C6 10.5 1 6.81758 1 5.5C1 4.18233 6 0.5 6 0.5" stroke="#808080" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Back to Basecamp Users
            </a>
        </div>

        <!-- Flash Message -->
        @if (session()->has('message'))
            <div 
                x-data="{ show: true }" 
                x-init="setTimeout(() => show = false, 3000)" 
                x-show="show"
                x-transition.duration.500ms
                class="mb-8 px-6 py-4 text-white text-base font-medium rounded-xl shadow-lg
                {{ session('type') === 'success' ? 'bg-green-600' : 'bg-red-600' }}">
                {{ session('message') }}
            </div>
        @endif

        <!-- Card -->
        <div class="bg-white shadow-md border border-gray-200 rounded-lg p-6">
            
            <!-- Title -->
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-[#EB1C24] font-medium text-[24px]">User Details</h2>
            </div>

            <!-- Profile Photo -->
            <div class="mb-6 flex flex-col items-center" x-data="imagePreview()">
                <div class="relative w-24 h-24">
                    <img 
                        :src="previewUrl || '{{ $existingPhoto ? asset('storage/' . $existingPhoto) : asset('images/default-user-red.svg') }}'" 
                        class="w-24 h-24 rounded-full object-cover border-2 border-gray-300"
                        alt="Profile Preview"
                    >
                    <label class="absolute bottom-0 right-0 w-7 h-7 bg-red-500 rounded-full flex items-center justify-center cursor-pointer border-2 border-white shadow-md hover:bg-red-600 transition-colors">
                        <img src="https://console-tribe.nativeappdev.com/images/pencil1.png" alt="Edit" class="w-4 h-4">
                        <input type="file" class="hidden" wire:model="profile_photo" @change="showPreview($event)" accept="image/*">
                    </label>
                </div>
                @error('profile_photo')
                    <span class="text-red-500 text-xs mt-2 block text-center">{{ $message }}</span>
                @enderror
            </div>

            <script>
            function imagePreview() {
                return {
                    previewUrl: null,
                    showPreview(event) {
                        const file = event.target.files[0];
                        if (!file) return;
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            this.previewUrl = e.target.result;
                        };
                        reader.readAsDataURL(file);
                    }
                }
            }
            </script>

            <form wire:submit.prevent="saveUser">
                <!-- Form Fields -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    
                    {{-- First Name --}}
                    <div>
                        <input type="text" 
                               placeholder="Enter first name"
                               wire:model="first_name"
                               class="border border-gray-300 focus:ring-red-500 focus:border-red-500 rounded-md px-3 py-2 w-full outline-none">
                        @error('first_name') 
                            <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> 
                        @enderror
                    </div>

                    {{-- Last Name --}}
                    <div>
                        <input type="text" 
                               placeholder="Enter last name"
                               wire:model="last_name"
                               class="border border-gray-300 focus:ring-red-500 focus:border-red-500 rounded-md px-3 py-2 w-full outline-none">
                        @error('last_name') 
                            <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> 
                        @enderror
                    </div>

                    {{-- Email --}}
                    <div>
                        <input 
                            type="email"
                            placeholder="Enter email address"
                            wire:model="email"
                            class="border border-gray-300 focus:ring-red-500 focus:border-red-500 
                                   rounded-md px-3 py-2 w-full outline-none"
                        />
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
                                        $refs.basecampPhoneInput,
                                        @this,
                                        'phone',
                                        'country_code',
                                        @js($phone ?? ''),
                                        @js($country_code ?? '+1')
                                    )
                                "
                                style="width: 100% !important;">
                                
                                <input
                                    x-ref="basecampPhoneInput"
                                    type="tel"
                                    placeholder="Phone Number"
                                    class="border border-gray-300 focus:ring-red-500 focus:border-red-500 rounded-md px-3 py-2 w-full outline-none"
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

                    {{-- Status --}}
                    <div>
                        <select wire:model="status" 
                                class="border border-gray-300 focus:ring-red-500 focus:border-red-500 rounded-md px-3 py-2 w-full outline-none">
                            <option value="0">Unverified</option>
                            <option value="1">Verified</option>
                        </select>
                        @error('status') 
                            <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> 
                        @enderror
                    </div>

                </div>

                <!-- Buttons -->
                <div class="flex items-center mt-8">
                    <button type="submit"
                            class="bg-[#EB1C24] text-white text-[16px] px-5 py-3 rounded-[8px] font-[400] hover:bg-[#c71313] transition">
                        Update User
                    </button>
                    <button type="button" 
                            wire:click="resetForm" 
                            class="ml-3 text-[#808080] text-[16px] font-[400] hover:text-gray-900 transition">
                        Reset All
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
function initTelInput(input, livewire, phoneField, countryField, initialPhone = '', initialCountryCode = null) {
    
    const initialDialCode = (initialCountryCode || '+1').replace('+', '');
    let initialIsoCode = 'us';
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
                // Ignore error
            }
        }
    }

    let iti = window.intlTelInput(input, {
        initialCountry: useGeoIp ? "auto" : initialIsoCode, 
        separateDialCode: true,
        
        geoIpLookup: useGeoIp ? function(callback) {
            fetch("https://ipapi.co/json")
                .then(res => res.json())
                .then(data => callback(data.country_code))
                .catch(() => callback("us")); 
        } : undefined,

        utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js",
    });

    function updateValues() {
        let isValid = iti.isValidNumber();
        let phoneNumber = isValid ? iti.getNumber(intlTelInputUtils.numberFormat.E164) : ''; 
        
        let countryData = iti.getSelectedCountryData();
        let dialCode = countryData.dialCode || '1';
        let justPhone = '';

        if (phoneNumber.startsWith(`+${dialCode}`)) {
            justPhone = phoneNumber.replace(`+${dialCode}`, '');
        } else {
            justPhone = input.value;
        }

        livewire.set(phoneField, justPhone.replace(/\D/g, '')); 
        livewire.set(countryField, `+${dialCode}`);
    }

    if (initialPhone || initialCountryCode) {
        const fullNumber = (initialCountryCode + initialPhone).replace(/\s/g, '');
        
        setTimeout(() => {
            if (fullNumber) {
                iti.setNumber(fullNumber);
            }
            updateValues();
        }, 50); 
    } else {
         updateValues();
    }
    
    input.addEventListener('input', updateValues);
    input.addEventListener('countrychange', updateValues);
}
</script>

