<?php

use Carbon\Carbon;

if (! function_exists('calculateUserTargetHours')) {
    /**
     * Calculate target total hours for a user in a given period with work status changes
     * Based on calculatePerformanceMetrics but focused on target hours calculation only
     * Reuses existing helper functions and follows same patterns
     *
     * @param  object  $user  The IvaUser model instance
     * @param  string  $startDate  Start date (Y-m-d format)
     * @param  string  $endDate  End date (Y-m-d format)
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

                    // Track hours per week for simple average calculation
                    $periodHoursForAverage[] = $settingForPeriod['hours'];

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

                // Calculate simple average of period-specific hours per week
                $averageHoursPerWeek = ! empty($periodHoursForAverage)
                    ? round(array_sum($periodHoursForAverage) / count($periodHoursForAverage), 1)
                    : $combination['display_hours'];

                $allTargetCalculations[] = [
                    'target_id'             => $combination['id'],
                    'work_status'           => implode(' + ', $workStatusDisplay),
                    'target_hours_per_week' => $averageHoursPerWeek,
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

if (! function_exists('calculateBasicMetricsFromDailySummaries')) {
    /**
     * Calculate basic metrics from daily_worklog_summaries table
     * Optimized for performance by pushing all operations to database level
     *
     * @param  int  $ivaId  IVA user ID
     * @param  string  $startDate  Start date (Y-m-d format)
     * @param  string  $endDate  End date (Y-m-d format)
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
                    \Illuminate\Support\Facades\DB::raw('
                        ROUND(COALESCE(SUM(total_duration), 0) / 3600, 2) as total_hours
                    '),
                    \Illuminate\Support\Facades\DB::raw('
                        COALESCE(SUM(entries_count), 0) as total_entries
                    '),

                    // Period information
                    \Illuminate\Support\Facades\DB::raw('COUNT(*) as summary_records_found'),
                    \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT report_date) as unique_dates_with_data'),
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
     * @param  int  $ivaId  IVA user ID
     * @param  string  $startDate  Start date (Y-m-d format)
     * @param  string  $endDate  End date (Y-m-d format)
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
                    \Illuminate\Support\Facades\DB::raw('
                        ROUND(COALESCE(SUM(total_duration), 0) / 3600, 2) as total_hours
                    '),
                    \Illuminate\Support\Facades\DB::raw("
                        COALESCE(SUM(CASE WHEN category_type LIKE 'billable%' THEN entries_count END), 0) as billable_entries
                    "),
                    \Illuminate\Support\Facades\DB::raw("
                        COALESCE(SUM(CASE WHEN category_type LIKE '%non-billable%' THEN entries_count END), 0) as non_billable_entries
                    "),
                    \Illuminate\Support\Facades\DB::raw("
                        COALESCE(SUM(CASE WHEN category_type = 'uncategorized' THEN entries_count END), 0) as uncategorized_entries
                    "),
                    \Illuminate\Support\Facades\DB::raw('
                        COALESCE(SUM(entries_count), 0) as total_entries
                    '),
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
     * @param  IvaUser  $user  IVA user
     * @param  string  $startDate  Start date (Y-m-d format)
     * @param  string  $endDate  End date (Y-m-d format)
     * @param  float|null  $overallBillableHours  Optional pre-calculated billable hours
     * @return array Performance metrics with target vs actual comparison
     */
    function calculatePerformanceMetricsDailySummaries($user, $startDate, $endDate, $overallBillableHours = null)
    {
        try {

            // Calculate target hours using existing helper function
            // $targetHoursResult = calculateUserTargetHours($user, $startDate, $endDate);
            $targetHoursResult = calculateUserTargetHoursOptimized($user, $startDate, $endDate);

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
     * @param  int  $ivaId  IVA user ID
     * @param  string  $startDate  Start date (Y-m-d format)
     * @param  string  $endDate  End date (Y-m-d format)
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
     * @param  int  $reportCategoryId  Report category ID
     * @param  int  $ivaId  IVA user ID
     * @param  string  $startDate  Start date (Y-m-d format)
     * @param  string  $endDate  End date (Y-m-d format)
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

if (! function_exists('calculateUserTargetHoursOptimized')) {
    /**
     * Optimized version of calculateUserTargetHours that maintains exact same logic
     * but reduces database queries and improves performance
     *
     * Key optimizations:
     * - Bulk fetch all user customizations for the date range
     * - Pre-load configuration settings
     * - Eliminate redundant helper function calls
     * - Maintain exact same calculation logic as original
     *
     * @param  object  $user  The IvaUser model instance
     * @param  string  $startDate  Start date (Y-m-d format)
     * @param  string  $endDate  End date (Y-m-d format)
     * @return array Target hours calculation with detailed breakdown
     */
    function calculateUserTargetHoursOptimized($user, $startDate, $endDate)
    {
        try {
            $userId = $user->id;

            // OPTIMIZATION 1: Bulk fetch all user customizations for the entire date range
            $userCustomizations = \Illuminate\Support\Facades\DB::table('iva_user_customize')
                ->where('iva_user_id', $userId)
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->where(function ($q) use ($startDate, $endDate) {
                        // Customization overlaps with our period
                        $q->where(function ($subQ) use ($startDate) {
                            $subQ->whereNull('start_date')
                                ->orWhere('start_date', '<=', $startDate);
                        })->where(function ($subQ) use ($endDate) {
                            $subQ->whereNull('end_date')
                                ->orWhere('end_date', '>=', $endDate);
                        });
                    })->orWhere(function ($q) use ($startDate, $endDate) {
                        // Customization intersects with period
                        $q->where('start_date', '<=', $endDate)
                            ->where('end_date', '>=', $startDate);
                    });
                })
                ->get()
                ->groupBy('setting_id');

            // OPTIMIZATION 2: Pre-fetch all configuration settings
            $configSettings = \Illuminate\Support\Facades\DB::table('configuration_settings as cs')
                ->join('configuration_settings_type as cst', 'cs.setting_type_id', '=', 'cst.id')
                ->whereIn('cst.key', ['fulltime_hours', 'parttime_hours'])
                ->where('cs.is_active', 1)
                ->select('cs.id', 'cs.setting_value', 'cst.key', 'cs.order')
                ->orderBy('cs.order')
                ->get()
                ->keyBy('id');

            // Helper function to get custom value for specific period (optimized)
            $getCustomValueForPeriod = function ($settingId, $periodStart, $periodEnd) use ($userCustomizations) {
                if (! isset($userCustomizations[$settingId])) {
                    return null;
                }

                $periodStartDate = Carbon::parse($periodStart);
                $periodEndDate   = Carbon::parse($periodEnd);

                foreach ($userCustomizations[$settingId] as $customization) {
                    $customStart = $customization->start_date ? Carbon::parse($customization->start_date) : null;
                    $customEnd   = $customization->end_date ? Carbon::parse($customization->end_date) : null;

                    // Check if customization overlaps with period
                    $startsBeforeOrDuring = ! $customStart || $customStart->lte($periodEndDate);
                    $endsAfterOrDuring    = ! $customEnd || $customEnd->gte($periodStartDate);

                    if ($startsBeforeOrDuring && $endsAfterOrDuring) {
                        return (float) $customization->custom_value;
                    }
                }

                return null;
            };

            // Helper function to get work hour settings for period (optimized)
            $getWorkHourSettings = function ($workStatus, $periodStart = null, $periodEnd = null) use ($configSettings, $getCustomValueForPeriod) {
                $workStatus = $workStatus ?: 'full-time';
                $settingKey = $workStatus === 'full-time' ? 'fulltime_hours' : 'parttime_hours';

                $hourSettings = [];
                foreach ($configSettings as $setting) {
                    if ($setting->key === $settingKey) {
                        $defaultHours = (float) $setting->setting_value;
                        $customHours  = $getCustomValueForPeriod($setting->id, $periodStart, $periodEnd);
                        $actualHours  = $customHours !== null ? $customHours : $defaultHours;

                        $hourSettings[] = [
                            'id'            => $setting->id,
                            'setting_name'  => $setting->setting_value,
                            'hours'         => $actualHours,
                            'is_custom'     => $customHours !== null,
                            'default_hours' => $defaultHours,
                            'custom_hours'  => $customHours,
                        ];
                    }
                }

                return $hourSettings;
            };

            // OPTIMIZATION 3: Bulk fetch work status changes
            $workStatusChanges = \Illuminate\Support\Facades\DB::table('iva_user_changelogs')
                ->where('iva_user_id', $userId)
                ->where('field_changed', 'work_status')
                ->orderBy('effective_date')
                ->get();

            // Now replicate the exact same logic as original function
            // but using our optimized data

            // Calculate work status periods (same logic as calculateWorkStatusPeriods)
            $periods     = [];
            $startCarbon = Carbon::parse($startDate);
            $endCarbon   = Carbon::parse($endDate);

            $currentWeekStart = $startCarbon->copy()->startOfWeek(Carbon::MONDAY);
            $finalWeekEnd     = $endCarbon->copy()->endOfWeek(Carbon::SUNDAY);

            // Get initial work status (same as getInitialWorkStatus)
            // $currentWorkStatus = 'full-time'; // Default
            $currentWorkStatus = $user->work_status;
            $earliestChange    = $workStatusChanges->where('effective_date', '<=', $currentWeekStart->toDateString())->last();
            if ($earliestChange) {
                $currentWorkStatus = json_decode($earliestChange->new_value, true) ?: 'full-time';
            }

            while ($currentWeekStart->lte($finalWeekEnd)) {
                $currentWeekEnd = $currentWeekStart->copy()->endOfWeek(Carbon::SUNDAY);

                // Find changes in this week
                $changesInWeek = $workStatusChanges->filter(function ($change) use ($currentWeekStart, $currentWeekEnd) {
                    $changeDate = Carbon::parse($change->effective_date);

                    return $changeDate->gte($currentWeekStart) && $changeDate->lte($currentWeekEnd);
                })->sortBy('effective_date');

                if ($changesInWeek->isNotEmpty()) {
                    $lastChange        = $changesInWeek->last();
                    $currentWorkStatus = json_decode($lastChange->new_value, true) ?: 'full-time';
                }

                // Calculate actual period dates
                $periodStart = $currentWeekStart->lt($startCarbon) ? $startCarbon : $currentWeekStart;
                $periodEnd   = $currentWeekEnd->gt($endCarbon) ? $endCarbon : $currentWeekEnd;

                if ($periodStart->lte($endCarbon) && $periodEnd->gte($startCarbon)) {
                    $startDateOnly = Carbon::parse($periodStart->toDateString());
                    $endDateOnly   = Carbon::parse($periodEnd->toDateString());

                    $periods[] = [
                        'work_status' => $currentWorkStatus,
                        'start_date'  => $periodStart->toDateString(),
                        'end_date'    => $periodEnd->toDateString(),
                        'days'        => $startDateOnly->diffInDays($endDateOnly) + 1,
                        'week_start'  => $currentWeekStart->toDateString(),
                        'week_end'    => $currentWeekEnd->toDateString(),
                    ];
                }

                $currentWeekStart->addWeek();
            }

            // Determine setting combinations (same logic as determineSettingCombinations)
            $uniqueStatuses = collect($periods)->pluck('work_status')->map(function ($status) {
                return $status ?: 'full-time';
            })->unique()->values()->toArray();

            $combinations = [];

            if (count($uniqueStatuses) === 1) {
                $status   = $uniqueStatuses[0];
                $settings = $getWorkHourSettings($status);

                foreach ($settings as $setting) {
                    $combinations[] = [
                        'id'            => $setting['id'],
                        'display_hours' => $setting['hours'],
                        'details'       => [
                            'type'       => 'single_status',
                            'status'     => $status,
                            'setting_id' => $setting['id'],
                        ],
                    ];
                }
            } else {
                $fullTimeSettings = $getWorkHourSettings('full-time');

                foreach ($fullTimeSettings as $setting) {
                    $combinations[] = [
                        'id'            => $setting['id'],
                        'display_hours' => $setting['hours'],
                        'details'       => [
                            'type'               => 'mixed_status',
                            'primary_setting_id' => $setting['id'],
                            'statuses'           => $uniqueStatuses,
                        ],
                    ];
                }
            }

            // Calculate target hours for each combination (same logic as original)
            $allTargetCalculations = [];

            foreach ($combinations as $combination) {
                $targetTotalHours      = 0;
                $totalPeriodWeeks      = 0;
                $totalPeriodDays       = 0;
                $workStatusDisplay     = [];
                $periodBreakdown       = [];
                $periodHoursForAverage = []; // Track hours per week for each period

                foreach ($periods as $periodIndex => $period) {
                    $workStatus  = $period['work_status'] ?: 'full-time';
                    $periodDays  = $period['days'];
                    $periodWeeks = $periodDays / 7;
                    $periodStart = $period['start_date'];
                    $periodEnd   = $period['end_date'];

                    // Get setting for this period (same logic as getSettingForPeriod)
                    $settingForPeriod = null;

                    if ($combination['details']['type'] === 'single_status') {
                        $periodHourSettings = $getWorkHourSettings($workStatus, $periodStart, $periodEnd);
                        $settingForPeriod   = collect($periodHourSettings)->firstWhere('id', $combination['details']['setting_id']) ?? $periodHourSettings[0] ?? ['hours' => 0];
                    } else {
                        $periodHourSettings = $getWorkHourSettings($workStatus, $periodStart, $periodEnd);

                        if ($workStatus === 'part-time') {
                            $settingForPeriod = $periodHourSettings[0] ?? ['hours' => 20];
                        } else {
                            $settingForPeriod = collect($periodHourSettings)->firstWhere('id', $combination['details']['primary_setting_id']) ?? $periodHourSettings[0] ?? ['hours' => 40];
                        }
                    }

                    $targetHoursForPeriod = $settingForPeriod['hours'] * $periodWeeks;
                    $targetTotalHours += $targetHoursForPeriod;
                    $totalPeriodWeeks += $periodWeeks;
                    $totalPeriodDays += $periodDays;

                    // Track hours per week for simple average calculation
                    $periodHoursForAverage[] = $settingForPeriod['hours'];

                    $statusDisplay = ucwords(str_replace('-', ' ', $workStatus));
                    if (! in_array($statusDisplay, $workStatusDisplay)) {
                        $workStatusDisplay[] = $statusDisplay;
                    }

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

                // Calculate simple average of period-specific hours per week
                $averageHoursPerWeek = ! empty($periodHoursForAverage)
                    ? round(array_sum($periodHoursForAverage) / count($periodHoursForAverage), 1)
                    : $combination['display_hours'];

                $allTargetCalculations[] = [
                    'target_id'             => $combination['id'],
                    'work_status'           => implode(' + ', $workStatusDisplay),
                    'target_hours_per_week' => $averageHoursPerWeek,
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
                'work_status_periods_count' => count($periods),
                'target_calculations'       => $allTargetCalculations,
                'calculation_date'          => Carbon::now()->toDateTimeString(),
            ];

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to calculate optimized user target hours: ' . $e->getMessage(), [
                'user_id'    => $user->id ?? null,
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            // Fallback to original function
            if (function_exists('calculateUserTargetHours')) {
                $fallbackResult                       = calculateUserTargetHours($user, $startDate, $endDate);
                $fallbackResult['used_fallback']      = true;
                $fallbackResult['optimization_error'] = $e->getMessage();

                return $fallbackResult;
            }

            return [
                'success'             => false,
                'error'               => 'Optimized calculation failed: ' . $e->getMessage(),
                'user_id'             => $user->id ?? null,
                'target_calculations' => [],
                'used_fallback'       => false,
            ];
        }
    }
}
if (! function_exists('calculateFullCategoryBreakdownFromSummaries')) {
    function calculateFullCategoryBreakdownFromSummaries($ivaId, $startDate, $endDate)
    {
        try {
            $categoryData = \Illuminate\Support\Facades\DB::table('report_categories as rc')
                ->leftJoin('daily_worklog_summaries as dws', function ($join) use ($ivaId, $startDate, $endDate) {
                    $join->on('rc.id', '=', 'dws.report_category_id')
                        ->where('dws.iva_id', '=', $ivaId)
                        ->whereBetween('dws.report_date', [$startDate, $endDate]);
                })
                ->leftJoin('configuration_settings as cs', 'rc.category_type', '=', 'cs.id') // Join config
                ->select([
                    'rc.id as category_id',
                    'rc.cat_name as category_name',
                    'rc.category_order',
                    \Illuminate\Support\Facades\DB::raw("COALESCE(cs.setting_value, 'Uncategorized') as category_type"), // ✅ FIXED HERE
                    \Illuminate\Support\Facades\DB::raw('ROUND(COALESCE(SUM(dws.total_duration), 0) / 3600, 2) as total_hours'),
                    \Illuminate\Support\Facades\DB::raw('COALESCE(SUM(dws.entries_count), 0) as entries_count'),
                ])
                ->where('rc.is_active', true)
                ->groupBy('rc.id', 'rc.cat_name', 'rc.category_order', 'cs.setting_value') // ✅ FIXED HERE
                ->orderBy('rc.category_order')
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

            $billableCategories    = [];
            $nonBillableCategories = [];
            $billableTotalHours    = 0;
            $nonBillableTotalHours = 0;

            foreach ($categoryData as $category) {
                $categoryInfo = [
                    'category_id'   => $category->category_id,
                    'category_name' => $category->category_name ?? 'Uncategorized',
                    'total_hours'   => (float) $category->total_hours,
                    'entries_count' => (int) $category->entries_count,
                ];

                $type = strtolower($category->category_type);

                if (strpos($type, 'billable') === 0) {
                    $billableCategories[] = $categoryInfo;
                    $billableTotalHours += $categoryInfo['total_hours'];
                } elseif (strpos($type, 'non-billable') !== false || $type === 'uncategorized') {
                    $nonBillableCategories[] = $categoryInfo;
                    $nonBillableTotalHours += $categoryInfo['total_hours'];
                }
            }

            $categoryBreakdown = [];

            if (! empty($billableCategories)) {
                $categoryBreakdown[] = [
                    'type'             => 'Billable',
                    'total_hours'      => round($billableTotalHours, 2),
                    'categories_count' => count($billableCategories),
                    'categories'       => $billableCategories,
                ];
            }

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

}

if (! function_exists('getReportCategories')) {
    /**
     * Get report categories filtered by type.
     *
     * @param  string  $type  'all' | 'billable' | 'non-billable'
     * @return array<int, array{id:int, name:string, order:int|null, type:string}>
     */
    function getReportCategories(string $type = 'all'): array
    {
        try {
            $type = strtolower(trim($type));
            if (! in_array($type, ['all', 'billable', 'non-billable'], true)) {
                return [
                    'success' => false,
                    'message' => "Invalid type '{$type}'. Use 'all', 'billable', or 'non-billable'.",
                    'data' => [],
                ];
            }
            $query = DB::table('report_categories as rc')
                ->join('configuration_settings as cs', 'rc.category_type', '=', 'cs.id')
                ->where('rc.is_active', true)
                ->select([
                    'rc.id',
                    'rc.cat_name as name',
                    'rc.category_order as category_order',
                    'cs.setting_value as category_type',
                ]);

            if ($type !== 'all') {
                $query->where('cs.setting_value', $type); // exact match
            }

            $rows = $query
                ->orderBy('rc.category_order')
                ->orderBy('rc.cat_name')
                ->get()
                ->map(function ($r) {
                    return [
                        'id'    => (int) $r->id,
                        'name'  => $r->name,
                        'order' => $r->category_order !== null ? (int) $r->category_order : null,
                        'type'  => $r->category_type,
                    ];
                })
                ->toArray();

            return [
                'success' => true,
                'message' => 'Categories retrieved successfully.',
                'data'    => $rows,
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to get report categories: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to get report categories.',
                'data'    => [],
            ];
        }
    }
}