<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Add New Theme
        </h2>
    </x-slot>

    <div class="flex-1 overflow-auto">
        <div class="max-w-4xl mx-auto p-4">
            <div class="mb-4">
                <a href="{{ route('admin.theme-list', ['orgId' => $orgId]) }}"
                   class="bg-[#fff] px-4 py-3 rounded-md shadow hover:bg-[#c71313] text-sm hover:text-white transition inline-flex items-center">
                    <svg width="7" height="11" viewBox="0 0 7 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
                        <path d="M6 10.5C6 10.5 1 6.81758 1 5.5C1 4.18233 6 0.5 6 0.5" stroke="#808080" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Back to Themes
                </a>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <form action="{{ route('admin.store-theme') }}" method="POST" class="space-y-6">
                    @csrf
                    <input type="hidden" name="orgId" value="{{ $orgId }}">

                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">
                            Theme Title <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               id="title"
                               name="title"
                               required
                               class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]"
                               placeholder="Enter theme title">
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
                                  class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]"
                                  placeholder="Enter theme description"></textarea>
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
                                   class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]"
                                   placeholder="Type ID">
                        </div>
                        <div>
                            <label for="submission" class="block text-sm font-medium text-gray-700 mb-1">
                                Submission Type
                            </label>
                            <input type="text"
                                   id="submission"
                                   name="submission"
                                   class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]"
                                   placeholder="Submission type">
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Initial Risk Assessment</h3>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="initialLikelihood" class="block text-sm font-medium text-gray-700 mb-1">
                                    Likelihood (0-5)
                                </label>
                                <select id="initialLikelihood"
                                        name="initialLikelihood"
                                        class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]">
                                    <option value="">Select...</option>
                                    <option value="0">0 - Not applicable</option>
                                    <option value="1">1 - Very unlikely</option>
                                    <option value="2">2 - Unlikely</option>
                                    <option value="3">3 - Possible</option>
                                    <option value="4">4 - Likely</option>
                                    <option value="5">5 - Almost certain</option>
                                </select>
                            </div>
                            <div>
                                <label for="initialConsequence" class="block text-sm font-medium text-gray-700 mb-1">
                                    Consequence (0-5)
                                </label>
                                <select id="initialConsequence"
                                        name="initialConsequence"
                                        class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]">
                                    <option value="">Select...</option>
                                    <option value="0">0 - Not applicable</option>
                                    <option value="1">1 - Negligible</option>
                                    <option value="2">2 - Minor</option>
                                    <option value="3">3 - Moderate</option>
                                    <option value="4">4 - Major</option>
                                    <option value="5">5 - Catastrophic</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4 p-3 bg-blue-50 rounded-md">
                            <p class="text-sm text-gray-700">
                                <strong>Risk Rating:</strong>
                                <span id="riskRatingDisplay" class="font-semibold text-blue-700">-</span>
                                <span class="text-xs text-gray-500 ml-2">(Likelihood × Consequence)</span>
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-4 pt-4 border-t border-gray-200">
                        <a href="{{ route('admin.theme-list', ['orgId' => $orgId]) }}"
                           class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition">
                            Cancel
                        </a>
                        <button type="submit"
                                class="px-6 py-2 bg-[#EB1C24] text-white rounded-md hover:bg-[#c71313] transition">
                            Create Theme
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function calculateRiskRating() {
            const likelihood = parseInt(document.getElementById('initialLikelihood').value) || 0;
            const consequence = parseInt(document.getElementById('initialConsequence').value) || 0;
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

        document.getElementById('initialLikelihood').addEventListener('change', calculateRiskRating);
        document.getElementById('initialConsequence').addEventListener('change', calculateRiskRating);
    </script>
    @endpush
</x-app-layout>

