<x-slot name="header">
 <h2 class="text-[24px] md:text-[30px] capitalize font-bold text-[#EB1C24]">
    Learning Checklist
  </h2>
</x-slot>
<div class=" w-full" x-data="{ showConfirm: false, deleteId: null }">
    {{-- Header --}}
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold text-red-600"></h2>
        <a href="{{ route('learningchecklist.add') }}"
           class="bg-[#EB1C24] hover:bg-red-600 text-[16px] text-white px-5 py-3 rounded flex items-center gap-2">
               <img src="{{ asset('images/add-square.svg') }}" alt="Add"  />Add new Learning Checklist
        </a>
    </div>

    {{-- Flash Message --}}
    @if (session()->has('message'))
        <div x-data="{ show: true }" 
             x-init="setTimeout(() => show = false, 2000)" 
             x-show="show" x-transition
             class="gap-2 items-center mb-4 px-4 py-2 rounded-lg border {{ session('type') === 'error' ? 'bg-red-500 border-red-400 text-white' : 'bg-red-500 border-red-400 text-white' }}">
            <span>{{ session('message') }}</span>
        </div>
    @endif
<div class="bg-white p-5 rounded-[10px] border border-[#E5E5E5] grid overflow-hidden ">
{{-- Search + Filters Bar --}}
<div class="overflow-x-auto">
<div class="flex items-center gap-3 mb-4 ">
    {{-- Search --}}
    <div class="flex-1">
        <div class="relative">
            <button class="absolute top-3 left-4 flex items-center" type="button">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9.16667 15.8333C12.8486 15.8333 15.8333 12.8486 15.8333 9.16667C15.8333 5.48477 12.8486 2.5 9.16667 2.5C5.48477 2.5 2.5 5.48477 2.5 9.16667C2.5 12.8486 5.48477 15.8333 9.16667 15.8333Z" stroke="#808080" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M17.5 17.5L13.875 13.875" stroke="#808080" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>


                    </button>   
            <input type="text"
                   placeholder="Search by title or principal..."
                   wire:model.live="search"
                   class="w-full border border-[#E5E5E5] bg-[#F8F9FA] rounded-[8px] px-4 pl-10 py-2.5 text-[14px] text-[#808080] focus:ring-1 focus:ring-red-500 focus:border-red-500 placeholder-gray-400">
           </div>
    </div>

    {{-- Principle Filter --}}
    <select wire:model.live="selectedPrincipleId"
            class="border border-[#E5E5E5] bg-[#F8F9FA] rounded-[8px] px-3 py-2.5 text-[14px] text-[#808080] focus:ring-1 focus:ring-red-500 focus:border-red-500">
        <option value="">All</option>
        @foreach ($principles as $p)
            <option value="{{ $p->id }}">{{ $p->title }}</option>
        @endforeach
    </select>

    {{-- Learning Type Filter --}}
    <select wire:model.live="selectedLearningTypeId"
            class="border border-[#E5E5E5] bg-[#F8F9FA] rounded-[8px] px-3 py-2.5 text-[14px] text-[#808080] focus:ring-1 focus:ring-red-500 focus:border-red-500">
        <option value="">Learning Type</option>
        @foreach ($learningTypes as $lt)
            <option value="{{ $lt->id }}">{{ $lt->title }}</option>
        @endforeach
    </select>
 {{-- Sort Filter --}}
<select wire:model.live="sortDirection"
        class="border border-[#E5E5E5] rounded-[8px] bg-[#F8F9FA] px-3 py-2.5 text-[14px] text-[#808080] focus:ring-1 focus:ring-red-500 focus:border-red-500 w-[100px]">
    <option value="desc">Newest</option>
    <option value="asc">Oldest</option>
</select>

</div>



    {{-- Table --}}
    
        <table class="w-full border-collapse border border-gray-200 text-sm tr-bg-alternate">
            <thead>
                <tr class="bg-[#F8F9FA]">
                    <th class=" px-4 py-5 text-left font-[600] text-[#020202] text-[14px]">Title</th>
                    <th class=" px-4 py-5 text-left font-[600] text-[#020202] text-[14px]">Principle</th>
                	<th class="px-4 py-5 text-left font-[600] text-[#020202] text-[14px]">Description</th>
                    <th class=" px-4 py-5 text-left font-[600] text-[#020202] text-[14px]">Learning Type</th>
                    <th class=" px-4 py-5 text-left font-[600] text-[#020202] text-[14px]">Video/Document</th> 
                    <th class=" px-4 py-5 text-left font-[600] text-[#020202] text-[14px] w-[110px]">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($checklists as $item)
                    <tr class="">
                        <td class="px-4 py-5 font-[400] text-[#020202] text-[14px]">{{ $item->title }}</td>
                        <td class="px-4 py-5 font-[400] text-[#808080] text-[14px]">{{ $item->principle->title ?? 'All' }}</td>
  <td class="px-4 py-5 font-[400] text-[#808080] text-[14px] max-w-xs">
  <div class="line-clamp-3 overflow-hidden text-ellipsis">
    {{ $item->description }}
  </div>
