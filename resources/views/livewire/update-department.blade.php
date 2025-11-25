<x-slot name="header">
 <h2 class="text-2xl font-bold capitalize text-[#ff2323]">
    Department
  </h2>
</x-slot>
<div class="flex-1 overflow-auto">
  <div class="max-w-8xl mx-auto">
    <!-- Header with Back Button -->
    <div class="flex justify-between items-center mb-6">
      <a href="{{ route('department') }}" 
          class="ml-2 bg-white px-6 py-2 rounded">
         Back
      </a>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
    <h2 class="text-lg font-bold text-red-600 mb-4">Edit Department</h2>

    @if (session()->has('message'))
        <div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4">
            {{ session('message') }}
        </div>
    @endif

    <form wire:submit="update" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Department Name</label>
            <input type="text" wire:model="name"
                   class="w-full border-gray-300 rounded p-2 focus:ring-red-500 focus:border-red-500">
            @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex gap-2">
            <button type="submit"
                    class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded">
                Update
            </button>
            <a href="{{ route('department') }}" wire:navigate
               class="bg-gray-300 hover:bg-gray-400 text-black px-4 py-2 rounded">
                Cancel
            </a>
        </div>
    </form>
</div>
</div></div>
