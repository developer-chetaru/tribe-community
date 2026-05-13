<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\IOTSentMsgMail;
use App\Models\IotAllocatedTheme;
use App\Models\IotAdminChatMessage;
use App\Models\IotFeedback;
use App\Models\IotFeedbackStatus;
use App\Models\IotMessage;
use App\Models\IotNotification;
use App\Models\IotRiskPriority;
use App\Models\IotTheme;
use App\Models\Office;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;

class AdminIOTController extends Controller
{
    private function checkAccess(): void
    {
        if (Session::get('improvementPassword') !== 'done') {
            abort(403, 'Access denied. Please verify password.');
        }
    }

    public function getIotDeshboard($orgId)
    {
        $this->checkAccess();

        $org = Organisation::findOrFail($orgId);
        $offices = Office::where('organisation_id', $orgId)->get();

        $activeUserStatuses = ['active_verified', 'active_unverified', '1'];

        $activeOrgUserIds = DB::table('users')
            ->whereIn('status', $activeUserStatuses)
            ->pluck('id');

        $messagesFeedbackIds = DB::table('iot_messages')->distinct()->pluck('feedbackId');

        $newCount = DB::table('iot_feedbacks')
            ->where('status', 'Active')
            ->where('orgId', $orgId)
            ->whereIn('userId', $activeOrgUserIds)
            ->whereNotIn('id', $messagesFeedbackIds)
            ->count();

        $onHoldCount = DB::table('iot_feedbacks')
            ->where('status', 'Active')
            ->where('orgId', $orgId)
            ->whereIn('userId', $activeOrgUserIds)
            ->whereIn('id', $messagesFeedbackIds)
            ->count();

        $completedCount = DB::table('iot_feedbacks')
            ->where('status', 'Completed')
            ->where('orgId', $orgId)
            ->whereIn('userId', $activeOrgUserIds)
            ->count();

        $awaitingCount = DB::table('iot_feedbacks')
            ->where('status', 'Active')
            ->where('orgId', $orgId)
            ->whereIn('userId', $activeOrgUserIds)
            ->whereIn('id', function ($query) {
                $query->select('feedbackId')
                    ->from('iot_messages')
                    ->where('status', 'Active')
                    ->whereRaw('id = (SELECT MAX(id) FROM iot_messages WHERE feedbackId = iot_feedbacks.id AND status = "Active")')
                    ->whereNotIn('sendFrom', function ($q) {
                        $q->select('id')->from('users')->whereIn('id', function ($uq) {
                            $uq->select('model_id')
                                ->from('model_has_roles')
                                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                                ->where('roles.name', 'admin');
                        });
                    });
            })
            ->count();

        return view('admin.iot.dashboard', compact('org', 'offices', 'newCount', 'onHoldCount', 'completedCount', 'awaitingCount'));
    }

    public function feedbackList($orgId, $status, $officeId = null)
    {
        $this->checkAccess();

        $activeUserStatuses = ['active_verified', 'active_unverified', '1'];
        $activeOrgUserIds = DB::table('users')->whereIn('status', $activeUserStatuses)->pluck('id');
        $messagesFeedbackIds = DB::table('iot_messages')->distinct()->pluck('feedbackId');

        $query = DB::table('iot_feedbacks')
            ->leftJoin('users', 'users.id', 'iot_feedbacks.userId')
            ->leftJoin('offices', 'offices.id', 'users.officeId')
            ->where('iot_feedbacks.orgId', $orgId)
            ->whereIn('iot_feedbacks.userId', $activeOrgUserIds)
            ->select(
                'iot_feedbacks.*',
                'users.first_name',
                'users.last_name',
                'users.email',
                'offices.name as office_name'
            );

        switch ($status) {
            case 'new':
                $query->where('iot_feedbacks.status', 'Active')
                    ->whereNotIn('iot_feedbacks.id', $messagesFeedbackIds);
                break;
            case 'on_hold':
                $query->where('iot_feedbacks.status', 'Active')
                    ->whereIn('iot_feedbacks.id', $messagesFeedbackIds);
                break;
            case 'completed':
                $query->where('iot_feedbacks.status', 'Completed');
                break;
            case 'awaiting':
                $query->where('iot_feedbacks.status', 'Active')
                    ->whereIn('iot_feedbacks.id', function ($q) {
                        $q->select('feedbackId')
                            ->from('iot_messages')
                            ->where('status', 'Active')
                            ->whereRaw('id = (SELECT MAX(id) FROM iot_messages WHERE feedbackId = iot_feedbacks.id AND status = "Active")')
                            ->whereNotIn('sendFrom', function ($sq) {
                                $sq->select('id')->from('users')->whereIn('id', function ($uq) {
                                    $uq->select('model_id')
                                        ->from('model_has_roles')
                                        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                                        ->where('roles.name', 'admin');
                                });
                            });
                    });
                break;
        }

        if ($officeId) {
            $query->where('users.officeId', $officeId);
        }

        $feedbacks = $query->orderBy('iot_feedbacks.id', 'desc')->get();

        return view('admin.iot.feedback-list', compact('feedbacks', 'status', 'orgId', 'officeId'));
    }

