<x-slot name="header">
    <h2 class="text-[24px] md:text-[30px] capitalize font-bold text-[#EB1C24]">
        Edit Industry
    </h2>
</x-slot>

<div class="flex-1 overflow-auto">
    <div>
        <!-- Back Button -->
        <div class="flex justify-between items-center mb-6">
            <a href="{{ route('industries.list') }}"
               class="bg-[#fff] px-5 py-3 rounded-md shadow hover:bg-[#c71313] text-sm hover:text-white transition inline-flex items-center">
                ← Back
            </a>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h2 class="text-[24px] font-[500] mb-4 text-[#EB1C24]">Update Industry</h2>

            @if (session()->has('message'))
                <div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4">
                    {{ session('message') }}
                </div>
            @endif

            <form wire:submit.prevent="update" class="space-y-4">
                <!-- Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Industry Name</label>
                    <input type="text" wire:model="name"
                           class="w-full border-gray-300 rounded p-2 focus:ring-red-500 focus:border-red-500">
                    @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select wire:model="status"
                            class="w-full border-gray-300 rounded p-2 focus:ring-red-500 focus:border-red-500">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                    @error('status') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Buttons -->
                <div class="flex gap-2">
                    <button type="submit"
                            class="px-4 py-3 text-white rounded bg-[#EB1C24] rounded-[8px]">
                        Update
                    </button>
                    <a href="{{ route('industries.list') }}"
                       class="bg-[#F8F9FA] text-[#808080] hover:bg-gray-400 px-4 py-3 rounded-[8px]">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
