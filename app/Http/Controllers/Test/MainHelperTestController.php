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
        $weekNumber = $request->get('week_number', getCurrentWeekNumber());
        $monthNumber = $request->get('month_number', getCurrentMonthNumber());
        $year = $request->get('year', Carbon::now()->year);

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
                $testResults['calculateUserTargetHours'] = [
                    'function' => 'calculateUserTargetHours',
                    'parameters' => [
                        'user_id' => $user->id,
                        'user_name' => $user->full_name ?? $user->email,
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                    'result' => calculateUserTargetHours($user, $startDate, $endDate)
                ];
            } catch (\Exception $e) {
                $testResults['calculateUserTargetHours'] = [
                    'function' => 'calculateUserTargetHours',
                    'parameters' => [
                        'user_id' => $user->id,
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                    'error' => $e->getMessage()
                ];
            }

            try {
                // Test getTargetHoursForCurrentWeek
                $testResults['getTargetHoursForCurrentWeek'] = [
                    'function' => 'getTargetHoursForCurrentWeek',
                    'parameters' => [
                        'user_id' => $user->id,
                        'user_name' => $user->full_name ?? $user->email
                    ],
                    'result' => getTargetHoursForCurrentWeek($user)
                ];
            } catch (\Exception $e) {
                $testResults['getTargetHoursForCurrentWeek'] = [
                    'function' => 'getTargetHoursForCurrentWeek',
                    'parameters' => ['user_id' => $user->id],
                    'error' => $e->getMessage()
                ];
            }

            try {
                // Test getTargetHoursForCurrentMonth
                $testResults['getTargetHoursForCurrentMonth'] = [
                    'function' => 'getTargetHoursForCurrentMonth',
                    'parameters' => [
                        'user_id' => $user->id,
                        'user_name' => $user->full_name ?? $user->email
                    ],
                    'result' => getTargetHoursForCurrentMonth($user)
                ];
            } catch (\Exception $e) {
                $testResults['getTargetHoursForCurrentMonth'] = [
                    'function' => 'getTargetHoursForCurrentMonth',
                    'parameters' => ['user_id' => $user->id],
                    'error' => $e->getMessage()
                ];
            }

            try {
                // Test getTargetHoursForLastWeek
                $testResults['getTargetHoursForLastWeek'] = [
                    'function' => 'getTargetHoursForLastWeek',
                    'parameters' => [
                        'user_id' => $user->id,
                        'user_name' => $user->full_name ?? $user->email
                    ],
                    'result' => getTargetHoursForLastWeek($user)
                ];
            } catch (\Exception $e) {
                $testResults['getTargetHoursForLastWeek'] = [
                    'function' => 'getTargetHoursForLastWeek',
                    'parameters' => ['user_id' => $user->id],
                    'error' => $e->getMessage()
                ];
            }

            try {
                // Test getTargetHoursForLastMonth
                $testResults['getTargetHoursForLastMonth'] = [
                    'function' => 'getTargetHoursForLastMonth',
                    'parameters' => [
                        'user_id' => $user->id,
                        'user_name' => $user->full_name ?? $user->email
                    ],
                    'result' => getTargetHoursForLastMonth($user)
                ];
            } catch (\Exception $e) {
                $testResults['getTargetHoursForLastMonth'] = [
                    'function' => 'getTargetHoursForLastMonth',
                    'parameters' => ['user_id' => $user->id],
                    'error' => $e->getMessage()
                ];
            }

            try {
                // Test getTargetHoursForWeekNumber
                $testResults['getTargetHoursForWeekNumber'] = [
                    'function' => 'getTargetHoursForWeekNumber',
                    'parameters' => [
                        'user_id' => $user->id,
                        'user_name' => $user->full_name ?? $user->email,
                        'week_number' => $weekNumber,
                        'year' => $year
                    ],
                    'result' => getTargetHoursForWeekNumber($user, $weekNumber, $year)
                ];
            } catch (\Exception $e) {
                $testResults['getTargetHoursForWeekNumber'] = [
                    'function' => 'getTargetHoursForWeekNumber',
                    'parameters' => [
                        'user_id' => $user->id,
                        'week_number' => $weekNumber,
                        'year' => $year
                    ],
                    'error' => $e->getMessage()
                ];
            }

            try {
                // Test getTargetHoursForMonthNumber
                $testResults['getTargetHoursForMonthNumber'] = [
                    'function' => 'getTargetHoursForMonthNumber',
                    'parameters' => [
                        'user_id' => $user->id,
                        'user_name' => $user->full_name ?? $user->email,
                        'month_number' => $monthNumber,
                        'year' => $year
                    ],
                    'result' => getTargetHoursForMonthNumber($user, $monthNumber, $year)
                ];
            } catch (\Exception $e) {
                $testResults['getTargetHoursForMonthNumber'] = [
                    'function' => 'getTargetHoursForMonthNumber',
                    'parameters' => [
                        'user_id' => $user->id,
                        'month_number' => $monthNumber,
                        'year' => $year
                    ],
                    'error' => $e->getMessage()
                ];
            }

            try {
                // Test getTargetHoursSummaryForUser
                $testResults['getTargetHoursSummaryForUser'] = [
                    'function' => 'getTargetHoursSummaryForUser',
                    'parameters' => [
                        'user_id' => $user->id,
                        'user_name' => $user->full_name ?? $user->email
                    ],
                    'result' => getTargetHoursSummaryForUser($user)
                ];
            } catch (\Exception $e) {
                $testResults['getTargetHoursSummaryForUser'] = [
                    'function' => 'getTargetHoursSummaryForUser',
                    'parameters' => ['user_id' => $user->id],
                    'error' => $e->getMessage()
                ];
            }

            try {
                // Test calculateTargetHoursForMultipleUsers
                $multipleUsers = IvaUser::take(3)->get();
                $testResults['calculateTargetHoursForMultipleUsers'] = [
                    'function' => 'calculateTargetHoursForMultipleUsers',
                    'parameters' => [
                        'user_count' => $multipleUsers->count(),
                        'user_ids' => $multipleUsers->pluck('id')->toArray(),
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                    'result' => calculateTargetHoursForMultipleUsers($multipleUsers, $startDate, $endDate)
                ];
            } catch (\Exception $e) {
                $testResults['calculateTargetHoursForMultipleUsers'] = [
                    'function' => 'calculateTargetHoursForMultipleUsers',
                    'parameters' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
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
            'weekNumber', 
            'monthNumber', 
            'year',
            'availableUsers',
            'user'
        ));
    }
}