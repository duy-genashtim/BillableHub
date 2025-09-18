<?php

namespace App\Http\Controllers;

use App\Models\IvaUser;
use App\Models\WorklogsData;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class IvaDailyReportController extends Controller
{
    /**
     * Get daily performance report for all active IVA users
     */
    public function getDailyPerformanceReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
            'work_status' => 'nullable|string',
            'region' => 'nullable|string',
            'search' => 'nullable|string',
            'sort_by' => 'nullable|string|in:name,billable,non_billable,uncategorized,total',
            'sort_order' => 'nullable|string|in:asc,desc',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Get date (default to yesterday)
        $date = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::yesterday();
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

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

        // Apply work status filter
        if ($request->filled('work_status')) {
            $usersQuery->where('work_status', $request->input('work_status'));
        }

        // Apply region filter
        if ($request->filled('region')) {
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

        // Batch load all worklogs for the day with categorization
        $userIds = $users->pluck('id')->toArray();

        $worklogsData = $this->getBatchOptimizedWorklogData(
            $userIds,
            $startOfDay->format('Y-m-d H:i:s'),
            $endOfDay->format('Y-m-d H:i:s'),
            $taskCategories
        );

        // Process user performance data
        $performanceData = [];

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

            // Check adjusted start date for performance calculation
            $adjustedDateInfo = ivaAdjustStartDate($user, $date->format('Y-m-d'), $date->format('Y-m-d'), false);
            $hasData = $adjustedDateInfo['adjusted_start_date'];

            $performanceData[] = [
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
                'entries_count' => $userWorklogs->count(),
                'has_data' => $hasData,
                'hire_date' => $user->hire_date ? $user->hire_date->format('Y-m-d') : null,
                'end_date' => $user->end_date ? $user->end_date->format('Y-m-d') : null,
            ];
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'name');
        $sortOrder = $request->input('sort_order', 'asc');

        $performanceData = $this->sortPerformanceData($performanceData, $sortBy, $sortOrder);

        // Get work status options
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

        // Get region options
        $regionOptions = DB::table('regions')
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->toArray();

        // Calculate summary statistics
        $summary = [
            'total_users' => count($performanceData),
            'total_billable_hours' => collect($performanceData)->sum('billable_hours'),
            'total_non_billable_hours' => collect($performanceData)->sum('non_billable_hours'),
            'total_uncategorized_hours' => collect($performanceData)->sum('uncategorized_hours'),
            'total_hours' => collect($performanceData)->sum('total_hours'),
            'users_with_data' => collect($performanceData)->where('total_hours', '>', 0)->count(),
            'users_without_data' => collect($performanceData)->where('total_hours', '=', 0)->count(),
        ];

        return response()->json([
            'success' => true,
            'date' => $date->format('Y-m-d'),
            'is_yesterday' => $date->isYesterday(),
            'performance_data' => $performanceData,
            'summary' => $summary,
            'work_status_options' => $workStatusOptions,
            'region_options' => $regionOptions,
        ]);
    }

    /**
     * Get task categories mapping (reused from WorklogDashboardController)
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
     * Get batch optimized worklog data for multiple users
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
     * Sort performance data
     */
    private function sortPerformanceData($data, $sortBy, $sortOrder)
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
            case 'name':
            default:
                $sorted = $collection->sortBy('full_name', SORT_REGULAR, $sortOrder === 'desc');
                break;
        }

        return $sorted->values()->toArray();
    }
}
