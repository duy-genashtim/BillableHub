<?php
namespace App\Http\Controllers;

use App\Models\IvaUser;
use App\Models\Project;
use App\Models\Task;
use App\Models\WorklogsData;
use App\Services\ActivityLogService;
use App\Services\TimeDoctor\TimeDoctorService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimeDoctorLongOperationController extends Controller
{
    protected $timeDoctorService;

    const BATCH_SIZE       = 100;
    const MAX_RETRIES      = 3;
    const PAGINATION_LIMIT = 250;

    public function __construct(TimeDoctorService $timeDoctorService)
    {
        $this->timeDoctorService = $timeDoctorService;
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

        Log::info("Worklog sync request received", [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date'   => $endDate->format('Y-m-d'),
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
                $companyInfo = $this->timeDoctorService->getCompanyInfo();
                Log::debug("Company info response", ['data' => $companyInfo]);

                if (! isset($companyInfo['accounts'][0]['company_id'])) {
                    echo "data: " . json_encode([
                        'type'     => 'error',
                        'message'  => 'Could not retrieve company ID from TimeDoctor',
                        'progress' => 0,
                    ]) . "\n\n";
                    flush();
                    return;
                }

                $companyId = $companyInfo['accounts'][0]['company_id'];
                Log::info("Using TimeDoctor company ID: {$companyId}");

                $users = IvaUser::with('timedoctorUser')
                    ->where('is_active', true)
                    ->whereHas('timedoctorUser', function ($query) {
                        $query->where('is_active', true);
                    })
                    ->get();

                Log::info("Found " . $users->count() . " active IVA users with TimeDoctor mapping");

                if ($users->isEmpty()) {
                    echo "data: " . json_encode([
                        'type'     => 'error',
                        'message'  => 'No active TimeDoctor users found. Please sync users first.',
                        'progress' => 0,
                    ]) . "\n\n";
                    flush();
                    return;
                }

                $totalDays     = $startDate->diffInDays($endDate) + 1;
                $processedDays = 0;

                echo "data: " . json_encode([
                    'type'     => 'info',
                    'message'  => 'Starting worklog sync for date range: ' . $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d'),
                    'progress' => 0,
                ]) . "\n\n";
                flush();

                $currentDate = clone $startDate;
                $totalSynced = 0;

                while ($currentDate->lte($endDate)) {
                    $dayStr      = $currentDate->format('Y-m-d');
                    $dayProgress = round(($processedDays / $totalDays) * 100);

                    echo "data: " . json_encode([
                        'type'         => 'progress',
                        'message'      => "Processing worklog data for: {$dayStr} (Day " . ($processedDays + 1) . " of {$totalDays})",
                        'progress'     => $dayProgress,
                        'current_date' => $dayStr,
                    ]) . "\n\n";
                    flush();

                    $dayResult = $this->processUsersWorklogsForDay($companyId, $users, $currentDate, function ($message, $type = 'info') use ($dayProgress, $processedDays, $totalDays) {
                        $overallProgress = round(($processedDays / $totalDays) * 100);

                        echo "data: " . json_encode([
                            'type'     => $type,
                            'message'  => $message,
                            'progress' => $overallProgress,
                        ]) . "\n\n";
                        flush();
                    });

                    $totalSynced += $dayResult['inserted'] + $dayResult['updated'];
                    $processedDays++;
                    $currentDate->addDay();

                    $overallProgress = round(($processedDays / $totalDays) * 100);
                    echo "data: " . json_encode([
                        'type'     => 'progress',
                        'message'  => "Completed day {$dayStr}: {$overallProgress}% overall progress",
                        'progress' => $overallProgress,
                    ]) . "\n\n";
                    flush();
                }

                ActivityLogService::log('import_excel_time', 'TimeDoctor worklog sync completed via streaming', [
                    'module'       => 'timedoctor_integration',
                    'start_date'   => $startDate->format('Y-m-d'),
                    'end_date'     => $endDate->format('Y-m-d'),
                    'total_synced' => $totalSynced,
                    'total_days'   => $totalDays,
                ]);

                echo "data: " . json_encode([
                    'type'     => 'complete',
                    'message'  => 'Worklog sync completed for date range: ' . $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d'),
                    'progress' => 100,
                    'stats'    => ['records_synced' => $totalSynced],
                ]) . "\n\n";
                flush();

            } catch (\Exception $e) {
                Log::error('Error in worklog sync stream', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                ActivityLogService::log('import_excel_time', 'TimeDoctor worklog sync failed via streaming', [
                    'module' => 'timedoctor_integration',
                    'error'  => $e->getMessage(),
                ]);

                echo "data: " . json_encode([
                    'type'     => 'error',
                    'message'  => 'Error syncing worklogs: ' . $e->getMessage(),
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

    public function processUsersWorklogsForDay($companyId, $users, Carbon $date, callable $progressCallback)
    {
        $totalUsers     = $users->count();
        $processedUsers = 0;
        $totalInserted  = 0;
        $totalUpdated   = 0;
        $totalErrors    = 0;

        foreach ($users as $user) {
            if (! $user->timedoctorUser) {
                $progressCallback("Skipping user (no TimeDoctor user mapping)", 'warning');
                $processedUsers++;
                continue;
            }

            $userName = $user->timedoctorUser->tm_fullname;

            try {
                $progressCallback("Fetching worklogs for user: {$userName}");

                $offset       = 1;
                $hasMoreData  = true;
                $batchNumber  = 1;
                $userInserted = 0;
                $userUpdated  = 0;

                while ($hasMoreData) {
                    $worklogData = $this->timeDoctorService->getUserWorklogs(
                        $companyId,
                        $user->timedoctorUser->timedoctor_id,
                        $date->copy()->startOfDay(),
                        $date->copy()->endOfDay(),
                        $offset,
                        self::PAGINATION_LIMIT
                    );

                    Log::debug("TimeDoctor API response for user {$userName}", [
                        'response_keys'   => is_array($worklogData) ? array_keys($worklogData) : 'Response is not an array',
                        'worklogs_exists' => isset($worklogData['worklogs']),
                        'worklogs_type'   => isset($worklogData['worklogs']) ? gettype($worklogData['worklogs']) : 'not set',
                    ]);

                    if (! is_array($worklogData)) {
                        $progressCallback("Received invalid response from TimeDoctor API for user: {$userName}", 'error');
                        $totalErrors++;
                        break;
                    }

                    if (! isset($worklogData['worklogs']) || empty($worklogData['worklogs'])) {
                        $progressCallback("No worklogs found for user: {$userName} on {$date->format('Y-m-d')}", 'info');
                        break;
                    }

                    if (isset($worklogData['worklogs']['items'])) {
                        $worklogItems = $worklogData['worklogs']['items'];
                    } else if (is_array($worklogData['worklogs'])) {
                        $worklogItems = $worklogData['worklogs'];
                    } else {
                        $progressCallback("Unexpected worklog response format for user: {$userName}", 'warning');
                        $totalErrors++;
                        break;
                    }

                    $worklogCount = count($worklogItems);

                    if ($worklogCount === 0) {
                        $progressCallback("No worklog items in current batch for user: {$userName}", 'info');
                        break;
                    }

                    $progressCallback("Processing batch #{$batchNumber} for {$userName}: {$worklogCount} worklog records");

                    $result = $this->processWorklogBatch($worklogItems, $user, function ($message, $type = 'info') use ($progressCallback, $userName) {
                        $progressCallback("[User: {$userName}] {$message}", $type);
                    });

                    if (is_array($result)) {
                        $userInserted += $result['inserted'];
                        $userUpdated += $result['updated'];
                        $totalErrors += $result['errors'];
                    }

                    $hasMoreData = $worklogCount >= self::PAGINATION_LIMIT;
                    $offset += self::PAGINATION_LIMIT;
                    $batchNumber++;

                    usleep(200000);
                }

                $totalInserted += $userInserted;
                $totalUpdated += $userUpdated;

                $processedUsers++;
                $progressCallback("Completed processing worklogs for user: {$userName} (Added: {$userInserted}, Updated: {$userUpdated})");

            } catch (\Exception $e) {
                Log::error("Error processing worklogs for user {$userName}", [
                    'user_id' => $user->id,
                    'date'    => $date->format('Y-m-d'),
                    'error'   => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(),
                ]);

                $progressCallback("Error processing user {$userName}: {$e->getMessage()}", 'error');
                $totalErrors++;
                $processedUsers++;
            }
        }

        Log::info("Completed worklog sync for date {$date->format('Y-m-d')}", [
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
            Log::info("No worklog items to process for user {$user->timedoctorUser->tm_fullname}");
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
                    Log::debug("Processing worklog item", ['worklog' => $worklog]);

                    if (! isset($worklog['id']) || ! isset($worklog['start_time']) || ! isset($worklog['end_time'])) {
                        Log::warning("Missing required fields in worklog item", ['worklog' => $worklog]);
                        $errorCount++;
                        continue;
                    }

                    $startTime = Carbon::parse($worklog['start_time']);
                    $endTime   = Carbon::parse($worklog['end_time']);
                    $duration  = isset($worklog['length']) ? (int) $worklog['length'] : $endTime->diffInSeconds($startTime);

                    $projectId = null;
                    $taskId    = null;

                    if (isset($worklog['project_id'])) {
                        $project = Project::where('timedoctor_id', $worklog['project_id'])->first();
                        if ($project) {
                            $projectId = $project->id;
                        }
                    }

                    if (isset($worklog['task_id'])) {
                        $task = Task::whereJsonContains('user_list', ['timedoctorId' => $worklog['task_id']])
                            ->orWhere('user_list', 'like', '%"timedoctorId":"' . $worklog['task_id'] . '"%')
                            ->orWhere('user_list', 'like', '%"timedoctorId":' . $worklog['task_id'] . '%')
                            ->first();

                        if ($task) {
                            $taskId = $task->id;
                        }
                    }

                    $existingWorklog = WorklogsData::where('timedoctor_worklog_id', $worklog['id'])
                        ->where('api_type', 'timedoctor')
                        ->first();

                    if ($existingWorklog) {
                        $existingWorklog->update([
                            'timedoctor_project_id' => $worklog['project_id'] ?? null,
                            'timedoctor_task_id'    => $worklog['task_id'] ?? null,
                            'project_id'            => $projectId,
                            'task_id'               => $taskId,
                            'work_mode'             => $worklog['work_mode'] ?? '0',
                            'end_time'              => $endTime,
                            'duration'              => $duration,
                            'is_active'             => true,
                        ]);
                        $updatedCount++;
                    } else {
                        $worklogsToInsert[] = [
                            'iva_id'                => $user->id,
                            'timedoctor_project_id' => $worklog['project_id'] ?? null,
                            'timedoctor_task_id'    => $worklog['task_id'] ?? null,
                            'project_id'            => $projectId,
                            'task_id'               => $taskId,
                            'work_mode'             => $worklog['work_mode'] ?? '0',
                            'start_time'            => $startTime,
                            'end_time'              => $endTime,
                            'duration'              => $duration,
                            'device_id'             => null,
                            'comment'               => null,
                            'api_type'              => 'timedoctor',
                            'timedoctor_worklog_id' => $worklog['id'],
                            'timedoctor_version'    => 1,
                            'tm_user_id'            => $worklog['user_id'] ?? null,
                            'is_active'             => true,
                            'created_at'            => now(),
                            'updated_at'            => now(),
                        ];
                        $insertedCount++;
                    }
                } catch (\Exception $e) {
                    Log::error("Error processing individual worklog", [
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
                Log::info("Inserted {$insertedCount} worklog records");
                if ($progressCallback) {
                    $progressCallback("Inserted {$insertedCount} new worklog records", 'success');
                }
            } else {
                Log::info("No new worklog records to insert");
                if ($progressCallback) {
                    $progressCallback("No new worklog records to insert", 'info');
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
            Log::error("Error in processWorklogBatch", [
                'user'  => $user->timedoctorUser->tm_fullname ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($progressCallback) {
                $progressCallback("Error processing worklog batch: {$e->getMessage()}", 'error');
            }

            return ['inserted' => 0, 'updated' => 0, 'errors' => 1];
        }
    }
}