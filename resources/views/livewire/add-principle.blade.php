<div class="flex-1 overflow-auto">
  <div class="max-w-8xl mx-auto">
    <!-- Header with Back Button -->
    <div class="flex justify-between items-center mb-6">
      <a href="{{ route('principles') }}" 
          class="bg-[#fff] px-5 py-3 rounded-md shadow hover:bg-[#c71313] text-sm hover:text-white transition inline-flex items-center back-btn">
            <svg width="7" height="11" viewBox="0 0 7 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
            <path d="M6 10.5C6 10.5 1 6.81758 1 5.5C1 4.18233 6 0.5 6 0.5" stroke="#808080" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
         Back
      </a>
    </div>
    
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
      <h2  class="text-[24px] font-[500]  mb-4 text-[#EB1C24]">Add Principle</h2>


      <form wire:submit.prevent="save" class="space-y-4">
          <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
              <input type="text" wire:model="title" placeholder="Enter principle title" 
                     class="w-full border-gray-300 rounded p-2 focus:ring-red-500 focus:border-red-500" />
              @error('title') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
          </div>

          <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea wire:model="description" rows="4" placeholder="Enter description" 
                        class="w-full border-gray-300 rounded p-2 focus:ring-red-500 focus:border-red-500"></textarea>
              @error('description') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
          </div>

		  <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
              <input type="number" min="1" wire:model="priority"
                    placeholder="Enter priority number"
                    class="w-full border-gray-300 rounded p-2 focus:ring-red-500 focus:border-red-500" />
              @error('priority') 
                  <p class="text-red-500 text-sm mt-1">{{ $message }}</p> 
              @enderror
          </div>

     {{-- Success Message --}}
    @if(session()->has('message'))
    <div x-data="{ show: true }" x-show="show" 
         x-init="setTimeout(() => show = false, 3000)"
         class="m-4 text-red-600 font-medium">
        {{ session('message') }}
    </div>
@endif
          <div class="flex gap-2">
              <button type="submit" class=" px-4 py-3 text-[#fff] rounded bg-[#EB1C24] rounded-[8px]">
                  Add
              </button>
              <a href="{{ route('principles') }}" class="bg-[#F8F9FA] text-[#808080] hover:bg-gray-400 px-4 py-3 rounded-[8px]">
                  Cancel
              </a>
          </div>
      </form>
    </div>
  </div>
</div>
