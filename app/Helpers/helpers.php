<?php

use App\Models\ConfigurationSetting;
use App\Models\ConfigurationSettingType;
use App\Models\IvaUser;
use App\Models\IvaUserChangelog;
use App\Models\IvaUserCustomize;
use App\Models\TaskReportCategory;
use Carbon\Carbon;

if (! function_exists('emailToFileName')) {
    function emailToFileName($email)
    {
        return preg_replace('/[^a-zA-Z0-9]/', '_', $email);
    }
}

if (! function_exists('isGenashtimEmail')) {
    function isGenashtimEmail($email)
    {
        // Check if the string is a valid email
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Extract the domain part of the email
            $domain = substr(strrchr($email, "@"), 1);

            // Define the list of allowed domains
            $allowedDomains = explode(",", env('SITE_CONFIG_ALLOWED_DOMAINS', 'genashtim.com'));

            // Check if the domain is in the list of allowed domains
            if (in_array($domain, $allowedDomains)) {
                return true; // Email is valid and from an allowed domain
            }
        }
        return false; // Email is not valid or not from an allowed domain
    }
}

if (! function_exists('EncryptData')) {
    function EncryptData($input, $key)
    {
        $key    = str_pad($key, 256, ' '); // Pad or trim key to 256 characters
        $input  = (string) $input;
        $output = '';

        for ($i = 0; $i < strlen($input); $i++) {
            $charCode          = ord($input[$i]);
            $keyChar           = ord($key[$i % strlen($key)]);
            $encryptedCharCode = $charCode ^ $keyChar;
            $output .= chr($encryptedCharCode);
        }

        return base64_encode($output);
    }
}

if (! function_exists('DecryptData')) {
    function DecryptData($input, $key)
    {
        $key    = str_pad($key, 256, ' ');
        $input  = base64_decode($input);
        $output = '';

        for ($i = 0; $i < strlen($input); $i++) {
            $charCode          = ord($input[$i]);
            $keyChar           = ord($key[$i % strlen($key)]);
            $decryptedCharCode = $charCode ^ $keyChar;
            $output .= chr($decryptedCharCode);
        }

        return $output;
    }
}

if (! function_exists('encryptUserData')) {
    function encryptUserData($user)
    {
        if (! $user) {
            return null;
        }

        $key = env('API_NAD_SECRET_KEY');
        if (! $key) {
            return null;
        }

        $data = [
            'id'           => $user->id,
            'employee_id'  => $user->azure_id,
            'email'        => $user->email,
            'datetime'     => Carbon::now()->toIso8601String(),
            'name_request' => 'iva_biilable',
        ];

        return EncryptData(json_encode($data), $key);
    }
}

if (! function_exists('decryptUserToken')) {
    function decryptUserToken($token)
    {
        if (! $token) {
            return null;
        }

        $key = env('API_NAD_SECRET_KEY');
        if (! $key) {
            return null;
        }

        $decrypted = DecryptData($token, $key);
        return json_decode($decrypted, true);
    }
}

