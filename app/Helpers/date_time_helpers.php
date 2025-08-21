<?php

use Carbon\Carbon;

if (! function_exists('getWeekListForYear')) {
    /**
     * Generate week list for a given year based on constants
     * Week 1 of 2024 starts on January 15, 2024 (Monday)
     * Each week runs Monday to Sunday
     * 
     * @param int $year - Year to generate weeks for
     * @return array Array of week objects with start_date, end_date, week_number, year, label
     */
    function getWeekListForYear($year)
    {
        $startYear = config('constants.start_year', 2024);
        $week1Start = config('constants.week_start', '2024-01-15');
        $weeksPerYear = config('constants.week_per_year', 52);
        $timezone = config('app.timezone', 'Asia/Singapore');

        if ($year < $startYear) {
            throw new \Exception("Year must be >= {$startYear}");
        }

        $differentYear = $year - $startYear + 1;
        $totalWeeks = $weeksPerYear * $differentYear;
        
        $allWeeks = [];
        $baseStart = Carbon::parse($week1Start, $timezone)->startOfDay();

        $currentYear = $startYear;
        for ($i = 0; $i < $totalWeeks; $i++) {
            $startDate = $baseStart->copy()->addDays($i * 7);
            $endDate = $startDate->copy()->addDays(6);
            
            $currentWeekNumber = ($i % $weeksPerYear) + 1;

            $allWeeks[] = [
                'week_number' => $currentWeekNumber,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'year' => $currentYear,
                'label' => sprintf(
                    'Week %d (%s - %s)',
                    $currentWeekNumber,
                    $startDate->format('M j'),
                    $endDate->format('M j')
                ),
            ];
            
            if ($currentWeekNumber === $weeksPerYear) {
                $currentYear++;
            }
        }

        // Filter only weeks that match the target year
        return array_filter($allWeeks, function($week) use ($year) {
            return $week['year'] === $year;
        });
    }
}

if (! function_exists('getMonthListForYear')) {
    /**
     * Generates grouped 4-week month-like periods for a given year
     * Each group has a title like "Month 1 (Jan 15 - Feb 11)"
     * 
     * @param int $year - Year to generate months for
     * @return array Array of month objects with title, value, subtitle, weeks, start_date, end_date
     */
    function getMonthListForYear($year)
    {
        $weeks = getWeekListForYear($year);
        $monthGroups = [];

        for ($i = 0, $count = 1; $i < count($weeks); $i += 4, $count++) {
            $monthWeeks = array_slice($weeks, $i, 4);

            if (count($monthWeeks) === 4) {
                $firstWeek = $monthWeeks[0];
                $lastWeek = $monthWeeks[3];

                $startDate = Carbon::parse($firstWeek['start_date']);
                $endDate = Carbon::parse($lastWeek['end_date']);

                $startStr = $startDate->format('M j');
                $endStr = $endDate->format('M j');

                $monthGroups[] = [
                    'title' => "Month {$count} ({$startStr} - {$endStr})",
                    'value' => $count,
                    'subtitle' => "Weeks {$firstWeek['week_number']}-{$lastWeek['week_number']}",
                    'weeks' => $monthWeeks,
                    'start_date' => $firstWeek['start_date'],
                    'end_date' => $lastWeek['end_date'],
                ];
            }
        }

        return $monthGroups;
    }
}

