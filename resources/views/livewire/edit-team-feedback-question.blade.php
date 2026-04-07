<x-slot name="header">
 <h2 class="text-2xl font-bold capitalize text-[#ff2323]">
   Team Feedback Question
  </h2>
</x-slot>
<div class="flex-1 overflow-auto">
  <div class="max-w-8xl mx-auto">
    <!-- Header with Back Button -->
    <div class="flex justify-between items-center mb-6">
      <a href="{{ route('team-feedback.list') }}" 
          class="ml-2 bg-white px-6 py-2 rounded">
         Back
      </a>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
    <h2 class="text-xl font-bold text-red-600 mb-4">Edit Team Feedback Question</h2>

    <form wire:submit.prevent="update" class="space-y-4">

        <div>
            <label class="block font-medium mb-1">Question</label>
            <textarea wire:model.defer="question_text" rows="4" 
                      class="w-full border rounded p-2 focus:ring-red-500 focus:border-red-500"></textarea>
            @error('question_text') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block font-medium mb-1">Principle</label>
            <select wire:model.defer="principle_id" 
                    class="w-full border rounded p-2 focus:ring-red-500 focus:border-red-500">
                <option value="">-- Select Principle --</option>
                @foreach($principles as $p)
                    <option value="{{ $p->id }}">{{ $p->title }}</option>
                @endforeach
            </select>
            @error('principle_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex gap-2">
            <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded">Update</button>
            <a href="{{ route('team-feedback.list') }}" class="bg-gray-300 hover:bg-gray-400 text-black px-4 py-2 rounded">Cancel</a>
        </div>
    </form>
</div></div></div>