if (! function_exists('callNADApi')) {
    /**
     * Send a request to the NAD API with encrypted token and given data.
     *
     * @param string $action
     * @param array $data
     * @return array|null
     */
    function callNADApi(string $action, array $data): ?array
    {
        try {
            $user = request()->user();
            if (! $user) {
                throw new \Exception('User not authenticated.');
            }

            $formData = [
                ['name' => 'email', 'contents' => $user->email],
                ['name' => 'token', 'contents' => encryptUserData($user)],
                ['name' => 'action', 'contents' => $action],
                ['name' => 'data', 'contents' => json_encode($data)],
            ];

            $response = Http::asMultipart()
                ->post(config('services.nad.url'), $formData);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('NAD API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }
        } catch (\Throwable $e) {
            Log::error('NAD API connection failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}

// Functions for handling IVA User BillAble reports
if (! function_exists('ivaAdjustStartDate')) {

/**
 * Adjust start date based on user hire date
 *
 * @param object $user - The IvaUser model instance
 * @param string $startDate - The original start date
 * @param string $endDate - The end date for validation
 * @return array - Returns adjusted start date and validation status
 */
    function ivaAdjustStartDate($user, $startDate, $endDate)
    {
        $originalStartDate = Carbon::parse($startDate);
        $parsedEndDate     = Carbon::parse($endDate);

        // Check if user hire_date is null or before start date
        if (is_null($user->hire_date) || Carbon::parse($user->hire_date)->lt($originalStartDate)) {
            $adjustedStartDate = $originalStartDate;
        } else {
            // Get Monday of the week that hire date falls in
            $hireDate          = Carbon::parse($user->hire_date);
            $adjustedStartDate = $hireDate->startOfWeek(Carbon::MONDAY);
        }

        // include both start and end dates
        $daysDiffInclusive = $adjustedStartDate->diffInDays($parsedEndDate) + 1;

        return [
            'adjusted_start_date' => $adjustedStartDate->format('Y-m-d'),
            'original_start_date' => $originalStartDate->format('Y-m-d'),
            'is_valid_week_range' => $daysDiffInclusive >= 7,
            'days_difference'     => $daysDiffInclusive,
            'hire_date_used'      => ! is_null($user->hire_date) && Carbon::parse($user->hire_date)->gte($originalStartDate),
        ];
    }
}

if (! function_exists('calculateWorkStatusPeriods')) {
    /**
     * Calculate work status periods during the date range.
     */
    function calculateWorkStatusPeriods($user, $startDate, $endDate, $workStatusChanges)
    {
        $periods   = [];
        $startDate = Carbon::parse($startDate);
        $endDate   = Carbon::parse($endDate);

        // Get the Monday of the week containing startDate
        $currentWeekStart = $startDate->copy()->startOfWeek(Carbon::MONDAY);

        // Get the Sunday of the week containing endDate
        $finalWeekEnd = $endDate->copy()->endOfWeek(Carbon::SUNDAY);

        // Determine initial work status (before any changes)
        $currentWorkStatus = getInitialWorkStatus($user, $workStatusChanges, $currentWeekStart);

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
                    'week_start'  => $currentWeekStart->toDateString(),
                    'week_end'    => $currentWeekEnd->toDateString(),
                ];
            }

            // Move to next week
            $currentWeekStart->addWeek();
        }

        return $periods;
    }
}

if (! function_exists('getInitialWorkStatus')) {
    /**
     * Helper method to determine initial work status before any changes
     */
    function getInitialWorkStatus($user, $workStatusChanges, $targetDate)
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
}

if (! function_exists('getWorkHourSettings')) {
    /**
     * Get work hour settings for a user and work status with custom overrides.
     */
    function getWorkHourSettings($user, $workStatus, $periodStartDate = null, $periodEndDate = null)
    {
        $workStatus = $workStatus ?: 'full-time'; // Treat null as full-time
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
            $customHours  = getCustomValueForPeriod($user->id, $setting->id, $periodStartDate, $periodEndDate);
            $actualHours  = $customHours !== null ? $customHours : $defaultHours;

            $hourSettings[] = [
                'id'            => $setting->id,
                'setting_name'  => $setting->setting_value,
                'hours'         => $actualHours,
                'is_custom'     => $customHours !== null,
                'default_hours' => $defaultHours,
                'custom_hours'  => $customHours,
            ];
        }

        return $hourSettings;
    }
}

if (! function_exists('getCustomValueForPeriod')) {
    /**
     * Get custom value for a setting during a specific period.
     */
    function getCustomValueForPeriod($userId, $settingId, $periodStartDate = null, $periodEndDate = null)
    {
        if (! $periodStartDate || ! $periodEndDate) {
            // If no period specified, check for any active customization
            $customSetting = IvaUserCustomize::where('iva_user_id', $userId)
                ->where('setting_id', $settingId)
                ->first();

            return $customSetting ? (float) $customSetting->custom_value : null;
        }

        $periodStart = Carbon::parse($periodStartDate);
        $periodEnd   = Carbon::parse($periodEndDate);

        // Find customizations that overlap with the given period
        $customSetting = IvaUserCustomize::where('iva_user_id', $userId)
            ->where('setting_id', $settingId)
            ->where(function ($query) use ($periodStart, $periodEnd) {
                $query->where(function ($q) use ($periodStart, $periodEnd) {
                    // Custom setting starts before or during period and ends after or during period
                    $q->where(function ($subQ) use ($periodStart) {
                        $subQ->whereNull('start_date')
                            ->orWhere('start_date', '<=', $periodStart->toDateString());
                    })->where(function ($subQ) use ($periodEnd) {
                        $subQ->whereNull('end_date')
                            ->orWhere('end_date', '>=', $periodEnd->toDateString());
                    });
                })->orWhere(function ($q) use ($periodStart, $periodEnd) {
                    // Custom setting overlaps with period
                    $q->where(function ($subQ) use ($periodStart, $periodEnd) {
                        $subQ->where('start_date', '<=', $periodEnd->toDateString())
                            ->where('end_date', '>=', $periodStart->toDateString());
                    });
                });
            })
            ->orderBy('start_date', 'desc')
            ->first();

        return $customSetting ? (float) $customSetting->custom_value : null;
    }
}

if (! function_exists('calculateTargetPerformancesForUser')) {
    /**
     * Calculate target performances with support for multiple work hour settings and custom overrides.
     */
    function calculateTargetPerformancesForUser($user, $worklogs, $startDate, $endDate, $workStatusChanges)
    {
        // Calculate total billable hours for the entire period
        $billableHours = $worklogs->filter(function ($worklog) {
            return isTaskBillable($worklog->task);
        })->sum('duration') / 3600;

        // Get work status periods
        $workStatusPeriods = calculateWorkStatusPeriods($user, $startDate, $endDate, $workStatusChanges);

        // Determine which setting combinations we need to calculate
        $settingCombinations = determineSettingCombinations($user, $workStatusPeriods);

        $performances = [];

        foreach ($settingCombinations as $combination) {
            $targetTotalHours  = 0;
            $totalPeriodWeeks  = 0;
            $totalPeriodDays   = 0;
            $workStatusDisplay = [];

            // Sum target hours across all periods for this setting combination
            foreach ($workStatusPeriods as $periodIndex => $period) {
                $workStatus  = $period['work_status'] ?: 'full-time';
                $periodDays  = $period['days'];
                $periodWeeks = $periodDays / 7;
                $periodStart = $period['start_date'];
                $periodEnd   = $period['end_date'];

                // Get setting for this period based on the combination
                $settingForPeriod = getSettingForPeriod($user, $workStatus, $periodStart, $periodEnd, $combination, $periodIndex);

                $targetHoursForPeriod = $settingForPeriod['hours'] * $periodWeeks;
                $targetTotalHours += $targetHoursForPeriod;
                $totalPeriodWeeks += $periodWeeks;
                $totalPeriodDays += $periodDays;

                // Collect work status for display
                $statusDisplay = ucwords(str_replace('-', ' ', $workStatus));
                if (! in_array($statusDisplay, $workStatusDisplay)) {
                    $workStatusDisplay[] = $statusDisplay;
                }
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
                'target_id'             => $combination['id'],
                'work_status'           => implode(' + ', $workStatusDisplay),
                'target_hours_per_week' => $combination['display_hours'],
                'target_total_hours'    => round($targetTotalHours, 2),
                'actual_hours'          => round($billableHours, 2),
                'percentage'            => round($percentage, 1),
                'status'                => $status,
                'actual_vs_target'      => round($billableHours - $targetTotalHours, 2),
                'period_weeks'          => round($totalPeriodWeeks, 1),
                'period_days'           => $totalPeriodDays,
                'combination_details'   => $combination['details'],
            ];
        }
        // dd($workStatusPeriods, $performances);
        return $performances;
    }
}

if (! function_exists('determineSettingCombinations')) {
    /**
     * Determine all possible setting combinations based on work status periods.
     */
    function determineSettingCombinations($user, $workStatusPeriods)
    {
        // Get all unique work statuses in the periods
        $uniqueStatuses = collect($workStatusPeriods)->pluck('work_status')->map(function ($status) {
            return $status ?: 'full-time';
        })->unique()->values()->toArray();

        // Get settings for each status
        $statusSettings = [];
        foreach ($uniqueStatuses as $status) {
            $statusSettings[$status] = getWorkHourSettings($user, $status);
        }

        $combinations = [];

        if (count($uniqueStatuses) === 1) {
            // Single work status throughout the period
            $status   = $uniqueStatuses[0];
            $settings = $statusSettings[$status];

            foreach ($settings as $setting) {
                $combinations[] = [
                    'id'            => $setting['id'],
                    'display_hours' => $setting['hours'],
                    'details'       => [
                        'type'       => 'single_status',
                        'status'     => $status,
                        'setting_id' => $setting['id'],
                    ],
                ];
            }
        } else {
            // Mixed work statuses - create combinations
            // For mixed status, we always return all combinations from full-time settings
            $fullTimeSettings = $statusSettings['full-time'] ?? [];

            foreach ($fullTimeSettings as $setting) {
                $combinations[] = [
                    'id'            => $setting['id'],
                    'display_hours' => $setting['hours'],
                    'details'       => [
                        'type'               => 'mixed_status',
                        'primary_setting_id' => $setting['id'],
                        'statuses'           => $uniqueStatuses,
                    ],
                ];
            }
        }

        return $combinations;
    }
}

if (! function_exists('getSettingForPeriod')) {
    /**
     * Get the appropriate setting for a specific period based on the combination.
     */
    function getSettingForPeriod($user, $workStatus, $periodStart, $periodEnd, $combination, $periodIndex)
    {
        $workStatus = $workStatus ?: 'full-time';

        if ($combination['details']['type'] === 'single_status') {
            // Single status - use the setting directly
            $periodHourSettings = getWorkHourSettings($user, $workStatus, $periodStart, $periodEnd);
            return collect($periodHourSettings)->firstWhere('id', $combination['details']['setting_id']) ?? $periodHourSettings[0] ?? ['hours' => 0];
        } else {
            // Mixed status - use combination logic
            $periodHourSettings = getWorkHourSettings($user, $workStatus, $periodStart, $periodEnd);

            if ($workStatus === 'part-time') {
                // For part-time periods, use the single part-time setting
                return $periodHourSettings[0] ?? ['hours' => 20];
            } else {
                // For full-time periods, use the setting from the combination
                return collect($periodHourSettings)->firstWhere('id', $combination['details']['primary_setting_id']) ?? $periodHourSettings[0] ?? ['hours' => 40];
            }
        }
    }
}

if (! function_exists('isTaskBillable')) {
    /**
     * Check if a task is billable based on its category.
     */
    function isTaskBillable($task)
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
}

if (! function_exists('isTaskNonBillable')) {
    /**
     * Check if a task is non-billable based on its category.
     */
    function isTaskNonBillable($task)
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
}

if (! function_exists('calculateBasicMetrics')) {
    /**
     * Calculate basic metrics for worklogs.
     */
    function calculateBasicMetrics($worklogs)
    {
        // dd($worklogs->toArray());
        $billableWorklogs = $worklogs->filter(function ($worklog) {
            return isTaskBillable($worklog->task);
        });

        $nonBillableWorklogs = $worklogs->filter(function ($worklog) {
            return isTaskNonBillable($worklog->task);
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
}

if (! function_exists('calculateDailyBreakdown')) {
    /**
     * Calculate daily breakdown with billable/non-billable split.
     */
    function calculateDailyBreakdown($worklogs, $startDate, $endDate)
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
                return isTaskBillable($worklog->task);
            });

            $nonBillableWorklogs = $dayWorklogs->filter(function ($worklog) {
                return isTaskNonBillable($worklog->task);
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
}

if (! function_exists('calculateCategoryBreakdown')) {
    /**
     * Calculate category breakdown with hierarchical structure.
     */
    function calculateCategoryBreakdown($worklogs)
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
            return isTaskBillableByMapping($worklog->task_id, $taskCategoryMappings);
        });

        $nonBillableWorklogs = $worklogs->filter(function ($worklog) use ($taskCategoryMappings) {
            return isTaskNonBillableByMapping($worklog->task_id, $taskCategoryMappings);
        });

        if ($billableWorklogs->count() > 0) {
            $categoryBreakdown[] = processCategoryGroup($billableWorklogs, $taskCategoryMappings, 'Billable');
        }

        if ($nonBillableWorklogs->count() > 0) {
            $categoryBreakdown[] = processCategoryGroup($nonBillableWorklogs, $taskCategoryMappings, 'Non-Billable');
        }

        return array_filter($categoryBreakdown, function ($group) {
            return $group['total_hours'] > 0;
        });
    }
}

if (! function_exists('isTaskBillableByMapping')) {
    /**
     * Check if task is billable by mapping.
     */
    function isTaskBillableByMapping($taskId, $taskCategoryMappings)
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
}

if (! function_exists('isTaskNonBillableByMapping')) {
    /**
     * Check if task is non-billable by mapping.
     */
    function isTaskNonBillableByMapping($taskId, $taskCategoryMappings)
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
}

if (! function_exists('processCategoryGroup')) {
    /**
     * Process category group for billable/non-billable.
     */
    function processCategoryGroup($worklogs, $taskCategoryMappings, $type)
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

if (! function_exists('calculateBimonthlyData')) {
    /**
     * Calculate bimonthly data for first and second half of month.
     */
    function calculateBimonthlyData($user, $year, $month, $splitDate = 15)
    {

        // First half: 1st to splitDate
        $firstHalfStart = Carbon::create($year, $month, 1)->startOfDay();
        $firstHalfEnd   = Carbon::create($year, $month, $splitDate)->endOfDay();
        $firstHalfData  = [
            'start_date' => $firstHalfStart,
            'end_date'   => $firstHalfEnd,
            'blab_only'  => 1,
            'email_list' => [$user->email], // Use user's email for NAD API
        ];
        $responseNAD      = callNADApi('get_nad_by_date_range', $firstHalfData);
        $firstHalfNADData = [];

        if (! empty($responseNAD['status']) && $responseNAD['status'] === true && ! empty($responseNAD['data']) && count($responseNAD['data']) > 0) {
            $firstHalfNADData = collect($responseNAD['data'])->firstWhere('email', $user->email) ?? [];
        }
        // Second half: (splitDate + 1) to end of month
        $secondHalfStart = Carbon::create($year, $month, $splitDate + 1)->startOfDay();
        $secondHalfEnd   = Carbon::create($year, $month)->endOfMonth();

        $secondHalfData = [
            'start_date' => $secondHalfStart,
            'end_date'   => $secondHalfEnd,
            'blab_only'  => 1,
            'email_list' => [$user->email], // Use user's email for NAD API
        ];
        $responseNAD    = callNADApi('get_nad_by_date_range', $firstHalfData);
        $secondHalfData = [];

        if (! empty($responseNAD['status']) && $responseNAD['status'] === true && ! empty($responseNAD['data']) && count($responseNAD['data']) > 0) {
            $secondHalfData = collect($responseNAD['data'])->firstWhere('email', $user->email) ?? [];
        }
        // Get worklogs for each half
        $firstHalfWorklogs = \App\Models\WorklogsData::where('iva_id', $user->id)
            ->where('is_active', true)
            ->whereBetween('start_time', [$firstHalfStart, $firstHalfEnd])
            ->with(['project', 'task'])
            ->get();

        $secondHalfWorklogs = \App\Models\WorklogsData::where('iva_id', $user->id)
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
                'nad_data'           => $firstHalfNADData,
                'basic_metrics'      => calculateBasicMetrics($firstHalfWorklogs),
                'daily_breakdown'    => calculateDailyBreakdown($firstHalfWorklogs, $firstHalfStart->format('Y-m-d'), $firstHalfEnd->format('Y-m-d')),
                'category_breakdown' => calculateCategoryBreakdown($firstHalfWorklogs),
            ],
            'second_half' => [
                'date_range'         => [
                    'start' => $secondHalfStart->format('Y-m-d'),
                    'end'   => $secondHalfEnd->format('Y-m-d'),
                ],
                'nad_data'           => $secondHalfData,
                'basic_metrics'      => calculateBasicMetrics($secondHalfWorklogs),
                'daily_breakdown'    => calculateDailyBreakdown($secondHalfWorklogs, $secondHalfStart->format('Y-m-d'), $secondHalfEnd->format('Y-m-d')),
                'category_breakdown' => calculateCategoryBreakdown($secondHalfWorklogs),
            ],
        ];
    }
}