if (! function_exists('getCurrentWeek')) {
    /**
     * Get current week information based on today's date
     * 
     * @return array|null Current week data or null if not found
     */
    function getCurrentWeek()
    {
        $timezone = config('app.timezone', 'Asia/Singapore');
        $now = Carbon::now($timezone);
        $year = $now->year;

        try {
            $weekRanges = getWeekListForYear($year);

            foreach ($weekRanges as $weekRange) {
                $start = Carbon::parse($weekRange['start_date'], $timezone);
                $end = Carbon::parse($weekRange['end_date'], $timezone)->endOfDay();

                if ($now->between($start, $end)) {
                    return $weekRange;
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Error getting current week: ' . $e->getMessage());
        }

        return null;
    }
}

if (! function_exists('getCurrentMonth')) {
    /**
     * Get current month information based on today's date
     * 
     * @return array|null Current month data or null if not found
     */
    function getCurrentMonth()
    {
        $timezone = config('app.timezone', 'Asia/Singapore');
        $now = Carbon::now($timezone);
        $year = $now->year;

        try {
            $monthRanges = getMonthListForYear($year);

            foreach ($monthRanges as $monthRange) {
                $start = Carbon::parse($monthRange['start_date'], $timezone);
                $end = Carbon::parse($monthRange['end_date'], $timezone)->endOfDay();

                if ($now->between($start, $end)) {
                    return $monthRange;
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Error getting current month: ' . $e->getMessage());
        }

        return null;
    }
}

if (! function_exists('getLastWeek')) {
    /**
     * Get previous week information based on current week
     * 
     * @return array|null Last week data or null if not found
     */
    function getLastWeek()
    {
        $currentWeek = getCurrentWeek();
        if (!$currentWeek) {
            return null;
        }

        $year = $currentWeek['year'];
        $weekNumber = $currentWeek['week_number'];

        // If it's week 1, get week 52 of previous year
        if ($weekNumber === 1) {
            $year--;
            $weekNumber = config('constants.week_per_year', 52);
        } else {
            $weekNumber--;
        }

        try {
            $weekRanges = getWeekListForYear($year);
            
            foreach ($weekRanges as $weekRange) {
                if ($weekRange['week_number'] === $weekNumber) {
                    return $weekRange;
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Error getting last week: ' . $e->getMessage());
        }

        return null;
    }
}

if (! function_exists('getLastMonth')) {
    /**
     * Get previous month information based on current month
     * 
     * @return array|null Last month data or null if not found
     */
    function getLastMonth()
    {
        $currentMonth = getCurrentMonth();
        if (!$currentMonth) {
            return null;
        }

        $timezone = config('app.timezone', 'Asia/Singapore');
        $currentStart = Carbon::parse($currentMonth['start_date'], $timezone);
        
        // Get the Monday that's 4 weeks before current month start
        $lastMonthStart = $currentStart->copy()->subWeeks(4);
        $year = $lastMonthStart->year;

        try {
            $monthRanges = getMonthListForYear($year);

            foreach ($monthRanges as $monthRange) {
                $start = Carbon::parse($monthRange['start_date'], $timezone);
                $end = Carbon::parse($monthRange['end_date'], $timezone)->endOfDay();

                if ($lastMonthStart->between($start, $end)) {
                    return $monthRange;
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Error getting last month: ' . $e->getMessage());
        }

        return null;
    }
}

if (! function_exists('getCurrentWeekNumber')) {
    /**
     * Get current week number based on today's date
     * Falls back to 1 if no match is found
     * 
     * @return int Current week number
     */
    function getCurrentWeekNumber()
    {
        $currentWeek = getCurrentWeek();
        return $currentWeek ? $currentWeek['week_number'] : 1;
    }
}

if (! function_exists('getCurrentMonthNumber')) {
    /**
     * Get current month number based on today's date
     * Falls back to 1 if no match is found
     * 
     * @return int Current month number
     */
    function getCurrentMonthNumber()
    {
        $currentMonth = getCurrentMonth();
        return $currentMonth ? $currentMonth['value'] : 1;
    }
}

if (! function_exists('getWeekByNumber')) {
    /**
     * Get week information by week number and year
     * 
     * @param int $weekNumber - Week number to find
     * @param int|null $year - Year (defaults to current year)
     * @return array|null Week data or null if not found
     */
    function getWeekByNumber($weekNumber, $year = null)
    {
        $timezone = config('app.timezone', 'Asia/Singapore');
        $year = $year ?: Carbon::now($timezone)->year;

        try {
            $weekRanges = getWeekListForYear($year);
            
            foreach ($weekRanges as $weekRange) {
                if ($weekRange['week_number'] === $weekNumber) {
                    return $weekRange;
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Error getting week by number: ' . $e->getMessage());
        }

        return null;
    }
}

if (! function_exists('getMonthByNumber')) {
    /**
     * Get month information by month number and year
     * 
     * @param int $monthNumber - Month number to find
     * @param int|null $year - Year (defaults to current year)
     * @return array|null Month data or null if not found
     */
    function getMonthByNumber($monthNumber, $year = null)
    {
        $timezone = config('app.timezone', 'Asia/Singapore');
        $year = $year ?: Carbon::now($timezone)->year;

        try {
            $monthRanges = getMonthListForYear($year);
            
            foreach ($monthRanges as $monthRange) {
                if ($monthRange['value'] === $monthNumber) {
                    return $monthRange;
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Error getting month by number: ' . $e->getMessage());
        }

        return null;
    }
}

if (! function_exists('getDateRangeWeeks')) {
    /**
     * Get all weeks that fall within a date range
     * 
     * @param string $startDate - Start date (Y-m-d format)
     * @param string $endDate - End date (Y-m-d format)
     * @param int|null $year - Year (defaults to start date year)
     * @return array Array of weeks that intersect with the date range
     */
    function getDateRangeWeeks($startDate, $endDate, $year = null)
    {
        $timezone = config('app.timezone', 'Asia/Singapore');
        $start = Carbon::parse($startDate, $timezone);
        $end = Carbon::parse($endDate, $timezone);
        $year = $year ?: $start->year;

        try {
            $weekRanges = getWeekListForYear($year);
            $intersectingWeeks = [];

            foreach ($weekRanges as $weekRange) {
                $weekStart = Carbon::parse($weekRange['start_date'], $timezone);
                $weekEnd = Carbon::parse($weekRange['end_date'], $timezone);

                // Check if week intersects with the date range
                if ($weekStart->lte($end) && $weekEnd->gte($start)) {
                    $intersectingWeeks[] = $weekRange;
                }
            }

            return $intersectingWeeks;
        } catch (\Exception $e) {
            \Log::warning('Error getting date range weeks: ' . $e->getMessage());
        }

        return [];
    }
}

if (! function_exists('getDateRangeMonths')) {
    /**
     * Get all months that fall within a date range
     * 
     * @param string $startDate - Start date (Y-m-d format)
     * @param string $endDate - End date (Y-m-d format)
     * @param int|null $year - Year (defaults to start date year)
     * @return array Array of months that intersect with the date range
     */
    function getDateRangeMonths($startDate, $endDate, $year = null)
    {
        $timezone = config('app.timezone', 'Asia/Singapore');
        $start = Carbon::parse($startDate, $timezone);
        $end = Carbon::parse($endDate, $timezone);
        $year = $year ?: $start->year;

        try {
            $monthRanges = getMonthListForYear($year);
            $intersectingMonths = [];

            foreach ($monthRanges as $monthRange) {
                $monthStart = Carbon::parse($monthRange['start_date'], $timezone);
                $monthEnd = Carbon::parse($monthRange['end_date'], $timezone);

                // Check if month intersects with the date range
                if ($monthStart->lte($end) && $monthEnd->gte($start)) {
                    $intersectingMonths[] = $monthRange;
                }
            }

            return $intersectingMonths;
        } catch (\Exception $e) {
            \Log::warning('Error getting date range months: ' . $e->getMessage());
        }

        return [];
    }
}