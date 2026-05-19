<?php

namespace App\Services;

use App\Models\SupportChatMessage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class SupportChatService
{
    private const MAX_HISTORY_MESSAGES = 20;

    public function __construct(
        private SupportChatDatabaseSearch $databaseSearch,
        private SupportChatActionRegistry $actionRegistry,
    ) {}

    /**
     * @return array{success: bool, message: string, actions?: array, metadata?: array}
     */
    public function chat(User $user, string $userMessage, Collection $history): array
    {
        $apiKey = config('openai.api_key');
        if (empty($apiKey)) {
            return [
                'success' => false,
                'message' => 'AI support is not configured. Please contact your administrator.',
            ];
        }

        $search = $this->databaseSearch->search($user, $userMessage);
        $messages = $this->buildMessages($user, $history, $userMessage, $search['context']);

        try {
            $response = OpenAI::chat()->create([
                'model' => config('openai.support_chat_model', config('openai.default_model', 'gpt-4o-mini')),
                'messages' => $messages,
                'max_tokens' => (int) config('openai.support_chat_max_tokens', 800),
                'temperature' => (float) config('openai.support_chat_temperature', 0.5),
                'response_format' => ['type' => 'json_object'],
            ]);

            $raw = trim($response['choices'][0]['message']['content'] ?? '');
            $parsed = $this->parseAssistantResponse($raw);

            if ($parsed['reply'] === '') {
                return [
                    'success' => false,
                    'message' => 'I could not generate a response. Please try again.',
                ];
            }

            $actions = $this->actionRegistry->resolveForUser($user, $parsed['actions']);

            return [
                'success' => true,
                'message' => $parsed['reply'],
                'actions' => $actions,
                'metadata' => [
                    'sources' => $search['sources'],
                ],
            ];
        } catch (\OpenAI\Exceptions\RateLimitException $e) {
            Log::warning('Support chat rate limit', ['user_id' => $user->id]);

            return [
                'success' => false,
                'message' => 'Support is busy right now. Please wait a moment and try again.',
            ];
        } catch (\Throwable $e) {
            Log::error('Support chat OpenAI error: '.$e->getMessage(), ['user_id' => $user->id]);

            return [
                'success' => false,
                'message' => 'Something went wrong while reaching support. Please try again later.',
            ];
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, SupportChatMessage>
     */
    public function loadHistory(User $user): Collection
    {
        return SupportChatMessage::where('user_id', $user->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    public function clearHistory(User $user): void
    {
        SupportChatMessage::where('user_id', $user->id)->delete();
    }

    private function buildMessages(User $user, Collection $history, string $userMessage, string $dbContext): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => $this->systemPrompt($user, $dbContext),
            ],
        ];

        $recent = $history->slice(-self::MAX_HISTORY_MESSAGES);
        foreach ($recent as $msg) {
            $messages[] = [
                'role' => $msg->role,
                'content' => $msg->content,
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];

        return $messages;
    }

    private function systemPrompt(User $user, string $dbContext): string
    {
        $roles = $user->getRoleNames()->implode(', ') ?: 'user';
        $actionKeys = $this->actionRegistry->actionKeysForPrompt($user);

        return <<<PROMPT
You are the Tribe 365 Vibe Help & Support assistant. You help users navigate the platform using their real account data and suggest next steps.

User context:
- Name: {$user->name}
- Email: {$user->email}
- Role(s): {$roles}

Database search results for this message (use this data when answering — do not invent records):
---
{$dbContext}
---

Available action keys you may suggest (use exact keys only):
{$actionKeys}

Response format — reply with valid JSON only, no markdown fences:
{
  "reply": "Your helpful answer referencing database data when relevant",
  "actions": ["action_key_1", "action_key_2"]
}

Rules:
- "reply" is plain text for the user (2–6 short paragraphs or bullets max).
- "actions" is an array of 0–3 action keys from the list above when a page visit or follow-up helps; use [] if none.
- Base answers on database search results when the user asks about their data.
- When [Teammates / people search] data is present, use it to answer "who is …" questions with the person's name, email, and role. Do not say you lack information if a match is listed.
- For name spelling variants (e.g. prahlad vs prahalad), trust the database match shown.
- If no teammate match is listed, say they were not found in the organisation directory and suggest myteam action.
- Be friendly and concise. Do not request passwords or payment card details.
- For human-only issues, suggest offloading_create or contact admin.
PROMPT;
    }

    /**
     * @return array{reply: string, actions: array<int, string>}
     */
    private function parseAssistantResponse(string $raw): array
    {
        if ($raw === '') {
            return ['reply' => '', 'actions' => []];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded) && isset($decoded['reply'])) {
            return [
                'reply' => trim((string) $decoded['reply']),
                'actions' => array_values(array_filter(
                    array_map('strval', $decoded['actions'] ?? [])
                )),
            ];
        }

        return [
            'reply' => $raw,
            'actions' => [],
        ];
    }
}