    public function iotChatbox($feedbackId)
    {
        $this->checkAccess();

        $feedback = IotFeedback::with(['user', 'organisation', 'messages.sender', 'messages.recipient', 'themes'])
            ->findOrFail($feedbackId);

        $statuses = IotFeedbackStatus::where('status', 'Active')
            ->orderBy('id')
            ->get();

        $themes = IotTheme::where('orgId', $feedback->orgId)
            ->where('status', 'Active')
            ->get();

        $riskPriorities = IotRiskPriority::where('status', 'Active')->get();

        return view('admin.iot.chatbox', compact('feedback', 'statuses', 'themes', 'riskPriorities'));
    }

    public function sendChatMessagesFromAdmin(Request $request)
    {
        $this->checkAccess();

        $request->validate([
            'feedbackId' => 'required|exists:iot_feedbacks,id',
            'message' => 'nullable|string',
            'sendTo' => 'required|exists:users,id',
            'sendFrom' => 'required|exists:users,id',
            'file' => 'nullable|file|mimes:jpeg,jpg,png,gif,mp4,mov,avi,wmv,flv,webm|max:10240',
        ]);

        $imageFilename = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();
            $filename = 'iot_' . time() . '.' . $extension;
            $path = public_path('uploads/iot_files');

            if (!file_exists($path)) {
                mkdir($path, 0755, true);
            }

            $file->move($path, $filename);
            $imageFilename = $filename;
        }

        $message = IotAdminChatMessage::create([
            'feedbackId' => $request->feedbackId,
            'message' => $request->message,
            'sendTo' => $request->sendTo,
            'sendFrom' => $request->sendFrom,
            'file' => $imageFilename,
            'status' => 'Active',
        ]);

        IotMessage::create([
            'feedbackId' => $request->feedbackId,
            'message' => $request->message,
            'sendTo' => $request->sendTo,
            'sendFrom' => $request->sendFrom,
            'file' => $imageFilename,
            'status' => 'Active',
        ]);

        $recipient = User::find($request->sendTo);
        $sender = User::find($request->sendFrom);
        $feedback = IotFeedback::find($request->feedbackId);

