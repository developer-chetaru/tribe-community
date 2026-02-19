<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Helpers\CommonHelper;
use Carbon\Carbon; 
class EngagementReportService
{
    public function individualUserEngageDotReport($perArray = [], $date = false)
    {
        $orgId     = $perArray['orgId'];
        $userId    = $perArray['userId'];
        $month     = $perArray['month'];
        $monthYear = date('Y-m-t', strtotime($month));

        $query = DB::table('dot_values_individual_user_status')
            ->select('completeStatus')
            ->where('orgId', $orgId)
            ->where('userId', $userId);

        if (!empty($month)) {
            $query->whereDate('date', '<=', $monthYear);
        }

        if (!empty($date)) {
            $query->whereDate('date', '<=', $date);
        }

        $row = $query->orderBy('date', 'DESC')->first();

        if (!empty($row) && $row->complete_status == 1) {
            return true;
        } elseif (!empty($row) && $row->complete_status == 0) {
            return false;
        } else {
            return false;
        }
    }

    public function individualUserEngageDiagnosticReport($perArray = [], $date = false)
    {

    }

    public function individualUserEngageFeedbackReport($perArray = [], $date = false)
    {

    }

    public function individualUserEngagePersonalityTypeReport($perArray = [], $date = false)
    {
   
    }

    public function individualUserEngageCultureStructureReport($perArray = [], $date = false)
    {
  
    }

    public function individualUserEngageMotivationReport($perArray = [], $date = false)
    {

    }

    public function individualUserEngageTeamRoleReport($perArray = [], $date = false)
    {
  
    }

