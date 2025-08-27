<?php
namespace App\Http\Controllers;

use App\Models\IvaUser;
use App\Services\ActivityLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WorklogDashboardController extends Controller
{
    /**
     * Get dashboard data for a specific IVA user.
     */
    public function getDashboardData(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'year'           => 'nullable|integer|min:2024',
            'week_number'    => 'nullable|integer|min:1|max:52',
            'week_count'     => 'nullable|integer|min:1|max:12',
            'month'          => 'nullable|integer|min:1|max:12',
            'month_count'    => 'nullable|integer|min:1|max:12',
            'bimonthly_date' => 'nullable|integer|min:1|max:28',
            'start_date'     => 'required|date',
            'end_date'       => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = IvaUser::with(['region', 'customizations.setting.settingType'])->findOrFail($id);

        // Get date range from request
        $originalStartDate = $request->input('start_date');
        $endDate           = $request->input('end_date');

        // Apply start date adjustment logic
        $isSetMonday    = in_array($this->getDateMode($request), ['weeks', 'weekly_summary', 'month_summary']);
        $dateAdjustment = ivaAdjustStartDate($user, $originalStartDate, $endDate, $isSetMonday);
        $startDate      = $dateAdjustment['adjusted_start_date'];

        // Validate date range (max 13 months)
        $daysDiff = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
        if ($daysDiff > 395) {
            return response()->json([
                'success' => false,
                'message' => 'Date range cannot exceed 13 months (395 days)',
            ], 422);
        }

        // Calculate dashboard metrics
        $dashboardData = [
            'user' => [
                'id'                 => $user->id,
                'name'               => $user->full_name,
                'work_status'        => $user->work_status,
                'region'             => $user->region ? $user->region->name : null,
                'timedoctor_version' => $user->timedoctor_version,
            ],
        ];

        if ($isSetMonday && $dateAdjustment['changed_start_date']) {
            $dashboardData['adjusted_start_date'] = [
                'is_adjusted'   => true,
                'original_date' => $dateAdjustment['original_start_date'],
                'adjusted_date' => $dateAdjustment['adjusted_start_date'],
                'message'       => $dateAdjustment['adjustment_message'],
            ];
        }

        $dateMode = $this->getDateMode($request);

        // Add bimonthly data if needed
        if ($dateMode === 'bimonthly') {
            $dashboardData['bimonthly_data'] = $this->calculateOptimizedBimonthlyData(
                $user,
                $request->input('year'),
                $request->input('month'),
                $request->input('bimonthly_date', 15),
                $startDate,
                $endDate
            );
        } elseif ($dateMode === 'weekly_summary') {
            // Handle weekly summary mode
            if (! $dateAdjustment['is_valid_week_range']) {
                return response()->json([
                    'success'             => false,
                    'message'             => 'Insufficient date range for weekly summary calculation. At least 1 week is required.',
                    'details'             => [
                        'days_available'   => $dateAdjustment['days_difference'],
                        'minimum_required' => 7,
                    ],
                    'adjusted_start_date' => $dashboardData['adjusted_start_date'] ?? null,
                ], 422);
            }

            $dashboardData['weekly_summary_data'] = $this->calculateWeeklySummaryDataDailySummary(
                $user,
                $startDate,
                $endDate,
                $request->input('week_number')
            );
            $totalBillableHours                                          = $dashboardData['weekly_summary_data']['summary']['total_billable_hours'] ?? null;
            $dashboardData['weekly_summary_data']['target_performances'] = calculatePerformanceMetricsDailySummaries(
                $user,
                $startDate,
                $endDate,
                $totalBillableHours
            );

        } elseif ($dateMode === 'month_summary') {
            // Handle monthly summary mode
            if (! $dateAdjustment['is_valid_week_range']) {
                return response()->json([
                    'success'             => false,
                    'message'             => 'Insufficient date range for weekly summary calculation. At least 1 week is required.',
                    'details'             => [
                        'days_available'   => $dateAdjustment['days_difference'],
                        'minimum_required' => 7,
                    ],
                    'adjusted_start_date' => $dashboardData['adjusted_start_date'] ?? null,
                ], 422);
            }
            // Handle monthly summary mode
            $dashboardData['monthly_summary_data'] = $this->calculateMonthlySummaryDataDailySummary(
                $user,
                $dateAdjustment['original_start_date'],
                $endDate,
                $request->input('month_count', 1),
                $dateAdjustment['adjusted_start_date']
            );
            $totalBillableHours                                           = $dashboardData['monthly_summary_data']['summary']['total_billable_hours'] ?? null;
            $dashboardData['monthly_summary_data']['target_performances'] = calculatePerformanceMetricsDailySummaries(
                $user,
                $startDate,
                $endDate,
                $totalBillableHours
            );
        } else {
            // Regular mode (weeks, monthly, custom)
            $nadDataResult = fetchNADDataForPeriod($user, $startDate, $endDate);

            $dashboardData['basic_metrics'] = calculateBasicMetricsFromDailySummaries($user->id, $startDate, $endDate);
            // related to calculateOptimizedPeriodMetrics function. check that later.
            $dashboardData['nad_data']   = $nadDataResult['nad_data'];
            $dashboardData['date_range'] = [
                'start'               => $startDate,
                'end'                 => $endDate,
                'original_start'      => $originalStartDate,
                'start_date_adjusted' => $dateAdjustment['changed_start_date'],
                'days_count'          => $daysDiff + 1,
                'mode'                => $dateMode,
            ];
            $dashboardData['daily_breakdown']        = calculateDailyBreakdownFromSummaries($user->id, $startDate, $endDate);
            $dashboardData['category_breakdown_cat'] = calculateCategoryBreakdownFromSummaries(

                $user->id,
                $startDate,
                $endDate
            );
            // Add performance data for weeks mode
            if ($dateMode === 'weeks') {
                if (! $dateAdjustment['is_valid_week_range']) {
                    return response()->json([
                        'success'             => false,
                        'message'             => 'Insufficient date range for weekly performance calculation. At least 1 week is required.',
                        'details'             => [
                            'days_available'   => $dateAdjustment['days_difference'],
                            'minimum_required' => 7,
                        ],
                        'adjusted_start_date' => $dashboardData['adjusted_start_date'] ?? null,
                    ], 422);
                }
                $billableTotal                        = $dashboardData['basic_metrics']['billable_hours'] ?? null;
                $dashboardData['target_performances'] = calculatePerformanceMetricsDailySummaries(
                    $user,
                    $startDate,
                    $endDate,
                    $billableTotal
                );
            }
        }

        // Log the activity
        ActivityLogService::log(
            'view_worklog_dashboard',
            'Viewed worklog dashboard for user: ' . $user->full_name,
            [
                'user_id'             => $id,
                'start_date'          => $startDate,
                'original_start_date' => $originalStartDate,
                'end_date'            => $endDate,
                'start_date_adjusted' => $dateAdjustment['changed_start_date'],
                'total_hours'         => $this->getTotalHoursForLogging($dashboardData, $dateMode),
                'date_mode'           => $dateMode,
                'days_span'           => $daysDiff + 1,
            ]
        );

        return response()->json([
            'success'   => true,
            'dashboard' => $dashboardData,
        ]);
    }

    /**
     * Calculate optimized bimonthly data
     */
    private function calculateOptimizedBimonthlyData($user, $year, $month, $splitDate, $adjustedStartDate, $adjustedEndDate)
    {
        // Use adjusted start date if available
        $useAdjustedDate = $adjustedStartDate !== null;

        if ($useAdjustedDate) {
            $startOfMonth = Carbon::parse($adjustedStartDate);
        } else {
            $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        }

        // First half: from start date to splitDate
        $firstHalfStart = $startOfMonth;
        $firstHalfEnd   = Carbon::create($year, $month, $splitDate)->endOfDay();

        // Second half: (splitDate + 1) to end date or end of month
        $secondHalfStart = Carbon::create($year, $month, $splitDate + 1)->startOfDay();
        if ($useAdjustedDate) {
            $secondHalfEnd = Carbon::parse($adjustedEndDate);
        } else {
            $secondHalfEnd = Carbon::create($year, $month)->endOfMonth();
        }

        // Get NAD data for both halves
        $firstHalfNAD  = fetchNADDataForPeriod($user, $firstHalfStart->format('Y-m-d'), $firstHalfEnd->format('Y-m-d'));
        $secondHalfNAD = fetchNADDataForPeriod($user, $secondHalfStart->format('Y-m-d'), $secondHalfEnd->format('Y-m-d'));

        return [
            'first_half'  => [
                'date_range'             => [
                    'start' => $firstHalfStart->format('Y-m-d'),
                    'end'   => $firstHalfEnd->format('Y-m-d'),
                ],
                'nad_data'               => $firstHalfNAD['nad_data'],
                'basic_metrics'          => calculateBasicMetricsFromDailySummaries($user->id, $firstHalfStart->format('Y-m-d'), $firstHalfEnd->format('Y-m-d')),
                'daily_breakdown'        => calculateDailyBreakdownFromSummaries($user->id, $firstHalfStart->format('Y-m-d'), $firstHalfEnd->format('Y-m-d')),
                // 'category_breakdown_cat' => $this->calculateOptimizedCategoryBreakdown($firstHalfWorklogData, $taskCategories),
                'category_breakdown_cat' => calculateCategoryBreakdownFromSummaries(
                    $user->id,
                    $firstHalfStart->format('Y-m-d'),
                    $firstHalfEnd->format('Y-m-d'), ),
            ],
            'second_half' => [
                'date_range'             => [
                    'start' => $secondHalfStart->format('Y-m-d'),
                    'end'   => $secondHalfEnd->format('Y-m-d'),
                ],
                'nad_data'               => $secondHalfNAD['nad_data'],
                'basic_metrics'          => calculateBasicMetricsFromDailySummaries($user->id, $secondHalfStart->format('Y-m-d'), $secondHalfEnd->format('Y-m-d')),
                'daily_breakdown'        => calculateDailyBreakdownFromSummaries($user->id, $secondHalfStart->format('Y-m-d'), $secondHalfEnd->format('Y-m-d')),
                // 'category_breakdown' => $this->calculateOptimizedCategoryBreakdown($secondHalfWorklogData, $taskCategories),
                'category_breakdown_cat' => calculateCategoryBreakdownFromSummaries(
                    $user->id,
                    $secondHalfStart->format('Y-m-d'),
                    $secondHalfStart->format('Y-m-d'), ),
            ],
        ];
    }

    /**
     * Calculate optimized weekly summary data
     */

    private function calculateWeeklySummaryDataDailySummary($user, $startDate, $endDate, $startWeekNumber)
    {
        // Generate week ranges for the requested period
        $selectedWeeks = getWeekRangeForDates($startDate, $endDate, $startWeekNumber);

        $weeklyBreakdown       = [];
        $totalBillableHours    = 0;
        $totalNonBillableHours = 0;
        $totalHours            = 0;
        $totalNadHours         = 0;
        $totalNadCount         = 0;
        $nadHourRate           = 0;

        foreach ($selectedWeeks as $weekData) {

            $weekMetrics     = calculateBasicMetricsFromDailySummaries($user->id, $weekData['start_date'], $weekData['end_date']);
            $weekBillable    = $weekMetrics['billable_hours'];
            $weekPerformance = calculatePerformanceMetricsDailySummaries($user, $weekData['start_date'], $weekData['end_date'], $weekBillable);
            $nadData         = fetchNADDataForPeriod($user, $weekData['start_date'], $weekData['end_date']);

            $weeklyBreakdown[] = [
                'week_number'        => $weekData['week_number'],
                'start_date'         => $weekData['start_date'],
                'end_date'           => $weekData['end_date'],
                'label'              => $weekData['label'],
                'billable_hours'     => $weekMetrics['billable_hours'],
                'non_billable_hours' => $weekMetrics['non_billable_hours'],
                'total_hours'        => $weekMetrics['total_hours'],
                'nad_count'          => $nadData['nad_count'],
                'nad_hours'          => $nadData['nad_hours'],
                'nad_data'           => $nadData['nad_data'],
                'performance'        => $weekPerformance,
                'entries_count'      => $weekMetrics['total_entries'],
            ];

            // Add to totals
            $totalBillableHours += $weekMetrics['billable_hours'];
            $totalNonBillableHours += $weekMetrics['non_billable_hours'];
            $totalHours += $weekMetrics['total_hours'];
            $totalNadHours += $nadData['nad_hours'];
            $totalNadCount += $nadData['nad_count'];
            $nadHourRate = $nadData['nad_hour_rate'];
        }

        // Calculate overall category breakdown
        $categoryBreakdown = calculateCategoryBreakdownFromSummaries(
            $user->id,
            $startDate,
            $endDate, );

        return [
            'summary'            => [
                'total_weeks'              => count($selectedWeeks),
                'total_billable_hours'     => round($totalBillableHours, 2),
                'total_non_billable_hours' => round($totalNonBillableHours, 2),
                'total_hours'              => round($totalHours, 2),
                'total_nad_count'          => $totalNadCount,
                'total_nad_hours'          => round($totalNadHours, 2),
                'nad_hour_rate'            => $nadHourRate,
            ],
            'weekly_breakdown'   => $weeklyBreakdown,
            'category_breakdown' => $categoryBreakdown,
            'date_range'         => [
                'start' => $selectedWeeks[0]['start_date'] ?? $startDate,
                'end'   => end($selectedWeeks)['end_date'] ?? $endDate,
                'mode'  => 'weekly_summary',
            ],
        ];
    }

    /**
     * Calculate optimized monthly summary data
     */

    private function calculateMonthlySummaryDataDailySummary($user, $startDate, $endDate, $monthCount, $adjustedStartDate)
    {
        // Generate month ranges for the requested period
        $selectedMonths        = getMonthRangeForDates($startDate, $endDate, $monthCount, $adjustedStartDate);
        $monthlyBreakdown      = [];
        $totalBillableHours    = 0;
        $totalNonBillableHours = 0;
        $totalHours            = 0;
        $totalNadHours         = 0;
        $totalNadCount         = 0;
        $nadHourRate           = 0;
        // dd($selectedMonths, $startDate, $endDate, $year, $startMonth, $monthCount);
        foreach ($selectedMonths as $monthData) {
            // Get optimized worklog data for this month
            // $monthWorklogData = $this->getOptimizedWorklogData(
            //     $user->id,
            //     $monthData['start_date'],
            //     $monthData['end_date'],
            //     $taskCategories
            // );

            // Calculate period metrics with NAD data
            // $monthMetrics = $this->calculateOptimizedPeriodMetrics($user, $monthWorklogData, $monthData['start_date'], $monthData['end_date'], true, $timezone);

            // // Calculate weekly breakdown for this month
            // $weeklyBreakdown = $this->calculateOptimizedWeeklyBreakdownForMonth($user, $monthWorklogData, $monthData['start_date'], $monthData['end_date'], $workStatusChanges, $taskCategories);

            // // Calculate performance for this month
            // $monthPerformance = calculatePerformanceMetrics($user, $monthWorklogData['all_worklogs'], $monthData['start_date'], $monthData['end_date'], $workStatusChanges);

            $monthMetrics     = calculateBasicMetricsFromDailySummaries($user->id, $monthData['start_date'], $monthData['end_date']);
            $weeklyBreakdown  = $this->calculateWeeklyBreakdownForMonthDailySummary($user, $monthData['start_date'], $monthData['end_date']);
            $weekBillable     = $monthMetrics['billable_hours'];
            $monthPerformance = calculatePerformanceMetricsDailySummaries($user, $monthData['start_date'], $monthData['end_date'], $weekBillable);
            $nadData          = fetchNADDataForPeriod($user, $monthData['start_date'], $monthData['end_date']);

            $monthlyBreakdown[] = [
                'month_number'       => $monthData['month_number'],
                'start_date'         => $monthData['start_date'],
                'end_date'           => $monthData['end_date'],
                'label'              => $monthData['label'],
                'billable_hours'     => $monthMetrics['billable_hours'],
                'non_billable_hours' => $monthMetrics['non_billable_hours'],
                'total_hours'        => $monthMetrics['total_hours'],
                'nad_count'          => $nadData['nad_count'],
                'nad_hours'          => $nadData['nad_hours'],
                'nad_data'           => $nadData['nad_data'],
                'performance'        => $monthPerformance,
                'entries_count'      => $monthMetrics['total_entries'],
                'weekly_breakdown'   => $weeklyBreakdown,
            ];

            // Add to totals
            $totalBillableHours += $monthMetrics['billable_hours'];
            $totalNonBillableHours += $monthMetrics['non_billable_hours'];
            $totalHours += $monthMetrics['total_hours'];
            $totalNadHours += $nadData['nad_hours'];
            $totalNadCount += $nadData['nad_count'];
            $nadHourRate = $nadData['nad_hour_rate'];
        }

        // Calculate overall category breakdown
        // $categoryBreakdown = $this->calculateOptimizedCategoryBreakdownSummary($worklogData, $taskCategories);
        $categoryBreakdown = calculateCategoryBreakdownFromSummaries(
            $user->id,
            $startDate,
            $endDate, );

        return [
            'summary'            => [
                'total_months'             => count($selectedMonths),
                'total_billable_hours'     => round($totalBillableHours, 2),
                'total_non_billable_hours' => round($totalNonBillableHours, 2),
                'total_hours'              => round($totalHours, 2),
                'total_nad_count'          => $totalNadCount,
                'total_nad_hours'          => round($totalNadHours, 2),
                'nad_hour_rate'            => $nadHourRate,
            ],
            'monthly_breakdown'  => $monthlyBreakdown,
            'category_breakdown' => $categoryBreakdown,
            'date_range'         => [
                'start' => $selectedMonths[0]['start_date'] ?? $startDate,
                'end'   => end($selectedMonths)['end_date'] ?? $endDate,
                'mode'  => 'month_summary',
            ],
        ];
    }

    private function calculateWeeklyBreakdownForMonthDailySummary($user, $startDate, $endDate)
    {
        $timezone = config('app.timezone', 'Asia/Singapore');

        // Get all weeks that fall within this month
        $monthStart = Carbon::parse($startDate, $timezone);
        $monthEnd   = Carbon::parse($endDate, $timezone);

        // Find the Monday of the first week and Sunday of the last week
        $firstMonday = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $lastSunday  = $monthEnd->copy()->endOfWeek(Carbon::SUNDAY);

        $weeks       = [];
        $currentWeek = $firstMonday->copy();
        $weekNumber  = 1;

        while ($currentWeek->lte($lastSunday)) {
            $weekStart = $currentWeek->copy();
            $weekEnd   = $currentWeek->copy()->endOfWeek(Carbon::SUNDAY);

            // Only include weeks that overlap with the month
            if ($weekEnd->gte($monthStart) && $weekStart->lte($monthEnd)) {
                // Adjust week boundaries to month boundaries if needed
                $adjustedStart = $weekStart->lt($monthStart) ? $monthStart : $weekStart;
                $adjustedEnd   = $weekEnd->gt($monthEnd) ? $monthEnd : $weekEnd;

                $weekMetrics = calculateBasicMetricsFromDailySummaries($user->id, $adjustedStart->format('Y-m-d'),
                    $adjustedEnd->format('Y-m-d'));
                $nadData = fetchNADDataForPeriod($user, $adjustedStart->format('Y-m-d'),
                    $adjustedEnd->format('Y-m-d'));
                $weeks[] = [
                    'week_number'        => $weekNumber,
                    'start_date'         => $adjustedStart->format('Y-m-d'),
                    'end_date'           => $adjustedEnd->format('Y-m-d'),
                    'label'              => sprintf(
                        'Week %d (%s - %s)',
                        $weekNumber,
                        $adjustedStart->format('M d'),
                        $adjustedEnd->format('M d')
                    ),
                    'billable_hours'     => $weekMetrics['billable_hours'],
                    'non_billable_hours' => $weekMetrics['non_billable_hours'],
                    'total_hours'        => $weekMetrics['total_hours'],
                    'nad_count'          => $nadData['nad_count'],
                    'nad_hours'          => $nadData['nad_hours'],
                    'nad_data'           => $nadData['nad_data'],
                    'entries_count'      => $weekMetrics['total_entries'],
                ];

                $weekNumber++;
            }

            $currentWeek->addWeek();
        }

        return $weeks;
    }

    /**
     * Get total hours for logging based on mode.
     */
    private function getTotalHoursForLogging($dashboardData, $dateMode)
    {
        if ($dateMode === 'bimonthly' && isset($dashboardData['bimonthly_data'])) {
            return $dashboardData['bimonthly_data']['first_half']['basic_metrics']['billable_hours'] +
                $dashboardData['bimonthly_data']['second_half']['basic_metrics']['billable_hours'];
        } elseif ($dateMode === 'weekly_summary' && isset($dashboardData['weekly_summary_data'])) {
            return $dashboardData['weekly_summary_data']['summary']['total_billable_hours'];
        } elseif ($dateMode === 'month_summary' && isset($dashboardData['monthly_summary_data'])) {
            return $dashboardData['monthly_summary_data']['summary']['total_billable_hours'];
        } else {
            return $dashboardData['basic_metrics']['billable_hours'] ?? 0;
        }
    }

    /**
     * Get date mode from request.
     */
    private function getDateMode(Request $request)
    {
        if ($request->has('year') && $request->has('week_number')) {
            // Check if it's weekly_summary based on a parameter or pattern
            if ($request->input('mode') === 'weekly_summary') {
                return 'weekly_summary';
            }
            return 'weeks';
        } elseif ($request->has('year') && $request->has('month')) {
            // Check if it's month_summary based on a parameter
            if ($request->input('mode') === 'month_summary') {
                return 'month_summary';
            } elseif ($request->has('bimonthly_date')) {
                return 'bimonthly';
            }
            return 'monthly';
        } else {
            return 'custom';
        }
    }

    /**
     * Get tasks by report category for worklog dashboard
     */
    public function getTasksByCategory(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|integer|exists:report_categories,id',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            // Verify the IVA user exists
            $user = IvaUser::findOrFail($id);

            $categoryId = $request->input('category_id');
            $startDate  = $request->input('start_date');
            $endDate    = $request->input('end_date');

                                       // Apply start date adjustment logic (same as dashboard)
            $isSetMonday       = true; // Default for category breakdown
            $dateAdjustment    = ivaAdjustStartDate($user, $startDate, $endDate, $isSetMonday);
            $adjustedStartDate = $dateAdjustment['adjusted_start_date'];

            // Get tasks using the helper function
            $tasks = getTasksByReportCategory($categoryId, $user->id, $adjustedStartDate, $endDate);

            return response()->json([
                'success' => true,
                'message' => 'Tasks retrieved successfully',
                'data'    => $tasks,
                'meta'    => [
                    'category_id'         => $categoryId,
                    'iva_id'              => $user->id,
                    'iva_name'            => $user->full_name,
                    'start_date'          => $adjustedStartDate,
                    'end_date'            => $endDate,
                    'original_start_date' => $startDate,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tasks: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }
}