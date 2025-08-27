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

if (! function_exists('encryptSecureHRMSToken')) {
    function encryptSecureHRMSToken(): ?string
    {
        $key = env('API_NAD_SECRET_KEY');
        if (! $key) {
            return null;
        }

        $payload = [
            'secret_key' => $key,
            'datetime'   => Carbon::now()->toIso8601String(),
        ];

        return EncryptData(json_encode($payload), $key);
    }
}

if (! function_exists('TestencryptSecureHRMSToken')) {
    function TestencryptSecureHRMSToken(): ?string
    {
        $key = 'HRMS_SECRET_DATA_KEY';
        if (! $key) {
            return null;
        }

        $payload = [
            'secret_key' => $key,
            'datetime'   => (new DateTime())->format(DateTime::ATOM),
        ];

        return EncryptData(json_encode($payload), $key);
    }
}
if (! function_exists('decryptAndValidateSecureHRMSToken')) {
    function decryptAndValidateSecureHRMSToken(string $encrypted): bool
    {
        $key = env('API_NAD_SECRET_KEY');
        if (! $key || ! $encrypted) {
            return false;
        }

        $decryptedJson = DecryptData($encrypted, $key);
        $payload       = json_decode($decryptedJson, true);

        if (! $payload || ! isset($payload['datetime'])) {
            return false;
        }

        try {
            $datetime = Carbon::parse($payload['datetime']);
        } catch (\Exception $e) {
            return false;
        }

        $now = Carbon::now();
        if ($datetime->between($now->copy()->subDay(), $now->copy()->addDay())) {
            return $payload; // You can return `true` here if you only care about validity
        }

        return false;
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
            // if (! $user) {
            //     // For testing purposes, use user ID 1
            //     $user = \App\Models\IvaUser::find(1);
            //     if (! $user) {
            //         throw new \Exception('Test user (ID: 1) not found.');
            //     }
            // }

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

// NEW: Unified NAD API data fetching function
if (! function_exists('fetchNADDataForPeriod')) {
    /**
     * Fetch NAD data for a specific period.
     *
     * @param object $user The IvaUser model instance
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return array NAD data with count, hours, and raw data
     */
    function fetchNADDataForPeriod($user, $startDate, $endDate)
    {
        $nadHourRate = config('services.nad.nad_hour_rate.rate', 8);

        $nadData = [
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'blab_only'  => 1,
            'email_list' => [$user->email],
        ];

        $nadResponse = callNADApi('get_nad_by_date_range', $nadData);
        $nadUserData = [];
        $nadCount    = 0;
        $nadHours    = 0;

        if (! empty($nadResponse['status']) && $nadResponse['status'] === true && ! empty($nadResponse['data'])) {
            $nadUserData = collect($nadResponse['data'])->firstWhere('email', $user->email) ?? [];
            $nadCount    = $nadUserData['nad_count'] ?? 0;
            $nadHours    = $nadCount * $nadHourRate;
        }

        return [
            'nad_data'      => $nadUserData,
            'nad_count'     => $nadCount,
            'nad_hours'     => round($nadHours, 2),
            'nad_hour_rate' => $nadHourRate,
        ];
    }
}
if (! function_exists('fetchNADDataByEmails')) {
    /**
     * Fetch NAD data for a specific period.
     *
     * @param array $emails The array of emails need to fetch
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return array NAD data with count, hours, and raw data
     */
    function fetchNADDataByEmails($emails, $startDate, $endDate)
    {
        $nadHourRate = config('services.nad.nad_hour_rate.rate', 8);

        $nadData = [
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'blab_only'  => 1,
            'email_list' => $emails,
        ];

        $nadResponse = callNADApi('get_nad_by_date_range', $nadData);
        $nadUserData = [];
        $nadCount    = 0;
        $nadHours    = 0;

        if (! empty($nadResponse['status']) && $nadResponse['status'] === true && ! empty($nadResponse['data'])) {
            $nadUserData = $nadResponse['data'] ?? [];
            $nadCount    = is_array($nadUserData) && isset($nadUserData['nad_count']) ? $nadUserData['nad_count'] : 0;
            $nadHours    = $nadCount * $nadHourRate;
        }

        return [
            'nad_data'      => $nadUserData,
            'nad_count'     => $nadCount,
            'nad_hours'     => round($nadHours, 2),
            'nad_hour_rate' => $nadHourRate,
        ];
    }
}
if (! function_exists('fetchNADDataForUsers')) {
    /**
     * Fetch NAD data for a specific period.
     *
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return array NAD data with count, hours, and raw data
     */
    function fetchNADDataForUsers($startDate, $endDate)
    {
        $nadHourRate = config('services.nad.nad_hour_rate.rate', 8);

        $nadData = [
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'blab_only'  => 1,
            'email_list' => [],
        ];

        $nadResponse = callNADApi('get_nad_by_date_range', $nadData);
        $nadUserData = [];
        $nadCount    = 0;
        $nadHours    = 0;

        if (! empty($nadResponse['status']) && $nadResponse['status'] === true && ! empty($nadResponse['data'])) {
            $nadUserData = $nadResponse['data'] ?? [];
            $nadCount    = is_array($nadUserData) && isset($nadUserData['nad_count']) ? $nadUserData['nad_count'] : 0;
            $nadHours    = $nadCount * $nadHourRate;
        }

        return [
            'nad_data'      => $nadUserData,
            'nad_count'     => $nadCount,
            'nad_hours'     => round($nadHours, 2),
            'nad_hour_rate' => $nadHourRate,
        ];
    }
}
// For cache data
// Generic Caching Functions for Performance Reports
if (! function_exists('generateReportCacheKey')) {
    /**
     * Generate a cache key for performance reports
     *
     * @param string $reportType - 'daily', 'weekly', 'monthly', 'yearly'
     * @param array $params - Parameters like year, week_number, month, start_date, end_date, etc.
     * @param array $filters - Filters like work_status, region, search, sort_by, sort_order
     * @return string
     */
    function generateReportCacheKey($reportType, array $params = [], array $filters = [])
    {
        // Base key with report type
        $keyParts = ['performance_report', $reportType];

        // Add time period parameters
        if (isset($params['year'])) {
            $keyParts[] = 'year_' . $params['year'];
        }
        if (isset($params['week_number'])) {
            $keyParts[] = 'week_' . $params['week_number'];
        }
        if (isset($params['month'])) {
            $keyParts[] = 'month_' . $params['month'];
        }
        if (isset($params['start_date']) && isset($params['end_date'])) {
            $keyParts[] = 'range_' . $params['start_date'] . '_to_' . $params['end_date'];
        }

        // Add filter parameters
        $filterParts = [];
        foreach ($filters as $key => $value) {
            if (! empty($value)) {
                $filterParts[] = $key . '_' . md5($value);
            }
        }

        if (! empty($filterParts)) {
            $keyParts[] = 'filters_' . implode('_', $filterParts);
        }

        return implode(':', $keyParts);
    }
}

if (! function_exists('getCachedReportData')) {
    /**
     * Get cached report data
     *
     * @param string $reportType
     * @param array $params
     * @param array $filters
     * @return mixed|null
     */
    function getCachedReportData($reportType, array $params = [], array $filters = [])
    {
        $cacheKey = generateReportCacheKey($reportType, $params, $filters);

        try {
            return Cache::get($cacheKey);
        } catch (\Exception $e) {
            Log::warning('Cache retrieval failed', [
                'cache_key' => $cacheKey,
                'error'     => $e->getMessage(),
            ]);
            return null;
        }
    }
}

if (! function_exists('setCachedReportData')) {
    /**
     * Set cached report data
     *
     * @param string $reportType
     * @param array $params
     * @param array $filters
     * @param mixed $data
     * @param int $ttlMinutes - Time to live in minutes
     * @return bool
     */
    function setCachedReportData($reportType, array $params = [], array $filters = [], $data = null, $ttlMinutes = 30)
    {
        $cacheKey = generateReportCacheKey($reportType, $params, $filters);

        try {
            return Cache::put($cacheKey, $data, now()->addMinutes($ttlMinutes));
        } catch (\Exception $e) {
            Log::warning('Cache storage failed', [
                'cache_key' => $cacheKey,
                'error'     => $e->getMessage(),
            ]);
            return false;
        }
    }
}

if (! function_exists('clearReportCache')) {
    /**
     * Clear cached report data
     *
     * @param string $reportType - 'daily', 'weekly', 'monthly', 'yearly', 'all'
     * @param array $params - Optional specific parameters to clear
     * @return bool
     */
    function clearReportCache($reportType = 'all', array $params = [])
    {
        try {
            if ($reportType === 'all') {
                // Clear all performance report caches
                $pattern = 'performance_report:*';

                // Get all cache keys matching the pattern
                $keys = Cache::getRedis()->keys($pattern);

                if (! empty($keys)) {
                    foreach ($keys as $key) {
                        // Remove the Redis prefix from key name
                        $cleanKey = str_replace(config('database.redis.options.prefix'), '', $key);
                        Cache::forget($cleanKey);
                    }
                }

                Log::info('Cleared all performance report caches');
                return true;
            } else {
                // Clear specific report type cache
                if (! empty($params)) {
                    $cacheKey = generateReportCacheKey($reportType, $params);
                    Cache::forget($cacheKey);
                    Log::info('Cleared specific cache', ['cache_key' => $cacheKey]);
                } else {
                    // Clear all caches for this report type
                    $pattern = "performance_report:{$reportType}:*";
                    $keys    = Cache::getRedis()->keys($pattern);

                    if (! empty($keys)) {
                        foreach ($keys as $key) {
                            $cleanKey = str_replace(config('database.redis.options.prefix'), '', $key);
                            Cache::forget($cleanKey);
                        }
                    }

                    Log::info('Cleared report type caches', ['report_type' => $reportType]);
                }

                return true;
            }
        } catch (\Exception $e) {
            Log::error('Cache clearing failed', [
                'report_type' => $reportType,
                'params'      => $params,
                'error'       => $e->getMessage(),
            ]);
            return false;
        }
    }
}

if (! function_exists('getReportCacheInfo')) {
    /**
     * Get cache information for debugging
     *
     * @param string $reportType
     * @param array $params
     * @param array $filters
     * @return array
     */
    function getReportCacheInfo($reportType, array $params = [], array $filters = [])
    {
        $cacheKey = generateReportCacheKey($reportType, $params, $filters);

        try {
            $exists = Cache::has($cacheKey);
            $data   = $exists ? Cache::get($cacheKey) : null;

            return [
                'cache_key' => $cacheKey,
                'exists'    => $exists,
                'data_size' => $data ? strlen(serialize($data)) : 0,
                'cached_at' => $exists && is_array($data) && isset($data['cached_at']) ? $data['cached_at'] : null,
            ];
        } catch (\Exception $e) {
            return [
                'cache_key' => $cacheKey,
                'exists'    => false,
                'error'     => $e->getMessage(),
            ];
        }
    }
}

if (! function_exists('wrapDataWithCacheInfo')) {
    /**
     * Wrap data with cache metadata
     *
     * @param mixed $data
     * @return array
     */
    function wrapDataWithCacheInfo($data)
    {
        return [
            'data'          => $data,
            'cached_at'     => now()->toISOString(),
            'cache_version' => '1.0',
        ];
    }
}
// End of cache data

// NEW: Unified worklog filtering function
if (! function_exists('filterWorklogsByDateRange')) {
    /**
     * Filter worklogs by date range with timezone support.
     *
     * @param \Illuminate\Support\Collection $worklogs
     * @param string $startDate
     * @param string $endDate
     * @param string $timezone
     * @return \Illuminate\Support\Collection
     */
    function filterWorklogsByDateRange($worklogs, $startDate, $endDate, $timezone = 'Asia/Singapore')
    {
        $start = Carbon::parse($startDate, $timezone)->startOfDay();
        $end   = Carbon::parse($endDate, $timezone)->endOfDay();

        return $worklogs->filter(function ($worklog) use ($start, $end, $timezone) {
            $worklogDate = Carbon::parse($worklog->start_time)->setTimezone($timezone);
            return $worklogDate->between($start, $end);
        });
    }
}

// NEW: Unified task category checking function
if (! function_exists('checkTaskCategoryType')) {
    /**
     * Check if a task belongs to a specific category type.
     *
     * @param int $taskId
     * @param \Illuminate\Support\Collection $taskCategoryMappings
     * @param string $categoryType 'billable' or 'non-billable'
     * @return bool
     */
    function checkTaskCategoryType($taskId, $taskCategoryMappings, $categoryType)
    {
        if (! isset($taskCategoryMappings[$taskId]) || $taskCategoryMappings[$taskId]->isEmpty()) {
            return false;
        }

        $mapping = $taskCategoryMappings[$taskId]->first();
        if ($mapping && $mapping->category && $mapping->category->categoryType) {
            $actualCategoryType = $mapping->category->categoryType->setting_value;
            return strtolower($actualCategoryType) === strtolower($categoryType);
        }

        return false;
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
     * @param bool $getMonday - Whether to get Monday of hire date week
     * @return array - Returns adjusted start date and validation status
     */
    function ivaAdjustStartDate($user, $startDate, $endDate, $getMonday = false)
    {
        $originalStartDate = Carbon::parse($startDate);
        $originalEndDate   = Carbon::parse($endDate);
        $adjustedStartDate = $originalStartDate;
        $adjustedEndDate   = $originalEndDate;
        $changedStartDate  = false;
        $adjustmentMessage = '';
        // Check if hire date is in the middle of start and end date
        if (Carbon::parse($user->hire_date)->between($originalStartDate, $originalEndDate)) {
            $hireDate = Carbon::parse($user->hire_date);

            if ($getMonday) {
                // Get Monday of the week that hire date falls in
                $adjustedStartDate = $hireDate->copy()->startOfWeek(Carbon::MONDAY);
                $adjustmentMessage = 'The start date is the Monday of the week of the hire date. ' . $hireDate->format('M d, Y');
            } else {
                // Use hire date as is
                $adjustedStartDate = $hireDate;
                $adjustmentMessage = 'Start date adjusted to hire date';
            }
            $changedStartDate = true;
        }
        // If hire date is after end date
        elseif (Carbon::parse($user->hire_date)->gt($originalEndDate)) {
            $hireDate = Carbon::parse($user->hire_date);

            if ($getMonday) {
                // Get Monday of the week that hire date falls in
                $adjustedStartDate = $hireDate->copy()->startOfWeek(Carbon::MONDAY);
                $adjustedEndDate   = $hireDate->copy()->startOfWeek(Carbon::MONDAY);
                $adjustmentMessage = 'Hire date is after end date, both start and end dates set to Monday of hire date week';
            } else {
                // Use hire date as is
                $adjustedStartDate = $hireDate;
                $adjustedEndDate   = $hireDate;
                $adjustmentMessage = 'Hire date is after end date, both start and end dates set to hire date';
            }
            $changedStartDate = true;
        }

        // Calculate days difference (inclusive)
        $daysDiffInclusive = $adjustedStartDate->diffInDays($adjustedEndDate) + 1;

        return [
            'adjusted_start_date' => $adjustedStartDate->format('Y-m-d'),
            'adjusted_end_date'   => $adjustedEndDate->format('Y-m-d'),
            'original_start_date' => $originalStartDate->format('Y-m-d'),
            'original_end_date'   => $originalEndDate->format('Y-m-d'),
            'is_valid_week_range' => $daysDiffInclusive >= 7,
            'days_difference'     => $daysDiffInclusive,
            // 'hire_date_used'      => ! is_null($user->hire_date) && Carbon::parse($user->hire_date)->gte($originalStartDate),
            'changed_start_date'  => $changedStartDate,
            'adjustment_message'  => $adjustmentMessage,
        ];
        //  if (!is_null($user->hire_date)) {
        //     $hireDate = Carbon::parse($user->hire_date);

        //     // If hire_date is between original start and end date
        //     if ($hireDate->gt($originalStartDate) && $hireDate->lte($parsedEndDate)) {
        //         if ($getMonday) {
        //             $adjustedStartDate = $hireDate->startOfWeek(Carbon::MONDAY);
        //         } else {
        //             $adjustedStartDate = $hireDate;
        //         }
        //     }
        // }

        // // Check if user hire_date is null or before start date
        // if (is_null($user->hire_date) || Carbon::parse($user->hire_date)->lt($originalStartDate)) {
        //     $adjustedStartDate = $originalStartDate;
        //     $hireDateUsed      = false;
        // } else {
        //     $hireDate = Carbon::parse($user->hire_date);
        //     if ($getMonday) {
        //         // Get Monday of the week that hire date falls in
        //         $adjustedStartDate = $hireDate->startOfWeek(Carbon::MONDAY);
        //     } else {
        //         // Use hire date as is
        //         $adjustedStartDate = $hireDate;
        //     }
        //     $hireDateUsed = true;
        // }

        // // include both start and end dates
        // $daysDiffInclusive = $adjustedStartDate->diffInDays($parsedEndDate) + 1;

        // return [
        //     'adjusted_start_date' => $adjustedStartDate->format('Y-m-d'),
        //     'original_start_date' => $originalStartDate->format('Y-m-d'),
        //     'is_valid_week_range' => $daysDiffInclusive >= 7,
        //     'days_difference'     => $daysDiffInclusive,
        //     'hire_date_used'      => $hireDateUsed,
        //     'adjustment_message'  => $hireDateUsed && $adjustedStartDate->format('Y-m-d') !== $originalStartDate->format('Y-m-d')
        //     ? "System auto adjusted the start date from {$originalStartDate->format('Y-m-d')} to {$adjustedStartDate->format('Y-m-d')} based on Monday of hire date setup in the system."
        //     : null,
        // ];
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

// NEW: Unified performance calculation function
if (! function_exists('calculatePerformanceMetrics')) {
    /**
     * Calculate performance metrics for a user and period.
     *
     * @param object $user The IvaUser model instance
     * @param \Illuminate\Support\Collection $worklogs
     * @param string $startDate
     * @param string $endDate
     * @param \Illuminate\Support\Collection $workStatusChanges
     * @return array Performance metrics
     */
    function calculatePerformanceMetrics($user, $worklogs, $startDate, $endDate, $workStatusChanges)
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

            $status = 'BELOW';
            if ($percentage >= 101) {
                $status = 'EXCEEDED';
            } elseif ($percentage >= 99) {
                $status = 'MEET';
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

        return $performances;
    }
}

if (! function_exists('calculateTargetPerformancesForUser')) {
    /**
     * Calculate target performances with support for multiple work hour settings and custom overrides.
     */
    function calculateTargetPerformancesForUser($user, $worklogs, $startDate, $endDate, $workStatusChanges)
    {
        return calculatePerformanceMetrics($user, $worklogs, $startDate, $endDate, $workStatusChanges);
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
    function isTaskBillable($task, $taskCategories = null)
    {
        if (! $task) {
            return false;
        }

        if ($taskCategories === null) {
            $taskCategories = TaskReportCategory::where('task_id', $task->id)
                ->with(['category.categoryType'])
                ->get();
        }

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
    function isTaskNonBillable($task, $taskCategories = null)
    {
        if (! $task) {
            return false;
        }

        if ($taskCategories === null) {
            $taskCategories = TaskReportCategory::where('task_id', $task->id)
                ->with(['category.categoryType'])
                ->get();
        }

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

if (! function_exists('getWorkStatusChanges')) {
    /**
     * Get work status changes during the specified period.
     */
    function getWorkStatusChanges($user, $startDate, $endDate)
    {
        return IvaUserChangelog::where('iva_user_id', $user->id)
            ->where('field_changed', 'work_status')
        // ->whereBetween('effective_date', [$startDate, $endDate])
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

if (! function_exists('getMonthRangeForDates')) {
    /**
     * Generate simplified month-like ranges (each consisting of 4 weeks = 28 days).
     * Optionally adjusts start date to a given adjusted date if it falls inside a range.
     *
     * @param string      $startDate     Must be a Monday (Y-m-d format)
     * @param string      $endDate       Must be a Sunday (Y-m-d format)
     * @param int         $monthCount    Number of 28-day periods to generate
     * @param string|null $adjustedDate  Optional date to shift start within a matching 28-day period
     * @return array
     * @throws \Exception
     */
    function getMonthRangeForDates($startDate, $endDate, $monthCount = 1, $adjustedDate = null)
    {
        $timezone = config('app.timezone', 'Asia/Singapore');

        // Create Carbon instances
        $start    = Carbon::createFromFormat('Y-m-d', $startDate, $timezone)->startOfDay();
        $end      = Carbon::createFromFormat('Y-m-d', $endDate, $timezone)->endOfDay();
        $adjusted = $adjustedDate ? Carbon::createFromFormat('Y-m-d', $adjustedDate, $timezone)->startOfDay() : null;

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
            $rangeStart = $current->copy();
            $rangeEnd   = $current->copy()->addDays(27); // 28-day period

            if ($rangeEnd->gt($end)) {
                break; // skip if we exceed allowed range
            }

            // If adjusted date is set and falls within this 28-day period
            if ($adjusted && $adjusted->between($rangeStart, $rangeEnd)) {
                $rangeStart = $adjusted->copy();
            }

            // If adjusted date is AFTER this 28-day period, skip
            if ($adjusted && $adjusted->gt($rangeEnd)) {
                $current->addDays(28);
                continue;
            }

            $months[] = [
                'month_number' => $i + 1,
                'start_date'   => $rangeStart->format('Y-m-d'),
                'end_date'     => $rangeEnd->format('Y-m-d'),
                'label'        => $rangeStart->format('F'),
            ];

            $current->addDays(28);
        }

        return $months;
    }
}

// Database index optimization suggestions (to be added as migrations)
/*
Recommended composite indexes for optimal performance:

1. For worklogs_data table:
   - INDEX idx_worklogs_user_active_time (iva_id, is_active, start_time)
   - INDEX idx_worklogs_task_time (task_id, start_time)
   - INDEX idx_worklogs_user_task_time (iva_id, task_id, start_time)

2. For task_report_categories table:
   - INDEX idx_task_report_cat_task (task_id)
   - INDEX idx_task_report_cat_category (cat_id)

3. For report_categories table:
   - INDEX idx_report_categories_type_active (category_type, is_active)

4. For configuration_settings table:
   - INDEX idx_config_settings_type_active (setting_type_id, is_active)

Example migration to add these indexes:

Schema::table('worklogs_data', function (Blueprint $table) {
    $table->index(['iva_id', 'is_active', 'start_time'], 'idx_worklogs_user_active_time');
    $table->index(['task_id', 'start_time'], 'idx_worklogs_task_time');
    $table->index(['iva_id', 'task_id', 'start_time'], 'idx_worklogs_user_task_time');
});

Schema::table('task_report_categories', function (Blueprint $table) {
    $table->index(['task_id'], 'idx_task_report_cat_task');
    $table->index(['cat_id'], 'idx_task_report_cat_category');
});

Schema::table('report_categories', function (Blueprint $table) {
    $table->index(['category_type', 'is_active'], 'idx_report_categories_type_active');
});

Schema::table('configuration_settings', function (Blueprint $table) {
    $table->index(['setting_type_id', 'is_active'], 'idx_config_settings_type_active');
});
*/