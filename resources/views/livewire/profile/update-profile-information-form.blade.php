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
            <template x-if="!photoPreview && '{{ $this->user->profile_photo_url }}' !== ''">
              <img class="h-24 w-24 rounded-full object-cover" src="{{ $this->user->profile_photo_url }}" alt="{{ $this->user->name }}">
            </template>
            <template x-if="!photoPreview && '{{ $this->user->profile_photo_url }}' === ''">
              <div class="h-24 w-24 rounded-full bg-gray-200 flex items-center justify-center">
                <span class="text-gray-400 text-2xl font-semibold">{{ strtoupper(substr($this->user->first_name, 0, 1) . substr($this->user->last_name, 0, 1)) }}</span>
              </div>
            </template>

            {{-- Buttons Below Photo --}}
            <div class="mt-3 flex justify-center gap-2">
                {{-- Edit Button --}}
                <label for="photo"
                       class="p-2 bg-red-600 hover:bg-red-700 text-white rounded-md cursor-pointer transition-colors flex items-center justify-center shadow-md"
                       title="Edit Photo">
                    <img src="{{ asset('images/pencil1.png') }}" alt="Edit" class="w-2.5 h-2.5">
                </label>

                {{-- Delete Button - Only show when photo exists --}}
                @if($this->user->profile_photo_path)
                    <button type="button" 
                            wire:click="deletePhoto" 
                            wire:confirm="Are you sure you want to delete your profile photo?"
                            wire:loading.attr="disabled"
                            wire:target="deletePhoto"
                            class="p-2 bg-red-600 hover:bg-red-700 text-white rounded-md cursor-pointer transition-colors disabled:opacity-50 flex items-center justify-center shadow-md"
                            title="Delete Photo">
                        <svg wire:loading.remove wire:target="deletePhoto" xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <svg wire:loading wire:target="deletePhoto" class="animate-spin h-2.5 w-2.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                @endif
            </div>

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
                $city = ucwords(strtolower($city)); // Proper capitalization
                
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
            $currentTz = $this->user->timezone ?? ($this->state['timezone'] ?? '');
            $currentDisplay = collect($timezones)->firstWhere('value', $currentTz)['display'] ?? $currentTz;
        @endphp
        <script>
            function timezoneComponent() {
                return {
                    search: '',
                    showSuggestions: false,
                    selectedTimezone: @js($currentTz ?? ''),
                    timezones: @js($timezones ?? []),
            get filteredTimezones() {
                if (!this.search || this.search.length < 1) return [];
                const query = this.search.toLowerCase().trim();
                
                // Country name mapping for better search (including partial matches)
                const countryMap = {
                    'india': 'IN', 'indian': 'IN', 'ind': 'IN', // Partial match for "ind"
                    'united states': 'US', 'usa': 'US', 'america': 'US', 'us': 'US',
                    'united kingdom': 'GB', 'uk': 'GB', 'britain': 'GB', 'brit': 'GB',
                    'china': 'CN', 'japan': 'JP', 'korea': 'KR',
                    'australia': 'AU', 'canada': 'CA', 'germany': 'DE',
                    'france': 'FR', 'spain': 'ES', 'italy': 'IT',
                    'singapore': 'SG', 'thailand': 'TH', 'indonesia': 'ID',
                    'philippines': 'PH', 'malaysia': 'MY', 'uae': 'AE',
                    'dubai': 'AE', 'south africa': 'ZA', 'egypt': 'EG',
                    'brazil': 'BR', 'mexico': 'MX', 'argentina': 'AR',
                    'new zealand': 'NZ', 'netherlands': 'NL', 'belgium': 'BE',
                    'pakistan': 'PK', 'pakistani': 'PK', 'pakis': 'PK', 'paki': 'PK',
                    'nepal': 'NP', 'nepalese': 'NP', 'nepali': 'NP'
                };
                
                // Check if query is GMT/UTC offset (e.g., "gmt+5", "utc+5:30", "+5", "-8")
                let matchedOffset = null;
                const offsetMatch = query.match(/^(gmt|utc)?[\+\-]?(\d{1,2})(:(\d{2}))?$/i);
                if (offsetMatch) {
                    const sign = query.includes('-') ? '-' : '+';
                    const hours = parseInt(offsetMatch[2]) || 0;
                    const minutes = parseInt(offsetMatch[4]) || 0;
                    matchedOffset = `${sign}${hours}:${minutes.toString().padStart(2, '0')}`;
                }
                
                // Special handling for "ind" - always prioritize India
                let matchedCountryCode = null;
                // Check for "ind" first (before checking countryMap) to prioritize India
                if (query === 'ind' || query === 'indi' || (query.length >= 3 && query.length <= 5 && query.startsWith('ind') && query !== 'indiana')) {
                    // "ind", "indi", or any query starting with "ind" (except "indiana") should match India
                    matchedCountryCode = 'IN';
                } else if (!matchedOffset) {
                    // Check exact match first
                    matchedCountryCode = countryMap[query];
                    
                    // If no exact match, check for partial country name matches
                    if (!matchedCountryCode) {
                        // Expanded country name mappings for better search
                        const countryNames = {
                            'IN': ['india', 'indian'],
                            'US': ['united states', 'usa', 'america', 'united states of america'],
                            'GB': ['united kingdom', 'uk', 'britain', 'british', 'england'],
                            'CN': ['china', 'chinese'],
                            'JP': ['japan', 'japanese'],
                            'KR': ['korea', 'korean', 'south korea'],
                            'AU': ['australia', 'australian'],
                            'CA': ['canada', 'canadian'],
                            'DE': ['germany', 'german'],
                            'FR': ['france', 'french'],
                            'ES': ['spain', 'spanish'],
                            'IT': ['italy', 'italian'],
                            'SG': ['singapore'],
                            'TH': ['thailand', 'thai'],
                            'ID': ['indonesia', 'indonesian'],
                            'PH': ['philippines', 'philippine'],
                            'MY': ['malaysia', 'malaysian'],
                            'AE': ['uae', 'united arab emirates', 'dubai', 'emirates'],
                            'ZA': ['south africa', 'south african'],
                            'EG': ['egypt', 'egyptian'],
                            'BR': ['brazil', 'brazilian'],
                            'MX': ['mexico', 'mexican'],
                            'AR': ['argentina', 'argentine'],
                            'NZ': ['new zealand'],
                            'NL': ['netherlands', 'dutch'],
                            'BE': ['belgium', 'belgian'],
                            'CH': ['switzerland', 'swiss'],
                            'AT': ['austria', 'austrian'],
                            'SE': ['sweden', 'swedish'],
                            'NO': ['norway', 'norwegian'],
                            'DK': ['denmark', 'danish'],
                            'FI': ['finland', 'finnish'],
                            'PL': ['poland', 'polish'],
                            'IE': ['ireland', 'irish'],
                            'PT': ['portugal', 'portuguese'],
                            'GR': ['greece', 'greek'],
                            'TR': ['turkey', 'turkish'],
                            'RU': ['russia', 'russian'],
                            'PK': ['pakistan', 'pakistani', 'pakis', 'paki'],
                            'NP': ['nepal', 'nepalese', 'nepali'],
                            'BD': ['bangladesh', 'bangladeshi'],
                            'LK': ['sri lanka', 'sri lankan'],
                            'VN': ['vietnam', 'vietnamese'],
                            'TW': ['taiwan', 'taiwanese'],
                            'HK': ['hong kong'],
                            'IL': ['israel', 'israeli'],
                            'SA': ['saudi arabia', 'saudi'],
                            'AE': ['uae', 'united arab emirates', 'dubai', 'emirates'],
                            'JO': ['jordan', 'jordanian'],
                            'KW': ['kuwait', 'kuwaiti'],
                            'QA': ['qatar', 'qatari'],
                            'BH': ['bahrain', 'bahraini'],
                            'OM': ['oman', 'omani'],
                            'YE': ['yemen', 'yemeni'],
                            'IQ': ['iraq', 'iraqi'],
                            'IR': ['iran', 'iranian'],
                            'AF': ['afghanistan', 'afghan']
                        };
                        
                        for (const [code, names] of Object.entries(countryNames)) {
                            for (const name of names) {
                                // Check if query matches the start of country name or country name contains query
                                if ((name.startsWith(query) || name.includes(query)) && query.length >= 2) {
                                    matchedCountryCode = code;
                                    break;
                                }
                            }
                            if (matchedCountryCode) break;
                        }
                    }
                }
                
                const matchedContinent = query === 'asia' ? 'Asia' : 
                                       query === 'europe' ? 'Europe' :
                                       query === 'america' ? 'America' :
                                       query === 'africa' ? 'Africa' :
                                       query === 'australia' ? 'Australia' :
                                       query === 'pacific' ? 'Pacific' : null;
                
                // Filter timezones with priority
                let filtered = this.timezones.filter(tz => {
                    // If country code is matched, show timezones from that country
                    if (matchedCountryCode) {
                        // For India search, be more inclusive
                        if (matchedCountryCode === 'IN') {
                            const tzValueLower = tz.value.toLowerCase();
                            const searchLower = tz.search.toLowerCase();
                            
                            // STRICTLY EXCLUDE Indiana timezones first
                            if (tzValueLower.includes('indiana') || searchLower.includes('indiana') || 
                                tz.city.includes('indiana') || tz.value.includes('Indiana')) {
                                return false; // Explicitly exclude all Indiana
                            }
                            
                            // ALWAYS include Asia/Kolkata (main India timezone) - check this first
                            if (tz.value === 'Asia/Kolkata' || tz.value === 'Asia/Calcutta') {
                                return true;
                            }
                            
                            // Direct country code match
                            if (tz.countryCode === 'IN') {
                                return true;
                            }
                            
                            // Country name match (this should catch most India timezones)
                            if (tz.countryName && tz.countryName.toLowerCase().includes('india')) {
                                return true;
                            }
                            
                            // If search field contains "india" (but not "indiana"), include it
                            // This is the most important check - the search field includes country name
                            // Also check for "ind" in search field (which would match "India")
                            if ((searchLower.includes('india') || searchLower.includes(' ind')) && !searchLower.includes('indiana')) {
                                return true;
                            }
                            
                            // Check if timezone value contains India-related city identifiers
                            if (tzValueLower.includes('kolkata') || tzValueLower.includes('calcutta') ||
                                tzValueLower.includes('mumbai') || tzValueLower.includes('bombay') ||
                                tzValueLower.includes('delhi') || tzValueLower.includes('chennai') ||
                                tzValueLower.includes('madras') || tzValueLower.includes('bangalore') ||
                                tzValueLower.includes('hyderabad') || tzValueLower.includes('pune') ||
                                tzValueLower.includes('ahmedabad') || tzValueLower.includes('jaipur') ||
                                tzValueLower.includes('lucknow') || tzValueLower.includes('kanpur')) {
                                return true;
                            }
                            
                            // Check search field for India cities
                            if (searchLower.includes('kolkata') || searchLower.includes('calcutta') ||
                                searchLower.includes('mumbai') || searchLower.includes('bombay') ||
                                searchLower.includes('delhi') || searchLower.includes('chennai') ||
                                searchLower.includes('madras') || searchLower.includes('bangalore') ||
                                searchLower.includes('hyderabad') || searchLower.includes('pune') ||
                                searchLower.includes('ahmedabad') || searchLower.includes('jaipur')) {
                                return true;
                            }
                            
                            // Don't show non-India results
                            return false;
                        }
                        
                        // For other countries
                        // Direct country code match
                        if (tz.countryCode === matchedCountryCode) {
                            return true;
                        }
                        
                        // Country name match - check if search field contains country name
                        if (tz.countryName && tz.countryName.toLowerCase().includes(query)) {
                            return true;
                        }
                        
                        // Check if search field contains country-related terms or query
                        const searchLower = tz.search.toLowerCase();
                        const tzValueLower = tz.value.toLowerCase();
                        const cityLower = tz.city.toLowerCase();
                        
                        // Check if search field or timezone value contains query (for city names, etc.)
                        if (searchLower.includes(query) || tzValueLower.includes(query) || cityLower.includes(query)) {
                            // Get country terms for the matched country
                            const countryTerms = countryNames[matchedCountryCode] || [];
                            
                            // If search field contains any country term, include it
                            for (const term of countryTerms) {
                                if (searchLower.includes(term) || tz.countryName?.toLowerCase().includes(term)) {
                                    return true;
                                }
                            }
                            
                            // Also check if timezone value contains country code or country name
                            if (tz.countryCode === matchedCountryCode || 
                                (tz.countryName && countryTerms.some(term => tz.countryName.toLowerCase().includes(term)))) {
                                return true;
                            }
                        }
                        
                        // Don't show other countries when searching for a specific country
                        return false;
                    }
                    
                    // If continent is matched, show timezones from that continent
                    if (matchedContinent) {
                        return tz.continent === matchedContinent;
                    }
                    
                    // BEFORE general search, check if query is "ind" and exclude Indiana
                    // Only exclude Indiana if query is "ind", "indi", or starts with "ind" but is NOT "indiana"
                    if ((query === 'ind' || query === 'indi' || (query.startsWith('ind') && query !== 'indiana' && query.length <= 4)) && 
                        (tz.value.toLowerCase().includes('indiana') || tz.search.includes('indiana') || tz.city.includes('indiana'))) {
                        // Explicitly exclude Indiana for "ind" queries
                        return false;
                    }
                    
                    // General search - check if query matches any part of the search field
                    if (tz.search.includes(query)) {
                        // Special handling for "ind" or "indi" - EXCLUDE Indiana completely
                        // Only apply this for "ind" or "indi", not for "indiana"
                        if (query === 'ind' || query === 'indi' || (query.startsWith('ind') && query !== 'indiana' && query.length <= 4)) {
                            // STRICTLY EXCLUDE Indiana timezones
                            const tzValueLower = tz.value.toLowerCase();
                            const searchLower = tz.search.toLowerCase();
                            const cityLower = tz.city.toLowerCase();
                            
                            if (tzValueLower.includes('indiana') || searchLower.includes('indiana') || 
                                cityLower.includes('indiana') || tz.value.includes('Indiana')) {
                                return false; // Explicitly exclude all Indiana
                            }
                            
                            // Only show India-related timezones
                            if (tz.countryCode === 'IN' || 
                                (tz.countryName && tz.countryName.toLowerCase().includes('india')) ||
                                tzValueLower.includes('kolkata') || tzValueLower.includes('calcutta') ||
                                tzValueLower.includes('mumbai') || tzValueLower.includes('bombay') ||
                                tzValueLower.includes('delhi') || tzValueLower.includes('chennai') ||
                                tzValueLower.includes('madras') || tzValueLower.includes('bangalore') ||
                                tzValueLower.includes('hyderabad') || tzValueLower.includes('pune') ||
                                tzValueLower.includes('ahmedabad') || tzValueLower.includes('jaipur') ||
                                tzValueLower.includes('lucknow') || tzValueLower.includes('kanpur') ||
                                searchLower.includes('kolkata') || searchLower.includes('calcutta') ||
                                searchLower.includes('mumbai') || searchLower.includes('bombay') ||
                                searchLower.includes('delhi') || searchLower.includes('chennai') ||
                                searchLower.includes('madras') || searchLower.includes('bangalore') ||
                                searchLower.includes('hyderabad') || searchLower.includes('pune') ||
                                searchLower.includes('ahmedabad') || searchLower.includes('jaipur')) {
                                return true;
                            }
                            // Don't show any other results for "ind" search
                            return false;
                        }
                        
                        // Avoid false matches: "india" should not match "Indiana"
                        if ((query === 'india' || query === 'indian') && 
                            (tz.search.includes('indiana') || tz.city.includes('indiana') || 
                             tz.value.includes('Indiana'))) {
                            return false;
                        }
                        // Avoid false matches: "korea" should not match "korean" in wrong context
                        if (query === 'korea' && tz.search.includes('north korea') && !tz.countryCode) {
                            // Allow if it's actually Korea
                            return tz.countryCode === 'KR' || tz.search.includes('south korea');
                        }
                        return true;
                    }
                    
                    // Check country name match (partial match) - prioritize this
                    if (tz.countryName && tz.countryName.toLowerCase().includes(query)) {
                        return true;
                    }
                    
                    // Check city name match - should work for "mumbai", "karachi", "kathmandu", etc.
                    const cityLower = (tz.city || '').toLowerCase();
                    if (cityLower.includes(query)) {
                        return true;
                    }
                    
                    // Check timezone value/city in value (e.g., "Asia/Mumbai")
                    const tzValueLower = tz.value.toLowerCase();
                    if (tzValueLower.includes(query)) {
                        return true;
                    }
                    
                    // Check GMT/UTC offset match if query is an offset
                    if (matchedOffset) {
                        // Extract offset from timezone search field (format: UTC+05:30)
                        const offsetRegex = /UTC([\+\-])(\d{2}):(\d{2})/;
                        const tzOffsetMatch = tz.search.match(offsetRegex);
                        if (tzOffsetMatch) {
                            const tzOffset = `${tzOffsetMatch[1]}${tzOffsetMatch[2]}:${tzOffsetMatch[3]}`;
                            // Normalize both offsets for comparison
                            const normalizeOffset = (offset) => {
                                const parts = offset.split(':');
                                const hours = parseInt(parts[0].replace(/[^\d\-+]/g, ''));
                                const minutes = parseInt(parts[1] || '0');
                                return `${hours >= 0 ? '+' : ''}${hours}:${minutes.toString().padStart(2, '0')}`;
                            };
                            
                            // Check if offsets match (allow for slight variations)
                            const queryOffsetNorm = normalizeOffset(matchedOffset);
                            const tzOffsetNorm = normalizeOffset(tzOffset);
                            
                            // Exact match
                            if (queryOffsetNorm === tzOffsetNorm) {
                                return true;
                            }
                            
                            // Match by hours only (e.g., "+5" matches "+5:30")
                            const queryHours = parseInt(queryOffsetNorm.split(':')[0].replace(/[^\d\-+]/g, ''));
                            const tzHours = parseInt(tzOffsetNorm.split(':')[0].replace(/[^\d\-+]/g, ''));
                            if (queryHours === tzHours) {
                                return true;
                            }
                        }
                        
                        // Also check if search field contains the offset string
                        if (tz.search.includes(matchedOffset) || tz.search.includes(`UTC${matchedOffset}`) || 
                            tz.search.includes(`GMT${matchedOffset}`)) {
                            return true;
                        }
                    }
                    
                    // Check if search field contains query (general fallback)
                    if (tz.search.includes(query)) {
                        return true;
                    }
                    
                    return false;
                });
                
                // Sort: country matches first, then continent, then others
                filtered.sort((a, b) => {
                    if (matchedCountryCode) {
                        // Country code matches get highest priority
                        if (a.countryCode === matchedCountryCode && b.countryCode !== matchedCountryCode) return -1;
                        if (a.countryCode !== matchedCountryCode && b.countryCode === matchedCountryCode) return 1;
                        
                        // For India search, prioritize Kolkata
                        if (matchedCountryCode === 'IN') {
                            const aIsKolkata = a.value === 'Asia/Kolkata' || a.search.includes('kolkata');
                            const bIsKolkata = b.value === 'Asia/Kolkata' || b.search.includes('kolkata');
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
                $wire.set('state.timezone', tz.value);
            },
            clearTimezone() {
                this.selectedTimezone = '';
                this.search = '';
                this.showSuggestions = false;
                $wire.set('state.timezone', '');
            },
                    init() {
                        // Update Livewire when timezone changes
                        this.$watch('selectedTimezone', value => {
                            $wire.set('state.timezone', value);
                        });
                        
                        // Listen for timezone updates from Livewire
                        $wire.on('timezone-updated', (timezone) => {
                            if (timezone) {
                                this.selectedTimezone = timezone;
                                $wire.set('state.timezone', timezone);
                            }
                        });
                    }
                };
            }
        </script>
        <div class="col-span-6 sm:col-span-3" 
             x-data="timezoneComponent()">
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