    public function individualUserEngageTribeometerReport($perArray = [], $date = false)
    {

    }
    
    
    public function individualUserEngageHappyIndexReport($perArray = [] , $date = false)
    {
        $orgId               = $perArray['orgId'];
        $userId              = $perArray['userId'];
        $month               = $perArray['month'];
        $HI_include_saturday = $perArray['HI_include_saturday'];
        $HI_include_sunday   = $perArray['HI_include_sunday'];

        if (! empty($month)) {
            $divideMonthYear = explode('-', $month);
            $year            = $divideMonthYear[0];
            $singleMonth     = $divideMonthYear[1];

            //check user created date
            $userData = DB::table('users')
                ->select('created_at')
                ->where('id', $userId)
                ->where('status', 'Active')
                ->where('roleId', 3)
                ->first();

            if (! empty($userData)) {
                if (date('Y-m-d', strtotime($userData->created_at)) > $month . "-01") {
                    $startDate = date('Y-m-d', strtotime($userData->created_at));
                } else {
                    $startDate = date('Y-m-d', strtotime($month . "-01"));
                }
            }

            if (! empty($month) && ($month == date('Y-m'))) {
                $noOfDaysInMonth = date('j');
                // $noOfDaysInMonth = date('j')-1;
                $lastDate = date('Y-m-d', strtotime($month . "-" . $noOfDaysInMonth));
            } else {
                $noOfDaysInMonth = cal_days_in_month(CAL_GREGORIAN, $singleMonth, $year);
                $lastDate        = date('Y-m-t', strtotime($month));
            }

            //Get weekends
            if ($HI_include_saturday == 1 && $HI_include_sunday == 2) {
                $getWeekendDates = CommonHelper::getDatesFromRangeExcludeSunday($startDate, $lastDate);
            } elseif ($HI_include_saturday == 2 && $HI_include_sunday == 1) {
                 $getWeekendDates = CommonHelper::getDatesFromRangeExcludeSaturday($startDate, $lastDate);
            } elseif ($HI_include_saturday == 1 && $HI_include_sunday == 1) {
                $getWeekendDates = [];
            } elseif ($HI_include_saturday == 2 && $HI_include_sunday == 2) {
                  $getWeekendDates = CommonHelper::getDatesFromRange($startDate, $lastDate);
            }

            $moodCount = 0;
            for ($i = 1; $i <= $noOfDaysInMonth; $i++) {

                $moodCountQuery = DB::table('happy_indexes')
                    ->select('happy_indexes.id')
                    ->leftjoin('users', 'users.id', 'happy_indexes.user_id')
                    ->where('happy_indexes.user_id', $userId)
                    ->where('users.status', 'Active')
                    ->where('happy_indexes.status', 'Active')
                    ->whereDate('happy_indexes.created_at', $year . "-" . $singleMonth . "-" . $i);
                if (! empty($getWeekendDates)) {
                    $moodCountQuery->whereNotIn(DB::raw("(DATE_FORMAT(happy_indexes.created_at,'%Y-%m-%d'))"), $getWeekendDates);
                }
                if (! empty($orgId)) {
                    $moodCountQuery->where('users.orgId', $orgId);
                }
                if (! empty($officeId) && empty($departmentId)) {
                    $moodCountQuery->where('users.officeId', $officeId);
                } elseif (! empty($officeId) && ! empty($departmentId)) {
                    $moodCountQuery->where('users.officeId', $officeId);
                    $moodCountQuery->where('users.departmentId', $departmentId);
                } elseif (empty($officeId) && ! empty($departmentId)) {
                    $moodCountQuery->leftJoin('departments', 'departments.id', 'users.departmentId')
                        ->where('departments.status', 'Active')
                        ->where('departments.departmentId', $departmentId);
                }
                $moodResult = $moodCountQuery->first();

                if (! empty($moodResult)) {
                    $moodCount += 200; //add 200 per day per user
                } else {
                    $query = DB::table('happy_indexes')
                        ->select('happy_indexes.created_at')
                        ->leftjoin('users', 'users.id', 'happy_indexes.user_id')
                        ->where('happy_indexes.user_id', $userId)
                        ->where('users.status', 'Active')
                        ->where('happy_indexes.status', 'Active')
                        ->whereDate('happy_indexes.created_at', '<=', $year . "-" . $singleMonth . "-" . $i);
                    if (! empty($getWeekendDates)) {
                        $query->whereNotIn(DB::raw("(DATE_FORMAT(happy_indexes.created_at,'%Y-%m-%d'))"), $getWeekendDates);
                    }
                    if (! empty($orgId)) {
                        $query->where('users.orgId', $orgId);
                    }
                    if (! empty($officeId) && empty($departmentId)) {
                        $query->where('users.officeId', $officeId);
                    } elseif (! empty($officeId) && ! empty($departmentId)) {
                        $query->where('users.officeId', $officeId);
                        $query->where('users.departmentId', $departmentId);
                    } elseif (empty($officeId) && ! empty($departmentId)) {
                        $query->leftJoin('departments', 'departments.id', 'users.departmentId')
                            ->where('departments.status', 'Active')
                            ->where('departments.departmentId', $departmentId);
                    }
                    $moodResult1 = $query->orderBy('happy_indexes.created_at', 'DESC')->first();

                    if (! empty($moodResult1)) {
                        $firstDate = $moodResult1->created_at;
                        if ($i <= 9) {
                            $i = "0" . $i;
                        }
                        $currentDate = $year . "-" . $singleMonth . "-" . $i;

                        $totalLeaveDays = 0;

                        $userLeaves = DB::table('user_leave_management')
                            ->where('user_id', $userId)
                            ->whereDate('start_date', '>=', date('Y-m-d', strtotime($firstDate)))
                            ->whereDate('start_date', '<=', date('Y-m-d', strtotime($currentDate)))
                            ->get();

                        if (count($userLeaves)) {
                            foreach ($userLeaves as $leaveVal) {
                                $leaveStart  = $leaveVal->startDate;
                                $leaveResume = $leaveVal->resumeDate;
                                $leaveStatus = $leaveVal->leaveStatus;

                                if ($leaveResume > $currentDate) {
                                    $leaveDaysArr = ['startDate' => $leaveStart, 'currentDate' => $currentDate, 'HI_include_saturday' => $HI_include_saturday, 'HI_include_sunday' => $HI_include_sunday];
                                    $leaveDays    = CommonHelper::calculateNumberOfDaysWithSatSunConcept($leaveDaysArr);
;

                                    $totalLeaveDays += $leaveDays;
                                } else {
                                    $leaveDaysArr = ['startDate' => $leaveStart, 'currentDate' => $leaveResume, 'HI_include_saturday' => $HI_include_saturday, 'HI_include_sunday' => $HI_include_sunday];
                                    $leaveDays    =CommonHelper::calculateNumberOfDaysWithSatSunConcept($leaveDaysArr);
;

                                    if ($leaveDays > 0) {
                                        $leaveDays--;
                                    }
                                    $totalLeaveDays += $leaveDays;
                                }

                            }
                        }

                        if (($firstDate < $currentDate) || $firstDate = $currentDate) {
                            $numberDaysArr = ['startDate' => $firstDate, 'currentDate' => $currentDate, 'HI_include_saturday' => $HI_include_saturday, 'HI_include_sunday' => $HI_include_sunday];
                            $days          = CommonHelper::calculateNumberOfDaysWithSatSunConcept($numberDaysArr);

                            if ($totalLeaveDays > $days) {
                                $noOfDays = 0;
                            } else {
                                $noOfDays = $days - $totalLeaveDays;
                            }
                        } else {
                            $noOfDays = 0;
                        }
                        // }

                        $moodCount += ($noOfDays * (-50)); 
                    } else {
                        $userDetail = DB::table('users')
                            ->select('created_at')
                            ->where('id', $userId)
                            ->where('status', 'Active')
                            ->where('roleId', 3)
                            ->whereDate('created_at', '<=', $year . "-" . $singleMonth . "-" . $i)
                            ->first();

                        if (! empty($userDetail)) {
                            $firstDate = $userDetail->created_at;
                            if ($i <= 9) {
                                $i = "0" . $i;
                            }
                            $currentDate = $year . "-" . $singleMonth . "-" . $i;

                            $totalLeaveDays = 0;

                            $userLeaves = DB::table('user_leave_management')
                                ->where('user_id', $userId)
                                ->whereDate('start_date', '>=', date('Y-m-d', strtotime($firstDate)))
                                ->whereDate('start_date', '<=', date('Y-m-d', strtotime($currentDate)))
                                ->get();

                            if (count($userLeaves)) {
                                foreach ($userLeaves as $leaveVal) {
                                    $leaveStart  = $leaveVal->startDate;
                                    $leaveResume = $leaveVal->resumeDate;
                                    $leaveStatus = $leaveVal->leaveStatus;

                                    // if ($leaveStatus == 1 && $leaveResume > $currentDate) {
                                    if ($leaveResume > $currentDate) {
                                        $leaveDaysArr = ['start_date' => $leaveStart, 'currentDate' => $currentDate, 'HI_include_saturday' => $HI_include_saturday, 'HI_include_sunday' => $HI_include_sunday];
                                        $leaveDays    = CommonHelper::calculateNumberOfDaysWithSatSunConcept($leaveDaysArr);
;

                                        $totalLeaveDays += $leaveDays;
                                    } else {
                                        $leaveDaysArr = ['start_date' => $leaveStart, 'currentDate' => $leaveResume, 'HI_include_saturday' => $HI_include_saturday, 'HI_include_sunday' => $HI_include_sunday];
                                        $leaveDays    = CommonHelper::calculateNumberOfDaysWithSatSunConcept($leaveDaysArr);
;

                                        if ($leaveDays > 0) {
                                            $leaveDays--;
                                        }
                                        $totalLeaveDays += $leaveDays;
                                    }

                                }
                            }

                            if (($firstDate < $currentDate)) {
                                $numberDaysArr = ['start_date' => $firstDate, 'currentDate' => $currentDate, 'HI_include_saturday' => $HI_include_saturday, 'HI_include_sunday' => $HI_include_sunday];
                               
                                $days = CommonHelper::calculateNumberOfDaysWithSatSunConcept($numberDaysArr);
                               
                                if ($totalLeaveDays > $days) {
                                    $noOfDays = 0;
                                } else {
                                    $noOfDays = $days + 1 - $totalLeaveDays; 
                                }

                            } elseif ($firstDate = $currentDate) {
                                $numberDaysArr = ['start_date' => $firstDate, 'currentDate' => $currentDate, 'HI_include_saturday' => $HI_include_saturday, 'HI_include_sunday' => $HI_include_sunday];
                                $days          = aCommonHelper::calculateNumberOfDaysWithSatSunConcept($numberDaysArr);
                             
                                if ($totalLeaveDays > $days) {
                                    $noOfDays = 0;
                                } else {
                                    $noOfDays = $days - $totalLeaveDays; 
                                }
                            } else {
                                $noOfDays = 0;
                            }

                            $moodCount += ($noOfDays * (-50)); 
                        } else {
                            $moodCount += 0;
                        }
                    }
                }
                // }
            }

            $thisMonthUserLeaves = DB::table('user_leave_management')
                ->where('user_id', $userId)
                ->where(function ($query) use ($month) {
                    $query->where('start_date', 'LIKE', $month . "%")
                        ->orWhere('resume_date', 'LIKE', $month . "%");
                })
                ->get();

            $totalLeaveDays1 = 0;
            if (count($thisMonthUserLeaves)) {
                foreach ($thisMonthUserLeaves as $userLeaveVal) {
                    $leaveStart1  = $userLeaveVal->startDate;
                    $leaveResume1 = $userLeaveVal->resumeDate;
                    $leaveStatus1 = $userLeaveVal->leaveStatus;

                    if ($leaveStart1 < date('Y-m-01', strtotime($month))) {
                        $leaveStart1 = date('Y-m-01', strtotime($month));
                    }

                    if ($leaveResume1 > date('Y-m-t', strtotime($month))) {
                        $leaveResume1 = date('Y-m-31', strtotime($month));
                    }

                    if ($leaveResume1 > $lastDate) {
                        $leaveDaysArr = ['start_date' => $leaveStart1, 'currentDate' => $lastDate, 'HI_include_saturday' => $HI_include_saturday, 'HI_include_sunday' => $HI_include_sunday];
                        $leaveDays1   = CommonHelper::calculateNumberOfDaysWithSatSunConcept($leaveDaysArr);
                        $totalLeaveDays1 += $leaveDays1;
                    } else {
                        $leaveDaysArr = ['start_date' => $leaveStart1, 'currentDate' => $leaveResume1, 'HI_include_saturday' => $HI_include_saturday, 'HI_include_sunday' => $HI_include_sunday];
                        $leaveDays1   = CommonHelper::calculateNumberOfDaysWithSatSunConcept($leaveDaysArr);


                        if ($leaveDays1 > 0) {
                            $leaveDays1--;
                        }
                        $totalLeaveDays1 += $leaveDays1;
                    }
                }
            }

            $numberDaysArr = ['start_date' => $startDate, 'currentDate' => $lastDate, 'HI_include_saturday' => $HI_include_saturday, 'HI_include_sunday' => $HI_include_sunday];
            $noOfDays1     = CommonHelper::calculateNumberOfDaysWithSatSunConcept($numberDaysArr);

            if ($totalLeaveDays1 >= $noOfDays1) {
                $noOfDays2 = 0;
            } else {
                $noOfDays2 = $noOfDays1 - $totalLeaveDays1; 
            }

            $happyIndexCount = 0;
            if (! empty($moodCount) && ! empty($noOfDays2)) {
                $happyIndexCount = ($moodCount / $noOfDays2);
            }
        } else {
            // Get user's timezone to properly check date
            $user = DB::table('users')->where('id', $userId)->first();
            $userTimezone = $user && $user->timezone ? $user->timezone : 'Europe/London';
            if (!in_array($userTimezone, timezone_identifiers_list())) {
                $userTimezone = 'Europe/London';
            }

            $moodCountQuery = DB::table('happy_indexes')
                ->select('happy_indexes.id', 'happy_indexes.created_at')
                ->leftjoin('users', 'users.id', 'happy_indexes.user_id')
                ->where('happy_indexes.user_id', $userId)
                ->whereIn('users.status', ['Active', 'active_verified', 'active_unverified'])
                ->where('happy_indexes.status', 'Active');
            
            if (! empty($date)) {
                // Convert date to UTC range for proper timezone comparison
                // Use createFromFormat to ensure correct timezone handling
                $targetDate = Carbon::createFromFormat('Y-m-d', $date, $userTimezone)->startOfDay();
                $startUTC = $targetDate->copy()->utc();
                $endUTC = $targetDate->copy()->endOfDay()->utc();
                $moodCountQuery->whereBetween('happy_indexes.created_at', [$startUTC, $endUTC]);
            } else {
                // Use today in user's timezone
                $todayInUserTz = Carbon::now($userTimezone)->startOfDay();
                $startUTC = $todayInUserTz->copy()->utc();
                $endUTC = $todayInUserTz->copy()->endOfDay()->utc();
                $moodCountQuery->whereBetween('happy_indexes.created_at', [$startUTC, $endUTC]);
            }
            
            if (! empty($orgId)) {
                $moodCountQuery->where('users.orgId', $orgId);
            }
            $moodResult = $moodCountQuery->first();

            $moodCount = 0;
            if (! empty($moodResult)) {
                $moodCount = 200; 
            } else {
                $query = DB::table('happy_indexes')
                    ->select('happy_indexes.created_at')
                    ->leftjoin('users', 'users.id', 'happy_indexes.user_id')
                    ->where('happy_indexes.user_id', $userId)
                    ->whereIn('users.status', ['Active', 'active_verified', 'active_unverified'])
                    ->where('happy_indexes.status', 'Active');
                if (! empty($date)) { //This is from engagement index cron
                    $query->whereDate('happy_indexes.created_at', '<=', $date);
                }
                if (! empty($orgId)) {
                    $query->where('users.orgId', $orgId);
                }
                $moodResult1 = $query->orderBy('happy_indexes.created_at', 'DESC')->first();

                if (! empty($moodResult1)) {
                    $firstDate = $moodResult1->created_at;
                    if (! empty($date)) { //This is from engagement index cron
                        $currentDate = $date;
                    } else {
                        $currentDate = Carbon::today();
                    }
                    // $currentDate    = Carbon::yesterday();

                    $totalLeaveDays = 0;

                    $userLeaves = DB::table('user_leave_management')
                        ->where('user_id', $userId)
                        ->whereDate('start_date', '>=', date('Y-m-d', strtotime($firstDate)))
                        ->get();

                    if (count($userLeaves)) {
                        foreach ($userLeaves as $leaveVal) {
                            $leaveStart  = $leaveVal->startDate;
                            $leaveResume = $leaveVal->resumeDate;
                            $leaveStatus = $leaveVal->leaveStatus;

                            if ($leaveStatus == 1 && $leaveResume > $currentDate) {
                                $leaveDaysArr = ['start_date' => $leaveStart, 'currentDate' => $currentDate, 'HI_include_saturday' => $HI_include_saturday, 'HI_include_sunday' => $HI_include_sunday];
                                $leaveDays    = CommonHelper::calculateNumberOfDaysWithSatSunConcept($leaveDaysArr);


                                $totalLeaveDays += $leaveDays;
                            } else {
                                $leaveDaysArr = ['start_date' => $leaveStart, 'currentDate' => $leaveResume, 'HI_include_saturday' => $HI_include_saturday, 'HI_include_sunday' => $HI_include_sunday];
                                $leaveDays    = CommonHelper::calculateNumberOfDaysWithSatSunConcept($leaveDaysArr);


                                if ($leaveDays > 0) {
                                    $leaveDays--;
                                }
                                $totalLeaveDays += $leaveDays;
                            }

                        }
                    }

                    $numberDaysArr = ['start_date' => $firstDate, 'currentDate' => $currentDate, 'HI_include_saturday' => $HI_include_saturday, 'HI_include_sunday' => $HI_include_sunday];
                    $days          = CommonHelper::calculateNumberOfDaysWithSatSunConcept($numberDaysArr);


                    if ($totalLeaveDays > $days) {
                        $noOfDays = 0;
                    } else {
                        $noOfDays = $days - $totalLeaveDays;
                    }

                    $moodCount += ($noOfDays * (-50)); 
                } else {
                    $userDetailQuery = DB::table('users')
                        ->select('created_at')
                        ->where('id', $userId)
                        ->where('status', '1');
                      
                    if (! empty($date)) { 
                        $userDetailQuery->whereDate('created_at', '<=', $date);
                    } else {
                        $userDetailQuery->whereDate('created_at', '<=', Carbon::today());
                    }
                    $userDetail = $userDetailQuery->first();

                    if (! empty($userDetail)) {
                        $firstDate = $userDetail->created_at;
                        if (! empty($date)) { 
                            $currentDate = $date;
                        } else {
                            $currentDate = Carbon::today();
                        }

                        $totalLeaveDays = 0;

                        $userLeaves = DB::table('user_leave_management')
                            ->where('user_id', $userId)
                            ->whereDate('start_date', '>=', date('Y-m-d', strtotime($firstDate)))
                            ->get();

                        if (count($userLeaves)) {
                            foreach ($userLeaves as $leaveVal) {
                                $leaveStart  = $leaveVal->start_date;
                                $leaveResume = $leaveVal->resume_date;
                                $leaveStatus = $leaveVal->leave_status;

                                if ($leaveStatus == 1 && $leaveResume > $currentDate) {
                                    $leaveDaysArr = ['start_date' => $leaveStart, 'currentDate' => $currentDate, 'HI_include_saturday' => $HI_include_saturday, 'HI_include_sunday' => $HI_include_sunday];
                                    $leaveDays    = CommonHelper::calculateNumberOfDaysWithSatSunConcept($leaveDaysArr);


                                    $totalLeaveDays += $leaveDays;
                                } else {
                                    $leaveDaysArr = ['start_date' => $leaveStart, 'currentDate' => $leaveResume, 'HI_include_saturday' => $HI_include_saturday, 'HI_include_sunday' => $HI_include_sunday];
                                    $leaveDays    = CommonHelper::calculateNumberOfDaysWithSatSunConcept($leaveDaysArr);


                                    if ($leaveDays > 0) {
                                        $leaveDays--;
                                    }
                                    $totalLeaveDays += $leaveDays;
                                }

                            }
                        }

                        $numberDaysArr = ['start_date' => $firstDate, 'currentDate' => $currentDate, 'HI_include_saturday' => $HI_include_saturday, 'HI_include_sunday' => $HI_include_sunday];
                        $days          = CommonHelper::calculateNumberOfDaysWithSatSunConcept($numberDaysArr);

                        if ($totalLeaveDays > $days) {
                            $noOfDays = 0;
                        } else {
                            $noOfDays = $days + 1 - $totalLeaveDays; 
                        }

                        $moodCount += ($noOfDays * (-50)); 
                    } else {
                        $moodCount += 0;
                    }
                }
            }
            $happyIndexCount = $moodCount;
        }
        return $happyIndexCount;
    }
}

