<?php

namespace App\Http\Controllers\Test;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use App\Services\DailyWorklogSummaryService;

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
                'count' => count(getWeekListForYear($year))
            ];

            // Test getMonthListForYear
            $testResults['getMonthListForYear'] = [
                'function' => 'getMonthListForYear',
                'parameters' => ['year' => $year],
                'result' => getMonthListForYear($year),
                'count' => count(getMonthListForYear($year))
            ];

            // Test getCurrentWeek
            $testResults['getCurrentWeek'] = [
                'function' => 'getCurrentWeek',
                'parameters' => [],
                'result' => getCurrentWeek()
            ];

            // Test getCurrentMonth
            $testResults['getCurrentMonth'] = [
                'function' => 'getCurrentMonth',
                'parameters' => [],
                'result' => getCurrentMonth()
            ];

            // Test getLastWeek
            $testResults['getLastWeek'] = [
                'function' => 'getLastWeek',
                'parameters' => [],
                'result' => getLastWeek()
            ];

            // Test getLastMonth
            $testResults['getLastMonth'] = [
                'function' => 'getLastMonth',
                'parameters' => [],
                'result' => getLastMonth()
            ];

            // Test getCurrentWeekNumber
            $testResults['getCurrentWeekNumber'] = [
                'function' => 'getCurrentWeekNumber',
                'parameters' => [],
                'result' => getCurrentWeekNumber()
            ];

            // Test getCurrentMonthNumber
            $testResults['getCurrentMonthNumber'] = [
                'function' => 'getCurrentMonthNumber',
                'parameters' => [],
                'result' => getCurrentMonthNumber()
            ];

            // Test getWeekByNumber
            $weekNumber = $request->get('week_number', 1);
            $testResults['getWeekByNumber'] = [
                'function' => 'getWeekByNumber',
                'parameters' => ['week_number' => $weekNumber, 'year' => $year],
                'result' => getWeekByNumber($weekNumber, $year)
            ];

            // Test getMonthByNumber
            $monthNumber = $request->get('month_number', 1);
            $testResults['getMonthByNumber'] = [
                'function' => 'getMonthByNumber',
                'parameters' => ['month_number' => $monthNumber, 'year' => $year],
                'result' => getMonthByNumber($monthNumber, $year)
            ];

            // Test getDateRangeWeeks
            $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
            $testResults['getDateRangeWeeks'] = [
                'function' => 'getDateRangeWeeks',
                'parameters' => ['start_date' => $startDate, 'end_date' => $endDate, 'year' => $year],
                'result' => getDateRangeWeeks($startDate, $endDate, $year),
                'count' => count(getDateRangeWeeks($startDate, $endDate, $year))
            ];

            // Test getDateRangeMonths
            $testResults['getDateRangeMonths'] = [
                'function' => 'getDateRangeMonths',
                'parameters' => ['start_date' => $startDate, 'end_date' => $endDate, 'year' => $year],
                'result' => getDateRangeMonths($startDate, $endDate, $year),
                'count' => count(getDateRangeMonths($startDate, $endDate, $year))
            ];

        } catch (\Exception $e) {
            $testResults['error'] = [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'success' => false
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
            $summaryService = new DailyWorklogSummaryService();
            
            $params = [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'calculate_all' => $calculateAll
            ];

            if ($userId && !$calculateAll) {
                $params['iva_user_ids'] = [$userId];
            }

            $result = $summaryService->calculateSummaries($params);

            $testResult = [
                'function' => 'DailyWorklogSummaryService::calculateSummaries',
                'parameters' => $params,
                'result' => $result,
                'success' => $result['success'] ?? false
            ];

        } catch (\Exception $e) {
            $testResult = [
                'function' => 'DailyWorklogSummaryService::calculateSummaries',
                'parameters' => $params ?? [],
                'error' => $e->getMessage(),
                'success' => false
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
                    'end_date' => $endDate
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
                    'has_data' => !empty($result['nad_data']),
                    'request_timestamp' => $debugInfo['api_called_at'] ?? now()->toIsoString(),
                ],
                'success' => true
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
                    'end_date' => $endDate
                ],
                'error' => $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ],
                'execution_time_ms' => $executionTime,
                'success' => false
            ];
        } finally {
            // Restore original log level
            config(['logging.level' => $originalLogLevel]);
        }

        return response()->json($testResult);
    }
}