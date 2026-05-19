@php
    $user = auth()->user();
    $userInitial = strtoupper(substr($user?->first_name ?? $user?->email ?? 'U', 0, 1));
@endphp

<div
    class="max-w-6xl mx-auto -mt-2"
    x-data="{ mobileActionsOpen: false }"
    style="height: calc(100vh - 9.5rem); min-height: 520px;"
>
    <div class="grid h-full grid-cols-1 lg:grid-cols-[280px_1fr] gap-4 lg:gap-5">

        {{-- Sidebar: quick actions --}}
        <aside class="flex flex-col min-h-0 lg:max-h-full order-2 lg:order-1">
            <div class="lg:hidden mb-2">
                <button
                    type="button"
                    @click="mobileActionsOpen = !mobileActionsOpen"
                    class="w-full flex items-center justify-between px-4 py-3 bg-white rounded-xl border border-gray-200 shadow-sm text-sm font-medium text-gray-700"
                >
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#EB1C24]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Quick actions
                    </span>
                    <svg class="w-4 h-4 transition-transform" :class="mobileActionsOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
            </div>

            <div
                class="flex-1 flex-col min-h-0 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden hidden lg:flex"
                :class="mobileActionsOpen ? 'flex max-lg:flex' : 'max-lg:hidden'"
            >
                <div class="px-4 py-3 border-b border-gray-100 bg-gradient-to-br from-red-50 to-white">
                    <h3 class="text-sm font-semibold text-gray-900">Quick actions</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Navigate or search your data</p>
                </div>

                <div class="flex-1 overflow-y-auto p-3 space-y-4">
                    @if(count($searchActions) > 0)
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#EB1C24] mb-2 px-1">Search my data</p>
                            <div class="space-y-1">
                                @foreach($searchActions as $action)
                                    <button
                                        type="button"
                                        wire:click="runAction('{{ $action['key'] }}')"
                                        wire:loading.attr="disabled"
                                        @disabled($isLoading)
                                        class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-left text-sm text-gray-700 hover:bg-red-50 hover:text-[#EB1C24] border border-transparent hover:border-red-100 transition group disabled:opacity-50"
                                    >
                                        <span class="flex-shrink-0 w-8 h-8 rounded-lg bg-red-100 text-[#EB1C24] flex items-center justify-center group-hover:bg-[#EB1C24] group-hover:text-white transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                            </svg>
                                        </span>
                                        <span class="font-medium leading-tight">{{ $action['label'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(count($navigateActions) > 0)
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-2 px-1">Go to page</p>
                            <div class="space-y-1">
                                @foreach($navigateActions as $action)
                                    <button
                                        type="button"
                                        wire:click="runAction('{{ $action['key'] }}')"
                                        wire:loading.attr="disabled"
                                        @disabled($isLoading)
                                        class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-left text-sm text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition disabled:opacity-50"
                                    >
                                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                        <span>{{ $action['label'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="px-4 py-3 border-t border-gray-100 bg-gray-50">
                    <p class="text-[11px] text-gray-500 leading-relaxed">
                        <span class="font-medium text-gray-600">Tip:</span> Ask in plain language — the assistant reads your live Tribe 365 data.
                    </p>
                </div>
            </div>
        </aside>

        {{-- Main chat --}}
        <section class="flex flex-col min-h-0 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden order-1 lg:order-2">
            {{-- Chat header --}}
            <header class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-100 bg-white flex-shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="relative flex-shrink-0">
                        <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-[#EB1C24] to-[#c71313] flex items-center justify-center shadow-md shadow-red-200/50">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/>
                            </svg>
                        </div>
                        <span class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-green-500 border-2 border-white rounded-full"></span>
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-gray-900 truncate">Tribe 365 Assistant</h2>
                        <p class="text-xs text-gray-500 truncate">Online · Uses your account data</p>
                    </div>
                </div>
                <button
                    type="button"
                    wire:click="clearChat"
                    wire:confirm="Clear this conversation? This cannot be undone."
                    @disabled($isLoading)
                    class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium text-gray-600 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-lg transition disabled:opacity-50"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Clear
                </button>
            </header>

            {{-- Messages --}}
            <div
                id="supportChatMessages"
                class="flex-1 overflow-y-auto px-4 sm:px-5 py-5 space-y-5 min-h-0"
                style="background: linear-gradient(180deg, #fafafa 0%, #f3f4f6 100%);"
            >
                @if(empty($messages) && ! $isLoading)
                    <div class="max-w-lg mx-auto text-center py-6">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-white border border-gray-200 shadow-sm flex items-center justify-center">
                            <svg class="w-8 h-8 text-[#EB1C24]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">How can we help?</h3>
                        <p class="text-sm text-gray-500 mt-1 mb-6">Ask anything about Tribe 365 or pick a suggestion below.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-left">
                            @foreach($suggestedPrompts as $prompt)
                                <button
                                    type="button"
                                    wire:click="askPrompt(@js($prompt))"
                                    @disabled($isLoading)
                                    class="p-3.5 rounded-xl bg-white border border-gray-200 text-sm text-gray-700 hover:border-[#EB1C24] hover:shadow-md hover:shadow-red-100/50 transition text-left disabled:opacity-50"
                                >
                                    <span class="line-clamp-2">{{ $prompt }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @foreach($messages as $msg)
                    @if($msg['role'] === 'user')
                        <div class="flex justify-end items-end gap-2">
                            <div class="max-w-[min(85%,28rem)]">
                                <div class="bg-[#EB1C24] text-white rounded-2xl rounded-br-md px-4 py-3 shadow-md shadow-red-200/30">
                                    <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ $msg['content'] }}</p>
                                </div>
                                <p class="text-[10px] text-gray-400 mt-1 text-right pr-1">{{ $msg['created_at'] }}</p>
                            </div>
                            <div class="w-8 h-8 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center text-xs font-bold flex-shrink-0 ring-2 ring-white">
                                {{ $userInitial }}
                            </div>
                        </div>
                    @else
                        <div class="flex justify-start items-end gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#EB1C24] to-[#c71313] flex items-center justify-center flex-shrink-0 shadow-sm">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                </svg>
                            </div>
                            <div class="max-w-[min(90%,32rem)] min-w-0">
                                <div class="bg-white rounded-2xl rounded-bl-md px-4 py-3 shadow-sm border border-gray-100">
                                    <p class="text-sm text-gray-800 leading-relaxed whitespace-pre-wrap">{{ $msg['content'] }}</p>
                                    @if(!empty($msg['sources']))
                                        <div class="flex flex-wrap gap-1.5 mt-3 pt-3 border-t border-gray-100">
                                            @foreach($msg['sources'] as $source)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-gray-50 text-[10px] font-medium text-gray-500 border border-gray-100">
                                                    <svg class="w-3 h-3 text-[#EB1C24]" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z" clip-rule="evenodd"/>
                                                    </svg>
                                                    {{ str_replace('_', ' ', $source) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <p class="text-[10px] text-gray-400 mt-1 pl-1">{{ $msg['created_at'] }}</p>
                                @if(!empty($msg['actions']))
                                    <div class="flex flex-wrap gap-2 mt-2.5">
                                        @foreach($msg['actions'] as $action)
                                            <button
                                                type="button"
                                                wire:click="runAction('{{ $action['key'] }}')"
                                                @disabled($isLoading)
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition disabled:opacity-50
                                                    {{ ($action['type'] ?? '') === 'query'
                                                        ? 'bg-red-50 text-[#EB1C24] border border-red-200 hover:bg-[#EB1C24] hover:text-white'
                                                        : 'bg-white text-gray-700 border border-gray-200 hover:border-[#EB1C24] hover:text-[#EB1C24] shadow-sm' }}"
                                            >
                                                @if(($action['type'] ?? '') === 'query')
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                                    </svg>
                                                @else
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                                    </svg>
                                                @endif
                                                {{ $action['label'] }}
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach

                @if($isLoading)
                    <div class="flex justify-start items-end gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#EB1C24] to-[#c71313] flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-white animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                        </div>
                        <div class="bg-white rounded-2xl rounded-bl-md px-4 py-3 shadow-sm border border-gray-100">
                            <div class="flex items-center gap-2 text-sm text-gray-500">
                                <span class="flex gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#EB1C24] animate-bounce"></span>
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#EB1C24] animate-bounce" style="animation-delay: 0.15s"></span>
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#EB1C24] animate-bounce" style="animation-delay: 0.3s"></span>
                                </span>
                                Searching your data…
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            @if($errorMessage)
                <div class="mx-4 mb-2 px-4 py-2.5 rounded-lg bg-red-50 border border-red-100 text-red-700 text-sm flex items-start gap-2 flex-shrink-0">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    {{ $errorMessage }}
                </div>
            @endif

            {{-- Input --}}
            <footer class="flex-shrink-0 p-4 border-t border-gray-100 bg-white">
                <form wire:submit="sendMessage" class="relative">
                    <div class="flex items-end gap-2 rounded-2xl border border-gray-200 bg-gray-50 focus-within:border-[#EB1C24] focus-within:ring-2 focus-within:ring-red-100 transition shadow-sm">
                        <textarea
                            wire:model="newMessage"
                            rows="1"
                            placeholder="Message Tribe 365 Assistant…"
                            class="flex-1 max-h-32 min-h-[44px] py-3 pl-4 pr-2 bg-transparent border-0 focus:ring-0 text-sm text-gray-800 placeholder-gray-400 resize-none"
                            @disabled($isLoading)
                            x-data
                            @keydown.enter.prevent="if (!$event.shiftKey) { $wire.sendMessage() }"
                            @input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 128) + 'px'"
                        ></textarea>
                        <button
                            type="submit"
                            @disabled($isLoading)
                            class="flex-shrink-0 m-1.5 w-10 h-10 rounded-xl bg-[#EB1C24] hover:bg-[#c71313] text-white flex items-center justify-center transition disabled:opacity-40 shadow-md shadow-red-200/40"
                            title="Send (Enter)"
                        >
                            <span wire:loading.remove wire:target="sendMessage,askPrompt,runAction">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                            </span>
                            <span wire:loading wire:target="sendMessage,askPrompt,runAction">
                                <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </span>
                        </button>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-2 text-center">Enter to send · Shift+Enter for new line</p>
                </form>
            </footer>
        </section>
    </div>

    @script
    <script>
        const scrollSupportChat = () => {
            const el = document.getElementById('supportChatMessages');
            if (el) el.scrollTop = el.scrollHeight;
        };
        $wire.on('support-chat-scroll', scrollSupportChat);
        Livewire.hook('morph.updated', ({ el }) => {
            if (el.querySelector?.('#supportChatMessages') || el.id === 'supportChatMessages') {
                scrollSupportChat();
            }
        });
        document.addEventListener('livewire:navigated', scrollSupportChat);
        scrollSupportChat();
    </script>
    @endscript
</div>
