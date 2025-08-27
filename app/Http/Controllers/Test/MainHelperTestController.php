<?php

namespace App\Http\Controllers\Test;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\IvaUser;
use App\Http\Controllers\Controller;

class MainHelperTestController extends Controller
{
    /**
     * Display test results for main_helper.php functions
     */
    public function index(Request $request)
    {
        $testResults = [];
        $errors = [];

        // Get test parameters
        $userId = $request->get('user_id', 1);
        $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
        $categoryId = $request->get('category_id', 1);

        // Try to get a test user
        $user = null;
        try {
            $user = IvaUser::find($userId) ?: IvaUser::first();
            if (!$user) {
                $errors[] = "No IvaUser found in database. Please create a test user first.";
            }
        } catch (\Exception $e) {
            $errors[] = "Error fetching user: " . $e->getMessage();
        }

        if ($user) {
            try {
                // Test calculateUserTargetHours
                $startTime = microtime(true);
                $result = calculateUserTargetHours($user, $startDate, $endDate);
                $executionTime = microtime(true) - $startTime;
                
                $testResults['calculateUserTargetHours'] = [
                    'function' => 'calculateUserTargetHours',
                    'parameters' => [
                        'user_id' => $user->id,
                        'user_name' => $user->full_name ?? $user->email,
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                    'execution_time_ms' => round($executionTime * 1000, 2),
                    'result' => $result
                ];
            } catch (\Exception $e) {
                $testResults['calculateUserTargetHours'] = [
                    'function' => 'calculateUserTargetHours',
                    'parameters' => [
                        'user_id' => $user->id,
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                    'execution_time_ms' => 0,
                    'error' => $e->getMessage()
                ];
            }

            try {
                // Test calculateBasicMetricsFromDailySummaries
                $startTime = microtime(true);
                $result = calculateBasicMetricsFromDailySummaries($user->id, $startDate, $endDate);
                $executionTime = microtime(true) - $startTime;
                
                $testResults['calculateBasicMetricsFromDailySummaries'] = [
                    'function' => 'calculateBasicMetricsFromDailySummaries',
                    'parameters' => [
                        'iva_id' => $user->id,
                        'user_name' => $user->full_name ?? $user->email,
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                    'execution_time_ms' => round($executionTime * 1000, 2),
                    'result' => $result
                ];
            } catch (\Exception $e) {
                $testResults['calculateBasicMetricsFromDailySummaries'] = [
                    'function' => 'calculateBasicMetricsFromDailySummaries',
                    'parameters' => [
                        'iva_id' => $user->id,
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                    'execution_time_ms' => 0,
                    'error' => $e->getMessage()
                ];
            }

            try {
                // Test calculateDailyBreakdownFromSummaries
                $startTime = microtime(true);
                $result = calculateDailyBreakdownFromSummaries($user->id, $startDate, $endDate);
                $executionTime = microtime(true) - $startTime;
                
                $testResults['calculateDailyBreakdownFromSummaries'] = [
                    'function' => 'calculateDailyBreakdownFromSummaries',
                    'parameters' => [
                        'iva_id' => $user->id,
                        'user_name' => $user->full_name ?? $user->email,
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                    'execution_time_ms' => round($executionTime * 1000, 2),
                    'result' => $result
                ];
            } catch (\Exception $e) {
                $testResults['calculateDailyBreakdownFromSummaries'] = [
                    'function' => 'calculateDailyBreakdownFromSummaries',
                    'parameters' => [
                        'iva_id' => $user->id,
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                    'execution_time_ms' => 0,
                    'error' => $e->getMessage()
                ];
            }

            try {
                // Test calculatePerformanceMetricsDailySummaries
                $startTime = microtime(true);
                $result = calculatePerformanceMetricsDailySummaries($user, $startDate, $endDate);
                $executionTime = microtime(true) - $startTime;
                
                $testResults['calculatePerformanceMetricsDailySummaries'] = [
                    'function' => 'calculatePerformanceMetricsDailySummaries',
                    'parameters' => [
                        'user_id' => $user->id,
                        'user_name' => $user->full_name ?? $user->email,
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                    'execution_time_ms' => round($executionTime * 1000, 2),
                    'result' => $result
                ];
            } catch (\Exception $e) {
                $testResults['calculatePerformanceMetricsDailySummaries'] = [
                    'function' => 'calculatePerformanceMetricsDailySummaries',
                    'parameters' => [
                        'user_id' => $user->id,
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                    'execution_time_ms' => 0,
                    'error' => $e->getMessage()
                ];
            }

            try {
                // Test calculateCategoryBreakdownFromSummaries
                $startTime = microtime(true);
                $result = calculateCategoryBreakdownFromSummaries($user->id, $startDate, $endDate);
                $executionTime = microtime(true) - $startTime;
                
                $testResults['calculateCategoryBreakdownFromSummaries'] = [
                    'function' => 'calculateCategoryBreakdownFromSummaries',
                    'parameters' => [
                        'iva_id' => $user->id,
                        'user_name' => $user->full_name ?? $user->email,
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                    'execution_time_ms' => round($executionTime * 1000, 2),
                    'result' => $result
                ];
            } catch (\Exception $e) {
                $testResults['calculateCategoryBreakdownFromSummaries'] = [
                    'function' => 'calculateCategoryBreakdownFromSummaries',
                    'parameters' => [
                        'iva_id' => $user->id,
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                    'execution_time_ms' => 0,
                    'error' => $e->getMessage()
                ];
            }

            try {
                // Test getTasksByReportCategory - using first available category ID
                $categoryId = $request->get('category_id', 1);
                $startTime = microtime(true);
                $result = getTasksByReportCategory($categoryId, $user->id, $startDate, $endDate);
                $executionTime = microtime(true) - $startTime;
                
                $testResults['getTasksByReportCategory'] = [
                    'function' => 'getTasksByReportCategory',
                    'parameters' => [
                        'report_category_id' => $categoryId,
                        'iva_id' => $user->id,
                        'user_name' => $user->full_name ?? $user->email,
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                    'execution_time_ms' => round($executionTime * 1000, 2),
                    'result' => $result
                ];
            } catch (\Exception $e) {
                $testResults['getTasksByReportCategory'] = [
                    'function' => 'getTasksByReportCategory',
                    'parameters' => [
                        'report_category_id' => $request->get('category_id', 1),
                        'iva_id' => $user->id,
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                    'execution_time_ms' => 0,
                    'error' => $e->getMessage()
                ];
            }

            try {
                // Test calculateUserTargetHoursOptimized - Performance optimized version
                $startTime = microtime(true);
                $result = calculateUserTargetHoursOptimized($user, $startDate, $endDate);
                $executionTime = microtime(true) - $startTime;
                
                $testResults['calculateUserTargetHoursOptimized'] = [
                    'function' => 'calculateUserTargetHoursOptimized',
                    'parameters' => [
                        'user_id' => $user->id,
                        'user_name' => $user->full_name ?? $user->email,
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                    'execution_time_ms' => round($executionTime * 1000, 2),
                    'result' => $result
                ];
            } catch (\Exception $e) {
                $testResults['calculateUserTargetHoursOptimized'] = [
                    'function' => 'calculateUserTargetHoursOptimized',
                    'parameters' => [
                        'user_id' => $user->id,
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                    'execution_time_ms' => 0,
                    'error' => $e->getMessage()
                ];
            }
        }

        // Get available users for dropdown
        $availableUsers = [];
        try {
            $availableUsers = IvaUser::select('id', 'email', 'full_name')->take(10)->get();
        } catch (\Exception $e) {
            $errors[] = "Error fetching available users: " . $e->getMessage();
        }

        return view('main-helper-test', compact(
            'testResults', 
            'errors', 
            'userId', 
            'startDate', 
            'endDate', 
            'categoryId',
            'availableUsers',
            'user'
        ));
    }
}