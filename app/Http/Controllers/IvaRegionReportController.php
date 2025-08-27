<?php
namespace App\Http\Controllers;

use App\Models\IvaUser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        $request->merge([
            'force_reload' => filter_var($request->query('force_reload'), FILTER_VALIDATE_BOOLEAN),
            'show_details' => filter_var($request->query('show_details'), FILTER_VALIDATE_BOOLEAN),
        ]);

        $validator = Validator::make($request->all(), [
            'region_id'    => 'required|exists:regions,id',
            'year'         => 'required|integer|min:2024',
            'start_date'   => 'required|date|date_format:Y-m-d',
            'end_date'     => 'required|date|date_format:Y-m-d|after_or_equal:start_date',
            'mode'         => 'required|in:weekly,monthly,yearly',
            'show_details' => 'nullable|boolean',
            'force_reload' => 'nullable|boolean',
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

        // Cache key
        $cacheKey    = $this->generateCacheKey($request->all());
        $forceReload = $request->boolean('force_reload');

        // Try to get cached data
        if (! $forceReload) {
            $cachedData = Cache::get($cacheKey);
            if ($cachedData !== null) {
                Log::info('Region report served from cache', ['cache_key' => $cacheKey]);
                return response()->json([
                    'success'   => true,
                    'cached'    => true,
                    'cached_at' => $cachedData['cached_at'] ?? null,
                    ...$cachedData['data'],
                ]);
            }
        }

        // Generate fresh data
        $reportData = $this->generateRegionReportData($request);

        // Cache the data (60 minutes TTL)
        $wrappedData = [
            'data'      => $reportData,
            'cached_at' => now()->toISOString(),
        ];
        Cache::put($cacheKey, $wrappedData, 60);

        Log::info('Region report generated fresh', [
            'cache_key'   => $cacheKey,
            'region_id'   => $request->input('region_id'),
            'users_count' => count($reportData['users_data'] ?? [])
        ]);

        return response()->json([
            'success'      => true,
            'cached'       => false,
            'generated_at' => now()->toISOString(),
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
     */
    private function getActiveUsersInRegion($regionId, $startDate, $endDate)
    {
        return IvaUser::select([
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
            ->where('region_id', $regionId)
            ->where('is_active', true)
            ->where(function ($query) use ($startDate, $endDate) {
                // User was active during the period
                $query->where(function ($q) use ($startDate) {
                    $q->whereNull('hire_date')
                        ->orWhere('hire_date', '<=', $startDate);
                })
                    ->where(function ($q) use ($endDate) {
                        $q->whereNull('end_date')
                            ->orWhere('end_date', '>=', $endDate);
                    });
            })
            ->orderBy('work_status')
            ->orderBy('full_name')
            ->get();
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

            $userData = [
                'id'                 => $user->id,
                'full_name'          => $user->full_name,
                'email'              => $user->email,
                'job_title'          => $user->job_title,
                'work_status'        => $user->work_status,
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

            // Separate by work status
            if ($user->work_status === 'full-time') {
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

            $userData = [
                'id'                 => $user->id,
                'full_name'          => $user->full_name,
                'email'              => $user->email,
                'job_title'          => $user->job_title,
                'work_status'        => $user->work_status,
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

            // Separate by work status
            if ($user->work_status === 'full-time') {
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

            $userData = [
                'id'                 => $user->id,
                'full_name'          => $user->full_name,
                'email'              => $user->email,
                'job_title'          => $user->job_title,
                'work_status'        => $user->work_status,
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

            // Separate by work status
            if ($user->work_status === 'full-time') {
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
     * Clear region report cache
     */
    public function clearRegionReportCache(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'region_id' => 'nullable|exists:regions,id',
            'year'      => 'nullable|integer',
            'mode'      => 'nullable|in:weekly,monthly,yearly',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $pattern = 'region_performance_report:';

            if ($request->filled('region_id')) {
                $pattern .= 'region_' . $request->input('region_id') . ':';
            } else {
                $pattern .= '*';
            }

            if ($request->filled('year')) {
                $pattern .= 'year_' . $request->input('year') . ':';
            }

            if ($request->filled('mode')) {
                $pattern .= 'mode_' . $request->input('mode') . ':';
            }

            $pattern .= '*';

            // Clear matching cache keys
            $keys = Cache::getRedis()->keys($pattern);
            foreach ($keys as $key) {
                $cleanKey = str_replace(config('database.redis.options.prefix'), '', $key);
                Cache::forget($cleanKey);
            }

            Log::info('Region report cache cleared', ['pattern' => $pattern, 'keys_cleared' => count($keys)]);

            return response()->json([
                'success'    => true,
                'message'    => 'Cache cleared successfully',
                'cleared_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear region report cache', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache',
            ], 500);
        }
    }

    /**
     * Get available regions for report
     */
    public function getAvailableRegions()
    {
        $regions = DB::table('regions')
            ->where('is_active', true)
            ->orderBy('name')
            ->select('id', 'name', 'description')
            ->get()
            ->map(function ($region) {
                // Get user count for each region
                $userCount = IvaUser::where('region_id', $region->id)
                    ->where('is_active', true)
                    ->count();

                return [
                    'id'          => $region->id,
                    'name'        => $region->name,
                    'description' => $region->description,
                    'user_count'  => $userCount,
                ];
            });

        return response()->json([
            'success' => true,
            'regions' => $regions,
        ]);
    }
}
