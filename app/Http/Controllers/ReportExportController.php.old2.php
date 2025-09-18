<?php
namespace App\Http\Controllers;

use App\Exports\PerformanceReportExport;
use App\Models\IvaUser;
use App\Models\Region;
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
     * Export performance report to Excel.
     * Accepts the same params used by ReportExport.vue
     */
    public function exportReport(Request $request)
    {
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
            $reportData = $this->prepareReportData($request);
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

            return Excel::download(new PerformanceReportExport($reportData), $filename);
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
     * PRIVATE: Build the data structure used both by Excel export and by the test harness
     * (/test/date-time-helpers -> DateTimeHelperTestController::testExportData).
     */
    private function prepareReportData(Request $request)
    {
        $reportType = $request->input('report_type', 'overall');
        $period     = $request->input('report_period', 'weekly_summary');
        $year       = (int) $request->input('year', Carbon::now()->year);

        // 1) Resolve date range + mode
        $range     = $this->resolveDateRange($period, $year, $request);
        $startDate = $range['start'];
        $endDate   = $range['end'];
        $mode      = $range['mode']; // weekly | monthly | yearly (internal)

        $base = [
            'report_type' => $reportType,
            'date_range'  => ['start' => $startDate, 'end' => $endDate],
            'mode'        => $mode,
        ];

        // 2) Dispatch by report type
        if ($reportType === 'region') {
            $regionId = (int) $request->input('region_id');
            $region   = Region::select('id', 'name')->find($regionId);
            if (! $region) {
                throw new \RuntimeException('Region not found');
            }

            $users = $this->getActiveUsersInRegion($regionId, $startDate, $endDate);

            $data = [
                'region'     => ['id' => $region->id, 'name' => $region->name],
                'users_data' => [],
                'summary'    => [],
            ];

            switch ($mode) {
                case 'weekly':
                    $data = $this->processWeeklySummaryData($users, $startDate, $endDate, $data);
                    break;
                case 'monthly':
                    $data = $this->processMonthlySummaryData($users, $startDate, $endDate, $data);
                    break;
                case 'yearly':
                    $data = $this->processYearlyData($users, $startDate, $endDate, $data);
                    break;
            }

            $data['category_summary'] = $this->calculateCategorySummary($data['users_data']);

            return array_merge($base, $data);
        }

        // overall
        $regions  = Region::select('id', 'name')->orderBy('name')->get();
        $allUsers = $this->getAllActiveUsers($startDate, $endDate);

        $allRegionsData = [];
        $allUsersData   = [];

        foreach ($regions as $region) {
            $users = $allUsers->where('region_id', $region->id);
            if ($users->isEmpty()) {
                continue;
            }

            $regionData = [
                'region'     => ['id' => $region->id, 'name' => $region->name],
                'users_data' => [],
                'summary'    => [],
            ];

            switch ($mode) {
                case 'weekly':
                    $regionData = $this->processWeeklySummaryData($users, $startDate, $endDate, $regionData);
                    break;
                case 'monthly':
                    $regionData = $this->processMonthlySummaryData($users, $startDate, $endDate, $regionData);
                    break;
                case 'yearly':
                    $regionData = $this->processYearlyData($users, $startDate, $endDate, $regionData);
                    break;
            }

            $allRegionsData[] = $regionData;
            $allUsersData     = array_merge($allUsersData, $regionData['users_data']);
        }

        return array_merge($base, [
            'regions_data'     => $allRegionsData,
            'users_data'       => $allUsersData,
            'summary'          => $this->calculateGroupSummary($allUsersData),
            'category_summary' => $this->calculateCategorySummary($allUsersData),
        ]);
    }

    /**
     * Map ReportExport.vue selection to an exact date range + internal mode.
     */
    private function resolveDateRange(string $period, int $year, Request $request): array
    {
        switch ($period) {
            case 'weekly_summary':{
                    // Default: current week of the selected year (Mon-Sun)
                    $start = Carbon::create($year, 1, 15)->startOfWeek(Carbon::MONDAY);
                    $end   = Carbon::create($year, 1, 15)->endOfWeek(Carbon::SUNDAY);
                    // If explicit range is provided from UI selection, respect it
                    if ($request->filled('start_date') && $request->filled('end_date')) {
                        $start = Carbon::parse($request->input('start_date'));
                        $end   = Carbon::parse($request->input('end_date'));
                    }
                    return ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d'), 'mode' => 'weekly'];
                }

            case 'monthly_summary':{
                    // UI passes start_date/end_date for the chosen month window
                    $start = Carbon::parse($request->input('start_date'));
                    $end   = Carbon::parse($request->input('end_date'));
                    return ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d'), 'mode' => 'monthly'];
                }

            case 'yearly_summary':{
                    $start = Carbon::create($year, 1, 1)->startOfWeek(Carbon::MONDAY);
                    $end   = Carbon::create($year, 12, 31)->endOfWeek(Carbon::SUNDAY);
                    return ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d'), 'mode' => 'yearly'];
                }

            case 'calendar_month':{
                    $month = (int) $request->input('month');
                    $start = Carbon::create($year, $month, 1)->startOfDay();
                    $end   = Carbon::create($year, $month, 1)->endOfMonth();
                    return ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d'), 'mode' => 'monthly'];
                }

            case 'bimonthly':{
                    $month       = (int) $request->input('month');
                    $cut         = (int) $request->input('bimonthly_date', 15);
                    $firstStart  = Carbon::create($year, $month, 1);
                    $firstEnd    = Carbon::create($year, $month, $cut)->endOfDay();
                    $secondStart = Carbon::create($year, $month, $cut + 1);
                    $secondEnd   = Carbon::create($year, $month, 1)->endOfMonth();
                    // Default to first half window
                    return ['start' => $firstStart->format('Y-m-d'), 'end' => $firstEnd->format('Y-m-d'), 'mode' => 'weekly'];
                }

            case 'custom':
            default: {
                    $start = Carbon::parse($request->input('start_date'));
                    $end   = Carbon::parse($request->input('end_date'));
                    // Internal mode: if >= 52 weeks → yearly; if aligns to month → monthly; else weekly
                    $days = $start->diffInDays($end) + 1;
                    $mode = $days >= 350 ? 'yearly' : ($start->isSameMonth($end) ? 'monthly' : 'weekly');
                    return ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d'), 'mode' => $mode];
                }
        }
    }

    // ----------------------------------------------------------------------
    // Data generation helpers (no dependency on IvaRegionReportController / IvaOverallReportController)
    // ----------------------------------------------------------------------

    /** Get active users in a region during a period */
    private function getActiveUsersInRegion(int $regionId, string $startDate, string $endDate)
    {
        return IvaUser::select([
            'id', 'full_name', 'email', 'job_title', 'work_status', 'region_id', 'hire_date', 'end_date', 'timedoctor_version',
        ])
            ->with(['region:id,name', 'customizations.setting.settingType'])
            ->where('region_id', $regionId)
            ->where('is_active', true)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($x) use ($startDate) {
                    $x->whereNull('hire_date')->orWhere('hire_date', '<=', $startDate);
                })->where(function ($x) use ($endDate) {
                    $x->whereNull('end_date')->orWhere('end_date', '>=', $endDate);
                });
            })
            ->orderBy('full_name')
            ->get();
    }

    /** Get all active users during a period */
    private function getAllActiveUsers(string $startDate, string $endDate)
    {
        return IvaUser::select([
            'id', 'full_name', 'email', 'job_title', 'work_status', 'region_id', 'hire_date', 'end_date', 'timedoctor_version',
        ])
            ->with(['region:id,name', 'customizations.setting.settingType'])
            ->where('is_active', true)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($x) use ($startDate) {
                    $x->whereNull('hire_date')->orWhere('hire_date', '<=', $startDate);
                })->where(function ($x) use ($endDate) {
                    $x->whereNull('end_date')->orWhere('end_date', '>=', $endDate);
                });
            })
            ->orderBy('full_name')
            ->get();
    }

    /** Process weekly performance data */
    private function processWeeklySummaryData($users, string $startDate, string $endDate, array $reportData): array
    {
        $usersData = [];
        foreach ($users as $user) {
            $row = $this->generateUserPerformanceData($user, $startDate, $endDate, 'weekly');
            if ($row) {
                $usersData[] = $row;
            }

        }
        $reportData['users_data'] = $usersData;
        $reportData['summary']    = $this->calculateGroupSummary($usersData);
        return $reportData;
    }

    /** Process monthly performance data */
    private function processMonthlySummaryData($users, string $startDate, string $endDate, array $reportData): array
    {
        $usersData = [];
        foreach ($users as $user) {
            $row = $this->generateUserPerformanceData($user, $startDate, $endDate, 'monthly');
            if ($row) {
                $usersData[] = $row;
            }

        }
        $reportData['users_data'] = $usersData;
        $reportData['summary']    = $this->calculateGroupSummary($usersData);
        return $reportData;
    }

    /** Process yearly performance data */
    private function processYearlyData($users, string $startDate, string $endDate, array $reportData): array
    {
        $usersData = [];
        foreach ($users as $user) {
            $row = $this->generateUserPerformanceData($user, $startDate, $endDate, 'yearly');
            if ($row) {
                $usersData[] = $row;
            }

        }
        $reportData['users_data'] = $usersData;
        $reportData['summary']    = $this->calculateGroupSummary($usersData);
        return $reportData;
    }

    /**
     * Build one user row (metrics, performance, categories).
     * Uses helper functions from main_helper.php for consistency and performance.
     */
    private function generateUserPerformanceData($userRow, string $startDate, string $endDate, string $mode)
    {
        try {
            // Ensure full model (with relations) for helper functions
            $user = $userRow instanceof IvaUser ? $userRow : IvaUser::find($userRow->id);
            if (! $user) {
                return null;
            }

            // Categories (from daily_worklog_summaries) -> flatten to [{category_id, category_name, hours}]
            $categories = $this->getUserCategoriesBreakdown($user->id, $startDate, $endDate);

            // Basic metrics from summaries (billable/non-billable/total)
            $basic            = calculateBasicMetricsFromDailySummaries($user->id, $startDate, $endDate);
            $billableHours    = (float) ($basic['billable_hours'] ?? 0);
            $nonBillableHours = (float) ($basic['non_billable_hours'] ?? 0);
            $totalHours       = (float) ($basic['total_hours'] ?? ($billableHours + $nonBillableHours));

            // Performance (target vs actual) — pass overall billable hours to avoid re-summing
            $perfList    = calculatePerformanceMetricsDailySummaries($user, $startDate, $endDate, $billableHours);
            $performance = is_array($perfList) && ! empty($perfList) ? $perfList[0] : [];

            // NAD data (stub / integrate with your NAD logic if available)
            $nad = $this->getUserNADData($user->id, $startDate, $endDate);

            return [
                'id'                  => $user->id,
                'full_name'           => $user->full_name,
                'email'               => $user->email,
                'job_title'           => $user->job_title,
                'work_status'         => $user->work_status ?: 'full-time',
                'region_id'           => $user->region_id,
                'region_name'         => $user->region->name ?? $this->getRegionName($user->region_id),

                'billable_hours'      => round($billableHours, 2),
                'non_billable_hours'  => round($nonBillableHours, 2),
                'total_hours'         => round($totalHours, 2),

                'target_hours'        => round((float) ($performance['target_total_hours'] ?? 0), 2),
                'performance'         => $performance,
                'performance_percent' => isset($performance['percentage']) ? (float) $performance['percentage'] : null,

                'nad_count'           => (int) ($nad['count'] ?? 0),
                'nad_hours'           => round((float) ($nad['hours'] ?? 0), 2),

                'categories'          => $categories,

                // for weekly mode include a simple week_number display
                'weekly_data'         => $mode === 'weekly' ? [[
                    'week_number' => $this->calculateWeekNumber($startDate),
                    'start_date'  => $startDate,
                    'end_date'    => $endDate,
                    'metrics'     => [
                        'billable_hours'     => round($billableHours, 2),
                        'non_billable_hours' => round($nonBillableHours, 2),
                        'total_hours'        => round($totalHours, 2),
                    ],
                ]] : [],
            ];
        } catch (\Throwable $e) {
            Log::error('Error generating user performance data', [
                'user_id' => $userRow->id ?? null,
                'start'   => $startDate,
                'end'     => $endDate,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /** Category hours breakdown (flat) using helper */
    private function getUserCategoriesBreakdown(int $userId, string $startDate, string $endDate): array
    {
        try {
            $resp = calculateFullCategoryBreakdownFromSummaries($userId, $startDate, $endDate);
            $flat = [];
            if (is_array($resp)) {
                foreach ($resp as $group) {
                    if (! isset($group['categories'])) {
                        continue;
                    }

                    foreach ($group['categories'] as $c) {
                        $flat[] = [
                            'category_id'   => $c['category_id'] ?? $c['id'] ?? null,
                            'category_name' => $c['category_name'] ?? $c['name'] ?? 'Unknown',
                            'hours'         => round((float) ($c['total_hours'] ?? $c['hours'] ?? 0), 2),
                        ];
                    }
                }
            }
            return $flat;
        } catch (\Throwable $e) {
            Log::error('Failed to build category breakdown', [
                'iva_id' => $userId,
                'error'  => $e->getMessage(),
            ]);
            return [];
        }
    }

    /** Sum up group stats for a users_data array */
    private function calculateGroupSummary(array $usersData): array
    {
        $sum = [
            'total_users'              => count($usersData),
            'full_time_users'          => 0,
            'part_time_users'          => 0,
            'total_billable_hours'     => 0.0,
            'total_non_billable_hours' => 0.0,
            'total_hours'              => 0.0,
            'average_performance'      => 0.0,
        ];

        $perfVals = [];
        foreach ($usersData as $u) {
            ($u['work_status'] === 'full-time') ? $sum['full_time_users']++ : $sum['part_time_users']++;
            $sum['total_billable_hours'] += (float) ($u['billable_hours'] ?? 0);
            $sum['total_non_billable_hours'] += (float) ($u['non_billable_hours'] ?? 0);
            $sum['total_hours'] += (float) ($u['total_hours'] ?? 0);
            if (isset($u['performance_percent'])) {
                $perfVals[] = (float) $u['performance_percent'];
            }

        }

        $sum['total_billable_hours']     = round($sum['total_billable_hours'], 2);
        $sum['total_non_billable_hours'] = round($sum['total_non_billable_hours'], 2);
        $sum['total_hours']              = round($sum['total_hours'], 2);
        $sum['average_performance']      = count($perfVals) ? round(array_sum($perfVals) / count($perfVals), 2) : 0.0;

        return $sum;
    }

    /** Aggregate categories across all users */
    private function calculateCategorySummary(array $usersData): array
    {
        $agg = [];
        foreach ($usersData as $u) {
            if (empty($u['categories'])) {
                continue;
            }

            foreach ($u['categories'] as $c) {
                $id   = $c['category_id'] ?? $c['id'] ?? null;
                $name = $c['category_name'] ?? $c['name'] ?? 'Unknown';
                $hrs  = (float) ($c['hours'] ?? $c['total_hours'] ?? 0);
                if ($id === null) {
                    continue;
                }

                if (! isset($agg[$id])) {
                    $agg[$id] = ['category_id' => $id, 'category_name' => $name, 'total_hours' => 0.0];
                }
                $agg[$id]['total_hours'] += $hrs;
            }
        }
        foreach ($agg as &$row) {$row['total_hours'] = round($row['total_hours'], 2);}
        return array_values($agg);
    }

    // ----------------------------------------------------------------------
    // Utilities
    // ----------------------------------------------------------------------

    private function calculateWeekNumber(string $date): int
    {
        return (int) Carbon::parse($date)->isoWeek();
    }

    private function generateFilename(array $data): string
    {
        $type  = ($data['report_type'] === 'region') ? 'Region_Report' : 'Overall_Report';
        $start = Carbon::parse($data['date_range']['start'])->format('M-d');
        $end   = Carbon::parse($data['date_range']['end'])->format('M-d');
        $year  = Carbon::parse($data['date_range']['start'])->format('Y');
        if (! empty($data['region']['name'] ?? '')) {
            $type .= '_' . str_replace(' ', '_', $data['region']['name']);
        }
        return sprintf('%s_%s_to_%s_%s.xlsx', $type, $start, $end, $year);
    }

    /** Compute user target hours via helper (optimized) */
    private function calculateUserTargetHours(IvaUser $user, string $startDate, string $endDate): float
    {
        try {
            $res = calculateUserTargetHoursOptimized($user, $startDate, $endDate);
            if (is_array($res) && ($res['success'] ?? false)) {
                return (float) ($res['target_total_hours'] ?? 0);
            }
        } catch (\Throwable $e) {
            Log::warning('Target hour calculation fallback', [
                'iva_id' => $user->id,
                'error'  => $e->getMessage(),
            ]);
        }
        return 0.0;
    }

    /** NAD lookup placeholder. Integrate real NAD logic if available. */
    private function getUserNADData(int $userId, string $startDate, string $endDate): array
    {
        // Implement with your NAD storage if needed. For tests it can be zero.
        return ['count' => 0, 'hours' => 0.0];
    }

    private function getRegionName(?int $regionId): string
    {
        if (! $regionId) {
            return 'Unknown';
        }

        $r = DB::table('regions')->select('name')->where('id', $regionId)->first();
        return $r->name ?? 'Unknown';
    }
}