if (! function_exists('getWorkStatusChanges')) {
    /**
     * Get work status changes during the specified period.
     */
    function getWorkStatusChanges($user, $startDate, $endDate)
    {
        return IvaUserChangelog::where('iva_user_id', $user->id)
            ->where('field_changed', 'work_status')
            ->whereBetween('effective_date', [$startDate, $endDate])
            ->orderBy('effective_date')
            ->get();
    }
}

if (! function_exists('getAllWorkStatusChanges')) {
    /**
     * Get all work status changes for a user.
     */
    function getAllWorkStatusChanges($user)
    {
        return IvaUserChangelog::where('iva_user_id', $user->id)
            ->where('field_changed', 'work_status')
            ->orderBy('effective_date')
            ->get();
    }
}

// For Timedoctor v2 Time
if (! function_exists('convertToTimeDoctorTimezone')) {
    /**
     * Convert local time to UTC for TimeDoctor API
     * TimeDoctor stores data in UTC, so we need to convert from local timezone
     *
     * @param string|Carbon $dateTime
     * @param string $timezone Default to Singapore timezone (Asia/Singapore)
     * @return Carbon
     */
    function convertToTimeDoctorTimezone($dateTime, $timezone = 'Asia/Singapore')
    {
        if (is_string($dateTime)) {
            $dateTime = Carbon::parse($dateTime, $timezone);
        } elseif ($dateTime instanceof Carbon) {
            // If it's already a Carbon instance, ensure it has the correct timezone
            if ($dateTime->getTimezone()->getName() === 'UTC') {
                // If it's UTC, assume it was meant to be in local timezone
                $dateTime = Carbon::parse($dateTime->format('Y-m-d H:i:s'), $timezone);
            }
        }

        // Convert to UTC for TimeDoctor API
        return $dateTime->utc();
    }
}

