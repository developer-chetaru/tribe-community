<div>
    <div class="max-w-6xl mx-auto p-6">
        <!-- Alert Messages -->
        @if($alertMessage)
            <div class="mb-6 p-4 rounded-lg border {{ $alertType === 'success' ? 'bg-green-100 border-green-300 text-green-800' : 'bg-red-100 border-red-300 text-red-800' }}">
                <div class="flex items-center justify-between">
                    <span>{{ $alertMessage }}</span>
                    <button wire:click="$set('alertMessage', '')" class="text-lg font-bold">&times;</button>
                </div>
            </div>
        @endif

        <!-- Back Button -->
        <div class="mb-4">
            <a href="{{ route('offloading.list') }}"
               class="bg-white px-4 py-2 rounded-md shadow hover:bg-gray-100 text-sm transition inline-flex items-center">
                <svg width="7" height="11" viewBox="0 0 7 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
                    <path d="M6 10.5C6 10.5 1 6.81758 1 5.5C1 4.18233 6 0.5 6 0.5" stroke="#808080" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Back to My Feedback
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column: Chat Messages -->
            <div class="lg:col-span-2 bg-white rounded-lg shadow">
                <!-- Chat Header -->
                <div class="bg-gray-50 p-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Offloading Feedback #{{ $feedback->id }}
                    </h3>
                    <p class="text-xs text-gray-400 mt-1">
                        Submitted: {{ $feedback->created_at->format('d M Y, h:i A') }}
                    </p>
                </div>

                <!-- Original Message -->
                <div class="p-4 border-b border-gray-200 bg-blue-50">
                    <p class="text-sm font-medium text-gray-700 mb-2">Your Original Feedback:</p>
                    <p class="text-gray-900">{{ $feedback->message }}</p>
                    @if($feedback->image)
                        @php
                            $fileExtension = strtolower(pathinfo($feedback->image, PATHINFO_EXTENSION));
                            $isVideo = in_array($fileExtension, ['mp4', 'mov', 'avi', 'wmv', 'flv', 'webm']);
                        @endphp
                        <div class="mt-3">
                            @if($isVideo)
                                <video controls class="max-w-xs rounded-lg border border-gray-300" preload="metadata">
                                    <source src="{{ asset('uploads/iot_files/' . $feedback->image) }}" type="video/{{ $fileExtension }}">
                                    Your browser does not support the video tag.
                                </video>
                            @else
                                <img src="{{ asset('uploads/iot_files/' . $feedback->image) }}"
                                     alt="Feedback Image"
                                     class="max-w-xs rounded-lg border border-gray-300 cursor-pointer"
                                     onclick="window.open('{{ asset('uploads/iot_files/' . $feedback->image) }}', '_blank')">
                            @endif
                        </div>
                    @endif
                </div>

                <!-- Chat Messages -->
                <div wire:poll.5s="loadMessages" wire:loading.class.remove="opacity-0" id="chatMessages" class="p-4 space-y-4 max-h-[500px] overflow-y-auto bg-gray-50">
                    @foreach($messages as $msg)
                        @php
                            $initial = strtoupper(substr($msg['sender_name'], 0, 1));
                            $avatarColor = $msg['isAdmin'] ? 'bg-red-500 text-white' : 'bg-gray-300 text-gray-700';
                        @endphp
                        <div class="flex {{ $msg['isMe'] ? 'justify-end' : 'justify-start' }} items-end gap-2">

                            {{-- Avatar: left side for others --}}
                            @if(!$msg['isMe'])
                                @if($msg['sender_photo'])
                                    <img src="{{ $msg['sender_photo'] }}"
                                         class="w-8 h-8 rounded-full object-cover flex-shrink-0"
                                         alt="{{ $initial }}"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="w-8 h-8 {{ $avatarColor }} rounded-full items-center justify-center text-xs font-bold flex-shrink-0"
                                         style="display:none;">
                                        {{ $initial }}
                                    </div>
                                @else
                                    <div class="w-8 h-8 {{ $avatarColor }} rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">
                                        {{ $initial }}
                                    </div>
                                @endif
                            @endif

                            {{-- Message Bubble --}}
                            <div class="max-w-md rounded-2xl p-3 shadow-sm
                                {{ $msg['isMe']
                                    ? 'bg-[#EB1C24] text-white rounded-br-none'
                                    : 'bg-white text-gray-900 rounded-bl-none border border-gray-100' }}">
                                <p class="text-xs mb-1 {{ $msg['isMe'] ? 'text-red-100' : 'text-gray-500' }}">
                                    {{ $msg['isMe'] ? 'You' : $msg['sender_name'] }}
                                    <span class="{{ $msg['isMe'] ? 'text-red-200' : 'text-gray-400' }}">
                                        ({{ $msg['isMe'] ? 'You' : ($msg['isAdmin'] ? 'Admin' : 'User') }})
                                    </span>
                                </p>
                                @if($msg['message'])
                                    <p class="text-sm">{{ $msg['message'] }}</p>
                                @endif
                                @if($msg['file'])
                                    <div class="mt-2">
                                        @if($msg['fileType'] === 'video')
                                            <video controls class="max-w-xs rounded-lg border border-gray-300" preload="metadata">
                                                <source src="{{ $msg['file'] }}" type="video/mp4">
                                                Your browser does not support the video tag.
                                            </video>
                                        @else
                                            <img src="{{ $msg['file'] }}"
                                                 alt="Message Image"
                                                 class="max-w-xs rounded-lg border border-gray-300 cursor-pointer hover:opacity-90"
                                                 onclick="window.open('{{ $msg['file'] }}', '_blank')">
                                        @endif
                                    </div>
                                @endif
                                <p class="text-xs mt-1 {{ $msg['isMe'] ? 'text-red-200 text-right' : 'text-gray-400' }}">
                                    {{ $msg['created_at'] }}
                                </p>
                            </div>

                            {{-- Avatar: right side for me --}}
                            @if($msg['isMe'])
                                @if($msg['sender_photo'])
                                    <img src="{{ $msg['sender_photo'] }}"
                                         class="w-8 h-8 rounded-full object-cover flex-shrink-0"
                                         alt="{{ $initial }}"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="w-8 h-8 bg-[#EB1C24] text-white rounded-full items-center justify-center text-xs font-bold flex-shrink-0"
                                         style="display:none;">
                                        {{ $initial }}
                                    </div>
                                @else
                                    <div class="w-8 h-8 bg-[#EB1C24] text-white rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">
                                        {{ $initial }}
                                    </div>
                                @endif
                            @endif

                        </div>
                    @endforeach
                </div>

                <!-- Send Message Form -->
                <div class="p-4 border-t border-gray-200 bg-white">
                    <form wire:submit.prevent="sendMessage" class="space-y-2">
                        @if($filePreview)
                            <div class="relative inline-block mb-2">
                                @if($fileType === 'video')
                                    <video src="{{ $filePreview }}" class="h-20 w-20 object-cover rounded border" controls></video>
                                @else
                                    <img src="{{ $filePreview }}" alt="Preview" class="h-20 w-20 object-cover rounded border">
                                @endif
                                <button
                                    type="button"
                                    wire:click="removeFile"
                                    class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600"
                                >
                                    ×
                                </button>
                            </div>
                        @endif

                        <div class="flex gap-2">
                            <input type="text"
                                   wire:model="newMessage"
                                   wire:loading.attr="disabled" wire:target="sendMessage"
                                   placeholder="Type your message..."
                                   class="flex-1 border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]">

                            <label for="fileInput" class="cursor-pointer bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-md inline-flex items-center" title="Attach image or video" wire:loading.attr="disabled" wire:target="sendMessage">
                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                </svg>
                            </label>
                            <input type="file" id="fileInput" wire:model="newFile" class="hidden" accept="image/*,video/*">

                            <button type="submit"
                                    wire:loading.attr="disabled" wire:target="sendMessage"
                                    class="bg-[#EB1C24] text-white px-6 py-2 rounded-md hover:bg-[#c71313] transition disabled:opacity-50">
                                <span wire:loading.remove wire:target="sendMessage">Send</span>
                                <span wire:loading wire:target="sendMessage">
                                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Sending...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right Column: Feedback Details -->
            <div class="space-y-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Feedback Details</h3>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <p class="text-sm text-gray-900">{{ $feedback->status }}</p>
                        </div>

                        @if($feedback->feedbackSummary)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Summary</label>
                                <p class="text-sm text-gray-900">{{ $feedback->feedbackSummary }}</p>
                            </div>
                        @endif

                        @if($feedback->actionTaken)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Action Taken</label>
                                <p class="text-sm text-gray-900">{{ $feedback->actionTaken }}</p>
                            </div>
                        @endif

                        @if($feedback->initialRiskScore || $feedback->mitigatedScore)
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Initial Risk</label>
                                    <p class="text-sm text-gray-900">{{ $feedback->initialRiskScore ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Mitigated Risk</label>
                                    <p class="text-sm text-gray-900">{{ $feedback->mitigatedScore ?? 'N/A' }}</p>
                                </div>
                            </div>
                        @endif

                        @if($feedback->updatedText)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Updated Information</label>
                                <p class="text-sm text-gray-900">{{ $feedback->updatedText }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.hook('morph.updated', () => {
                const chatMessages = document.getElementById('chatMessages');
                if (chatMessages) {
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }
            });
        });

        window.addEventListener('load', () => {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        });
    </script>
</div>

