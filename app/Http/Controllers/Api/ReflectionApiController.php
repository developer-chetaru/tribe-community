<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reflection;
use App\Models\ReflectionMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Reflections",
 *     description="Reflection management endpoints for creating, viewing, and managing user reflections and chat messages"
 * )
 */
class ReflectionApiController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/reflections",
     *     tags={"Reflections"},
     *     summary="Get user's reflection list",
     *     description="Retrieve all reflections for the authenticated user. Returns reflections ordered by most recent first.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Reflections retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="topic", type="string", example="Work-life balance"),
     *                     @OA\Property(property="message", type="string", example="I need to improve my work-life balance"),
     *                     @OA\Property(property="status", type="string", example="new", enum={"new", "inprogress", "resolved"}),
     *                     @OA\Property(property="image", type="string", nullable=true, example="http://example.com/storage/hptm_files/image1.jpg", description="First image URL (for backward compatibility)"),
     *                     @OA\Property(property="images", type="array", @OA\Items(type="string", example="http://example.com/storage/hptm_files/image1.jpg"), description="Array of all image URLs"),
     *                     @OA\Property(property="created_at", type="string", example="2026-01-13 10:30 AM")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     )
     * )
     */
    public function list(Request $request)
    {
        $user = Auth::user();

        $reflections = Reflection::where('userId', $user->id)
            ->orderByDesc('id')
            ->get()
            ->map(function($r){
                // Handle multiple images (JSON array) or single image
                $images = [];
                if ($r->image) {
                    $decoded = json_decode($r->image, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        // Multiple images stored as JSON array
                        foreach ($decoded as $fileName) {
                            $images[] = url('storage/hptm_files/' . $fileName);
                        }
                    } else {
                        // Single image stored as string (backward compatibility)
                        $images[] = url('storage/hptm_files/' . $r->image);
                    }
                }

                return [
                    'id' => $r->id,
                    'topic' => $r->topic,
                    'message' => $r->message,
                    'status' => $r->status,
                    'image' => !empty($images) ? $images[0] : null, // Keep for backward compatibility
                    'images' => $images, // Array of all image URLs
                    'created_at' => $r->created_at->format('Y-m-d H:i A'),
                ];
            });

        return response()->json(['status' => true, 'data' => $reflections]);
    }

    /**
     * @OA\Post(
     *     path="/api/reflections",
     *     tags={"Reflections"},
     *     summary="Create a new reflection",
     *     description="Create a new reflection for the authenticated user. Supports multiple image attachments (up to 10 images, max 5MB each). For mobile apps, send images as 'images[]' array in multipart/form-data.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"topic", "message"},
     *                 @OA\Property(property="topic", type="string", maxLength=255, example="Work-life balance", description="Reflection topic"),
     *                 @OA\Property(property="message", type="string", example="I need to improve my work-life balance", description="Reflection message"),
     *                 @OA\Property(property="image", type="string", format="binary", description="Optional single image file (for backward compatibility, max 5MB)"),
     *                 @OA\Property(property="images[]", type="array", @OA\Items(type="string", format="binary"), description="Optional multiple image files (up to 10 images, max 5MB each). Use this for mobile apps.")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reflection created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Reflection created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="userId", type="integer", example=1),
     *                 @OA\Property(property="orgId", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="topic", type="string", example="Work-life balance"),
     *                 @OA\Property(property="message", type="string", example="I need to improve my work-life balance"),
     *                 @OA\Property(property="status", type="string", example="new"),
     *                 @OA\Property(property="image", type="string", nullable=true, example="image1.jpg", description="First image filename (for backward compatibility)"),
     *                 @OA\Property(property="images", type="array", @OA\Items(type="string", example="http://example.com/storage/hptm_files/image1.jpg"), description="Array of all image URLs"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="topic",
     *                     type="array",
     *                     @OA\Items(type="string", example="The topic field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="message",
     *                     type="array",
     *                     @OA\Items(type="string", example="The message field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="images.0",
     *                     type="array",
     *                     @OA\Items(type="string", example="The images.0 may not be greater than 5120 kilobytes.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function create(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'topic' => 'required|string|max:255',
            'message' => 'required|string',
            'image' => 'nullable|image|max:5120', // Single image (backward compatibility, max 5MB)
            'images' => 'nullable|array|max:10', // Multiple images array (up to 10 images)
            'images.*' => 'nullable|image|max:5120', // Each image max 5MB
        ]);

        $imageData = null;
        $imageUrls = [];

        // Handle multiple images (preferred for mobile apps)
        if ($request->hasFile('images')) {
            $imageFiles = $request->file('images');
            $imageNames = [];
            
            foreach ($imageFiles as $imageFile) {
                $path = $imageFile->store('public/hptm_files');
                $fileName = basename($path);
                $imageNames[] = $fileName;
                $imageUrls[] = url('storage/hptm_files/' . $fileName);
            }
            
            // Store as JSON array
            $imageData = json_encode($imageNames);
        }
        // Handle single image (backward compatibility)
        elseif ($request->hasFile('image')) {
            $path = $request->file('image')->store('public/hptm_files');
            $fileName = basename($path);
            $imageData = $fileName; // Store as string for backward compatibility
            $imageUrls[] = url('storage/hptm_files/' . $fileName);
        }

        $reflection = Reflection::create([
            'userId' => $user->id,
            'orgId' => $user->orgId,
            'topic' => $validated['topic'],
            'message' => $validated['message'],
            'image' => $imageData,
            'status' => 'new',
        ]);

        // Add image URLs to response
        $reflection->images = $imageUrls;
        $reflection->image = !empty($imageUrls) ? $imageUrls[0] : null; // Backward compatibility

        return response()->json(['status' => true, 'message' => 'Reflection created successfully', 'data' => $reflection]);
    }

    /**
     * @OA\Get(
     *     path="/api/reflections/{reflection}/chats",
     *     tags={"Reflections"},
     *     summary="Get chat messages for a reflection",
     *     description="Retrieve all chat messages for a specific reflection. Only the reflection owner can access the chat.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="reflection",
     *         in="path",
     *         required=true,
     *         description="Reflection ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chat messages retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="from", type="integer", example=1, description="User ID who sent the message"),
     *                     @OA\Property(property="message", type="string", example="How can I help you with this?", description="Chat message text"),
     *                     @OA\Property(property="file", type="string", nullable=true, example="http://example.com/storage/hptm_files/file.jpg", description="First file URL (for backward compatibility)"),
     *                     @OA\Property(property="files", type="array", @OA\Items(type="string", example="http://example.com/storage/hptm_files/file.jpg"), description="Array of all file URLs if message has attachments"),
     *                     @OA\Property(property="time", type="string", example="2026-01-13 10:30 AM", description="Message timestamp"),
     *                     @OA\Property(property="isMe", type="boolean", example=true, description="Whether the message is from the authenticated user")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Reflection not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Reflection not found")
     *         )
     *     )
     * )
     */
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
                // Handle multiple files (JSON array) or single file
                $files = [];
                if ($msg->file) {
                    $decoded = json_decode($msg->file, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        // Multiple files stored as JSON array
                        foreach ($decoded as $fileName) {
                            $files[] = url('storage/hptm_files/' . $fileName);
                        }
                    } else {
                        // Single file stored as string (backward compatibility)
                        $files[] = url('storage/hptm_files/' . $msg->file);
                    }
                }

                return [
                    'from' => $msg->sendFrom,
                    'message' => $msg->message,
                    'file' => !empty($files) ? $files[0] : null, // Keep for backward compatibility
                    'files' => $files, // Array of all file URLs
                    'time' => $msg->created_at->format('Y-m-d H:i A'),
                    'isMe' => $msg->sendFrom == $user->id,
                ];
            });

        return response()->json(['status' => true, 'data' => $messages]);
    }

    /**
     * @OA\Post(
     *     path="/api/reflections/{reflection}/chats",
     *     tags={"Reflections"},
     *     summary="Send a chat message to a reflection",
     *     description="Send a chat message (text and/or file) to a specific reflection. Only the reflection owner can send messages.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="reflection",
     *         in="path",
     *         required=true,
     *         description="Reflection ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="message", type="string", nullable=true, example="How can I help you with this?", description="Chat message text (required if files are not provided)"),
     *                 @OA\Property(property="file", type="string", format="binary", nullable=true, description="Optional single file attachment (max 2MB, for backward compatibility)"),
     *                 @OA\Property(property="files[]", type="array", @OA\Items(type="string", format="binary"), nullable=true, description="Optional multiple file attachments (up to 10 files, max 2MB each). Use this for mobile apps.")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Message sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Message sent successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="sendFrom", type="integer", example=1),
     *                 @OA\Property(property="sendTo", type="integer", example=1),
     *                 @OA\Property(property="reflectionId", type="integer", example=1),
     *                 @OA\Property(property="message", type="string", nullable=true, example="How can I help you with this?"),
     *                 @OA\Property(property="file", type="string", nullable=true, example="file.jpg", description="First file filename (for backward compatibility)"),
     *                 @OA\Property(property="files", type="array", @OA\Items(type="string", example="http://example.com/storage/hptm_files/file.jpg"), description="Array of all file URLs"),
     *                 @OA\Property(property="status", type="string", example="Active"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Reflection not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Reflection not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="file",
     *                     type="array",
     *                     @OA\Items(type="string", example="The file may not be greater than 2048 kilobytes.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
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
            'file' => 'nullable|file|max:2048', // Single file (backward compatibility)
            'files' => 'nullable|array|max:10', // Multiple files array (up to 10 files)
            'files.*' => 'nullable|file|max:2048', // Each file max 2MB
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

        $fileData = null;
        $fileUrls = [];

        // Handle multiple files (preferred for mobile apps)
        if ($request->hasFile('files')) {
            $fileUploads = $request->file('files');
            $fileNames = [];
            
            foreach ($fileUploads as $fileUpload) {
                $path = $fileUpload->store('public/hptm_files');
                $fileName = basename($path);
                $fileNames[] = $fileName;
                $fileUrls[] = url('storage/hptm_files/' . $fileName);
            }
            
            // Store as JSON array
            $fileData = json_encode($fileNames);
        }
        // Handle single file (backward compatibility)
        elseif ($request->hasFile('file')) {
            $path = $request->file('file')->store('public/hptm_files');
            $fileName = basename($path);
            $fileData = $fileName; // Store as string for backward compatibility
            $fileUrls[] = url('storage/hptm_files/' . $fileName);
        }

        if ($fileData) {
            $data['file'] = $fileData;
        }

        $msg = ReflectionMessage::create($data);

        // Add file URLs to response
        $msg->files = $fileUrls;
        $msg->file = !empty($fileUrls) ? $fileUrls[0] : null; // Backward compatibility

        return response()->json(['status' => true, 'message' => 'Message sent successfully', 'data' => $msg]);
    }

    /**
     * @OA\Post(
     *     path="/api/reflections/update-status",
     *     tags={"Reflections"},
     *     summary="Update reflection status",
     *     description="Update the status of a reflection. Users can only update their own reflections, unless they are super_admin.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status", "id"},
     *             @OA\Property(property="id", type="integer", example=1, description="Reflection ID"),
     *             @OA\Property(property="status", type="string", enum={"inprogress", "resolved"}, example="inprogress", description="New status for the reflection")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reflection status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Reflection status updated successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="reflection_id", type="integer", example=1),
     *                 @OA\Property(property="status", type="string", example="inprogress")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission to update this reflection",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Forbidden: You do not have permission to update this reflection.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Reflection not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Reflection not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="status",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected status is invalid.")
     *                 ),
     *                 @OA\Property(
     *                     property="id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The id field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Something went wrong. Please try again later.")
     *         )
     *     )
     * )
     */
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