<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reflection;
use App\Models\ReflectionMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ReflectionApiController extends Controller
{
    // 1) Show reflection list
    public function list(Request $request)
    {
        $user = Auth::user();

        $reflections = Reflection::where('userId', $user->id)
            ->orderByDesc('id')
            ->get()
            ->map(function($r){
                return [
                    'id' => $r->id,
                    'topic' => $r->topic,
                    'message' => $r->message,
					'status' => $r->status,
                    'created_at' => $r->created_at->format('Y-m-d H:i A'),
                ];
            });

        return response()->json(['status' => true, 'data' => $reflections]);
    }

    // 2) Create reflection
    public function create(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'topic' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('public/hptm_files');
            $validated['image'] = basename($path);
        }

        $reflection = Reflection::create([
            'userId' => $user->id,
            'orgId' => $user->orgId,
            'topic' => $validated['topic'],
            'message' => $validated['message'],
            'status' => 'new',
        ]);

        return response()->json(['status' => true, 'message' => 'Reflection created successfully', 'data' => $reflection]);
    }

    // 3) Show chat for a reflection
    public function chats($reflectionId)
    {
        $user = Auth::user();

        $reflection = Reflection::where('id', $reflectionId)
            ->where('userId', $user->id)
            ->first();

        if (!$reflection) {
            return response()->json(['status' => false, 'message' => 'Reflection not found'], 404);
        }

        $messages = ReflectionMessage::where('reflectionId', $reflectionId)
            ->where('status', 'Active')
            ->with('user')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function($msg) use ($user) {
                return [
                    'from' => $msg->sendFrom,
                    'message' => $msg->message,
                    'file' => $msg->file ? url('storage/hptm_files/'.$msg->file) : null,
                    'time' => $msg->created_at->format('Y-m-d H:i A'),
                    'isMe' => $msg->sendFrom == $user->id,
                ];
            });

        return response()->json(['status' => true, 'data' => $messages]);
    }

    // 4) Send chat message
    public function sendChat(Request $request, $reflectionId)
    {
        $user = Auth::user();

        $reflection = Reflection::where('id', $reflectionId)
            ->where('userId', $user->id)
            ->first();

        if (!$reflection) {
            return response()->json(['status' => false, 'message' => 'Reflection not found'], 404);
        }

        $validated = $request->validate([
            'message' => 'nullable|string',
            'file' => 'nullable|file|max:2048',
        ]);

        $data = [
            'sendFrom' => $user->id,
            'sendTo' => $reflection->userId,
            'reflectionId' => $reflection->id,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (!empty($validated['message'])) $data['message'] = $validated['message'];

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('public/hptm_files');
            $data['file'] = basename($path);
        }

        $msg = ReflectionMessage::create($data);

        return response()->json(['status' => true, 'message' => 'Message sent successfully', 'data' => $msg]);
    }

	public function statusReflection(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $request->validate([
                'status' => 'required|in:inprogress,resolved',
                'id' => 'required',
            ]);

            $newStatus = $request->input('status');
            $id = $request->input('id');

            $reflection = Reflection::find($id);
            if (!$reflection) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reflection not found'
                ], 404);
            }

            if ($reflection->userId !== $user->id && !$user->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: You do not have permission to update this reflection.'
                ], 403);
            }

            $reflection->status = $newStatus;
            $reflection->save();

            \Log::info('Reflection status updated by user', [
                'reflection_id' => $reflection->id,
                'new_status' => $newStatus,
                'user_id' => $user->id,
                'user_role' => $user->getRoleNames()->first()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reflection status updated successfully.',
                'data' => [
                    'reflection_id' => $reflection->id,
                    'status' => $reflection->status,
                ]
            ], 200);
        } catch (\Throwable $e) {
            \Log::error('Error updating reflection status', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.'
            ], 500);
        }
    }
}