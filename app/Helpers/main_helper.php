<?php

use Carbon\Carbon;

if (! function_exists('calculateUserTargetHours')) {
    /**
     * Calculate target total hours for a user in a given period with work status changes
     * Based on calculatePerformanceMetrics but focused on target hours calculation only
     * Reuses existing helper functions and follows same patterns
     *
     * @param object $user The IvaUser model instance
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return array Target hours calculation with detailed breakdown
     */
    function calculateUserTargetHours($user, $startDate, $endDate)
    {
        try {
            // Reuse existing helper functions from helpers.php
            $workStatusChanges = getWorkStatusChanges($user, $startDate, $endDate);
            $workStatusPeriods = calculateWorkStatusPeriods($user, $startDate, $endDate, $workStatusChanges);

            // Use existing determineSettingCombinations to handle custom settings
            $settingCombinations = determineSettingCombinations($user, $workStatusPeriods);

            $allTargetCalculations = [];

            foreach ($settingCombinations as $combination) {
                $targetTotalHours  = 0;
                $totalPeriodWeeks  = 0;
                $totalPeriodDays   = 0;
                $workStatusDisplay = [];
                $periodBreakdown   = [];

                // Sum target hours across all periods for this setting combination
                // (Same logic as calculatePerformanceMetrics lines 861-881)
                foreach ($workStatusPeriods as $periodIndex => $period) {
                    $workStatus  = $period['work_status'] ?: 'full-time';
                    $periodDays  = $period['days'];
                    $periodWeeks = $periodDays / 7;
                    $periodStart = $period['start_date'];
                    $periodEnd   = $period['end_date'];

                    // Get setting for this period based on the combination
                    // This handles custom user settings automatically
                    $settingForPeriod = getSettingForPeriod($user, $workStatus, $periodStart, $periodEnd, $combination, $periodIndex);

                    $targetHoursForPeriod = $settingForPeriod['hours'] * $periodWeeks;
                    $targetTotalHours += $targetHoursForPeriod;
                    $totalPeriodWeeks += $periodWeeks;
                    $totalPeriodDays += $periodDays;

                    // Collect work status for display
                    $statusDisplay = ucwords(str_replace('-', ' ', $workStatus));
                    if (! in_array($statusDisplay, $workStatusDisplay)) {
                        $workStatusDisplay[] = $statusDisplay;
                    }

                    // Add to breakdown
                    $periodBreakdown[] = [
                        'period_start'        => $periodStart,
                        'period_end'          => $periodEnd,
                        'work_status'         => $workStatus,
                        'work_status_display' => $statusDisplay,
                        'days'                => $periodDays,
                        'weeks'               => round($periodWeeks, 2),
                        'hours_per_week'      => $settingForPeriod['hours'],
                        'target_hours'        => round($targetHoursForPeriod, 2),
                        'setting_used'        => $settingForPeriod,
                        'week_start'          => $period['week_start'],
                        'week_end'            => $period['week_end'],
                    ];
                }

                $allTargetCalculations[] = [
                    'target_id'             => $combination['id'],
                    'work_status'           => implode(' + ', $workStatusDisplay),
                    'target_hours_per_week' => $combination['display_hours'],
                    'target_total_hours'    => round($targetTotalHours, 2),
                    'period_weeks'          => round($totalPeriodWeeks, 1),
                    'period_days'           => $totalPeriodDays,
                    'combination_details'   => $combination['details'],
                    'period_breakdown'      => $periodBreakdown,
                ];
            }

            return [
                'success'                    => true,
                'user_id'                    => $user->id,
                'date_range'                 => [
                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                ],
                'has_work_status_changes'    => $workStatusChanges->isNotEmpty(),
                'work_status_periods_count'  => count($workStatusPeriods),
                'setting_combinations_count' => count($settingCombinations),
                'setting_combinations'       => $settingCombinations,
                'target_calculations'        => $allTargetCalculations,
                'calculation_date'           => Carbon::now()->toDateTimeString(),
            ];
        } catch (\Exception $e) {
            return [
                'success'             => false,
                'error'               => $e->getMessage(),
                'user_id'             => $user->id ?? null,
                'target_calculations' => [],
            ];
        }
    }
}

if (! function_exists('getTargetHoursForCurrentWeek')) {
    /**
     * Get target hours for current week using date_time_helpers functions
     *
     * @param object $user The IvaUser model instance
     * @return array|null Current week target hours or null if not found
     */
    function getTargetHoursForCurrentWeek($user)
    {
        $currentWeek = getCurrentWeek();
        if (! $currentWeek) {
            return null;
        }

        $result = calculateUserTargetHours(
            $user,
            $currentWeek['start_date'],
            $currentWeek['end_date']
        );

        $result['week_info'] = $currentWeek;
        return $result;
    }
}

