<?php
namespace App\Http\Controllers;

use App\Models\IvaUser;
use App\Models\Project;
use App\Models\Task;
use App\Models\WorklogsData;
use App\Services\ActivityLogService;
use App\Services\TimeDoctor\TimeDoctorV2Service;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimeDoctorV2LongOperationController extends Controller
{
    protected $timeDoctorV2Service;

    const BATCH_SIZE  = 100;
    const MAX_RETRIES = 3;

    public function __construct(TimeDoctorV2Service $timeDoctorV2Service)
    {
        $this->timeDoctorV2Service = $timeDoctorV2Service;
        set_time_limit(600);
        ini_set('memory_limit', '1024M');
    }

    public function streamWorklogSync(Request $request): StreamedResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date'   => 'required|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return new StreamedResponse(function () use ($validator) {
                echo "data: " . json_encode([
                    'type'     => 'error',
                    'message'  => 'Validation error: ' . implode(', ', $validator->errors()->all()),
                    'progress' => 0,
                ]) . "\n\n";
                flush();
            });
        }

        $startDate = Carbon::parse($request->input('start_date'));
        $endDate   = Carbon::parse($request->input('end_date'));

        Log::info("TimeDoctor V2 worklog sync request received", [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date'   => $endDate->format('Y-m-d'),
            'user_id'    => auth()->id(),
        ]);

        if ($startDate->diffInDays($endDate) > 30) {
            return new StreamedResponse(function () {
                echo "data: " . json_encode([
                    'type'     => 'error',
                    'message'  => 'Date range cannot exceed 31 days',
                    'progress' => 0,
                ]) . "\n\n";
                flush();
            });
        }

        $response = new StreamedResponse(function () use ($startDate, $endDate) {
            if (ob_get_level()) {
                ob_end_clean();
            }

            try {
                // First check if we have a valid token
                echo "data: " . json_encode([
                    'type'     => 'info',
                    'message'  => 'Checking TimeDoctor V2 connection...',
                    'progress' => 0,
                ]) . "\n\n";
                flush();

                $companyInfo = $this->timeDoctorV2Service->getCompanyInfo();
                Log::debug("TimeDoctor V2 company info response", [
                    'has_data'  => ! empty($companyInfo),
                    'data_keys' => is_array($companyInfo) ? array_keys($companyInfo) : 'not array',
                ]);

                if (empty($companyInfo)) {
                    echo "data: " . json_encode([
                        'type'     => 'error',
                        'message'  => 'Could not retrieve company info from TimeDoctor V2. Please check your connection.',
                        'progress' => 0,
                    ]) . "\n\n";
                    flush();
                    return;
                }

                echo "data: " . json_encode([
                    'type'     => 'success',
                    'message'  => 'TimeDoctor V2 connection verified successfully',
                    'progress' => 5,
                ]) . "\n\n";
                flush();

                $users = IvaUser::with('timedoctorV2User')
                    ->where('is_active', true)
                    ->where('timedoctor_version', 2)
                    ->whereHas('timedoctorV2User', function ($query) {
                        $query->where('is_active', true);
                    })
                    ->get();
                Log::info("Found " . $users->count() . " active IVA users with TimeDoctor V2 mapping");

                if ($users->isEmpty()) {
                    echo "data: " . json_encode([
                        'type'     => 'error',
                        'message'  => 'No active TimeDoctor V2 users found. Please sync users first.',
                        'progress' => 0,
                    ]) . "\n\n";
                    flush();
                    return;
                }

                $totalDays     = $startDate->diffInDays($endDate) + 1;
                $processedDays = 0;

                echo "data: " . json_encode([
                    'type'     => 'info',
                    'message'  => 'Starting TimeDoctor V2 worklog sync for date range: ' . $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d') . ' (' . $users->count() . ' users)',
                    'progress' => 10,
                ]) . "\n\n";
                flush();

                $currentDate = clone $startDate;
                $totalSynced = 0;

                while ($currentDate->lte($endDate)) {
                    $dayStr      = $currentDate->format('Y-m-d');
                    $dayProgress = round((($processedDays / $totalDays) * 80) + 10); // 10-90% range

                    echo "data: " . json_encode([
                        'type'         => 'progress',
                        'message'      => "Processing TimeDoctor V2 worklog data for: {$dayStr} (Day " . ($processedDays + 1) . " of {$totalDays})",
                        'progress'     => $dayProgress,
                        'current_date' => $dayStr,
                    ]) . "\n\n";
                    flush();

                    $dayResult = $this->processUsersWorklogsForDay($users, $currentDate, function ($message, $type = 'info') use ($dayProgress) {
                        echo "data: " . json_encode([
                            'type'     => $type,
                            'message'  => $message,
                            'progress' => $dayProgress,
                        ]) . "\n\n";
                        flush();
                    });

                    $totalSynced += $dayResult['inserted'] + $dayResult['updated'];
                    $processedDays++;
                    $currentDate->addDay();

                    $overallProgress = round((($processedDays / $totalDays) * 80) + 10);
                    echo "data: " . json_encode([
                        'type'     => 'progress',
                        'message'  => "Completed day {$dayStr}: {$overallProgress}% overall progress (Synced: {$dayResult['inserted']} new, {$dayResult['updated']} updated)",
                        'progress' => $overallProgress,
                    ]) . "\n\n";
                    flush();
                }

                ActivityLogService::log('sync_timedoctor_v2_data', 'TimeDoctor V2 worklog sync completed via streaming', [
                    'module'       => 'timedoctor_v2_integration',
                    'start_date'   => $startDate->format('Y-m-d'),
                    'end_date'     => $endDate->format('Y-m-d'),
                    'total_synced' => $totalSynced,
                    'total_days'   => $totalDays,
                    'version'      => 2,
                ]);

                echo "data: " . json_encode([
                    'type'     => 'complete',
                    'message'  => 'TimeDoctor V2 worklog sync completed for date range: ' . $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d') . ' (Total records: ' . $totalSynced . ')',
                    'progress' => 100,
                    'stats'    => ['records_synced' => $totalSynced],
                ]) . "\n\n";
                flush();

            } catch (\Exception $e) {
                Log::error('Error in TimeDoctor V2 worklog sync stream', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                ActivityLogService::log('sync_timedoctor_v2_data', 'TimeDoctor V2 worklog sync failed via streaming', [
                    'module'  => 'timedoctor_v2_integration',
                    'error'   => $e->getMessage(),
                    'version' => 2,
                ]);

                echo "data: " . json_encode([
                    'type'     => 'error',
                    'message'  => 'Error syncing TimeDoctor V2 worklogs: ' . $e->getMessage(),
                    'progress' => 0,
                ]) . "\n\n";
                flush();
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    public function processUsersWorklogsForDay($users, Carbon $date, callable $progressCallback)
    {
        $totalUsers     = $users->count();
        $processedUsers = 0;
        $totalInserted  = 0;
        $totalUpdated   = 0;
        $totalErrors    = 0;

        foreach ($users as $user) {
            if (! $user->timedoctorV2User) {
                $progressCallback("Skipping user (no TimeDoctor V2 user mapping)", 'warning');
                $processedUsers++;
                continue;
            }

            $userName         = $user->timedoctorV2User->tm_fullname;
            $timeDoctorUserId = $user->timedoctorV2User->timedoctor_id;

            try {
                $progressCallback("Fetching TimeDoctor V2 worklogs for user: {$userName}");

                // Use the helper functions for proper timezone conversion
                $localStartOfDay = Carbon::createFromFormat('Y-m-d H:i:s', $date->format('Y-m-d') . ' 00:00:00', config('app.timezone', 'Asia/Singapore'));
                $localEndOfDay   = Carbon::createFromFormat('Y-m-d H:i:s', $date->format('Y-m-d') . ' 23:59:59', config('app.timezone', 'Asia/Singapore'));

                $worklogData = $this->timeDoctorV2Service->getUserWorklogs(
                    $timeDoctorUserId,
                    $localStartOfDay,
                    $localEndOfDay
                );

                Log::debug("TimeDoctor V2 API response for user {$userName}", [
                    'response_keys' => is_array($worklogData) ? array_keys($worklogData) : 'Response is not an array',
                    'data_exists'   => isset($worklogData['data']),
                    'data_type'     => isset($worklogData['data']) ? gettype($worklogData['data']) : 'not set',
                    'user_id'       => $timeDoctorUserId,
                    'date'          => $date->format('Y-m-d'),
                ]);

                if (! is_array($worklogData)) {
                    $progressCallback("Received invalid response from TimeDoctor V2 API for user: {$userName}", 'error');
                    $totalErrors++;
                    $processedUsers++;
                    continue;
                }

                if (! isset($worklogData['data']) || empty($worklogData['data'])) {
                    $progressCallback("No worklogs found for user: {$userName} on {$date->format('Y-m-d')}", 'info');
                    $processedUsers++;
                    continue;
                }

                // TimeDoctor V2 returns nested arrays by day
                $worklogItems = [];
                foreach ($worklogData['data'] as $dayData) {
                    if (is_array($dayData)) {
                        $worklogItems = array_merge($worklogItems, $dayData);
                    }
                }

                if (empty($worklogItems)) {
                    $progressCallback("No worklog items for user: {$userName}", 'info');
                    $processedUsers++;
                    continue;
                }

                $progressCallback("Processing {$userName}: " . count($worklogItems) . " worklog records");

                $result = $this->processWorklogBatch($worklogItems, $user, function ($message, $type = 'info') use ($progressCallback, $userName) {
                    $progressCallback("[User: {$userName}] {$message}", $type);
                });

                if (is_array($result)) {
                    $totalInserted += $result['inserted'];
                    $totalUpdated += $result['updated'];
                    $totalErrors += $result['errors'];
                }

                $progressCallback("Completed processing TimeDoctor V2 worklogs for user: {$userName} (Added: {$result['inserted']}, Updated: {$result['updated']})");

            } catch (\Exception $e) {
                Log::error("Error processing TimeDoctor V2 worklogs for user {$userName}", [
                    'user_id'            => $user->id,
                    'timedoctor_user_id' => $timeDoctorUserId,
                    'date'               => $date->format('Y-m-d'),
                    'error'              => $e->getMessage(),
                    'trace'              => $e->getTraceAsString(),
                ]);

                $progressCallback("Error processing user {$userName}: {$e->getMessage()}", 'error');
                $totalErrors++;
            }

            $processedUsers++;
        }

        Log::info("Completed TimeDoctor V2 worklog sync for date {$date->format('Y-m-d')}", [
            'total_inserted' => $totalInserted,
            'total_updated'  => $totalUpdated,
            'total_errors'   => $totalErrors,
        ]);

        if ($progressCallback) {
            $progressCallback("Daily summary: Added {$totalInserted} records, Updated {$totalUpdated} records, Errors: {$totalErrors}", 'success');
        }

        return [
            'inserted' => $totalInserted,
            'updated'  => $totalUpdated,
            'errors'   => $totalErrors,
        ];
    }

    private function processWorklogBatch(array $worklogItems, $user, callable $progressCallback = null)
    {
        if (empty($worklogItems)) {
            if ($progressCallback) {
                $progressCallback("No worklog items found for this user", 'info');
            }
            return ['inserted' => 0, 'updated' => 0, 'errors' => 0];
        }

        $insertedCount = 0;
        $updatedCount  = 0;
        $errorCount    = 0;

        try {
            DB::beginTransaction();

            $worklogsToInsert = [];

            foreach ($worklogItems as $worklog) {
                try {
                    Log::debug("Processing TimeDoctor V2 worklog item", ['worklog' => $worklog]);

                    if (! isset($worklog['start']) || ! isset($worklog['time'])) {
                        Log::warning("Missing required fields in TimeDoctor V2 worklog item", ['worklog' => $worklog]);
                        $errorCount++;
                        continue;
                    }

                    // Convert from UTC to local timezone for proper storage
                    // TimeDoctor V2 returns UTC timestamps, convert to Singapore time
                    $startTimeUtc = Carbon::parse($worklog['start'], 'UTC');
                    $startTime    = convertFromTimeDoctorTimezone($startTimeUtc, config('app.timezone', 'Asia/Singapore'));
                    $endTime      = $startTime->copy()->addSeconds($worklog['time']);

                    // Duration is already in seconds from V2 API
                    $duration = (int) $worklog['time'];

                    $projectId = null;
                    $taskId    = null;

                    if (isset($worklog['projectId'])) {
                        $project = Project::where('timedoctor_id', $worklog['projectId'])
                            ->where('timedoctor_version', 2)
                            ->first();
                        if ($project) {
                            $projectId = $project->id;
                        }
                    }

                    if (isset($worklog['taskId'])) {
                        $task = Task::whereJsonContains('user_list', ['tId' => $worklog['taskId'], 'vId' => 2])
                            ->first();

                        if ($task) {
                            $taskId = $task->id;
                        }
                    }

                    // Create unique identifier for V2 worklogs
                    $worklogId = $worklog['userId'] . '_' . $worklog['start'] . '_' . $worklog['time'];

                    $existingWorklog = WorklogsData::where('timedoctor_worklog_id', $worklogId)
                        ->where('api_type', 'timedoctor')
                        ->where('timedoctor_version', 2)
                        ->first();

                    if ($existingWorklog) {
                        $existingWorklog->update([
                            'timedoctor_project_id' => $worklog['projectId'] ?? null,
                            'timedoctor_task_id'    => $worklog['taskId'] ?? null,
                            'project_id'            => $projectId,
                            'task_id'               => $taskId,
                            'work_mode'             => $worklog['mode'] ?? 'computer',
                            'end_time'              => $endTime,
                            'duration'              => $duration,
                            'is_active'             => true,
                        ]);
                        $updatedCount++;
                    } else {
                        $worklogsToInsert[] = [
                            'iva_id'                => $user->id,
                            'timedoctor_project_id' => $worklog['projectId'] ?? null,
                            'timedoctor_task_id'    => $worklog['taskId'] ?? null,
                            'project_id'            => $projectId,
                            'task_id'               => $taskId,
                            'work_mode'             => $worklog['mode'] ?? 'computer',
                            'start_time'            => $startTime,
                            'end_time'              => $endTime,
                            'duration'              => $duration,
                            'device_id'             => $worklog['deviceId'] ?? null,
                            'comment'               => null,
                            'api_type'              => 'timedoctor',
                            'timedoctor_worklog_id' => $worklogId,
                            'timedoctor_version'    => 2,
                            'tm_user_id'            => $worklog['userId'] ?? null,
                            'is_active'             => true,
                            'created_at'            => now(),
                            'updated_at'            => now(),
                        ];
                        $insertedCount++;
                    }
                } catch (\Exception $e) {
                    Log::error("Error processing individual TimeDoctor V2 worklog", [
                        'worklog' => $worklog ?? 'unknown',
                        'error'   => $e->getMessage(),
                        'trace'   => $e->getTraceAsString(),
                    ]);
                    $errorCount++;
                }
            }

            if (! empty($worklogsToInsert)) {
                foreach (array_chunk($worklogsToInsert, self::BATCH_SIZE) as $chunk) {
                    WorklogsData::insert($chunk);
                }
                Log::info("Inserted {$insertedCount} TimeDoctor V2 worklog records");
                if ($progressCallback) {
                    $progressCallback("Inserted {$insertedCount} new TimeDoctor V2 worklog records", 'success');
                }
            }

            DB::commit();

            return [
                'inserted' => $insertedCount,
                'updated'  => $updatedCount,
                'errors'   => $errorCount,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error in processWorklogBatch for TimeDoctor V2", [
                'user'  => $user->timedoctorV2User->tm_fullname ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($progressCallback) {
                $progressCallback("Error processing TimeDoctor V2 worklog batch: {$e->getMessage()}", 'error');
            }

            return ['inserted' => 0, 'updated' => 0, 'errors' => 1];
        }
    }
}