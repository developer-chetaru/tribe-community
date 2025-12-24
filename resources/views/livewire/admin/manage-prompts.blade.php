<x-slot name="header">
    <h2 class="text-[24px] md:text-[30px] font-semibold capitalize text-[#EB1C24]">
        Manage AI Prompts
    </h2>
</x-slot>

<div class="max-w-6xl mx-auto p-6">
    @if(session()->has('success'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
            <p class="text-green-800">{{ session('success') }}</p>
        </div>
    @endif

    @if(session()->has('error'))
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
            <p class="text-red-800">{{ session('error') }}</p>
        </div>
    @endif

    <form wire:submit.prevent="savePrompts">
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4 text-[#EB1C24]">Weekly Summary Prompt</h3>
            <p class="text-sm text-gray-600 mb-2">
                This prompt is used to generate weekly emotional summaries. Use <code class="bg-gray-100 px-1 rounded">{weekLabel}</code> and <code class="bg-gray-100 px-1 rounded">{entries}</code> as placeholders.
            </p>
            <textarea
                wire:model="weeklySummaryPrompt"
                rows="15"
                class="w-full border border-gray-300 rounded-md p-3 focus:ring-2 focus:ring-[#EB1C24] focus:border-transparent"
                placeholder="Enter weekly summary prompt template..."
            ></textarea>
            @error('weeklySummaryPrompt')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4 text-[#EB1C24]">Monthly Summary Prompt</h3>
            <p class="text-sm text-gray-600 mb-2">
                This prompt is used to generate monthly emotional summaries. Use <code class="bg-gray-100 px-1 rounded">{monthName}</code> and <code class="bg-gray-100 px-1 rounded">{entries}</code> as placeholders.
            </p>
            <textarea
                wire:model="monthlySummaryPrompt"
                rows="20"
                class="w-full border border-gray-300 rounded-md p-3 focus:ring-2 focus:ring-[#EB1C24] focus:border-transparent"
                placeholder="Enter monthly summary prompt template..."
            ></textarea>
            @error('monthlySummaryPrompt')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex justify-end space-x-3">
            <button
                type="submit"
                class="px-6 py-2 bg-[#EB1C24] text-white rounded-md hover:bg-red-700 transition-colors font-medium"
            >
                <span wire:loading.remove wire:target="savePrompts">Save Prompts</span>
                <span wire:loading wire:target="savePrompts">Saving...</span>
            </button>
        </div>
    </form>
</div>
