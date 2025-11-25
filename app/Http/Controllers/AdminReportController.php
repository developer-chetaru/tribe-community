<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Office;
use App\Models\Department;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminReportController extends Controller
{
  
    /**
    * Generate Happy Index report for a given payload.
    *
    * Results are upserted into `happy_index_dashboard_graphs`.
    *
    * @param array $perArr Payload containing:
    *  - 'officeId' (int): Office ID to generate report for
    *  - 'date' (string|null): Optional date (Y-m-d or datetime string)
    *
    * @return void|null
    */
    public function getHappyIndexReport($perArr = [])
    {
        $officeId = data_get($perArr, 'officeId'); 
        $dateIn   = data_get($perArr, 'date');     

        $date = $dateIn
            ? Carbon::parse($dateIn)->toDateString()
            : now()->toDateString();

      
        if (!$officeId) {
            \Log::warning('getHappyIndexReport: Missing officeId in payload', $perArr);
            return null;
        }

        $office = Office::with(['users' => function ($query) use ($date) {
                $query->where('status', 1)
                      ->whereDate('created_at', '<=', $date);
            }])
            ->where('id', $officeId)
            ->whereDate('created_at', '<=', $date)
            ->first();

 
        if (!$office) {
            Log::channel('daily')->warning("Office not found with id: $officeId");
            return;
        }

        $orgId = $office->organisation_id ?? null;
        if (!$orgId) {
            Log::channel('daily')->warning("Organization ID missing for office id: $officeId");
            return;
        }

        $indexHappyIndexOrg = $this->calculateHappyIndexGraph(base64_encode($orgId), '', '', $date);
        foreach ($indexHappyIndexOrg as $happyIndexOrg) {
            $categoryId = str_replace('.', '_', $happyIndexOrg['id']);
            $this->upsertHappyIndex($date, $orgId, $categoryId, null, null, $happyIndexOrg['percentage']);
        }

        $indexHappyIndexOffice = $this->calculateHappyIndexGraph(base64_encode($orgId), $officeId, '', $date);
        foreach ($indexHappyIndexOffice as $happyIndexOffice) {
            $categoryId = str_replace('.', '_', $happyIndexOffice['id']);
            $this->upsertHappyIndex($date, $orgId, $categoryId, $officeId, null, $happyIndexOffice['percentage']);
        }

        $departments = Department::where('organisation_id', $orgId)
            ->where('office_id', $officeId)
            ->whereDate('created_at', '<=', $date)
            ->with(['users' => function ($query) {
                $query->where('status', 1);
            }])
            ->get();

        foreach ($departments as $department) {
            if ($department->users->count() === 0) continue;

            $departmentId = $department->id;
            $allDepartmentId = $department->all_department_id ?? null;

            // All Departments (without officeId)
            $indexHappyIndexAllDept = $this->calculateHappyIndexGraph(base64_encode($orgId), '', $allDepartmentId, $date);
            foreach ($indexHappyIndexAllDept as $happyIndexAllDept) {
                $categoryId = str_replace('.', '_', $happyIndexAllDept['id']);
                $this->upsertHappyIndex($date, $orgId, $categoryId, null, $allDepartmentId, $happyIndexAllDept['percentage']);
            }

            // Specific Department + Office
            $indexHappyIndexDept = $this->calculateHappyIndexGraph(base64_encode($orgId), $officeId, $departmentId, $date);
            foreach ($indexHappyIndexDept as $happyIndexDept) {
                $categoryId = str_replace('.', '_', $happyIndexDept['id']);
                $this->upsertHappyIndex($date, $orgId, $categoryId, $officeId, $departmentId, $happyIndexDept['percentage']);
            }
        }
    }

    /**
    * Insert or update a Happy Index record in the dashboard table.
    *
    * @param string $date Date in Y-m-d format
    * @param int $orgId Organization ID
    * @param string $categoryId Mood category ID
    * @param int|null $officeId Optional Office ID
    * @param int|null $departmentId Optional Department ID
    * @param float $percentage Mood percentage
    *
    * @return void
    */
   private function upsertHappyIndex($date, $orgId, $categoryId, $officeId = null, $departmentId = null, $percentage)
	{
    	$date = \Carbon\Carbon::parse($date)->toDateString(); // ensure Y-m-d

    	$cleanCategoryId = str_replace('.', '_', $categoryId);

    	$query = DB::table('happy_index_dashboard_graphs')
        	->whereDate('date', $date)
       	 	->where('orgId', $orgId)
        	->where('categoryId', $cleanCategoryId);

    	if ($officeId) {
        	$query->where('officeId', $officeId);
    	} else {
       		$query->whereNull('officeId');
    	}

    	if ($departmentId) {
        	$query->where('departmentId', $departmentId);
    	} else {
        	$query->whereNull('departmentId');
    	}

    	$existing = $query->first();

    	$data = [
        	'with_weekend' => $percentage,
        	'updated_at'   => now(),
    	];

    	$insertData = [
        	'date'         => $date,
        	'orgId'        => $orgId,
        	'categoryId'   => $cleanCategoryId,
        	'officeId'     => $officeId,
        	'departmentId' => $departmentId,
        	'with_weekend' => $percentage,
        	'status'       => 1,         
        	'created_at'   => now(),
        	'updated_at'   => now(),
    	];

    	$cleanInsertData = $this->sanitizeKeysRecursive($insertData);

    	if ($existing) {
        	Log::channel('daily')->info("Updating HappyIndex: " . json_encode($data));
        	$query->update($data);
    	} else {
        	Log::channel('daily')->info("Inserting HappyIndex: " . json_encode($cleanInsertData));
        	DB::table('happy_index_dashboard_graphs')->insert($cleanInsertData);
    	}
	}
  
	/**
 	* Calculate Happy Index percentage distribution for given filters.
 	*
 	* @param  string      $orgIdEncoded  
 	* @param  string|null $officeId      
 	* @param  string|null $departmentId  
 	* @param  string      $date          
 	* @param  int|false   $categoryId    
 	*
 	* @return array<int, array<string, mixed>> Returns an array of mood values with percentages.
	*/
    public function calculateHappyIndexGraph($orgIdEncoded, $officeId = '', $departmentId = '', $date, $categoryId = false)
    {
        $orgId = base64_decode($orgIdEncoded);

        $happyIndexMoodValuesQuery = DB::table('happy_index_mood_values');

        if (!empty($categoryId)) {
            $happyIndexMoodValuesQuery->where('id', $categoryId);
        }

        $happyIndexMoodValues = $happyIndexMoodValuesQuery->get();

        $totalUsersQuery = DB::table('users')
            ->where('status', 1)
            ->whereDate('created_at', '<=', $date)
           
            ->where('onLeave', 0);

        if ($orgId) {
            $totalUsersQuery->where('orgId', $orgId);
        }
        if ($officeId) {
            $totalUsersQuery->where('officeId', $officeId);
        }
        if ($departmentId) {
            $totalUsersQuery->where('departmentId', $departmentId);
        }

        $totalUsers = $totalUsersQuery->count();

        $happyIndexResultArray = [];

      	$date = Carbon::parse($date)->format('Y-m-d'); 
      
 	
	foreach ($happyIndexMoodValues as $moodVal) {
    	$moodCountQuery = DB::table('happy_indexes')
        	->join('users', 'users.id', '=', 'happy_indexes.user_id')
        	->where('users.onLeave', 0)
        	->where('users.status', 1)
        	->where('happy_indexes.status', 'active')
 			->where('happy_indexes.mood_value', 3)
        	->whereDate('happy_indexes.created_at', $date) ;
  

    if ($orgId) {
        $moodCountQuery->where('users.orgId', $orgId);
    }

    if ($officeId) {
        $moodCountQuery->where('users.officeId', $officeId);
    }

    if ($departmentId) {
        $moodCountQuery->where('users.departmentId', $departmentId);
    }

    $moodCount = $moodCountQuery->count();

    $percentage = ($moodCount && $totalUsers) ? round(($moodCount / $totalUsers) * 100, 2) : 0;
  
            $happyIndexResultArray[] = [
                'id' => $moodVal->id,
                'percentage' => $percentage,
            ];
        }
	
        return $happyIndexResultArray;
    }

  	/**
 	* Recursively sanitize array keys by replacing dots with underscores.
 	*
 	* @param  array $array 
 	* @return array      
 	*
 	*/
    private function sanitizeKeysRecursive($array)
    {
        $sanitized = [];

        foreach ($array as $key => $value) {
            $cleanKey = str_replace('.', '_', $key);

            if (is_array($value)) {
                $sanitized[$cleanKey] = $this->sanitizeKeysRecursive($value);
            } else {
                $sanitized[$cleanKey] = $value;
            }
        }

        return $sanitized;
    }
}
