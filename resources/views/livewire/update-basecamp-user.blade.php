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

                    {{-- Timezone --}}
                    @php
                        // Country code to country name mapping
                        $countryNames = [
                            'IN' => 'India', 'AE' => 'United Arab Emirates', 'SG' => 'Singapore',
                            'JP' => 'Japan', 'CN' => 'China', 'HK' => 'Hong Kong',
                            'TH' => 'Thailand', 'ID' => 'Indonesia', 'PH' => 'Philippines',
                            'KR' => 'South Korea', 'MY' => 'Malaysia',
                            'GB' => 'United Kingdom', 'FR' => 'France', 'DE' => 'Germany',
                            'IT' => 'Italy', 'ES' => 'Spain', 'NL' => 'Netherlands',
                            'BE' => 'Belgium', 'AT' => 'Austria', 'CH' => 'Switzerland',
                            'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark',
                            'FI' => 'Finland', 'PL' => 'Poland', 'CZ' => 'Czech Republic',
                            'HU' => 'Hungary', 'GR' => 'Greece', 'PT' => 'Portugal',
                            'IE' => 'Ireland',
                            'US' => 'United States', 'CA' => 'Canada',
                            'MX' => 'Mexico', 'BR' => 'Brazil', 'AR' => 'Argentina',
                            'CL' => 'Chile', 'PE' => 'Peru', 'CO' => 'Colombia',
                            'AU' => 'Australia',
                            'NZ' => 'New Zealand',
                            'EG' => 'Egypt', 'ZA' => 'South Africa', 'NG' => 'Nigeria',
                            'KE' => 'Kenya',
                            'PK' => 'Pakistan', 'NP' => 'Nepal', 'BD' => 'Bangladesh',
                            'LK' => 'Sri Lanka', 'VN' => 'Vietnam', 'TW' => 'Taiwan',
                            'IL' => 'Israel', 'SA' => 'Saudi Arabia', 'JO' => 'Jordan',
                            'KW' => 'Kuwait', 'QA' => 'Qatar', 'BH' => 'Bahrain',
                            'OM' => 'Oman', 'YE' => 'Yemen', 'IQ' => 'Iraq',
                            'IR' => 'Iran', 'AF' => 'Afghanistan',
                        ];
                        
                        $tzToCountry = [
                            'Asia/Kolkata' => 'IN', 'Asia/Dubai' => 'AE', 'Asia/Singapore' => 'SG',
                            'Asia/Tokyo' => 'JP', 'Asia/Shanghai' => 'CN', 'Asia/Hong_Kong' => 'HK',
                            'Asia/Bangkok' => 'TH', 'Asia/Jakarta' => 'ID', 'Asia/Manila' => 'PH',
                            'Asia/Seoul' => 'KR', 'Asia/Kuala_Lumpur' => 'MY',
                            'Asia/Karachi' => 'PK', 'Asia/Islamabad' => 'PK',
                            'Asia/Kathmandu' => 'NP',
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
                        
                        // Add more timezones for common countries - especially India
                        $allTimezones = timezone_identifiers_list();
                        foreach ($allTimezones as $tz) {
                            $parts = explode('/', $tz);
                            $continent = $parts[0] ?? '';
                            
                            // Auto-detect country for Asia timezones
                            if ($continent === 'Asia' && !isset($tzToCountry[$tz])) {
                                // India timezones
                                $indiaCities = ['Kolkata', 'Calcutta', 'Mumbai', 'Bombay', 'Delhi', 'New_Delhi', 
                                               'Chennai', 'Madras', 'Bangalore', 'Bangalore', 'Hyderabad', 
                                               'Pune', 'Ahmedabad', 'Jaipur', 'Lucknow', 'Kanpur'];
                                foreach ($indiaCities as $city) {
                                    if (strpos($tz, $city) !== false) {
                                        $tzToCountry[$tz] = 'IN';
                                        break;
                                    }
                                }
                                
                                // Pakistan timezones
                                if (!isset($tzToCountry[$tz])) {
                                    $pakistanCities = ['Karachi', 'Islamabad', 'Lahore', 'Rawalpindi', 'Quetta', 'Peshawar'];
                                    foreach ($pakistanCities as $city) {
                                        if (strpos($tz, $city) !== false) {
                                            $tzToCountry[$tz] = 'PK';
                                            break;
                                        }
                                    }
                                }
                                
                                // Nepal timezones
                                if (!isset($tzToCountry[$tz])) {
                                    $nepalCities = ['Kathmandu'];
                                    foreach ($nepalCities as $city) {
                                        if (strpos($tz, $city) !== false) {
                                            $tzToCountry[$tz] = 'NP';
                                            break;
                                        }
                                    }
                                }
                                
                                // Bangladesh timezones
                                if (!isset($tzToCountry[$tz])) {
                                    $bangladeshCities = ['Dhaka', 'Chittagong'];
                                    foreach ($bangladeshCities as $city) {
                                        if (strpos($tz, $city) !== false) {
                                            $tzToCountry[$tz] = 'BD';
                                            break;
                                        }
                                    }
                                }
                                
                                // Also check if it's the main India timezone
                                if ($tz === 'Asia/Kolkata' || $tz === 'Asia/Calcutta') {
                                    $tzToCountry[$tz] = 'IN';
                                }
                            }
                        }
                        
                        $timezones = collect(timezone_identifiers_list())->map(function($tz) use ($tzToCountry, $countryNames) {
                            try {
                                $dt = new \DateTime('now', new \DateTimeZone($tz));
                                $offset = $dt->getOffset();
                                $hours = intval(abs($offset) / 3600);
                                $minutes = intval((abs($offset) % 3600) / 60);
                                $sign = $offset >= 0 ? '+' : '-';
                                $utcOffset = sprintf('UTC%s%02d:%02d', $sign, $hours, $minutes);
                            } catch (\Exception $e) {
                                $utcOffset = '';
                            }
                            
                            $countryCode = $tzToCountry[$tz] ?? '';
                            $countryName = $countryNames[$countryCode] ?? '';
                            $parts = explode('/', $tz);
                            $continent = $parts[0] ?? '';
                            $city = str_replace('_', ' ', end($parts));
                            $city = ucwords(strtolower($city));
                            
                            // Standard display format: Country Code - City (UTC Offset) - IANA Identifier
                            if ($countryCode && $utcOffset) {
                                $display = $countryCode . ' - ' . $city . ' (' . $utcOffset . ') - ' . $tz;
                            } elseif ($countryCode) {
                                $display = $countryCode . ' - ' . $city . ' (' . $tz . ')';
                            } elseif ($utcOffset) {
                                $display = $city . ' (' . $utcOffset . ') - ' . $tz;
                            } else {
                                $display = $tz;
                            }
                            
                            // Enhanced search field includes: timezone, city, country code, country name, continent, UTC offset
                            $searchTerms = strtolower($tz . ' ' . $city . ' ' . ($countryCode ?? '') . ' ' . ($countryName ?? '') . ' ' . ($continent ?? '') . ' ' . ($utcOffset ?? ''));
                            
                            return [
                                'value' => $tz, 
                                'display' => $display, 
                                'search' => $searchTerms,
                                'countryCode' => $countryCode,
                                'countryName' => $countryName,
                                'continent' => $continent,
                                'city' => strtolower($city)
                            ];
                        })->values()->all();
                        $currentTz = $timezone ?? '';
                        $currentDisplay = collect($timezones)->firstWhere('value', $currentTz)['display'] ?? $currentTz;
                    @endphp
                    <div x-data="timezoneComponent(@entangle('timezone'))">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
                        <div class="relative">
                            <input 
                                type="text"
                                :value="search || displayValue"
                                @input="search = $event.target.value; showSuggestions = search.length > 0"
                                @focus="if(!search && selectedTimezone) { search = ''; } showSuggestions = search.length > 0"
                                @click.away="showSuggestions = false; if(!search && selectedTimezone) search = '';"
                                @keydown.escape="showSuggestions = false; search = '';"
                                placeholder="Type to search timezone..."
                                class="border border-gray-300 focus:ring-red-500 focus:border-red-500 rounded-md px-3 py-2 w-full outline-none pr-8"
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
                        @error('timezone') 
                            <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> 
                        @enderror
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
function timezoneComponent(livewireTimezone) {
    return {
        search: '',
        showSuggestions: false,
        selectedTimezone: '',
        livewireTimezone: livewireTimezone,
        timezones: @js($timezones ?? []),
        get filteredTimezones() {
            if (!this.search || this.search.length < 1) return [];
            const query = this.search.toLowerCase().trim();
            
            // Country name mapping for better search
            const countryMap = {
                'india': 'IN', 'indian': 'IN', 'ind': 'IN',
                'united states': 'US', 'usa': 'US', 'america': 'US', 'us': 'US',
                'united kingdom': 'GB', 'uk': 'GB', 'britain': 'GB',
                'china': 'CN', 'japan': 'JP', 'korea': 'KR',
                'australia': 'AU', 'canada': 'CA', 'germany': 'DE',
                'france': 'FR', 'spain': 'ES', 'italy': 'IT',
                'singapore': 'SG', 'thailand': 'TH', 'indonesia': 'ID',
                'philippines': 'PH', 'malaysia': 'MY', 'uae': 'AE',
                'dubai': 'AE', 'pakistan': 'PK', 'nepal': 'NP'
            };
            
            // Check if query is GMT/UTC offset
            let matchedOffset = null;
            const offsetMatch = query.match(/^(gmt|utc)?[\+\-]?(\d{1,2})(:(\d{2}))?$/i);
            if (offsetMatch) {
                const sign = query.includes('-') ? '-' : '+';
                const hours = parseInt(offsetMatch[2]) || 0;
                const minutes = parseInt(offsetMatch[4]) || 0;
                matchedOffset = `${sign}${hours}:${minutes.toString().padStart(2, '0')}`;
            }
            
            let matchedCountryCode = null;
            if (query === 'ind' || query === 'indi' || (query.length >= 3 && query.length <= 5 && query.startsWith('ind') && query !== 'indiana')) {
                matchedCountryCode = 'IN';
            } else if (!matchedOffset) {
                matchedCountryCode = countryMap[query];
            }
            
            const matchedContinent = query === 'asia' ? 'Asia' : 
                                   query === 'europe' ? 'Europe' :
                                   query === 'america' ? 'America' :
                                   query === 'africa' ? 'Africa' :
                                   query === 'australia' ? 'Australia' :
                                   query === 'pacific' ? 'Pacific' : null;
            
            // Filter timezones
            let filtered = this.timezones.filter(tz => {
                if (matchedCountryCode) {
                    if (matchedCountryCode === 'IN') {
                        const tzValueLower = tz.value.toLowerCase();
                        const searchLower = tz.search.toLowerCase();
                        
                        if (tzValueLower.includes('indiana') || searchLower.includes('indiana')) {
                            return false;
                        }
                        
                        if (tz.value === 'Asia/Kolkata' || tz.value === 'Asia/Calcutta') {
                            return true;
                        }
                        
                        if (tz.countryCode === 'IN' || 
                            (tz.countryName && tz.countryName.toLowerCase().includes('india')) ||
                            searchLower.includes('india') || searchLower.includes(' ind')) {
                            return true;
                        }
                        
                        return false;
                    }
                    
                    if (tz.countryCode === matchedCountryCode) {
                        return true;
                    }
                    
                    if (tz.countryName && tz.countryName.toLowerCase().includes(query)) {
                        return true;
                    }
                    
                    // Check if search field contains country-related terms
                    const searchLower = tz.search.toLowerCase();
                    if (searchLower.includes(query)) {
                        return true;
                    }
                    
                    return false;
                }
                
                if (matchedContinent) {
                    return tz.continent === matchedContinent;
                }
                
                if (tz.search.includes(query)) {
                    if ((query === 'ind' || query === 'indi') && 
                        (tz.value.toLowerCase().includes('indiana') || tz.search.includes('indiana'))) {
                        return false;
                    }
                    return true;
                }
                
                if (matchedOffset) {
                    const offsetRegex = /UTC([\+\-])(\d{2}):(\d{2})/;
                    const tzOffsetMatch = tz.search.match(offsetRegex);
                    if (tzOffsetMatch) {
                        const tzOffset = `${tzOffsetMatch[1]}${tzOffsetMatch[2]}:${tzOffsetMatch[3]}`;
                        if (tzOffset === matchedOffset) return true;
                    }
                }
                
                return false;
            });
            
            // Sort: country matches first, then continent, then others
            filtered.sort((a, b) => {
                if (matchedCountryCode) {
                    if (a.countryCode === matchedCountryCode && b.countryCode !== matchedCountryCode) return -1;
                    if (a.countryCode !== matchedCountryCode && b.countryCode === matchedCountryCode) return 1;
                    
                    if (matchedCountryCode === 'IN') {
                        const aIsKolkata = a.value === 'Asia/Kolkata';
                        const bIsKolkata = b.value === 'Asia/Kolkata';
                        if (aIsKolkata && !bIsKolkata) return -1;
                        if (!aIsKolkata && bIsKolkata) return 1;
                    }
                }
                if (matchedContinent) {
                    if (a.continent === matchedContinent && b.continent !== matchedContinent) return -1;
                    if (a.continent !== matchedContinent && b.continent === matchedContinent) return 1;
                }
                return 0;
            });
            
            return filtered.slice(0, 15);
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
        },
        clearTimezone() {
            this.selectedTimezone = '';
            this.search = '';
            this.showSuggestions = false;
        },
        init() {
            // Initialize selectedTimezone from Livewire on mount
            const initialTz = this.livewireTimezone || '';
            if (initialTz) {
                this.selectedTimezone = initialTz;
            }
            
            // Sync selectedTimezone changes to Livewire (Alpine -> Livewire)
            // The entangle binding automatically handles this, but we ensure it's set
            this.$watch('selectedTimezone', (newValue, oldValue) => {
                // Only update if value actually changed
                if (newValue !== oldValue) {
                    this.livewireTimezone = newValue || '';
                }
            });
            
            // Sync Livewire changes to selectedTimezone (Livewire -> Alpine)
            // The entangle binding automatically handles this too
            this.$watch('livewireTimezone', (newValue, oldValue) => {
                // Only update if value actually changed and is different from selectedTimezone
                if (newValue !== oldValue && newValue !== this.selectedTimezone) {
                    this.selectedTimezone = newValue || '';
                    this.search = '';
                }
            });
        }
    };
}

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

