<?php
namespace App\Http\Controllers;

use App\Models\IvaUser;
use App\Models\WorklogsData;
use App\Services\ActivityLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Pre-process task categories for performance optimization
        $taskCategories = $this->getTaskCategoriesMapping();

        // Get optimized worklog data with categorization
        $worklogData = $this->getOptimizedWorklogData($id, $startDate, $endDate, $taskCategories);
        // dd($worklogData); // Debugging line, remove in production
        // Get work status changes during the period to handle performance calculations
        $workStatusChanges = getWorkStatusChanges($user, $startDate, $endDate);
        // dd($workStatusChanges);
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
                $endDate,
                $taskCategories
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

            $dashboardData['weekly_summary_data'] = $this->calculateOptimizedWeeklySummaryData(
                $user,
                $worklogData,
                $startDate,
                $endDate,
                $request->input('year'),
                $request->input('week_number'),
                $request->input('week_count', 1),
                $workStatusChanges,
                $taskCategories
            );
            $dashboardData['weekly_summary_data']['target_performances'] = calculateTargetPerformancesForUser(
                $user,
                $worklogData['all_worklogs'],
                $startDate,
                $endDate,
                $workStatusChanges
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
            $dashboardData['monthly_summary_data'] = $this->calculateOptimizedMonthlySummaryData(
                $user,
                $worklogData,
                $dateAdjustment['original_start_date'],
                $endDate,
                $request->input('year'),
                $request->input('month'),
                $request->input('month_count', 1),
                $workStatusChanges,
                $taskCategories,
                $dateAdjustment['adjusted_start_date']
            );
            $dashboardData['monthly_summary_data']['target_performances'] = calculateTargetPerformancesForUser(
                $user,
                $worklogData['all_worklogs'],
                $startDate,
                $endDate,
                $workStatusChanges
            );
        } else {
            // Regular mode (weeks, monthly, custom)
            $nadDataResult = fetchNADDataForPeriod($user, $startDate, $endDate);

            $dashboardData['basic_metrics'] = $this->calculateOptimizedBasicMetrics($worklogData);
            $dashboardData['nad_data']      = $nadDataResult['nad_data'];
            $dashboardData['date_range']    = [
                'start'               => $startDate,
                'end'                 => $endDate,
                'original_start'      => $originalStartDate,
                'start_date_adjusted' => $dateAdjustment['changed_start_date'],
                'days_count'          => $daysDiff + 1,
                'mode'                => $dateMode,
            ];
            $dashboardData['daily_breakdown']    = $this->calculateOptimizedDailyBreakdown($worklogData, $startDate, $endDate);
            $dashboardData['category_breakdown'] = $this->calculateOptimizedCategoryBreakdown($worklogData, $taskCategories);

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

                $dashboardData['target_performances'] = calculateTargetPerformancesForUser(
                    $user,
                    $worklogData['all_worklogs'],
                    $startDate,
                    $endDate,
                    $workStatusChanges
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
        $dashboardData['worklogs'] = $worklogData;

        return response()->json([
            'success'   => true,
            'dashboard' => $dashboardData,
        ]);
    }
    /**
     * Pre-process task categories for performance optimization
     */
    private function getTaskCategoriesMapping()
    {
        // Using raw SQL with JOIN for better performance
        // $billableTaskIds = DB::table('task_report_categories as trc')
        //     ->join('report_categories as rc', 'trc.cat_id', '=', 'rc.id')
        //     ->join('configuration_settings as cs', 'rc.category_type', '=', 'cs.id')
        //     ->where('cs.setting_value', 'LIKE', 'billable%')
        //     ->where('rc.is_active', true)
        //     ->pluck('trc.task_id')
        //     ->unique()
        //     ->toArray();

        // $nonBillableTaskIds = DB::table('task_report_categories as trc')
        //     ->join('report_categories as rc', 'trc.cat_id', '=', 'rc.id')
        //     ->join('configuration_settings as cs', 'rc.category_type', '=', 'cs.id')
        //     ->where('cs.setting_value', 'LIKE', '%non-billable%')
        //     ->where('rc.is_active', true)
        //     ->pluck('trc.task_id')
        //     ->unique()
        //     ->toArray();

        // // Get full category mapping for detailed breakdown
        // $fullCategoryMapping = DB::table('task_report_categories as trc')
        //     ->join('report_categories as rc', 'trc.cat_id', '=', 'rc.id')
        //     ->join('configuration_settings as cs', 'rc.category_type', '=', 'cs.id')
        //     ->join('tasks as t', 'trc.task_id', '=', 't.id')
        //     ->where('rc.is_active', true)
        //     ->select([
        //         'trc.task_id',
        //         'rc.cat_name',
        //         'cs.setting_value as category_type',
        //         't.task_name',
        //     ])
        //     ->get()
        //     ->groupBy('task_id');

        // return [
        //     'billable_task_ids'     => $billableTaskIds,
        //     'non_billable_task_ids' => $nonBillableTaskIds,
        //     'full_mapping'          => $fullCategoryMapping,
        // ];
        $categoryMapping = DB::table('task_report_categories as trc')
            ->join('report_categories as rc', 'trc.cat_id', '=', 'rc.id')
            ->join('configuration_settings as cs', 'rc.category_type', '=', 'cs.id')
            ->join('tasks as t', 'trc.task_id', '=', 't.id')
            ->where('rc.is_active', true)
            ->select([
                'trc.task_id',
                'rc.cat_name',
                'cs.setting_value as category_type',
                't.task_name',
            ])
            ->get();

        // Process in PHP instead of multiple DB queries
        $billableTaskIds    = [];
        $nonBillableTaskIds = [];
        $fullMapping        = [];

        foreach ($categoryMapping as $mapping) {
            $taskId = $mapping->task_id;

            // Build full mapping
            if (! isset($fullMapping[$taskId])) {
                $fullMapping[$taskId] = collect();
            }
            $fullMapping[$taskId]->push($mapping);

            // Categorize by type
            if (stripos($mapping->category_type, 'billable') === 0) {
                $billableTaskIds[] = $taskId;
            } elseif (stripos($mapping->category_type, 'non-billable') !== false) {
                $nonBillableTaskIds[] = $taskId;
            }
        }

        return [
            'billable_task_ids'     => array_unique($billableTaskIds),
            'non_billable_task_ids' => array_unique($nonBillableTaskIds),
            'full_mapping'          => collect($fullMapping),
        ];
    }

    /**
     * Get optimized worklog data with pre-categorization
     */
    private function getOptimizedWorklogData($userId, $startDate, $endDate, $taskCategories)
    {
        // Base query with optimized column selection and date filtering
        // $baseQuery = WorklogsData::select([
        //     'id',
        //     'task_id',
        //     'start_time',
        //     'end_time',
        //     'duration',
        //     'comment',
        // ])
        //     ->where('iva_id', $userId)
        //     ->where('is_active', true)
        //     ->whereBetween('start_time', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
        //     ->orderBy('start_time');

        // // Get billable worklogs
        // $billableWorklogs = (clone $baseQuery)
        //     ->whereIn('task_id', $taskCategories['billable_task_ids'])
        //     ->get();

        // // Get non-billable worklogs
        // $nonBillableWorklogs = (clone $baseQuery)
        //     ->whereIn('task_id', $taskCategories['non_billable_task_ids'])
        //     ->get();

        // // Get uncategorized worklogs
        // $categorizedTaskIds = array_merge(
        //     $taskCategories['billable_task_ids'],
        //     $taskCategories['non_billable_task_ids']
        // );

        // $uncategorizedWorklogs = (clone $baseQuery)
        //     ->whereNotIn('task_id', $categorizedTaskIds)
        //     ->get();

        // // Combine all worklogs for functions that need complete data
        // $allWorklogs = $billableWorklogs->concat($nonBillableWorklogs)->concat($uncategorizedWorklogs);

        // return [
        //     'billable_worklogs'      => $billableWorklogs,
        //     'non_billable_worklogs'  => $nonBillableWorklogs,
        //     'uncategorized_worklogs' => $uncategorizedWorklogs,
        //     'all_worklogs'           => $allWorklogs,
        // ];

        $allWorklogs = WorklogsData::select([
            'id',
            'task_id',
            'start_time',
            'end_time',
            'duration',
            'comment',
            // Add case statement to categorize in SQL
            DB::raw("CASE
            WHEN task_id IN (" . implode(',', array_merge([0], $taskCategories['billable_task_ids'])) . ") THEN 'billable'
            WHEN task_id IN (" . implode(',', array_merge([0], $taskCategories['non_billable_task_ids'])) . ") THEN 'non_billable'
            ELSE 'uncategorized'
        END as worklog_category"),
        ])
            ->where('iva_id', $userId)
            ->where('is_active', true)
            ->whereBetween('start_time', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->orderBy('start_time')
            ->get();

        // Group in PHP (faster than separate DB queries)
        $categorized = $allWorklogs->groupBy('worklog_category');

        return [
            'billable_worklogs'      => $categorized->get('billable', collect()),
            'non_billable_worklogs'  => $categorized->get('non_billable', collect()),
            'uncategorized_worklogs' => $categorized->get('uncategorized', collect()),
            'all_worklogs'           => $allWorklogs,
        ];
    }

    /**
     * Calculate optimized basic metrics
     */
    private function calculateOptimizedBasicMetrics($worklogData)
    {
        $billableSeconds    = $worklogData['billable_worklogs']->sum('duration');
        $nonBillableSeconds = $worklogData['non_billable_worklogs']->sum('duration');
        $totalSeconds       = $billableSeconds + $nonBillableSeconds + $worklogData['uncategorized_worklogs']->sum('duration');

        $billableHours    = round($billableSeconds / 3600, 2);
        $nonBillableHours = round($nonBillableSeconds / 3600, 2);
        $totalHours       = round($totalSeconds / 3600, 2);

        return [
            'billable_hours'       => $billableHours,
            'non_billable_hours'   => $nonBillableHours,
            'total_hours'          => $totalHours,
            'total_entries'        => $worklogData['all_worklogs']->count(),
            'billable_entries'     => $worklogData['billable_worklogs']->count(),
            'non_billable_entries' => $worklogData['non_billable_worklogs']->count(),
        ];
    }

    /**
     * Calculate optimized daily breakdown
     */
    private function calculateOptimizedDailyBreakdown($worklogData, $startDate, $endDate)
    {
        // $dailyData   = [];
        // $currentDate = Carbon::parse($startDate);
        // $endDate     = Carbon::parse($endDate);

        // // Group worklogs by date for faster lookup
        // $billableByDate = $worklogData['billable_worklogs']->groupBy(function ($worklog) {
        //     return Carbon::parse($worklog->start_time)->toDateString();
        // });

        // $nonBillableByDate = $worklogData['non_billable_worklogs']->groupBy(function ($worklog) {
        //     return Carbon::parse($worklog->start_time)->toDateString();
        // });

        // $uncategorizedByDate = $worklogData['uncategorized_worklogs']->groupBy(function ($worklog) {
        //     return Carbon::parse($worklog->start_time)->toDateString();
        // });

        // while ($currentDate <= $endDate) {
        //     $dateString = $currentDate->toDateString();

        //     $dayBillableWorklogs      = $billableByDate->get($dateString, collect());
        //     $dayNonBillableWorklogs   = $nonBillableByDate->get($dateString, collect());
        //     $dayUncategorizedWorklogs = $uncategorizedByDate->get($dateString, collect());

        //     $billableSeconds      = $dayBillableWorklogs->sum('duration');
        //     $nonBillableSeconds   = $dayNonBillableWorklogs->sum('duration');
        //     $uncategorizedSeconds = $dayUncategorizedWorklogs->sum('duration');
        //     $totalSeconds         = $billableSeconds + $nonBillableSeconds + $uncategorizedSeconds;

        //     $billableHours    = round($billableSeconds / 3600, 2);
        //     $nonBillableHours = round($nonBillableSeconds / 3600, 2);
        //     $totalHours       = round($totalSeconds / 3600, 2);

        //     $dailyData[] = [
        //         'date'                 => $dateString,
        //         'day_name'             => $currentDate->format('l'),
        //         'day_short'            => $currentDate->format('D'),
        //         'is_weekend'           => $currentDate->isWeekend(),
        //         'billable_hours'       => $billableHours,
        //         'non_billable_hours'   => $nonBillableHours,
        //         'total_hours'          => $totalHours,
        //         'entries_count'        => $dayBillableWorklogs->count() + $dayNonBillableWorklogs->count() + $dayUncategorizedWorklogs->count(),
        //         'billable_entries'     => $dayBillableWorklogs->count(),
        //         'non_billable_entries' => $dayNonBillableWorklogs->count(),
        //     ];

        //     $currentDate->addDay();
        // }

        // return $dailyData;

        $dateRange   = [];
        $currentDate = Carbon::parse($startDate);
        $endDate     = Carbon::parse($endDate);

        while ($currentDate <= $endDate) {
            $dateString             = $currentDate->toDateString();
            $dateRange[$dateString] = [
                'date'       => $dateString,
                'day_name'   => $currentDate->format('l'),
                'day_short'  => $currentDate->format('D'),
                'is_weekend' => $currentDate->isWeekend(),
            ];
            $currentDate->addDay();
        }

        // Group all worklogs by date in one pass
        $worklogsByDate = [
            'billable'      => [],
            'non_billable'  => [],
            'uncategorized' => [],
        ];

        foreach (['billable_worklogs' => 'billable', 'non_billable_worklogs' => 'non_billable', 'uncategorized_worklogs' => 'uncategorized'] as $key => $type) {
            foreach ($worklogData[$key] as $worklog) {
                $date = Carbon::parse($worklog->start_time)->toDateString();
                if (! isset($worklogsByDate[$type][$date])) {
                    $worklogsByDate[$type][$date] = ['duration' => 0, 'count' => 0];
                }
                $worklogsByDate[$type][$date]['duration'] += $worklog->duration;
                $worklogsByDate[$type][$date]['count']++;
            }
        }

        // Build final array
        $dailyData = [];
        foreach ($dateRange as $dateString => $dateInfo) {
            $billable      = $worklogsByDate['billable'][$dateString] ?? ['duration' => 0, 'count' => 0];
            $nonBillable   = $worklogsByDate['non_billable'][$dateString] ?? ['duration' => 0, 'count' => 0];
            $uncategorized = $worklogsByDate['uncategorized'][$dateString] ?? ['duration' => 0, 'count' => 0];

            $totalSeconds = $billable['duration'] + $nonBillable['duration'] + $uncategorized['duration'];

            $dailyData[] = array_merge($dateInfo, [
                'billable_hours'       => round($billable['duration'] / 3600, 2),
                'non_billable_hours'   => round($nonBillable['duration'] / 3600, 2),
                'total_hours'          => round($totalSeconds / 3600, 2),
                'entries_count'        => $billable['count'] + $nonBillable['count'] + $uncategorized['count'],
                'billable_entries'     => $billable['count'],
                'non_billable_entries' => $nonBillable['count'],
            ]);
        }

        return $dailyData;
    }

    /**
     * Calculate optimized category breakdown
     */
    private function calculateOptimizedCategoryBreakdown($worklogData, $taskCategories)
    {
        $categoryBreakdown = [];

        // Process billable worklogs
        if ($worklogData['billable_worklogs']->count() > 0) {
            $categoryBreakdown[] = $this->processOptimizedCategoryGroup(
                $worklogData['billable_worklogs'],
                $taskCategories['full_mapping'],
                'Billable'
            );
        }

        // Process non-billable worklogs
        if ($worklogData['non_billable_worklogs']->count() > 0) {
            $categoryBreakdown[] = $this->processOptimizedCategoryGroup(
                $worklogData['non_billable_worklogs'],
                $taskCategories['full_mapping'],
                'Non-Billable'
            );
        }

        return array_filter($categoryBreakdown, function ($group) {
            return $group['total_hours'] > 0;
        });
    }

    /**
     * Process optimized category group
     */
    private function processOptimizedCategoryGroup($worklogs, $fullMapping, $type)
    {
        $categories = [];
        $totalHours = 0;

        // Group worklogs by category
        $worklogsByCategory = [];

        foreach ($worklogs as $worklog) {
            $categoryName = 'Uncategorized';
            $taskName     = 'Uncategorized Task';

            if (isset($fullMapping[$worklog->task_id]) && $fullMapping[$worklog->task_id]->isNotEmpty()) {
                $mapping      = $fullMapping[$worklog->task_id]->first();
                $categoryName = $mapping->cat_name;

                // change query on getOptimizedWorklogData to get task name.
                $taskName = $mapping->task_name;
            }

            if (! isset($worklogsByCategory[$categoryName])) {
                $worklogsByCategory[$categoryName] = [];
            }

            $worklog->task_name                  = $taskName; // Add task name for display
            $worklogsByCategory[$categoryName][] = $worklog;
        }

        // Process each category
        foreach ($worklogsByCategory as $categoryName => $categoryWorklogs) {
            if ($categoryName === 'Uncategorized') {
                continue; // Skip uncategorized for main summary
            }

            $categoryHours = collect($categoryWorklogs)->sum('duration') / 3600;
            $totalHours += $categoryHours;

            // Group by tasks within category
            $taskGroups = collect($categoryWorklogs)->groupBy('task_id');
            $tasks      = [];

            foreach ($taskGroups as $taskId => $taskWorklogs) {
                $taskHours    = collect($taskWorklogs)->sum('duration') / 3600;
                $firstWorklog = collect($taskWorklogs)->first();

                $entries = collect($taskWorklogs)->map(function ($worklog) {
                    return [
                        'id'             => $worklog->id,
                        'start_time'     => $worklog->start_time,
                        'end_time'       => $worklog->end_time,
                        'duration_hours' => round($worklog->duration / 3600, 2),
                        'comment'        => $worklog->comment,
                        'project_name'   => 'No Project', // Optimized: Not fetching project data
                    ];
                })->toArray();

                $tasks[] = [
                    'task_id'     => $taskId,
                    'task_name'   => $firstWorklog->task_name,
                    'total_hours' => round($taskHours, 2),
                    'entries'     => $entries,
                ];
            }

            $categories[] = [
                'category_name' => $categoryName,
                'total_hours'   => round($categoryHours, 2),
                'entries_count' => count($categoryWorklogs),
                'tasks'         => $tasks,
            ];
        }

        return [
            'type'        => $type,
            'total_hours' => round($totalHours, 2),
            'categories'  => $categories,
        ];
    }
    /**
     * Calculate optimized bimonthly data
     */
    private function calculateOptimizedBimonthlyData($user, $year, $month, $splitDate, $adjustedStartDate, $adjustedEndDate, $taskCategories)
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

        // Get optimized worklog data for each half
        $firstHalfWorklogData = $this->getOptimizedWorklogData(
            $user->id,
            $firstHalfStart->format('Y-m-d'),
            $firstHalfEnd->format('Y-m-d'),
            $taskCategories
        );

        $secondHalfWorklogData = $this->getOptimizedWorklogData(
            $user->id,
            $secondHalfStart->format('Y-m-d'),
            $secondHalfEnd->format('Y-m-d'),
            $taskCategories
        );

        return [
            'first_half'  => [
                'date_range'         => [
                    'start' => $firstHalfStart->format('Y-m-d'),
                    'end'   => $firstHalfEnd->format('Y-m-d'),
                ],
                'nad_data'           => $firstHalfNAD['nad_data'],
                'basic_metrics'      => $this->calculateOptimizedBasicMetrics($firstHalfWorklogData),
                'daily_breakdown'    => $this->calculateOptimizedDailyBreakdown($firstHalfWorklogData, $firstHalfStart->format('Y-m-d'), $firstHalfEnd->format('Y-m-d')),
                'category_breakdown' => $this->calculateOptimizedCategoryBreakdown($firstHalfWorklogData, $taskCategories),
            ],
            'second_half' => [
                'date_range'         => [
                    'start' => $secondHalfStart->format('Y-m-d'),
                    'end'   => $secondHalfEnd->format('Y-m-d'),
                ],
                'nad_data'           => $secondHalfNAD['nad_data'],
                'basic_metrics'      => $this->calculateOptimizedBasicMetrics($secondHalfWorklogData),
                'daily_breakdown'    => $this->calculateOptimizedDailyBreakdown($secondHalfWorklogData, $secondHalfStart->format('Y-m-d'), $secondHalfEnd->format('Y-m-d')),
                'category_breakdown' => $this->calculateOptimizedCategoryBreakdown($secondHalfWorklogData, $taskCategories),
            ],
        ];
    }

    /**
     * Calculate optimized weekly summary data
     */
    private function calculateOptimizedWeeklySummaryData($user, $worklogData, $startDate, $endDate, $year, $startWeekNumber, $weekCount, $workStatusChanges, $taskCategories)
    {
        $timezone = config('app.timezone', 'Asia/Singapore');

        // Generate week ranges for the requested period
        $selectedWeeks = getWeekRangeForDates($startDate, $endDate, $startWeekNumber);

        $weeklyBreakdown       = [];
        $totalBillableHours    = 0;
        $totalNonBillableHours = 0;
        $totalNadHours         = 0;
        $totalNadCount         = 0;

        foreach ($selectedWeeks as $weekData) {
            // Get optimized worklog data for this week
            $weekWorklogData = $this->getOptimizedWorklogData(
                $user->id,
                $weekData['start_date'],
                $weekData['end_date'],
                $taskCategories
            );

            // Calculate period metrics with NAD data
            $weekMetrics = $this->calculateOptimizedPeriodMetrics($user, $weekWorklogData, $weekData['start_date'], $weekData['end_date'], true, $timezone);

            // Calculate performance for this week
            $weekPerformance = calculatePerformanceMetrics($user, $weekWorklogData['all_worklogs'], $weekData['start_date'], $weekData['end_date'], $workStatusChanges);

            $weeklyBreakdown[] = [
                'week_number'        => $weekData['week_number'],
                'start_date'         => $weekData['start_date'],
                'end_date'           => $weekData['end_date'],
                'label'              => $weekData['label'],
                'billable_hours'     => $weekMetrics['basic_metrics']['billable_hours'],
                'non_billable_hours' => $weekMetrics['basic_metrics']['non_billable_hours'],
                'total_hours'        => $weekMetrics['basic_metrics']['total_hours'],
                'nad_count'          => $weekMetrics['nad_count'],
                'nad_hours'          => $weekMetrics['nad_hours'],
                'nad_data'           => $weekMetrics['nad_data'],
                'performance'        => $weekPerformance,
                'entries_count'      => $weekMetrics['entries_count'],
            ];

            // Add to totals
            $totalBillableHours += $weekMetrics['basic_metrics']['billable_hours'];
            $totalNonBillableHours += $weekMetrics['basic_metrics']['non_billable_hours'];
            $totalNadHours += $weekMetrics['nad_hours'];
            $totalNadCount += $weekMetrics['nad_count'];
        }

        // Calculate overall category breakdown
        $categoryBreakdown = $this->calculateOptimizedCategoryBreakdownSummary($worklogData, $taskCategories);

        return [
            'summary'            => [
                'total_weeks'              => count($selectedWeeks),
                'total_billable_hours'     => round($totalBillableHours, 2),
                'total_non_billable_hours' => round($totalNonBillableHours, 2),
                'total_hours'              => round($totalBillableHours + $totalNonBillableHours, 2),
                'total_nad_count'          => $totalNadCount,
                'total_nad_hours'          => round($totalNadHours, 2),
                'nad_hour_rate'            => $weekMetrics['nad_hour_rate'] ?? 8,
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
    private function calculateOptimizedMonthlySummaryData($user, $worklogData, $startDate, $endDate, $year, $startMonth, $monthCount, $workStatusChanges, $taskCategories, $adjustedStartDate)
    {
        $timezone = config('app.timezone', 'Asia/Singapore');

        // Generate month ranges for the requested period
        $selectedMonths        = getMonthRangeForDates($startDate, $endDate, $monthCount, $adjustedStartDate);
        $monthlyBreakdown      = [];
        $totalBillableHours    = 0;
        $totalNonBillableHours = 0;
        $totalNadHours         = 0;
        $totalNadCount         = 0;
        // dd($selectedMonths, $startDate, $endDate, $year, $startMonth, $monthCount);
        foreach ($selectedMonths as $monthData) {
            // Get optimized worklog data for this month
            $monthWorklogData = $this->getOptimizedWorklogData(
                $user->id,
                $monthData['start_date'],
                $monthData['end_date'],
                $taskCategories
            );

            // Calculate period metrics with NAD data
            $monthMetrics = $this->calculateOptimizedPeriodMetrics($user, $monthWorklogData, $monthData['start_date'], $monthData['end_date'], true, $timezone);

            // Calculate weekly breakdown for this month
            $weeklyBreakdown = $this->calculateOptimizedWeeklyBreakdownForMonth($user, $monthWorklogData, $monthData['start_date'], $monthData['end_date'], $workStatusChanges, $taskCategories);

            // Calculate performance for this month
            $monthPerformance = calculatePerformanceMetrics($user, $monthWorklogData['all_worklogs'], $monthData['start_date'], $monthData['end_date'], $workStatusChanges);

            $monthlyBreakdown[] = [
                'month_number'       => $monthData['month_number'],
                'start_date'         => $monthData['start_date'],
                'end_date'           => $monthData['end_date'],
                'label'              => $monthData['label'],
                'billable_hours'     => $monthMetrics['basic_metrics']['billable_hours'],
                'non_billable_hours' => $monthMetrics['basic_metrics']['non_billable_hours'],
                'total_hours'        => $monthMetrics['basic_metrics']['total_hours'],
                'nad_count'          => $monthMetrics['nad_count'],
                'nad_hours'          => $monthMetrics['nad_hours'],
                'nad_data'           => $monthMetrics['nad_data'],
                'performance'        => $monthPerformance,
                'entries_count'      => $monthMetrics['entries_count'],
                'weekly_breakdown'   => $weeklyBreakdown,
            ];

            // Add to totals
            $totalBillableHours += $monthMetrics['basic_metrics']['billable_hours'];
            $totalNonBillableHours += $monthMetrics['basic_metrics']['non_billable_hours'];
            $totalNadHours += $monthMetrics['nad_hours'];
            $totalNadCount += $monthMetrics['nad_count'];
        }

        // Calculate overall category breakdown
        $categoryBreakdown = $this->calculateOptimizedCategoryBreakdownSummary($worklogData, $taskCategories);

        return [
            'summary'            => [
                'total_months'             => count($selectedMonths),
                'total_billable_hours'     => round($totalBillableHours, 2),
                'total_non_billable_hours' => round($totalNonBillableHours, 2),
                'total_hours'              => round($totalBillableHours + $totalNonBillableHours, 2),
                'total_nad_count'          => $totalNadCount,
                'total_nad_hours'          => round($totalNadHours, 2),
                'nad_hour_rate'            => $monthMetrics['nad_hour_rate'] ?? 8,
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

    /**
     * Calculate optimized period metrics
     */
    private function calculateOptimizedPeriodMetrics($user, $worklogData, $startDate, $endDate, $includeNAD = true, $timezone = 'Asia/Singapore')
    {
        // Calculate basic metrics
        $basicMetrics = $this->calculateOptimizedBasicMetrics($worklogData);

        $result = [
            'basic_metrics' => $basicMetrics,
            'entries_count' => $basicMetrics['total_entries'],
        ];
        // Add NAD data if requested
        if ($includeNAD) {
            $nadData = fetchNADDataForPeriod($user, $startDate, $endDate);
            $result  = array_merge($result, $nadData);
        }

        return $result;
    }

    /**
     * Calculate optimized weekly breakdown for month
     */
    private function calculateOptimizedWeeklyBreakdownForMonth($user, $monthWorklogData, $startDate, $endDate, $workStatusChanges, $taskCategories)
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

                // Get optimized worklog data for this week
                $weekWorklogData = $this->getOptimizedWorklogData(
                    $user->id,
                    $adjustedStart->format('Y-m-d'),
                    $adjustedEnd->format('Y-m-d'),
                    $taskCategories
                );

                // Calculate period metrics with NAD data
                $weekMetrics = $this->calculateOptimizedPeriodMetrics(
                    $user,
                    $weekWorklogData,
                    $adjustedStart->format('Y-m-d'),
                    $adjustedEnd->format('Y-m-d'),
                    true,
                    $timezone
                );
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
                    'billable_hours'     => $weekMetrics['basic_metrics']['billable_hours'],
                    'non_billable_hours' => $weekMetrics['basic_metrics']['non_billable_hours'],
                    'total_hours'        => $weekMetrics['basic_metrics']['total_hours'],
                    'nad_count'          => $weekMetrics['nad_count'],
                    'nad_hours'          => $weekMetrics['nad_hours'],
                    'nad_data'           => $weekMetrics['nad_data'],
                    'entries_count'      => $weekMetrics['entries_count'],
                ];

                $weekNumber++;
            }

            $currentWeek->addWeek();
        }

        return $weeks;
    }

    /**
     * Calculate optimized category breakdown for summary views
     */
    private function calculateOptimizedCategoryBreakdownSummary($worklogData, $taskCategories)
    {
        $categoryBreakdown = [];

        // Process billable worklogs for summary
        if ($worklogData['billable_worklogs']->count() > 0) {
            $categoryBreakdown[] = $this->processOptimizedCategoryGroupSummary(
                $worklogData['billable_worklogs'],
                $taskCategories['full_mapping'],
                'Billable'
            );
        }

        // Process non-billable worklogs for summary
        if ($worklogData['non_billable_worklogs']->count() > 0) {
            $categoryBreakdown[] = $this->processOptimizedCategoryGroupSummary(
                $worklogData['non_billable_worklogs'],
                $taskCategories['full_mapping'],
                'Non-Billable'
            );
        }

        return array_filter($categoryBreakdown, function ($group) {
            return $group['total_hours'] > 0;
        });
    }

    /**
     * Process optimized category group for summary views
     */
    private function processOptimizedCategoryGroupSummary($worklogs, $fullMapping, $type)
    {
        $categories = [];
        $totalHours = 0;

        // Group worklogs by category
        $worklogsByCategory = [];

        foreach ($worklogs as $worklog) {
            $categoryName = 'Uncategorized';

            if (isset($fullMapping[$worklog->task_id]) && $fullMapping[$worklog->task_id]->isNotEmpty()) {
                $mapping      = $fullMapping[$worklog->task_id]->first();
                $categoryName = $mapping->cat_name;
            }

            if (! isset($worklogsByCategory[$categoryName])) {
                $worklogsByCategory[$categoryName] = [];
            }
            $worklogsByCategory[$categoryName][] = $worklog;
        }

        // Process each category for summary
        foreach ($worklogsByCategory as $categoryName => $categoryWorklogs) {
            if ($categoryName === 'Uncategorized') {
                continue; // Skip uncategorized for main summary
            }

            $categoryHours = collect($categoryWorklogs)->sum('duration') / 3600;
            $totalHours += $categoryHours;

            $categories[] = [
                'category_name' => $categoryName,
                'total_hours'   => round($categoryHours, 2),
                'entries_count' => count($categoryWorklogs),
            ];
        }

        // Sort categories by total hours descending (for summary views)
        usort($categories, function ($a, $b) {
            return $b['total_hours'] <=> $a['total_hours'];
        });

        return [
            'type'        => $type,
            'total_hours' => round($totalHours, 2),
            'categories'  => $categories,
        ];
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
     * Get weekly date range.
     */
    private function getWeeklyDateRange(Request $request)
    {
        $year       = $request->input('year');
        $weekNumber = $request->input('week_number');
        $weekCount  = $request->input('week_count', 1);

        // Use start_date and end_date from request if available
        if ($request->has('start_date') && $request->has('end_date')) {
            return [
                'start' => $request->input('start_date'),
                'end'   => $request->input('end_date'),
            ];
        }

        // Fallback to current week if invalid
        $today       = Carbon::now();
        $startOfWeek = $today->copy()->startOfWeek(Carbon::MONDAY);
        $endOfWeek   = $today->copy()->endOfWeek(Carbon::SUNDAY);

        return [
            'start' => $startOfWeek->toDateString(),
            'end'   => $endOfWeek->toDateString(),
        ];
    }

    /**
     * Get monthly date range.
     */
    private function getMonthlyDateRange(Request $request)
    {
        // Use start_date and end_date from request if available
        if ($request->has('start_date') && $request->has('end_date')) {
            return [
                'start' => $request->input('start_date'),
                'end'   => $request->input('end_date'),
            ];
        }

        $year  = $request->input('year');
        $month = $request->input('month');

        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth   = Carbon::create($year, $month)->endOfMonth();

        return [
            'start' => $startOfMonth->toDateString(),
            'end'   => $endOfMonth->toDateString(),
        ];
    }

    /**
     * Get custom date range.
     */
    private function getCustomDateRange(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfWeek()->toDateString());
        $endDate   = $request->input('end_date', Carbon::now()->endOfWeek()->toDateString());

        // Validate dates
        try {
            Carbon::parse($startDate);
            Carbon::parse($endDate);
        } catch (\Exception $e) {
            $startDate = Carbon::now()->startOfWeek()->toDateString();
            $endDate   = Carbon::now()->endOfWeek()->toDateString();
        }

        return [
            'start' => $startDate,
            'end'   => $endDate,
        ];
    }
}
