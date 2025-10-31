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

class IvaWeeklyReportController extends Controller
{
    /**
     * Get weekly performance report for all active IVA users with caching
     */
    public function getWeeklyPerformanceReport(Request $request)
    {
        $request->merge([
            'force_reload' => filter_var($request->query('force_reload'), FILTER_VALIDATE_BOOLEAN),
        ]);
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2024',
            'week_number' => 'required|integer|min:1|max:52',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'work_status' => 'nullable|string',
            'region' => 'nullable|string',
            'search' => 'nullable|string',
            'sort_by' => 'nullable|string|in:name,billable,non_billable,uncategorized,total,performance',
            'sort_order' => 'nullable|string|in:asc,desc',
            'force_reload' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Validate week dates (must be Monday to Sunday, exactly 7 days)
        $startDate = Carbon::parse($request->input('start_date'));
        $endDate = Carbon::parse($request->input('end_date'));

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

        $daysDiff = $startDate->diffInDays($endDate);
        if ($daysDiff != 6) {
            return response()->json([
                'success' => false,
                'message' => 'Week must be exactly 7 days (Monday to Sunday)',
                'details' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'days_diff' => $daysDiff,
                ],
            ], 422);
        }

        // Prepare cache parameters
        $cacheParams = [
            'year' => $request->input('year'),
            'week_number' => $request->input('week_number'),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ];

        $cacheFilters = [
            'work_status' => $request->input('work_status', ''),
            'region' => $request->input('region', ''),
            'search' => $request->input('search', ''),
            'sort_by' => $request->input('sort_by', 'performance'),
            'sort_order' => $request->input('sort_order', 'desc'),
        ];

        // Check if force reload is requested
        $forceReload = $request->boolean('force_reload');

        // Try to get cached data first (unless force reload)
        if (! $forceReload) {
            $cachedData = getCachedReportData('weekly', $cacheParams, $cacheFilters);

            if ($cachedData !== null && isset($cachedData['data'])) {
                Log::info('Weekly report served from cache', [
                    'cache_key' => generateReportCacheKey('weekly', $cacheParams, $cacheFilters),
                    'cached_at' => $cachedData['cached_at'] ?? 'unknown',
                ]);

                return response()->json([
                    'success' => true,
                    'cached' => true,
                    'cached_at' => $cachedData['cached_at'] ?? null,
                    ...$cachedData['data'],
                ]);
            }
        }

        // Generate fresh data
        $reportData = $this->generateWeeklyReportData($request, $startDate, $endDate);

        // Cache the data (30 minutes TTL for weekly reports)
        $wrappedData = wrapDataWithCacheInfo($reportData);
        setCachedReportData('weekly', $cacheParams, $cacheFilters, $wrappedData, 30);

        Log::info('Weekly report generated fresh', [
            'cache_key' => generateReportCacheKey('weekly', $cacheParams, $cacheFilters),
            'users_count' => count($reportData['performance_data'] ?? []),
        ]);