if (! function_exists('convertFromTimeDoctorTimezone')) {
    /**
     * Convert UTC time from TimeDoctor API to local timezone
     *
     * @param string|Carbon $dateTime
     * @param string $timezone Default to Singapore timezone (Asia/Singapore)
     * @return Carbon
     */
    function convertFromTimeDoctorTimezone($dateTime, $timezone = 'Asia/Singapore')
    {
        if (is_string($dateTime)) {
            $dateTime = Carbon::parse($dateTime, 'UTC');
        } elseif ($dateTime instanceof Carbon) {
            // Ensure it's treated as UTC first
            $dateTime = $dateTime->utc();
        }

        // Convert to local timezone
        return $dateTime->setTimezone($timezone);
    }
}

if (! function_exists('formatTimeDoctorApiDateTime')) {
    /**
     * Format datetime for TimeDoctor API (ISO 8601 format in UTC)
     *
     * @param string|Carbon $dateTime
     * @param string $localTimezone
     * @return string
     */
    function formatTimeDoctorApiDateTime($dateTime, $localTimezone = 'Asia/Singapore')
    {
        $utcDateTime = convertToTimeDoctorTimezone($dateTime, $localTimezone);
        return $utcDateTime->format('Y-m-d\TH:i:s.v\Z');
    }
}

if (! function_exists('getTimeDoctorDateRange')) {
    /**
     * Get date range for TimeDoctor API with proper timezone conversion
     * According to TimeDoctor V2 docs: convert local time to UTC
     * Example: Jul 2nd, 2021 06:36 AM UTC+8 = Jul 1st, 2021 10:36 PM UTC
     *
     * For date 2025-06-22 in GMT+8:
     * - Start: 2025-06-22 00:00:00 GMT+8 = 2025-06-21 16:00:00 UTC
     * - End: 2025-06-22 23:59:59 GMT+8 = 2025-06-22 15:59:59 UTC
     *
     * @param string $startDate Y-m-d format
     * @param string $endDate Y-m-d format
     * @param string $localTimezone
     * @return array ['from' => string, 'to' => string]
     */
    function getTimeDoctorDateRange($startDate, $endDate, $localTimezone = 'Asia/Singapore')
    {
        // Create start of day in local timezone (GMT+8)
        $fromLocal = Carbon::createFromFormat('Y-m-d H:i:s', $startDate . ' 00:00:00', $localTimezone);

        // Create end of day in local timezone (GMT+8)
        $toLocal = Carbon::createFromFormat('Y-m-d H:i:s', $endDate . ' 23:59:59', $localTimezone);

        // Convert to UTC as required by TimeDoctor API
        $fromUtc = $fromLocal->utc();
        $toUtc   = $toLocal->utc();

        // Format for TimeDoctor API (exact format as in your example)
        $fromFormatted = $fromUtc->format('Y-m-d\TH:i:s.v\Z');
        $toFormatted   = $toUtc->format('Y-m-d\TH:i:s.v\Z');

        Log::debug("TimeDoctor date range conversion", [
            'input_start'    => $startDate,
            'input_end'      => $endDate,
            'local_timezone' => $localTimezone,
            'from_local'     => $fromLocal->format('Y-m-d H:i:s T'),
            'to_local'       => $toLocal->format('Y-m-d H:i:s T'),
            'from_utc'       => $fromUtc->format('Y-m-d H:i:s T'),
            'to_utc'         => $toUtc->format('Y-m-d H:i:s T'),
            'from_formatted' => $fromFormatted,
            'to_formatted'   => $toFormatted,
        ]);

        return [
            'from' => $fromFormatted,
            'to'   => $toFormatted,
        ];
    }
}

// Add these functions to the existing helpers.php file

