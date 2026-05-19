<?php

namespace App\Livewire;

use App\Models\SupportChatMessage;
use App\Services\SupportChatActionRegistry;
use App\Services\SupportChatService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class HelpSupportChat extends Component
{
    public array $messages = [];

    public array $quickActions = [];

    public array $navigateActions = [];

    public array $searchActions = [];

    public array $suggestedPrompts = [];

    public string $newMessage = '';

    public bool $isLoading = false;

    public string $errorMessage = '';

    public function mount(SupportChatService $supportChat, SupportChatActionRegistry $actions): void
    {
        $user = Auth::user();
        if ($user) {
            $this->quickActions = $actions->quickActionsFor($user);
            $this->navigateActions = array_values(array_filter(
                $this->quickActions,
                fn (array $a) => $a['type'] === 'link'
            ));
            $this->searchActions = array_values(array_filter(
                $this->quickActions,
                fn (array $a) => $a['type'] === 'query'
            ));
        }

        $this->suggestedPrompts = [
            'What is my latest sentiment this week?',
            'Summarise my recent weekly and monthly summaries.',
            'How many notifications do I have?',
            'Explain how HPTM learning works on Tribe 365.',
        ];

        $this->loadMessages($supportChat);
    }

    public function askPrompt(string $prompt, SupportChatService $supportChat): void
    {
        $this->newMessage = $prompt;
        $this->sendMessage($supportChat);
    }

    public function loadMessages(SupportChatService $supportChat): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $this->messages = $supportChat->loadHistory($user)->map(fn (SupportChatMessage $msg) => [
            'id' => $msg->id,
            'role' => $msg->role,
            'content' => $msg->content,
            'actions' => $msg->actions ?? [],
            'sources' => $msg->metadata['sources'] ?? [],
            'created_at' => $msg->created_at->format('d M Y, h:i A'),
        ])->toArray();
    }

    public function sendMessage(SupportChatService $supportChat): void
    {
        $this->errorMessage = '';

        $validated = $this->validate([
            'newMessage' => 'required|string|min:1|max:2000',
        ]);

        $user = Auth::user();
        if (! $user) {
            return;
        }

        $text = trim($validated['newMessage']);
        $this->newMessage = '';
        $this->isLoading = true;

        $history = $supportChat->loadHistory($user);

        SupportChatMessage::create([
            'user_id' => $user->id,
            'role' => 'user',
            'content' => $text,
        ]);

        $result = $supportChat->chat($user, $text, $history);

        if ($result['success']) {
            SupportChatMessage::create([
                'user_id' => $user->id,
                'role' => 'assistant',
                'content' => $result['message'],
                'actions' => $result['actions'] ?? [],
                'metadata' => $result['metadata'] ?? null,
            ]);
        } else {
            $this->errorMessage = $result['message'];
        }

        $this->isLoading = false;
        $this->loadMessages($supportChat);
        $this->dispatch('support-chat-scroll');
    }

    public function runAction(string $key, SupportChatService $supportChat, SupportChatActionRegistry $actions): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $action = $actions->findForUser($user, $key);
        if (! $action) {
            return;
        }

        if ($action['type'] === 'link' && ! empty($action['url'])) {
            $this->redirect($action['url']);

            return;
        }

        if ($action['type'] === 'query' && ! empty($action['message'])) {
            $this->newMessage = $action['message'];
            $this->sendMessage($supportChat);
        }
    }

    public function clearChat(SupportChatService $supportChat): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $supportChat->clearHistory($user);
        $this->messages = [];
        $this->errorMessage = '';
        $this->newMessage = '';
    }

    public function render()
    {
        return view('livewire.help-support-chat')
            ->layout('layouts.app', [
                'header' => 'Help & Support',
            ]);
    }
}
