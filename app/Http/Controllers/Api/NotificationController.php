<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\{Organisation, IotNotification};

class NotificationController extends Controller
{
    
    public function viewNotificationList(Request $request)
    {
        try {
            $userId = $request->input('userId');
            $orgId = Auth::user()->orgId ?? null;

            if (!$userId) {
                return response()->json([
                    'code' => 400,
                    'status' => false,
                    'message' => 'User ID is required.'
                ], 400);
            }

            if (!$orgId) {
                return response()->json([
                    'code' => 400,
                    'status' => false,
                    'message' => 'User is not linked to any organisation.'
                ], 400);
            }

            
            $organisation = Organisation::where('id', $orgId)
                ->where('status', 'Active')
                ->first();

            $appPaymentVersion = $organisation->appPaymentVersion ?? 0;
          
            $counts = [
                'custom_notification' => 0,
            ];

            
            $counts['custom_notification'] = IotNotification::where('to_bubble_user_id', $userId)
				->where('archive', false)
                ->where('notificationType', 'custom notification')
                ->where(function ($q) {
                    if (DB::getSchemaBuilder()->hasColumn('iot_notifications', 'is_read')) {
                        $q->where('is_read', 0);
                    }
                })
                ->count();
          
            $notifications = IotNotification::where('to_bubble_user_id', $userId)
				->where('archive', false)
                ->where('notificationType', 'custom notification')
                ->orderBy('created_at', 'desc')
                ->get([
                    'id',
                    'title',
                    'description',
                    'notificationLinks',
                    'status',
                    'notificationType',
                    'created_at'
                ]);

            return response()->json([
                'code' => 200,
                'status' => true,
                'service_name' => 'DOT-bubble-ratings-notification',
                'message' => 'Unread bubble notifications retrieved successfully.',
                'data' => [
                    'notifications' => $notifications->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'title' => $item->title,
                            'description' => $item->description,
                            'notificationLinks' => $item->notificationLinks,
                            'read_status' => $item->status,
                            'notificationType' => $item->notificationType,
                            'created_at' => $item->created_at->toDateTimeString(),
                        ];
                    }),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'Something went wrong.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

	
	public function notificationArchive(Request $request)
    {
        try {
            $userId = $request->input('userId');

            if (!$userId) {
                return response()->json([
                    'code' => 400,
                    'status' => false,
                    'message' => 'Invalid request. userId is required.'
                ], 400);
            }

            // ✅ Update all notifications where archive = false for this user
            $updated = IotNotification::where('to_bubble_user_id', $userId)
                ->where('archive', false)
                ->update(['archive' => true]);

            return response()->json([
                'code' => 200,
                'status' => true,
                'message' => $updated > 0
                    ? 'All notifications moved to trash successfully.'
                    : 'No notifications found to move.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'Failed to move notifications to trash.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

	public function getArchivedNotifications(Request $request)
    {
        try {
            $userId = $request->input('userId');

            if (!$userId) {
                return response()->json([
                    'code' => 400,
                    'status' => false,
                    'message' => 'Invalid request. userId is required.'
                ], 400);
            }

            $archivedNotifications = IotNotification::where('to_bubble_user_id', $userId)
                ->where('archive', true)
                ->orderBy('created_at', 'desc')
                ->get([
                    'id',
                    'title',
                    'description',
                    'notificationLinks',
                    'status',
                    'notificationType',
                    'archive',
                    'created_at'
                ]);

            return response()->json([
                'code' => 200,
                'status' => true,
                'message' => $archivedNotifications->isEmpty()
                    ? 'No archived notifications found.'
                    : 'Archived notifications retrieved successfully.',
                'data' => [
                    'count' => $archivedNotifications->count(),
                    'notifications' => $archivedNotifications->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'title' => $item->title,
                            'description' => $item->description,
                            'notificationLinks' => $item->notificationLinks,
                            'status' => $item->status,
                            'notificationType' => $item->notificationType,
                            'archive' => (bool)$item->archive,
                            'created_at' => $item->created_at->toDateTimeString(),
                        ];
                    }),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'Failed to fetch archived notifications.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

	public function markAllAsRead(Request $request)
    {
        try {
            $userId = $request->input('userId');

            if (!$userId) {
                return response()->json([
                    'code' => 400,
                    'status' => false,
                    'message' => 'userId is required'
                ], 400);
            }

            IotNotification::where('to_bubble_user_id', $userId)
                ->where('status', 'Active')
                ->update(['status' => 'Read']);

            $unreadCount = IotNotification::where('to_bubble_user_id', $userId)
                ->where('status', 'Active')
                ->count();

            return response()->json([
                'code' => 200,
                'status' => true,
                'message' => 'All notifications marked as read successfully.',
                'unread_count' => $unreadCount,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'Failed to mark notifications as read.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