if (! function_exists('calculateWeeklySummaryData')) {
    /**
     * Calculate weekly summary data with NAD hours and performance metrics.
     */
    function calculateWeeklySummaryData($user, $worklogs, $startDate, $endDate, $year, $startWeekNumber, $weekCount, $workStatusChanges)
    {
        $nadHourRate = config('services.nad.nad_hour_rate.rate', 8);
        $timezone    = config('app.timezone', 'Asia/Singapore');
        // Generate week ranges for the requested period
        // $startWeekNumber = $startWeekNumber > 52 ? 1 : $startWeekNumber;
        $selectedWeeks = getWeekRangeForDates($startDate, $endDate, $startWeekNumber);

        $weeklyBreakdown       = [];
        $totalBillableHours    = 0;
        $totalNonBillableHours = 0;
        $totalNadHours         = 0;
        $totalNadCount         = 0;
        foreach ($selectedWeeks as $weekData) {
            $weekStart = Carbon::parse($weekData['start_date'], $timezone)->startOfDay();
            $weekEnd   = Carbon::parse($weekData['end_date'], $timezone)->endOfDay();

            // Get worklogs for this week
            $weekWorklogs = $worklogs->filter(function ($worklog) use ($weekStart, $weekEnd, $timezone) {
                $worklogDate = Carbon::parse($worklog->start_time)->setTimezone($timezone);
                return $worklogDate->between($weekStart, $weekEnd);
            });

            // Calculate basic metrics for the week
            $weekMetrics = calculateBasicMetrics($weekWorklogs);

            // Get NAD data for this week
            $nadData = [
                'start_date' => $weekData['start_date'],
                'end_date'   => $weekData['end_date'],
                'blab_only'  => 1,
                'email_list' => [$user->email],
            ];

            $nadResponse  = callNADApi('get_nad_by_date_range', $nadData);
            $weekNadData  = [];
            $weekNadCount = 0;
            $weekNadHours = 0;

            if (! empty($nadResponse['status']) && $nadResponse['status'] === true && ! empty($nadResponse['data'])) {
                $weekNadData  = collect($nadResponse['data'])->firstWhere('email', $user->email) ?? [];
                $weekNadCount = $weekNadData['nad_count'] ?? 0;
                $weekNadHours = $weekNadCount * $nadHourRate;
            }

            // Calculate performance for this week
            $weekPerformance = calculateWeeklyPerformance(
                $user,
                $weekWorklogs,
                $weekData['start_date'],
                $weekData['end_date'],
                $workStatusChanges
            );

            $weeklyBreakdown[] = [
                'week_number'        => $weekData['week_number'],
                'start_date'         => $weekData['start_date'],
                'end_date'           => $weekData['end_date'],
                'label'              => $weekData['label'],
                'billable_hours'     => $weekMetrics['billable_hours'],
                'non_billable_hours' => $weekMetrics['non_billable_hours'],
                'total_hours'        => $weekMetrics['total_hours'],
                'nad_count'          => $weekNadCount,
                'nad_hours'          => round($weekNadHours, 2),
                'nad_data'           => $weekNadData,
                'performance'        => $weekPerformance,
                'entries_count'      => $weekMetrics['total_entries'],
            ];

            // Add to totals
            $totalBillableHours += $weekMetrics['billable_hours'];
            $totalNonBillableHours += $weekMetrics['non_billable_hours'];
            $totalNadHours += $weekNadHours;
            $totalNadCount += $weekNadCount;
        }

        // Calculate overall category breakdown (simplified for weekly summary)
        $categoryBreakdown = calculateWeeklyCategoryBreakdown($worklogs);

        return [
            'summary'            => [
                'total_weeks'              => count($selectedWeeks),
                'total_billable_hours'     => round($totalBillableHours, 2),
                'total_non_billable_hours' => round($totalNonBillableHours, 2),
                'total_hours'              => round($totalBillableHours + $totalNonBillableHours, 2),
                'total_nad_count'          => $totalNadCount,
                'total_nad_hours'          => round($totalNadHours, 2),
                'nad_hour_rate'            => $nadHourRate,
            ],
            'weekly_breakdown'   => $weeklyBreakdown,
            'category_breakdown' => $categoryBreakdown,
            'date_range'         => [
                'start' => $selectedWeeks[0]['start_date'] ?? $startDate,
                'end'   => end($selectedWeeks)['end_date'] ?? $endDate,
                'mode'  => 'weekly_summary',
            ],
        ];
    }
}

if (! function_exists('calculateWeeklyPerformance')) {
    /**
     * Calculate performance metrics for a specific week.
     */
    function calculateWeeklyPerformance($user, $weekWorklogs, $startDate, $endDate, $workStatusChanges)
    {
        // Get work status periods for this week
        $workStatusPeriods = calculateWorkStatusPeriods($user, $startDate, $endDate, $workStatusChanges);

        // Calculate billable hours for the week
        $billableHours = $weekWorklogs->filter(function ($worklog) {
            return isTaskBillable($worklog->task);
        })->sum('duration') / 3600;

        // Determine setting combinations for the week
        $settingCombinations = determineSettingCombinations($user, $workStatusPeriods);

        $performances = [];
        foreach ($settingCombinations as $combination) {
            $targetTotalHours  = 0;
            $totalPeriodWeeks  = 0;
            $workStatusDisplay = [];

            // Calculate target hours for this week
            foreach ($workStatusPeriods as $periodIndex => $period) {
                $workStatus  = $period['work_status'] ?: 'full-time';
                $periodDays  = $period['days'];
                $periodWeeks = $periodDays / 7;
                $periodStart = $period['start_date'];
                $periodEnd   = $period['end_date'];

                $settingForPeriod     = getSettingForPeriod($user, $workStatus, $periodStart, $periodEnd, $combination, $periodIndex);
                $targetHoursForPeriod = $settingForPeriod['hours'] * $periodWeeks;
                $targetTotalHours += $targetHoursForPeriod;
                $totalPeriodWeeks += $periodWeeks;

                $statusDisplay = ucwords(str_replace('-', ' ', $workStatus));
                if (! in_array($statusDisplay, $workStatusDisplay)) {
                    $workStatusDisplay[] = $statusDisplay;
                }
            }

            $percentage = $targetTotalHours > 0 ? ($billableHours / $targetTotalHours) * 100 : 0;

            $status = 'POOR';
            if ($percentage >= 100) {
                $status = 'EXCELLENT';
            } elseif ($percentage >= 90) {
                $status = 'WARNING';
            }

            $performances[] = [
                'target_id'             => $combination['id'],
                'work_status'           => implode(' + ', $workStatusDisplay),
                'target_hours_per_week' => $combination['display_hours'],
                'target_total_hours'    => round($targetTotalHours, 2),
                'actual_hours'          => round($billableHours, 2),
                'percentage'            => round($percentage, 1),
                'status'                => $status,
                'actual_vs_target'      => round($billableHours - $targetTotalHours, 2),
                'period_weeks'          => round($totalPeriodWeeks, 1),
                'combination_details'   => $combination['details'],
            ];
        }

        return $performances;
    }
}

