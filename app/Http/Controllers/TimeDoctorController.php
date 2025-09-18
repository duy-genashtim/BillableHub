<?php

namespace App\Http\Controllers;

use App\Jobs\SyncTimeDoctorWorklogs;
use App\Models\IvaUser;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimedoctorV1User;
use App\Models\WorklogsData;
use App\Services\ActivityLogService;
use App\Services\TimeDoctor\TimeDoctorService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TimeDoctorController extends Controller
{
    protected $timeDoctorService;

    public function __construct(TimeDoctorService $timeDoctorService)
    {
        $this->timeDoctorService = $timeDoctorService;
        set_time_limit(300);
        ini_set('memory_limit', '512M');
    }

    public function syncUsers(): JsonResponse
    {
        try {
            $companyInfo = $this->timeDoctorService->getCompanyInfo();

            if (! isset($companyInfo['accounts'][0]['company_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not retrieve company ID from TimeDoctor',
                ], 400);
            }

            $companyId = $companyInfo['accounts'][0]['company_id'];
            $usersData = $this->timeDoctorService->getUsers($companyId);

            if (! isset($usersData['users']) || ! is_array($usersData['users'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid user data received from TimeDoctor',
                ], 400);
            }

            $syncCount = 0;

            DB::beginTransaction();

            try {
                foreach ($usersData['users'] as $user) {
                    $timeDoctorUser = TimedoctorV1User::updateOrCreate(
                        ['timedoctor_id' => $user['user_id']],
                        [
                            'tm_fullname' => $user['full_name'],
                            'tm_email' => $user['email'],
                            'is_active' => true,
                            'last_synced_at' => now(),
                        ]
                    );

                    if (! empty($user['email'])) {
                        $ivaUser = IvaUser::where('email', $user['email'])->first();
                        if ($ivaUser) {
                            $timeDoctorUser->iva_user_id = $ivaUser->id;
                            $timeDoctorUser->save();

                        }
                    }

                    $syncCount++;
                }

                DB::commit();

                ActivityLogService::log('sync_timedoctor_data', 'TimeDoctor users synced successfully', [
                    'module' => 'timedoctor_integration',
                    'synced_count' => $syncCount,
                    'total_users' => count($usersData['users']),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Users synced successfully',
                    'synced_count' => $syncCount,
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error syncing TimeDoctor users', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            ActivityLogService::log('sync_timedoctor_data', 'Failed to sync TimeDoctor users', [
                'module' => 'timedoctor_integration',
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error syncing users: '.$e->getMessage(),
            ], 500);
        }
    }

    public function syncProjects(): JsonResponse
    {
        try {
            $projectsData = $this->timeDoctorService->getProjects();

            if (! isset($projectsData['count']) || ! is_array($projectsData['count'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid project data received from TimeDoctor',
                ], 400);
            }

            $syncCount = 0;

            DB::beginTransaction();

            try {
                foreach ($projectsData['count'] as $project) {
                    Project::updateOrCreate(
                        ['timedoctor_id' => $project['id']],
                        [
                            'timedoctor_version' => 1,
                            'project_name' => $project['name'],
                            'is_active' => ! ($project['deleted'] ?? false),
                            'description' => null,
                            'last_synced_at' => now(),
                        ]
                    );

                    $syncCount++;
                }

                DB::commit();

                ActivityLogService::log('sync_timedoctor_data', 'TimeDoctor projects synced successfully', [
                    'module' => 'timedoctor_integration',
                    'synced_count' => $syncCount,
                    'total_projects' => count($projectsData['count']),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Projects synced successfully',
                    'synced_count' => $syncCount,
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error syncing TimeDoctor projects', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            ActivityLogService::log('sync_timedoctor_data', 'Failed to sync TimeDoctor projects', [
                'module' => 'timedoctor_integration',
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error syncing projects: '.$e->getMessage(),
            ], 500);
        }
    }

    public function syncTasks(): JsonResponse
    {
        try {
            $companyInfo = $this->timeDoctorService->getCompanyInfo();

            if (! isset($companyInfo['accounts'][0]['company_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not retrieve company ID from TimeDoctor',
                ], 400);
            }

            $companyId = $companyInfo['accounts'][0]['company_id'];
            $users = TimedoctorV1User::where('is_active', true)->whereNotNull('iva_user_id')->get();

            if ($users->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active TimeDoctor users found. Please sync users first.',
                ], 400);
            }

            $syncCount = 0;

            DB::beginTransaction();

            try {
                foreach ($users as $user) {
                    $tasksData = $this->timeDoctorService->getTasks($companyId, $user->timedoctor_id);

                    if (! isset($tasksData['tasks']) || ! is_array($tasksData['tasks'])) {
                        continue;
                    }

                    foreach ($tasksData['tasks'] as $task) {
                        $userListData = [
                            'tId' => $task['task_id'] ?? null,
                            'vId' => 1,
                        ];

                        $existingTask = Task::where('task_name', $task['task_name'])->first();

                        if ($existingTask) {
                            $existingUserList = $existingTask->user_list ?? [];
                            $userExists = false;

                            if (is_array($existingUserList)) {
                                foreach ($existingUserList as $key => $userData) {
                                    // Check if this task already exists for this user by comparing tId
                                    if (isset($userData['tId']) && $userData['tId'] == ($task['task_id'] ?? null)) {
                                        $existingUserList[$key] = $userListData;
                                        $userExists = true;
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
                                'is_active' => ($task['status'] === 'Active' || $task['active'] ?? false),
                                'last_synced_at' => now(),
                                'user_list' => $existingUserList,
                            ]);
                        } else {
                            Task::create([
                                'task_name' => $task['task_name'],
                                'user_list' => [$userListData],
                                'is_active' => ($task['status'] === 'Active' || $task['active'] ?? false),
                                'last_synced_at' => now(),
                            ]);
                        }

                        $syncCount++;
                    }
                }

                DB::commit();

                ActivityLogService::log('sync_timedoctor_data', 'TimeDoctor tasks synced successfully', [
                    'module' => 'timedoctor_integration',
                    'synced_count' => $syncCount,
                    'users_processed' => $users->count(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Tasks synced successfully',
                    'synced_count' => $syncCount,
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error syncing TimeDoctor tasks', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            ActivityLogService::log('sync_timedoctor_data', 'Failed to sync TimeDoctor tasks', [
                'module' => 'timedoctor_integration',
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error syncing tasks: '.$e->getMessage(),
            ], 500);
        }
    }

    public function syncWorklogs(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'queue_job' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $startDate = Carbon::parse($request->input('start_date'));
        $endDate = Carbon::parse($request->input('end_date'));
        $queueJob = $request->input('queue_job', true);

        if ($startDate->diffInDays($endDate) > 30) {
            return response()->json([
                'success' => false,
                'message' => 'Date range cannot exceed 31 days',
            ], 400);
        }

        if ($queueJob) {
            SyncTimeDoctorWorklogs::dispatch($startDate->toDateString(), $endDate->toDateString());

            ActivityLogService::log('sync_timedoctor_data', 'TimeDoctor worklog sync job queued', [
                'module' => 'timedoctor_integration',
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Worklog sync job has been queued',
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ]);
        } else {
            return response()->json([
                'success' => true,
                'message' => 'For immediate sync, please use the streaming endpoint',
                'stream_url' => url('/api/time-doctor/stream-worklog-sync?start_date='.$startDate->toDateString().'&end_date='.$endDate->toDateString()),
            ]);
        }
    }

    public function getUserCount(): JsonResponse
    {
        $count = TimedoctorV1User::count();

        return response()->json([
            'success' => true,
            'count' => $count,
        ]);
    }

    public function getProjectCount(): JsonResponse
    {
        $count = Project::count();

        return response()->json([
            'success' => true,
            'count' => $count,
        ]);
    }

    public function getTaskCount(): JsonResponse
    {
        $count = Task::count();

        return response()->json([
            'success' => true,
            'count' => $count,
        ]);
    }

    public function getWorklogCount(): JsonResponse
    {
        $count = WorklogsData::where('api_type', 'timedoctor')->count();

        return response()->json([
            'success' => true,
            'count' => $count,
        ]);
    }
}
