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
        ActivityLogService::log(
            'view_timedoctor_records',
            'Viewed Time Doctor records for user: ' . $user->full_name,
            [
                'user_id'       => $id,
                'total_records' => $total,
                'filters'       => $request->only(['start_date', 'end_date', 'project_id', 'task_id', 'is_active', 'api_type']),
            ]
        );

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
     * Store a newly created Time Doctor record.
     */
    public function store(Request $request, $id)
    {
        $user = IvaUser::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'start_time' => 'required|date',
            'end_time'   => 'required|date|after:start_time',
            'project_id' => 'required|exists:projects,id',
            'task_id'    => 'required|exists:tasks,id',
            'comment'    => 'nullable|string|max:1000',
            'work_mode'  => 'required|string|in:manual,automatic',
            'api_type'   => 'required|string|in:manual,timedoctor',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check for overlapping time entries
        $startTime = $request->start_time;
        $endTime   = $request->end_time;

        $overlap = WorklogsData::where('iva_id', $id)
            ->where('is_active', true)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereBetween('start_time', [$startTime, $endTime])
                    ->orWhereBetween('end_time', [$startTime, $endTime])
                    ->orWhere(function ($q) use ($startTime, $endTime) {
                        $q->where('start_time', '<=', $startTime)
                            ->where('end_time', '>=', $endTime);
                    });
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'This time entry overlaps with an existing active record.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Calculate duration in seconds
            $duration = strtotime($endTime) - strtotime($startTime);

            $worklog = WorklogsData::create([
                'iva_id'                => $id,
                'project_id'            => $request->project_id,
                'task_id'               => $request->task_id,
                'work_mode'             => $request->work_mode,
                'start_time'            => $startTime,
                'end_time'              => $endTime,
                'duration'              => $duration,
                'comment'               => $request->comment,
                'api_type'              => $request->api_type,
                'is_active'             => true,
                'timedoctor_version'    => $user->timedoctor_version,
                'tm_user_id'            => $user->email, // Using email as TM user ID for manual entries
                'timedoctor_project_id' => null,         // Set to null to avoid data truncation
                'timedoctor_task_id'    => null,         // Set to null to avoid data truncation
            ]);

            // Log the activity
            ActivityLogService::log(
                'create_timedoctor_record',
                'Created Time Doctor record for user: ' . $user->full_name,
                array_merge($worklog->toArray(), [
                    'project_name' => $request->project_id ? Project::find($request->project_id)?->project_name : null,
                    'task_name'    => $request->task_id ? Task::find($request->task_id)?->task_name : null,
                ])
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Time Doctor record created successfully',
                'worklog' => $worklog->load(['project', 'task']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create Time Doctor record',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified Time Doctor record.
     */
    public function update(Request $request, $id, $worklogId)
    {
        $user    = IvaUser::findOrFail($id);
        $worklog = WorklogsData::where('iva_id', $id)->findOrFail($worklogId);

        $validator = Validator::make($request->all(), [
            'start_time' => 'required|date',
            'end_time'   => 'required|date|after:start_time',
            'project_id' => 'required|exists:projects,id',
            'task_id'    => 'required|exists:tasks,id',
            'comment'    => 'nullable|string|max:1000',
            'is_active'  => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check for overlapping time entries (excluding current record)
        $startTime = $request->start_time;
        $endTime   = $request->end_time;

        $overlap = WorklogsData::where('iva_id', $id)
            ->where('id', '!=', $worklogId)
            ->where('is_active', true)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereBetween('start_time', [$startTime, $endTime])
                    ->orWhereBetween('end_time', [$startTime, $endTime])
                    ->orWhere(function ($q) use ($startTime, $endTime) {
                        $q->where('start_time', '<=', $startTime)
                            ->where('end_time', '>=', $endTime);
                    });
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'This time entry overlaps with an existing active record.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $oldValues = $worklog->toArray();

            // Calculate new duration in seconds
            $duration = strtotime($endTime) - strtotime($startTime);

            $worklog->update([
                'project_id'            => $request->project_id,
                'task_id'               => $request->task_id,
                'start_time'            => $startTime,
                'end_time'              => $endTime,
                'duration'              => $duration,
                'comment'               => $request->comment,
                'is_active'             => $request->is_active ?? $worklog->is_active,
                'update_comment'        => 'Updated via web interface',
                'timedoctor_project_id' => null, // Set to null to avoid data truncation
                'timedoctor_task_id'    => null, // Set to null to avoid data truncation
            ]);

            // Log the activity
            ActivityLogService::log(
                'update_timedoctor_record',
                'Updated Time Doctor record for user: ' . $user->full_name,
                [
                    'worklog_id'   => $worklogId,
                    'old_values'   => $oldValues,
                    'new_values'   => $worklog->fresh()->toArray(),
                    'project_name' => $request->project_id ? Project::find($request->project_id)?->project_name : null,
                    'task_name'    => $request->task_id ? Task::find($request->task_id)?->task_name : null,
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Time Doctor record updated successfully',
                'worklog' => $worklog->fresh(['project', 'task']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update Time Doctor record',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified Time Doctor record.
     */
    public function destroy(Request $request, $id, $worklogId)
    {
        $user    = IvaUser::findOrFail($id);
        $worklog = WorklogsData::where('iva_id', $id)->findOrFail($worklogId);

        DB::beginTransaction();

        try {
            // Log the activity before deletion
            ActivityLogService::log(
                'delete_timedoctor_record',
                'Deleted Time Doctor record for user: ' . $user->full_name,
                array_merge($worklog->toArray(), [
                    'project_name' => $worklog->project?->project_name,
                    'task_name'    => $worklog->task?->task_name,
                ])
            );

            $worklog->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Time Doctor record deleted successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete Time Doctor record',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle the status of a Time Doctor record.
     */
    public function toggleStatus(Request $request, $id, $worklogId)
    {
        $user    = IvaUser::findOrFail($id);
        $worklog = WorklogsData::where('iva_id', $id)->findOrFail($worklogId);

        DB::beginTransaction();

        try {
            $oldStatus          = $worklog->is_active;
            $worklog->is_active = ! $worklog->is_active;
            $worklog->save();

            // Log the activity
            ActivityLogService::log(
                'toggle_timedoctor_record_status',
                ($oldStatus ? 'Deactivated' : 'Activated') . ' Time Doctor record for user: ' . $user->full_name,
                [
                    'worklog_id'     => $worklogId,
                    'old_status'     => $oldStatus,
                    'new_status'     => $worklog->is_active,
                    'project_name'   => $worklog->project?->project_name,
                    'task_name'      => $worklog->task?->task_name,
                    'start_time'     => $worklog->start_time,
                    'end_time'       => $worklog->end_time,
                    'duration_hours' => round($worklog->duration / 3600, 2),
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Time Doctor record status updated successfully',
                'worklog' => $worklog->fresh(['project', 'task']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update Time Doctor record status',
                'error'   => $e->getMessage(),
            ], 500);
        }
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

        $syncedCount  = 0;
        $updatedCount = 0;
        $errorCount   = 0;

        DB::beginTransaction();

        try {
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

                            // Check if worklog already exists
                            $existingWorklog = WorklogsData::where('timedoctor_worklog_id', $worklog['id'])
                                ->where('api_type', 'timedoctor')
                                ->where('timedoctor_version', 1)
                                ->first();

                            if ($existingWorklog) {
                                // Update existing worklog
                                $existingWorklog->update([
                                    'timedoctor_project_id' => $worklog['project_id'] ?? null,
                                    'timedoctor_task_id'    => $worklog['task_id'] ?? null,
                                    'project_id'            => $projectId,
                                    'task_id'               => $taskId,
                                    'work_mode'             => $worklog['work_mode'] ?? '0',
                                    'end_time'              => $worklogEndTime,
                                    'duration'              => $duration,
                                    'is_active'             => true,
                                ]);
                                $updatedCount++;
                            } else {
                                // Create new worklog
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
                                    'comment'               => null,
                                    'api_type'              => 'timedoctor',
                                    'timedoctor_worklog_id' => $worklog['id'],
                                    'timedoctor_version'    => 1,
                                    'tm_user_id'            => $worklog['user_id'] ?? null,
                                    'is_active'             => true,
                                ]);
                                $syncedCount++;
                            }
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
                    'updated_count'      => $updatedCount,
                    'error_count'        => $errorCount,
                    'total_processed'    => $syncedCount + $updatedCount,
                ]
            );

            return response()->json([
                'success'       => true,
                'message'       => 'TimeDoctor V1 records sync completed successfully',
                'synced_count'  => $syncedCount,
                'updated_count' => $updatedCount,
                'error_count'   => $errorCount,
                'total_records' => $syncedCount + $updatedCount,
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

        $syncedCount  = 0;
        $updatedCount = 0;
        $errorCount   = 0;

        DB::beginTransaction();

        try {
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

                        // Check if worklog already exists
                        $existingWorklog = WorklogsData::where('timedoctor_worklog_id', $worklogId)
                            ->where('api_type', 'timedoctor')
                            ->where('timedoctor_version', 2)
                            ->first();

                        if ($existingWorklog) {
                            // Update existing worklog
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
                            // Create new worklog
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
                                'comment'               => null,
                                'api_type'              => 'timedoctor',
                                'timedoctor_worklog_id' => $worklogId,
                                'timedoctor_version'    => 2,
                                'tm_user_id'            => $worklog['userId'] ?? null,
                                'is_active'             => true,
                            ]);
                            $syncedCount++;
                        }
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
                    'updated_count'      => $updatedCount,
                    'error_count'        => $errorCount,
                    'total_processed'    => $syncedCount + $updatedCount,
                ]
            );

            return response()->json([
                'success'       => true,
                'message'       => 'TimeDoctor V2 records sync completed successfully',
                'synced_count'  => $syncedCount,
                'updated_count' => $updatedCount,
                'error_count'   => $errorCount,
                'total_records' => $syncedCount + $updatedCount,
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