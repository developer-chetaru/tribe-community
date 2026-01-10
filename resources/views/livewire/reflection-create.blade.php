<div>
    <div class="p-6">
        {{-- Show Back To Reflection button only after reflection is added --}}
        @if($reflectionAdded)
        <div class="bg-white shadow-md rounded-lg p-4 max-w-xs mb-6"> 
            <div class="mb-0">
                <a href="{{ route('admin.reflections.index') }}" class="text-gray-500 hover:text-gray-700 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back To Reflection
                </a>
            </div>
        </div>
        @endif
        
        <div class="bg-white shadow-lg rounded-lg p-6 max-w-4xl"> 

            {{-- Title --}}
            <h2 class="text-2xl font-semibold mb-6 text-red-600">Add New Reflection</h2>

            @if($alertMessage)
                <div 
                    x-data="{ show: true }" 
                    x-show="show" 
                    x-init="setTimeout(() => show = false, 2000)" 
                    x-transition 
                    class="p-3 mb-4 rounded {{ $alertType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                    {{ $alertMessage }}
                </div>
            @endif

            <form wire:submit.prevent="submit" class="space-y-4">
                {{-- Topic Input --}}
                <div>
                    <input 
                        type="text" 
                        wire:model.defer="topic" 
                        class="w-full border border-gray-300 rounded p-3 focus:ring-red-500 focus:border-red-500 placeholder-gray-500" 
                        placeholder="Topic"
                    >
                    @error('topic') <span class="text-red-600 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- Details/Message Textarea --}}
                <div>
                    <textarea 
                        wire:model.defer="message" 
                        class="w-full border border-gray-300 rounded p-3 focus:ring-red-500 focus:border-red-500 placeholder-gray-500" 
                        rows="8" 
                        placeholder="Details..."
                    ></textarea>
                    @error('message') <span class="text-red-600 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- Submit Button --}}
                <div class="pt-4">
                    <button 
                        type="submit" 
                        class="bg-red-600 text-white font-semibold px-6 py-2 rounded-lg hover:bg-red-700 transition duration-150 ease-in-out shadow-md"
                    >
                        Send
                    </button>
                </div>
            </form>
        </div> 
        {{-- END OF WHITE CARD CONTAINER --}}
    </div>
</div>