</td>

                                          
   <td class="px-4 py-5 font-[400] text-[#808080] text-[14px]">{{ $item->learningType->title ?? '-' }}</td>
  <td x-data="{ showModal: false, type: '', src: '' }" class="px-4 py-5 font-[400] text-[#808080] text-[14px] ">

    {{-- Video Button + Modal with YouTube IFrame API and fallback to watch page --}}
@if(!empty($item->link))
    @php
        // extract id server-side (supports youtu.be, watch?v=, shorts)
        $videoId = '';
        if (str_contains($item->link, 'youtu.be/')) {
            $videoId = ltrim(parse_url($item->link, PHP_URL_PATH), '/');
        } elseif (str_contains($item->link, 'youtube.com/watch')) {
            parse_str(parse_url($item->link, PHP_URL_QUERY) ?? '', $q);
            $videoId = $q['v'] ?? '';
        } elseif (str_contains($item->link, 'youtube.com/shorts/')) {
            $videoId = basename(parse_url($item->link, PHP_URL_PATH));
        }
        $embedLink = $videoId ? "https://www.youtube.com/embed/{$videoId}" : $item->link;
        $watchLink = $videoId ? "https://www.youtube.com/watch?v={$videoId}" : $item->link;
    @endphp

    <div
        x-data="youtubeModal({
            embedLink: '{{ $embedLink }}',
            watchLink: '{{ $watchLink }}',
            videoId: '{{ $videoId }}'
        })"
        x-init="init()"
        class="inline-block"
    >
        <button
            class="text-[#808080] flex items-center gap-1 hover:underline"
            @click="open()"
        >
            <img src="{{ asset('images/play-circle.svg') }}" class="h-5 w-5" /> Watch
        </button>

        <!-- Modal -->
        <div
            x-show="openModal"
            x-cloak
            class="fixed inset-0 bg-black/80 flex items-center justify-center z-50"
        >
            <div class="relative w-full h-full flex items-center justify-center">
                <button
                    @click="close()"
                    class="absolute top-6 right-6 text-white text-3xl font-bold hover:text-gray-300"
                >×</button>

                <!-- Player placeholder -->
                <div id="yt-player-container" class="w-[90%] md:w-[70%] h-[70vh] bg-black rounded-lg overflow-hidden flex items-center justify-center">
                    <!-- while loading we show nothing; YT API will replace this element with the player -->
                    <div x-show="loading" class="text-white">Loading video…</div>
                </div>
            </div>
        </div>
    </div>
@endif

    {{-- PDF Button --}}
    @if(!empty($item->document))
        <button class="text-[#808080] flex items-center" @click="showModal = true; type = 'pdf'; src = '{{ asset('storage/'.$item->document) }}'" 
                class="flex items-center gap-1 text-red-600 hover:underline">
            <img src="{{ asset('images/pdf-01.svg') }}" class="h-5 w-5" /> View
        </button>
    @endif


    {{-- Full Screen Modal --}}
    <div 
        x-show="showModal" 
        x-cloak 
        class="fixed inset-0 bg-black bg-opacity-80 flex items-center justify-center z-50"
    >
        <div class="relative w-full h-full flex items-center justify-center">

            <!-- Close Button -->
            <button class="text-[#fff] flex items-center absolute top-4 right-4 text-white  hover:opacity-75  flex items-center justify-center text-xl font-[500]" @click="showModal = false" >
                ✕
            </button>

            <!-- Video -->
            <template x-if="type === 'video'">
                <iframe 
                    :src="src" 
                    class="w-[95%] h-[90%] rounded-lg shadow-lg border-0" 
                    frameborder="0" 
                    allow="autoplay; encrypted-media" 
                    allowfullscreen>
                </iframe>
            </template>

            <!-- PDF -->
            <template x-if="type === 'pdf'">
                <iframe 
                    :src="src" 
                    class="w-[95%] h-[90%] rounded-lg shadow-lg border-0">
                </iframe>
            </template>
        </div>
    </div>

</td>

                      
                        <td class="px-4 py-5 w-[110px] ">
                            <div class="flex gap-1 justify-end">
                                {{-- Edit --}}
                            <a href="{{ route('learningchecklist.edit', $item->id) }}"
   class="rounded flex items-center justify-center"
   title="Edit">
    <img src="{{ asset('images/edit.svg') }}" alt="Edit" class="h-8 w-24">
