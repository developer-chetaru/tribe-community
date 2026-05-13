<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Edit Theme - {{ $theme->title }}
        </h2>
    </x-slot>

    <div class="flex-1 overflow-auto">
        <div class="max-w-4xl mx-auto p-4">
            <div class="mb-4">
                <a href="{{ route('admin.theme-list', ['orgId' => $theme->orgId]) }}"
                   class="bg-[#fff] px-4 py-3 rounded-md shadow hover:bg-[#c71313] text-sm hover:text-white transition inline-flex items-center">
                    <svg width="7" height="11" viewBox="0 0 7 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
                        <path d="M6 10.5C6 10.5 1 6.81758 1 5.5C1 4.18233 6 0.5 6 0.5" stroke="#808080" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Back to Themes
                </a>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <form action="{{ route('admin.update-theme', ['themeId' => $theme->id]) }}" method="POST" class="space-y-6">
                    @csrf
                    @method('POST')

                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">
                            Theme Title <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               id="title"
                               name="title"
                               required
                               value="{{ old('title', $theme->title) }}"
                               class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]">
                        @error('title')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">
                            Description
                        </label>
                        <textarea id="description"
                                  name="description"
                                  rows="4"
                                  class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]">{{ old('description', $theme->description) }}</textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700 mb-1">
                                Type
                            </label>
                            <input type="number"
                                   id="type"
                                   name="type"
                                   min="0"
                                   value="{{ old('type', $theme->type) }}"
                                   class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]">
                        </div>
                        <div>
                            <label for="submission" class="block text-sm font-medium text-gray-700 mb-1">
                                Submission Type
                            </label>
                            <input type="text"
                                   id="submission"
                                   name="submission"
                                   value="{{ old('submission', $theme->submission) }}"
                                   class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]">
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Current Risk Assessment</h3>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="currentLikelihood" class="block text-sm font-medium text-gray-700 mb-1">
                                    Current Likelihood (0-5)
                                </label>
                                <select id="currentLikelihood"
                                        name="currentLikelihood"
                                        class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]">
                                    <option value="">Select...</option>
                                    <option value="0" {{ old('currentLikelihood', $theme->currentLikelihood) == '0' ? 'selected' : '' }}>0 - Not applicable</option>
                                    <option value="1" {{ old('currentLikelihood', $theme->currentLikelihood) == '1' ? 'selected' : '' }}>1 - Very unlikely</option>
                                    <option value="2" {{ old('currentLikelihood', $theme->currentLikelihood) == '2' ? 'selected' : '' }}>2 - Unlikely</option>
                                    <option value="3" {{ old('currentLikelihood', $theme->currentLikelihood) == '3' ? 'selected' : '' }}>3 - Possible</option>
                                    <option value="4" {{ old('currentLikelihood', $theme->currentLikelihood) == '4' ? 'selected' : '' }}>4 - Likely</option>
                                    <option value="5" {{ old('currentLikelihood', $theme->currentLikelihood) == '5' ? 'selected' : '' }}>5 - Almost certain</option>
                                </select>
                            </div>
                            <div>
                                <label for="currentConsequence" class="block text-sm font-medium text-gray-700 mb-1">
                                    Current Consequence (0-5)
                                </label>
                                <select id="currentConsequence"
                                        name="currentConsequence"
                                        class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]">
                                    <option value="">Select...</option>
                                    <option value="0" {{ old('currentConsequence', $theme->currentConsequence) == '0' ? 'selected' : '' }}>0 - Not applicable</option>
                                    <option value="1" {{ old('currentConsequence', $theme->currentConsequence) == '1' ? 'selected' : '' }}>1 - Negligible</option>
                                    <option value="2" {{ old('currentConsequence', $theme->currentConsequence) == '2' ? 'selected' : '' }}>2 - Minor</option>
                                    <option value="3" {{ old('currentConsequence', $theme->currentConsequence) == '3' ? 'selected' : '' }}>3 - Moderate</option>
                                    <option value="4" {{ old('currentConsequence', $theme->currentConsequence) == '4' ? 'selected' : '' }}>4 - Major</option>
                                    <option value="5" {{ old('currentConsequence', $theme->currentConsequence) == '5' ? 'selected' : '' }}>5 - Catastrophic</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4 p-3 bg-blue-50 rounded-md">
                            <p class="text-sm text-gray-700">
                                <strong>Current Risk Rating:</strong>
                                <span id="riskRatingDisplay" class="font-semibold text-blue-700">{{ $theme->currentRiskRating ?? '-' }}</span>
                                <span class="text-xs text-gray-500 ml-2">(Likelihood × Consequence)</span>
                            </p>
                            @if($theme->initialRiskRating)
                                <p class="text-xs text-gray-500 mt-1">
                                    Initial Risk Rating: {{ $theme->initialRiskRating }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="linkedAction" class="block text-sm font-medium text-gray-700 mb-1">
                                Linked Action IDs
                            </label>
                            <input type="text"
                                   id="linkedAction"
                                   name="linkedAction"
                                   value="{{ old('linkedAction', $theme->linkedAction) }}"
                                   class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]"
                                   placeholder="Comma-separated action IDs">
                        </div>
                        <div>
                            <label for="themeStatus" class="block text-sm font-medium text-gray-700 mb-1">
                                Theme Status
                            </label>
                            <select id="themeStatus"
                                    name="themeStatus"
                                    class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]">
                                <option value="Open" {{ old('themeStatus', $theme->themeStatus) == 'Open' ? 'selected' : '' }}>Open</option>
                                <option value="Closed" {{ old('themeStatus', $theme->themeStatus) == 'Closed' ? 'selected' : '' }}>Closed</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="comment" class="block text-sm font-medium text-gray-700 mb-1">
                            Comments
                        </label>
                        <textarea id="comment"
                                  name="comment"
                                  rows="3"
                                  class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]">{{ old('comment', $theme->comment) }}</textarea>
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">
                            Status
                        </label>
                        <select id="status"
                                name="status"
                                class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]">
                            <option value="Active" {{ old('status', $theme->status) == 'Active' ? 'selected' : '' }}>Active</option>
                            <option value="Inactive" {{ old('status', $theme->status) == 'Inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>

                    <div class="flex justify-end gap-4 pt-4 border-t border-gray-200">
                        <a href="{{ route('admin.theme-list', ['orgId' => $theme->orgId]) }}"
                           class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition">
                            Cancel
                        </a>
                        <button type="submit"
                                class="px-6 py-2 bg-[#EB1C24] text-white rounded-md hover:bg-[#c71313] transition">
                            Update Theme
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function calculateRiskRating() {
            const likelihood = parseInt(document.getElementById('currentLikelihood').value) || 0;
            const consequence = parseInt(document.getElementById('currentConsequence').value) || 0;
            const rating = likelihood * consequence;

            const display = document.getElementById('riskRatingDisplay');
            if (likelihood > 0 && consequence > 0) {
                display.textContent = rating;

                if (rating <= 5) {
                    display.className = 'font-semibold text-green-700';
                } else if (rating <= 12) {
                    display.className = 'font-semibold text-yellow-700';
                } else if (rating <= 20) {
                    display.className = 'font-semibold text-orange-700';
                } else {
                    display.className = 'font-semibold text-red-700';
                }
            } else {
                display.textContent = '-';
                display.className = 'font-semibold text-blue-700';
            }
        }

        document.getElementById('currentLikelihood').addEventListener('change', calculateRiskRating);
        document.getElementById('currentConsequence').addEventListener('change', calculateRiskRating);
        calculateRiskRating();
    </script>
    @endpush
</x-app-layout>