if (! function_exists('calculateWeeklyCategoryBreakdown')) {
    /**
     * Calculate category breakdown for weekly summary (simplified version).
     */
    function calculateWeeklyCategoryBreakdown($worklogs)
    {
        $categoryBreakdown = [];

        // Get unique task IDs from worklogs
        $taskIds = $worklogs->pluck('task_id')->unique()->filter();

        // Get all task-category mappings for the tasks in our worklogs
        $taskCategoryMappings = [];
        if ($taskIds->isNotEmpty()) {
            $taskCategoryMappings = \App\Models\TaskReportCategory::with(['task', 'category.categoryType'])
                ->whereIn('task_id', $taskIds)
                ->get()
                ->groupBy('task_id');
        }

        // Group worklogs by billable/non-billable based on category
        $billableWorklogs = $worklogs->filter(function ($worklog) use ($taskCategoryMappings) {
            return isTaskBillableByMapping($worklog->task_id, $taskCategoryMappings);
        });

        $nonBillableWorklogs = $worklogs->filter(function ($worklog) use ($taskCategoryMappings) {
            return isTaskNonBillableByMapping($worklog->task_id, $taskCategoryMappings);
        });

        if ($billableWorklogs->count() > 0) {
            $categoryBreakdown[] = processWeeklyCategoryGroup($billableWorklogs, $taskCategoryMappings, 'Billable');
        }

        if ($nonBillableWorklogs->count() > 0) {
            $categoryBreakdown[] = processWeeklyCategoryGroup($nonBillableWorklogs, $taskCategoryMappings, 'Non-Billable');
        }

        return array_filter($categoryBreakdown, function ($group) {
            return $group['total_hours'] > 0;
        });
    }
}

if (! function_exists('processWeeklyCategoryGroup')) {
    /**
     * Process category group for weekly summary (without task details).
     */
    function processWeeklyCategoryGroup($worklogs, $taskCategoryMappings, $type)
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

        // Process each category (simplified - no task breakdown)
        foreach ($worklogsByCategory as $categoryName => $categoryWorklogs) {
            if ($categoryName === 'Uncategorized') {
                continue; // Skip uncategorized for main summary
            }

            $categoryHours = collect($categoryWorklogs)->sum('duration') / 3600;
            $totalHours += $categoryHours;

            $categories[] = [
                'category_name' => $categoryName,
                'total_hours'   => round($categoryHours, 2),
                'entries_count' => count($categoryWorklogs),
            ];
        }

        // Sort categories by total hours descending
        usort($categories, function ($a, $b) {
            return $b['total_hours'] <=> $a['total_hours'];
        });

        return [
            'type'        => $type,
            'total_hours' => round($totalHours, 2),
            'categories'  => $categories,
        ];
    }
}

if (! function_exists('getWeekRangeForDates')) {
    /**
     * Generate week ranges between two dates.
     *
     * @param string $startDate Start date (must be a Monday)
     * @param string $endDate End date (must be a Sunday)
     * @param int $weekNumber Starting week number
     * @return array
     * @throws \Exception
     */
    function getWeekRangeForDates($startDate, $endDate, $weekNumber = 1)
    {
        $timezone = config('app.timezone', 'Asia/Singapore');

        // Create Carbon instances
        $start = Carbon::createFromFormat('Y-m-d', $startDate, $timezone)->startOfDay();
        $end   = Carbon::createFromFormat('Y-m-d', $endDate, $timezone)->endOfDay();

        // Validation
        if (! $start->isMonday()) {
            throw new \Exception("Start date must be a Monday.");
        }

        if (! $end->isSunday()) {
            throw new \Exception("End date must be a Sunday.");
        }

        if ($start->gt($end)) {
            throw new \Exception("Start date must be before end date.");
        }

        $weeks = [];

        $current = $start->copy();

        while ($current->lte($end)) {
            $weekStart = $current->copy();
            $weekEnd   = $current->copy()->addDays(6);

            $weeks[] = [
                'week_number' => $weekNumber,
                'start_date'  => $weekStart->format('Y-m-d'),
                'end_date'    => $weekEnd->format('Y-m-d'),
                'label'       => sprintf(
                    'Week %d (%s - %s)',
                    $weekNumber,
                    $weekStart->format('M d'),
                    $weekEnd->format('M d')
                ),
            ];

            $current->addWeek();
            $weekNumber++;
            $weekNumber = $weekNumber > 52 ? 1 : $weekNumber; // Reset week number after 52
        }

        return $weeks;
    }
}

// Add these functions to the existing helpers.php file

