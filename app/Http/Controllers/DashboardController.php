<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard overview data
     */
    public function getDashboardOverview(Request $request)
    {
        $cacheKey = 'dashboard_overview_'.auth()->id();

        // Try to get cached data (5 minute TTL)
        $cachedData = Cache::get($cacheKey);
        if ($cachedData !== null) {
            return response()->json([
                'success' => true,
                'cached' => true,
                'cached_at' => $cachedData['cached_at'] ?? null,
                ...$cachedData['data'],
            ]);
        }

        // Generate fresh data
        $dashboardData = $this->generateDashboardData();

        // Cache the data
        $wrappedData = [
            'data' => $dashboardData,
            'cached_at' => now()->toISOString(),
        ];
        Cache::put($cacheKey, $wrappedData, 300); // 5 minutes

        return response()->json([
            'success' => true,
            'cached' => false,
            'generated_at' => now()->toISOString(),
            ...$dashboardData,
        ]);
    }

    /**
     * Generate dashboard data
     */
    private function generateDashboardData()
    {
        // Get current week date range (Monday to Sunday)
        $now = Carbon::now();
        $startOfWeek = $now->clone()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = $now->clone()->endOfWeek(Carbon::SUNDAY);

        // Get current month date range
        $startOfMonth = $now->clone()->startOfMonth();
        $endOfMonth = $now->clone()->endOfMonth();

        return [
            // System overview
            'system_overview' => $this->getSystemOverview(),

            // Current week performance
            'current_week_performance' => $this->getCurrentWeekPerformance($startOfWeek, $endOfWeek),

            // Current month performance
            'current_month_performance' => $this->getCurrentMonthPerformance($startOfMonth, $endOfMonth),

            // Regional breakdown
            'regional_breakdown' => $this->getRegionalBreakdown(),

            // Cohort breakdown
            'cohort_breakdown' => $this->getCohortBreakdown(),

            // Recent activity
            'recent_activity' => $this->getRecentActivity(),

            // Top performers (last 3 days)
            'top_performers' => $this->getTopPerformers(),

            // Performance trends (last 4 weeks)
            'performance_trends' => $this->getPerformanceTrends(),
        ];
    }

    /**
     * Get system overview metrics
     */
    private function getSystemOverview()
    {
        // Use the correct table name 'iva_user' from migrations
        $totalUsers = DB::table('iva_user')->where('is_active', true)->count();
        $fullTimeUsers = DB::table('iva_user')->where('is_active', true)->where('work_status', 'full-time')->count();
        $partTimeUsers = DB::table('iva_user')->where('is_active', true)->where('work_status', 'part-time')->count();

        $totalRegions = DB::table('regions')->where('is_active', true)->count();
        $totalCohorts = DB::table('cohorts')->where('is_active', true)->count();

        // Use correct table names from migrations
        $totalTasks = DB::table('tasks')->where('is_active', true)->count();
        $totalProjects = DB::table('projects')->where('is_active', true)->count();

        // Task categories - use exact matching for category types
        $billableCategories = DB::table('report_categories as rc')
            ->join('configuration_settings as cs', 'rc.category_type', '=', 'cs.id')
            ->where('rc.is_active', true)
            ->where('cs.setting_value', 'billable')
            ->count();

        $nonBillableCategories = DB::table('report_categories as rc')
            ->join('configuration_settings as cs', 'rc.category_type', '=', 'cs.id')
            ->where('rc.is_active', true)
            ->where('cs.setting_value', 'non-billable')
            ->count();

        return [
            'total_users' => $totalUsers,
            'full_time_users' => $fullTimeUsers,
            'part_time_users' => $partTimeUsers,
            'total_regions' => $totalRegions,
            'total_cohorts' => $totalCohorts,
            'total_tasks' => $totalTasks,
            'total_projects' => $totalProjects,
            'billable_categories' => $billableCategories,
            'non_billable_categories' => $nonBillableCategories,
        ];
    }

    /**
     * Get current week performance
     */
    private function getCurrentWeekPerformance($startDate, $endDate)
    {
        $taskCategories = $this->getTaskCategoriesMapping();

        // Use correct table name from migrations
        $activeUsers = DB::table('iva_user')
            ->where('is_active', true)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where(function ($q) use ($startDate) {
                    $q->whereNull('hire_date')
                        ->orWhere('hire_date', '<=', $startDate);
                })
                    ->where(function ($q) use ($endDate) {
                        $q->whereNull('end_date')
                            ->orWhere('end_date', '>=', $endDate);
                    });
            })
            ->count();

        // Get worklog data for current week
        $worklogSummary = $this->getWorklogSummary($startDate->format('Y-m-d'), $endDate->format('Y-m-d'), $taskCategories);

        // Get NAD data
        $nadData = $this->getNADDataForPeriod($startDate->format('Y-m-d'), $endDate->format('Y-m-d'));

        return [
            'active_users' => $activeUsers,
            'billable_hours' => $worklogSummary['billable_hours'],
            'non_billable_hours' => $worklogSummary['non_billable_hours'],
            'total_hours' => $worklogSummary['total_hours'],
            'total_entries' => $worklogSummary['total_entries'],
            'nad_count' => $nadData['nad_count'],
            'nad_hours' => $nadData['nad_hours'],
        ];
    }

    /**
     * Get current month performance
     */
    private function getCurrentMonthPerformance($startDate, $endDate)
    {
        $taskCategories = $this->getTaskCategoriesMapping();

        // Get worklog data for current month
        $worklogSummary = $this->getWorklogSummary($startDate->format('Y-m-d'), $endDate->format('Y-m-d'), $taskCategories);

        // Get NAD data
        $nadData = $this->getNADDataForPeriod($startDate->format('Y-m-d'), $endDate->format('Y-m-d'));

        return [
            'billable_hours' => $worklogSummary['billable_hours'],
            'non_billable_hours' => $worklogSummary['non_billable_hours'],
            'total_hours' => $worklogSummary['total_hours'],
            'total_entries' => $worklogSummary['total_entries'],
            'nad_count' => $nadData['nad_count'],
            'nad_hours' => $nadData['nad_hours'],
        ];
    }

    /**
     * Get regional breakdown
     */
    private function getRegionalBreakdown()
    {
        return DB::table('regions as r')
            ->leftJoin('iva_user as iu', function ($join) {
                $join->on('r.id', '=', 'iu.region_id')
                    ->where('iu.is_active', true);
            })
            ->where('r.is_active', true)
            ->select([
                'r.id',
                'r.name',
                'r.description',
                DB::raw('COUNT(iu.id) as user_count'),
                DB::raw('SUM(CASE WHEN iu.work_status = "full-time" THEN 1 ELSE 0 END) as full_time_count'),
                DB::raw('SUM(CASE WHEN iu.work_status = "part-time" THEN 1 ELSE 0 END) as part_time_count'),
            ])
            ->groupBy('r.id', 'r.name', 'r.description')
            ->orderBy('user_count', 'desc')
            ->get()
            ->map(function ($region) {
                return [
                    'id' => $region->id,
                    'name' => $region->name,
                    'description' => $region->description,
                    'user_count' => (int) $region->user_count,
                    'full_time_count' => (int) $region->full_time_count,
                    'part_time_count' => (int) $region->part_time_count,
                ];
            });
    }

    /**
     * Get cohort breakdown
     */
    private function getCohortBreakdown()
    {
        return DB::table('cohorts as c')
            ->leftJoin('iva_user as iu', function ($join) {
                $join->on('c.id', '=', 'iu.cohort_id')
                    ->where('iu.is_active', true);
            })
            ->where('c.is_active', true)
            ->select([
                'c.id',
                'c.name',
                'c.description',
                'c.start_date',
                DB::raw('COUNT(iu.id) as user_count'),
                DB::raw('SUM(CASE WHEN iu.work_status = "full-time" THEN 1 ELSE 0 END) as full_time_count'),
                DB::raw('SUM(CASE WHEN iu.work_status = "part-time" THEN 1 ELSE 0 END) as part_time_count'),
            ])
            ->groupBy('c.id', 'c.name', 'c.description', 'c.start_date')
            ->orderBy('user_count', 'desc')
            ->get()
            ->map(function ($cohort) {
                return [
                    'id' => $cohort->id,
                    'name' => $cohort->name,
                    'description' => $cohort->description,
                    'start_date' => $cohort->start_date,
                    'user_count' => (int) $cohort->user_count,
                    'full_time_count' => (int) $cohort->full_time_count,
                    'part_time_count' => (int) $cohort->part_time_count,
                ];
            });
    }

    /**
     * Get categories performance for current week
     */
    private function getCategoriesPerformance($startDate, $endDate)
    {
        $taskCategories = $this->getTaskCategoriesMapping();

        if (empty($taskCategories['billable_task_ids'])) {
            return [];
        }

        $billableIds = implode(',', array_merge([0], $taskCategories['billable_task_ids']));

        return DB::table('worklogs_data as wd')
            ->join('task_report_categories as trc', 'wd.task_id', '=', 'trc.task_id')
            ->join('report_categories as rc', 'trc.cat_id', '=', 'rc.id')
            ->where('wd.is_active', true)
            ->whereBetween('wd.start_time', [$startDate->format('Y-m-d').' 00:00:00', $endDate->format('Y-m-d').' 23:59:59'])
            ->whereRaw("wd.task_id IN ({$billableIds})")
            ->select([
                'rc.id as category_id',
                'rc.cat_name as category_name',
                DB::raw('SUM(wd.duration) / 3600 as total_hours'),
                DB::raw('COUNT(DISTINCT wd.iva_id) as user_count'),
                DB::raw('COUNT(wd.id) as entry_count'),
            ])
            ->groupBy('rc.id', 'rc.cat_name')
            ->orderBy('total_hours', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($category) {
                return [
                    'category_id' => $category->category_id,
                    'category_name' => $category->category_name,
                    'total_hours' => round((float) $category->total_hours, 2),
                    'user_count' => (int) $category->user_count,
                    'entry_count' => (int) $category->entry_count,
                    'avg_hours_per_user' => $category->user_count > 0 ? round($category->total_hours / $category->user_count, 2) : 0,
                ];
            })
            ->toArray();
    }

    /**
     * Get recent activity (latest worklogs)
     */
    private function getRecentActivity()
    {
        return DB::table('worklogs_data as wd')
            ->join('iva_user as iu', 'wd.iva_id', '=', 'iu.id')
            ->leftJoin('tasks as t', 'wd.task_id', '=', 't.id')
            ->leftJoin('projects as p', 'wd.project_id', '=', 'p.id')
            ->leftJoin('regions as r', 'iu.region_id', '=', 'r.id')
            ->where('wd.is_active', true)
            ->where('wd.start_time', '>=', Carbon::now()->subDays(7))
            ->select([
                'wd.id',
                'wd.start_time',
                'wd.duration',
                'iu.id as user_id',
                'iu.full_name as user_name',
                'iu.work_status',
                'r.name as region_name',
                't.task_name as task_name',
                'p.project_name as project_name',
                DB::raw('wd.duration / 3600 as hours'),
            ])
            ->orderBy('wd.duration', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'start_time' => $activity->start_time,
                    'duration' => (int) $activity->duration,
                    'hours' => round((float) $activity->hours, 2),
                    'user_id' => $activity->user_id,
                    'user_name' => $activity->user_name,
                    'work_status' => $activity->work_status,
                    'region_name' => $activity->region_name,
                    'task_name' => $activity->task_name ?? 'Unknown Task',
                    'project_name' => $activity->project_name ?? 'Unknown Project',
                ];
            });
    }

    /**
     * Get top performers (highest hours in last 3 days)
     */
    private function getTopPerformers()
    {
        return DB::table('worklogs_data as wd')
            ->join('iva_user as iu', 'wd.iva_id', '=', 'iu.id')
            ->leftJoin('regions as r', 'iu.region_id', '=', 'r.id')
            ->where('wd.is_active', true)
            ->where('wd.start_time', '>=', Carbon::now()->subDays(3))
            ->select([
                'iu.id as user_id',
                'iu.full_name as user_name',
                'iu.work_status',
                'r.name as region_name',
                DB::raw('SUM(wd.duration) / 3600 as total_hours'),
                DB::raw('COUNT(wd.id) as total_entries'),
                DB::raw('AVG(wd.duration) / 3600 as avg_hours_per_entry'),
            ])
            ->groupBy('iu.id', 'iu.full_name', 'iu.work_status', 'r.name')
            ->orderBy('total_hours', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($performer) {
                return [
                    'user_id' => $performer->user_id,
                    'user_name' => $performer->user_name,
                    'work_status' => $performer->work_status,
                    'region_name' => $performer->region_name,
                    'total_hours' => round((float) $performer->total_hours, 2),
                    'total_entries' => (int) $performer->total_entries,
                    'avg_hours_per_entry' => round((float) $performer->avg_hours_per_entry, 2),
                ];
            });
    }

    /**
     * Get performance trends for last 4 weeks
     */
    private function getPerformanceTrends()
    {
        $taskCategories = $this->getTaskCategoriesMapping();
        $trends = [];

        for ($i = 3; $i >= 0; $i--) {
            $weekStart = Carbon::now()->subWeeks($i)->startOfWeek();
            $weekEnd = Carbon::now()->subWeeks($i)->endOfWeek();

            $weekData = $this->getWorklogSummary($weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d'), $taskCategories);

            $trends[] = [
                'week_start' => $weekStart->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'week_label' => $weekStart->format('M d').' - '.$weekEnd->format('M d'),
                'billable_hours' => $weekData['billable_hours'],
                'non_billable_hours' => $weekData['non_billable_hours'],
                'total_hours' => $weekData['total_hours'],
                'total_entries' => $weekData['total_entries'],
            ];
        }

        return $trends;
    }

    /**
     * Helper methods
     */
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

        $billableTaskIds = [];
        $nonBillableTaskIds = [];

        foreach ($mappingData as $mapping) {
            if (stripos($mapping->category_type, 'billable') !== false) {
                $billableTaskIds[] = $mapping->task_id;
            } elseif (stripos($mapping->category_type, 'non-billable') !== false) {
                $nonBillableTaskIds[] = $mapping->task_id;
            }
        }

        return [
            'billable_task_ids' => array_unique($billableTaskIds),
            'non_billable_task_ids' => array_unique($nonBillableTaskIds),
        ];
    }

    private function getWorklogSummary($startDate, $endDate, $taskCategories)
    {
        // Handle case where no task categories are defined yet
        if (empty($taskCategories['billable_task_ids']) && empty($taskCategories['non_billable_task_ids'])) {
            // Just get total hours without categorization
            $result = DB::table('worklogs_data')
                ->select([
                    DB::raw('0 as billable_hours'),
                    DB::raw('0 as non_billable_hours'),
                    DB::raw('SUM(duration) / 3600 as total_hours'),
                    DB::raw('COUNT(*) as total_entries'),
                ])
                ->where('is_active', true)
                ->whereBetween('start_time', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                ->first();
        } else {
            $billableIds = $taskCategories['billable_task_ids'] ?? [];
            $nonBillableIds = $taskCategories['non_billable_task_ids'] ?? [];

            if (empty($billableIds) && empty($nonBillableIds)) {
                // No categorization available
                $result = DB::table('worklogs_data')
                    ->where('is_active', true)
                    ->whereBetween('start_time', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                    ->select([
                        DB::raw('0 as billable_hours'),
                        DB::raw('0 as non_billable_hours'),
                        DB::raw('SUM(duration) / 3600 as total_hours'),
                        DB::raw('COUNT(*) as total_entries'),
                    ])->first();
            } else {
                $result = DB::table('worklogs_data')
                    ->where('is_active', true)
                    ->whereBetween('start_time', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                    ->select([
                        // Billable hours - only tasks explicitly marked as billable
                        DB::raw(empty($billableIds) ? '0 as billable_hours' :
                            'COALESCE(SUM(CASE WHEN task_id IN ('.implode(',', $billableIds).') THEN duration END), 0) / 3600 as billable_hours'
                        ),
                        // Non-billable hours - only tasks explicitly marked as non-billable
                        DB::raw(empty($nonBillableIds) ? '0 as non_billable_hours' :
                            'COALESCE(SUM(CASE WHEN task_id IN ('.implode(',', $nonBillableIds).') THEN duration END), 0) / 3600 as non_billable_hours'
                        ),
                        // Total hours - all tasks
                        DB::raw('COALESCE(SUM(duration), 0) / 3600 as total_hours'),
                        // Total entries
                        DB::raw('COUNT(*) as total_entries'),
                    ])
                    ->first();
            }
        }

        return [
            'billable_hours' => round((float) ($result->billable_hours ?? 0), 2),
            'non_billable_hours' => round((float) ($result->non_billable_hours ?? 0), 2),
            'total_hours' => round((float) ($result->total_hours ?? 0), 2),
            'total_entries' => (int) ($result->total_entries ?? 0),
        ];
    }

    private function getNADDataForPeriod($startDate, $endDate)
    {
        $nadHourRate = config('services.nad.nad_hour_rate.rate', 8);

        $nadData = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'blab_only' => 1,
            'email_list' => [],
        ];

        $nadResponse = callNADApi('get_nad_by_date_range', $nadData);
        $nadCount = 0;
        $nadHours = 0;

        if (! empty($nadResponse['status']) && $nadResponse['status'] === true && ! empty($nadResponse['data'])) {

            $nadCount = $nadResponse['data']['nad_count'] ?? 0;
            $nadHours = $nadCount * $nadHourRate;
        }

        return [
            'nad_count' => $nadCount,
            'nad_hours' => round($nadHours, 2),
        ];

    }

    /**
     * Clear dashboard cache
     */
    public function clearDashboardCache()
    {
        $cacheKey = 'dashboard_overview_'.auth()->id();
        Cache::forget($cacheKey);

        return response()->json([
            'success' => true,
            'message' => 'Dashboard cache cleared successfully',
        ]);
    }
}
