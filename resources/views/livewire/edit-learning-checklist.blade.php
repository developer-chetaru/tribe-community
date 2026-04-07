<div 
    x-data="{ selectedType: null }" 
    x-init="selectedType = '{{ strtolower($learningTypes->firstWhere('id', $output)?->title ?? '') }}'">

    <x-slot name="header">
         <h2 class="text-[24px] md:text-[30px] capitalize font-bold text-[#EB1C24]">
            Learning Checklist
        </h2>
    </x-slot>

    <div class="flex-1 overflow-auto">
        <div class="max-w-8xl mx-auto">

            <div class="flex justify-between items-center mb-6">
                <a href="{{ route('learningchecklist.list') }}" 
        class="bg-[#fff] px-5 py-3 rounded-md shadow hover:bg-[#c71313] text-sm hover:text-white transition inline-flex items-center back-btn">
            <svg width="7" height="11" viewBox="0 0 7 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
            <path d="M6 10.5C6 10.5 1 6.81758 1 5.5C1 4.18233 6 0.5 6 0.5" stroke="#808080" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
                    Back
                </a>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h2  class="text-[24px] font-[500]  mb-5 text-[#EB1C24]">Edit Learning Checklist</h2>

                @if (session()->has('message'))
                    <div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4">
                        {{ session('message') }}
                    </div>
                @endif

                <form wire:submit.prevent="save" class="space-y-4">

                    <!-- Title + Principle -->
                    <div class="flex gap-4">
                        <div class="w-1/2">
                            <input type="text" placeholder="Title" wire:model="title" 
                                class="w-full border rounded p-2 focus:ring-red-500 focus:border-red-500" />
                            @error('title') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

     <div 
    x-data="multiSelectDropdown()" 
    class="w-1/2 relative select-principle"
>
    <!-- Trigger -->
    <button 
        @click="open = !open" 
        type="button"
        class="w-full border rounded p-2 text-left flex justify-between items-center border-[#808080]"
    >
        <span x-text="selectedLabel()"></span>
        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <!-- Dropdown -->
    <div 
        x-show="open" 
        @click.outside="open = false" 
        class="absolute z-10 mt-1 w-full bg-white border rounded shadow-lg max-h-60 overflow-y-auto"
    >
        <ul>
            <!-- All option -->
            <li class="flex items-center px-3 py-2 cursor-pointer hover:bg-gray-100">
                <input 
                    type="checkbox" 
                    class="mr-2 text-red-500 focus:ring-red-500" 
                    :checked="allSelected"
                    @change="toggleAll()"
                >
                <span>All</span>
            </li>

            <!-- Individual options -->
            <template x-for="(item, index) in items" :key="item.id">
                <li class="flex items-center px-3 py-2 cursor-pointer hover:bg-gray-100">
                    <input 
                        type="checkbox" 
                        class="mr-2 text-red-500 focus:ring-red-500" 
                        :id="'principle-' + item.id"
                        :checked="item.selected"
                        @change="toggleItem(index)"
                    >
                    <label 
                        :for="'principle-' + item.id" 
                        class="cursor-pointer" 
                        x-text="item.title">
                    </label>
                </li>
            </template>
        </ul>
    </div>
</div>

<script>
function multiSelectDropdown() {
    return {
        open: false,
        items: @json($principlesArray),
        allSelected: false,
        principleId: @entangle('principleId').live, 

        init() {
            // Sync initial state from Livewire
            if (this.principleId && Array.isArray(this.principleId)) {
                this.items.forEach(item => {
                    item.selected = this.principleId.includes(item.id);
                });
                this.allSelected = this.items.every(i => i.selected);
            }
        },

        toggleItem(index) {
            this.items[index].selected = !this.items[index].selected;
            this.allSelected = this.items.every(i => i.selected);
            this.syncWithLivewire();
        },

        toggleAll() {
            this.allSelected = !this.allSelected;
            this.items.forEach(i => i.selected = this.allSelected);
            this.syncWithLivewire();
        },

        selectedLabel() {
            if (this.allSelected) return 'All';
            let selectedItems = this.items.filter(i => i.selected).map(i => i.title);
            return selectedItems.length ? selectedItems.join(', ') : 'Select Principle';
        },

        selectedIds() {
            return this.items.filter(i => i.selected).map(i => i.id);
        },

        syncWithLivewire() {
            this.principleId = this.selectedIds(); 
            console.log("Synced IDs:", this.principleId);
        }
    }
}
</script>

                    </div>

                    <!-- Learning Type -->
                    <div>
                        <select 
                            wire:model="output" 
                            x-on:change="selectedType = $event.target.options[$event.target.selectedIndex].text.toLowerCase()"
                            class="w-full border rounded p-2 focus:ring-red-500 focus:border-red-500">
                            <option value="">Select Learning Type</option>
                            @foreach ($learningTypes as $lt)
                                <option value="{{ $lt->id }}">{{ $lt->title }}</option>
                            @endforeach
                        </select>
                        @error('output') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- Description -->
                    <div>
                        <textarea wire:model="description" rows="3" placeholder="Description"
                            class="w-full border rounded p-2 focus:ring-red-500 focus:border-red-500"></textarea>
                        @error('description') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- Video Link -->
                    <div 
                        x-show="selectedType && selectedType.includes('video')" 
                        class="mt-3">
                        <input type="text" wire:model="link" placeholder="Link" 
                            class="w-full border rounded p-2 focus:ring-red-500 focus:border-red-500" />
                        @error('link') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- Document Upload -->
                    <div 
                        x-show="selectedType && !selectedType.includes('video')" 
                        class="mt-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Upload Document (PDF)
                        </label>

                        <div class="flex items-center justify-center border border-gray-300 rounded-md p-3 bg-white">
                            <input 
                                type="file" 
                                wire:model="documentFile" 
                                accept="application/pdf"
                                class="text-sm text-gray-600
                                       file:mr-4 file:py-1.5 file:px-4
                                       file:rounded-md file:border-0
                                       file:text-sm file:font-semibold
                                       file:bg-gray-100 file:text-gray-700
                                       hover:file:bg-gray-200"
                            />
                        </div>

                        @error('documentFile')
                            <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                        @enderror

                        @if($documentFile)
                            <p class="mt-2 text-sm text-gray-600">
                                Selected file: {{ $documentFile->getClientOriginalName() }}
                            </p>
                        @elseif($document)
                            <p class="mt-2 text-sm text-gray-600">
                                Current document: 
                                <a href="{{ asset('storage/'.$document) }}" 
                                target="_blank" 
                                class="text-red-600 underline">
                                {{ basename($document) }}
                                </a>
                            </p>
                        @endif
                    </div>

                    <!-- Buttons -->
                    <div class="flex gap-2">
                        <button type="submit" 
                            class=" px-6 py-3 text-[#fff] text-[16px] font-[500]  bg-[#EB1C24] rounded-[8px] border border-[#EB1C24]">
                            Save
                        </button>
                        <a href="{{ route('learningchecklist.list') }}" 
                            class="px-6 py-3 text-[#EB1C24] text-[16px] font-[500] border border-[#EB1C24] rounded-[8px] hover:bg-[#EB1C24] hover:text-[#fff]">
                            Cancel
                        </a>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>
