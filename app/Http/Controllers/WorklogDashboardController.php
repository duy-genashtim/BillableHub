<?php
namespace App\Http\Controllers;

use App\Models\ConfigurationSetting;
use App\Models\ConfigurationSettingType;
use App\Models\IvaUser;
use App\Models\IvaUserChangelog;
use App\Models\IvaUserCustomize;
use App\Models\TaskReportCategory;
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
            'bimonthly_part' => 'nullable|string|in:first,second',
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
        $dateAdjustment = ivaAdjustStartDate($user, $originalStartDate, $endDate);
        $startDate      = $dateAdjustment['adjusted_start_date'];

        // Validate date range (max 3 months)
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
        $workStatusChanges = $this->getWorkStatusChanges($user, $startDate, $endDate);

        // Calculate dashboard metrics
        $dashboardData = [
            'user'               => [
                'id'                 => $user->id,
                'name'               => $user->full_name,
                'work_status'        => $user->work_status,
                'region'             => $user->region ? $user->region->name : null,
                'timedoctor_version' => $user->timedoctor_version,
            ],
            'date_range'         => [
                'start'               => $startDate,
                'end'                 => $endDate,
                'original_start'      => $originalStartDate,
                'start_date_adjusted' => $dateAdjustment['hire_date_used'],
                'days_count'          => $daysDiff + 1,
                'mode'                => $this->getDateMode($request),
            ],
            'basic_metrics'      => $this->calculateBasicMetrics($worklogs),
            'daily_breakdown'    => $this->calculateDailyBreakdown($worklogs, $startDate, $endDate),
            'category_breakdown' => $this->calculateCategoryBreakdown($worklogs),
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

            $dashboardData['target_performances'] = $this->calculateTargetPerformances(
                $user,
                $worklogs,
                $startDate,
                $endDate,
                $workStatusChanges
            );
        }

        // Add bimonthly data if needed
        if ($this->getDateMode($request) === 'bimonthly') {
            $dashboardData['bimonthly_data'] = $this->calculateBimonthlyData(
                $user,
                $request->input('year'),
                $request->input('month'),
                $request->input('bimonthly_date', 15)
            );
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
                'total_hours'         => $dashboardData['basic_metrics']['billable_hours'] + $dashboardData['basic_metrics']['non_billable_hours'],
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
     * Calculate bimonthly data for first and second half of month.
     */
    private function calculateBimonthlyData($user, $year, $month, $splitDate = 15)
    {
        // First half: 1st to splitDate
        $firstHalfStart = Carbon::create($year, $month, 1)->startOfDay();
        $firstHalfEnd   = Carbon::create($year, $month, $splitDate)->endOfDay();

        // Second half: (splitDate + 1) to end of month
        $secondHalfStart = Carbon::create($year, $month, $splitDate + 1)->startOfDay();
        $secondHalfEnd   = Carbon::create($year, $month)->endOfMonth();

        // Get worklogs for each half
        $firstHalfWorklogs = WorklogsData::where('iva_id', $user->id)
            ->where('is_active', true)
            ->whereBetween('start_time', [$firstHalfStart, $firstHalfEnd])
            ->with(['project', 'task'])
            ->get();

        $secondHalfWorklogs = WorklogsData::where('iva_id', $user->id)
            ->where('is_active', true)
            ->whereBetween('start_time', [$secondHalfStart, $secondHalfEnd])
            ->with(['project', 'task'])
            ->get();

        return [
            'first_half'  => [
                'date_range'         => [
                    'start' => $firstHalfStart->format('Y-m-d'),
                    'end'   => $firstHalfEnd->format('Y-m-d'),
                ],
                'basic_metrics'      => $this->calculateBasicMetrics($firstHalfWorklogs),
                'daily_breakdown'    => $this->calculateDailyBreakdown($firstHalfWorklogs, $firstHalfStart->format('Y-m-d'), $firstHalfEnd->format('Y-m-d')),
                'category_breakdown' => $this->calculateCategoryBreakdown($firstHalfWorklogs),
            ],
            'second_half' => [
                'date_range'         => [
                    'start' => $secondHalfStart->format('Y-m-d'),
                    'end'   => $secondHalfEnd->format('Y-m-d'),
                ],
                'basic_metrics'      => $this->calculateBasicMetrics($secondHalfWorklogs),
                'daily_breakdown'    => $this->calculateDailyBreakdown($secondHalfWorklogs, $secondHalfStart->format('Y-m-d'), $secondHalfEnd->format('Y-m-d')),
                'category_breakdown' => $this->calculateCategoryBreakdown($secondHalfWorklogs),
            ],
        ];
    }

    /**
     * Get work status changes during the specified period.
     */
    private function getWorkStatusChanges($user, $startDate, $endDate)
    {
        return IvaUserChangelog::where('iva_user_id', $user->id)
            ->where('field_changed', 'work_status')
            ->whereBetween('effective_date', [$startDate, $endDate])
            ->orderBy('effective_date')
            ->get();
    }

    private function getAllWorkStatusChanges($user)
    {
        return IvaUserChangelog::where('iva_user_id', $user->id)
            ->where('field_changed', 'work_status')
            ->orderBy('effective_date')
            ->get();
    }

    /**
     * Get date range based on request parameters.
     */
    private function getDateRange(Request $request)
    {
        $mode = $this->getDateMode($request);

        switch ($mode) {
            case 'weeks':
                return $this->getWeeklyDateRange($request);

            case 'monthly':
                return $this->getMonthlyDateRange($request);

            case 'bimonthly':
                return $this->getBimonthlyDateRange($request);

            case 'custom':
            default:
                return $this->getCustomDateRange($request);
        }
    }

    /**
     * Get date mode from request.
     */
    private function getDateMode(Request $request)
    {
        if ($request->has('year') && $request->has('week_number')) {
            return 'weeks';
        } elseif ($request->has('year') && $request->has('month') && ($request->has('bimonthly_date') || $request->has('bimonthly_part'))) {
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
     * Get bimonthly date range for the selected part.
     */
    private function getBimonthlyDateRange(Request $request)
    {
        // Use start_date and end_date from request if available
        if ($request->has('start_date') && $request->has('end_date')) {
            return [
                'start' => $request->input('start_date'),
                'end'   => $request->input('end_date'),
            ];
        }

        $year          = $request->input('year');
        $month         = $request->input('month');
        $bimonthlyDate = $request->input('bimonthly_date', 15);
        $bimonthlyPart = $request->input('bimonthly_part', 'first');

        if ($bimonthlyPart === 'first') {
            // First half: 1st to splitDate
            $startDate = Carbon::create($year, $month, 1)->startOfDay();
            $endDate   = Carbon::create($year, $month, $bimonthlyDate)->endOfDay();
        } else {
            // Second half: (splitDate + 1) to end of month
            $startDate = Carbon::create($year, $month, $bimonthlyDate + 1)->startOfDay();
            $endDate   = Carbon::create($year, $month)->endOfMonth();
        }

        return [
            'start' => $startDate->toDateString(),
            'end'   => $endDate->toDateString(),
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

    /**
     * Calculate target performances with support for multiple work hour settings.
     */
    // private function calculateTargetPerformances($user, $worklogs, $startDate, $endDate, $workStatusChanges)
    // {
    //     $billableHours = $worklogs->filter(function ($worklog) {
    //         return $this->isTaskBillable($worklog->task);
    //     })->sum('duration') / 3600;

    //     $workStatusPeriods = $this->calculateWorkStatusPeriods($user, $startDate, $endDate, $workStatusChanges);

    //     // Group by target_id and sum their respective target_total_hours
    //     $targetsById = [];

    //     foreach ($workStatusPeriods as $period) {
    //         $workStatus  = $period['work_status'];
    //         $periodDays  = $period['days'];
    //         $periodWeeks = $periodDays / 7;

    //         $hourSettings = $this->getWorkHourSettings($user, $workStatus);

    //         foreach ($hourSettings as $setting) {
    //             $targetId           = $setting['id'];
    //             $targetHoursPerWeek = $setting['hours'];
    //             $targetHours        = $targetHoursPerWeek * $periodWeeks;

    //             if (! isset($targetsById[$targetId])) {
    //                 $targetsById[$targetId] = [
    //                     'target_id'             => $targetId,
    //                     'work_status'           => ucwords(str_replace('-', ' ', $workStatus)), // Will be overwritten later
    //                     'target_hours_per_week' => $targetHoursPerWeek,
    //                     'target_total_hours'    => 0,
    //                     'period_weeks'          => 0,
    //                     'period_days'           => 0,
    //                 ];
    //             }

    //             // Aggregate target hours
    //             $targetsById[$targetId]['target_total_hours'] += $targetHours;
    //             $targetsById[$targetId]['period_weeks'] += $periodWeeks;
    //             $targetsById[$targetId]['period_days'] += $periodDays;

    //             // Overwrite with latest status name (so last wins)
    //             $targetsById[$targetId]['work_status'] = ucwords(str_replace('-', ' ', $workStatus));
    //         }
    //     }

    //     // Final processing: add actual_hours, % calculation etc
    //     $performances = [];
    //     foreach ($targetsById as $target) {
    //         $targetTotalHours = $target['target_total_hours'];
    //         $percentage       = $targetTotalHours > 0 ? ($billableHours / $targetTotalHours) * 100 : 0;

    //         $status = 'POOR';
    //         if ($percentage >= 100) {
    //             $status = 'EXCELLENT';
    //         } elseif ($percentage >= 90) {
    //             $status = 'WARNING';
    //         }

    //         $performances[] = array_merge($target, [
    //             'actual_hours'     => $billableHours,
    //             'percentage'       => round($percentage, 1),
    //             'status'           => $status,
    //             'actual_vs_target' => round($billableHours - $targetTotalHours, 2),
    //         ]);
    //     }
    //     dd($performances);
    //     return $performances;
    // }

    /**
     * Calculate target performances with support for multiple work hour settings.
     */
    // private function calculateTargetPerformances($user, $worklogs, $startDate, $endDate, $workStatusChanges)
    // {
    //     $billableHours = $worklogs->filter(function ($worklog) {
    //         return $this->isTaskBillable($worklog->task);
    //     })->sum('duration') / 3600;

    //     $workStatusPeriods = $this->calculateWorkStatusPeriods($user, $startDate, $endDate, $workStatusChanges);

    //     // Get the final/current work status from the last period
    //     $finalWorkStatus = end($workStatusPeriods)['work_status'];

    //     // Get hour settings for the final work status
    //     $finalHourSettings = $this->getWorkHourSettings($user, $finalWorkStatus);

    //     $performances = [];

    //     // Calculate total period metrics
    //     $totalPeriodWeeks = 0;
    //     $totalPeriodDays  = 0;
    //     foreach ($workStatusPeriods as $period) {
    //         $totalPeriodDays += $period['days'];
    //         $totalPeriodWeeks += $period['days'] / 7;
    //     }

    //     // For each setting in the final work status, calculate cumulative target hours
    //     foreach ($finalHourSettings as $index => $setting) {
    //         $targetTotalHours = 0;

    //         // Sum target hours across all periods for this setting
    //         foreach ($workStatusPeriods as $period) {
    //             $workStatus  = $period['work_status'];
    //             $periodWeeks = $period['days'] / 7;

    //             // Get hour settings for this period's work status
    //             $periodHourSettings = $this->getWorkHourSettings($user, $workStatus);

    //             // For part-time periods, use the single setting available
    //             if ($workStatus === 'part-time') {
    //                 if (! empty($periodHourSettings)) {
    //                     $targetTotalHours += $periodHourSettings[0]['hours'] * $periodWeeks;
    //                 }
    //             } else {
    //                 // For full-time periods, use the corresponding setting by index
    //                 if (isset($periodHourSettings[$index])) {
    //                     $targetTotalHours += $periodHourSettings[$index]['hours'] * $periodWeeks;
    //                 } else {
    //                     // If the setting doesn't exist in this period, use the first available
    //                     if (! empty($periodHourSettings)) {
    //                         $targetTotalHours += $periodHourSettings[0]['hours'] * $periodWeeks;
    //                     }
    //                 }
    //             }
    //         }

    //         $percentage = $targetTotalHours > 0 ? ($billableHours / $targetTotalHours) * 100 : 0;

    //         $status = 'POOR';
    //         if ($percentage >= 100) {
    //             $status = 'EXCELLENT';
    //         } elseif ($percentage >= 90) {
    //             $status = 'WARNING';
    //         }

    //         $performances[] = [
    //             'target_id'             => $index,
    //             'work_status'           => ucwords(str_replace('-', ' ', $finalWorkStatus)),
    //             'target_hours_per_week' => $setting['hours'],
    //             'target_total_hours'    => round($targetTotalHours, 2),
    //             'actual_hours'          => round($billableHours, 2),
    //             'percentage'            => round($percentage, 1),
    //             'status'                => $status,
    //             'actual_vs_target'      => round($billableHours - $targetTotalHours, 2),
    //             'period_weeks'          => round($totalPeriodWeeks, 1),
    //             'period_days'           => (int) $totalPeriodDays,
    //         ];
    //     }
    //     // dd($performances);
    //     return $performances;
    // }

    /**
     * Calculate target performances with support for multiple work hour settings.
     */
    private function calculateTargetPerformances($user, $worklogs, $startDate, $endDate, $workStatusChanges)
    {
        // Calculate total billable hours for the entire period
        $billableHours = $worklogs->filter(function ($worklog) {
            return $this->isTaskBillable($worklog->task);
        })->sum('duration') / 3600;

        // Get work status periods
        $workStatusPeriods = $this->calculateWorkStatusPeriods($user, $startDate, $endDate, $workStatusChanges);
        dd($workStatusPeriods);
        // Determine the final/current work status (last period's status)
        $finalWorkStatus = end($workStatusPeriods)['work_status'] ?? $user->work_status ?? 'full-time';

        // Get hour settings for the final work status
        $finalHourSettings = $this->getWorkHourSettings($user, $finalWorkStatus);

        // Calculate target hours for each setting across all periods
        $performances = [];

        foreach ($finalHourSettings as $setting) {
            $targetTotalHours = 0;
            $totalPeriodWeeks = 0;
            $totalPeriodDays  = 0;

            // Sum target hours across all periods for this setting
            foreach ($workStatusPeriods as $period) {
                $workStatus  = $period['work_status'];
                $periodDays  = $period['days'];
                $periodWeeks = $periodDays / 7;

                // Get the corresponding setting for this period's work status
                $periodHourSettings = $this->getWorkHourSettings($user, $workStatus);

                // Find matching setting by ID, or use first setting if not found
                $matchingSetting = collect($periodHourSettings)->firstWhere('id', $setting['id']);

                if (! $matchingSetting) {
                    // If this setting doesn't exist for this work status,
                    // use the first available setting for that status
                    $matchingSetting = $periodHourSettings[0] ?? ['hours' => 0];
                }

                $targetHoursForPeriod = $matchingSetting['hours'] * $periodWeeks;
                $targetTotalHours += $targetHoursForPeriod;
                $totalPeriodWeeks += $periodWeeks;
                $totalPeriodDays += $periodDays;
            }

            // Calculate performance metrics
            $percentage = $targetTotalHours > 0 ? ($billableHours / $targetTotalHours) * 100 : 0;

            $status = 'POOR';
            if ($percentage >= 100) {
                $status = 'EXCELLENT';
            } elseif ($percentage >= 90) {
                $status = 'WARNING';
            }

            $performances[] = [
                'target_id'             => $setting['id'],
                'work_status'           => ucwords(str_replace('-', ' ', $finalWorkStatus)),
                'target_hours_per_week' => $setting['hours'],
                'target_total_hours'    => round($targetTotalHours, 2),
                'actual_hours'          => round($billableHours, 2),
                'percentage'            => round($percentage, 1),
                'status'                => $status,
                'actual_vs_target'      => round($billableHours - $targetTotalHours, 2),
                'period_weeks'          => round($totalPeriodWeeks, 1),
                'period_days'           => $totalPeriodDays,
            ];
        }
        // dd($performances);
        return $performances;
    }
    /**
     * Calculate work status periods during the date range.
     */private function calculateWorkStatusPeriods($user, $startDate, $endDate, $workStatusChanges)
    {
        $periods   = [];
        $startDate = Carbon::parse($startDate);
        $endDate   = Carbon::parse($endDate);

        // Get the Monday of the week containing startDate
        $currentWeekStart = $startDate->copy()->startOfWeek(Carbon::MONDAY);

        // Get the Sunday of the week containing endDate
        $finalWeekEnd = $endDate->copy()->endOfWeek(Carbon::SUNDAY);

        // Determine initial work status (before any changes)
        $currentWorkStatus = $this->getInitialWorkStatus($user, $workStatusChanges, $currentWeekStart);
        // dd($currentWorkStatus);
        while ($currentWeekStart->lte($finalWeekEnd)) {
            $currentWeekEnd = $currentWeekStart->copy()->endOfWeek(Carbon::SUNDAY);

            // Find all changes that occur in this week
            $changesInWeek = $workStatusChanges->filter(function ($change) use ($currentWeekStart, $currentWeekEnd) {
                $changeDate = Carbon::parse($change->effective_date);
                return $changeDate->gte($currentWeekStart) && $changeDate->lte($currentWeekEnd);
            })->sortBy('effective_date');

            // If there are changes in this week, use the last change
            if ($changesInWeek->isNotEmpty()) {
                $lastChange        = $changesInWeek->last();
                $currentWorkStatus = json_decode($lastChange->new_value, true);
            }

            // Calculate the actual start and end dates for this period
            // (constrain to the original date range)
            $periodStart = $currentWeekStart->lt($startDate) ? $startDate : $currentWeekStart;
            $periodEnd   = $currentWeekEnd->gt($endDate) ? $endDate : $currentWeekEnd;

            // Only add period if it's within our date range
            if ($periodStart->lte($endDate) && $periodEnd->gte($startDate)) {
                // Ensure we're working with date-only for accurate day calculation
                $startDateOnly = Carbon::parse($periodStart->toDateString());
                $endDateOnly   = Carbon::parse($periodEnd->toDateString());

                $periods[] = [
                    'work_status' => $currentWorkStatus,
                    'start_date'  => $periodStart->toDateString(),
                    'end_date'    => $periodEnd->toDateString(),
                    'days'        => $startDateOnly->diffInDays($endDateOnly) + 1,
                    'week_start'  => $currentWeekStart->toDateString(), // For debugging
                    'week_end'    => $currentWeekEnd->toDateString(),   // For debugging
                ];
            }

            // Move to next week
            $currentWeekStart->addWeek();
        }

        return $periods;
    }

/**
 * Helper method to determine initial work status before any changes
 */
    private function getInitialWorkStatus($user, $workStatusChanges, $targetDate)
    {
        $targetDate = Carbon::parse($targetDate);

        // Sort all changes by effective date
        $sortedChanges = $workStatusChanges->sortBy('effective_date');

        if ($sortedChanges->isEmpty()) {
            // No changes at all, use user's current status
            return $user->work_status ?: 'full-time';
        }

        $firstChange     = $sortedChanges->first();
        $firstChangeDate = Carbon::parse($firstChange->effective_date);

        // If target date is before the first change, use the old_value from first change
        if ($targetDate->lt($firstChangeDate)) {

            return json_decode($firstChange->old_value, true);
        }

        // Find the most recent change that happened on or before the target date
        $applicableChange = null;
        foreach ($sortedChanges as $change) {
            $changeDate = Carbon::parse($change->effective_date);
            if ($changeDate->lte($targetDate)) {
                $applicableChange = $change;
            } else {
                break; // Changes are sorted, so we can stop here
            }
        }
        if ($applicableChange) {
            // Use the new_value from the most recent applicable change
            return json_decode($applicableChange->new_value, true);
        }

        // Fallback (shouldn't reach here given the logic above)
        return json_decode($firstChange->old_value, true);
    }

    /**
     * Get work hour settings for a user and work status.
     */
    private function getWorkHourSettings($user, $workStatus)
    {
        $settingKey = $workStatus === 'full-time' ? 'fulltime_hours' : 'parttime_hours';

        // Get setting type
        $settingType = ConfigurationSettingType::where('key', $settingKey)->first();
        if (! $settingType) {
            // Default fallback
            return [['id' => 1, 'hours' => $workStatus === 'full-time' ? 40 : 20]];
        }

        // Get all settings for this type
        $settings = ConfigurationSetting::where('setting_type_id', $settingType->id)
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        $hourSettings = [];

        foreach ($settings as $setting) {
            $defaultHours = (float) $setting->setting_value;

            // Check if user has custom setting for this
            $customSetting = IvaUserCustomize::where('iva_user_id', $user->id)
                ->where('setting_id', $setting->id)
                ->first();

            $actualHours = $customSetting ? (float) $customSetting->custom_value : $defaultHours;

            $hourSettings[] = [
                'id'           => $setting->id,
                'setting_name' => $setting->setting_value,
                'hours'        => $actualHours,
                'is_custom'    => (bool) $customSetting,
            ];
        }

        return $hourSettings;
    }

    /**
     * Calculate basic metrics.
     */
    private function calculateBasicMetrics($worklogs)
    {
        $billableWorklogs = $worklogs->filter(function ($worklog) {
            return $this->isTaskBillable($worklog->task);
        });

        $nonBillableWorklogs = $worklogs->filter(function ($worklog) {
            return $this->isTaskNonBillable($worklog->task);
        });

        $billableSeconds    = $billableWorklogs->sum('duration');
        $nonBillableSeconds = $nonBillableWorklogs->sum('duration');
        $totalSeconds       = $worklogs->sum('duration');

        $billableHours    = round($billableSeconds / 3600, 2);
        $nonBillableHours = round($nonBillableSeconds / 3600, 2);
        $totalHours       = round($totalSeconds / 3600, 2);

        return [
            'billable_hours'       => $billableHours,
            'non_billable_hours'   => $nonBillableHours,
            'total_hours'          => $totalHours,
            'total_entries'        => $worklogs->count(),
            'billable_entries'     => $billableWorklogs->count(),
            'non_billable_entries' => $nonBillableWorklogs->count(),
        ];
    }

    /**
     * Check if a task is billable based on its category.
     */
    private function isTaskBillable($task)
    {
        if (! $task) {
            return false;
        }

        $taskCategories = TaskReportCategory::where('task_id', $task->id)
            ->with(['category.categoryType'])
            ->get();

        foreach ($taskCategories as $taskCategory) {
            if ($taskCategory->category && $taskCategory->category->categoryType) {
                $categoryType = $taskCategory->category->categoryType->setting_value;
                if (strtolower($categoryType) === 'billable') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a task is non-billable based on its category.
     */
    private function isTaskNonBillable($task)
    {
        if (! $task) {
            return false;
        }

        $taskCategories = TaskReportCategory::where('task_id', $task->id)
            ->with(['category.categoryType'])
            ->get();

        foreach ($taskCategories as $taskCategory) {
            if ($taskCategory->category && $taskCategory->category->categoryType) {
                $categoryType = $taskCategory->category->categoryType->setting_value;
                if (strtolower($categoryType) === 'non-billable') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Calculate daily breakdown with billable/non-billable split.
     */
    private function calculateDailyBreakdown($worklogs, $startDate, $endDate)
    {
        $dailyData   = [];
        $currentDate = Carbon::parse($startDate);
        $endDate     = Carbon::parse($endDate);

        while ($currentDate <= $endDate) {
            $dateString  = $currentDate->toDateString();
            $dayWorklogs = $worklogs->filter(function ($worklog) use ($dateString) {
                return Carbon::parse($worklog->start_time)->toDateString() === $dateString;
            });

            $billableWorklogs = $dayWorklogs->filter(function ($worklog) {
                return $this->isTaskBillable($worklog->task);
            });

            $nonBillableWorklogs = $dayWorklogs->filter(function ($worklog) {
                return $this->isTaskNonBillable($worklog->task);
            });

            $billableSeconds    = $billableWorklogs->sum('duration');
            $nonBillableSeconds = $nonBillableWorklogs->sum('duration');
            $totalSeconds       = $dayWorklogs->sum('duration');

            $billableHours    = round($billableSeconds / 3600, 2);
            $nonBillableHours = round($nonBillableSeconds / 3600, 2);
            $totalHours       = round($totalSeconds / 3600, 2);

            $dailyData[] = [
                'date'                 => $dateString,
                'day_name'             => $currentDate->format('l'),
                'day_short'            => $currentDate->format('D'),
                'is_weekend'           => $currentDate->isWeekend(),
                'billable_hours'       => $billableHours,
                'non_billable_hours'   => $nonBillableHours,
                'total_hours'          => $totalHours,
                'entries_count'        => $dayWorklogs->count(),
                'billable_entries'     => $billableWorklogs->count(),
                'non_billable_entries' => $nonBillableWorklogs->count(),
            ];

            $currentDate->addDay();
        }

        return $dailyData;
    }

    /**
     * Calculate category breakdown with hierarchical structure.
     */
    private function calculateCategoryBreakdown($worklogs)
    {
        $categoryBreakdown = [];

        // Get unique task IDs from worklogs
        $taskIds = $worklogs->pluck('task_id')->unique()->filter();

        // Get all task-category mappings for the tasks in our worklogs
        $taskCategoryMappings = [];
        if ($taskIds->isNotEmpty()) {
            $taskCategoryMappings = TaskReportCategory::with(['task', 'category.categoryType'])
                ->whereIn('task_id', $taskIds)
                ->get()
                ->groupBy('task_id');
        }

        // Group worklogs by billable/non-billable based on category
        $billableWorklogs = $worklogs->filter(function ($worklog) use ($taskCategoryMappings) {
            return $this->isTaskBillableByMapping($worklog->task_id, $taskCategoryMappings);
        });

        $nonBillableWorklogs = $worklogs->filter(function ($worklog) use ($taskCategoryMappings) {
            return $this->isTaskNonBillableByMapping($worklog->task_id, $taskCategoryMappings);
        });

        if ($billableWorklogs->count() > 0) {
            $categoryBreakdown[] = $this->processCategoryGroup($billableWorklogs, $taskCategoryMappings, 'Billable');
        }

        if ($nonBillableWorklogs->count() > 0) {
            $categoryBreakdown[] = $this->processCategoryGroup($nonBillableWorklogs, $taskCategoryMappings, 'Non-Billable');
        }

        return array_filter($categoryBreakdown, function ($group) {
            return $group['total_hours'] > 0;
        });
    }

    /**
     * Check if task is billable by mapping.
     */
    private function isTaskBillableByMapping($taskId, $taskCategoryMappings)
    {
        if (! isset($taskCategoryMappings[$taskId]) || $taskCategoryMappings[$taskId]->isEmpty()) {
            return false;
        }

        $mapping = $taskCategoryMappings[$taskId]->first();
        if ($mapping && $mapping->category && $mapping->category->categoryType) {
            $categoryType = $mapping->category->categoryType->setting_value;
            return strtolower($categoryType) === 'billable';
        }

        return false;
    }

    /**
     * Check if task is non-billable by mapping.
     */
    private function isTaskNonBillableByMapping($taskId, $taskCategoryMappings)
    {
        if (! isset($taskCategoryMappings[$taskId]) || $taskCategoryMappings[$taskId]->isEmpty()) {
            return false;
        }

        $mapping = $taskCategoryMappings[$taskId]->first();
        if ($mapping && $mapping->category && $mapping->category->categoryType) {
            $categoryType = $mapping->category->categoryType->setting_value;
            return strtolower($categoryType) === 'non-billable';
        }

        return false;
    }

    /**
     * Process category group for billable/non-billable.
     */
    private function processCategoryGroup($worklogs, $taskCategoryMappings, $type)
    {
        $categories = [];
        $totalHours = 0;

        // Group worklogs by category
        $worklogsByCategory = [];

        foreach ($worklogs as $worklog) {
            $categoryName = 'Uncategorized';

            if (isset($taskCategoryMappings[$worklog->task_id]) && $taskCategoryMappings[$worklog->task_id]->isNotEmpty()) {
                $mapping = $taskCategoryMappings[$worklog->task_id]->first();
                if ($mapping && $mapping->category) {
                    $categoryName = $mapping->category->cat_name;
                }
            }

            if (! isset($worklogsByCategory[$categoryName])) {
                $worklogsByCategory[$categoryName] = [];
            }
            $worklogsByCategory[$categoryName][] = $worklog;
        }

        // Process each category (exclude Uncategorized for summary)
        foreach ($worklogsByCategory as $categoryName => $categoryWorklogs) {
            if ($categoryName === 'Uncategorized') {
                continue; // Skip uncategorized for main summary
            }

            $categoryHours = collect($categoryWorklogs)->sum('duration') / 3600;
            $totalHours += $categoryHours;

            // Group by tasks within category
            $taskGroups = collect($categoryWorklogs)->groupBy('task_id');
            $tasks      = [];

            foreach ($taskGroups as $taskId => $taskWorklogs) {
                $task      = $taskWorklogs->first()->task;
                $taskHours = collect($taskWorklogs)->sum('duration') / 3600;

                $entries = collect($taskWorklogs)->map(function ($worklog) {
                    return [
                        'id'             => $worklog->id,
                        'start_time'     => $worklog->start_time,
                        'end_time'       => $worklog->end_time,
                        'duration_hours' => round($worklog->duration / 3600, 2),
                        'comment'        => $worklog->comment,
                        'project_name'   => $worklog->project?->project_name ?? 'No Project',
                    ];
                })->toArray();

                $tasks[] = [
                    'task_id'     => $taskId,
                    'task_name'   => $task ? $task->task_name : 'Unknown Task',
                    'total_hours' => round($taskHours, 2),
                    'entries'     => $entries,
                ];
            }

            $categories[] = [
                'category_name' => $categoryName,
                'total_hours'   => round($categoryHours, 2),
                'tasks'         => $tasks,
            ];
        }

        return [
            'type'        => $type,
            'total_hours' => round($totalHours, 2),
            'categories'  => $categories,
        ];
    }
}