if (! function_exists('calculateMonthlySummaryData')) {
    /**
     * Calculate monthly summary data with NAD hours and performance metrics.
     */
    function calculateMonthlySummaryData($user, $worklogs, $startDate, $endDate, $year, $startMonth, $monthCount, $workStatusChanges)
    {
        $nadHourRate = config('services.nad.nad_hour_rate.rate', 8);
        $timezone    = config('app.timezone', 'Asia/Singapore');

        // Generate month ranges for the requested period
        $selectedMonths        = getMonthRangeForDates($startDate, $endDate, $monthCount);
        $monthlyBreakdown      = [];
        $totalBillableHours    = 0;
        $totalNonBillableHours = 0;
        $totalNadHours         = 0;
        $totalNadCount         = 0;
        foreach ($selectedMonths as $monthData) {
            $monthStart = Carbon::parse($monthData['start_date'], $timezone)->startOfDay();
            $monthEnd   = Carbon::parse($monthData['end_date'], $timezone)->endOfDay();

            // Get worklogs for this month
            $monthWorklogs = $worklogs->filter(function ($worklog) use ($monthStart, $monthEnd, $timezone) {
                $worklogDate = Carbon::parse($worklog->start_time)->setTimezone($timezone);
                return $worklogDate->between($monthStart, $monthEnd);
            });

            // Calculate basic metrics for the month
            $monthMetrics = calculateBasicMetrics($monthWorklogs);

            // Get NAD data for this month
            $nadData = [
                'start_date' => $monthData['start_date'],
                'end_date'   => $monthData['end_date'],
                'blab_only'  => 1,
                'email_list' => [$user->email],
            ];

            $nadResponse   = callNADApi('get_nad_by_date_range', $nadData);
            $monthNadData  = [];
            $monthNadCount = 0;
            $monthNadHours = 0;

            if (! empty($nadResponse['status']) && $nadResponse['status'] === true && ! empty($nadResponse['data'])) {
                $monthNadData  = collect($nadResponse['data'])->firstWhere('email', $user->email) ?? [];
                $monthNadCount = $monthNadData['nad_count'] ?? 0;
                $monthNadHours = $monthNadCount * $nadHourRate;
            }

            // Calculate weekly breakdown for this month
            $weeklyBreakdown = calculateWeeklyBreakdownForMonth(
                $user,
                $monthWorklogs,
                $monthData['start_date'],
                $monthData['end_date'],
                $workStatusChanges
            );

            // Calculate performance for this month
            $monthPerformance = calculateMonthlyPerformance(
                $user,
                $monthWorklogs,
                $monthData['start_date'],
                $monthData['end_date'],
                $workStatusChanges
            );

            $monthlyBreakdown[] = [
                'month_number'       => $monthData['month_number'],
                // 'year'               => $monthData['year'],
                'start_date'         => $monthData['start_date'],
                'end_date'           => $monthData['end_date'],
                'label'              => $monthData['label'],
                'billable_hours'     => $monthMetrics['billable_hours'],
                'non_billable_hours' => $monthMetrics['non_billable_hours'],
                'total_hours'        => $monthMetrics['total_hours'],
                'nad_count'          => $monthNadCount,
                'nad_hours'          => round($monthNadHours, 2),
                'nad_data'           => $monthNadData,
                'performance'        => $monthPerformance,
                'entries_count'      => $monthMetrics['total_entries'],
                'weekly_breakdown'   => $weeklyBreakdown,
            ];

            // Add to totals
            $totalBillableHours += $monthMetrics['billable_hours'];
            $totalNonBillableHours += $monthMetrics['non_billable_hours'];
            $totalNadHours += $monthNadHours;
            $totalNadCount += $monthNadCount;
        }

        // Calculate overall category breakdown (simplified for monthly summary)
        $categoryBreakdown = calculateMonthlyCategoryBreakdown($worklogs);

        return [
            'summary'            => [
                'total_months'             => count($selectedMonths),
                'total_billable_hours'     => round($totalBillableHours, 2),
                'total_non_billable_hours' => round($totalNonBillableHours, 2),
                'total_hours'              => round($totalBillableHours + $totalNonBillableHours, 2),
                'total_nad_count'          => $totalNadCount,
                'total_nad_hours'          => round($totalNadHours, 2),
                'nad_hour_rate'            => $nadHourRate,
            ],
            'monthly_breakdown'  => $monthlyBreakdown,
            'category_breakdown' => $categoryBreakdown,
            'date_range'         => [
                'start' => $selectedMonths[0]['start_date'] ?? $startDate,
                'end'   => end($selectedMonths)['end_date'] ?? $endDate,
                'mode'  => 'month_summary',
            ],
        ];
    }
}

if (! function_exists('getMonthRangeForDates')) {
    /**
     * Generate simplified month-like ranges (each consisting of 4 weeks) between two dates.
     * Each "month" will always be 28 days (4 weeks), starting from the given start date.
     *
     * @param string $startDate   Must be a Monday (Y-m-d format)
     * @param string $endDate     Must be a Sunday (Y-m-d format)
     * @param int    $monthCount  Number of month-like ranges to generate
     * @return array
     * @throws \Exception
     */
    function getMonthRangeForDates($startDate, $endDate, $monthCount = 1)
    {
        $timezone = config('app.timezone', 'Asia/Singapore');

        // Create Carbon instances
        $start = Carbon::createFromFormat('Y-m-d', $startDate, $timezone)->startOfDay();
        $end   = Carbon::createFromFormat('Y-m-d', $endDate, $timezone)->endOfDay();

        // Validation
        if (! $start->isMonday()) {
            throw new \Exception("Start date must be a Monday.");
        }

        if (! $end->isSunday()) {
            throw new \Exception("End date must be a Sunday.");
        }

        if ($start->gt($end)) {
            throw new \Exception("Start date must be before end date.");
        }

        $months  = [];
        $current = $start->copy();

        for ($i = 0; $i < $monthCount; $i++) {
            $monthStart = $current->copy();
            $monthEnd   = $current->copy()->addDays(27); // 4 weeks = 28 days

            // Ensure we don’t exceed the end date
            if ($monthEnd->gt($end)) {
                break;
            }

            $months[] = [
                'month_number' => $i + 1,
                'start_date'   => $monthStart->format('Y-m-d'),
                'end_date'     => $monthEnd->format('Y-m-d'),
                'label'        => $monthStart->format('F'), // Month name only
            ];

            $current->addDays(28);
        }

        return $months;
    }
}

if (! function_exists('calculateWeeklyBreakdownForMonth')) {
    /**
     * Calculate weekly breakdown within a month.
     */
    function calculateWeeklyBreakdownForMonth($user, $monthWorklogs, $startDate, $endDate, $workStatusChanges)
    {
        $timezone    = config('app.timezone', 'Asia/Singapore');
        $nadHourRate = config('services.nad.nad_hour_rate.rate', 8);

        // Get all weeks that fall within this month
        $monthStart = Carbon::parse($startDate, $timezone);
        $monthEnd   = Carbon::parse($endDate, $timezone);

        // Find the Monday of the first week and Sunday of the last week
        $firstMonday = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $lastSunday  = $monthEnd->copy()->endOfWeek(Carbon::SUNDAY);

        $weeks       = [];
        $currentWeek = $firstMonday->copy();
        $weekNumber  = 1;

        while ($currentWeek->lte($lastSunday)) {
            $weekStart = $currentWeek->copy();
            $weekEnd   = $currentWeek->copy()->endOfWeek(Carbon::SUNDAY);

            // Only include weeks that overlap with the month
            if ($weekEnd->gte($monthStart) && $weekStart->lte($monthEnd)) {
                // Adjust week boundaries to month boundaries if needed
                $adjustedStart = $weekStart->lt($monthStart) ? $monthStart : $weekStart;
                $adjustedEnd   = $weekEnd->gt($monthEnd) ? $monthEnd : $weekEnd;

                // Get worklogs for this week
                $weekWorklogs = $monthWorklogs->filter(function ($worklog) use ($adjustedStart, $adjustedEnd, $timezone) {
                    $worklogDate = Carbon::parse($worklog->start_time)->setTimezone($timezone);
                    return $worklogDate->between($adjustedStart, $adjustedEnd);
                });

                // Calculate basic metrics for the week
                $weekMetrics = calculateBasicMetrics($weekWorklogs);

                // Get NAD data for this week
                $nadData = [
                    'start_date' => $adjustedStart->format('Y-m-d'),
                    'end_date'   => $adjustedEnd->format('Y-m-d'),
                    'blab_only'  => 1,
                    'email_list' => [$user->email],
                ];

                $nadResponse  = callNADApi('get_nad_by_date_range', $nadData);
                $weekNadData  = [];
                $weekNadCount = 0;
                $weekNadHours = 0;

                if (! empty($nadResponse['status']) && $nadResponse['status'] === true && ! empty($nadResponse['data'])) {
                    $weekNadData  = collect($nadResponse['data'])->firstWhere('email', $user->email) ?? [];
                    $weekNadCount = $weekNadData['nad_count'] ?? 0;
                    $weekNadHours = $weekNadCount * $nadHourRate;
                }

                $weeks[] = [
                    'week_number'        => $weekNumber,
                    'start_date'         => $adjustedStart->format('Y-m-d'),
                    'end_date'           => $adjustedEnd->format('Y-m-d'),
                    'label'              => sprintf(
                        'Week %d (%s - %s)',
                        $weekNumber,
                        $adjustedStart->format('M d'),
                        $adjustedEnd->format('M d')
                    ),
                    'billable_hours'     => $weekMetrics['billable_hours'],
                    'non_billable_hours' => $weekMetrics['non_billable_hours'],
                    'total_hours'        => $weekMetrics['total_hours'],
                    'nad_count'          => $weekNadCount,
                    'nad_hours'          => round($weekNadHours, 2),
                    'nad_data'           => $weekNadData,
                    'entries_count'      => $weekMetrics['total_entries'],
                ];

                $weekNumber++;
            }

            $currentWeek->addWeek();
        }

        return $weeks;
    }
}