if (! function_exists('getTargetHoursForCurrentMonth')) {
    /**
     * Get target hours for current month using date_time_helpers functions
     *
     * @param object $user The IvaUser model instance
     * @return array|null Current month target hours or null if not found
     */
    function getTargetHoursForCurrentMonth($user)
    {
        $currentMonth = getCurrentMonth();
        if (! $currentMonth) {
            return null;
        }

        $result = calculateUserTargetHours(
            $user,
            $currentMonth['start_date'],
            $currentMonth['end_date']
        );

        $result['month_info'] = $currentMonth;
        return $result;
    }
}

if (! function_exists('getTargetHoursForLastWeek')) {
    /**
     * Get target hours for last week using date_time_helpers functions
     *
     * @param object $user The IvaUser model instance
     * @return array|null Last week target hours or null if not found
     */
    function getTargetHoursForLastWeek($user)
    {
        $lastWeek = getLastWeek();
        if (! $lastWeek) {
            return null;
        }

        $result = calculateUserTargetHours(
            $user,
            $lastWeek['start_date'],
            $lastWeek['end_date']
        );

        $result['week_info'] = $lastWeek;
        return $result;
    }
}

if (! function_exists('getTargetHoursForLastMonth')) {
    /**
     * Get target hours for last month using date_time_helpers functions
     *
     * @param object $user The IvaUser model instance
     * @return array|null Last month target hours or null if not found
     */
    function getTargetHoursForLastMonth($user)
    {
        $lastMonth = getLastMonth();
        if (! $lastMonth) {
            return null;
        }

        $result = calculateUserTargetHours(
            $user,
            $lastMonth['start_date'],
            $lastMonth['end_date']
        );

        $result['month_info'] = $lastMonth;
        return $result;
    }
}

if (! function_exists('getTargetHoursForWeekNumber')) {
    /**
     * Get target hours for specific week number using date_time_helpers functions
     *
     * @param object $user The IvaUser model instance
     * @param int $weekNumber Week number
     * @param int|null $year Year (defaults to current year)
     * @return array|null Week target hours or null if not found
     */
    function getTargetHoursForWeekNumber($user, $weekNumber, $year = null)
    {
        $week = getWeekByNumber($weekNumber, $year);
        if (! $week) {
            return null;
        }

        $result = calculateUserTargetHours(
            $user,
            $week['start_date'],
            $week['end_date']
        );

        $result['week_info'] = $week;
        return $result;
    }
}

if (! function_exists('getTargetHoursForMonthNumber')) {
    /**
     * Get target hours for specific month number using date_time_helpers functions
     *
     * @param object $user The IvaUser model instance
     * @param int $monthNumber Month number
     * @param int|null $year Year (defaults to current year)
     * @return array|null Month target hours or null if not found
     */
    function getTargetHoursForMonthNumber($user, $monthNumber, $year = null)
    {
        $month = getMonthByNumber($monthNumber, $year);
        if (! $month) {
            return null;
        }

        $result = calculateUserTargetHours(
            $user,
            $month['start_date'],
            $month['end_date']
        );

        $result['month_info'] = $month;
        return $result;
    }
}

if (! function_exists('getTargetHoursSummaryForUser')) {
    /**
     * Get comprehensive target hours summary for a user including current/last week/month
     *
     * @param object $user The IvaUser model instance
     * @return array Comprehensive target hours summary
     */
    function getTargetHoursSummaryForUser($user)
    {
        return [
            'user_id'       => $user->id,
            'user_name'     => $user->full_name ?? $user->email,
            'current_week'  => getTargetHoursForCurrentWeek($user),
            'last_week'     => getTargetHoursForLastWeek($user),
            'current_month' => getTargetHoursForCurrentMonth($user),
            'last_month'    => getTargetHoursForLastMonth($user),
            'generated_at'  => Carbon::now()->toDateTimeString(),
        ];
    }
}

if (! function_exists('calculateTargetHoursForMultipleUsers')) {
    /**
     * Calculate target hours for multiple users
     *
     * @param array|\Illuminate\Support\Collection $users Array of IvaUser model instances
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return array Target hours calculations for all users
     */
    function calculateTargetHoursForMultipleUsers($users, $startDate, $endDate)
    {
        $results = [];
        $summary = [
            'total_users'             => count($users),
            'successful_calculations' => 0,
            'failed_calculations'     => 0,
        ];

        foreach ($users as $user) {
            $userResult = calculateUserTargetHours($user, $startDate, $endDate);
            $results[]  = $userResult;

            if ($userResult['success']) {
                $summary['successful_calculations']++;
            } else {
                $summary['failed_calculations']++;
            }
        }

        return [
            'users'            => $results,
            'summary'          => $summary,
            'calculation_date' => Carbon::now()->toDateTimeString(),
            'date_range'       => [
                'start_date' => $startDate,
                'end_date'   => $endDate,
            ],
        ];
    }
}
