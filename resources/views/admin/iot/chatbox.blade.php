<x-app-layout>
    <x-slot name="header">
        <h2 class="text-[24px] md:text-[30px] font-semibold text-[#EB1C24]">
            Offloading Chat - #{{ $feedback->id }}
        </h2>
    </x-slot>

    <div class="flex-1 overflow-auto">
        <div class="max-w-8xl mx-auto p-4">
            <div class="mb-4">
                <a href="{{ route('admin.iot.dashboard', ['orgId' => $feedback->orgId]) }}"
                   class="bg-[#fff] px-4 py-3 rounded-md shadow hover:bg-[#c71313] text-sm hover:text-white transition inline-flex items-center">
                    <svg width="7" height="11" viewBox="0 0 7 11" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
                        <path d="M6 10.5C6 10.5 1 6.81758 1 5.5C1 4.18233 6 0.5 6 0.5" stroke="#808080" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Back to Dashboard
                </a>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white rounded-lg shadow">
                    <div class="bg-gray-50 p-4 border-b border-gray-200">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">
                                    {{ $feedback->user->name ?? 'User' }}
                                </h3>
                                <p class="text-sm text-gray-500">{{ $feedback->user->email ?? '' }}</p>
                                <p class="text-xs text-gray-400 mt-1">
                                    Submitted: {{ $feedback->created_at->format('d M Y, h:i A') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 border-b border-gray-200 bg-blue-50">
                        <p class="text-sm font-medium text-gray-700 mb-2">Original Offloading:</p>
                        <p class="text-gray-900">{{ $feedback->message }}</p>
                        @if($feedback->image)
                            <div class="mt-3">
                                @php
                                    $fileExtension = strtolower(pathinfo($feedback->image, PATHINFO_EXTENSION));
                                    $isVideo = in_array($fileExtension, ['mp4', 'mov', 'avi', 'wmv', 'flv', 'webm']);
                                @endphp
                                @if($isVideo)
                                    <video controls class="max-w-xs rounded-lg border border-gray-300" preload="metadata">
                                        <source src="{{ asset('uploads/iot_files/' . $feedback->image) }}" type="video/{{ $fileExtension }}">
                                        Your browser does not support the video tag.
                                    </video>
                                @else
                                    <img src="{{ asset('uploads/iot_files/' . $feedback->image) }}"
                                         alt="Offloading Image"
                                         class="max-w-xs rounded-lg border border-gray-300 cursor-pointer"
                                         onclick="window.open('{{ asset('uploads/iot_files/' . $feedback->image) }}', '_blank')">
                                @endif
                            </div>
                        @endif
                    </div>

                    <div id="chatMessages" class="p-4 space-y-4 max-h-[500px] overflow-y-auto bg-gray-50">
                        @foreach($feedback->messages as $message)
                            <div class="flex {{ $message->sendFrom == auth()->id() ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-md bg-white rounded-lg p-3 shadow-sm {{ $message->sendFrom == auth()->id() ? 'bg-blue-50' : '' }}">
                                    <div class="flex items-start gap-2">
                                        @if($message->sender)
                                            <div class="flex-shrink-0">
                                                @if($message->sender->profile_photo_path)
                                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($message->sender->profile_photo_path) }}"
                                                         class="w-8 h-8 rounded-full" alt="Avatar">
                                                @else
                                                    <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center text-xs font-medium">
                                                        {{ strtoupper(substr($message->sender->name ?? 'U', 0, 1)) }}
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                        <div class="flex-1">
                                            <p class="text-xs text-gray-500 mb-1">
                                                {{ $message->sender->name ?? 'Unknown' }}
                                                <span class="text-gray-400">({{ $message->sender && $message->sender->hasAnyRole(['super_admin', 'organisation_admin']) ? 'Admin' : 'User' }})</span>
                                            </p>
                                            @if($message->message)
                                                <p class="text-gray-900 text-sm">{{ $message->message }}</p>
                                            @endif
                                            @if($message->file)
                                                <div class="mt-2">
                                                    @php
                                                        $fileExtension = strtolower(pathinfo($message->file, PATHINFO_EXTENSION));
                                                        $isVideo = in_array($fileExtension, ['mp4', 'mov', 'avi', 'wmv', 'flv', 'webm']);
                                                    @endphp
                                                    @if($isVideo)
                                                        <video controls class="max-w-xs rounded-lg border border-gray-300" preload="metadata">
                                                            <source src="{{ asset('uploads/iot_files/' . $message->file) }}" type="video/{{ $fileExtension }}">
                                                            Your browser does not support the video tag.
                                                        </video>
                                                    @else
                                                        <img src="{{ asset('uploads/iot_files/' . $message->file) }}"
                                                             alt="Message Image"
                                                             class="max-w-xs rounded-lg border border-gray-300 cursor-pointer"
                                                             onclick="window.open('{{ asset('uploads/iot_files/' . $message->file) }}', '_blank')">
                                                    @endif
                                                </div>
                                            @endif
                                            <p class="text-xs text-gray-400 mt-1">
                                                {{ $message->created_at->format('d M Y, h:i A') }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="p-4 border-t border-gray-200 bg-white">
                        <form id="sendMessageForm" class="flex gap-2">
                            @csrf
                            <input type="hidden" name="feedbackId" value="{{ $feedback->id }}">
                            <input type="hidden" name="sendTo" value="{{ $feedback->userId }}">
                            <input type="hidden" name="sendFrom" value="{{ auth()->id() }}">

                            <input type="text"
                                   name="message"
                                   id="messageInput"
                                   placeholder="Type your message..."
                                   class="flex-1 border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]">

                            <label for="fileInput" class="cursor-pointer bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-md inline-flex items-center" title="Attach image or video">
                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                </svg>
                            </label>
                            <input type="file" id="fileInput" name="file" class="hidden" accept="image/*,video/*">
                            <div id="filePreview" class="ml-2"></div>

                            <button type="submit"
                                    class="bg-[#EB1C24] text-white px-6 py-2 rounded-md hover:bg-[#c71313] transition">
                                Send
                            </button>
                        </form>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="bg-white rounded-lg shadow p-4">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Feedback Details</h3>

                        <form id="updateFeedbackForm" class="space-y-4">
                            @csrf
                            <input type="hidden" name="feedbackId" value="{{ $feedback->id }}">

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select name="feedbackStatus" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]">
                                    @foreach($statuses as $status)
                                        <option value="{{ $status->id }}" {{ $feedback->feedbackStatus == $status->id ? 'selected' : '' }}>
                                            {{ $status->title }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">General Status</label>
                                <select name="status" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]">
                                    <option value="Active" {{ $feedback->status == 'Active' ? 'selected' : '' }}>Active</option>
                                    <option value="Inactive" {{ $feedback->status == 'Inactive' ? 'selected' : '' }}>Inactive</option>
                                    <option value="Completed" {{ $feedback->status == 'Completed' ? 'selected' : '' }}>Completed</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Summary</label>
                                <textarea name="feedbackSummary"
                                          rows="3"
                                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]"
                                          placeholder="Enter summary...">{{ $feedback->feedbackSummary }}</textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Action Taken</label>
                                <textarea name="actionTaken"
                                          rows="3"
                                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]"
                                          placeholder="Enter actions taken...">{{ $feedback->actionTaken }}</textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Initial Risk</label>
                                    <input type="number"
                                           name="initialRiskScore"
                                           value="{{ $feedback->initialRiskScore }}"
                                           min="0" max="25"
                                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Mitigated Risk</label>
                                    <input type="text"
                                           name="mitigatedScore"
                                           value="{{ $feedback->mitigatedScore }}"
                                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Updated Information</label>
                                <textarea name="updatedText"
                                          rows="2"
                                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]"
                                          placeholder="Enter updates...">{{ $feedback->updatedText }}</textarea>
                            </div>

                            <button type="submit"
                                    class="w-full bg-[#EB1C24] text-white px-4 py-2 rounded-md hover:bg-[#c71313] transition">
                                Update Feedback
                            </button>
                        </form>
                    </div>

                    <div class="bg-white rounded-lg shadow p-4">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Themes</h3>

                        <div class="mb-4">
                            <p class="text-sm font-medium text-gray-700 mb-2">Assigned Themes:</p>
                            <div class="space-y-2">
                                @forelse($feedback->themes as $theme)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $theme->title }}
                                    </span>
                                @empty
                                    <p class="text-sm text-gray-500">No themes assigned</p>
                                @endforelse
                            </div>
                        </div>

                        <form id="assignThemeForm" class="space-y-2">
                            @csrf
                            <input type="hidden" name="feedbackId" value="{{ $feedback->id }}">

                            <select name="themeId" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]">
                                <option value="">Select a theme...</option>
                                @foreach($themes as $theme)
                                    <option value="{{ $theme->id }}">{{ $theme->title }}</option>
                                @endforeach
                            </select>

                            <button type="submit"
                                    class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                                Assign Theme
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.getElementById('sendMessageForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const messageInput = document.getElementById('messageInput');
            const fileInput = document.getElementById('fileInput');
            const sendButton = this.querySelector('button[type="submit"]');

            sendButton.disabled = true;
            sendButton.textContent = 'Sending...';

            try {
                const response = await fetch('{{ route("admin.iot.send-message") }}', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });

                if (!response.ok) {
                    const text = await response.text();
                    throw new Error('Server error: ' + response.status + ' - ' + text.substring(0, 100));
                }

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    throw new Error('Expected JSON response but got: ' + text.substring(0, 100));
                }

                const data = await response.json();
                if (data.status) {
                    messageInput.value = '';
                    fileInput.value = '';
                    filePreview.innerHTML = '';
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to send message'));
                    sendButton.disabled = false;
                    sendButton.textContent = 'Send';
                }
            } catch (error) {
                alert('Error: ' + error.message);
                sendButton.disabled = false;
                sendButton.textContent = 'Send';
            }
        });

        document.getElementById('updateFeedbackForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            try {
                const response = await fetch('{{ route("admin.iot.update-feedback") }}', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });

                const data = await response.json();
                if (data.status) {
                    alert('Feedback updated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to update feedback'));
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        });

        document.getElementById('assignThemeForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            try {
                const response = await fetch('{{ route("admin.iot.assign-theme") }}', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });

                const data = await response.json();
                if (data.status) {
                    alert('Theme assigned successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to assign theme'));
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        });

        const fileInput = document.getElementById('fileInput');
        const filePreview = document.getElementById('filePreview');

        fileInput.addEventListener('change', function(e) {
            filePreview.innerHTML = '';
            const file = e.target.files[0];
            if (!file) return;

            const fileType = file.type.split('/')[0];
            const reader = new FileReader();
            reader.onload = function(ev) {
                if (fileType === 'image') {
                    filePreview.innerHTML = `<img src="${ev.target.result}" alt="Preview" class="h-20 w-20 object-cover rounded border">`;
                } else if (fileType === 'video') {
                    filePreview.innerHTML = `
                        <video src="${ev.target.result}" class="h-20 w-20 object-cover rounded border" controls></video>
                        <p class="text-xs text-gray-500 mt-1">${file.name}</p>
                    `;
                }
            };
            reader.readAsDataURL(file);
        });

        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    </script>
    @endpush
</x-app-layout>

