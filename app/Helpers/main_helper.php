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
                'success'                   => true,
                'user_id'                   => $user->id,
                'date_range'                => [
                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                ],
                'has_work_status_changes'   => $workStatusChanges->isNotEmpty(),
                'work_status_periods_count' => count($workStatusPeriods),
                'target_calculations'       => $allTargetCalculations,
                'calculation_date'          => Carbon::now()->toDateTimeString(),
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

if (! function_exists('calculateBasicMetricsFromDailySummaries')) {
    /**
     * Calculate basic metrics from daily_worklog_summaries table
     * Optimized for performance by pushing all operations to database level
     *
     * @param int $ivaId IVA user ID
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return array Basic metrics with hours and entries count for each category
     */
    function calculateBasicMetricsFromDailySummaries($ivaId, $startDate, $endDate)
    {
        try {
            // Single database query with all aggregations and filtering
            $metrics = \Illuminate\Support\Facades\DB::table('daily_worklog_summaries')
                ->select([
                    // Billable metrics
                    \Illuminate\Support\Facades\DB::raw("
                        ROUND(COALESCE(SUM(CASE WHEN category_type LIKE 'billable%' THEN total_duration END), 0) / 3600, 2) as billable_hours
                    "),
                    \Illuminate\Support\Facades\DB::raw("
                        COALESCE(SUM(CASE WHEN category_type LIKE 'billable%' THEN entries_count END), 0) as billable_entries
                    "),
                    \Illuminate\Support\Facades\DB::raw("
                        COUNT(DISTINCT CASE WHEN category_type LIKE 'billable%' THEN report_category_id END) as billable_categories_count
                    "),

                    // Non-billable metrics
                    \Illuminate\Support\Facades\DB::raw("
                        ROUND(COALESCE(SUM(CASE WHEN category_type LIKE '%non-billable%' THEN total_duration END), 0) / 3600, 2) as non_billable_hours
                    "),
                    \Illuminate\Support\Facades\DB::raw("
                        COALESCE(SUM(CASE WHEN category_type LIKE '%non-billable%' THEN entries_count END), 0) as non_billable_entries
                    "),
                    \Illuminate\Support\Facades\DB::raw("
                        COUNT(DISTINCT CASE WHEN category_type LIKE '%non-billable%' THEN report_category_id END) as non_billable_categories_count
                    "),

                    // Uncategorized metrics
                    \Illuminate\Support\Facades\DB::raw("
                        ROUND(COALESCE(SUM(CASE WHEN category_type = 'uncategorized' THEN total_duration END), 0) / 3600, 2) as uncategorized_hours
                    "),
                    \Illuminate\Support\Facades\DB::raw("
                        COALESCE(SUM(CASE WHEN category_type = 'uncategorized' THEN entries_count END), 0) as uncategorized_entries
                    "),
                    \Illuminate\Support\Facades\DB::raw("
                        CASE WHEN SUM(CASE WHEN category_type = 'uncategorized' THEN 1 ELSE 0 END) > 0 THEN 1 ELSE 0 END as uncategorized_categories_count
                    "),

                    // Overall totals
                    \Illuminate\Support\Facades\DB::raw("
                        ROUND(COALESCE(SUM(total_duration), 0) / 3600, 2) as total_hours
                    "),
                    \Illuminate\Support\Facades\DB::raw("
                        COALESCE(SUM(entries_count), 0) as total_entries
                    "),

                    // Period information
                    \Illuminate\Support\Facades\DB::raw("COUNT(*) as summary_records_found"),
                    \Illuminate\Support\Facades\DB::raw("COUNT(DISTINCT report_date) as unique_dates_with_data"),
                ])
                ->where('iva_id', $ivaId)
                ->whereBetween('report_date', [$startDate, $endDate])
                ->first();

            // Handle case when no data found
            if (! $metrics || $metrics->summary_records_found == 0) {
                return [
                    'billable_hours'        => 0,
                    'non_billable_hours'    => 0,
                    'uncategorized_hours'   => 0,
                    'total_hours'           => 0,
                    'billable_entries'      => 0,
                    'non_billable_entries'  => 0,
                    'uncategorized_entries' => 0,
                    'total_entries'         => 0,
                ];
            }

            return [
                'billable_hours'        => (float) $metrics->billable_hours,
                'non_billable_hours'    => (float) $metrics->non_billable_hours,
                'uncategorized_hours'   => (float) $metrics->uncategorized_hours,
                'total_hours'           => (float) $metrics->total_hours,
                'billable_entries'      => (int) $metrics->billable_entries,
                'non_billable_entries'  => (int) $metrics->non_billable_entries,
                'uncategorized_entries' => (int) $metrics->uncategorized_entries,
                'total_entries'         => (int) $metrics->total_entries,
            ];

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to calculate basic metrics from daily summaries: ' . $e->getMessage(), [
                'iva_id'     => $ivaId,
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            return [
                'billable_hours'        => 0,
                'non_billable_hours'    => 0,
                'uncategorized_hours'   => 0,
                'total_hours'           => 0,
                'billable_entries'      => 0,
                'non_billable_entries'  => 0,
                'uncategorized_entries' => 0,
                'total_entries'         => 0,
            ];
        }
    }
}

if (! function_exists('calculateDailyBreakdownFromSummaries')) {
    /**
     * Calculate daily breakdown from daily_worklog_summaries table
     * Optimized for performance by pushing all operations to database level
     * Based on calculateOptimizedDailyBreakdown from WorklogDashboardController
     *
     * @param int $ivaId IVA user ID
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return array Daily breakdown with hours and entries for each day in the period
     */
    function calculateDailyBreakdownFromSummaries($ivaId, $startDate, $endDate)
    {
        try {
            // Get daily aggregated data from database in a single query
            $dailyMetrics = \Illuminate\Support\Facades\DB::table('daily_worklog_summaries')
                ->select([
                    'report_date',
                    \Illuminate\Support\Facades\DB::raw("
                        ROUND(COALESCE(SUM(CASE WHEN category_type LIKE 'billable%' THEN total_duration END), 0) / 3600, 2) as billable_hours
                    "),
                    \Illuminate\Support\Facades\DB::raw("
                        ROUND(COALESCE(SUM(CASE WHEN category_type LIKE '%non-billable%' THEN total_duration END), 0) / 3600, 2) as non_billable_hours
                    "),
                    \Illuminate\Support\Facades\DB::raw("
                        ROUND(COALESCE(SUM(CASE WHEN category_type = 'uncategorized' THEN total_duration END), 0) / 3600, 2) as uncategorized_hours
                    "),
                    \Illuminate\Support\Facades\DB::raw("
                        ROUND(COALESCE(SUM(total_duration), 0) / 3600, 2) as total_hours
                    "),
                    \Illuminate\Support\Facades\DB::raw("
                        COALESCE(SUM(CASE WHEN category_type LIKE 'billable%' THEN entries_count END), 0) as billable_entries
                    "),
                    \Illuminate\Support\Facades\DB::raw("
                        COALESCE(SUM(CASE WHEN category_type LIKE '%non-billable%' THEN entries_count END), 0) as non_billable_entries
                    "),
                    \Illuminate\Support\Facades\DB::raw("
                        COALESCE(SUM(CASE WHEN category_type = 'uncategorized' THEN entries_count END), 0) as uncategorized_entries
                    "),
                    \Illuminate\Support\Facades\DB::raw("
                        COALESCE(SUM(entries_count), 0) as total_entries
                    "),
                ])
                ->where('iva_id', $ivaId)
                ->whereBetween('report_date', [$startDate, $endDate])
                ->groupBy('report_date')
                ->orderBy('report_date')
                ->get()
                ->keyBy('report_date');

            // Generate complete date range
            $dailyData   = [];
            $currentDate = Carbon::parse($startDate);
            $endDate     = Carbon::parse($endDate);

            while ($currentDate <= $endDate) {
                $dateString = $currentDate->toDateString();

                // Get metrics for this date or use defaults
                $dayMetrics = $dailyMetrics->get($dateString);

                $dailyData[] = [
                    'date'                  => $dateString,
                    'day_name'              => $currentDate->format('l'),
                    'day_short'             => $currentDate->format('D'),
                    'is_weekend'            => $currentDate->isWeekend(),
                    'billable_hours'        => $dayMetrics ? (float) $dayMetrics->billable_hours : 0,
                    'non_billable_hours'    => $dayMetrics ? (float) $dayMetrics->non_billable_hours : 0,
                    'uncategorized_hours'   => $dayMetrics ? (float) $dayMetrics->uncategorized_hours : 0,
                    'total_hours'           => $dayMetrics ? (float) $dayMetrics->total_hours : 0,
                    'billable_entries'      => $dayMetrics ? (int) $dayMetrics->billable_entries : 0,
                    'non_billable_entries'  => $dayMetrics ? (int) $dayMetrics->non_billable_entries : 0,
                    'uncategorized_entries' => $dayMetrics ? (int) $dayMetrics->uncategorized_entries : 0,
                    'total_entries'         => $dayMetrics ? (int) $dayMetrics->total_entries : 0,
                ];

                $currentDate->addDay();
            }

            return $dailyData;

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to calculate daily breakdown from summaries: ' . $e->getMessage(), [
                'iva_id'     => $ivaId,
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            return [
                'success'       => false,
                'message'       => 'Failed to calculate daily breakdown: ' . $e->getMessage(),
                'data'          => [],
                'error_details' => [
                    'error_message' => $e->getMessage(),
                    'error_file'    => $e->getFile(),
                    'error_line'    => $e->getLine(),
                ],
            ];
        }
    }
}

if (! function_exists('calculatePerformanceMetricsDailySummaries')) {
    /**
     * Calculate performance metrics from daily_worklog_summaries table
     * Optimized for performance by pushing aggregations to database level
     * Based on calculatePerformanceMetrics from helpers.php
     *
     * @param IvaUser $user IVA user
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @param float|null $overallBillableHours Optional pre-calculated billable hours
     * @return array Performance metrics with target vs actual comparison
     */
    function calculatePerformanceMetricsDailySummaries($user, $startDate, $endDate, $overallBillableHours = null)
    {
        try {

            // Calculate target hours using existing helper function
            $targetHoursResult = calculateUserTargetHours($user, $startDate, $endDate);

            if (! $targetHoursResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to calculate target hours: ' . $targetHoursResult['message'],
                    'data'    => [],
                ];
            }

            // Get billable hours from daily summaries if not provided
            if ($overallBillableHours === null) {
                $billableHours = \Illuminate\Support\Facades\DB::table('daily_worklog_summaries')
                    ->where('iva_id', $user->id)
                    ->whereBetween('report_date', [$startDate, $endDate])
                    ->where('category_type', 'LIKE', 'billable%')
                    ->sum('total_duration');

                $overallBillableHours = round($billableHours / 3600, 2);
            }

            // Get performance thresholds from constants
            $thresholds = config('constants.performance_percentage_thresholds', [
                'EXCEEDED' => 101,
                'MEET'     => 99,
            ]);

            $statusLabels = config('constants.performance_status', [
                'BELOW'    => 'BELOW',
                'MEET'     => 'MEET',
                'EXCEEDED' => 'EXCEEDED',
            ]);

            $performances = [];

            // Process each target calculation combination
            foreach ($targetHoursResult['target_calculations'] as $combination) {
                $targetTotalHours = $combination['target_total_hours'];
                $percentage       = $targetTotalHours > 0 ? ($overallBillableHours / $targetTotalHours) * 100 : 0;

                // Determine status based on thresholds
                $status = $statusLabels['BELOW'];
                if ($percentage >= $thresholds['EXCEEDED']) {
                    $status = $statusLabels['EXCEEDED'];
                } elseif ($percentage >= $thresholds['MEET']) {
                    $status = $statusLabels['MEET'];
                }

                $performances[] = [
                    'target_id'             => $combination['target_id'],
                    'work_status'           => $combination['work_status'],
                    'target_hours_per_week' => $combination['target_hours_per_week'],
                    'target_total_hours'    => round($targetTotalHours, 2),
                    'actual_hours'          => round($overallBillableHours, 2),
                    'percentage'            => round($percentage, 1),
                    'status'                => $status,
                    'actual_vs_target'      => round($overallBillableHours - $targetTotalHours, 2),
                    'period_weeks'          => round($combination['period_weeks'], 1),
                    'period_days'           => $combination['period_days'],
                    'combination_details'   => $combination['combination_details'],
                ];
            }

            return $performances;

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to calculate performance metrics from daily summaries: ' . $e->getMessage(), [
                'iva_id'                 => $user->id ?? null,
                'start_date'             => $startDate,
                'end_date'               => $endDate,
                'overall_billable_hours' => $overallBillableHours,
                'error'                  => $e->getMessage(),
                'trace'                  => $e->getTraceAsString(),
            ]);

            return [
                'success'       => false,
                'message'       => 'Failed to calculate performance metrics: ' . $e->getMessage(),
                'data'          => [],
                'error_details' => [
                    'error_message' => $e->getMessage(),
                    'error_file'    => $e->getFile(),
                    'error_line'    => $e->getLine(),
                ],
            ];
        }
    }
}

if (! function_exists('calculateCategoryBreakdownFromSummaries')) {
    /**
     * Calculate category breakdown from daily_worklog_summaries table
     * Optimized for performance by pushing aggregations to database level
     * Based on calculateOptimizedCategoryBreakdown from WorklogDashboardController
     *
     * @param int $ivaId IVA user ID
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return array Category breakdown with billable and non-billable categories
     */
    function calculateCategoryBreakdownFromSummaries($ivaId, $startDate, $endDate)
    {
        try {
            // Get category breakdown data from database with category names
            $categoryData = \Illuminate\Support\Facades\DB::table('daily_worklog_summaries as dws')
                ->leftJoin('report_categories as rc', 'dws.report_category_id', '=', 'rc.id')
                ->select([
                    'dws.category_type',
                    'dws.report_category_id',
                    'rc.cat_name as category_name',
                    \Illuminate\Support\Facades\DB::raw('ROUND(SUM(dws.total_duration) / 3600, 2) as total_hours'),
                    \Illuminate\Support\Facades\DB::raw('SUM(dws.entries_count) as entries_count'),
                ])
                ->where('dws.iva_id', $ivaId)
                ->whereBetween('dws.report_date', [$startDate, $endDate])
                ->groupBy('dws.category_type', 'dws.report_category_id', 'rc.cat_name')
                ->orderByDesc('total_hours')
                ->get();

            if ($categoryData->isEmpty()) {
                return [
                    'success' => true,
                    'message' => 'No category data found for the specified period',
                    'data'    => [],
                    'summary' => [
                        'iva_id'                  => $ivaId,
                        'start_date'              => $startDate,
                        'end_date'                => $endDate,
                        'total_categories'        => 0,
                        'billable_categories'     => 0,
                        'non_billable_categories' => 0,
                    ],
                ];
            }

            // Group by category type and process
            $billableCategories    = [];
            $nonBillableCategories = [];
            $billableTotalHours    = 0;
            $nonBillableTotalHours = 0;

            foreach ($categoryData as $category) {
                $categoryInfo = [
                    'category_id'   => $category->report_category_id,
                    'category_name' => $category->category_name ?? 'Uncategorized',
                    'total_hours'   => (float) $category->total_hours,
                    'entries_count' => (int) $category->entries_count,
                ];

                // Categorize based on category_type
                if (stripos($category->category_type, 'billable') === 0) {
                    $billableCategories[] = $categoryInfo;
                    $billableTotalHours += $categoryInfo['total_hours'];
                } elseif (stripos($category->category_type, 'non-billable') !== false) {
                    $nonBillableCategories[] = $categoryInfo;
                    $nonBillableTotalHours += $categoryInfo['total_hours'];
                } elseif ($category->category_type === 'uncategorized') {
                    $nonBillableCategories[] = $categoryInfo;
                    $nonBillableTotalHours += $categoryInfo['total_hours'];
                }
            }

            // Build final breakdown structure
            $categoryBreakdown = [];

            // Add billable breakdown if exists
            if (! empty($billableCategories)) {
                $categoryBreakdown[] = [
                    'type'             => 'Billable',
                    'total_hours'      => round($billableTotalHours, 2),
                    'categories_count' => count($billableCategories),
                    'categories'       => $billableCategories,
                ];
            }

            // Add non-billable breakdown if exists
            if (! empty($nonBillableCategories)) {
                $categoryBreakdown[] = [
                    'type'             => 'Non-Billable',
                    'total_hours'      => round($nonBillableTotalHours, 2),
                    'categories_count' => count($nonBillableCategories),
                    'categories'       => $nonBillableCategories,
                ];
            }

            return $categoryBreakdown;

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to calculate category breakdown from summaries: ' . $e->getMessage(), [
                'iva_id'     => $ivaId,
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            return [
                'success'       => false,
                'message'       => 'Failed to calculate category breakdown: ' . $e->getMessage(),
                'data'          => [],
                'summary'       => [
                    'iva_id'                  => $ivaId,
                    'start_date'              => $startDate,
                    'end_date'                => $endDate,
                    'total_categories'        => 0,
                    'billable_categories'     => 0,
                    'non_billable_categories' => 0,
                ],
                'error_details' => [
                    'error_message' => $e->getMessage(),
                    'error_file'    => $e->getFile(),
                    'error_line'    => $e->getLine(),
                ],
            ];
        }
    }

    /**
     * Get tasks by report category with worklog entries
     *
     * @param int $reportCategoryId Report category ID
     * @param int $ivaId IVA user ID
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return array
     */
    if (! function_exists('getTasksByReportCategory')) {
        function getTasksByReportCategory($reportCategoryId, $ivaId, $startDate, $endDate)
        {
            try {
                // Get tasks under the specified report category with worklog entries
                $tasksQuery = \Illuminate\Support\Facades\DB::table('task_report_categories as trc')
                    ->join('tasks as t', 't.id', '=', 'trc.task_id')
                    ->join('worklogs_data as wd', function ($join) use ($ivaId, $startDate, $endDate) {
                        $join->on('wd.task_id', '=', 't.id')
                            ->where('wd.iva_id', $ivaId)
                            ->where('wd.is_active', true)
                            ->whereDate('wd.start_time', '>=', $startDate)
                            ->whereDate('wd.start_time', '<=', $endDate);
                    })
                    ->where('trc.cat_id', $reportCategoryId)
                    ->select([
                        't.id as task_id',
                        't.task_name',
                        \Illuminate\Support\Facades\DB::raw('ROUND(SUM(wd.duration) / 3600, 2) as total_hours'),
                        \Illuminate\Support\Facades\DB::raw('COUNT(wd.id) as entries_count'),
                    ])
                    ->groupBy('t.id', 't.task_name')
                    ->orderBy('total_hours', 'desc');

                $tasks = $tasksQuery->get();

                if ($tasks->isEmpty()) {
                    return [];
                }

                // Get detailed worklog entries for each task
                $tasksWithEntries = [];
                $totalHours       = 0;
                $totalEntries     = 0;

                foreach ($tasks as $task) {
                    // Get worklog entries for this specific task
                    $entries = \Illuminate\Support\Facades\DB::table('worklogs_data')
                        ->where('task_id', $task->task_id)
                        ->where('iva_id', $ivaId)
                        ->where('is_active', true)
                        ->whereDate('start_time', '>=', $startDate)
                        ->whereDate('start_time', '<=', $endDate)
                        ->select([
                            'id',
                            'start_time',
                            'end_time',
                            \Illuminate\Support\Facades\DB::raw('ROUND(duration / 3600, 2) as duration_hours'),
                        ])
                        ->orderBy('start_time', 'desc')
                        ->get()
                        ->toArray();

                    $tasksWithEntries[] = [
                        'task_id'       => (int) $task->task_id,
                        'task_name'     => $task->task_name,
                        'total_hours'   => (float) $task->total_hours,
                        'entries_count' => (int) $task->entries_count,
                        'entries'       => $entries,
                    ];

                    $totalHours += (float) $task->total_hours;
                    $totalEntries += (int) $task->entries_count;
                }
                return $tasksWithEntries;
                // return [
                //     'success' => true,
                //     'message' => 'Tasks retrieved successfully',
                //     'data'    => $tasksWithEntries,
                //     'summary' => [
                //         'report_category_id' => $reportCategoryId,
                //         'iva_id'             => $ivaId,
                //         'start_date'         => $startDate,
                //         'end_date'           => $endDate,
                //         'total_tasks'        => count($tasksWithEntries),
                //         'total_hours'        => round($totalHours, 2),
                //         'total_entries'      => $totalEntries,
                //     ],
                // ];

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to get tasks by report category: ' . $e->getMessage(), [
                    'report_category_id' => $reportCategoryId,
                    'iva_id'             => $ivaId,
                    'start_date'         => $startDate,
                    'end_date'           => $endDate,
                    'error'              => $e->getMessage(),
                    'trace'              => $e->getTraceAsString(),
                ]);

                return [
                    'success'       => false,
                    'message'       => 'Failed to get tasks by report category: ' . $e->getMessage(),
                    'data'          => [],
                    'summary'       => [
                        'report_category_id' => $reportCategoryId,
                        'iva_id'             => $ivaId,
                        'start_date'         => $startDate,
                        'end_date'           => $endDate,
                        'total_tasks'        => 0,
                        'total_hours'        => 0,
                        'total_entries'      => 0,
                    ],
                    'error_details' => [
                        'error_message' => $e->getMessage(),
                        'error_file'    => $e->getFile(),
                        'error_line'    => $e->getLine(),
                    ],
                ];
            }
        }
    }
}
