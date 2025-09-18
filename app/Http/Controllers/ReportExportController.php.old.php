<?php

namespace App\Http\Controllers;

use App\Exports\PerformanceReportExport;
use App\Models\IvaUser;
use App\Models\Region;
use App\Models\ReportCategory;
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
     */
    public function exportReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'report_type' => 'required|in:region,overall',
            'report_period' => 'required|in:weekly_summary,monthly_summary,yearly_summary,calendar_month,bimonthly,custom',
            'region_id' => 'required_if:report_type,region|exists:regions,id',
            'year' => 'required|integer|min:2024',
            'month' => 'nullable|integer|min:1|max:12',
            'bimonthly_date' => 'nullable|integer|min:1|max:28',
            'start_date' => 'nullable|date|required_if:report_period,custom',
            'end_date' => 'nullable|date|after_or_equal:start_date|required_if:report_period,custom',
        ]);

        if ($validator->fails()) {
            \Log::error('validator test failed', [
                'message' => $validator->errors(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Generate the date range and report data
            $reportData = $this->prepareReportData($request);

            // Generate filename
            $filename = $this->generateFilename($reportData);

            // Log the activity
            ActivityLogService::log(
                'export_report',
                "Exported {$request->report_type} report for period: {$reportData['date_range']['start']} to {$reportData['date_range']['end']}",
                [
                    'report_type' => $request->report_type,
                    'report_period' => $request->report_period,
                    'region_id' => $request->region_id,
                    'year' => $request->year,
                    'date_range' => $reportData['date_range'],
                ]
            );

            // Create and return the Excel export
            return Excel::download(
                new PerformanceReportExport($reportData),
                $filename,
                \Maatwebsite\Excel\Excel::XLSX
            );

        } catch (\Exception $e) {
            \Log::error('Report export failed', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);
            \Log::error('Export test failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Export failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Prepare report data based on request parameters
     */
    private function prepareReportData(Request $request)
    {
        // Calculate date range
        $dateRange = $this->calculateDateRange($request);

        // Get report data based on type
        if ($request->report_type === 'region') {
            $reportData = $this->getRegionReportData($request, $dateRange);
        } else {
            $reportData = $this->getOverallReportData($request, $dateRange);
        }

        // Add metadata
        $reportData['metadata'] = [
            'report_type' => $request->report_type,
            'report_period' => $request->report_period,
            'region_id' => $request->region_id,
            'year' => $request->year,
            'month' => $request->month,
            'bimonthly_date' => $request->bimonthly_date,
            'generated_at' => now()->toISOString(),
            'generated_by' => 'System',
        ];

        return $reportData;
    }

    /**
     * Calculate date range based on request parameters
     */
    private function calculateDateRange(Request $request)
    {
        $year = $request->year;
        $period = $request->report_period;

        switch ($period) {
            case 'weekly_summary':
                // For weekly summary, we need start/end dates from frontend
                return [
                    'start' => $request->start_date,
                    'end' => $request->end_date,
                    'mode' => 'weekly',
                ];

            case 'monthly_summary':
                // For monthly summary, we need start/end dates from frontend
                return [
                    'start' => $request->start_date,
                    'end' => $request->end_date,
                    'mode' => 'monthly',
                ];

            case 'yearly_summary':
                // Full year (52 weeks)
                $startDate = Carbon::create($year, 1, 15)->startOfWeek(Carbon::MONDAY); // First Monday
                $endDate = Carbon::create($year, 12, 31)->endOfWeek(Carbon::SUNDAY);  // Last Sunday

                return [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                    'mode' => 'yearly',
                ];

            case 'calendar_month':
                // Full calendar month
                $startDate = Carbon::create($year, $request->month, 1)->startOfDay();
                $endDate = Carbon::create($year, $request->month)->endOfMonth();

                return [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                    'mode' => 'monthly',
                ];

            case 'bimonthly':
                // Split month
                $splitDate = $request->bimonthly_date ?? 15;
                $firstHalfStart = Carbon::create($year, $request->month, 1)->startOfDay();
                $firstHalfEnd = Carbon::create($year, $request->month, $splitDate)->endOfDay();
                $secondHalfStart = Carbon::create($year, $request->month, $splitDate + 1)->startOfDay();
                $secondHalfEnd = Carbon::create($year, $request->month)->endOfMonth();

                return [
                    'start' => $firstHalfStart->format('Y-m-d'),
                    'end' => $secondHalfEnd->format('Y-m-d'),
                    'mode' => 'bimonthly',
                    'split_date' => $splitDate,
                    'first_half_start' => $firstHalfStart->format('Y-m-d'),
                    'first_half_end' => $firstHalfEnd->format('Y-m-d'),
                    'second_half_start' => $secondHalfStart->format('Y-m-d'),
                    'second_half_end' => $secondHalfEnd->format('Y-m-d'),
                ];

            case 'custom':
                return [
                    'start' => $request->start_date,
                    'end' => $request->end_date,
                    'mode' => 'custom',
                ];

            default:
                throw new \Exception('Invalid report period');
        }
    }

    /**
     * Get region report data
     */
    private function getRegionReportData(Request $request, array $dateRange)
    {
        $regionId = $request->region_id;
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];
        $mode = $this->mapModeForController($dateRange['mode']);
        $year = $request->year;

        // Get region info
        $region = Region::find($regionId);
        if (! $region) {
            throw new \Exception('Region not found');
        }

        // Get all active users in the region during the period
        $users = $this->getActiveUsersInRegion($regionId, $startDate, $endDate);

        // Initialize report data structure
        $reportData = [
            'region' => [
                'id' => $region->id,
                'name' => $region->name,
            ],
            'year' => $year,
            'mode' => $mode,
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate,
                'mode' => $dateRange['mode'],
            ],
            'users_data' => [],
            'summary' => [],
        ];

        // Process performance data based on mode
        switch ($mode) {
            case 'weekly':
                $reportData = $this->processWeeklySummaryData($users, $startDate, $endDate, $reportData);
                break;
            case 'monthly':
                $reportData = $this->processMonthlySummaryData($users, $startDate, $endDate, $reportData);
                break;
            case 'yearly':
                $reportData = $this->processYearlyData($users, $startDate, $endDate, $reportData);
                break;
        }

        // Add category summary
        $reportData['category_summary'] = $this->calculateCategorySummary($reportData['users_data']);

        // Add report type
        $reportData['report_type'] = 'region';

        return $reportData;
    }

    /**
     * Get overall report data
     */
    private function getOverallReportData(Request $request, array $dateRange)
    {
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];
        $mode = $this->mapModeForController($dateRange['mode']);
        $year = $request->year;

        // Get all active regions
        $regions = Region::where('is_active', true)->orderBy('name')->get();

        $reportData = [
            'year' => $year,
            'mode' => $mode,
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate,
                'mode' => $dateRange['mode'],
            ],
            'regions_data' => [],
            'summary' => [],
        ];

        $allUsersData = [];
        $allRegionsData = [];

        foreach ($regions as $region) {
            // Get users for this region
            $users = $this->getActiveUsersInRegion($region->id, $startDate, $endDate);

            if (empty($users)) {
                continue; // Skip regions with no active users
            }

            // Initialize region data
            $regionData = [
                'region' => [
                    'id' => $region->id,
                    'name' => $region->name,
                ],
                'users_data' => [],
                'summary' => [],
            ];

            // Process performance data based on mode
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
            $allUsersData = array_merge($allUsersData, $regionData['users_data']);
        }

        $reportData['regions_data'] = $allRegionsData;
        $reportData['users_data'] = $allUsersData;
        $reportData['category_summary'] = $this->calculateCategorySummary($allUsersData);
        $reportData['report_type'] = 'overall';

        return $reportData;
    }

    /**
     * Map date mode for existing controllers
     */
    private function mapModeForController($mode)
    {
        switch ($mode) {
            case 'weekly':
            case 'bimonthly':
            case 'custom':
                return 'weekly';
            case 'monthly':
                return 'monthly';
            case 'yearly':
                return 'yearly';
            default:
                return 'weekly';
        }
    }

    /**
     * Generate filename for export
     */
    private function generateFilename(array $reportData)
    {
        $filename = $reportData['report_type'] === 'region' ? 'Region_Report' : 'Overall_Report';

        $startDate = Carbon::parse($reportData['date_range']['start']);
        $endDate = Carbon::parse($reportData['date_range']['end']);

        // Format dates for filename
        if ($startDate->format('Y') === $endDate->format('Y')) {
            $dateStr = $startDate->format('M_d').'_to_'.$endDate->format('M_d').'_'.$startDate->format('Y');
        } else {
            $dateStr = $startDate->format('M_d_Y').'_to_'.$endDate->format('M_d_Y');
        }

        $filename .= '_'.$dateStr;

        return $filename.'.xlsx';
    }

    /**
     * Get available regions for export form
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
                    'id' => $region->id,
                    'name' => $region->name,
                    'description' => $region->description,
                    'user_count' => $userCount,
                ];
            });

        return response()->json([
            'success' => true,
            'regions' => $regions,
        ]);
    }

    /**
     * Get active users in region for the given period
     */
    private function getActiveUsersInRegion($regionId, $startDate, $endDate)
    {
        return DB::table('iva_user')
            ->where('region_id', $regionId)
            ->where('is_active', true)
            ->get()
            ->toArray();
    }

    /**
     * Process weekly summary data for users
     */
    private function processWeeklySummaryData($users, $startDate, $endDate, $reportData)
    {
        $usersData = [];

        foreach ($users as $user) {
            $userData = $this->generateUserPerformanceData($user, $startDate, $endDate, 'weekly');
            if ($userData) {
                $usersData[] = $userData;
            }
        }

        $reportData['users_data'] = $usersData;
        $reportData['summary'] = $this->calculateGroupSummary($usersData);

        return $reportData;
    }

    /**
     * Process monthly summary data for users
     */
    private function processMonthlySummaryData($users, $startDate, $endDate, $reportData)
    {
        $usersData = [];

        foreach ($users as $user) {
            $userData = $this->generateUserPerformanceData($user, $startDate, $endDate, 'monthly');
            if ($userData) {
                $usersData[] = $userData;
            }
        }

        $reportData['users_data'] = $usersData;
        $reportData['summary'] = $this->calculateGroupSummary($usersData);

        return $reportData;
    }

    /**
     * Process yearly data for users
     */
    private function processYearlyData($users, $startDate, $endDate, $reportData)
    {
        $usersData = [];

        foreach ($users as $user) {
            $userData = $this->generateUserPerformanceData($user, $startDate, $endDate, 'yearly');
            if ($userData) {
                $usersData[] = $userData;
            }
        }

        $reportData['users_data'] = $usersData;
        $reportData['summary'] = $this->calculateGroupSummary($usersData);

        return $reportData;
    }

    /**
     * Generate performance data for a single user
     */
    private function generateUserPerformanceData($userRow, $startDate, $endDate, $mode)
    {
        try {
            // Get the full IvaUser model
            $user = IvaUser::find($userRow->id);
            if (! $user) {
                return null;
            }

            // Get categories breakdown first
            $categories = $this->getUserCategoriesBreakdown($user->id, $startDate, $endDate);

            // Calculate basic metrics from worklog data
            $billableHours = 0;
            foreach ($categories as $category) {
                $billableHours += $category['hours'];
            }

            // Get non-billable hours
            $nonBillableHours = DB::table('worklogs_data')
                ->join('report_categories', 'worklogs_data.category_id', '=', 'report_categories.id')
                ->where('worklogs_data.user_id', $user->id)
                ->where('report_categories.category_type', 'non-billable')
                ->whereBetween('worklogs_data.date', [$startDate, $endDate])
                ->sum('worklogs_data.hours');

            $totalHours = $billableHours + $nonBillableHours;

            // Calculate target hours based on work status and period
            $targetHours = $this->calculateUserTargetHours($user, $startDate, $endDate);

            // Get NAD data
            $nadData = $this->getUserNADData($user->id, $startDate, $endDate);

            // Build performance metrics object
            $performanceMetrics = [
                'actual_total_billable_hours' => $billableHours,
                'actual_non_billable_hours' => $nonBillableHours,
                'actual_total_hours' => $totalHours,
                'target_total_hours' => $targetHours,
                'performance_percentage' => $targetHours > 0 ? ($billableHours / $targetHours) * 100 : 0,
                'nad_count' => $nadData['count'],
                'nad_hours' => $nadData['hours'],
            ];

            return [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'work_status' => $user->work_status ?: 'full-time',
                'billable_hours' => $billableHours,
                'non_billable_hours' => $nonBillableHours,
                'total_hours' => $totalHours,
                'target_hours' => $targetHours,
                'performance' => $performanceMetrics,
                'categories' => $categories,
                'nad_count' => $nadData['count'],
                'nad_hours' => $nadData['hours'],
                'region_id' => $user->region_id,
                'region_name' => $this->getRegionName($user->region_id),
            ];
        } catch (\Throwable $e) {
            Log::error('Error generating user performance data', [
                'user_id' => $userRow->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Get user categories breakdown
     */
    private function getUserCategoriesBreakdown($userId, $startDate, $endDate)
    {
        $categories = [];

        // Get all active report categories
        $reportCategories = ReportCategory::where('is_active', true)
            ->where('category_type', 'billable')
            ->orderBy('category_order')
            ->get();

        foreach ($reportCategories as $category) {
            // Get hours for this category from worklog data
            $hours = DB::table('worklogs_data')
                ->where('user_id', $userId)
                ->where('category_id', $category->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->sum('hours');

            $categories[] = [
                'id' => $category->id,
                'category_id' => $category->id,
                'category_name' => $category->category_name,
                'hours' => floatval($hours),
                'total_hours' => floatval($hours),
            ];
        }

        return $categories;
    }

    /**
     * Calculate group summary for multiple users
     */
    private function calculateGroupSummary($usersData)
    {
        $summary = [
            'total_users' => count($usersData),
            'full_time_users' => 0,
            'part_time_users' => 0,
            'total_billable_hours' => 0,
            'total_non_billable_hours' => 0,
            'total_hours' => 0,
            'average_performance' => 0,
        ];

        foreach ($usersData as $user) {
            if ($user['work_status'] === 'full-time') {
                $summary['full_time_users']++;
            } else {
                $summary['part_time_users']++;
            }

            $summary['total_billable_hours'] += $user['billable_hours'];
            $summary['total_non_billable_hours'] += $user['non_billable_hours'];
            $summary['total_hours'] += $user['total_hours'];
        }

        if ($summary['total_users'] > 0) {
            $summary['average_performance'] = $summary['total_billable_hours'] / $summary['total_users'];
        }

        return $summary;
    }

    /**
     * Calculate category summary
     */
    private function calculateCategorySummary($usersData)
    {
        $categorySummary = [];

        foreach ($usersData as $user) {
            if (isset($user['categories'])) {
                foreach ($user['categories'] as $category) {
                    $categoryId = $category['id'];
                    if (! isset($categorySummary[$categoryId])) {
                        $categorySummary[$categoryId] = [
                            'id' => $categoryId,
                            'category_name' => $category['category_name'],
                            'total_hours' => 0,
                            'user_count' => 0,
                        ];
                    }

                    $categorySummary[$categoryId]['total_hours'] += $category['hours'];
                    if ($category['hours'] > 0) {
                        $categorySummary[$categoryId]['user_count']++;
                    }
                }
            }
        }

        return array_values($categorySummary);
    }

    /**
     * Calculate user target hours for the given period
     */
    private function calculateUserTargetHours($user, $startDate, $endDate)
    {
        // Simple calculation: assume standard work hours based on work status
        $startCarbon = Carbon::parse($startDate);
        $endCarbon = Carbon::parse($endDate);
        $weeks = $startCarbon->diffInDays($endCarbon) / 7;

        $workStatus = $user->work_status ?: 'full-time';
        $hoursPerWeek = $workStatus === 'full-time' ? 40 : 20;

        return $weeks * $hoursPerWeek;
    }

    /**
     * Get user NAD (Not Available Days) data
     */
    private function getUserNADData($userId, $startDate, $endDate)
    {
        // For now, return empty data - this would need to be implemented based on your NAD tracking system
        return [
            'count' => 0,
            'hours' => 0,
        ];
    }

    /**
     * Get region name safely
     */
    private function getRegionName($regionId)
    {
        if (! $regionId) {
            return 'Unknown';
        }

        $region = DB::table('regions')->where('id', $regionId)->first();

        return $region ? $region->name : 'Unknown';
    }
}
