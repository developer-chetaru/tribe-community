<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\IOTSentMsgMail;
use App\Mail\PostFeedbackNotificationMail;
use App\Models\IotFeedback;
use App\Models\IotMessage;
use App\Models\IotTheme;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ApiIOTController extends Controller
{
    public function postFeedback(Request $request)
    {
        try {
            $request->validate([
                'message' => 'required|string',
                'userId' => 'required|exists:users,id',
                'orgId' => 'required|exists:organisations,id',
                'image' => 'nullable|string',
                'SWOT' => 'nullable|string|max:255',
                'themeId' => 'nullable|string',
            ]);

            $user = User::findOrFail($request->userId);
            $imageFilename = null;

            if ($request->has('image') && !empty($request->image)) {
                $imageData = $request->image;
                if (Str::startsWith($imageData, 'data:image')) {
                    $imageData = explode(',', $imageData)[1] ?? $imageData;
                }

                $decodedImage = base64_decode($imageData);
                if ($decodedImage !== false) {
                    $filename = 'iotFeedback_' . time() . '.png';
                    $path = public_path('uploads/iot_files');

                    if (!file_exists($path)) {
                        mkdir($path, 0775, true);
                        @chmod($path, 0775);
                    } elseif (!is_writable($path)) {
                        @chmod($path, 0775);
                        if (!is_writable($path)) {
                            throw new \Exception('Directory is not writable. Please run on server: chmod -R 775 ' . $path . ' or sudo chown -R www-data:www-data ' . $path);
                        }
                    }

                    $filePath = $path . '/' . $filename;
                    if (file_put_contents($filePath, $decodedImage) !== false) {
                        @chmod($filePath, 0664);
                        $imageFilename = $filename;
                    } else {
                        throw new \Exception('Failed to save image file. Please check directory permissions.');
                    }
                }
            }

            IotFeedback::create([
                'message' => $request->message,
                'image' => $imageFilename,
                'userId' => $request->userId,
                'orgId' => $request->orgId,
                'SWOT' => $request->SWOT,
                'themeId' => $request->themeId,
                'feedbackStatus' => '1',
                'initialRiskScore' => 1,
                'mitigatedScore' => '1',
                'status' => 'Active',
            ]);

            $user->increment('EIScore', 100);

            try {
                $imageUrl = $imageFilename ? url('uploads/iot_files/' . $imageFilename) : null;
                Mail::to('offloads@tribe365.co')->send(new PostFeedbackNotificationMail($user, $request->message, $imageUrl));
            } catch (\Exception $e) {
                Log::error('Failed to send offloading email: ' . $e->getMessage());
            }

            return response()->json([
                'code' => 200,
                'status' => true,
                'service_name' => 'iot-post-feedback',
                'message' => 'Record added successfully',
                'data' => [],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 400,
                'status' => false,
                'service_name' => 'iot-post-feedback',
                'message' => $e->getMessage(),
                'data' => [],
            ], 400);
        }
    }

    public function getFeedbackDetail(Request $request)
    {
        try {
            $request->validate([
                'userId' => 'required|exists:users,id',
                'page' => 'nullable|integer|min:1',
            ]);

            $userId = $request->userId;
            $page = $request->input('page', 1);
            $perPage = 10;

            $feedbacks = IotFeedback::where('userId', $userId)
                ->where('status', 'Active')
                ->with(['messages.sender', 'messages.recipient'])
                ->orderBy('id', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $data = $feedbacks->map(function ($feedback) {
                $messages = $feedback->messages()
                    ->where('status', 'Active')
                    ->orderBy('id', 'asc')
                    ->get()
                    ->map(function ($msg) {
                        $sender = User::find($msg->sendFrom);

                        return [
                            'id' => $msg->id,
                            'sendTo' => $msg->sendTo,
                            'sendFrom' => $msg->sendFrom,
                            'name' => $sender ? $sender->name : 'Unknown',
                            'message' => $msg->message,
                            'created_at' => $msg->created_at->format('Y-m-d H:i:s'),
                            'userImageUrl' => $sender && $sender->profile_photo_path
                                ? url('storage/' . $sender->profile_photo_path)
                                : '',
                            'msgImageUrl' => $msg->file ? url('uploads/iot_files/' . $msg->file) : '',
                            'msgFileType' => $msg->file ? $this->getFileType($msg->file) : null,
                            'userType' => $sender && $sender->hasRole('admin') ? 'Admin' : 'User',
                        ];
                    });

                return [
                    'id' => $feedback->id,
                    'message' => $feedback->message,
                    'image' => $feedback->image ? url('uploads/iot_files/' . $feedback->image) : null,
                    'imageFileType' => $feedback->image ? $this->getFileType($feedback->image) : null,
                    'createdAt' => $feedback->created_at->format('Y-m-d H:i:s'),
                    'status' => $feedback->status,
                    'messages' => $messages,
                ];
            });

            return response()->json([
                'code' => 200,
                'status' => true,
                'service_name' => 'iot-get-feedback-detail',
                'message' => '',
                'data' => $data,
                'totalPageCount' => $feedbacks->lastPage(),
                'currentPage' => $feedbacks->currentPage(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 400,
                'status' => false,
                'service_name' => 'iot-get-feedback-detail',
                'message' => $e->getMessage(),
                'data' => [],
            ], 400);
        }
    }

    public function iotSendMsg(Request $request)
    {
        try {
            $request->validate([
                'sendFrom' => 'required|exists:users,id',
                'sendTo' => 'required|exists:users,id',
                'message' => 'nullable|string',
                'image' => 'nullable|string',
                'feedbackId' => 'required|exists:iot_feedbacks,id',
                'postType' => 'required|in:msg,img,video',
            ]);

            $imageFilename = null;

            if ($request->postType === 'img' || ($request->postType === 'msg' && ($request->has('image') && !empty($request->image)))) {
                $imageData = null;
                if ($request->has('image') && !empty($request->image)) {
                    $imageData = $request->image;
                } elseif ($request->postType === 'img' && $request->has('message') && !empty($request->message)) {
                    $imageData = $request->message;
                }

                if ($imageData) {
                    if (Str::startsWith($imageData, 'data:image')) {
                        $imageData = explode(',', $imageData)[1] ?? $imageData;
                    }

                    $decodedImage = base64_decode($imageData);
                    if ($decodedImage !== false) {
                        $filename = 'iot_' . time() . '.png';
                        $path = public_path('uploads/iot_files');

                        if (!file_exists($path)) {
                            mkdir($path, 0775, true);
                            @chmod($path, 0775);
                        } elseif (!is_writable($path)) {
                            @chmod($path, 0775);
                            if (!is_writable($path)) {
                                throw new \Exception('Directory is not writable. Please run on server: chmod -R 775 ' . $path . ' or sudo chown -R www-data:www-data ' . $path);
                            }
                        }

                        $filePath = $path . '/' . $filename;
                        if (file_put_contents($filePath, $decodedImage) !== false) {
                            @chmod($filePath, 0664);
                            $imageFilename = $filename;
                        } else {
                            throw new \Exception('Failed to save image file. Please check directory permissions.');
                        }
                    }
                }
            }

            if ($request->postType === 'video' && $request->has('message') && !empty($request->message)) {
                $videoData = $request->message;
                $extension = 'mp4';

                if (Str::startsWith($videoData, 'data:video')) {
                    $parts = explode(';', $videoData);
                    if (count($parts) > 0) {
                        $mimeType = str_replace('data:video/', '', $parts[0]);
                        $extension = $mimeType === 'quicktime' ? 'mov' : ($mimeType === 'x-msvideo' ? 'avi' : 'mp4');
                    }
                    $videoData = explode(',', $videoData)[1] ?? $videoData;
                }

                $decodedVideo = base64_decode($videoData);
                if ($decodedVideo !== false) {
                    $filename = 'iot_' . time() . '.' . $extension;
                    $path = public_path('uploads/iot_files');

                    if (!file_exists($path)) {
                        mkdir($path, 0775, true);
                        @chmod($path, 0775);
                    } elseif (!is_writable($path)) {
                        @chmod($path, 0775);
                        if (!is_writable($path)) {
                            throw new \Exception('Directory is not writable. Please run on server: chmod -R 775 ' . $path . ' or sudo chown -R www-data:www-data ' . $path);
                        }
                    }

                    $filePath = $path . '/' . $filename;
                    if (file_put_contents($filePath, $decodedVideo) !== false) {
                        @chmod($filePath, 0664);
                        $imageFilename = $filename;
                    } else {
                        throw new \Exception('Failed to save video file. Please check directory permissions.');
                    }
                }
            }

            $messageText = null;
            if ($request->postType === 'msg') {
                $messageText = $request->message;
            }

            IotMessage::create([
                'feedbackId' => $request->feedbackId,
                'message' => $messageText,
                'sendTo' => $request->sendTo,
                'sendFrom' => $request->sendFrom,
                'file' => $imageFilename,
                'status' => 'Active',
            ]);

            try {
                $sender = User::find($request->sendFrom);
                $originalFeedback = IotFeedback::find($request->feedbackId);
                $fileUrl = $imageFilename ? url('uploads/iot_files/' . $imageFilename) : null;
                $messageType = $request->postType === 'msg'
                    ? $request->message
                    : ($request->postType === 'video' ? 'Video message' : 'Image message');

                if ($sender) {
                    Mail::to('team@tribe365.co')->send(new IOTSentMsgMail(
                        $sender,
                        $originalFeedback ? $originalFeedback->message : '',
                        $messageType,
                        $fileUrl
                    ));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send chat message email: ' . $e->getMessage());
            }

            $allMessages = IotMessage::where('feedbackId', $request->feedbackId)
                ->where('status', 'Active')
                ->orderBy('id', 'asc')
                ->get()
                ->map(function ($msg) {
                    $sender = User::find($msg->sendFrom);

                    return [
                        'id' => $msg->id,
                        'sendTo' => $msg->sendTo,
                        'sendFrom' => $msg->sendFrom,
                        'name' => $sender ? $sender->name : 'Unknown',
                        'message' => $msg->message,
                        'created_at' => $msg->created_at->format('Y-m-d H:i:s'),
                        'userImageUrl' => $sender && $sender->profile_photo_path
                            ? url('storage/' . $sender->profile_photo_path)
                            : '',
                        'msgImageUrl' => $msg->file ? url('uploads/iot_files/' . $msg->file) : '',
                        'msgFileType' => $msg->file ? $this->getFileType($msg->file) : null,
                        'userType' => $sender && $sender->hasRole('admin') ? 'Admin' : 'User',
                    ];
                });

            return response()->json([
                'code' => 200,
                'status' => true,
                'service_name' => 'iot-send-msg',
                'message' => '',
                'data' => [
                    'messages' => $allMessages,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 400,
                'status' => false,
                'service_name' => 'iot-send-msg',
                'message' => $e->getMessage(),
                'data' => [],
            ], 400);
        }
    }

    public function getInboxChatList(Request $request)
    {
        try {
            $request->validate([
                'userId' => 'required|exists:users,id',
            ]);

            $feedbacks = IotFeedback::where('userId', $request->userId)
                ->where('status', 'Active')
                ->whereHas('messages', function ($query) {
                    $query->where('status', 'Active');
                })
                ->with(['messages' => function ($query) {
                    $query->where('status', 'Active')
                        ->orderBy('id', 'desc')
                        ->limit(1);
                }])
                ->orderBy('id', 'desc')
                ->get();

            $inbox = $feedbacks->map(function ($feedback) {
                $lastMessage = $feedback->messages->first();

                return [
                    'id' => $feedback->id,
                    'feedback_msg' => $feedback->message,
                    'message' => $lastMessage ? $lastMessage->message : '',
                    'date' => $lastMessage ? $lastMessage->created_at->format('Y-m-d H:i:s') : $feedback->created_at->format('Y-m-d H:i:s'),
                    'image' => $lastMessage && $lastMessage->file ? true : false,
                ];
            });

            return response()->json([
                'code' => 200,
                'status' => true,
                'service_name' => 'iot-inbox-list',
                'message' => '',
                'data' => [
                    'inbox' => $inbox,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 400,
                'status' => false,
                'service_name' => 'iot-inbox-list',
                'message' => $e->getMessage(),
                'data' => [],
            ], 400);
        }
    }

    public function getChatMessages(Request $request)
    {
        try {
            $request->validate([
                'feedbackId' => 'required|exists:iot_feedbacks,id',
            ]);

            $feedback = IotFeedback::findOrFail($request->feedbackId);

            $messages = IotMessage::where('feedbackId', $request->feedbackId)
                ->where('status', 'Active')
                ->orderBy('id', 'asc')
                ->get()
                ->map(function ($msg) {
                    $sender = User::find($msg->sendFrom);

                    return [
                        'id' => $msg->id,
                        'sendTo' => $msg->sendTo,
                        'sendFrom' => $msg->sendFrom,
                        'name' => $sender ? $sender->name : 'Unknown',
                        'message' => $msg->message,
                        'created_at' => $msg->created_at->format('Y-m-d H:i:s'),
                        'userImageUrl' => $sender && $sender->profile_photo_path
                            ? url('storage/' . $sender->profile_photo_path)
                            : '',
                        'msgImageUrl' => $msg->file ? url('uploads/iot_files/' . $msg->file) : '',
                        'msgFileType' => $msg->file ? $this->getFileType($msg->file) : null,
                        'userType' => $sender && $sender->hasRole('admin') ? 'Admin' : 'User',
                    ];
                });

            return response()->json([
                'code' => 200,
                'status' => true,
                'service_name' => 'iot-get-msg',
                'message' => '',
                'data' => [
                    'feedback' => [
                        'initialMessage' => $feedback->message,
                        'initialMsgDate' => $feedback->created_at->format('Y-m-d H:i:s'),
                        'msgImageUrl' => $feedback->image ? url('uploads/iot_files/' . $feedback->image) : null,
                        'msgFileType' => $feedback->image ? $this->getFileType($feedback->image) : null,
                    ],
                    'messages' => $messages,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 400,
                'status' => false,
                'service_name' => 'iot-get-msg',
                'message' => $e->getMessage(),
                'data' => [],
            ], 400);
        }
    }

    public function getThemeList(Request $request)
    {
        try {
            $request->validate([
                'orgId' => 'required|exists:organisations,id',
            ]);

            $themes = IotTheme::where('orgId', $request->orgId)
                ->where('status', 'Active')
                ->orderBy('id', 'desc')
                ->get()
                ->map(function ($theme) {
                    return [
                        'id' => $theme->id,
                        'title' => $theme->title,
                    ];
                });

            return response()->json([
                'code' => 200,
                'status' => true,
                'service_name' => 'iot-get-theme-list',
                'message' => '',
                'data' => [
                    'themeList' => $themes,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 400,
                'status' => false,
                'service_name' => 'iot-get-theme-list',
                'message' => $e->getMessage(),
                'data' => [],
            ], 400);
        }
    }

    private function getFileType($filename)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $videoExtensions = ['mp4', 'mov', 'avi', 'wmv', 'flv', 'webm'];
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($extension, $videoExtensions)) {
            return 'video';
        }

        if (in_array($extension, $imageExtensions)) {
            return 'image';
        }

        return 'unknown';
    }
}

