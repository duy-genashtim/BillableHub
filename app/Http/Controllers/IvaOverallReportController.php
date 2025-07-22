<?php
namespace App\Http\Controllers;

use App\Models\IvaUser;
use App\Models\WorklogsData;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class IvaOverallReportController extends Controller
{
    /**
     * Get overall performance report for all IVA users grouped by region
     */
    public function getOverallPerformanceReport(Request $request)
    {
        $request->merge([
            'force_reload' => filter_var($request->query('force_reload'), FILTER_VALIDATE_BOOLEAN),
            'show_details' => filter_var($request->query('show_details'), FILTER_VALIDATE_BOOLEAN),
        ]);

        $validator = Validator::make($request->all(), [
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
                Log::info('Overall report served from cache', ['cache_key' => $cacheKey]);
                return response()->json([
                    'success'   => true,
                    'cached'    => true,
                    'cached_at' => $cachedData['cached_at'] ?? null,
                    ...$cachedData['data'],
                ]);
            }
        }

        // Generate fresh data
        $reportData = $this->generateOverallReportData($request);

        // Cache the data (60 minutes TTL)
        $wrappedData = [
            'data'      => $reportData,
            'cached_at' => now()->toISOString(),
        ];
        Cache::put($cacheKey, $wrappedData, 60);

        Log::info('Overall report generated fresh', [
            'cache_key'   => $cacheKey,
            'regions_count' => count($reportData['regions_data'] ?? []),
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
     * Generate cache key for overall report
     */
    private function generateCacheKey($params)
    {
        $keyParts = [
            'overall_performance_report',
            'year_' . $params['year'],
            'mode_' . $params['mode'],
            'start_' . $params['start_date'],
            'end_' . $params['end_date'],
            'details_' . ($params['show_details'] ?? false ? '1' : '0'),
        ];

        return implode(':', $keyParts);
    }

    /**
     * Generate overall report data grouped by regions
     */
    private function generateOverallReportData(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');
        $mode      = $request->input('mode');
        $year      = $request->input('year');

        // Get all active regions
        $regions = DB::table('regions')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Get all active users during the period
        $allUsers = $this->getAllActiveUsers($startDate, $endDate);

        // Get task categories mapping
        $taskCategories = $this->getTaskCategoriesMapping();

        // Process performance data based on mode
        $reportData = [
            'year'         => $year,
            'mode'         => $mode,
            'date_range'   => [
                'start' => $startDate,
                'end'   => $endDate,
            ],
            'regions_data' => [],
            'users_data'   => [],
            'summary'      => [],
        ];

        // Group users by region and work status
        $regionUserGroups = [];
        $allFullTimeUsers = [];
        $allPartTimeUsers = [];
        $allUsersData = [];

        foreach ($regions as $region) {
            $regionUsers = $allUsers->where('region_id', $region->id);
            
            if ($regionUsers->isEmpty()) {
                continue;
            }

            // Process region users
            $regionData = $this->processRegionUsers($regionUsers, $startDate, $endDate, $mode, $taskCategories, $region);
            
            if (!empty($regionData['users_data'])) {
                $reportData['regions_data'][] = $regionData;
                
                // Add to overall collections
                $allUsersData = array_merge($allUsersData, $regionData['users_data']);
                $allFullTimeUsers = array_merge($allFullTimeUsers, $regionData['full_time_users']);
                $allPartTimeUsers = array_merge($allPartTimeUsers, $regionData['part_time_users']);
            }
        }

        // Store overall user collections
        $reportData['users_data'] = $allUsersData;
        $reportData['full_time_users'] = $allFullTimeUsers;
        $reportData['part_time_users'] = $allPartTimeUsers;

        // Calculate overall summaries
        $reportData['summary'] = [
            'full_time' => $this->calculateGroupSummary($allFullTimeUsers),
            'part_time' => $this->calculateGroupSummary($allPartTimeUsers),
            'overall'   => $this->calculateGroupSummary($allUsersData),
        ];

        // Add category summary for all users
        $reportData['category_summary'] = $this->calculateCategorySummary($allUsersData, $taskCategories);

        return $reportData;
    }

    /**
     * Get all active users during the specified period
     */
    private function getAllActiveUsers($startDate, $endDate)
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
            ->with(['customizations.setting.settingType', 'region'])
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
            ->orderBy('region_id')
            ->orderBy('work_status')
            ->orderBy('full_name')
            ->get();
    }

    /**
     * Process users for a specific region
     */
    private function processRegionUsers($users, $startDate, $endDate, $mode, $taskCategories, $region)
    {
        $regionFullTimeUsers = [];
        $regionPartTimeUsers = [];
        $regionAllUsers = [];

        switch ($mode) {
            case 'weekly':
                $reportData = $this->processWeeklySummaryData($users, $startDate, $endDate, $taskCategories);
                break;
            case 'monthly':
                $reportData = $this->processMonthlySummaryData($users, $startDate, $endDate, $taskCategories);
                break;
            case 'yearly':
                $reportData = $this->processYearlyData($users, $startDate, $endDate, $taskCategories);
                break;
        }

        $regionAllUsers = $reportData['users_data'];
        $regionFullTimeUsers = $reportData['full_time_users'];
        $regionPartTimeUsers = $reportData['part_time_users'];

        return [
            'region' => [
                'id'   => $region->id,
                'name' => $region->name,
            ],
            'users_data'       => $regionAllUsers,
            'full_time_users'  => $regionFullTimeUsers,
            'part_time_users'  => $regionPartTimeUsers,
            'summary' => [
                'full_time' => $this->calculateGroupSummary($regionFullTimeUsers),
                'part_time' => $this->calculateGroupSummary($regionPartTimeUsers),
                'overall'   => $this->calculateGroupSummary($regionAllUsers),
            ],
        ];
    }

    // Reuse existing methods from IvaRegionReportController
    private function getTaskCategoriesMapping()
    {
        $mappingData = DB::table('task_report_categories as trc')
            ->join('report_categories as rc', 'trc.cat_id', '=', 'rc.id')
            ->join('configuration_settings as cs', 'rc.category_type', '=', 'cs.id')
            ->where('rc.is_active', true)
            ->select([
                'trc.task_id',
                'rc.id as category_id',
                'rc.cat_name',
                'cs.setting_value as category_type',
            ])
            ->get();

        $billableTaskIds       = [];
        $nonBillableTaskIds    = [];
        $fullMapping           = [];
        $taskToCategoryMap     = [];
        $allBillableCategories = [];

        foreach ($mappingData as $mapping) {
            $taskId = $mapping->task_id;

            if (! isset($fullMapping[$taskId])) {
                $fullMapping[$taskId] = collect();
            }
            $fullMapping[$taskId]->push($mapping);

            // Map task to category
            $taskToCategoryMap[$taskId] = $mapping->category_id;

            if (stripos($mapping->category_type, 'billable') === 0) {
                $billableTaskIds[] = $taskId;

                // Store all billable categories with their info
                $allBillableCategories[$mapping->category_id] = [
                    'category_id'   => $mapping->category_id,
                    'category_name' => $mapping->cat_name,
                ];
            } elseif (stripos($mapping->category_type, 'non-billable') !== false) {
                $nonBillableTaskIds[] = $taskId;
            }
        }

        return [
            'billable_task_ids'       => array_unique($billableTaskIds),
            'non_billable_task_ids'   => array_unique($nonBillableTaskIds),
            'full_mapping'            => collect($fullMapping),
            'category_mapping'        => $taskToCategoryMap,
            'all_billable_categories' => $allBillableCategories,
        ];
    }

    // Reuse processing methods from region controller
    private function processWeeklySummaryData($users, $startDate, $endDate, $taskCategories)
    {
        $allUsersData  = [];
        $fullTimeUsers = [];
        $partTimeUsers = [];

        // Get week ranges
        $weekRanges = getWeekRangeForDates($startDate, $endDate, 1);

        foreach ($users as $user) {
            // Get work status changes
            $workStatusChanges = getWorkStatusChanges($user, $startDate, $endDate);

            // Get all worklogs for the entire period first
            $allWorklogs = WorklogsData::where('iva_id', $user->id)
                ->where('is_active', true)
                ->whereBetween('start_time', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->get();

            // Process each week
            $weeklyData            = [];
            $totalBillableHours    = 0;
            $totalNonBillableHours = 0;
            $totalTargetHours      = 0;

            foreach ($weekRanges as $week) {
                $weekWorklogData = $this->getUserWorklogDataForPeriod(
                    $user->id,
                    $week['start_date'],
                    $week['end_date'],
                    $taskCategories
                );

                // Calculate week metrics
                $weekMetrics = $this->calculateWeekMetrics($user, $weekWorklogData, $week, $workStatusChanges);

                $weeklyData[] = $weekMetrics;
                $totalBillableHours += $weekMetrics['billable_hours'];
                $totalNonBillableHours += $weekMetrics['non_billable_hours'];
                $totalTargetHours += $weekMetrics['target_hours'];
            }

            // Calculate NAD data
            $nadData = fetchNADDataForPeriod($user, $startDate, $endDate);

            // Calculate performance using actual worklogs
            $performance = calculatePerformanceMetrics($user, $allWorklogs, $startDate, $endDate, $workStatusChanges);

            // Get categories breakdown
            $categoriesBreakdown = $this->getUserCategoriesBreakdown($user->id, $startDate, $endDate, $taskCategories);

            $userData = [
                'id'                 => $user->id,
                'full_name'          => $user->full_name,
                'email'              => $user->email,
                'job_title'          => $user->job_title,
                'work_status'        => $user->work_status,
                'region_id'          => $user->region_id,
                'region_name'        => $user->region->name ?? 'Unknown',
                'billable_hours'     => round($totalBillableHours, 2),
                'non_billable_hours' => round($totalNonBillableHours, 2),
                'total_hours'        => round($totalBillableHours + $totalNonBillableHours, 2),
                'target_hours'       => round($totalTargetHours, 2),
                'nad_count'          => $nadData['nad_count'],
                'nad_hours'          => $nadData['nad_hours'],
                'performance'        => $performance[0] ?? null,
                'weekly_breakdown'   => $weeklyData,
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

        return [
            'users_data'       => $allUsersData,
            'full_time_users'  => $fullTimeUsers,
            'part_time_users'  => $partTimeUsers,
        ];
    }

    private function processMonthlySummaryData($users, $startDate, $endDate, $taskCategories)
    {
        $allUsersData  = [];
        $fullTimeUsers = [];
        $partTimeUsers = [];

        // Get month ranges
        $monthRanges = getMonthRangeForDates($startDate, $endDate, 12, null);

        foreach ($users as $user) {
            // Get work status changes
            $workStatusChanges = getWorkStatusChanges($user, $startDate, $endDate);

            // Get all worklogs for the entire period first
            $allWorklogs = WorklogsData::where('iva_id', $user->id)
                ->where('is_active', true)
                ->whereBetween('start_time', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->get();

            // Process each month
            $monthlyData           = [];
            $totalBillableHours    = 0;
            $totalNonBillableHours = 0;
            $totalTargetHours      = 0;

            foreach ($monthRanges as $month) {
                $monthWorklogData = $this->getUserWorklogDataForPeriod(
                    $user->id,
                    $month['start_date'],
                    $month['end_date'],
                    $taskCategories
                );

                // Calculate month metrics
                $monthMetrics = $this->calculateMonthMetrics($user, $monthWorklogData, $month, $workStatusChanges);

                $monthlyData[] = $monthMetrics;
                $totalBillableHours += $monthMetrics['billable_hours'];
                $totalNonBillableHours += $monthMetrics['non_billable_hours'];
                $totalTargetHours += $monthMetrics['target_hours'];
            }

            // Calculate NAD data
            $nadData = fetchNADDataForPeriod($user, $startDate, $endDate);

            // Calculate performance using actual worklogs
            $performance = calculatePerformanceMetrics($user, $allWorklogs, $startDate, $endDate, $workStatusChanges);

            // Get categories breakdown
            $categoriesBreakdown = $this->getUserCategoriesBreakdown($user->id, $startDate, $endDate, $taskCategories);

            $userData = [
                'id'                 => $user->id,
                'full_name'          => $user->full_name,
                'email'              => $user->email,
                'job_title'          => $user->job_title,
                'work_status'        => $user->work_status,
                'region_id'          => $user->region_id,
                'region_name'        => $user->region->name ?? 'Unknown',
                'billable_hours'     => round($totalBillableHours, 2),
                'non_billable_hours' => round($totalNonBillableHours, 2),
                'total_hours'        => round($totalBillableHours + $totalNonBillableHours, 2),
                'target_hours'       => round($totalTargetHours, 2),
                'nad_count'          => $nadData['nad_count'],
                'nad_hours'          => $nadData['nad_hours'],
                'performance'        => $performance[0] ?? null,
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

        return [
            'users_data'       => $allUsersData,
            'full_time_users'  => $fullTimeUsers,
            'part_time_users'  => $partTimeUsers,
        ];
    }

    private function processYearlyData($users, $startDate, $endDate, $taskCategories)
    {
        $allUsersData  = [];
        $fullTimeUsers = [];
        $partTimeUsers = [];

        foreach ($users as $user) {
            // Get work status changes
            $workStatusChanges = getWorkStatusChanges($user, $startDate, $endDate);

            // Get all worklogs for the year
            $yearWorklogData = $this->getUserWorklogDataForPeriod(
                $user->id,
                $startDate,
                $endDate,
                $taskCategories
            );

            // Calculate metrics
            $billableHours    = $yearWorklogData['billable_hours'];
            $nonBillableHours = $yearWorklogData['non_billable_hours'];

            // Calculate NAD data
            $nadData = fetchNADDataForPeriod($user, $startDate, $endDate);

            // Calculate performance
            $worklogs = WorklogsData::where('iva_id', $user->id)
                ->where('is_active', true)
                ->whereBetween('start_time', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->get();

            $performance = calculatePerformanceMetrics($user, $worklogs, $startDate, $endDate, $workStatusChanges);

            // Get categories breakdown
            $categoriesBreakdown = $this->getUserCategoriesBreakdown($user->id, $startDate, $endDate, $taskCategories);

            $userData = [
                'id'                 => $user->id,
                'full_name'          => $user->full_name,
                'email'              => $user->email,
                'job_title'          => $user->job_title,
                'work_status'        => $user->work_status,
                'region_id'          => $user->region_id,
                'region_name'        => $user->region->name ?? 'Unknown',
                'billable_hours'     => round($billableHours, 2),
                'non_billable_hours' => round($nonBillableHours, 2),
                'total_hours'        => round($billableHours + $nonBillableHours, 2),
                'target_hours'       => $performance[0]['target_total_hours'] ?? 0,
                'nad_count'          => $nadData['nad_count'],
                'nad_hours'          => $nadData['nad_hours'],
                'performance'        => $performance[0] ?? null,
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

        return [
            'users_data'       => $allUsersData,
            'full_time_users'  => $fullTimeUsers,
            'part_time_users'  => $partTimeUsers,
        ];
    }

    // Reuse helper methods from region controller
    private function getUserWorklogDataForPeriod($userId, $startDate, $endDate, $taskCategories)
    {
        $billableIds    = implode(',', array_merge([0], $taskCategories['billable_task_ids']));
        $nonBillableIds = implode(',', array_merge([0], $taskCategories['non_billable_task_ids']));

        $result = WorklogsData::select([
            DB::raw("SUM(CASE WHEN task_id IN ({$billableIds}) THEN duration ELSE 0 END) / 3600 as billable_hours"),
            DB::raw("SUM(CASE WHEN task_id IN ({$nonBillableIds}) THEN duration ELSE 0 END) / 3600 as non_billable_hours"),
            DB::raw("SUM(duration) / 3600 as total_hours"),
            DB::raw("COUNT(*) as entries_count"),
        ])
            ->where('iva_id', $userId)
            ->where('is_active', true)
            ->whereBetween('start_time', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->first();

        return [
            'billable_hours'     => (float) ($result->billable_hours ?? 0),
            'non_billable_hours' => (float) ($result->non_billable_hours ?? 0),
            'total_hours'        => (float) ($result->total_hours ?? 0),
            'entries_count'      => (int) ($result->entries_count ?? 0),
        ];
    }

    private function getUserCategoriesBreakdown($userId, $startDate, $endDate, $taskCategories)
    {
        $taskToCategoryMap     = $taskCategories['category_mapping'];
        $allBillableCategories = $taskCategories['all_billable_categories'];

        // Get all worklogs with task info for billable tasks only
        $worklogs = WorklogsData::select(['task_id', 'duration'])
            ->where('iva_id', $userId)
            ->where('is_active', true)
            ->whereBetween('start_time', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->whereIn('task_id', $taskCategories['billable_task_ids'])
            ->get();

        // Initialize all billable categories with 0 hours
        $categoryHours = [];
        foreach ($allBillableCategories as $categoryId => $categoryInfo) {
            $categoryHours[$categoryId] = 0;
        }

        // Add actual worked hours
        foreach ($worklogs as $worklog) {
            $categoryId = $taskToCategoryMap[$worklog->task_id] ?? null;
            if ($categoryId && isset($categoryHours[$categoryId])) {
                $categoryHours[$categoryId] += $worklog->duration / 3600;
            }
        }

        // Build result array with all categories (including those with 0 hours)
        $result = [];
        foreach ($allBillableCategories as $categoryId => $categoryInfo) {
            $result[] = [
                'category_id'   => $categoryId,
                'category_name' => $categoryInfo['category_name'],
                'hours'         => round($categoryHours[$categoryId], 2),
            ];
        }

        // Sort by hours descending
        usort($result, function ($a, $b) {
            return $b['hours'] <=> $a['hours'];
        });

        return $result;
    }

    private function calculateWeekMetrics($user, $worklogData, $week, $workStatusChanges)
    {
        // Calculate performance for this week
        $worklogs = WorklogsData::where('iva_id', $user->id)
            ->where('is_active', true)
            ->whereBetween('start_time', [$week['start_date'] . ' 00:00:00', $week['end_date'] . ' 23:59:59'])
            ->get();

        $performance = calculatePerformanceMetrics(
            $user,
            $worklogs,
            $week['start_date'],
            $week['end_date'],
            $workStatusChanges
        );

        return [
            'week_number'        => $week['week_number'],
            'start_date'         => $week['start_date'],
            'end_date'           => $week['end_date'],
            'billable_hours'     => $worklogData['billable_hours'],
            'non_billable_hours' => $worklogData['non_billable_hours'],
            'total_hours'        => $worklogData['total_hours'],
            'target_hours'       => $performance[0]['target_total_hours'] ?? 0,
            'performance'        => $performance[0] ?? null,
        ];
    }

    private function calculateMonthMetrics($user, $worklogData, $month, $workStatusChanges)
    {
        // Calculate performance for this month
        $worklogs = WorklogsData::where('iva_id', $user->id)
            ->where('is_active', true)
            ->whereBetween('start_time', [$month['start_date'] . ' 00:00:00', $month['end_date'] . ' 23:59:59'])
            ->get();

        $performance = calculatePerformanceMetrics(
            $user,
            $worklogs,
            $month['start_date'],
            $month['end_date'],
            $workStatusChanges
        );

        // Get the month name from the start date
        $monthName = Carbon::parse($month['start_date'])->format('F');

        return [
            'month_number'       => $month['month_number'],
            'start_date'         => $month['start_date'],
            'end_date'           => $month['end_date'],
            'label'              => $monthName,
            'billable_hours'     => $worklogData['billable_hours'],
            'non_billable_hours' => $worklogData['non_billable_hours'],
            'total_hours'        => $worklogData['total_hours'],
            'target_hours'       => $performance[0]['target_total_hours'] ?? 0,
            'performance'        => $performance[0] ?? null,
        ];
    }

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

    private function calculateCategorySummary($usersData, $taskCategories)
    {
        $allBillableCategories = $taskCategories['all_billable_categories'];

        // Initialize with all billable categories
        $categorySummary = [];
        foreach ($allBillableCategories as $categoryId => $categoryInfo) {
            $categorySummary[$categoryId] = [
                'category_id'   => $categoryId,
                'category_name' => $categoryInfo['category_name'],
                'total_hours'   => 0,
                'user_count'    => 0,
            ];
        }

        // Add user data
        foreach ($usersData as $user) {
            if (! isset($user['categories'])) {
                continue;
            }

            foreach ($user['categories'] as $category) {
                $categoryId = $category['category_id'];

                if (isset($categorySummary[$categoryId])) {
                    $categorySummary[$categoryId]['total_hours'] += $category['hours'];

                    // Only count users who actually worked on this category
                    if ($category['hours'] > 0) {
                        $categorySummary[$categoryId]['user_count']++;
                    }
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
     * Clear overall report cache
     */
    public function clearOverallReportCache(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'year' => 'nullable|integer',
            'mode' => 'nullable|in:weekly,monthly,yearly',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $pattern = 'overall_performance_report:';

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

            Log::info('Overall report cache cleared', ['pattern' => $pattern, 'keys_cleared' => count($keys)]);

            return response()->json([
                'success'    => true,
                'message'    => 'Cache cleared successfully',
                'cleared_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear overall report cache', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache',
            ], 500);
        }
    }
}