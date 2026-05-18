<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CotTeamRoleDescription;
use App\Models\CotTeamRoleResult;
use App\Models\Department;
use App\Models\Office;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminCOTController extends Controller
{
    public function listTeamRoleMapDescription()
    {
        $descriptions = CotTeamRoleDescription::orderBy('order')->get();
        return view('admin.COT.listTeamRoleMapDescription', compact('descriptions'));
    }

    public function editTeamRoleMapDescription($id)
    {
        $description = CotTeamRoleDescription::findOrFail($id);
        return view('admin.COT.editTeamRoleMapDescription', compact('description'));
    }

    public function updateTeamRoleMapDescription(Request $request, $id)
    {
        $description = CotTeamRoleDescription::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'value_focus' => 'required|string|max:255',
            'description' => 'nullable|string',
            'focus' => 'nullable|string',
            'standard_questions' => 'nullable|string',
            'disruption' => 'nullable|string',
            'order' => 'nullable|integer',
            'status' => 'required|in:Active,Inactive',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $description->update($validator->validated());

        return redirect()->route('admin.cot.team-role-descriptions.index')
            ->with('success', 'Role description updated successfully.');
    }

    public function teamRoleMapResults(Request $request)
    {
        $query = CotTeamRoleResult::with(['user', 'organisation', 'roleDescription']);

        if ($request->has('user_id') && $request->user_id) {
            $query->where('userId', $request->user_id);
        }

        if ($request->has('org_id') && $request->org_id) {
            $query->where('orgId', $request->org_id);
        }

        if ($request->has('office_id') && $request->office_id) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('officeId', $request->office_id);
            });
        }

        if ($request->has('department_id') && $request->department_id) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('departmentId', $request->department_id);
            });
        }

        // Get latest assessment_date per user correctly using a subquery join
        $latestSubquery = DB::table('cot_team_role_results')
            ->select('userId', DB::raw('MAX(assessment_date) as latest_date'))
            ->groupBy('userId');

        $results = $query->select('cot_team_role_results.*')
            ->joinSub($latestSubquery, 'latest', function ($join) {
                $join->on('cot_team_role_results.userId', '=', 'latest.userId')
                     ->on('cot_team_role_results.assessment_date', '=', 'latest.latest_date');
            })
            ->orderBy('cot_team_role_results.userId')
            ->orderBy('cot_team_role_results.preference_rank')
            ->get()
            ->groupBy('userId');

        $organisations = Organisation::all();
        $offices = Office::when($request->org_id, fn ($q) => $q->where('organisation_id', $request->org_id))->get();
        $departments = Department::when($request->org_id, fn ($q) => $q->where('organisation_id', $request->org_id))->get();
        $users = User::when($request->org_id, fn ($q) => $q->where('orgId', $request->org_id))->get();

        return view('admin.COT.teamRoleMapResults', compact(
            'results',
            'organisations',
            'offices',
            'departments',
            'users'
        ));
    }

    public function exportTeamRoleMapResults(Request $request)
    {
        $query = CotTeamRoleResult::with(['user', 'organisation', 'roleDescription']);

        if ($request->has('org_id') && $request->org_id) {
            $query->where('orgId', $request->org_id);
        }

        $results = $query->get();

        $filename = 'team_role_map_results_' . date('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($results) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['User', 'Organisation', 'Role', 'Score', 'Preference Rank', 'Assessment Date']);

            foreach ($results as $result) {
                fputcsv($file, [
                    $result->user->name ?? '',
                    $result->organisation->name ?? '',
                    $result->roleDescription->title ?? $result->role_key,
                    $result->score,
                    $result->preference_rank,
                    optional($result->assessment_date)->format('Y-m-d') ?? (string) $result->assessment_date,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

