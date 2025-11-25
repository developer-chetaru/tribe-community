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
<div class="bg-white p-5 rounded-[10px] border border-[#E5E5E5]">
{{-- Search + Filters Bar --}}

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
    <div class="overflow-x-auto">
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

    {{-- Video Button --}}
    @if(!empty($item->link))
        @php
            $videoLink = $item->link;
            if (str_contains($item->link, 'youtube.com/watch?v=')) {
                parse_str(parse_url($item->link, PHP_URL_QUERY), $query);
                $videoId = $query['v'] ?? '';
                $videoLink = "https://www.youtube.com/embed/{$videoId}";
            }
        @endphp
        <button class="text-[#808080] flex items-center" @click="showModal = true; type = 'video'; src = '{{ $videoLink }}'" 
                class="flex items-center gap-1 text-black hover:underline">
            <img src="{{ asset('images/play-circle.svg') }}" class="h-5 w-5" /> Watch
        </button>
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
            <button class="text-[#808080] flex items-center" @click="showModal = false" 
                class="absolute top-4 right-4 text-white bg-black/50 hover:bg-black rounded-full w-10 h-10 flex items-center justify-center text-2xl font-bold">
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
                <button @click="$wire.delete(deleteId); showConfirm = false" 
                        class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                    Delete
                </button>
            </div>
        </div>
    </div>

</div>
