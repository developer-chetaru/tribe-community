<x-slot name="header">
 <h2 class="text-[24px] md:text-[30px] capitalize font-bold text-[#EB1C24]">
   Add Learning Type
  </h2>
</x-slot>

<div class="flex-1 overflow-auto">
  <div class="max-w-8xl mx-auto">
    <!-- Header with Back Button -->
    <div class="flex justify-between items-center mb-6">
      <a href="{{ route('learningtype.list') }}" 
          class="bg-[#fff] px-5 py-3 rounded-md shadow hover:bg-[#c71313] text-sm hover:text-white transition inline-flex items-center back-btn">
            <svg width="7" height="11" viewBox="0 0 7 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
            <path d="M6 10.5C6 10.5 1 6.81758 1 5.5C1 4.18233 6 0.5 6 0.5" stroke="#808080" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
         Back
      </a>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 p-6">
    <h2  class="text-[24px] font-[500]  mb-5 text-[#EB1C24]">Add Learning Type</h2>



    <form wire:submit.prevent="save" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
            <input type="text" wire:model="title" class="w-full border-gray-300 rounded p-2 focus:ring-red-500 focus:border-red-500" />
            @error('title') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Score</label>
            <input type="number" wire:model="score" class="w-full border-gray-300 rounded p-2 focus:ring-red-500 focus:border-red-500" />
            @error('score') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
            <input type="number" wire:model="priority" class="w-full border-gray-300 rounded p-2 focus:ring-red-500 focus:border-red-500" />
            @error('priority') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

    @if(session()->has('message'))
    <div x-data="{ show: true }" x-show="show" 
         x-init="setTimeout(() => show = false, 3000)"
         class="m-4 text-red-600 font-medium">
        {{ session('message') }}
    </div>
@endif


 <div class="flex gap-2">
                    <button type="submit" 
                            class=" px-6 py-3 text-[#fff] text-[16px] font-[500]  bg-[#EB1C24] rounded-[8px] border border-[#EB1C24]">
                        Save
                    </button>
                    <a href="{{ route('learningtype.list') }}" 
                       class="px-6 py-3 text-[#EB1C24] text-[16px] font-[500] border border-[#EB1C24] rounded-[8px] hover:bg-[#EB1C24] hover:text-[#fff]">
                        Cancel
                    </a>
                    <button type="reset" 
                            class="px-4 py-2 text-[16px] text-[#808080] font-[400]">
                        Reset All
                    </button>
                </div>
    </form>
</div>
</div>
</div>
