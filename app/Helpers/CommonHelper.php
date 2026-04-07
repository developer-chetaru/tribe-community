<?php

namespace App\Helpers;

use DateInterval;
use DatePeriod;
use DateTime;

class CommonHelper
{
    // Exclude Sunday, return ALL DAYS except Sunday
    public static function getDatesFromRangeExcludeSunday($start, $end, $format = 'Y-m-d')
    {
        $array = [];
        $interval = new DateInterval('P1D');
        $realEnd = new DateTime($end);
        $realEnd->add($interval);

        $period = new DatePeriod(new DateTime($start), $interval, $realEnd);

        foreach ($period as $date) {
            $getDate = $date->format($format);
            $nameOfDay = date('D', strtotime($getDate));
            if ($nameOfDay != 'Sun') { // Not Sunday
                $array[] = $getDate;
            }
        }
        return $array;
    }

    // Return only weekend dates (Saturday + Sunday)
    public static function getDatesFromRange($start, $end, $format = 'Y-m-d')
    {
        $array = [];
        $interval = new DateInterval('P1D');
        $realEnd = new DateTime($end);
        $realEnd->add($interval);

        $period = new DatePeriod(new DateTime($start), $interval, $realEnd);

        foreach ($period as $date) {
            $getDate = $date->format($format);
            $nameOfDay = date('D', strtotime($getDate));
            if ($nameOfDay == 'Sat' || $nameOfDay == 'Sun') { // Only Sat or Sun
                $array[] = $getDate;
            }
        }
        return $array;
    }

    // Calculate number of days excluding Sat/Sun based on flags
    public static function calculateNumberOfDaysWithSatSunConcept($perArr = [])
    {
        $startDate           = $perArr['start_date'];
        $currentDate         = $perArr['currentDate'];
        $HI_include_saturday = $perArr['HI_include_saturday'];
        $HI_include_sunday   = $perArr['HI_include_sunday'];

        $start = new DateTime($startDate);
        $end = new DateTime($currentDate);
        $end->modify('+1 day');

        $interval = $end->diff($start);
        $days = $interval->days;

        $period = new DatePeriod($start, new DateInterval('P1D'), $end);

        if ($HI_include_saturday == 1 && $HI_include_sunday == 2) {
            foreach ($period as $dt) {
                if ($dt->format('D') == 'Sun') $days--;
            }
        } elseif ($HI_include_saturday == 2 && $HI_include_sunday == 1) {
            foreach ($period as $dt) {
                if ($dt->format('D') == 'Sat') $days--;
            }
        } elseif ($HI_include_saturday == 1 && $HI_include_sunday == 1) {
            // No deduction (include both Sat & Sun)
        } elseif ($HI_include_saturday == 2 && $HI_include_sunday == 2) {
            foreach ($period as $dt) {
                if ($dt->format('D') == 'Sat' || $dt->format('D') == 'Sun') $days--;
            }
        }
        return $days;
    }
}
