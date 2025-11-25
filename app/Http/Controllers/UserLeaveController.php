<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserLeave;
use App\Services\EngagementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserLeaveController extends Controller
{
    protected $engagementService;

    public function __construct(EngagementService $engagementService)
    {
        $this->engagementService = $engagementService;
    }

    /**
     * Apply for leave for a specific user.
     *
     * Validates input, creates a leave record, updates user's leave status,
     * and recalculates the user's engagement score.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function userApplyLeave(Request $request)
    {
        $request->validate([
            'userId'    => 'required|exists:users,id',
            'startDate' => 'required|date',
            'endDate'   => 'required|date',
        ]);

        $user = User::where('id', $request->userId)
            ->where('status', '1')
            ->first();

        if (! $user) {
            return response()->json([
                'code'         => 400,
                'status'       => false,
                'service_name' => 'user-apply-leave',
                'message'      => 'User not found',
                'data'         => (object) [],
            ]);
        }

        //  Create leave using ORM
        UserLeave::create([
            'user_id'      => $user->id,
            'start_date'   => Carbon::parse($request->startDate)->toDateString(),
            'end_date'     => Carbon::parse($request->endDate)->toDateString(),
            'resume_date'  => Carbon::parse($request->endDate)->addDay()->toDateString(),
            'leave_status' => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $userDataArr = [
            'orgId'               => $user->orgId,
            'userId'              => $user->id,
            'HI_include_saturday' => $user->HI_include_saturday,
            'HI_include_sunday'   => $user->HI_include_sunday,
        ];

        $eiScore = $this->engagementService->getUserEngagementIndexForLastDay($userDataArr, now()->toDateString());

        $user->onLeave    = 1;
        $user->EIScore    = $eiScore;
        $user->updated_at = now();
        $user->save();

        return response()->json([
            'code'         => 200,
            'status'       => true,
            'service_name' => 'user-apply-leave',
            'message'      => 'Your leave applied successfully',
            'data'         => ['leaveStatus' => 1],
        ]);
    }

    /**
     * Change the user's leave status to "resumed work".
     *
     * Updates the latest active leave record and sets user as back to work.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function userChangeLeaveStatus(Request $request)
    {
        $userId = $request->input('userId');

        if (! $userId) {
            return response()->json([
                'code'         => 400,
                'status'       => false,
                'service_name' => 'user-change-leave-status',
                'message'      => 'User ID is required',
                'data'         => (object) [],
            ]);
        }

        $user = DB::table('users')
            ->select('id', 'orgId', 'HI_include_saturday', 'HI_include_sunday')
            ->where('id', $userId)
            ->where('status', 1)
            ->first();

        if (! $user) {
            return response()->json([
                'code'         => 400,
                'status'       => false,
                'service_name' => 'user-change-leave-status',
                'message'      => 'User not found',
                'data'         => (object) [],
            ]);
        }

        $userLeave = UserLeave::where('user_id', $userId)
            ->where('leave_status', 1)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($userLeave) {
            $userLeave->update([
                'resume_date'  => now()->toDateString(),
                'leave_status' => 0,
                'updated_at'  => now(),
            ]);
        }

        DB::table('users')
            ->where('id', $userId)
            ->where('status', '1')
            ->update([
                'onLeave'    => 0,
                'updated_at' => now(),
            ]);

        return response()->json([
            'code'         => 200,
            'status'       => true,
            'service_name' => 'user-change-leave-status',
            'message'      => 'You are back to work',
            'data'         => ['leave_status' => 0],
        ]);
    }

}
