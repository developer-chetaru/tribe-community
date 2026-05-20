<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportChatMessage;
use App\Services\SupportChatActionRegistry;
use App\Services\SupportChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiHelpSupportController extends Controller
{
    public function history(SupportChatService $supportChat, SupportChatActionRegistry $actions): JsonResponse
    {
        $user = Auth::user();

        $messages = $supportChat->loadHistory($user)->map(fn (SupportChatMessage $msg) => [
            'id'         => $msg->id,
            'role'       => $msg->role,
            'content'    => $msg->content,
            'actions'    => $msg->actions ?? [],
            'sources'    => $msg->metadata['sources'] ?? [],
            'created_at' => $msg->created_at->format('d M Y, h:i A'),
        ])->values()->toArray();

        $quickActions  = $actions->quickActionsFor($user);
        $navigateActions = array_values(array_filter($quickActions, fn ($a) => $a['type'] === 'link'));
        $queryActions    = array_values(array_filter($quickActions, fn ($a) => $a['type'] === 'query'));

        return response()->json([
            'status' => true,
            'data'   => [
                'messages'          => $messages,
                'navigate_actions'  => $navigateActions,
                'query_actions'     => $queryActions,
                'suggested_prompts' => [
                    'What is my latest sentiment this week?',
                    'Summarise my recent weekly and monthly summaries.',
                    'How many notifications do I have?',
                    'Explain how HPTM learning works on Tribe 365.',
                ],
            ],
        ]);
    }

    public function send(Request $request, SupportChatService $supportChat): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|min:1|max:2000',
        ]);

        $user = Auth::user();
        $text = trim($validated['message']);

        $history = $supportChat->loadHistory($user);

        $userMsg = SupportChatMessage::create([
            'user_id' => $user->id,
            'role'    => 'user',
            'content' => $text,
        ]);

        $result = $supportChat->chat($user, $text, $history);

        if (! $result['success']) {
            return response()->json([
                'status'  => false,
                'message' => $result['message'],
            ]);
        }

        $assistantMsg = SupportChatMessage::create([
            'user_id'  => $user->id,
            'role'     => 'assistant',
            'content'  => $result['message'],
            'actions'  => $result['actions'] ?? [],
            'metadata' => $result['metadata'] ?? null,
        ]);

        return response()->json([
            'status' => true,
            'data'   => [
                'user_message' => [
                    'id'         => $userMsg->id,
                    'role'       => $userMsg->role,
                    'content'    => $userMsg->content,
                    'actions'    => [],
                    'sources'    => [],
                    'created_at' => $userMsg->created_at->format('d M Y, h:i A'),
                ],
                'assistant_message' => [
                    'id'         => $assistantMsg->id,
                    'role'       => $assistantMsg->role,
                    'content'    => $assistantMsg->content,
                    'actions'    => $assistantMsg->actions ?? [],
                    'sources'    => $assistantMsg->metadata['sources'] ?? [],
                    'created_at' => $assistantMsg->created_at->format('d M Y, h:i A'),
                ],
            ],
        ]);
    }

    public function quickActions(SupportChatActionRegistry $actions): JsonResponse
    {
        $user = Auth::user();

        $quickActions    = $actions->quickActionsFor($user);
        $navigateActions = array_values(array_filter($quickActions, fn ($a) => $a['type'] === 'link'));
        $queryActions    = array_values(array_filter($quickActions, fn ($a) => $a['type'] === 'query'));

        return response()->json([
            'status' => true,
            'data'   => [
                'navigate_actions'  => $navigateActions,
                'query_actions'     => $queryActions,
                'suggested_prompts' => [
                    'What is my latest sentiment this week?',
                    'Summarise my recent weekly and monthly summaries.',
                    'How many notifications do I have?',
                    'Explain how HPTM learning works on Tribe 365.',
                ],
            ],
        ]);
    }

    public function runAction(Request $request, SupportChatService $supportChat, SupportChatActionRegistry $actions): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string',
        ]);

        $user   = Auth::user();
        $action = $actions->findForUser($user, $validated['key']);

        if (! $action) {
            return response()->json([
                'status'  => false,
                'message' => 'Action not found or not available for your role.',
            ]);
        }

        if ($action['type'] === 'link') {
            return response()->json([
                'status' => true,
                'data'   => [
                    'type'  => 'link',
                    'key'   => $action['key'],
                    'label' => $action['label'],
                    'url'   => $action['url'],
                ],
            ]);
        }

        // type = query: auto-send the preset message
        $message = $action['message'] ?? '';
        $history = $supportChat->loadHistory($user);

        $userMsg = SupportChatMessage::create([
            'user_id' => $user->id,
            'role'    => 'user',
            'content' => $message,
        ]);

        $result = $supportChat->chat($user, $message, $history);

        if (! $result['success']) {
            return response()->json([
                'status'  => false,
                'message' => $result['message'],
            ]);
        }

        $assistantMsg = SupportChatMessage::create([
            'user_id'  => $user->id,
            'role'     => 'assistant',
            'content'  => $result['message'],
            'actions'  => $result['actions'] ?? [],
            'metadata' => $result['metadata'] ?? null,
        ]);

        return response()->json([
            'status' => true,
            'data'   => [
                'type' => 'query',
                'user_message' => [
                    'id'         => $userMsg->id,
                    'role'       => $userMsg->role,
                    'content'    => $userMsg->content,
                    'actions'    => [],
                    'sources'    => [],
                    'created_at' => $userMsg->created_at->format('d M Y, h:i A'),
                ],
                'assistant_message' => [
                    'id'         => $assistantMsg->id,
                    'role'       => $assistantMsg->role,
                    'content'    => $assistantMsg->content,
                    'actions'    => $assistantMsg->actions ?? [],
                    'sources'    => $assistantMsg->metadata['sources'] ?? [],
                    'created_at' => $assistantMsg->created_at->format('d M Y, h:i A'),
                ],
            ],
        ]);
    }

    public function clear(SupportChatService $supportChat): JsonResponse
    {
        $user = Auth::user();
        $supportChat->clearHistory($user);

        return response()->json([
            'status'  => true,
            'message' => 'Chat history cleared successfully.',
        ]);
    }
}
