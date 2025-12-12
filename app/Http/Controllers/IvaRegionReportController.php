<?php
namespace App\Http\Controllers;

use App\Models\IvaUser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class IvaRegionReportController extends Controller
{
    /**
     * Get region performance report for specified date range
     */
    public function getRegionPerformanceReport(Request $request)
    {
        // Validate region access for users with view_team_data permission
        $regionValidation = validateManagerRegionAccess($request->user());
        if ($regionValidation) {
            return response()->json([
                'success' => false,
                'error' => $regionValidation['error'],
                'message' => $regionValidation['message'],
                'region_access_error' => true
            ], 403);
        }

        // Check if current user should be filtered by region
        $managerRegionFilter = getManagerRegionFilter($request->user());

        // If manager has view_team_data only, override region_id with their assigned region
        if ($managerRegionFilter) {
            $request->merge(['region_id' => $managerRegionFilter]);
        }

        $request->merge([
            'show_details' => filter_var($request->query('show_details'), FILTER_VALIDATE_BOOLEAN),
        ]);

        $validator = Validator::make($request->all(), [
            'region_id'    => 'required|exists:regions,id',
            'year'         => 'required|integer|min:2024',
            'start_date'   => 'required|date|date_format:Y-m-d',
            'end_date'     => 'required|date|date_format:Y-m-d|after_or_equal:start_date',
            'mode'         => 'required|in:weekly,monthly,yearly',
            'show_details' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Validate date range (must be Monday to Sunday)
        $startDate = Carbon::parse($request->input('start_date'));
        $endDate   = Carbon::parse($request->input('end_date'));

        if (! $startDate->isMonday()) {
            return response()->json([
                'success' => false,
                'message' => 'Start date must be a Monday',
            ], 422);
        }

        if (! $endDate->isSunday()) {
            return response()->json([
                'success' => false,
                'message' => 'End date must be a Sunday',
            ], 422);
        }

        // Validate date range based on mode
        $daysDiff = $startDate->diffInDays($endDate);

        switch ($request->input('mode')) {
            case 'weekly':
                if ($daysDiff < 6) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Weekly mode requires at least 7 days',
                    ], 422);
                }
                break;
            case 'monthly':
                if ($daysDiff < 27) { // At least 4 weeks
                    return response()->json([
                        'success' => false,
                        'message' => 'Monthly mode requires at least 4 weeks',
                    ], 422);
                }
                break;
            case 'yearly':
                if ($daysDiff < 363) { // At least 52 weeks
                    return response()->json([
                        'success' => false,
                        'message' => 'Yearly mode requires at least 52 weeks',
                    ], 422);
                }
                break;
        }

        // Generate fresh data
        $reportData = $this->generateRegionReportData($request);

        Log::info('Region report generated', [
            'region_id'   => $request->input('region_id'),
            'users_count' => count($reportData['users_data'] ?? []),
        ]);

        return response()->json([
            'success' => true,
            ...$reportData,
        ]);
    }

    /**
     * Generate cache key for region report
     */
    private function generateCacheKey($params)
    {
        $keyParts = [
            'region_performance_report',
            'region_' . $params['region_id'],
            'year_' . $params['year'],
            'mode_' . $params['mode'],
            'start_' . $params['start_date'],
            'end_' . $params['end_date'],
            'details_' . ($params['show_details'] ?? false ? '1' : '0'),
        ];

        return implode(':', $keyParts);
    }

    /**
     * Generate region report data using optimized approach
     */
    private function generateRegionReportData(Request $request)
    {
        $regionId  = $request->input('region_id');
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');
        $mode      = $request->input('mode');
        $year      = $request->input('year');

        // Get region info
        $region = DB::table('regions')->find($regionId);
        if (! $region) {
            throw new \Exception('Region not found');
        }

        // Get all active users in the region during the period
        $users = $this->getActiveUsersInRegion($regionId, $startDate, $endDate);

        // Process performance data based on mode using optimized functions
        $reportData = [
            'region'     => [
                'id'   => $region->id,
                'name' => $region->name,
            ],
            'year'       => $year,
            'mode'       => $mode,
            'date_range' => [
                'start' => $startDate,
                'end'   => $endDate,
            ],
            'users_data' => [],
            'summary'    => [],
        ];

        switch ($mode) {
            case 'weekly':
                $reportData = $this->processWeeklySummaryDataOptimized($users, $startDate, $endDate, $reportData);
                break;
            case 'monthly':
                $reportData = $this->processMonthlySummaryDataOptimized($users, $startDate, $endDate, $reportData);
                break;
            case 'yearly':
                $reportData = $this->processYearlyDataOptimized($users, $startDate, $endDate, $reportData);
                break;
        }

        // Add category summary using optimized approach
        $reportData['category_summary'] = $this->calculateCategorySummaryOptimized($reportData['users_data']);

        return $reportData;
    }

    /**
     * Get active users in region during the specified period
     * Now uses historical region assignment during the reporting period
     */
    private function getActiveUsersInRegion($regionId, $startDate, $endDate)
    {
        // Get all active users during the period (regardless of current region)
        $allUsers = IvaUser::select([
            'id',
            'full_name',
            'email',
            'job_title',
            'work_status',
            'region_id',
            'hire_date',
            'end_date',
            'timedoctor_version',
        ])
            ->with(['customizations.setting.settingType'])
        // ->where('is_active', true) -- Duy remove inactive because we have end date.
            ->where(function ($query) use ($startDate, $endDate) {
                // User was active during the period
                $query->where(function ($q) use ($startDate) {
                    $q->whereNull('hire_date')
                        ->orWhere('hire_date', '<=', $startDate);
                })
                    ->where(function ($q) use ($startDate) {
                        $q->whereNull('end_date')
                            ->orWhere('end_date', '>=', $startDate);
                    });
            })
            ->orderBy('work_status')
            ->orderBy('full_name')
            ->get();

        // Filter users who were predominantly in this region during the reporting period
        // This mirrors the work status filtering logic exactly
        $usersInRegion = $allUsers->filter(function ($user) use ($regionId, $startDate, $endDate) {
            $predominantRegion = getPredominantRegionForPeriod($user, $startDate, $endDate);
            return $predominantRegion == $regionId;
        });

        return $usersInRegion;
    }

    /**
     * Process weekly summary data using optimized daily summaries
     */
    private function processWeeklySummaryDataOptimized($users, $startDate, $endDate, $reportData)
    {
        $allUsersData  = [];
        $fullTimeUsers = [];
        $partTimeUsers = [];

        // Fetch NAD data for all users once (optimized)
        $nadDataResponse = fetchNADDataForUsers($startDate, $endDate);
        $nadDataByEmail  = [];

        if (isset($nadDataResponse['nad_data']) && is_array($nadDataResponse['nad_data'])) {
            foreach ($nadDataResponse['nad_data'] as $nadUser) {
                $nadDataByEmail[$nadUser['email']] = [
                    'nad_count' => $nadUser['nad_count'] ?? 0,
                    'nad_hours' => ($nadUser['nad_count'] ?? 0) * ($nadDataResponse['nad_hour_rate'] ?? 8),
                    'requests'  => $nadUser['requests'] ?? 0,
                ];
            }
        }

        foreach ($users as $user) {
            // Use optimized basic metrics from daily summaries for the single week
            $weekMetrics = calculateBasicMetricsFromDailySummaries(
                $user->id,
                $startDate,
                $endDate
            );

            // Calculate performance using optimized function
            $weekPerformance = calculatePerformanceMetricsDailySummaries(
                $user,
                $startDate,
                $endDate,
                $weekMetrics['billable_hours']
            );

            // Get NAD data from optimized lookup
            $userNadData = $nadDataByEmail[$user->email] ?? ['nad_count' => 0, 'nad_hours' => 0, 'requests' => 0];

            // Get categories breakdown using optimized helper
            $categoriesResponse = calculateFullCategoryBreakdownFromSummaries($user->id, $startDate, $endDate);

            // Transform nested category structure to flat array for frontend compatibility
            $categoriesBreakdown = [];
            if (is_array($categoriesResponse)) {
                foreach ($categoriesResponse as $typeGroup) {
                    if (isset($typeGroup['categories']) && is_array($typeGroup['categories'])) {
                        foreach ($typeGroup['categories'] as $category) {
                            $categoriesBreakdown[] = [
                                'category_id'   => $category['category_id'],
                                'category_name' => $category['category_name'],
                                'hours'         => $category['total_hours'],
                            ];
                        }
                    }
                }
            }

            // Create single-week breakdown array
            $weeklyData = [[
                'week_number'        => $this->calculateWeekNumber($startDate),
                'start_date'         => $startDate,
                'end_date'           => $endDate,
                'billable_hours'     => $weekMetrics['billable_hours'],
                'non_billable_hours' => $weekMetrics['non_billable_hours'],
                'total_hours'        => $weekMetrics['total_hours'],
                'target_hours'       => $weekPerformance[0]['target_total_hours'] ?? 0,
                'performance'        => $weekPerformance[0] ?? null,
            ]];

            // Calculate historical work status for the period
            $predominantWorkStatus = getPredominantWorkStatusForPeriod($user, $startDate, $endDate);

            $userData = [
                'id'                 => $user->id,
                'full_name'          => $user->full_name,
                'email'              => $user->email,
                'job_title'          => $user->job_title,
                'work_status'        => $predominantWorkStatus,
                'billable_hours'     => round($weekMetrics['billable_hours'], 2),
                'non_billable_hours' => round($weekMetrics['non_billable_hours'], 2),
                'total_hours'        => round($weekMetrics['total_hours'], 2),
                'target_hours'       => round($weekPerformance[0]['target_total_hours'] ?? 0, 2),
                'nad_count'          => $userNadData['nad_count'],
                'nad_hours'          => round($userNadData['nad_hours'], 2),
                'performance'        => $weekPerformance[0] ?? null,
                'weekly_breakdown'   => $weeklyData,
                'categories'         => $categoriesBreakdown,
                'categoryresponse'   => $categoriesResponse, // For debugging only
            ];

            $allUsersData[] = $userData;

            // Separate by work status - use already calculated historical work status
            if ($predominantWorkStatus === 'full-time') {
                $fullTimeUsers[] = $userData;
            } else {
                $partTimeUsers[] = $userData;
            }
        }

        // Calculate summaries
        $reportData['users_data']      = $allUsersData;
        $reportData['full_time_users'] = $fullTimeUsers;
        $reportData['part_time_users'] = $partTimeUsers;

        $reportData['summary'] = [
            'full_time' => $this->calculateGroupSummary($fullTimeUsers),
            'part_time' => $this->calculateGroupSummary($partTimeUsers),
            'overall'   => $this->calculateGroupSummary($allUsersData),
        ];

        return $reportData;
    }

    /**
     * Process monthly summary data using optimized daily summaries
     */
    private function processMonthlySummaryDataOptimized($users, $startDate, $endDate, $reportData)
    {
        $allUsersData  = [];
        $fullTimeUsers = [];
        $partTimeUsers = [];

        // Fetch NAD data for all users once (optimized)
        $nadDataResponse = fetchNADDataForUsers($startDate, $endDate);
        $nadDataByEmail  = [];

        if (isset($nadDataResponse['nad_data']) && is_array($nadDataResponse['nad_data'])) {
            foreach ($nadDataResponse['nad_data'] as $nadUser) {
                $nadDataByEmail[$nadUser['email']] = [
                    'nad_count' => $nadUser['nad_count'] ?? 0,
                    'nad_hours' => ($nadUser['nad_count'] ?? 0) * ($nadDataResponse['nad_hour_rate'] ?? 8),
                    'requests'  => $nadUser['requests'] ?? 0,
                ];
            }
        }

        foreach ($users as $user) {
            // Use optimized basic metrics from daily summaries for the single month
            $monthMetrics = calculateBasicMetricsFromDailySummaries(
                $user->id,
                $startDate,
                $endDate
            );

            // Calculate performance using optimized function
            $monthPerformance = calculatePerformanceMetricsDailySummaries(
                $user,
                $startDate,
                $endDate,
                $monthMetrics['billable_hours']
            );

            // Get NAD data from optimized lookup
            $userNadData = $nadDataByEmail[$user->email] ?? ['nad_count' => 0, 'nad_hours' => 0, 'requests' => 0];

            // Get categories breakdown using optimized helper
            $categoriesResponse = calculateCategoryBreakdownFromSummaries($user->id, $startDate, $endDate);

            // Transform nested category structure to flat array for frontend compatibility
            $categoriesBreakdown = [];
            if (is_array($categoriesResponse)) {
                foreach ($categoriesResponse as $typeGroup) {
                    if (isset($typeGroup['categories']) && is_array($typeGroup['categories'])) {
                        foreach ($typeGroup['categories'] as $category) {
                            $categoriesBreakdown[] = [
                                'category_id'   => $category['category_id'],
                                'category_name' => $category['category_name'],
                                'hours'         => $category['total_hours'],
                            ];
                        }
                    }
                }
            }

            // Create single-month breakdown array
            $monthName   = Carbon::parse($startDate)->format('F');
            $monthlyData = [[
                'month_number'       => Carbon::parse($startDate)->month,
                'start_date'         => $startDate,
                'end_date'           => $endDate,
                'label'              => $monthName,
                'billable_hours'     => $monthMetrics['billable_hours'],
                'non_billable_hours' => $monthMetrics['non_billable_hours'],
                'total_hours'        => $monthMetrics['total_hours'],
                'target_hours'       => $monthPerformance[0]['target_total_hours'] ?? 0,
                'performance'        => $monthPerformance[0] ?? null,
            ]];

            // Calculate historical work status for the period
            $predominantWorkStatus = getPredominantWorkStatusForPeriod($user, $startDate, $endDate);

            $userData = [
                'id'                 => $user->id,
                'full_name'          => $user->full_name,
                'email'              => $user->email,
                'job_title'          => $user->job_title,
                'work_status'        => $predominantWorkStatus,
                'billable_hours'     => round($monthMetrics['billable_hours'], 2),
                'non_billable_hours' => round($monthMetrics['non_billable_hours'], 2),
                'total_hours'        => round($monthMetrics['total_hours'], 2),
                'target_hours'       => round($monthPerformance[0]['target_total_hours'] ?? 0, 2),
                'nad_count'          => $userNadData['nad_count'],
                'nad_hours'          => round($userNadData['nad_hours'], 2),
                'performance'        => $monthPerformance[0] ?? null,
                'monthly_breakdown'  => $monthlyData,
                'categories'         => $categoriesBreakdown,
            ];

            $allUsersData[] = $userData;

            // Separate by work status - use already calculated historical work status
            if ($predominantWorkStatus === 'full-time') {
                $fullTimeUsers[] = $userData;
            } else {
                $partTimeUsers[] = $userData;
            }
        }

        // Calculate summaries
        $reportData['users_data']      = $allUsersData;
        $reportData['full_time_users'] = $fullTimeUsers;
        $reportData['part_time_users'] = $partTimeUsers;

        $reportData['summary'] = [
            'full_time' => $this->calculateGroupSummary($fullTimeUsers),
            'part_time' => $this->calculateGroupSummary($partTimeUsers),
            'overall'   => $this->calculateGroupSummary($allUsersData),
        ];

        return $reportData;
    }

    /**
     * Process yearly data using optimized daily summaries
     */
    private function processYearlyDataOptimized($users, $startDate, $endDate, $reportData)
    {
        $allUsersData  = [];
        $fullTimeUsers = [];
        $partTimeUsers = [];

        // Fetch NAD data for all users once (optimized)
        $nadDataResponse = fetchNADDataForUsers($startDate, $endDate);
        $nadDataByEmail  = [];

        if (isset($nadDataResponse['nad_data']) && is_array($nadDataResponse['nad_data'])) {
            foreach ($nadDataResponse['nad_data'] as $nadUser) {
                $nadDataByEmail[$nadUser['email']] = [
                    'nad_count' => $nadUser['nad_count'] ?? 0,
                    'nad_hours' => ($nadUser['nad_count'] ?? 0) * ($nadDataResponse['nad_hour_rate'] ?? 8),
                    'requests'  => $nadUser['requests'] ?? 0,
                ];
            }
        }

        foreach ($users as $user) {
            // Use optimized basic metrics from daily summaries for the year
            $yearMetrics = calculateBasicMetricsFromDailySummaries(
                $user->id,
                $startDate,
                $endDate
            );

            // Calculate performance using optimized function
            $yearPerformance = calculatePerformanceMetricsDailySummaries(
                $user,
                $startDate,
                $endDate,
                $yearMetrics['billable_hours']
            );

            // Get NAD data from optimized lookup
            $userNadData = $nadDataByEmail[$user->email] ?? ['nad_count' => 0, 'nad_hours' => 0, 'requests' => 0];

            // Get categories breakdown using optimized helper
            $categoriesResponse = calculateCategoryBreakdownFromSummaries($user->id, $startDate, $endDate);

            // Transform nested category structure to flat array for frontend compatibility
            $categoriesBreakdown = [];
            if (is_array($categoriesResponse)) {
                foreach ($categoriesResponse as $typeGroup) {
                    if (isset($typeGroup['categories']) && is_array($typeGroup['categories'])) {
                        foreach ($typeGroup['categories'] as $category) {
                            $categoriesBreakdown[] = [
                                'category_id'   => $category['category_id'],
                                'category_name' => $category['category_name'],
                                'hours'         => $category['total_hours'],
                            ];
                        }
                    }
                }
            }

            // Calculate historical work status for the period
            $predominantWorkStatus = getPredominantWorkStatusForPeriod($user, $startDate, $endDate);

            $userData = [
                'id'                 => $user->id,
                'full_name'          => $user->full_name,
                'email'              => $user->email,
                'job_title'          => $user->job_title,
                'work_status'        => $predominantWorkStatus,
                'billable_hours'     => round($yearMetrics['billable_hours'], 2),
                'non_billable_hours' => round($yearMetrics['non_billable_hours'], 2),
                'total_hours'        => round($yearMetrics['total_hours'], 2),
                'target_hours'       => round($yearPerformance[0]['target_total_hours'] ?? 0, 2),
                'nad_count'          => $userNadData['nad_count'],
                'nad_hours'          => round($userNadData['nad_hours'], 2),
                'performance'        => $yearPerformance[0] ?? null,
                'categories'         => $categoriesBreakdown,
            ];

            $allUsersData[] = $userData;

            // Separate by work status - use already calculated historical work status
            if ($predominantWorkStatus === 'full-time') {
                $fullTimeUsers[] = $userData;
            } else {
                $partTimeUsers[] = $userData;
            }
        }

        // Calculate summaries
        $reportData['users_data']      = $allUsersData;
        $reportData['full_time_users'] = $fullTimeUsers;
        $reportData['part_time_users'] = $partTimeUsers;

        $reportData['summary'] = [
            'full_time' => $this->calculateGroupSummary($fullTimeUsers),
            'part_time' => $this->calculateGroupSummary($partTimeUsers),
            'overall'   => $this->calculateGroupSummary($allUsersData),
        ];

        return $reportData;
    }

    /**
     * Calculate group summary with optimized performance data structure
     */
    private function calculateGroupSummary($users)
    {
        if (empty($users)) {
            return [
                'total_users'              => 0,
                'total_billable_hours'     => 0,
                'total_non_billable_hours' => 0,
                'total_hours'              => 0,
                'total_target_hours'       => 0,
                'total_nad_count'          => 0,
                'total_nad_hours'          => 0,
                'avg_performance'          => 0,
                'performance_breakdown'    => [
                    'exceeded' => 0,
                    'meet'     => 0,
                    'below'    => 0,
                ],
            ];
        }

        $totalBillableHours    = 0;
        $totalNonBillableHours = 0;
        $totalTargetHours      = 0;
        $totalNadCount         = 0;
        $totalNadHours         = 0;
        $performanceBreakdown  = [
            'exceeded' => 0,
            'meet'     => 0,
            'below'    => 0,
        ];

        foreach ($users as $user) {
            $totalBillableHours += $user['billable_hours'];
            $totalNonBillableHours += $user['non_billable_hours'];
            $totalTargetHours += $user['target_hours'];
            $totalNadCount += $user['nad_count'];
            $totalNadHours += $user['nad_hours'];

            if (isset($user['performance']) && $user['performance']) {
                $status = $user['performance']['status'];
                switch ($status) {
                    case 'EXCEEDED':
                        $performanceBreakdown['exceeded']++;
                        break;
                    case 'MEET':
                        $performanceBreakdown['meet']++;
                        break;
                    case 'BELOW':
                        $performanceBreakdown['below']++;
                        break;
                }
            }
        }

        $avgPerformance = $totalTargetHours > 0
            ? round(($totalBillableHours / $totalTargetHours) * 100, 1)
            : 0;

        return [
            'total_users'              => count($users),
            'total_billable_hours'     => round($totalBillableHours, 2),
            'total_non_billable_hours' => round($totalNonBillableHours, 2),
            'total_hours'              => round($totalBillableHours + $totalNonBillableHours, 2),
            'total_target_hours'       => round($totalTargetHours, 2),
            'total_nad_count'          => $totalNadCount,
            'total_nad_hours'          => round($totalNadHours, 2),
            'avg_performance'          => $avgPerformance,
            'performance_breakdown'    => $performanceBreakdown,
        ];
    }

    /**
     * Calculate category summary across all users using optimized approach
     */
    private function calculateCategorySummaryOptimized($usersData)
    {
        $categorySummary = [];

        foreach ($usersData as $user) {
            if (! isset($user['categories'])) {
                continue;
            }

            foreach ($user['categories'] as $category) {
                $categoryId = $category['category_id'];

                if (! isset($categorySummary[$categoryId])) {
                    $categorySummary[$categoryId] = [
                        'category_id'   => $categoryId,
                        'category_name' => $category['category_name'],
                        'total_hours'   => 0,
                        'user_count'    => 0,
                    ];
                }

                $categorySummary[$categoryId]['total_hours'] += $category['hours'];

                // Only count users who actually worked on this category
                if ($category['hours'] > 0) {
                    $categorySummary[$categoryId]['user_count']++;
                }
            }
        }

        // Convert to array and sort by total hours descending
        $result = array_values($categorySummary);
        usort($result, function ($a, $b) {
            return $b['total_hours'] <=> $a['total_hours'];
        });

        // Calculate averages and round hours
        foreach ($result as &$category) {
            $category['total_hours']        = round($category['total_hours'], 2);
            $category['avg_hours_per_user'] = $category['user_count'] > 0
                ? round($category['total_hours'] / $category['user_count'], 2)
                : 0;
        }

        return $result;
    }

    /**
     * Calculate week number for a given date
     */
    private function calculateWeekNumber($date)
    {
        return Carbon::parse($date)->weekOfYear;
    }

    /**
     * Get available regions for report
     */
    public function getAvailableRegions(Request $request)
    {
        // Validate region access for users with view_team_data permission
        $regionValidation = validateManagerRegionAccess($request->user());
        if ($regionValidation) {
            return response()->json([
                'success' => false,
                'error' => $regionValidation['error'],
                'message' => $regionValidation['message'],
                'region_access_error' => true
            ], 403);
        }

        // Check if current user should be filtered by region
        $managerRegionFilter = getManagerRegionFilter($request->user());

        $query = DB::table('regions')
            ->where('is_active', true)
            ->orderBy('name')
            ->select('id', 'name', 'description');

        // Filter regions if manager has view_team_data only
        if ($managerRegionFilter) {
            $query->where('id', $managerRegionFilter);
        }

        $regions = $query->get()
            ->map(function ($region) {
                // Get user count for each region
                $userCount = IvaUser::where('region_id', $region->id)
                    ->where('is_active', true)
                    ->count();

                // Get cohorts in this region
                $cohorts = DB::table('iva_user')
                    ->join('cohorts', 'iva_user.cohort_id', '=', 'cohorts.id')
                    ->where('iva_user.region_id', $region->id)
                    ->where('iva_user.is_active', true)
                    ->where('cohorts.is_active', true)
                    ->select('cohorts.id', 'cohorts.name', 'cohorts.cohort_order')
                    ->distinct()
                    ->orderBy('cohorts.cohort_order')
                    ->get();

                // Count cohorts
                $cohortCount = $cohorts->count();
                $cohortNames = $cohorts->pluck('name')->implode(', ');

                return [
                    'id'           => $region->id,
                    'name'         => $region->name,
                    'description'  => $region->description,
                    'user_count'   => $userCount,
                    'cohort_count' => $cohortCount,
                    'cohort_names' => $cohortNames,
                    'cohorts'      => $cohorts->toArray(),
                ];
            });

        return response()->json([
            'success'       => true,
            'regions'       => $regions,
            'region_filter' => $managerRegionFilter ? [
                'applied'   => true,
                'region_id' => $managerRegionFilter,
                'locked'    => true,
                'reason'    => 'view_team_data_permission',
            ] : ['applied' => false, 'locked' => false],
        ]);
    }
}