        if ($recipient) {
            // In-app notification to user
            IotNotification::create([
                'title' => 'Admin replied to your Offloading',
                'description' => 'You have a new reply on Feedback #' . $request->feedbackId,
                'to_bubble_user_id' => $recipient->id,
                'from_bubble_user_id' => $request->sendFrom,
                'notificationType' => 'offloading-chat',
                'notificationLinks' => route('offloading.chat', ['feedbackId' => $request->feedbackId]),
                'status' => 'Active',
                'archive' => false,
            ]);

            // Email notification to user
            if ($sender && $feedback) {
                try {
                    $imageUrl = $imageFilename ? url('uploads/iot_files/' . $imageFilename) : null;
                    Mail::to($recipient->email)->send(
                        new IOTSentMsgMail($sender, $feedback->message, $request->message, $imageUrl)
                    );
                } catch (\Exception $e) {
                    Log::error('Offloading chat email to user failed: ' . $e->getMessage());
                }
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Message sent successfully',
            'data' => $message,
        ]);
    }

    public function updateFeedback(Request $request)
    {
        $this->checkAccess();

        $request->validate([
            'feedbackId' => 'required|exists:iot_feedbacks,id',
            'feedbackSummary' => 'nullable|string',
            'actionTaken' => 'nullable|string',
            'feedbackStatus' => 'nullable|string',
            'initialRiskScore' => 'nullable|integer',
            'mitigatedScore' => 'nullable|string',
            'updatedText' => 'nullable|string',
            'status' => 'nullable|in:Active,Inactive,Completed',
        ]);

        $feedback = IotFeedback::findOrFail($request->feedbackId);

        $feedback->update($request->only([
            'feedbackSummary',
            'actionTaken',
            'feedbackStatus',
            'initialRiskScore',
            'mitigatedScore',
            'updatedText',
            'status',
        ]));

        return response()->json([
            'status' => true,
            'message' => 'Feedback updated successfully',
            'data' => $feedback,
        ]);
    }

    public function assignTheme(Request $request)
    {
        $this->checkAccess();

        $request->validate([
            'feedbackId' => 'required|exists:iot_feedbacks,id',
            'themeId' => 'required|exists:iot_themes,id',
        ]);

        $existing = IotAllocatedTheme::where('feedbackId', $request->feedbackId)
            ->where('themeId', $request->themeId)
            ->where('status', 'Active')
            ->first();

        if ($existing) {
            return response()->json([
                'status' => false,
                'message' => 'Theme already assigned to this feedback',
            ], 400);
        }

        IotAllocatedTheme::create([
            'feedbackId' => $request->feedbackId,
            'themeId' => $request->themeId,
            'status' => 'Active',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Theme assigned successfully',
        ]);
    }

    public function themeList($orgId)
    {
        $this->checkAccess();

        $themes = IotTheme::where('orgId', $orgId)
            ->orderBy('id', 'desc')
            ->get();

        return view('admin.iot.theme-list', compact('themes', 'orgId'));
    }

    public function addTheme($orgId)
    {
        $this->checkAccess();

        return view('admin.iot.add-theme', compact('orgId'));
    }

    public function storeTheme(Request $request)
    {
        $this->checkAccess();

        $request->validate([
            'orgId' => 'required|exists:organisations,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|integer',
            'submission' => 'nullable|string|max:255',
            'initialLikelihood' => 'nullable|in:0,1,2,3,4,5',
            'initialConsequence' => 'nullable|in:0,1,2,3,4,5',
        ]);

        $initialRiskRating = null;
        if ($request->initialLikelihood && $request->initialConsequence) {
            $initialRiskRating = (int) $request->initialLikelihood * (int) $request->initialConsequence;
        }

        IotTheme::create([
            'dateOpened' => now(),
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'orgId' => $request->orgId,
            'submission' => $request->submission,
            'initialLikelihood' => $request->initialLikelihood,
            'initialConsequence' => $request->initialConsequence,
            'initialRiskRating' => $initialRiskRating,
            'currentLikelihood' => $request->initialLikelihood,
            'currentConsequence' => $request->initialConsequence,
            'currentRiskRating' => $initialRiskRating,
            'themeStatus' => 'Open',
            'status' => 'Active',
        ]);

        return redirect()->route('admin.theme-list', $request->orgId)
            ->with('success', 'Theme created successfully');
    }

    public function editTheme($themeId)
    {
        $this->checkAccess();

        $theme = IotTheme::findOrFail($themeId);

        return view('admin.iot.edit-theme', compact('theme'));
    }

    public function updateTheme(Request $request, $themeId)
    {
        $this->checkAccess();

        $theme = IotTheme::findOrFail($themeId);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|integer',
            'submission' => 'nullable|string|max:255',
            'currentLikelihood' => 'nullable|in:0,1,2,3,4,5',
            'currentConsequence' => 'nullable|in:0,1,2,3,4,5',
            'linkedAction' => 'nullable|string|max:255',
            'comment' => 'nullable|string',
            'themeStatus' => 'nullable|in:Open,Closed',
            'status' => 'nullable|in:Active,Inactive',
        ]);

        $currentRiskRating = null;
        if ($request->currentLikelihood && $request->currentConsequence) {
            $currentRiskRating = (int) $request->currentLikelihood * (int) $request->currentConsequence;
        }

        $theme->update([
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'submission' => $request->submission,
            'currentLikelihood' => $request->currentLikelihood,
            'currentConsequence' => $request->currentConsequence,
            'currentRiskRating' => $currentRiskRating,
            'linkedAction' => $request->linkedAction,
            'comment' => $request->comment,
            'themeStatus' => $request->themeStatus,
            'status' => $request->status,
        ]);

        return redirect()->route('admin.theme-list', $theme->orgId)
            ->with('success', 'Theme updated successfully');
    }
}

