<div class="flex-1 overflow-auto bg-[#f6f8fa]">
<div class="w-full bg-white rounded-md p-5">
    <div class="flex items-center mb-6 flex-wrap sm:flex-nowrap">
        <h2 class="text-[14px] sm:text-[24px] font-semibold text-[#EB1C24]">The 5 HPTM Principles</h2>
        <button class="ml-6 bg-[#FFEFF0] border border-[#FF9AA0] rounded-md flex items-center py-2 px-4 text-[#EB1C24] text-[16px]" style="line-height: normal;"> 
            HPTM <span class="text-black ml-2">
    {{ $principleArray['hptmScore'] ?? 0 }}
</span>

        </button>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        @foreach($principleArray['principleData'] ?? [] as $principle)
            <div class="bg-[#F8F9FA] rounded-md p-4 border border-gray-200 text-center relative pb-[60px]">
                <h3 class="text-[13px] sm:text-[20px] font-semibold text-[#EB1C24] mt-1">{{ $principle['title'] }}</h3>
                <p class="text-[12px] sm:text-[14px] xl:text-[16px] font-[400] text-[#808080] mt-1">{{ $principle['description'] }}</p>
                <span class="mt-3 bg-white text-[12px] sm:text-[14px] border border-[#FF9AA0] rounded-lg px-2 py-1 justify-center text-[#010101] flex w-full" style="position: absolute;bottom: 16px;left: 15px;width: calc(100% - 30px);">
                    Completion - {{ round($principle['completionPercent'] ?? 0) }}%
                </span>
            </div>
        @endforeach
    </div>
</div>

<div class="w-full bg-white rounded-md p-5 mt-6">
    <div class="flex items-center mb-6">
        <h2 class="text-[14px] sm:text-[24px] font-semibold text-[#EB1C24]">Self Learning Checklist</h2>
    </div>

    <div class="flex items-center mb-5">  
        <ul class="tab-menu flex items-center flex-wrap sm:flex-nowrap">
            @foreach($principleArray['principleData'] ?? [] as $principle)
                <li wire:click="setActivePrinciple({{ $principle['id'] }})" 
                    class="tab-btn cursor-pointer flex items-center px-4 py-2 mb-1 font-medium rounded-md text-[13px] sm:text-[16px] mr-4
                        {{ $activePrincipleId == $principle['id'] ? 'bg-[#EB1C24] text-white border-none' : 'bg-[#F8F9FA] text-[#020202] border border-[#020202]' }}">
                    {{ $principle['title'] }}
                </li> 
            @endforeach
        </ul>
    </div>

