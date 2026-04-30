<?php

namespace App\Helpers;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Dashboard + weekly_summaries use (year, month, week_number):
 * - Month is the selected calendar month in the UI (not always Monday's month).
 * - week_number is the 1-based index of the week's Monday in that month’s grid
 *   (first Monday-aligned block intersecting the month), not Carbon::weekOfMonth.
 */
final class WeeklySummaryCalendarHelper
{
    /**
     * Month/year for the weekly_summaries row so it appears under the correct dashboard month.
     * Uses the week’s Thursday (same as ISO “which month does this week belong to” convention).
     *
     * @return array{0: int, 1: int} [year, month]
     */
    public static function dashboardYearMonthForWeek(Carbon $weekStartMonday): array
    {
        $monday = $weekStartMonday->copy()->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
        $thursday = $monday->copy()->addDays(3);

        return [(int) $thursday->year, (int) $thursday->month];
    }

    /**
     * Sequential week index (1…) matching Livewire/API: walk Monday starts from the first
     * week slice that intersects $displayYear-$displayMonth until we hit $weekStartMonday.
     */
    public static function sequentialWeekNumberForMonth(Carbon $weekStartMonday, int $displayYear, int $displayMonth): int
    {
        $target = $weekStartMonday->copy()->startOfWeek(CarbonInterface::MONDAY)->startOfDay();

        $firstDay = Carbon::create($displayYear, $displayMonth, 1)->startOfMonth();
        $lastDay = $firstDay->copy()->endOfMonth();
        $cursor = $firstDay->copy()->startOfWeek(CarbonInterface::MONDAY)->startOfDay();

        $weekNum = 1;
        while ($cursor->lte($lastDay)) {
            if ($cursor->equalTo($target)) {
                return $weekNum;
            }
            $weekNum++;
            $cursor->addWeek();
        }

        return $weekNum;
    }
}
