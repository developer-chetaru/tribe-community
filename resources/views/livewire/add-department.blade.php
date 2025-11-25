<x-slot name="header">
 <h2 class="text-[24px] md:text-[30px] capitalize font-bold text-[#EB1C24]">
    Department
  </h2>
</x-slot>
<div class="flex-1 overflow-auto">
  <div class="">
    <!-- Header with Back Button -->
    <div class="flex justify-between items-center mb-6">
      <a href="{{ route('department') }}" 
          class="bg-[#fff] px-5 py-3 rounded-md shadow hover:bg-[#c71313] text-sm hover:text-white transition inline-flex items-center back-btn">
            <svg width="7" height="11" viewBox="0 0 7 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
            <path d="M6 10.5C6 10.5 1 6.81758 1 5.5C1 4.18233 6 0.5 6 0.5" stroke="#808080" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
         Back
      </a>
    </div>

    <!-- Card -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
    <h2 class="text-[24px] font-[500]  mb-4 text-[#EB1C24]">Add Department</h2>

    @if (session()->has('message'))
        <div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4">
            {{ session('message') }}
        </div>
    @endif

    <form wire:submit.prevent="save" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Department Name</label>
            <input type="text" wire:model="name"
                   class="w-full border-gray-300 rounded p-2 focus:ring-red-500 focus:border-red-500">
            @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex gap-2">
            <button type="submit" 
                    class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded">
                Save
            </button>
          <a href="{{ route('department') }}" wire:navigate
   class="bg-gray-300 hover:bg-gray-400 px-4 py-2 rounded">
    Cancel
</a>
        </div>
    </form>
</div></div>
