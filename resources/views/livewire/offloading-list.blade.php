<div>
    <div class="max-w-6xl mx-auto p-6">
        <!-- Header -->
        <div class="mb-6 flex justify-between items-center">
            <h2 class="text-2xl font-semibold text-gray-900">My Offloading Feedback</h2>
            <a href="{{ route('offloading.create') }}"
               class="bg-[#EB1C24] text-white px-4 py-2 rounded-md hover:bg-[#c71313] transition">
                + New Feedback
            </a>
        </div>

        <!-- Feedbacks List -->
        @if(count($feedbacks) > 0)
            <div class="space-y-4">
                @foreach($feedbacks as $feedback)
                    <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <h3 class="text-lg font-semibold text-gray-900">
                                        Feedback #{{ $feedback['id'] }}
                                    </h3>
                                    <span class="px-2 py-1 text-xs rounded-full font-medium
                                        {{ $feedback['display_status'] === 'Completed' ? 'bg-green-100 text-green-800' :
                                           ($feedback['display_status'] === 'In Progress' ? 'bg-blue-100 text-blue-800' : 'bg-orange-100 text-orange-800') }}">
                                        {{ $feedback['display_status'] }}
                                    </span>
                                </div>

                                <p class="text-gray-700 mb-3 line-clamp-2">
                                    {{ \Illuminate\Support\Str::limit($feedback['message'], 150) }}
                                </p>

                                <div class="flex items-center gap-4 text-sm text-gray-500">
                                    <span>Submitted: {{ $feedback['created_at'] }}</span>
                                    @if($feedback['has_messages'])
                                        <span class="flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                            </svg>
                                            Last message: {{ $feedback['last_message_date'] }}
                                        </span>
                                        @if($feedback['has_file'])
                                            <span class="flex items-center gap-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                                Attachment
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-orange-600 font-medium">No responses yet</span>
                                    @endif
                                </div>
                            </div>

                            <div class="ml-4">
                                <a href="{{ route('offloading.chat', ['feedbackId' => $feedback['id']]) }}"
                                   class="bg-[#EB1C24] text-white px-4 py-2 rounded-md hover:bg-[#c71313] transition inline-flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                    </svg>
                                    {{ $feedback['has_messages'] ? 'View Chat' : 'Start Chat' }}
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="bg-white rounded-lg shadow p-12 text-center">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">No feedback submissions yet</h3>
                <p class="text-gray-500 mb-6">Start by submitting your first offloading feedback.</p>
                <a href="{{ route('offloading.create') }}"
                   class="bg-[#EB1C24] text-white px-6 py-3 rounded-md hover:bg-[#c71313] transition inline-flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Submit Your First Feedback
                </a>
            </div>
        @endif
    </div>
</div>