        return response()->json([
            'success' => true,
            'cached' => false,
            'generated_at' => now()->toISOString(),
            ...$reportData,
        ]);
    }

    /**
     * Clear weekly report cache
     */
    public function clearWeeklyReportCache(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cache_type' => 'nullable|string|in:all,weekly,daily,monthly',
            'year' => 'nullable|integer',
            'week_number' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $cacheType = $request->input('cache_type', 'weekly');
        $params = [];

        if ($request->filled('year')) {
            $params['year'] = $request->input('year');
        }
        if ($request->filled('week_number')) {
            $params['week_number'] = $request->input('week_number');
        }

        $cleared = clearReportCache($cacheType, $params);

        return response()->json([
            'success' => $cleared,
            'message' => $cleared ? 'Cache cleared successfully' : 'Failed to clear cache',
            'cache_type' => $cacheType,
            'cleared_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get cache information for debugging
     */
    public function getCacheInfo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer',
            'week_number' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $cacheParams = [
            'year' => $request->input('year'),
            'week_number' => $request->input('week_number'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
        ];

        $cacheFilters = [
            'work_status' => $request->input('work_status', ''),
            'region' => $request->input('region', ''),
            'search' => $request->input('search', ''),
            'sort_by' => $request->input('sort_by', 'performance'),
            'sort_order' => $request->input('sort_order', 'desc'),
        ];

        $cacheInfo = getReportCacheInfo('weekly', $cacheParams, $cacheFilters);

        return response()->json([
            'success' => true,
            'cache_info' => $cacheInfo,
        ]);
    }

    /**
     * Generate weekly report data (extracted for caching)
     */
    private function generateWeeklyReportData(Request $request, Carbon $startDate, Carbon $endDate)
    {
        // Check if current user should be filtered by region
        $managerRegionFilter = getManagerRegionFilter($request->user());

        // Build user query with optimized loading
        $usersQuery = IvaUser::select([
            'id',
            'full_name',
            'email',
            'job_title',
            'work_status',
            'region_id',
            'cohort_id',
            'timedoctor_version',
            'hire_date',
            'end_date',
        ])
            ->with(['region:id,name', 'cohort:id,name'])
            ->where('is_active', true);

        // Apply region filter for managers with view_team_data only
        if ($managerRegionFilter) {
            $usersQuery->where('region_id', $managerRegionFilter);
        }

        // Apply work status filter
        if ($request->filled('work_status')) {
            $usersQuery->where('work_status', $request->input('work_status'));
        }

        // Apply region filter (only if not already filtered by manager region)
        if (!$managerRegionFilter && $request->filled('region')) {
            $usersQuery->whereHas('region', function ($q) use ($request) {
                $q->where('name', $request->input('region'));
            });
        }

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $usersQuery->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Get users
        $users = $usersQuery->get();

        // Get task categories mapping
        $taskCategories = $this->getTaskCategoriesMapping();

        // Batch load all worklogs for the week with categorization
        $userIds = $users->pluck('id')->toArray();

        $worklogsData = $this->getBatchOptimizedWorklogData(
            $userIds,
            $startDate->format('Y-m-d 00:00:00'),
            $endDate->format('Y-m-d 23:59:59'),
            $taskCategories
        );

        // Process user performance data
        $performanceData = [];
        $totalBillableHours = 0;
        $totalNonBillableHours = 0;
        $totalUncategorizedHours = 0;
        $totalTargetHours = 0;

        foreach ($users as $user) {
            $userId = $user->id;
            $userWorklogs = $worklogsData->get($userId, collect());

            // Calculate hours by category
            $billableHours = 0;
            $nonBillableHours = 0;
            $uncategorizedHours = 0;

            foreach ($userWorklogs as $worklog) {
                $hours = $worklog->duration / 3600;

                switch ($worklog->worklog_category) {
                    case 'billable':
                        $billableHours += $hours;
                        break;
                    case 'non_billable':
                        $nonBillableHours += $hours;
                        break;
                    default:
                        $uncategorizedHours += $hours;
                        break;
                }
            }

            $totalHours = $billableHours + $nonBillableHours + $uncategorizedHours;

            // Calculate performance using existing helper functions
            $workStatusChanges = getWorkStatusChanges($user, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'));
            $performances = calculatePerformanceMetrics(
                $user,
                $userWorklogs,
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d'),
                $workStatusChanges
            );

            // Get the primary performance (first one or default)
            $primaryPerformance = ! empty($performances) ? $performances[0] : null;
            $targetHours = $primaryPerformance ? $primaryPerformance['target_total_hours'] : 0;
            $performancePercentage = $primaryPerformance ? $primaryPerformance['percentage'] : 0;

            // Check if user has adjusted start date
            $adjustedDateInfo = ivaAdjustStartDate($user, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'), true);
            $hasAdjustedDate = $adjustedDateInfo['changed_start_date'];

            // Format performance display (e.g., "5%(0h 50m / 35h)")
            $actualHoursDisplay = $this->formatHoursDisplay($billableHours);
            $targetHoursDisplay = $this->formatHoursDisplay($targetHours);
            $performanceDisplay = "{$performancePercentage}% ({$actualHoursDisplay} / {$targetHoursDisplay})";

            $userData = [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'job_title' => $user->job_title,
                'work_status' => $user->work_status,
                'region' => $user->region ? $user->region->name : null,
                'cohort' => $user->cohort ? $user->cohort->name : null,
                'timedoctor_version' => $user->timedoctor_version,
                'billable_hours' => round($billableHours, 2),
                'non_billable_hours' => round($nonBillableHours, 2),
                'uncategorized_hours' => round($uncategorizedHours, 2),
                'total_hours' => round($totalHours, 2),
                'target_hours' => round($targetHours, 2),
                'performance_percentage' => round($performancePercentage, 1),
                'performance_display' => $performanceDisplay,
                'performance_status' => $primaryPerformance ? $primaryPerformance['status'] : 'POOR',
                'entries_count' => $userWorklogs->count(),
                'has_data' => $totalHours > 0,
                'has_adjusted_date' => $hasAdjustedDate,
                'adjusted_start_date' => $hasAdjustedDate ? $adjustedDateInfo['adjusted_start_date'] : null,
                'adjustment_message' => $hasAdjustedDate ? $adjustedDateInfo['adjustment_message'] : null,
                'hire_date' => $user->hire_date ? $user->hire_date->format('Y-m-d') : null,
                'end_date' => $user->end_date ? $user->end_date->format('Y-m-d') : null,
            ];

            $performanceData[] = $userData;

            // Add to totals
            $totalBillableHours += $billableHours;
            $totalNonBillableHours += $nonBillableHours;
            $totalUncategorizedHours += $uncategorizedHours;
            $totalTargetHours += $targetHours;
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'performance');
        $sortOrder = $request->input('sort_order', 'desc');

        $performanceData = $this->sortWeeklyPerformanceData($performanceData, $sortBy, $sortOrder);

        // Get filter options
        $workStatusOptions = DB::table('configuration_settings as cs')
            ->join('configuration_settings_type as cst', 'cs.setting_type_id', '=', 'cst.id')
            ->where('cst.key', 'work_status')
            ->where('cs.is_active', true)
            ->orderBy('cs.order')
            ->select('cs.setting_value', 'cs.description')
            ->get()
            ->map(function ($item) {
                return [
                    'value' => $item->setting_value,
                    'description' => $item->description,
                ];
            })
            ->toArray();

        // Get region options (filtered and locked if manager has view_team_data only)
        $regionOptionsQuery = DB::table('regions')
            ->where('is_active', true)
            ->orderBy('name');

        if ($managerRegionFilter) {
            $regionOptionsQuery->where('id', $managerRegionFilter);
        }

        $regionOptions = $regionOptionsQuery->pluck('name')->toArray();

        // Calculate summary statistics
        $overallPerformancePercentage = $totalTargetHours > 0 ? round(($totalBillableHours / $totalTargetHours) * 100, 1) : 0;
        $overallPerformanceDisplay = "{$overallPerformancePercentage}% ({$this->formatHoursDisplay($totalBillableHours)} / {$this->formatHoursDisplay($totalTargetHours)})";

        $summary = [
            'total_users' => count($performanceData),
            'total_billable_hours' => round($totalBillableHours, 2),
            'total_non_billable_hours' => round($totalNonBillableHours, 2),
            'total_uncategorized_hours' => round($totalUncategorizedHours, 2),
            'total_hours' => round($totalBillableHours + $totalNonBillableHours + $totalUncategorizedHours, 2),
            'total_target_hours' => round($totalTargetHours, 2),
            'overall_performance_percentage' => $overallPerformancePercentage,
            'overall_performance_display' => $overallPerformanceDisplay,
            'users_with_data' => collect($performanceData)->where('total_hours', '>', 0)->count(),
            'users_without_data' => collect($performanceData)->where('total_hours', '=', 0)->count(),
            'users_excellent_performance' => collect($performanceData)->where('performance_status', 'EXCEEDED')->count(),
            'users_warning_performance' => collect($performanceData)->where('performance_status', 'MEET')->count(),
            'users_poor_performance' => collect($performanceData)->where('performance_status', 'BELOW')->count(),
        ];

        return [
            'year' => $request->input('year'),
            'week_number' => $request->input('week_number'),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'performance_data' => $performanceData,
            'summary' => $summary,
            'work_status_options' => $workStatusOptions,
            'region_options' => $regionOptions,
            'region_filter' => $managerRegionFilter ? [
                'applied' => true,
                'region_id' => $managerRegionFilter,
                'locked' => true,
                'reason' => 'view_team_data_permission'
            ] : ['applied' => false, 'locked' => false],
        ];
    }

    /**
     * Get task categories mapping (reused from IvaDailyReportController)
     */
    private function getTaskCategoriesMapping()
    {
        $categoryMapping = DB::table('task_report_categories as trc')
            ->join('report_categories as rc', 'trc.cat_id', '=', 'rc.id')
            ->join('configuration_settings as cs', 'rc.category_type', '=', 'cs.id')
            ->where('rc.is_active', true)
            ->select([
                'trc.task_id',
                'rc.cat_name',
                'cs.setting_value as category_type',
            ])
            ->get();

        $billableTaskIds = [];
        $nonBillableTaskIds = [];
        $fullMapping = [];

        foreach ($categoryMapping as $mapping) {
            $taskId = $mapping->task_id;

            if (! isset($fullMapping[$taskId])) {
                $fullMapping[$taskId] = collect();
            }
            $fullMapping[$taskId]->push($mapping);

            if (stripos($mapping->category_type, 'billable') === 0) {
                $billableTaskIds[] = $taskId;
            } elseif (stripos($mapping->category_type, 'non-billable') !== false) {
                $nonBillableTaskIds[] = $taskId;
            }
        }

        return [
            'billable_task_ids' => array_unique($billableTaskIds),
            'non_billable_task_ids' => array_unique($nonBillableTaskIds),
            'full_mapping' => collect($fullMapping),
        ];
    }

    /**
     * Get batch optimized worklog data for multiple users (reused from IvaDailyReportController)
     */
    private function getBatchOptimizedWorklogData($userIds, $startDateTime, $endDateTime, $taskCategories)
    {
        if (empty($userIds)) {
            return collect();
        }

        // Create case statement for categorization
        $billableIds = implode(',', array_merge([0], $taskCategories['billable_task_ids']));
        $nonBillableIds = implode(',', array_merge([0], $taskCategories['non_billable_task_ids']));

        $worklogs = WorklogsData::select([
            'id',
            'iva_id',
            'task_id',
            'start_time',
            'end_time',
            'duration',
            'comment',
            DB::raw("CASE
                WHEN task_id IN ({$billableIds}) THEN 'billable'
                WHEN task_id IN ({$nonBillableIds}) THEN 'non_billable'
                ELSE 'uncategorized'
            END as worklog_category"),
        ])
            ->whereIn('iva_id', $userIds)
            ->where('is_active', true)
            ->whereBetween('start_time', [$startDateTime, $endDateTime])
            ->get();

        // Group by user ID
        return $worklogs->groupBy('iva_id');
    }

    /**
     * Sort weekly performance data
     */
    private function sortWeeklyPerformanceData($data, $sortBy, $sortOrder)
    {
        $collection = collect($data);

        switch ($sortBy) {
            case 'billable':
                $sorted = $collection->sortBy('billable_hours', SORT_REGULAR, $sortOrder === 'desc');
                break;
            case 'non_billable':
                $sorted = $collection->sortBy('non_billable_hours', SORT_REGULAR, $sortOrder === 'desc');
                break;
            case 'uncategorized':
                $sorted = $collection->sortBy('uncategorized_hours', SORT_REGULAR, $sortOrder === 'desc');
                break;
            case 'total':
                $sorted = $collection->sortBy('total_hours', SORT_REGULAR, $sortOrder === 'desc');
                break;
            case 'performance':
                $sorted = $collection->sortBy('performance_percentage', SORT_REGULAR, $sortOrder === 'desc');
                break;
            case 'name':
            default:
                $sorted = $collection->sortBy('full_name', SORT_REGULAR, $sortOrder === 'desc');
                break;
        }

        return $sorted->values()->toArray();
    }

    /**
     * Format hours for display (e.g., "8h 30m")
     */
    private function formatHoursDisplay($hours)
    {
        if ($hours == 0) {
            return '0h';
        }

        $wholeHours = floor($hours);
        $minutes = round(($hours - $wholeHours) * 60);

        if ($minutes == 0) {
            return "{$wholeHours}h";
        } elseif ($wholeHours == 0) {
            return "{$minutes}m";
        } else {
            return "{$wholeHours}h {$minutes}m";
        }
    }
}