<div class="tab-content-box pb-15 mt-2" x-data="{ openModal: false, modalUrl: '', modalType: '' }">
    @if($activePrincipleId)
        @php
            $groupedChecklists = collect($learningCheckLists[$activePrincipleId] ?? [])
                ->flatten(1)
                ->groupBy('learningTypeTitle');
        @endphp

        <div class="tab-content active">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($groupedChecklists as $typeTitle => $checks)
                    @php
                        // Determine if all items in this group are read
                        $allChecked = collect($checks)->every(fn($check) => $check['userReadChecklist']);
                    @endphp

                    <div class="bg-[#F8F9FA] rounded-md p-4 border border-gray-200">
                        <!-- Group Title + Select All -->
                        <h3 class="text-[14px] sm:text-[20px] font-semibold text-[#020202] mt-1 flex items-center">
                            <input 
                                type="checkbox" 
                                class="appearance-none w-5 h-5 mr-3 border border-gray-400 rounded-full form-checkbox accent-red-500 text-red-600 focus:ring-red-500 focus:border-red-500 checked:bg-[#EB1C24] checked:border-[#EB1C24]"
                                wire:click="toggleAllChecks('{{ $typeTitle }}')"  
                                @checked($allChecked)
                            />
                            {{ $typeTitle }}
                        </h3>

                        @foreach($checks as $check)
                            <div class="border-l border-[#d1d1d1] pl-6 mt-4">
                                <!-- Individual Checklist -->
                                <h4 class="text-[13px] sm:text-[16px] font-semibold text-[#020202] mt-1 flex items-center">
                                    <input 
                                        type="checkbox" 
                                        name="checklist_{{ $check['typeId'] }}" 
                                        value="{{ $check['checklistId'] }}" 
                                        wire:click="changeReadStatusOfUserChecklist({{ $check['checklistId'] }}, {{ $check['userReadChecklist'] ? 0 : 1 }})"
                                        class="appearance-none w-5 h-5 mr-3 border border-gray-400 rounded-full form-checkbox accent-red-500 text-red-600 focus:ring-red-500 focus:border-red-500 checked:bg-[#EB1C24] checked:border-[#EB1C24]"
                                        @if($check['userReadChecklist']) checked @endif
                                    >
                                    {{ $check['checklistTitle'] }}
                                </h4>

                                <p class="text-[12px] sm:text-[16px] text-[#808080] mt-3 break-words">{{ $check['description'] }}</p>

                                {{-- Video Link --}}
                                @if(!empty($check['link']))
                                    @php
                                        // Extract video ID from various YouTube URL formats
                                        $videoId = '';
                                        $originalUrl = trim($check['link']);
                                        
                                        if (str_contains($originalUrl, 'youtu.be/')) {
                                            $path = parse_url($originalUrl, PHP_URL_PATH);
                                            $videoId = $path ? ltrim($path, '/') : '';
                                            // Remove any query params or fragments from video ID
                                            $videoId = explode('?', $videoId)[0];
                                            $videoId = explode('#', $videoId)[0];
                                        } elseif (str_contains($originalUrl, 'youtube.com/watch')) {
                                            parse_str(parse_url($originalUrl, PHP_URL_QUERY) ?? '', $q);
                                            $videoId = $q['v'] ?? '';
                                        } elseif (str_contains($originalUrl, 'youtube.com/shorts/')) {
                                            $path = parse_url($originalUrl, PHP_URL_PATH);
                                            $videoId = $path ? basename($path) : '';
                                        } elseif (str_contains($originalUrl, 'youtube.com/embed/')) {
                                            $path = parse_url($originalUrl, PHP_URL_PATH);
                                            $videoId = $path ? basename($path) : '';
                                        }
                                        
                                        // Clean video ID (remove any remaining query params)
                                        $videoId = trim($videoId);
                                        if (!empty($videoId)) {
                                            $videoId = explode('?', $videoId)[0];
                                            $videoId = explode('&', $videoId)[0];
                                        }
                                        
                                        // Build embed link with minimal parameters to avoid Error 153
                                        $embedLink = !empty($videoId) ? "https://www.youtube.com/embed/{$videoId}" : '';
                                    @endphp
                                    <div x-data="{ openVideoModal: false }" class="inline-block">
                                        <button 
                                            @click.stop="openVideoModal = true" 
                                            class="flex items-center text-[#EB1C24] text-[12px] sm:text-[14px] mt-3"
                                        >
                                            <img src="{{ asset('images/play-circle.svg') }}" class="mr-3" alt="Play Icon"> 
                                            Watch the video
                                        </button>

                                        <!-- Video Modal -->
                                        <div
                                            x-show="openVideoModal"
                                            x-cloak
                                            @click.self="openVideoModal = false"
                                            class="fixed inset-0 bg-black/80 flex items-center justify-center z-50"
                                        >
                                            <div class="relative w-[95%] md:w-[85%] lg:w-[75%] h-[85vh] bg-black rounded-lg overflow-hidden">
                                                <button
                                                    @click="openVideoModal = false"
                                                    class="absolute top-4 right-4 text-white bg-black/50 hover:bg-black/70 rounded-full w-10 h-10 flex items-center justify-center text-2xl font-bold z-10 transition-colors"
                                                >×</button>
                                                
                                                @if(!empty($videoId))
                                                    <iframe 
                                                        src="{{ $embedLink }}?autoplay=1&rel=0&modestbranding=1" 
                                                        class="w-full h-full border-0" 
                                                        frameborder="0" 
                                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                                        allowfullscreen
                                                    ></iframe>
                                                @else
                                                    <div class="w-full h-full flex items-center justify-center text-white p-8 text-center">
                                                        <div>
                                                            <div class="mb-4 text-2xl">⚠️</div>
                                                            <div class="mb-4 text-xl font-semibold">Invalid video URL</div>
                                                            <div class="mb-6 text-gray-300">Unable to extract video ID from the provided link.</div>
                                                            <a href="{{ $originalUrl }}" target="_blank" class="inline-block bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg transition-colors">
                                                                Open Original Link
                                                            </a>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                {{-- PDF Link --}}
                                @if($check['document'])
                                    <button 
                                        @click.stop="openModal = true; modalType = 'pdf'; modalUrl = '{{ $check['document'] }}'" 
                                        class="flex items-center text-[#EB1C24] text-[12px] sm:text-[14px] mt-3"
                                    >
                                        <img src="{{ asset('images/pdf-01.svg') }}" class="mr-3"> 
                                        View Attachment
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    @endif



    <!-- Modal for PDF -->
    <div x-show="openModal && modalType === 'pdf'" x-cloak
          class="fixed inset-0 bg-black bg-opacity-80 flex items-center justify-center z-50"
         x-transition>
          <div class="relative w-full h-full flex items-center justify-center">
            <!-- Close button -->
            <button @click="openModal = false; modalUrl=''; modalType='';" 
                      class="absolute top-4 right-4 text-white bg-black/50 hover:bg-black rounded-full w-10 h-10 flex items-center justify-center text-2xl font-bold">&times;</button>
            <!-- PDF -->
            <iframe :src="modalUrl" class="w-[95%] h-[90%] rounded-lg shadow-lg border-0" frameborder="0"></iframe>
          </div>
        </div>

</div>
      

	</div>
</div>