if (! function_exists('calculateMonthlyPerformance')) {
    /**
     * Calculate performance metrics for a specific month.
     */
    function calculateMonthlyPerformance($user, $monthWorklogs, $startDate, $endDate, $workStatusChanges)
    {
        // Get work status periods for this month
        $workStatusPeriods = calculateWorkStatusPeriods($user, $startDate, $endDate, $workStatusChanges);

        // Calculate billable hours for the month
        $billableHours = $monthWorklogs->filter(function ($worklog) {
            return isTaskBillable($worklog->task);
        })->sum('duration') / 3600;

        // Determine setting combinations for the month
        $settingCombinations = determineSettingCombinations($user, $workStatusPeriods);

        $performances = [];
        foreach ($settingCombinations as $combination) {
            $targetTotalHours  = 0;
            $totalPeriodWeeks  = 0;
            $workStatusDisplay = [];

            // Calculate target hours for this month
            foreach ($workStatusPeriods as $periodIndex => $period) {
                $workStatus  = $period['work_status'] ?: 'full-time';
                $periodDays  = $period['days'];
                $periodWeeks = $periodDays / 7;
                $periodStart = $period['start_date'];
                $periodEnd   = $period['end_date'];

                $settingForPeriod     = getSettingForPeriod($user, $workStatus, $periodStart, $periodEnd, $combination, $periodIndex);
                $targetHoursForPeriod = $settingForPeriod['hours'] * $periodWeeks;
                $targetTotalHours += $targetHoursForPeriod;
                $totalPeriodWeeks += $periodWeeks;

                $statusDisplay = ucwords(str_replace('-', ' ', $workStatus));
                if (! in_array($statusDisplay, $workStatusDisplay)) {
                    $workStatusDisplay[] = $statusDisplay;
                }
            }
            // dd($startDate, $endDate, $targetTotalHours, $billableHours, $workStatusDisplay, $combination, $workStatusPeriods);

            $percentage = $targetTotalHours > 0 ? ($billableHours / $targetTotalHours) * 100 : 0;

            $status = 'POOR';
            if ($percentage >= 100) {
                $status = 'EXCELLENT';
            } elseif ($percentage >= 90) {
                $status = 'WARNING';
            }

            $performances[] = [
                'target_id'             => $combination['id'],
                'work_status'           => implode(' + ', $workStatusDisplay),
                'target_hours_per_week' => $combination['display_hours'],
                'target_total_hours'    => round($targetTotalHours, 2),
                'actual_hours'          => round($billableHours, 2),
                'percentage'            => round($percentage, 1),
                'status'                => $status,
                'actual_vs_target'      => round($billableHours - $targetTotalHours, 2),
                'period_weeks'          => round($totalPeriodWeeks, 1),
                'combination_details'   => $combination['details'],
            ];
        }

        return $performances;
    }
}

if (! function_exists('calculateMonthlyCategoryBreakdown')) {
    /**
     * Calculate category breakdown for monthly summary (simplified version).
     */
    function calculateMonthlyCategoryBreakdown($worklogs)
    {
        $categoryBreakdown = [];

        // Get unique task IDs from worklogs
        $taskIds = $worklogs->pluck('task_id')->unique()->filter();

        // Get all task-category mappings for the tasks in our worklogs
        $taskCategoryMappings = [];
        if ($taskIds->isNotEmpty()) {
            $taskCategoryMappings = \App\Models\TaskReportCategory::with(['task', 'category.categoryType'])
                ->whereIn('task_id', $taskIds)
                ->get()
                ->groupBy('task_id');
        }

        // Group worklogs by billable/non-billable based on category
        $billableWorklogs = $worklogs->filter(function ($worklog) use ($taskCategoryMappings) {
            return isTaskBillableByMapping($worklog->task_id, $taskCategoryMappings);
        });

        $nonBillableWorklogs = $worklogs->filter(function ($worklog) use ($taskCategoryMappings) {
            return isTaskNonBillableByMapping($worklog->task_id, $taskCategoryMappings);
        });

        if ($billableWorklogs->count() > 0) {
            $categoryBreakdown[] = processMonthlyCategoryGroup($billableWorklogs, $taskCategoryMappings, 'Billable');
        }

        if ($nonBillableWorklogs->count() > 0) {
            $categoryBreakdown[] = processMonthlyCategoryGroup($nonBillableWorklogs, $taskCategoryMappings, 'Non-Billable');
        }

        return array_filter($categoryBreakdown, function ($group) {
            return $group['total_hours'] > 0;
        });
    }
}

if (! function_exists('processMonthlyCategoryGroup')) {
    /**
     * Process category group for monthly summary (without task details).
     */
    function processMonthlyCategoryGroup($worklogs, $taskCategoryMappings, $type)
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

        // Process each category (simplified - no task breakdown)
        foreach ($worklogsByCategory as $categoryName => $categoryWorklogs) {
            if ($categoryName === 'Uncategorized') {
                continue; // Skip uncategorized for main summary
            }

            $categoryHours = collect($categoryWorklogs)->sum('duration') / 3600;
            $totalHours += $categoryHours;

            $categories[] = [
                'category_name' => $categoryName,
                'total_hours'   => round($categoryHours, 2),
                'entries_count' => count($categoryWorklogs),
            ];
        }

        // Sort categories by total hours descending
        usort($categories, function ($a, $b) {
            return $b['total_hours'] <=> $a['total_hours'];
        });

        return [
            'type'        => $type,
            'total_hours' => round($totalHours, 2),
            'categories'  => $categories,
        ];
    }
}