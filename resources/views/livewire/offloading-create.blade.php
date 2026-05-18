<div>
        <div class="max-w-4xl mx-auto p-6">
            <!-- Alert Messages -->
            @if($alertMessage)
                <div class="mb-6 p-4 rounded-lg border {{ $alertType === 'success' ? 'bg-green-100 border-green-300 text-green-800' : 'bg-red-100 border-red-300 text-red-800' }}">
                    <div class="flex items-center justify-between">
                        <span>{{ $alertMessage }}</span>
                        <button wire:click="$set('alertMessage', '')" class="text-lg font-bold">&times;</button>
                    </div>
                </div>
            @endif

            <!-- Info Box -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <p class="text-sm text-blue-800">
                            <strong>What is Offloading?</strong> Share your thoughts, concerns, feedback, or issues in real-time.
                            Your feedback helps improve our organization. You'll earn <strong>+100 EI Score points</strong> for each submission.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Form -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <form wire:submit.prevent="submit">
                    <!-- Message Field -->
                    <div class="mb-6">
                        <label for="message" class="block text-sm font-medium text-gray-700 mb-2">
                            Your Feedback <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            wire:model="message"
                            id="message"
                            rows="6"
                            class="w-full border border-gray-300 rounded-md px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#EB1C24] focus:border-transparent"
                            placeholder="Share your thoughts, concerns, or feedback here..."
                        ></textarea>
                        @if($errors->has('message'))
                            <p class="mt-1 text-sm text-red-600">{{ $errors->first('message') }}</p>
                        @endif
                        <p class="mt-1 text-xs text-gray-500">Minimum 10 characters, maximum 2000 characters</p>
                    </div>

                    <!-- Image Upload -->
                    <div class="mb-6">
                        <label for="image" class="block text-sm font-medium text-gray-700 mb-2">
                            Attach Image (Optional)
                        </label>
                        <div class="flex items-center space-x-4">
                            <label for="image" class="cursor-pointer bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-md border border-gray-300 text-sm font-medium text-gray-700 transition">
                                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                Choose Image
                            </label>
                            <input
                                type="file"
                                wire:model="image"
                                id="image"
                                accept="image/*"
                                class="hidden"
                            >
                            @if($imagePreview)
                                <div class="relative inline-block">
                                    <img src="{{ $imagePreview }}" alt="Preview" class="h-20 w-20 object-cover rounded border">
                                    <button
                                        type="button"
                                        wire:click="removeImage"
                                        class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600"
                                    >
                                        ×
                                    </button>
                                </div>
                            @endif
                        </div>
                        @if($errors->has('image'))
                            <p class="mt-1 text-sm text-red-600">{{ $errors->first('image') }}</p>
                        @endif
                        <p class="mt-1 text-xs text-gray-500">Maximum file size: 2MB. Supported formats: JPG, PNG, GIF</p>
                    </div>

                    <!-- SWOT Category -->
                    <div class="mb-6">
                        <label for="SWOT" class="block text-sm font-medium text-gray-700 mb-2">
                            SWOT Category (Optional)
                        </label>
                        <select
                            wire:model="SWOT"
                            id="SWOT"
                            class="w-full border border-gray-300 rounded-md px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#EB1C24] focus:border-transparent"
                        >
                            <option value="">-- Select Category --</option>
                            <option value="Strength">Strength</option>
                            <option value="Weakness">Weakness</option>
                            <option value="Opportunity">Opportunity</option>
                            <option value="Threat">Threat</option>
                        </select>
                        @if($errors->has('SWOT'))
                            <p class="mt-1 text-sm text-red-600">{{ $errors->first('SWOT') }}</p>
                        @endif
                    </div>

                    <!-- Theme Selection -->
                    @if(count($themes) > 0)
                        <div class="mb-6">
                            <label for="themeId" class="block text-sm font-medium text-gray-700 mb-2">
                                Link to Theme (Optional)
                            </label>
                            <select
                                wire:model="themeId"
                                id="themeId"
                                class="w-full border border-gray-300 rounded-md px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#EB1C24] focus:border-transparent"
                            >
                                <option value="">-- Select Theme --</option>
                                @foreach($themes as $theme)
                                    <option value="{{ $theme->id }}">{{ $theme->title }}</option>
                                @endforeach
                            </select>
                            @if($errors->has('themeId'))
                                <p class="mt-1 text-sm text-red-600">{{ $errors->first('themeId') }}</p>
                            @endif
                        </div>
                    @endif

                    <!-- Submit Button -->
                    <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                        <div class="text-sm text-gray-600">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            You'll earn <strong>+100 EI Score points</strong> for submitting feedback
                        </div>
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="bg-[#EB1C24] text-white px-6 py-3 rounded-md hover:bg-[#c71313] transition font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span wire:loading.remove>Submit Feedback</span>
                            <span wire:loading>
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Submitting...
                            </span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Help Section -->
            <div class="mt-6 bg-gray-50 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Need Help?</h3>
                <ul class="space-y-2 text-sm text-gray-600">
                    <li class="flex items-start">
                        <svg class="w-5 h-5 text-[#EB1C24] mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>Your feedback is confidential and will be reviewed by administrators</span>
                    </li>
                    <li class="flex items-start">
                        <svg class="w-5 h-5 text-[#EB1C24] mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>You can track your submissions and communicate with admins through the chat feature</span>
                    </li>
                    <li class="flex items-start">
                        <svg class="w-5 h-5 text-[#EB1C24] mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>Administrators will respond to your feedback and keep you updated on actions taken</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

