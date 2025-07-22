<?php
namespace App\Http\Controllers;

use App\Jobs\SyncTimeDoctorV2Worklogs;
use App\Models\IvaUser;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimedoctorV2User;
use App\Models\WorklogsData;
use App\Services\ActivityLogService;
use App\Services\TimeDoctor\TimeDoctorV2Service;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TimeDoctorV2Controller extends Controller
{
    protected $timeDoctorV2Service;

    public function __construct(TimeDoctorV2Service $timeDoctorV2Service)
    {
        $this->timeDoctorV2Service = $timeDoctorV2Service;
        set_time_limit(300);
        ini_set('memory_limit', '512M');
    }

    public function syncUsers(): JsonResponse
    {
        try {
            $users = $this->timeDoctorV2Service->getAllUsersWithPagination();

            if (empty($users)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No users received from TimeDoctor V2',
                ], 400);
            }

            $syncCount = 0;

            DB::beginTransaction();

            try {
                foreach ($users as $user) {
                    $timeDoctorUser = TimedoctorV2User::updateOrCreate(
                        ['timedoctor_id' => $user['id']],
                        [
                            'tm_fullname'      => $user['full_name'] ?? $user['name'] ?? '',
                            'tm_email'         => $user['email'] ?? '',
                            'timezone'         => $user['timezone'] ?? null,
                            'profile_timezone' => $user['profile_timezone'] ?? null,
                            'role'             => $user['role'] ?? 'user',
                            'only_project_ids' => $user['only_project_ids'] ?? null,
                            'manager_ids'      => $user['manager_ids'] ?? null,
                            'tag_ids'          => $user['tag_ids'] ?? null,
                            'silent_info'      => $user['silent_info'] ?? null,
                            'is_active'        => $user['active'] ?? true,
                            'last_synced_at'   => now(),
                        ]
                    );

                    // Try to link with existing IVA user
                    if (! empty($user['email'])) {
                        $ivaUser = IvaUser::where('email', $user['email'])->first();
                        if ($ivaUser) {
                            $timeDoctorUser->iva_user_id = $ivaUser->id;
                            $timeDoctorUser->save();

                            // Update IVA user to use V2
                            // Duy - remove auto update to version 2
                            // $ivaUser->update(['timedoctor_version' => 2]);
                        }
                    }

                    $syncCount++;
                }

                DB::commit();

                ActivityLogService::log('sync_timedoctor_v2_data', 'TimeDoctor V2 users synced successfully', [
                    'module'       => 'timedoctor_v2_integration',
                    'synced_count' => $syncCount,
                    'total_users'  => count($users),
                    'version'      => 2,
                ]);

                return response()->json([
                    'success'      => true,
                    'message'      => 'Users synced successfully',
                    'synced_count' => $syncCount,
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error syncing TimeDoctor V2 users', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            ActivityLogService::log('sync_timedoctor_v2_data', 'Failed to sync TimeDoctor V2 users', [
                'module'  => 'timedoctor_v2_integration',
                'error'   => $e->getMessage(),
                'version' => 2,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error syncing users: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function syncProjects(): JsonResponse
    {
        try {
            $projects = $this->timeDoctorV2Service->getAllProjectsWithPagination();

            if (empty($projects)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No projects received from TimeDoctor V2',
                ], 400);
            }

            $syncCount = 0;

            DB::beginTransaction();

            try {
                foreach ($projects as $project) {
                    Project::updateOrCreate(
                        [
                            'timedoctor_id'      => $project['id'],
                            'timedoctor_version' => 2,
                        ],
                        [
                            'project_name'   => $project['name'] ?? '',
                            'is_active'      => $project['active'] ?? true,
                            'description'    => $project['description'] ?? null,
                            'last_synced_at' => now(),
                        ]
                    );

                    $syncCount++;
                }

                DB::commit();

                ActivityLogService::log('sync_timedoctor_v2_data', 'TimeDoctor V2 projects synced successfully', [
                    'module'         => 'timedoctor_v2_integration',
                    'synced_count'   => $syncCount,
                    'total_projects' => count($projects),
                    'version'        => 2,
                ]);

                return response()->json([
                    'success'      => true,
                    'message'      => 'Projects synced successfully',
                    'synced_count' => $syncCount,
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error syncing TimeDoctor V2 projects', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            ActivityLogService::log('sync_timedoctor_v2_data', 'Failed to sync TimeDoctor V2 projects', [
                'module'  => 'timedoctor_v2_integration',
                'error'   => $e->getMessage(),
                'version' => 2,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error syncing projects: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function syncTasks(): JsonResponse
    {
        try {
            $tasks = $this->timeDoctorV2Service->getAllTasksWithPagination();

            if (empty($tasks)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tasks received from TimeDoctor V2',
                ], 400);
            }

            $syncCount = 0;

            DB::beginTransaction();

            try {
                foreach ($tasks as $task) {
                    $userListData = [
                        'tId' => $task['id'] ?? null,
                        'vId' => 2,
                    ];

                    $existingTask = Task::where('task_name', $task['name'])->first();

                    if ($existingTask) {
                        $existingUserList = $existingTask->user_list ?? [];
                        $userExists       = false;

                        if (is_array($existingUserList)) {
                            foreach ($existingUserList as $key => $userData) {
                                if (isset($userData['tId']) && $userData['tId'] == ($task['id'] ?? null) &&
                                    isset($userData['vId']) && $userData['vId'] == 2) {
                                    $existingUserList[$key] = $userListData;
                                    $userExists             = true;
                                    break;
                                }
                            }

                            if (! $userExists) {
                                $existingUserList[] = $userListData;
                            }
                        } else {
                            $existingUserList = [$userListData];
                        }

                        $existingTask->update([
                            'is_active'      => ! ($task['deleted'] ?? false),
                            'last_synced_at' => now(),
                            'user_list'      => $existingUserList,
                        ]);
                    } else {
                        Task::create([
                            'task_name'      => $task['name'],
                            'slug'           => \Str::slug($task['name']),
                            'user_list'      => [$userListData],
                            'is_active'      => ! ($task['deleted'] ?? false),
                            'last_synced_at' => now(),
                        ]);
                    }

                    $syncCount++;
                }

                DB::commit();

                ActivityLogService::log('sync_timedoctor_v2_data', 'TimeDoctor V2 tasks synced successfully', [
                    'module'       => 'timedoctor_v2_integration',
                    'synced_count' => $syncCount,
                    'total_tasks'  => count($tasks),
                    'version'      => 2,
                ]);

                return response()->json([
                    'success'      => true,
                    'message'      => 'Tasks synced successfully',
                    'synced_count' => $syncCount,
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error syncing TimeDoctor V2 tasks', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            ActivityLogService::log('sync_timedoctor_v2_data', 'Failed to sync TimeDoctor V2 tasks', [
                'module'  => 'timedoctor_v2_integration',
                'error'   => $e->getMessage(),
                'version' => 2,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error syncing tasks: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function syncWorklogs(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date'   => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'queue_job'  => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $startDate = Carbon::parse($request->input('start_date'));
        $endDate   = Carbon::parse($request->input('end_date'));
        $queueJob  = $request->input('queue_job', true);

        if ($startDate->diffInDays($endDate) > 30) {
            return response()->json([
                'success' => false,
                'message' => 'Date range cannot exceed 31 days',
            ], 400);
        }

        if ($queueJob) {
            SyncTimeDoctorV2Worklogs::dispatch($startDate->toDateString(), $endDate->toDateString());

            ActivityLogService::log('sync_timedoctor_v2_data', 'TimeDoctor V2 worklog sync job queued', [
                'module'     => 'timedoctor_v2_integration',
                'start_date' => $startDate->toDateString(),
                'end_date'   => $endDate->toDateString(),
                'version'    => 2,
            ]);

            return response()->json([
                'success'    => true,
                'message'    => 'Worklog sync job has been queued',
                'start_date' => $startDate->toDateString(),
                'end_date'   => $endDate->toDateString(),
            ]);
        } else {
            return response()->json([
                'success'    => true,
                'message'    => 'For immediate sync, please use the streaming endpoint',
                'stream_url' => url('/api/timedoctor-v2/stream-worklog-sync?start_date=' . $startDate->toDateString() . '&end_date=' . $endDate->toDateString()),
            ]);
        }
    }

    public function getUserCount(): JsonResponse
    {
        $count = TimedoctorV2User::count();

        return response()->json([
            'success' => true,
            'count'   => $count,
        ]);
    }

    public function getProjectCount(): JsonResponse
    {
        $count = Project::where('timedoctor_version', 2)->count();

        return response()->json([
            'success' => true,
            'count'   => $count,
        ]);
    }

    public function getTaskCount(): JsonResponse
    {
        $count = Task::whereJsonContains('user_list', ['vId' => 2])->count();

        return response()->json([
            'success' => true,
            'count'   => $count,
        ]);
    }

    public function getWorklogCount(): JsonResponse
    {
        $count = WorklogsData::where('api_type', 'timedoctor')
            ->where('timedoctor_version', 2)
            ->count();

        return response()->json([
            'success' => true,
            'count'   => $count,
        ]);
    }
}
