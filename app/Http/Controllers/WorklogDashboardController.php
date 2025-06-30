<?php
namespace App\Http\Controllers;

use App\Models\IvaUser;
use App\Models\WorklogsData;
use App\Services\ActivityLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

        // $dateAdjustment = ivaAdjustStartDate($user, $originalStartDate, $endDate);
        $isSetMonday    = in_array($this->getDateMode($request), ['weeks', 'weekly_summary', 'month_summary']);
        $dateAdjustment = ivaAdjustStartDate($user, $originalStartDate, $endDate, $isSetMonday);
        $startDate      = $dateAdjustment['adjusted_start_date'];
        // dd($request->all(), $originalStartDate, $endDate, $dateAdjustment);
        // Validate date range (max 13 months)
        $daysDiff = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
        if ($daysDiff > 395) {
            return response()->json([
                'success' => false,
                'message' => 'Date range cannot exceed 13 months (395 days)',
            ], 422);
        }

        // Get worklogs for the specified period
        $worklogs = WorklogsData::where('iva_id', $id)
            ->where('is_active', true)
            ->whereBetween('start_time', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                         // ->with(['project', 'task'])
            ->with(['task']) // Only include task as project is not needed
            ->orderBy('start_time')
            ->get();
        // dd($worklogs->toArray());
        // Get work status changes during the period to handle performance calculations
        $workStatusChanges = getWorkStatusChanges($user, $startDate, $endDate);

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
            $dashboardData['bimonthly_data'] = calculateBimonthlyData(
                $user,
                $request->input('year'),
                $request->input('month'),
                $request->input('bimonthly_date', 15)
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

            $dashboardData['weekly_summary_data'] = calculateWeeklySummaryData(
                $user,
                $worklogs,
                $startDate,
                $endDate,
                $request->input('year'),
                $request->input('week_number'),
                $request->input('week_count', 1),
                $workStatusChanges
            );
            $dashboardData['weekly_summary_data']['target_performances'] = calculateTargetPerformancesForUser(
                $user,
                $worklogs,
                $startDate,
                $endDate,
                $workStatusChanges
            );
        } elseif ($dateMode === 'month_summary') {
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
            // Handle monthly summary mode
            $dashboardData['monthly_summary_data'] = calculateMonthlySummaryData(
                $user,
                $worklogs,
                $dateAdjustment['original_start_date'],
                $endDate,
                $request->input('year'),
                $request->input('month'),
                $request->input('month_count', 1),
                $workStatusChanges
            );
            $dashboardData['monthly_summary_data']['target_performances'] = calculateTargetPerformancesForUser(
                $user,
                $worklogs,
                $startDate,
                $endDate,
                $workStatusChanges
            );
        } else {
            // Regular mode (weeks, monthly, custom)
            // $data = [
            //     'start_date' => $originalStartDate,
            //     'end_date'   => $endDate,
            //     'blab_only'  => 1,
            //     'email_list' => [$user->email], // Use user's email for NAD API
            // ];

            // $responseNAD = callNADApi('get_nad_by_date_range', $data);
            // $NADData     = [];

            // if (! empty($responseNAD['status']) && $responseNAD['status'] === true && ! empty($responseNAD['data']) && count($responseNAD['data']) > 0) {
            //     $NADData = collect($responseNAD['data'])->firstWhere('email', $user->email) ?? [];
            // }
            $nadDataResult = fetchNADDataForPeriod($user, $startDate, $endDate);

            $dashboardData['basic_metrics'] = calculateBasicMetrics($worklogs);
            $dashboardData['nad_data']      = $nadDataResult['nad_data'];
            $dashboardData['date_range']    = [
                'start'               => $startDate,
                'end'                 => $endDate,
                'original_start'      => $originalStartDate,
                'start_date_adjusted' => $dateAdjustment['changed_start_date'],
                'days_count'          => $daysDiff + 1,
                'mode'                => $dateMode,
            ];
            $dashboardData['daily_breakdown']    = calculateDailyBreakdown($worklogs, $startDate, $endDate);
            $dashboardData['category_breakdown'] = calculateCategoryBreakdown($worklogs);

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
                    $worklogs,
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

        return response()->json([
            'success'   => true,
            'dashboard' => $dashboardData,
        ]);
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
