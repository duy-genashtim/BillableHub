<?php

namespace App\Services;

use App\Models\DailyWorklogSummary;
use App\Models\IvaUser;
use App\Models\WorklogsData;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailyWorklogSummaryService
{
    /**
     * Calculate daily worklog summaries for given parameters
     */
    public function calculateSummaries(array $params): array
    {
        $startDate = $params['start_date'];
        $endDate = $params['end_date'];
        $ivaUserIds = $params['iva_user_ids'] ?? [];
        $calculateAll = $params['calculate_all'] ?? false;

        $results = [];
        $totalProcessed = 0;
        $totalErrors = 0;

        try {
            // Log calculation parameters for debugging
            Log::info('Starting daily worklog summary calculation', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'calculate_all' => $calculateAll,
                'iva_user_ids' => $ivaUserIds,
                'iva_user_count' => count($ivaUserIds),
            ]);

            // Get IVA users to process
            $ivaUsers = $this->getIvaUsersToProcess($ivaUserIds, $calculateAll);
            $dateRange = $this->getDateRange($startDate, $endDate, $calculateAll);

            Log::info('Calculation scope determined', [
                'users_to_process' => $ivaUsers->count(),
                'user_names' => $ivaUsers->pluck('full_name')->toArray(),
                'date_range_count' => count($dateRange),
                'date_range' => [
                    'start' => $dateRange[0] ?? null,
                    'end' => $dateRange[count($dateRange) - 1] ?? null,
                ]
            ]);

            foreach ($ivaUsers as $ivaUser) {
                $userResult = [
                    'iva_id' => $ivaUser->id,
                    'iva_name' => $ivaUser->full_name,
                    'dates_processed' => [],
                    'dates_failed' => [],
                    'total_dates' => count($dateRange),
                    'success_count' => 0,
                    'error_count' => 0,
                ];

                foreach ($dateRange as $date) {
                    try {
                        $this->calculateDailySummaryForUser($ivaUser->id, $date);
                        $userResult['dates_processed'][] = $date;
                        $userResult['success_count']++;
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        Log::error("Failed to calculate summary for IVA {$ivaUser->id} on {$date}: ".$e->getMessage());
                        $userResult['dates_failed'][] = [
                            'date' => $date,
                            'error' => $e->getMessage(),
                        ];
                        $userResult['error_count']++;
                        $totalErrors++;
                    }
                }

                $results[] = $userResult;
            }

            return [
                'success' => true,
                'message' => 'Calculation completed',
                'summary' => [
                    'total_ivas' => count($ivaUsers),
                    'total_dates' => count($dateRange),
                    'total_processed' => $totalProcessed,
                    'total_errors' => $totalErrors,
                ],
                'details' => $results,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to calculate daily worklog summaries: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Calculation failed: '.$e->getMessage(),
                'summary' => [
                    'total_ivas' => 0,
                    'total_dates' => 0,
                    'total_processed' => $totalProcessed,
                    'total_errors' => $totalErrors,
                ],
                'details' => $results,
            ];
        }
    }

    /**
     * Calculate daily summary for a specific user and date
     */
    protected function calculateDailySummaryForUser(int $ivaUserId, string $date): void
    {
        DB::transaction(function () use ($ivaUserId, $date) {
            // Delete existing summaries for this date
            DailyWorklogSummary::where('iva_id', $ivaUserId)
                ->where('report_date', $date)
                ->delete();

            // Get worklogs for this user and date
            $worklogs = WorklogsData::where('iva_id', $ivaUserId)
                ->whereDate('start_time', $date)
                ->where('is_active', true)
                ->with(['task.reportCategories.categoryType'])
                ->get();

            if ($worklogs->isEmpty()) {
                return; // No worklogs for this date
            }

            // Group worklogs by category
            $categoryGroups = [];
            $uncategorizedDuration = 0;
            $uncategorizedCount = 0;

            foreach ($worklogs as $worklog) {
                if (! $worklog->task) {
                    // Handle worklogs without tasks - add to uncategorized
                    $uncategorizedDuration += $worklog->duration;
                    $uncategorizedCount++;

                    continue;
                }

                // Get the first report category for this task
                $reportCategory = $worklog->task->reportCategories->first();
                if (! $reportCategory) {
                    // Handle tasks without categories - add to uncategorized
                    $uncategorizedDuration += $worklog->duration;
                    $uncategorizedCount++;

                    continue;
                }

                $categoryId = $reportCategory->id;
                $categoryType = $reportCategory->categoryType->setting_value ?? 'unknown';

                if (! isset($categoryGroups[$categoryId])) {
                    $categoryGroups[$categoryId] = [
                        'report_category_id' => $categoryId,
                        'category_type' => $categoryType,
                        'total_duration' => 0,
                        'entries_count' => 0,
                    ];
                }

                $categoryGroups[$categoryId]['total_duration'] += $worklog->duration;
                $categoryGroups[$categoryId]['entries_count']++;
            }

            // Add uncategorized group if there are any uncategorized worklogs
            if ($uncategorizedCount > 0) {
                $categoryGroups['uncategorized'] = [
                    'report_category_id' => null,
                    'category_type' => 'uncategorized',
                    'total_duration' => $uncategorizedDuration,
                    'entries_count' => $uncategorizedCount,
                ];
            }

            // Insert summary records
            foreach ($categoryGroups as $categoryData) {
                try {
                    DailyWorklogSummary::create([
                        'iva_id' => $ivaUserId,
                        'report_category_id' => $categoryData['report_category_id'],
                        'report_date' => $date,
                        'total_duration' => $categoryData['total_duration'],
                        'entries_count' => $categoryData['entries_count'],
                        'category_type' => $categoryData['category_type'],
                    ]);

                } catch (\Exception $e) {
                    Log::error("DailyWorklogSummary: Failed to insert summary for user {$ivaUserId} on {$date}, category: ".($categoryData['category_type'] ?? 'unknown').' - Error: '.$e->getMessage());
                    throw $e; // Re-throw to maintain transaction rollback behavior
                }
            }
        });
    }

    /**
     * Get IVA users to process
     * Note: $calculateAll refers to date range calculation, not user selection
     */
    protected function getIvaUsersToProcess(array $ivaUserIds, bool $calculateAll): \Illuminate\Database\Eloquent\Collection
    {
        // If no specific user IDs provided, calculate for all active users
        if (empty($ivaUserIds)) {
            return IvaUser::where('is_active', true)->get();
        }

        // Calculate only for specified users
        return IvaUser::whereIn('id', $ivaUserIds)->where('is_active', true)->get();
    }

    /**
     * Get date range to process
     */
    protected function getDateRange(?string $startDate, ?string $endDate, bool $calculateAll): array
    {
        if ($calculateAll) {
            // Get the earliest worklog date
            $earliestWorklog = WorklogsData::where('is_active', true)
                ->orderBy('start_time')
                ->first();

            $startDate = $earliestWorklog ? $earliestWorklog->start_time->toDateString() : Carbon::now()->subYear()->toDateString();
            $endDate = Carbon::now()->toDateString();
        }

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $dates = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    /**
     * Get calculation progress (for real-time updates)
     */
    public function getCalculationProgress(string $sessionId): array
    {
        // This could be implemented with Redis or database if needed
        // For now, return basic status
        return [
            'status' => 'completed',
            'progress' => 100,
            'message' => 'Calculation completed',
        ];
    }

    /**
     * Validate calculation parameters
     */
    public function validateCalculationParams(array $params): array
    {
        $errors = [];

        // Validate date range
        if (! empty($params['start_date']) && ! empty($params['end_date'])) {
            $startDate = Carbon::parse($params['start_date']);
            $endDate = Carbon::parse($params['end_date']);

            if ($startDate->gt($endDate)) {
                $errors[] = 'Start date cannot be later than end date';
            }

            if ($startDate->diffInDays($endDate) > 365) {
                $errors[] = 'Date range cannot exceed 365 days';
            }
        }

        // Validate IVA user IDs
        if (! empty($params['iva_user_ids'])) {
            $validIds = IvaUser::whereIn('id', $params['iva_user_ids'])
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            $invalidIds = array_diff($params['iva_user_ids'], $validIds);
            if (! empty($invalidIds)) {
                $errors[] = 'Invalid IVA user IDs: '.implode(', ', $invalidIds);
            }
        }

        return $errors;
    }
}
