<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use App\Services\DailyWorklogSummaryService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DateTimeHelperTestController extends Controller
{
    /**
     * Display test results for date_time_helpers.php functions
     */
    public function index(Request $request)
    {
        $year = (int) $request->get('year', Carbon::now()->year);
        $testResults = [];

        try {
            // Test getWeekListForYear
            $testResults['getWeekListForYear'] = [
                'function' => 'getWeekListForYear',
                'parameters' => ['year' => $year],
                'result' => getWeekListForYear($year),
                'count' => count(getWeekListForYear($year)),
            ];

            // Test getMonthListForYear
            $testResults['getMonthListForYear'] = [
                'function' => 'getMonthListForYear',
                'parameters' => ['year' => $year],
                'result' => getMonthListForYear($year),
                'count' => count(getMonthListForYear($year)),
            ];

            // Test getCurrentWeek
            $testResults['getCurrentWeek'] = [
                'function' => 'getCurrentWeek',
                'parameters' => [],
                'result' => getCurrentWeek(),
            ];

            // Test getCurrentMonth
            $testResults['getCurrentMonth'] = [
                'function' => 'getCurrentMonth',
                'parameters' => [],
                'result' => getCurrentMonth(),
            ];

            // Test getLastWeek
            $testResults['getLastWeek'] = [
                'function' => 'getLastWeek',
                'parameters' => [],
                'result' => getLastWeek(),
            ];

            // Test getLastMonth
            $testResults['getLastMonth'] = [
                'function' => 'getLastMonth',
                'parameters' => [],
                'result' => getLastMonth(),
            ];

            // Test getCurrentWeekNumber
            $testResults['getCurrentWeekNumber'] = [
                'function' => 'getCurrentWeekNumber',
                'parameters' => [],
                'result' => getCurrentWeekNumber(),
            ];

            // Test getCurrentMonthNumber
            $testResults['getCurrentMonthNumber'] = [
                'function' => 'getCurrentMonthNumber',
                'parameters' => [],
                'result' => getCurrentMonthNumber(),
            ];

            // Test getWeekByNumber
            $weekNumber = $request->get('week_number', 1);
            $testResults['getWeekByNumber'] = [
                'function' => 'getWeekByNumber',
                'parameters' => ['week_number' => $weekNumber, 'year' => $year],
                'result' => getWeekByNumber($weekNumber, $year),
            ];

            // Test getMonthByNumber
            $monthNumber = $request->get('month_number', 1);
            $testResults['getMonthByNumber'] = [
                'function' => 'getMonthByNumber',
                'parameters' => ['month_number' => $monthNumber, 'year' => $year],
                'result' => getMonthByNumber($monthNumber, $year),
            ];

            // Test getDateRangeWeeks
            $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
            $testResults['getDateRangeWeeks'] = [
                'function' => 'getDateRangeWeeks',
                'parameters' => ['start_date' => $startDate, 'end_date' => $endDate, 'year' => $year],
                'result' => getDateRangeWeeks($startDate, $endDate, $year),
                'count' => count(getDateRangeWeeks($startDate, $endDate, $year)),
            ];

            // Test getDateRangeMonths
            $testResults['getDateRangeMonths'] = [
                'function' => 'getDateRangeMonths',
                'parameters' => ['start_date' => $startDate, 'end_date' => $endDate, 'year' => $year],
                'result' => getDateRangeMonths($startDate, $endDate, $year),
                'count' => count(getDateRangeMonths($startDate, $endDate, $year)),
            ];

        } catch (\Exception $e) {
            $testResults['error'] = [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];
        }

        return view('date-time-test', compact('testResults', 'year', 'weekNumber', 'monthNumber', 'startDate', 'endDate'));
    }

    /**
     * API endpoint for testing functions via AJAX
     */
    public function api(Request $request)
    {
        $function = $request->get('function');
        $year = (int) $request->get('year', Carbon::now()->year);
        $result = [];

        try {
            switch ($function) {
                case 'getWeekListForYear':
                    $result = getWeekListForYear($year);
                    break;
                case 'getMonthListForYear':
                    $result = getMonthListForYear($year);
                    break;
                case 'getCurrentWeek':
                    $result = getCurrentWeek();
                    break;
                case 'getCurrentMonth':
                    $result = getCurrentMonth();
                    break;
                case 'getLastWeek':
                    $result = getLastWeek();
                    break;
                case 'getLastMonth':
                    $result = getLastMonth();
                    break;
                case 'getCurrentWeekNumber':
                    $result = getCurrentWeekNumber();
                    break;
                case 'getCurrentMonthNumber':
                    $result = getCurrentMonthNumber();
                    break;
                case 'getWeekByNumber':
                    $weekNumber = $request->get('week_number', 1);
                    $result = getWeekByNumber($weekNumber, $year);
                    break;
                case 'getMonthByNumber':
                    $monthNumber = $request->get('month_number', 1);
                    $result = getMonthByNumber($monthNumber, $year);
                    break;
                case 'getDateRangeWeeks':
                    $startDate = $request->get('start_date');
                    $endDate = $request->get('end_date');
                    $result = getDateRangeWeeks($startDate, $endDate, $year);
                    break;
                case 'getDateRangeMonths':
                    $startDate = $request->get('start_date');
                    $endDate = $request->get('end_date');
                    $result = getDateRangeMonths($startDate, $endDate, $year);
                    break;
                default:
                    return response()->json(['error' => 'Invalid function name'], 400);
            }

            return response()->json([
                'function' => $function,
                'parameters' => $request->except(['function']),
                'result' => $result,
                'success' => true,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * Test DailyWorklogSummaryService calculateSummaries method
     */
    public function testDailyWorklogSummary(Request $request)
    {
        $userId = $request->get('user_id');
        $startDate = $request->get('start_date', Carbon::now()->subDays(7)->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->format('Y-m-d'));
        $calculateAll = $request->get('calculate_all', false);

        $testResult = [];

        try {
            $summaryService = new DailyWorklogSummaryService;

            $params = [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'calculate_all' => $calculateAll,
            ];

            if ($userId && ! $calculateAll) {
                $params['iva_user_ids'] = [$userId];
            }

            $result = $summaryService->calculateSummaries($params);

            $testResult = [
                'function' => 'DailyWorklogSummaryService::calculateSummaries',
                'parameters' => $params,
                'result' => $result,
                'success' => $result['success'] ?? false,
            ];

        } catch (\Exception $e) {
            $testResult = [
                'function' => 'DailyWorklogSummaryService::calculateSummaries',
                'parameters' => $params ?? [],
                'error' => $e->getMessage(),
                'success' => false,
            ];
        }

        return response()->json($testResult);
    }

    /**
     * Test fetchNADDataForUsers helper function
     */
    public function testNADData(Request $request)
    {
        $startDate = $request->get('start_date', Carbon::now()->subDays(7)->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->format('Y-m-d'));

        $testResult = [];
        $debugLogs = [];
        $executionStartTime = microtime(true);

        // Capture current log level to restore later
        $originalLogLevel = config('logging.level');

        try {
            // Enable debug logging temporarily for this test
            config(['logging.level' => 'debug']);

            $result = fetchNADDataForUsers($startDate, $endDate);

            $executionTime = round((microtime(true) - $executionStartTime) * 1000, 2);

            // Extract debug information if available
            $debugInfo = $result['debug_info'] ?? [];

            $testResult = [
                'function' => 'fetchNADDataForUsers',
                'parameters' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'result' => $result,
                'execution_time_ms' => $executionTime,
                'debug_summary' => [
                    'api_called' => true,
                    'api_url' => $debugInfo['nad_api_url'] ?? config('services.nad.url'),
                    'api_response_status' => $debugInfo['api_response_status'] ?? 'unknown',
                    'api_response_message' => $debugInfo['api_response_message'] ?? 'no message',
                    'nad_count' => $result['nad_count'] ?? 0,
                    'nad_hours' => $result['nad_hours'] ?? 0,
                    'has_data' => ! empty($result['nad_data']),
                    'request_timestamp' => $debugInfo['api_called_at'] ?? now()->toIsoString(),
                ],
                'success' => true,
            ];

            // If there are errors in the result, mark as partial success
            if (isset($result['debug_info']['api_response_status']) && $result['debug_info']['api_response_status'] === false) {
                $testResult['success'] = false;
                $testResult['warning'] = 'API call failed but function returned gracefully';
            }

        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $executionStartTime) * 1000, 2);

            $testResult = [
                'function' => 'fetchNADDataForUsers',
                'parameters' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'error' => $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ],
                'execution_time_ms' => $executionTime,
                'success' => false,
            ];
        } finally {
            // Restore original log level
            config(['logging.level' => $originalLogLevel]);
        }

        return response()->json($testResult);
    }

    /**
     * Test export data generation using ReportExportController
     */
    public function testExportData(Request $request)
    {
        try {
            $reportType = $request->input('report_type', 'overall');
            $reportPeriod = $request->input('report_period', 'weekly_summary');
            $year = $request->input('year', date('Y'));
            $startDate = $request->input('start_date', Carbon::now()->startOfWeek()->format('Y-m-d'));
            $endDate = $request->input('end_date', Carbon::now()->endOfWeek()->format('Y-m-d'));
            $regionId = $request->input('region_id');

            // Validate that we have required parameters for region reports
            if ($reportType === 'region' && empty($regionId)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Region ID is required for region reports',
                ]);
            }

            // Use the ReportExportController for consistent data generation
            $exportController = new \App\Http\Controllers\ReportExportController;

            // Build test request with additional fields required by export controller
            $testRequest = new Request([
                'report_type' => $reportType,
                'report_period' => $reportPeriod,
                'region_id' => $regionId,
                'year' => $year,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'month' => $request->input('month', date('n')),
                'bimonthly_date' => $request->input('bimonthly_date', 15),
            ]);

            // Use reflection to access the private prepareReportData method
            $reflectionClass = new \ReflectionClass($exportController);
            $prepareMethod = $reflectionClass->getMethod('generateReportData');
            $prepareMethod->setAccessible(true);

            // Generate the report data using the same logic as export
            $reportData = $prepareMethod->invoke($exportController, $testRequest);

            return response()->json([
                'success' => true,
                'data' => $reportData,
            ]);
            exit;
            if (! $reportData) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to generate report data',
                ]);
            }

            // Get task categories
            $taskCategories = \App\Models\ReportCategory::where('is_active', true)
                ->where('category_type', 'billable')
                ->orderBy('category_order')
                ->get();

            // Analyze the data structure
            $summary = [
                'report_type' => $reportType,
                'date_range' => $reportData['date_range'] ?? [
                    'start' => $startDate,
                    'end' => $endDate,
                    'mode' => $reportPeriod,
                ],
                'total_categories' => $taskCategories->count(),
                'metadata' => $reportData['metadata'] ?? [],
                'data_structure' => [],
            ];

            if ($reportType === 'overall') {
                $summary['total_regions'] = count($reportData['regions_data'] ?? []);
                $summary['total_users'] = count($reportData['users_data'] ?? []);

                // Count full-time and part-time users
                $fullTimeCount = 0;
                $partTimeCount = 0;
                foreach ($reportData['users_data'] ?? [] as $user) {
                    if (($user['work_status'] ?? 'full-time') === 'full-time') {
                        $fullTimeCount++;
                    } else {
                        $partTimeCount++;
                    }
                }
                $summary['full_time_users'] = $fullTimeCount;
                $summary['part_time_users'] = $partTimeCount;

                // Add regions breakdown
                $summary['regions_breakdown'] = [];
                foreach ($reportData['regions_data'] ?? [] as $regionData) {
                    $summary['regions_breakdown'][] = [
                        'name' => $regionData['region']['name'] ?? 'Unknown',
                        'user_count' => count($regionData['users_data'] ?? []),
                    ];
                }
            } else {
                $summary['region_name'] = $reportData['region']['name'] ?? 'Unknown';
                $summary['total_users'] = count($reportData['users_data'] ?? []);
            }

            // Examine first user structure to understand data format
            $firstUser = null;
            if (! empty($reportData['users_data'])) {
                $firstUser = $reportData['users_data'][0];
                $summary['user_data_structure'] = [
                    'has_categories' => isset($firstUser['categories']),
                    'category_count' => isset($firstUser['categories']) ? count($firstUser['categories']) : 0,
                    'category_sample' => isset($firstUser['categories']) ? array_slice($firstUser['categories'], 0, 2) : [],
                    'user_keys' => array_keys($firstUser),
                    'performance_keys' => isset($firstUser['performance']) ? array_keys($firstUser['performance']) : [],
                    'has_performance_data' => isset($firstUser['performance']),
                ];

                // Check category data structure
                if (isset($firstUser['categories']) && ! empty($firstUser['categories'])) {
                    $firstCategory = $firstUser['categories'][0];
                    $summary['category_data_structure'] = [
                        'category_keys' => array_keys($firstCategory),
                        'has_id' => isset($firstCategory['id']),
                        'has_category_id' => isset($firstCategory['category_id']),
                        'has_hours' => isset($firstCategory['hours']),
                        'has_total_hours' => isset($firstCategory['total_hours']),
                    ];
                }
            }

            // Add Excel export test preview
            $summary['excel_preview'] = [
                'would_generate_columns' => 3 + $taskCategories->count() + 8, // FT: No + Name + Non-billable + Categories + Billable + Performance columns
                'billable_categories' => $taskCategories->pluck('category_name')->toArray(),
                'estimated_rows' => 6 + count($reportData['users_data'] ?? []) + ($reportType === 'overall' ? count($reportData['regions_data'] ?? []) * 2 : 2), // Headers + Users + Region totals
            ];

            return response()->json([
                'success' => true,
                'data' => $reportData,
                'categories' => $taskCategories->toArray(),
                'summary' => $summary,
                'test_parameters' => [
                    'report_type' => $reportType,
                    'report_period' => $reportPeriod,
                    'year' => $year,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'region_id' => $regionId,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
