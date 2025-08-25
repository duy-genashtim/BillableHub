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
        $year = $request->get('year', Carbon::now()->year);
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
        $year = $request->get('year', Carbon::now()->year);
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
}