</a>

                                {{-- Delete --}}
                           <button @click="showConfirm = true; deleteId = {{ $item->id }}"
        class=" rounded flex items-center justify-center"
        title="Delete">
    <img src="{{ asset('images/delete.svg') }}" alt="Delete" class="h-8 w-24">
</button>

                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center px-4 py-3">No Checklists Found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
    {{-- Delete Confirmation Modal --}}
    <div x-show="showConfirm" x-cloak
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
            <h2 class="text-lg font-semibold text-red-600 mb-4">Confirm Deletion</h2>
            <p class="text-gray-700 mb-6">Are you sure you want to delete this checklist?</p>

            <div class="flex justify-end gap-3">
                <button @click="showConfirm = false" 
                        class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">
                    Cancel
                </button>
                <button type="button" @click="$wire.delete(deleteId); showConfirm = false" 
                        class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                    Delete
                </button>
            </div>
        </div>
    </div>

	<script>
    // load YouTube IFrame API once
    if (!window.YT_API_LOADED) {
        window.YT_API_LOADED = false;
        const tag = document.createElement('script');
        tag.src = "https://www.youtube.com/iframe_api";
        const firstScriptTag = document.getElementsByTagName('script')[0];
        firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
        // set flag when ready
        window.onYouTubeIframeAPIReady = function() {
            window.YT_API_LOADED = true;
            // dispatch event so instances can initialize
            window.dispatchEvent(new Event('yt-api-ready'));
        };
    }

    function youtubeModal({ embedLink, watchLink, videoId }) {
        return {
            openModal: false,
            player: null,
            loading: false,
            triedFallback: false,
            embedLink, watchLink, videoId,

            init() {
                // listen for API ready if not yet loaded
                if (!window.YT_API_LOADED) {
                    window.addEventListener('yt-api-ready', () => {
                        // API ready; nothing to do now, we'll create player on open()
                    }, { once: true });
                }
            },

            open() {
                this.openModal = true;
                this.loading = true;
                this.triedFallback = false;

                // Wait for YT API to be ready
                const startWhenReady = () => {
                    if (window.YT && window.YT.Player) {
                        this.createPlayer();
                    } else {
                        // try again shortly
                        setTimeout(startWhenReady, 250);
                    }
                };
                startWhenReady();
            },

            close() {
                this.openModal = false;
                this.loading = false;
                // destroy player if exists
                if (this.player && typeof this.player.destroy === 'function') {
                    try { this.player.destroy(); } catch (e) {}
                    this.player = null;
                }
                // clear iframe container content
                const cont = document.getElementById('yt-player-container');
                if (cont) cont.innerHTML = '';
            },

            createPlayer() {
                // remove previous player if present
                if (this.player && typeof this.player.destroy === 'function') {
                    this.player.destroy();
                    this.player = null;
                }

                const container = document.getElementById('yt-player-container');
                if (!container) {
                    // fallback: open watch link
                    window.open(this.watchLink, '_blank');
                    this.openModal = false;
                    return;
                }

                // create a <div id="yt-player"> inside container to host player
                container.innerHTML = '<div id="yt-player"></div>';

                // create player with enablejsapi so we can get events
                this.player = new YT.Player('yt-player', {
                    height: '100%',
                    width: '100%',
                    videoId: this.videoId || '',
                    playerVars: {
                        autoplay: 1,
                        controls: 1,
                        rel: 0,
                        modestbranding: 1,
                        enablejsapi: 1
                    },
                    events: {
                        onReady: (evt) => {
                            this.loading = false;
                            try {
                                evt.target.playVideo();
                            } catch (e) {
                                // if play fails, fallback
                                this.fallbackToWatch();
                            }
                        },
                        onError: (evt) => {
                            // common error codes: 101,150 (embedding disabled) -> fallback
                            this.fallbackToWatch();
                        }
                    }
                });

                // safety timeout: if player doesn't call onReady within X seconds, fallback
                setTimeout(() => {
                    if (this.loading && !this.triedFallback) {
                        this.fallbackToWatch();
                    }
                }, 5000);
            },

            fallbackToWatch() {
                if (this.triedFallback) return;
                this.triedFallback = true;

                // close modal
                this.openModal = false;

                // destroy player if any
                if (this.player && typeof this.player.destroy === 'function') {
                    try { this.player.destroy(); } catch (e) {}
                    this.player = null;
                }

                // open YouTube watch page in new tab
                window.open(this.watchLink, '_blank');
            }
        };
    }
</script>

</div>
