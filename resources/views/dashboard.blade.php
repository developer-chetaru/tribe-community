<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[14px] sm:text-[24px]  font-[600] text-[#EB1C24]">
           Dashboard
        </h2>
    </x-slot>
    <div>
      @hasanyrole('super_admin')
		
		
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
           <!-- @foreach ($cards as $card)
                <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition">
                    <h2 class="text-xl font-semibold capitalize text-gray-700 mb-4">{{ $card['name'] }}</h2>

                    <p class="text-gray-500">
                        Culture:
                        <span class="{{ $card['culture'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                            {{ $card['culture'] }}
                        </span>
                    </p>

                    <p class="text-gray-500">
                        Engagement:
                        <span class="{{ $card['engagement'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                            {{ $card['engagement'] }}
                        </span>
                    </p>

                    <p class="text-gray-500">
                        Good Day:
                        <span class="{{ $card['goodDay'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                            {{ $card['goodDay'] }}%
                        </span>
                    </p>

                    <p class="text-gray-500">
                        Bad Day:
                        <span class="{{ $card['badDay'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                            {{ $card['badDay'] }}%
                        </span>
                    </p>

                    <p class="text-gray-500">
                        HPTM:
                        <span class="{{ $card['hptm'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                            {{ $card['hptm'] }}
                        </span>
                    </p>
                </div>
            @endforeach   -->
        </div>
      	@else
            {{-- Fallback content for non-admins --}}
          	<div class="flex-1 overflow-auto">
			<div class="w-full bg-white rounded-md p-5">

			<div class="flex items-center w-full">
            @if(Auth::user()->organisation)
                <div class="flex items-center p-0 mt-0">
                    @if(Auth::user()->organisation->image)
                        <img class="w-[100px] h-[100px] rounded-md bg-gray-100 flex items-center justify-center mb-3 px-2 overflow-hidden object-contain"
                             src="{{ asset('storage/' . Auth::user()->organisation->image) }}"
                             alt="{{ Auth::user()->organisation->name }}">
                    @else
                        <div class="h-14 w-14 flex items-center justify-center rounded-full bg-gray-200 text-gray-500 text-sm">
                            No Logo
                        </div>
                    @endif
                </div>
            @endif
				

    <!--  <div class="flex items-center ml-auto">
       <button type="button" class="bg-[#f6f8fa] border text-black px-5 py-2 rounded-sm border-gray-200 font-medium ml-3 flex items-center">
						<svg class="mr-2" width="20" height="21" viewBox="0 0 20 21" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M11.2487 11.3333L14.1654 8M11.6654 13C11.6654 13.9205 10.9192 14.6667 9.9987 14.6667C9.0782 14.6667 8.33203 13.9205 8.33203 13C8.33203 12.0795 9.0782 11.3333 9.9987 11.3333C10.9192 11.3333 11.6654 12.0795 11.6654 13Z" stroke="#141B34" stroke-width="1.25" stroke-linecap="round"/>
							<path d="M5 10.5C5 7.73857 7.23857 5.5 10 5.5C10.9107 5.5 11.7646 5.74348 12.5 6.16891" stroke="#141B34" stroke-width="1.25" stroke-linecap="round"/>
							<path d="M2.08203 10.5007C2.08203 6.7687 2.08203 4.90273 3.2414 3.74335C4.40077 2.58398 6.26675 2.58398 9.99873 2.58398C13.7306 2.58398 15.5967 2.58398 16.756 3.74335C17.9154 4.90273 17.9154 6.7687 17.9154 10.5007C17.9154 14.2326 17.9154 16.0986 16.756 17.2579C15.5967 18.4174 13.7306 18.4174 9.99873 18.4174C6.26675 18.4174 4.40077 18.4174 3.2414 17.2579C2.08203 16.0986 2.08203 14.2326 2.08203 10.5007Z" stroke="#141B34" stroke-width="1.25"/>
						</svg>
						Performance Data
					</button>
					<button type="button" class="bg-[#f6f8fa] ml-3 border text-black px-5 py-2 rounded-sm border-gray-200 font-medium flex items-center">
						<svg class="mr-2" width="20" height="21" viewBox="0 0 20 21" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M7.08203 10.5C7.77239 10.5 8.33203 9.94036 8.33203 9.25C8.33203 8.55964 7.77239 8 7.08203 8C6.39168 8 5.83203 8.55964 5.83203 9.25C5.83203 9.94036 6.39168 10.5 7.08203 10.5Z" stroke="#141B34" stroke-width="1.25"/>
							<path d="M12.082 14.666C12.7724 14.666 13.332 14.1064 13.332 13.416C13.332 12.7257 12.7724 12.166 12.082 12.166C11.3917 12.166 10.832 12.7257 10.832 13.416C10.832 14.1064 11.3917 14.666 12.082 14.666Z" stroke="#141B34" stroke-width="1.25"/>
							<path d="M15.418 8C16.1083 8 16.668 7.44036 16.668 6.75C16.668 6.05964 16.1083 5.5 15.418 5.5C14.7276 5.5 14.168 6.05964 14.168 6.75C14.168 7.44036 14.7276 8 15.418 8Z" stroke="#141B34" stroke-width="1.25"/>
							<path d="M12.8617 12.4136L15 8M7.98542 10.1403L11.0032 12.4136M2.5 16.3333L6.32464 10.3993" stroke="#141B34" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M16.668 18H7.5013C4.75144 18 3.37651 18 2.52224 17.1457C1.66797 16.2914 1.66797 14.9165 1.66797 12.1667V3" stroke="#141B34" stroke-width="1.25" stroke-linecap="round"/>
						</svg>

						Weekly Report
					</button>
					<button type="button" class="bg-[#f6f8fa] ml-3 border border-gray-200 text-black px-5 py-2 rounded-sm  font-medium flex items-center">
						<svg class="mr-2" width="21" height="21" viewBox="0 0 21 21" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M3.83203 13V16.3333" stroke="#141B34" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M10.5 8V16.3333" stroke="#141B34" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M18.8346 18.834H2.16797" stroke="#141B34" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M17.168 11.334V16.334" stroke="#141B34" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M5.16804 7.83262C4.86398 7.42784 4.37989 7.16602 3.83464 7.16602C2.91416 7.16602 2.16797 7.91221 2.16797 8.83268C2.16797 9.75318 2.91416 10.4993 3.83464 10.4993C4.75511 10.4993 5.5013 9.75318 5.5013 8.83268C5.5013 8.45746 5.3773 8.11119 5.16804 7.83262ZM5.16804 7.83262L9.16789 4.83274M9.16789 4.83274C9.47197 5.23752 9.95605 5.49935 10.5013 5.49935C11.1538 5.49935 11.7187 5.1244 11.9923 4.5782M9.16789 4.83274C8.95864 4.55417 8.83464 4.20791 8.83464 3.83268C8.83464 2.91221 9.5808 2.16602 10.5013 2.16602C11.4218 2.16602 12.168 2.91221 12.168 3.83268C12.168 4.10067 12.1047 4.35387 11.9923 4.5782M11.9923 4.5782L15.677 6.4205M15.677 6.4205C15.5646 6.64482 15.5013 6.89803 15.5013 7.16602C15.5013 8.08649 16.2475 8.83268 17.168 8.83268C18.0885 8.83268 18.8346 8.08649 18.8346 7.16602C18.8346 6.24554 18.0885 5.49935 17.168 5.49935C16.5155 5.49935 15.9506 5.8743 15.677 6.4205Z" stroke="#141B34" stroke-width="1.25"/>
						</svg>
						Graphical Data
					</button>
    </div>  -->
</div>

@livewire('dashboard-summary')
@livewire('weekly-summary')
@livewire('monthly-summary')

       

			</div>
		</div>
	</div>
      
        @endhasanyrole
    </div>
</x-app-layout>
