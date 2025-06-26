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
            'year'           => 'nullable|integer|min:2024|max:2030',
            'week_number'    => 'nullable|integer|min:1|max:52',
            'week_count'     => 'nullable|integer|min:1|max:12',
            'month'          => 'nullable|integer|min:1|max:12',
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

        // get NAD Data
        $data = [
            'start_date' => $originalStartDate,
            'end_date'   => $endDate,
            'blab_only'  => 1,
            'email_list' => [$user->email], // Use user's email for NAD API
        ];

        // Apply start date adjustment logic
        $dateAdjustment = ivaAdjustStartDate($user, $originalStartDate, $endDate);
        $startDate      = $dateAdjustment['adjusted_start_date'];

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
            ->with(['project', 'task'])
            ->orderBy('start_time')
            ->get();
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
            // 'nad_data'           => $NADData,
            // 'date_range'         => [
            //     'start'               => $startDate,
            //     'end'                 => $endDate,
            //     'original_start'      => $originalStartDate,
            //     'start_date_adjusted' => $dateAdjustment['hire_date_used'],
            //     'days_count'          => $daysDiff + 1,
            //     'mode'                => $this->getDateMode($request),
            // ],
            // 'basic_metrics'      => calculateBasicMetrics($worklogs),
            // 'daily_breakdown'    => calculateDailyBreakdown($worklogs, $startDate, $endDate),
            // 'category_breakdown' => calculateCategoryBreakdown($worklogs),
        ];

        // Add performance data only for weekly reports
        if ($this->getDateMode($request) === 'weeks') {
            // Check if we have at least 1 week of data for performance calculation
            if (! $dateAdjustment['is_valid_week_range']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient date range for weekly performance calculation. At least 1 week is required.',
                    'details' => [
                        'days_available'   => $dateAdjustment['days_difference'],
                        'minimum_required' => 7,
                    ],
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

        // Add bimonthly data if needed
        if ($this->getDateMode($request) === 'bimonthly') {
            $dashboardData['bimonthly_data'] = calculateBimonthlyData(
                $user,
                $request->input('year'),
                $request->input('month'),
                $request->input('bimonthly_date', 15)
            );
        } else {
            $responseNAD = callNADApi('get_nad_by_date_range', $data);
            $NADData     = [];

            if (! empty($responseNAD['status']) && $responseNAD['status'] === true && ! empty($responseNAD['data']) && count($responseNAD['data']) > 0) {
                $NADData = collect($responseNAD['data'])->firstWhere('email', $user->email) ?? [];
            }
            $dashboardData['basic_metrics'] = calculateBasicMetrics($worklogs);
            $dashboardData['nad_data']      = $NADData;
            $dashboardData['date_range']    = [
                'start'               => $startDate,
                'end'                 => $endDate,
                'original_start'      => $originalStartDate,
                'start_date_adjusted' => $dateAdjustment['hire_date_used'],
                'days_count'          => $daysDiff + 1,
                'mode'                => $this->getDateMode($request),
            ];
            $dashboardData['daily_breakdown']    = calculateDailyBreakdown($worklogs, $startDate, $endDate);
            $dashboardData['category_breakdown'] = calculateCategoryBreakdown($worklogs);
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
                'start_date_adjusted' => $dateAdjustment['hire_date_used'],
                'total_hours'         => $this->getDateMode($request) != 'bimonthly' ? $dashboardData['basic_metrics']['billable_hours'] : $dashboardData['bimonthly_data']['first_half']['basic_metrics']['billable_hours'] +
                $dashboardData['bimonthly_data']['second_half']['basic_metrics']['billable_hours'],
                'date_mode'           => $this->getDateMode($request),
                'days_span'           => $daysDiff + 1,
            ]
        );

        return response()->json([
            'success'   => true,
            'dashboard' => $dashboardData,
        ]);
    }

    /**
     * Get date mode from request.
     */
    private function getDateMode(Request $request)
    {
        if ($request->has('year') && $request->has('week_number')) {
            return 'weeks';
        } elseif ($request->has('year') && $request->has('month') && $request->has('bimonthly_date')) {
            return 'bimonthly';
        } elseif ($request->has('year') && $request->has('month')) {
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
