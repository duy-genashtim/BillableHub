<?php
namespace App\Http\Controllers;

use App\Models\IvaUser;
use App\Models\Project;
use App\Models\Task;
use App\Models\WorklogsData;
use App\Services\ActivityLogService;
use App\Services\TimeDoctor\TimeDoctorService;
use App\Services\TimeDoctor\TimeDoctorV2Service;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class IvaUserTimeDoctorRecordsController extends Controller
{
    protected $timeDoctorService;
    protected $timeDoctorV2Service;
    const BATCH_SIZE = 100;

    public function __construct(TimeDoctorService $timeDoctorService, TimeDoctorV2Service $timeDoctorV2Service)
    {
        $this->timeDoctorService   = $timeDoctorService;
        $this->timeDoctorV2Service = $timeDoctorV2Service;
    }

    /**
     * Display a listing of Time Doctor records for a specific IVA user.
     */
    public function index(Request $request, $id)
    {
        $user = IvaUser::findOrFail($id);

        $query = WorklogsData::with(['project', 'task'])
            ->where('iva_id', $id)
            ->orderBy('start_time', 'desc');

        // Apply date filters
        if ($request->has('start_date') && ! empty($request->start_date)) {
            $query->whereDate('start_time', '>=', $request->start_date);
        }

        if ($request->has('end_date') && ! empty($request->end_date)) {
            $query->whereDate('start_time', '<=', $request->end_date);
        }

        // Apply project filter
        if ($request->has('project_id') && ! empty($request->project_id)) {
            $query->where('project_id', $request->project_id);
        }

        // Apply task filter
        if ($request->has('task_id') && ! empty($request->task_id)) {
            $query->where('task_id', $request->task_id);
        }

        // Apply status filter
        if ($request->has('is_active') && $request->is_active !== null) {
            $query->where('is_active', $request->is_active === 'true' || $request->is_active === '1');
        }

        // Apply API type filter
        if ($request->has('api_type') && ! empty($request->api_type)) {
            $query->where('api_type', $request->api_type);
        }

        $worklogs = $query->get();

        // Transform duration to hours for frontend
        $worklogs->transform(function ($worklog) {
            $worklog->duration_hours = $worklog->duration / 3600; // Convert seconds to hours
            return $worklog;
        });

        // Create pagination-like response
        $total = $worklogs->count();

        // Log the activity
        // ActivityLogService::log(
        //     'view_timedoctor_records',
        //     'Viewed Time Doctor records for user: ' . $user->full_name,
        //     [
        //         'user_id'       => $id,
        //         'total_records' => $total,
        //         'filters'       => $request->only(['start_date', 'end_date', 'project_id', 'task_id', 'is_active', 'api_type']),
        //     ]
        // );

        return response()->json([
            'success'  => true,
            'worklogs' => [
                'data'         => $worklogs,
                'total'        => $total,
                'current_page' => 1,
                'per_page'     => $total,
            ],
            'user'     => $user,
        ]);
    }

    /**
     * Sync Time Doctor records for a specific user.
     */
    public function syncTimeDoctorRecords(Request $request, $id)
    {
        $user = IvaUser::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate   = Carbon::parse($request->end_date)->endOfDay();

        // Check date range limit (31 days)
        if ($startDate->diffInDays($endDate) > 31) {
            return response()->json([
                'success' => false,
                'message' => 'Date range cannot exceed 31 days',
            ], 422);
        }

        try {
            // Check TimeDoctorVersion and route accordingly
            if ($user->timedoctor_version == 2) {
                return $this->syncTimeDoctorV2Records($user, $startDate, $endDate);
            } else {
                return $this->syncTimeDoctorV1Records($user, $startDate, $endDate);
            }

        } catch (\Exception $e) {
            Log::error('Error syncing Time Doctor records for user', [
                'user_id'            => $id,
                'user_name'          => $user->full_name,
                'timedoctor_version' => $user->timedoctor_version,
                'error'              => $e->getMessage(),
                'trace'              => $e->getTraceAsString(),
            ]);

            // Log the failed sync activity
            ActivityLogService::log(
                'sync_timedoctor_records',
                'Failed to sync Time Doctor records for user: ' . $user->full_name,
                [
                    'user_id'            => $id,
                    'start_date'         => $request->start_date,
                    'end_date'           => $request->end_date,
                    'timedoctor_version' => $user->timedoctor_version,
                    'error'              => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync Time Doctor records: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all projects for dropdown.
     */
    public function getProjects()
    {
        $projects = Project::where('is_active', true)
            ->orderBy('project_name')
            ->get(['id', 'project_name', 'timedoctor_id']);

        return response()->json([
            'success'  => true,
            'projects' => $projects,
        ]);
    }

    /**
     * Get all tasks for dropdown.
     */
    public function getTasks()
    {
        $tasks = Task::where('is_active', true)
            ->orderBy('task_name')
            ->get(['id', 'task_name', 'user_list']);

        return response()->json([
            'success' => true,
            'tasks'   => $tasks,
        ]);
    }

    private function syncTimeDoctorV1Records($user, $startDate, $endDate)
    {
        // Check if user has TimeDoctor V1 integration
        if (! $user->timedoctorUser) {
            return response()->json([
                'success' => false,
                'message' => 'User is not connected to TimeDoctor v1',
            ], 422);
        }

        // Get company ID
        $companyId = $this->timeDoctorService->getCompanyId();
        if (! $companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Could not retrieve TimeDoctor company ID',
            ], 500);
        }

        $syncedCount = 0;
        $errorCount  = 0;

        DB::beginTransaction();

        try {
            // Remove existing TimeDoctor records for the date range
            $deletedCount = WorklogsData::where('iva_id', $user->id)
                ->where('api_type', 'timedoctor')
                ->where('timedoctor_version', 1)
                ->whereBetween('start_time', [$startDate, $endDate])
                ->delete();

            Log::info("Removed {$deletedCount} existing V1 records for user {$user->full_name} in date range");

            $currentDate = $startDate->copy();

            while ($currentDate->lte($endDate)) {
                $dayStart = $currentDate->copy()->startOfDay();
                $dayEnd   = $currentDate->copy()->endOfDay();

                Log::info("Syncing V1 worklogs for user {$user->full_name} on {$currentDate->format('Y-m-d')}");

                // Fetch worklogs from TimeDoctor V1 for this day
                $offset      = 1;
                $limit       = 250;
                $hasMoreData = true;

                while ($hasMoreData) {
                    $worklogData = $this->timeDoctorService->getUserWorklogs(
                        $companyId,
                        $user->timedoctorUser->timedoctor_id,
                        $dayStart,
                        $dayEnd,
                        $offset,
                        $limit
                    );

                    if (! isset($worklogData['worklogs']) || empty($worklogData['worklogs'])) {
                        break;
                    }

                    $worklogItems = isset($worklogData['worklogs']['items'])
                    ? $worklogData['worklogs']['items']
                    : $worklogData['worklogs'];

                    if (empty($worklogItems)) {
                        break;
                    }

                    // Process each worklog item
                    foreach ($worklogItems as $worklog) {
                        try {
                            if (! isset($worklog['id']) || ! isset($worklog['start_time']) || ! isset($worklog['end_time'])) {
                                $errorCount++;
                                continue;
                            }

                            $worklogStartTime = Carbon::parse($worklog['start_time']);
                            $worklogEndTime   = Carbon::parse($worklog['end_time']);
                            $duration         = isset($worklog['length']) ? (int) $worklog['length'] : $worklogEndTime->diffInSeconds($worklogStartTime);

                            // Find project mapping
                            $projectId = null;
                            if (isset($worklog['project_id'])) {
                                $project = Project::where('timedoctor_id', $worklog['project_id'])
                                    ->where('timedoctor_version', 1)
                                    ->first();
                                if ($project) {
                                    $projectId = $project->id;
                                }
                            }

                            // Find task mapping
                            $taskId = null;
                            if (isset($worklog['task_id'])) {
                                $task = Task::where('user_list', 'like', '%"tId":"' . $worklog['task_id'] . '"%')
                                    ->orWhere('user_list', 'like', '%"tId":' . $worklog['task_id'] . '%')
                                    ->first();

                                if ($task) {
                                    $taskId = $task->id;
                                }
                            }

                            // Determine comment based on edited status
                            $comment = null;
                            if (isset($worklog['edited']) && $worklog['edited'] == '1') {
                                $comment = 'Manually Added/Edited time';
                            }

                            // Create new worklog (we already deleted existing ones)
                            WorklogsData::create([
                                'iva_id'                => $user->id,
                                'timedoctor_project_id' => $worklog['project_id'] ?? null,
                                'timedoctor_task_id'    => $worklog['task_id'] ?? null,
                                'project_id'            => $projectId,
                                'task_id'               => $taskId,
                                'work_mode'             => $worklog['work_mode'] ?? '0',
                                'start_time'            => $worklogStartTime,
                                'end_time'              => $worklogEndTime,
                                'duration'              => $duration,
                                'device_id'             => null,
                                'comment'               => $comment,
                                'api_type'              => 'timedoctor',
                                'timedoctor_worklog_id' => $worklog['id'],
                                'timedoctor_version'    => 1,
                                'tm_user_id'            => $worklog['user_id'] ?? null,
                                'is_active'             => true,
                            ]);
                            $syncedCount++;
                        } catch (\Exception $e) {
                            Log::error("Error processing V1 worklog item for user {$user->full_name}", [
                                'worklog' => $worklog,
                                'error'   => $e->getMessage(),
                            ]);
                            $errorCount++;
                        }
                    }

                    $hasMoreData = count($worklogItems) >= $limit;
                    $offset += $limit;

                                    // Add a small delay to avoid rate limiting
                    usleep(200000); // 200ms
                }

                $currentDate->addDay();
            }

            DB::commit();

            // Log the sync activity
            ActivityLogService::log(
                'sync_timedoctor_records',
                'Synced TimeDoctor V1 records for user: ' . $user->full_name,
                [
                    'user_id'            => $user->id,
                    'start_date'         => $startDate->format('Y-m-d'),
                    'end_date'           => $endDate->format('Y-m-d'),
                    'timedoctor_version' => 1,
                    'synced_count'       => $syncedCount,
                    'deleted_count'      => $deletedCount,
                    'error_count'        => $errorCount,
                    'total_processed'    => $syncedCount,
                ]
            );

            return response()->json([
                'success'       => true,
                'message'       => 'TimeDoctor V1 records sync completed successfully',
                'synced_count'  => $syncedCount,
                'deleted_count' => $deletedCount,
                'error_count'   => $errorCount,
                'total_records' => $syncedCount,
                'date_range'    => [
                    'start' => $startDate->format('Y-m-d'),
                    'end'   => $endDate->format('Y-m-d'),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

// 4. Add new method for V2 sync
    private function syncTimeDoctorV2Records($user, $startDate, $endDate)
    {
        // Check if user has TimeDoctor V2 integration
        if (! $user->timedoctorV2User) {
            return response()->json([
                'success' => false,
                'message' => 'User is not connected to TimeDoctor V2',
            ], 422);
        }

        $syncedCount = 0;
        $errorCount  = 0;

        DB::beginTransaction();

        try {
            // Remove existing TimeDoctor records for the date range
            $deletedCount = WorklogsData::where('iva_id', $user->id)
                ->where('api_type', 'timedoctor')
                ->where('timedoctor_version', 2)
                ->whereBetween('start_time', [$startDate, $endDate])
                ->delete();

            Log::info("Removed {$deletedCount} existing V2 records for user {$user->full_name} in date range");

            $currentDate = $startDate->copy();

            while ($currentDate->lte($endDate)) {
                Log::info("Syncing V2 worklogs for user {$user->full_name} on {$currentDate->format('Y-m-d')}");

                // Use the helper functions for proper timezone conversion
                $localStartOfDay = Carbon::createFromFormat('Y-m-d H:i:s', $currentDate->format('Y-m-d') . ' 00:00:00', config('app.timezone-timedoctor', 'Asia/Singapore'));
                $localEndOfDay   = Carbon::createFromFormat('Y-m-d H:i:s', $currentDate->format('Y-m-d') . ' 23:59:59', config('app.timezone-timedoctor', 'Asia/Singapore'));

                $worklogData = $this->timeDoctorV2Service->getUserWorklogs(
                    $user->timedoctorV2User->timedoctor_id,
                    $localStartOfDay,
                    $localEndOfDay
                );

                if (! is_array($worklogData) || ! isset($worklogData['data']) || empty($worklogData['data'])) {
                    $currentDate->addDay();
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
                    $currentDate->addDay();
                    continue;
                }

                // Process each worklog item
                foreach ($worklogItems as $worklog) {
                    try {
                        if (! isset($worklog['start']) || ! isset($worklog['time'])) {
                            $errorCount++;
                            continue;
                        }

                        // Convert from UTC to local timezone for proper storage
                        $startTimeUtc = Carbon::parse($worklog['start'], 'UTC');
                        $startTime    = convertFromTimeDoctorTimezone($startTimeUtc, config('app.timezone-timedoctor', 'Asia/Singapore'));
                        $endTime      = $startTime->copy()->addSeconds($worklog['time']);

                        // Duration is already in seconds from V2 API
                        $duration = (int) $worklog['time'];

                        // Find project mapping
                        $projectId = null;
                        if (isset($worklog['projectId'])) {
                            $project = Project::where('timedoctor_id', $worklog['projectId'])
                                ->where('timedoctor_version', 2)
                                ->first();
                            if ($project) {
                                $projectId = $project->id;
                            }
                        }

                        // Find task mapping
                        $taskId = null;
                        if (isset($worklog['taskId'])) {
                            $task = Task::whereJsonContains('user_list', ['tId' => $worklog['taskId'], 'vId' => 2])
                                ->first();

                            if ($task) {
                                $taskId = $task->id;
                            }
                        }

                        // Create unique identifier for V2 worklogs
                        $worklogId = $worklog['userId'] . '_' . $worklog['start'] . '_' . $worklog['time'];

                        // Determine comment based on edited status
                        $comment = null;
                        if (isset($worklog['edited']) && $worklog['edited'] == '1') {
                            $comment = 'Manually Added/Edited time';
                        }

                        // Create new worklog (we already deleted existing ones)
                        WorklogsData::create([
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
                            'comment'               => $comment,
                            'api_type'              => 'timedoctor',
                            'timedoctor_worklog_id' => $worklogId,
                            'timedoctor_version'    => 2,
                            'tm_user_id'            => $worklog['userId'] ?? null,
                            'is_active'             => true,
                        ]);
                        $syncedCount++;
                    } catch (\Exception $e) {
                        Log::error("Error processing V2 worklog item for user {$user->full_name}", [
                            'worklog' => $worklog,
                            'error'   => $e->getMessage(),
                        ]);
                        $errorCount++;
                    }
                }

                $currentDate->addDay();
            }

            DB::commit();

            // Log the sync activity
            ActivityLogService::log(
                'sync_timedoctor_records',
                'Synced TimeDoctor V2 records for user: ' . $user->full_name,
                [
                    'user_id'            => $user->id,
                    'start_date'         => $startDate->format('Y-m-d'),
                    'end_date'           => $endDate->format('Y-m-d'),
                    'timedoctor_version' => 2,
                    'synced_count'       => $syncedCount,
                    'deleted_count'      => $deletedCount,
                    'error_count'        => $errorCount,
                    'total_processed'    => $syncedCount,
                ]
            );

            return response()->json([
                'success'       => true,
                'message'       => 'TimeDoctor V2 records sync completed successfully',
                'synced_count'  => $syncedCount,
                'deleted_count' => $deletedCount,
                'error_count'   => $errorCount,
                'total_records' => $syncedCount,
                'date_range'    => [
                    'start' => $startDate->format('Y-m-d'),
                    'end'   => $endDate->format('Y-m-d'),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}