<?php
namespace App\Jobs;

use App\Models\IvaUser;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeDoctorWorklogSyncMetadata;
use App\Models\WorklogsData;
use App\Services\ActivityLogService;
use App\Services\TimeDoctor\TimeDoctorService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncTimeDoctorWorklogs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $startDate;
    protected $endDate;

    const BATCH_SIZE       = 100;
    const PAGINATION_LIMIT = 250;

    public $timeout       = 1800; // 30 minutes
    public $tries         = 3;
    public $maxExceptions = 1;

    public function __construct(string $startDate, string $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate   = $endDate;
    }

    public function handle(TimeDoctorService $timeDoctorService): void
    {
        $startDate = Carbon::parse($this->startDate);
        $endDate   = Carbon::parse($this->endDate);

        Log::info("Starting TimeDoctor worklog sync job", [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date'   => $endDate->format('Y-m-d'),
            'job_id'     => $this->job->getJobId(),
        ]);

        try {
            // Get company info
            $companyInfo = $timeDoctorService->getCompanyInfo();

            if (! isset($companyInfo['accounts'][0]['company_id'])) {
                throw new \Exception('Could not retrieve company ID from TimeDoctor');
            }

            $companyId = $companyInfo['accounts'][0]['company_id'];

            // Get active users
            $users = IvaUser::with('timedoctorUser')
                ->where('is_active', true)
                ->whereHas('timedoctorUser', function ($query) {
                    $query->where('is_active', true);
                })
                ->get();

            if ($users->isEmpty()) {
                throw new \Exception('No active TimeDoctor users found');
            }

            $totalDays     = $startDate->diffInDays($endDate) + 1;
            $processedDays = 0;
            $totalSynced   = 0;

            // Process each day in the range
            $currentDate = clone $startDate;

            while ($currentDate->lte($endDate)) {
                $dayStr = $currentDate->format('Y-m-d');
                $processedDays++;

                Log::info("Processing day {$processedDays} of {$totalDays}: {$dayStr}");

                // Create or update sync metadata for this day
                $syncMeta = TimeDoctorWorklogSyncMetadata::updateOrCreate(
                    ['sync_date' => $currentDate->format('Y-m-d')],
                    [
                        'status'     => 'in_progress',
                        'started_at' => now(),
                        'is_synced'  => false,
                    ]
                );

                try {
                    $dayResult = $this->processUsersWorklogsForDay(
                        $timeDoctorService,
                        $companyId,
                        $users,
                        $currentDate
                    );

                    $daySynced = $dayResult['inserted'] + $dayResult['updated'];
                    $totalSynced += $daySynced;

                    // Update sync metadata
                    $syncMeta->update([
                        'status'         => 'completed',
                        'is_synced'      => true,
                        'synced_records' => $daySynced,
                        'total_records'  => $dayResult['total_processed'] ?? $daySynced,
                        'completed_at'   => now(),
                    ]);

                    Log::info("Completed day {$dayStr}", [
                        'inserted' => $dayResult['inserted'],
                        'updated'  => $dayResult['updated'],
                        'errors'   => $dayResult['errors'],
                    ]);

                } catch (\Exception $e) {
                    Log::error("Failed to sync worklogs for date {$dayStr}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    // Update sync metadata with error
                    $syncMeta->update([
                        'status'        => 'failed',
                        'error_message' => $e->getMessage(),
                    ]);

                    // Continue with next day instead of failing the entire job
                }

                $currentDate->addDay();
            }

            ActivityLogService::log('sync_timedoctor_data', 'TimeDoctor worklog sync job completed', [
                'module'       => 'timedoctor_integration',
                'start_date'   => $startDate->format('Y-m-d'),
                'end_date'     => $endDate->format('Y-m-d'),
                'total_synced' => $totalSynced,
                'total_days'   => $totalDays,
                'job_id'       => $this->job->getJobId(),
            ]);

            Log::info("Completed TimeDoctor worklog sync job", [
                'start_date'   => $startDate->format('Y-m-d'),
                'end_date'     => $endDate->format('Y-m-d'),
                'total_synced' => $totalSynced,
                'total_days'   => $totalDays,
            ]);

        } catch (\Exception $e) {
            Log::error('TimeDoctor worklog sync job failed', [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date'   => $endDate->format('Y-m-d'),
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
                'job_id'     => $this->job->getJobId(),
            ]);

            ActivityLogService::log('sync_timedoctor_data', 'TimeDoctor worklog sync job failed', [
                'module'     => 'timedoctor_integration',
                'start_date' => $startDate->format('Y-m-d'),
                'end_date'   => $endDate->format('Y-m-d'),
                'error'      => $e->getMessage(),
                'job_id'     => $this->job->getJobId(),
            ]);

            throw $e;
        }
    }

    private function processUsersWorklogsForDay(
        TimeDoctorService $timeDoctorService,
        $companyId,
        $users,
        Carbon $date
    ): array {
        $totalUsers     = $users->count();
        $processedUsers = 0;
        $totalInserted  = 0;
        $totalUpdated   = 0;
        $totalErrors    = 0;
        $totalProcessed = 0;

        foreach ($users as $user) {
            if (! $user->timedoctorUser) {
                $processedUsers++;
                continue;
            }

            $userName = $user->timedoctorUser->tm_fullname;

            try {
                Log::debug("Fetching worklogs for user: {$userName} on {$date->format('Y-m-d')}");

                $offset       = 1;
                $hasMoreData  = true;
                $batchNumber  = 1;
                $userInserted = 0;
                $userUpdated  = 0;

                while ($hasMoreData) {
                    $worklogData = $timeDoctorService->getUserWorklogs(
                        $companyId,
                        $user->timedoctorUser->timedoctor_id,
                        $date->copy()->startOfDay(),
                        $date->copy()->endOfDay(),
                        $offset,
                        self::PAGINATION_LIMIT
                    );

                    if (! is_array($worklogData)) {
                        Log::warning("Invalid response from TimeDoctor API for user: {$userName}");
                        $totalErrors++;
                        break;
                    }

                    if (! isset($worklogData['worklogs']) || empty($worklogData['worklogs'])) {
                        Log::debug("No worklogs found for user: {$userName} on {$date->format('Y-m-d')}");
                        break;
                    }

                    if (isset($worklogData['worklogs']['items'])) {
                        $worklogItems = $worklogData['worklogs']['items'];
                    } else if (is_array($worklogData['worklogs'])) {
                        $worklogItems = $worklogData['worklogs'];
                    } else {
                        Log::warning("Unexpected worklog response format for user: {$userName}");
                        $totalErrors++;
                        break;
                    }

                    $worklogCount = count($worklogItems);
                    $totalProcessed += $worklogCount;

                    if ($worklogCount === 0) {
                        break;
                    }

                    Log::debug("Processing batch #{$batchNumber} for {$userName}: {$worklogCount} worklog records");

                    $result = $this->processWorklogBatch($worklogItems, $user);

                    if (is_array($result)) {
                        $userInserted += $result['inserted'];
                        $userUpdated += $result['updated'];
                        $totalErrors += $result['errors'];
                    }

                    $hasMoreData = $worklogCount >= self::PAGINATION_LIMIT;
                    $offset += self::PAGINATION_LIMIT;
                    $batchNumber++;

                                    // Small delay to avoid overloading the API
                    usleep(200000); // 200ms
                }

                $totalInserted += $userInserted;
                $totalUpdated += $userUpdated;

                $processedUsers++;
                Log::debug("Completed processing worklogs for user: {$userName} (Added: {$userInserted}, Updated: {$userUpdated})");

            } catch (\Exception $e) {
                Log::error("Error processing worklogs for user {$userName}", [
                    'user_id' => $user->id,
                    'date'    => $date->format('Y-m-d'),
                    'error'   => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(),
                ]);

                $totalErrors++;
                $processedUsers++;
            }
        }

        Log::info("Completed worklog sync for date {$date->format('Y-m-d')}", [
            'total_inserted'  => $totalInserted,
            'total_updated'   => $totalUpdated,
            'total_errors'    => $totalErrors,
            'total_processed' => $totalProcessed,
        ]);

        return [
            'inserted'        => $totalInserted,
            'updated'         => $totalUpdated,
            'errors'          => $totalErrors,
            'total_processed' => $totalProcessed,
        ];
    }

    private function processWorklogBatch(array $worklogItems, $user): array
    {
        if (empty($worklogItems)) {
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
                Log::debug("Inserted {$insertedCount} worklog records");
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

            return ['inserted' => 0, 'updated' => 0, 'errors' => 1];
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('TimeDoctor worklog sync job failed permanently', [
            'start_date' => $this->startDate,
            'end_date'   => $this->endDate,
            'error'      => $exception->getMessage(),
            'trace'      => $exception->getTraceAsString(),
            'job_id'     => $this->job?->getJobId(),
        ]);

        ActivityLogService::log('sync_timedoctor_data', 'TimeDoctor worklog sync job failed permanently', [
            'module'     => 'timedoctor_integration',
            'start_date' => $this->startDate,
            'end_date'   => $this->endDate,
            'error'      => $exception->getMessage(),
            'job_id'     => $this->job?->getJobId(),
        ]);
    }
}
