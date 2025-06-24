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