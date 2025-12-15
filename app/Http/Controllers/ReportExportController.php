<?php
namespace App\Http\Controllers;

use App\Exports\PerformanceReportExport;
use App\Models\IvaUser;
use App\Services\ActivityLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ReportExportController extends Controller
{
    /**
     * Export performance report to Excel
     * Based on IvaRegionReportController and IvaOverallReportController but without caching
     */
    public function exportReport(Request $request)
    {
        // Validate region access for users with view_team_data permission
        $regionValidation = validateManagerRegionAccess($request->user());
        if ($regionValidation) {
            return response()->json([
                'success'             => false,
                'error'               => $regionValidation['error'],
                'message'             => $regionValidation['message'],
                'region_access_error' => true,
            ], 403);
        }

        // Check if current user should be filtered by region
        $managerRegionFilter = getManagerRegionFilter($request->user());

        // If manager has view_team_data only, override report_type and region_id
        if ($managerRegionFilter) {
            $request->merge([
                'report_type' => 'region',
                'region_id'   => $managerRegionFilter,
            ]);
        }

        $validator = Validator::make($request->all(), [
            'report_type'    => 'required|in:region,overall',
            'report_period'  => 'required|in:weekly_summary,monthly_summary,yearly_summary,calendar_month,bimonthly,custom',
            'region_id'      => 'required_if:report_type,region|exists:regions,id',
            'year'           => 'required|integer|min:2024',
            'month'          => 'nullable|integer|min:1|max:12',
            'bimonthly_date' => 'nullable|integer|min:1|max:28',
            'start_date'     => 'nullable|date|required_if:report_period,custom',
            'end_date'       => 'nullable|date|after_or_equal:start_date|required_if:report_period,custom',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $reportData = $this->generateReportData($request);
            $filename   = $this->generateFilename($reportData);

            ActivityLogService::log(
                'export_report',
                "Exported {$reportData['report_type']} report for {$reportData['date_range']['start']} to {$reportData['date_range']['end']}",
                [
                    'report_type' => $reportData['report_type'],
                    'date_range'  => $reportData['date_range'],
                    'region'      => $reportData['region'] ?? null,
                    'summary'     => $reportData['summary'] ?? null,
                ]
            );

            $export = Excel::raw(new PerformanceReportExport($reportData), \Maatwebsite\Excel\Excel::XLSX);

            return response($export, 200, [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'X-Filename'          => $filename, // Custom header for easier frontend access
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to export report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export report',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export report data as JSON for client-side CSV generation
     * Same validation and data generation as exportReport() but returns JSON
     */
    public function exportReportData(Request $request)
    {
        // Validate region access for users with view_team_data permission
        $regionValidation = validateManagerRegionAccess($request->user());
        if ($regionValidation) {
            return response()->json([
                'success'             => false,
                'error'               => $regionValidation['error'],
                'message'             => $regionValidation['message'],
                'region_access_error' => true,
            ], 403);
        }

        // Check if current user should be filtered by region
        $managerRegionFilter = getManagerRegionFilter($request->user());

        // If manager has view_team_data only, override report_type and region_id
        if ($managerRegionFilter) {
            $request->merge([
                'report_type' => 'region',
                'region_id'   => $managerRegionFilter,
            ]);
        }

        $validator = Validator::make($request->all(), [
            'report_type'    => 'required|in:region,overall',
            'report_period'  => 'required|in:weekly_summary,monthly_summary,yearly_summary,calendar_month,bimonthly,custom',
            'region_id'      => 'required_if:report_type,region|exists:regions,id',
            'year'           => 'required|integer|min:2024',
            'month'          => 'nullable|integer|min:1|max:12',
            'bimonthly_date' => 'nullable|integer|min:1|max:28',
            'start_date'     => 'nullable|date|required_if:report_period,custom',
            'end_date'       => 'nullable|date|after_or_equal:start_date|required_if:report_period,custom',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $reportData = $this->generateReportData($request);

            ActivityLogService::log(
                'export_report_csv',
                "Exported {$reportData['report_type']} report as CSV for {$reportData['date_range']['start']} to {$reportData['date_range']['end']}",
                [
                    'report_type' => $reportData['report_type'],
                    'date_range'  => $reportData['date_range'],
                    'region'      => $reportData['region'] ?? null,
                ]
            );

            // For region reports, include users_data (removed at line 164 for XLSX)
            if ($reportData['report_type'] === 'region' && ! isset($reportData['users_data'])) {
                $reportData = $this->generateReportDataWithUsers($request);
            }

            // Return JSON data for client-side CSV generation
            return response()->json([
                'success' => true,
                'data'    => $reportData,
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to export report data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export report data',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate report data using same logic as IvaRegionReportController and IvaOverallReportController
     * but optimized and without caching
     */
    private function generateReportData(Request $request)
    {
        $reportType = $request->input('report_type');
        $period     = $request->input('report_period');
        $year       = $request->input('year');

        // Resolve date range and mode based on period
        $dateInfo  = $this->resolveDateRangeAndMode($period, $year, $request);
        $startDate = $dateInfo['start_date'];
        $endDate   = $dateInfo['end_date'];
        $mode      = $dateInfo['mode'];

        // Base report structure
        $reportData = [
            'report_type'   => $reportType,
            'report_period' => $period, // Add report_period to the data passed to export
            'year'          => $year,
            'mode'          => $mode,
            'date_range'    => [
                'start' => $startDate,
                'end'   => $endDate,
            ],
        ];

        if ($reportType === 'region') {
            return $this->generateRegionReportData($request, $reportData, $startDate, $endDate, $mode);
        } else {
            return $this->generateOverallReportData($request, $reportData, $startDate, $endDate, $mode);
        }
    }

    /**
     * Generate region report data (mirrors IvaRegionReportController logic)
     */
    private function generateRegionReportData(Request $request, array $reportData, string $startDate, string $endDate, string $mode)
    {
        // Check if current user should be filtered by region
        $managerRegionFilter = getManagerRegionFilter($request->user());

        $regionId = $request->input('region_id');

        // Get region info
        $region = DB::table('regions')->find($regionId);
        if (! $region) {
            throw new \Exception('Region not found');
        }

        $reportData['region'] = [
            'id'   => $region->id,
            'name' => $region->name,
        ];

        // Get all active users in the region during the period (with manager filter if applicable)
        $users = $this->getActiveUsersInRegion($regionId, $startDate, $endDate, $managerRegionFilter);

        // Process performance data based on mode
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

        // Add category summary
        $reportData['category_summary'] = $this->calculateCategorySummaryOptimized($reportData['users_data']);
        // remove users_data to make output smaller
        unset($reportData['users_data']);

        return $reportData;
    }

    /**
     * Generate report data with users_data included (for CSV export of region reports)
     * This is a modified version of generateReportData that doesn't remove users_data
     */
    private function generateReportDataWithUsers(Request $request)
    {
        $reportType = $request->input('report_type');
        $period     = $request->input('report_period');
        $year       = $request->input('year');

        $dateInfo  = $this->resolveDateRangeAndMode($period, $year, $request);
        $startDate = $dateInfo['start_date'];
        $endDate   = $dateInfo['end_date'];
        $mode      = $dateInfo['mode'];

        $reportData = [
            'report_type'   => $reportType,
            'report_period' => $period,
            'year'          => $year,
            'mode'          => $mode,
            'date_range'    => [
                'start' => $startDate,
                'end'   => $endDate,
            ],
        ];

        if ($reportType === 'region') {
            $managerRegionFilter = getManagerRegionFilter($request->user());
            $regionId            = $request->input('region_id');

            $region = DB::table('regions')->find($regionId);
            if (! $region) {
                throw new \Exception('Region not found');
            }

            $reportData['region'] = [
                'id'   => $region->id,
                'name' => $region->name,
            ];

            $users = $this->getActiveUsersInRegion($regionId, $startDate, $endDate, $managerRegionFilter);

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

            $reportData['category_summary'] = $this->calculateCategorySummaryOptimized($reportData['users_data']);
            // DON'T remove users_data for CSV export (unlike line 238)

            return $reportData;
        } else {
            // For overall reports, use the standard method
            return $this->generateOverallReportData($request, $reportData, $startDate, $endDate, $mode);
        }
    }

    /**
     * Generate overall report data (mirrors IvaOverallReportController logic)
     */
    private function generateOverallReportData(Request $request, array $reportData, string $startDate, string $endDate, string $mode)
    {
        // Check if current user should be filtered by region
        $managerRegionFilter = getManagerRegionFilter($request->user());

        // Get all active regions (filtered if manager has view_team_data only)
        $regionsQuery = DB::table('regions')
            ->where('is_active', true)
            ->orderBy('name');

        if ($managerRegionFilter) {
            $regionsQuery->where('id', $managerRegionFilter);
        }

        $regions = $regionsQuery->get();

        // Get all active users during the period (filtered if manager has view_team_data only)
        $allUsers = $this->getAllActiveUsers($startDate, $endDate, $managerRegionFilter);

        // Process performance data based on mode using optimized functions
        $reportData['regions_data'] = [];
        $reportData['users_data']   = [];

        $allFullTimeUsers = [];
        $allPartTimeUsers = [];
        $allUsersData     = [];

        foreach ($regions as $region) {
            // Filter users who were predominantly in this region during the reporting period
            // This mirrors the work status filtering logic exactly
            $regionUsers = $allUsers->filter(function ($user) use ($region, $startDate, $endDate) {
                $predominantRegion = getPredominantRegionForPeriod($user, $startDate, $endDate);
                return $predominantRegion == $region->id;
            });

            if ($regionUsers->isEmpty()) {
                continue;
            }

            // Process region users using optimized functions
            $regionData = $this->processRegionUsersOptimized($regionUsers, $startDate, $endDate, $mode, $region);

            if (! empty($regionData['users_data'])) {
                $reportData['regions_data'][] = $regionData;

                // Add to overall collections
                $allUsersData     = array_merge($allUsersData, $regionData['users_data']);
                $allFullTimeUsers = array_merge($allFullTimeUsers, $regionData['full_time_users']);
                $allPartTimeUsers = array_merge($allPartTimeUsers, $regionData['part_time_users']);
            }
        }

        // Calculate overall summaries using collected data (no need to store duplicate arrays)
        $reportData['summary'] = [
            'full_time' => $this->calculateGroupSummary($allFullTimeUsers),
            'part_time' => $this->calculateGroupSummary($allPartTimeUsers),
            'overall'   => $this->calculateGroupSummary($allUsersData),
        ];

        // Add category summary for all users
        $reportData['category_summary'] = $this->calculateCategorySummaryOptimized($allUsersData);

        return $reportData;
    }

    /**
     * Resolve date range and mode based on period selection
     */
    private function resolveDateRangeAndMode(string $period, int $year, Request $request): array
    {
        switch ($period) {
            case 'weekly_summary':
                // UI passes start_date/end_date for the chosen week
                $startDate = $request->input('start_date');
                $endDate   = $request->input('end_date');

                return [
                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                    'mode'       => 'weekly',
                ];

            case 'monthly_summary':
                // UI passes start_date/end_date for the chosen month window
                $startDate = $request->input('start_date');
                $endDate   = $request->input('end_date');

                return [
                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                    'mode'       => 'monthly',
                ];

            case 'yearly_summary':
                // UI passes start_date/end_date for 52 weeks
                $startDate = $request->input('start_date');
                $endDate   = $request->input('end_date');

                return [
                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                    'mode'       => 'yearly',
                ];

            case 'calendar_month':
                $month     = (int) $request->input('month');
                $startDate = Carbon::create($year, $month, 1)->startOfDay()->format('Y-m-d');
                $endDate   = Carbon::create($year, $month, 1)->endOfMonth()->format('Y-m-d');

                return [
                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                    'mode'       => 'monthly',
                ];

            case 'bimonthly':
                $month     = (int) $request->input('month');
                $cut       = (int) $request->input('bimonthly_date', 15);
                $startDate = Carbon::create($year, $month, 1)->format('Y-m-d');
                $endDate   = Carbon::create($year, $month, $cut)->endOfDay()->format('Y-m-d');

                return [
                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                    'mode'       => 'weekly',
                ];

            case 'custom':
            default:
                $startDate = $request->input('start_date');
                $endDate   = $request->input('end_date');
                // Determine mode based on date range
                $days = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
                $mode = $days >= 350 ? 'yearly' : ($days >= 28 ? 'monthly' : 'weekly');

                return [
                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                    'mode'       => $mode,
                ];
        }
    }

    /**
     * Get active users in region during the specified period
     * Now uses historical region assignment during the reporting period
     */
    private function getActiveUsersInRegion($regionId, $startDate, $endDate, $managerRegionFilter = null)
    {
        // Get all active users during the period (with manager region filter if applicable)
        $allUsers = $this->getAllActiveUsers($startDate, $endDate, $managerRegionFilter);

        // Filter users who were predominantly in this region during the reporting period
        // This mirrors the work status filtering logic exactly
        $usersInRegion = $allUsers->filter(function ($user) use ($regionId, $startDate, $endDate) {
            $predominantRegion = getPredominantRegionForPeriod($user, $startDate, $endDate);
            return $predominantRegion == $regionId;
        });

        return $usersInRegion;
    }

    /**
     * Get all active users during the specified period
     * (same logic as IvaOverallReportController)
     */
    private function getAllActiveUsers($startDate, $endDate, $managerRegionFilter = null)
    {
        $query = IvaUser::select([
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
        // ->where('is_active', true) -- Removed to include users active during period
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
            });

        // Apply region filter if manager has view_team_data only
        if ($managerRegionFilter) {
            $query->where('region_id', $managerRegionFilter);
        }

        return $query->orderBy('region_id')
            ->orderBy('work_status')
            ->orderBy('full_name')
            ->get();
    }

    /**
     * Process a single user considering work status changes during the period.
     * Returns an array of user data entries - one for each work status the user had.
     * Fixed target hours calculation to correctly handle work status transitions.
     */
    private function processUserWithWorkStatusPeriods(
        $user,
        $startDate,
        $endDate,
        $nadDataByEmail,
        $mode
    ) {
        $userEntries = [];

        // 1. Get work status periods
        $workStatusChanges = getWorkStatusChanges($user, $startDate, $endDate);
        $workStatusPeriods = calculateWorkStatusPeriods(
            $user,
            $startDate,
            $endDate,
            $workStatusChanges
        );

        // Fallback nếu không có period
        if (empty($workStatusPeriods)) {
            $workStatusPeriods = [[
                'work_status' => $user->work_status ?: 'full-time',
                'start_date'  => $startDate,
                'end_date'    => $endDate,
                'days'        => Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1,
            ]];
        }

        // 2. Group periods by work status
        $periodsByStatus = [];
        foreach ($workStatusPeriods as $period) {
            $status                     = $period['work_status'] ?: 'full-time';
            $periodsByStatus[$status][] = $period;
        }

        // 3. Process each work status separately
        foreach ($periodsByStatus as $workStatus => $periods) {

            $totalBillableHours    = 0;
            $totalNonBillableHours = 0;
            $totalHours            = 0;
            $allCategories         = [];

            // ===============================
            // 4. METRICS + CATEGORY BREAKDOWN
            // ===============================
            foreach ($periods as $period) {
                $periodStart = $period['start_date'];
                $periodEnd   = $period['end_date'];

                $metrics = calculateBasicMetricsFromDailySummaries(
                    $user->id,
                    $periodStart,
                    $periodEnd
                );

                $totalBillableHours += $metrics['billable_hours'];
                $totalNonBillableHours += $metrics['non_billable_hours'];
                $totalHours += $metrics['total_hours'];

                $categories = ($mode === 'weekly')
                    ? calculateFullCategoryBreakdownFromSummaries($user->id, $periodStart, $periodEnd)
                    : calculateCategoryBreakdownFromSummaries($user->id, $periodStart, $periodEnd);

                if (is_array($categories)) {
                    foreach ($categories as $typeGroup) {
                        foreach ($typeGroup['categories'] ?? [] as $category) {
                            $categoryId = $category['category_id'];
                            if (! isset($allCategories[$categoryId])) {
                                $allCategories[$categoryId] = [
                                    'category_id'   => $categoryId,
                                    'category_name' => $category['category_name'],
                                    'hours'         => 0,
                                ];
                            }
                            $allCategories[$categoryId]['hours']
                            += $category['total_hours'] ?? $category['hours'] ?? 0;
                        }
                    }
                }
            }

            $categoriesBreakdown = array_values($allCategories);

            // ===============================
            // 5. TARGET HOURS (FIXED LOGIC)
            // ===============================
            $targetTotalHours = 0;

            foreach ($periods as $period) {

                $current   = Carbon::parse($period['start_date'])->startOfDay();
                $periodEnd = Carbon::parse($period['end_date'])->endOfDay();

                // Align to Monday
                if (! $current->isMonday()) {
                    $current->startOfWeek(Carbon::MONDAY);
                }

                while ($current->lte($periodEnd)) {

                    $weekStart = $current->copy();
                    $weekEnd   = $current->copy()->endOfWeek(Carbon::SUNDAY);

                    if ($weekEnd->gt($periodEnd)) {
                        $weekEnd = $periodEnd->copy();
                    }

                    // Get hour settings for THIS week + THIS work status
                    $hourSettings = getWorkHourSettings(
                        $user,
                        $workStatus,
                        $weekStart->toDateString(),
                        $weekEnd->toDateString()
                    );

                    $hoursPerWeek = $hourSettings[0]['hours'] ?? ($workStatus === 'full-time' ? 35 : 20);

                    $targetTotalHours += $hoursPerWeek;

                    $current->addWeek();
                }
            }

            // ===============================
            // 6. PERFORMANCE CALCULATION
            // ===============================
            $percentage = $targetTotalHours > 0
                ? ($totalBillableHours / $targetTotalHours) * 100
                : 0;

            $thresholds = config('constants.performance_percentage_thresholds', [
                'EXCEEDED' => 101,
                'MEET'     => 99,
            ]);

            $statusLabels = config('constants.performance_status', [
                'BELOW'    => 'BELOW',
                'MEET'     => 'MEET',
                'EXCEEDED' => 'EXCEEDED',
            ]);

            $status = $statusLabels['BELOW'];
            if ($percentage >= $thresholds['EXCEEDED']) {
                $status = $statusLabels['EXCEEDED'];
            } elseif ($percentage >= $thresholds['MEET']) {
                $status = $statusLabels['MEET'];
            }

            $periodDays         = array_sum(array_column($periods, 'days'));
            $periodWeeks        = $periodDays / 7;
            $targetHoursPerWeek = $periodWeeks > 0
                ? $targetTotalHours / $periodWeeks
                : 0;

            $performance = [
                'work_status'           => ucwords(str_replace('-', ' ', $workStatus)),
                'target_total_hours'    => round($targetTotalHours, 2),
                'target_hours_per_week' => round($targetHoursPerWeek, 2),
                'period_weeks'          => round($periodWeeks, 1),
                'percentage'            => round($percentage, 1),
                'status'                => $status,
            ];

            // ===============================
            // 7. NAD (PROPORTIONAL)
            // ===============================
            $userNadData = $nadDataByEmail[$user->email] ?? ['nad_count' => 0, 'nad_hours' => 0, 'requests' => 0];

            $totalDays     = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
            $statusDays    = $periodDays;
            $nadProportion = $totalDays > 0 ? $statusDays / $totalDays : 0;

            // ===============================
            // 8. REGION
            // ===============================
            $predominantRegionId   = getPredominantRegionForPeriod($user, $startDate, $endDate);
            $predominantRegionName = 'Unknown';

            if ($predominantRegionId) {
                $region                = \DB::table('regions')->find($predominantRegionId);
                $predominantRegionName = $region->name ?? 'Unknown';
            }

            // ===============================
            // 9. BUILD EXPORT ROW
            // ===============================
            $userEntries[] = [
                'id'                 => $user->id,
                'full_name'          => $user->full_name,
                'email'              => $user->email,
                'job_title'          => $user->job_title,
                'work_status'        => $workStatus,
                'region_id'          => $predominantRegionId,
                'region_name'        => $predominantRegionName,
                'billable_hours'     => (float) $totalBillableHours,
                'non_billable_hours' => (float) $totalNonBillableHours,
                'total_hours'        => (float) $totalHours,
                'target_hours'       => (float) round($targetTotalHours, 2),
                'nad_count'          => round($userNadData['nad_count'] * $nadProportion),
                'nad_hours'          => round($userNadData['nad_hours'] * $nadProportion, 2),
                'performance'        => $performance,
                'categories'         => $categoriesBreakdown,
            ];
        }

        return $userEntries;
    }

    /**
     * Process weekly summary data using optimized daily summaries
     * (mirrors IvaRegionReportController logic)
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
            // Process user with work status periods - may return multiple entries
            $userEntries = $this->processUserWithWorkStatusPeriods($user, $startDate, $endDate, $nadDataByEmail, 'weekly');

            foreach ($userEntries as $userData) {
                $allUsersData[] = $userData;

                // Separate by work status
                if ($userData['work_status'] === 'full-time') {
                    $fullTimeUsers[] = $userData;
                } else {
                    $partTimeUsers[] = $userData;
                }
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
     * (mirrors IvaRegionReportController logic but adds weekly_breakdown for monthly_summary)
     */
    private function processMonthlySummaryDataOptimized($users, $startDate, $endDate, $reportData)
    {
        $allUsersData  = [];
        $fullTimeUsers = [];
        $partTimeUsers = [];

        // Fetch NAD data for all users once (optimized) for the entire month
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
            // Process user with work status periods - may return multiple entries
            $userEntries = $this->processUserWithWorkStatusPeriods($user, $startDate, $endDate, $nadDataByEmail, 'monthly');

            foreach ($userEntries as $userData) {
                // FOR MONTHLY_SUMMARY: Calculate weekly breakdown within this month
                $weeklyBreakdown              = $this->calculateWeeklyBreakdownForMonthOptimized($user, $startDate, $endDate, $nadDataByEmail);
                $userData['weekly_breakdown'] = $weeklyBreakdown;

                $allUsersData[] = $userData;

                // Separate by work status
                if ($userData['work_status'] === 'full-time') {
                    $fullTimeUsers[] = $userData;
                } else {
                    $partTimeUsers[] = $userData;
                }
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
     * (mirrors IvaRegionReportController logic)
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
            // Process user with work status periods - may return multiple entries
            $userEntries = $this->processUserWithWorkStatusPeriods($user, $startDate, $endDate, $nadDataByEmail, 'yearly');

            foreach ($userEntries as $userData) {
                $allUsersData[] = $userData;

                // Separate by work status
                if ($userData['work_status'] === 'full-time') {
                    $fullTimeUsers[] = $userData;
                } else {
                    $partTimeUsers[] = $userData;
                }
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
     * Calculate weekly breakdown for month using optimized approach
     * Based on WorklogDashboardController::calculateWeeklyBreakdownForMonthDailySummary
     * but optimized to use pre-fetched NAD data
     */
    private function calculateWeeklyBreakdownForMonthOptimized($user, $startDate, $endDate, $nadDataByEmail)
    {
        try {
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

                    // Use optimized NAD data lookup instead of individual API call
                    $userNadData = $nadDataByEmail[$user->email] ?? ['nad_count' => 0, 'nad_hours' => 0, 'requests' => 0];

                    // Calculate proportional NAD data for this week based on days overlap
                    $weekDays      = $adjustedStart->diffInDays($adjustedEnd) + 1;
                    $monthDays     = $monthStart->diffInDays($monthEnd) + 1;
                    $nadProportion = $weekDays / $monthDays;

                    $weekNadCount = round($userNadData['nad_count'] * $nadProportion);
                    $weekNadHours = round($userNadData['nad_hours'] * $nadProportion, 2);

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
                        'nad_count'          => $weekNadCount,
                        'nad_hours'          => $weekNadHours,
                        'nad_data'           => [
                            'nad_count' => $weekNadCount,
                            'nad_hours' => $weekNadHours,
                        ],
                        'entries_count'      => $weekMetrics['total_entries'],
                    ];

                    $weekNumber++;
                }

                $currentWeek->addWeek();
            }

            return $weeks;

        } catch (\Exception $e) {
            Log::error('Failed to calculate weekly breakdown for month', [
                'user_id'    => $user->id,
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'error'      => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Process users for a specific region using optimized daily summaries
     * (mirrors IvaOverallReportController logic)
     */
    private function processRegionUsersOptimized($users, $startDate, $endDate, $mode, $region)
    {
        switch ($mode) {
            case 'weekly':
                $reportData = $this->processWeeklySummaryDataOptimized($users, $startDate, $endDate, []);
                break;
            case 'monthly':
                $reportData = $this->processMonthlySummaryDataOptimized($users, $startDate, $endDate, []);
                break;
            case 'yearly':
                $reportData = $this->processYearlyDataOptimized($users, $startDate, $endDate, []);
                break;
        }

        return [
            'region'          => [
                'id'   => $region->id,
                'name' => $region->name,
            ],
            'users_data'      => $reportData['users_data'],
            'full_time_users' => $reportData['full_time_users'],
            'part_time_users' => $reportData['part_time_users'],
            'summary'         => $reportData['summary'],
        ];
    }

    /**
     * Calculate group summary with optimized performance data structure
     * (same logic as IvaRegionReportController and IvaOverallReportController)
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
            'total_billable_hours'     => $totalBillableHours,
            'total_non_billable_hours' => $totalNonBillableHours,
            'total_hours'              => $totalBillableHours + $totalNonBillableHours,
            'total_target_hours'       => $totalTargetHours,
            'total_nad_count'          => $totalNadCount,
            'total_nad_hours'          => $totalNadHours,
            'avg_performance'          => $avgPerformance,
            'performance_breakdown'    => $performanceBreakdown,
        ];
    }

    /**
     * Calculate category summary across all users using optimized approach
     * (same logic as IvaRegionReportController and IvaOverallReportController)
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

        // Calculate averages (keep raw precision)
        foreach ($result as &$category) {
            $category['total_hours']        = $category['total_hours'];
            $category['avg_hours_per_user'] = $category['user_count'] > 0
                ? ($category['total_hours'] / $category['user_count'])
                : 0;
        }

        return $result;
    }

    /**
     * Get available regions for the frontend dropdown
     * (copied from IvaRegionReportController for API compatibility)
     */
    public function getAvailableRegions(Request $request)
    {
        // Check if current user should be filtered by region
        $managerRegionFilter = getManagerRegionFilter($request->user());

        $query = DB::table('regions')
            ->where('is_active', true)
            ->orderBy('name');

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
                'applied'            => true,
                'region_id'          => $managerRegionFilter,
                'locked'             => true,
                'report_type_locked' => true,
                'reason'             => 'view_team_data_permission',
            ] : ['applied' => false, 'locked' => false, 'report_type_locked' => false],
        ]);
    }

    /**
     * Generate filename for export
     */
    private function generateFilename(array $data): string
    {
        $type = ($data['report_type'] === 'region') ? 'Region_Report' : 'Overall_Report';

        // Add region name if applicable
        if (! empty($data['region']['name'] ?? '')) {
            $type .= '_' . str_replace(' ', '_', $data['region']['name']);
        }

        // Add report period type
        $reportPeriod = $this->formatReportPeriod($data['report_period'] ?? 'custom');

        // Format date range
        $start = Carbon::parse($data['date_range']['start'])->format('M-d');
        $end   = Carbon::parse($data['date_range']['end'])->format('M-d');
        $year  = Carbon::parse($data['date_range']['start'])->format('Y');

        // Add export datetime
        $exportDateTime = Carbon::now()->format('Y-m-d_H-i-s');

        return sprintf('%s_%s_%s_to_%s_%s_exported_%s.xlsx',
            $type,
            $reportPeriod,
            $start,
            $end,
            $year,
            $exportDateTime
        );
    }

    /**
     * Format report period for filename
     */
    private function formatReportPeriod(string $period): string
    {
        switch ($period) {
            case 'weekly_summary':
                return 'Weekly_Summary';
            case 'monthly_summary':
                return 'Monthly_Summary';
            case 'yearly_summary':
                return 'Yearly_Summary';
            case 'calendar_month':
                return 'Calendar_Month';
            case 'bimonthly':
                return 'Bimonthly';
            case 'custom':
            default:
                return 'Custom_Range';
        }
    }

}
