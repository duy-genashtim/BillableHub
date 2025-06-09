<?php
namespace App\Http\Controllers;

use App\Jobs\SyncTimeDoctorWorklogs;
use App\Models\IvaUser;
use App\Models\Project;
use App\Models\Region;
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

            $syncCount       = 0;
            $nameToRegionMap = [
                'Blab - Afonso Pedroso'                      => 'America (Cohorts 1-5)',
                'Blab - Alexandra Castillo'                  => 'EMEA',
                'Blab - Alma Vazquez'                        => 'America (Cohorts 1-5)',
                'Blab - Almira Zaskya'                       => 'ID/PH',
                'Blab - Ananta Praditya'                     => 'ID/PH',
                'Blab - Angeline Gonzales'                   => 'ID/PH',
                'Blab - Aprilya Lestari'                     => 'ID/PH',
                'Blab - Bahman Mehryar'                      => 'ID/PH',
                'Blab - Bavani Jayadevan'                    => 'MY/SG',
                'Blab - Bernard Nathan'                      => 'MY/SG',
                'Blab - Bismarck Njeck Tifang'               => 'EMEA',
                'Blab - Charlie Lee'                         => 'MY/SG',
                'Blab - Charlyne Bong Chiao Lynn'            => 'MY/SG',
                'Blab - Cilene Mazzarelli'                   => 'America (Cohorts 1-5)',
                'Blab - Daniel Makombo Ali'                  => 'EMEA',
                'Blab - Eric Sanches Simões'                 => 'America (Cohorts 1-5)',
                'Blab - Erick Monteiro'                      => 'America (Cohorts 1-5)',
                'Blab - Eriona Abazi'                        => 'EMEA',
                'Blab - Felipe Matheus Müller'               => 'America (Cohorts 1-5)',
                'Blab - Gabriel Angulo'                      => 'America (Cohorts 1-5)',
                'Blab - Gabriela Eugenio'                    => 'America (Cohorts 1-5)',
                'Blab - Giordano Morello'                    => 'America (Cohorts 1-5)',
                'Blab - Jesús Eduardo Muruaga Díaz'          => 'America (Cohorts 1-5)',
                'Blab - Juan Quintero'                       => 'America (Cohorts 1-5)',
                'Blab - Juliana Cristancho'                  => 'EMEA',
                'Blab - Juliana Motta'                       => 'EMEA',
                'Blab - June Ng'                             => 'MY/SG',
                'Blab - Khales Manokra'                      => 'America (Cohorts 1-5)',
                'Blab - Kimi Ashikeen Iqbal'                 => 'MY/SG',
                'Blab - Kristina Golovina'                   => 'America (Cohort 6)',
                'Blab - Leelavathi Raju'                     => 'America (Cohorts 1-5)',
                'Blab - Leigh Mcintyre'                      => 'America (Cohorts 1-5)',
                'Blab - Marco Amorim Campos Siktberg'        => 'EMEA',
                'Blab - Maria Jose Ramirez'                  => 'America (Cohorts 1-5)',
                'Blab - Matheus Pinheiro Gomes'              => 'America (Cohorts 1-5)',
                'Blab - Mazrina Mahat'                       => 'MY/SG',
                'Blab - Michelle Nabong'                     => 'ID/PH',
                'Blab - Mohammad Salikandi'                  => 'EMEA',
                'Blab - Muhammad Adam Aiman Bin Mohd Raflee' => 'MY/SG',
                'Blab - Muneer Ahmad'                        => 'EMEA',
                'Blab - Natalia Lopes'                       => 'America (Cohorts 1-5)',
                'Blab - Norshahana Abd Halim'                => 'MY/SG',
                'Blab - Oceane Metais'                       => 'ID/PH',
                'Blab - Patricea D\'cruz'                    => 'MY/SG',
                'Blab - Pearly Mershia Wuryanto'             => 'ID/PH',
                'Blab - Raj Kumar Selvaraj'                  => 'MY/SG',
                'Blab - Ricardo Bosqueiro Ayres'             => 'America (Cohorts 1-5)',
                'Blab - Rizza Tenorio'                       => 'ID/PH',
                'Blab - Robin Low'                           => 'MY/SG',
                'Blab - Roger Ooi'                           => 'MY/SG',
                'Blab - Ronald Teo'                          => 'MY/SG',
                'Blab - Rose Ann De Mesa'                    => 'America (Cohort 6)',
                'Blab - Samantha Foo'                        => 'MY/SG',
                'Blab - Sara Barajas'                        => 'MY/SG',
                'Blab - Silvia Soncini'                      => 'EMEA',
                'Blab - Stephanie Roedel'                    => 'EMEA',
                'Blab - Subashini Maniam'                    => 'MY/SG',
                'Blab - Thiago Silva'                        => 'America (Cohorts 1-5)',
                'Blab - Usha Packia Rani Sithamparam'        => 'ID/PH',
                'Blab - Vera Verawati'                       => 'ID/PH',
                'Blab - Veronica Red Pioquinto'              => 'ID/PH',
                'Blab - Yaxal Vasquez Cabrera'               => 'America (Cohorts 1-5)',
                'Blab - Ahmed Heiba'                         => 'America (Cohort 6)',
                'Blab - Alexandra Donose'                    => 'EMEA',
                'Blab - Ashish Pradhan'                      => 'EMEA',
                'Blab - Bhagyaraj K'                         => 'EMEA',
                'Blab - Carlos Daniel Castañeda Novoa'       => 'America (Cohort 6)',
                'Blab - Christina Yeo'                       => 'MY/SG',
                'Blab - Cristina Luengo'                     => 'EMEA',
                'Blab - Daniel Aviña Ramírez'                => 'America (Cohort 6)',
                'Blab - Denisse Ramos'                       => 'America (Cohort 6)',
                'Blab - Dionelio Jesus Moreno'               => 'America (Cohort 6)',
                'Blab - Fadzai Praise Musakana'              => 'EMEA',
                'Blab - Gerardo Tallavas'                    => 'America (Cohort 6)',
                'Blab - Gillian Henderson'                   => 'America (Cohort 6)',
                'Blab - Juan Felipe Pinto Castelblanco'      => 'America (Cohort 6)',
                'Blab - Karen Ximena Laguna Serrato'         => 'America (Cohort 6)',
                'Blab - Laura Lizbeth Sanchez Ruiz'          => 'America (Cohort 6)',
                'Blab - Laura Sofia Llanos'                  => 'America (Cohort 6)',
                'Blab - Luz Ángela Libreros'                 => 'America (Cohort 6)',
                'Blab - Iris Pardillo'                       => 'ID/PH',
                'Blab - Isabel Sampaio Rodrigues'            => 'America (Cohort 6)',
                'Blab - Mariela Deancy Parcon'               => 'ID/PH',
                'Blab - Marizabel Valencia Sánchez'          => 'America (Cohort 6)',
                'Blab - Marzia Puya'                         => 'EMEA',
                'Blab - María Camila Cortés-Bohórquez'       => 'America (Cohort 6)',
                'Blab - María Paula Olaya Pulido'            => 'America (Cohort 6)',
                'Blab - Muhammad Harith Zamsaimi'            => 'MY/SG',
                'Blab - Santiago Osorio Piedrahita'          => 'America (Cohort 6)',
                'Blab - Siew Sim Lim'                        => 'MY/SG',
                'Blab - Sshreyas Hariharno'                  => 'EMEA',
                'Blab - Wei Jing Khaw'                       => 'MY/SG',
                'Blab - Isaac Robinson'                      => 'EMEA',
                'Blab - Tracey Ong'                          => 'MY/SG',
                'Blab - Eric Yves Wuilleumier'               => 'America (Cohort 6)',
                'Blab - Ruthy Yang'                          => 'ID/PH',
                'Blab - Giancarlo Fiorito'                   => 'EMEA',
                'Blab - Ahmad Kamil'                         => 'MY/SG',
                'Blab - Alberto Avila Nunez'                 => 'America (Cohort 6)',
                'Blab - Alice Faustine'                      => 'ID/PH',
                'Blab - Amadea Risa Hardigaloeh'             => 'ID/PH',
                'Blab - Anil Kotame'                         => 'EMEA',
                'Blab - Arnold L. Sagritalo'                 => 'ID/PH',
                'Blab - Ayesha Afreen'                       => 'EMEA',
                'Blab - Celine Lean'                         => 'MY/SG',
                'Blab - Christopher Mark Gomez'              => 'MY/SG',
                'Blab - Clarice Carvalho'                    => 'EMEA',
                'Blab - Faizah Newell'                       => 'MY/SG',
                'Blab - Julie Le Gallo'                      => 'EMEA',
                'Blab - Karina Sanchez'                      => 'America (Cohort 6)',
                'Blab - Leslie Leow'                         => 'MY/SG',
                'Blab - Michael Steven Alfaro Diaz'          => 'America (Cohort 6)',
                'Blab - Mohamed Eid'                         => 'EMEA',
                'Blab - Mohd Adree'                          => 'MY/SG',
                'Blab - Mohd Rizwan Ali'                     => 'EMEA',
                'Blab - Muhammad Luthvan Hood'               => 'ID/PH',
                'Blab - Paloma De Oliveira'                  => 'EMEA',
                'Blab - Rabia Batool'                        => 'EMEA',
                'Blab - Raoul Vicente Abraham Perez'         => 'ID/PH',
                'Blab - Sasho Boshevski'                     => 'EMEA',
                'Blab - Supachai Kulchokvanich'              => 'ID/PH',
                'Blab - Tunku Muhammad'                      => 'MY/SG',
                'Blab - Victor Robles'                       => 'America (Cohort 6)',
                'Blab - Bruno Scott'                         => 'EMEA',
                'Blab - Jeremy Lutgen'                       => 'MY/SG',
            ];

            DB::beginTransaction();

            try {
                foreach ($usersData['users'] as $user) {
                    $timeDoctorUser = TimedoctorV1User::updateOrCreate(
                        ['timedoctor_id' => $user['user_id']],
                        [
                            'tm_fullname'    => $user['full_name'],
                            'tm_email'       => $user['email'],
                            'is_active'      => true,
                            'last_synced_at' => now(),
                        ]
                    );

                    if (strpos($user['full_name'], 'Blab -') === 0) {
                        $regionName = $nameToRegionMap[$user['full_name']] ?? null;
                        $regionId   = null;

                        if ($regionName) {
                            $region   = Region::where('name', $regionName)->first();
                            $regionId = $region ? $region->id : null;
                        }

                        $ivaUser = IvaUser::firstOrCreate(
                            ['email' => $user['email']],
                            [
                                'full_name'          => $user['full_name'],
                                'timedoctor_version' => 1,
                                'is_active'          => true,
                                'hire_date'          => null,
                                'region_id'          => $regionId,
                            ]
                        );

                        $timeDoctorUser->iva_user_id = $ivaUser->id;
                        $timeDoctorUser->save();
                    }

                    $syncCount++;
                }

                DB::commit();

                ActivityLogService::log('import_excel_time', 'TimeDoctor users synced successfully', [
                    'module'       => 'timedoctor_integration',
                    'synced_count' => $syncCount,
                    'total_users'  => count($usersData['users']),
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
            Log::error('Error syncing TimeDoctor users', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            ActivityLogService::log('import_excel_time', 'Failed to sync TimeDoctor users', [
                'module' => 'timedoctor_integration',
                'error'  => $e->getMessage(),
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
                            'project_name'       => $project['name'],
                            'is_active'          => ! ($project['deleted'] ?? false),
                            'description'        => null,
                            'last_synced_at'     => now(),
                        ]
                    );

                    $syncCount++;
                }

                DB::commit();

                ActivityLogService::log('import_excel_time', 'TimeDoctor projects synced successfully', [
                    'module'         => 'timedoctor_integration',
                    'synced_count'   => $syncCount,
                    'total_projects' => count($projectsData['count']),
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
            Log::error('Error syncing TimeDoctor projects', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            ActivityLogService::log('import_excel_time', 'Failed to sync TimeDoctor projects', [
                'module' => 'timedoctor_integration',
                'error'  => $e->getMessage(),
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
            $companyInfo = $this->timeDoctorService->getCompanyInfo();

            if (! isset($companyInfo['accounts'][0]['company_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not retrieve company ID from TimeDoctor',
                ], 400);
            }

            $companyId = $companyInfo['accounts'][0]['company_id'];
            $users     = TimedoctorV1User::where('is_active', true)->whereNotNull('iva_user_id')->get();

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
                            'userId'       => $user->timedoctor_id,
                            'timedoctorId' => $task['task_id'] ?? null,
                        ];

                        $existingTask = Task::where('task_name', $task['task_name'])->first();

                        if ($existingTask) {
                            $existingUserList = $existingTask->user_list ?? [];
                            $userExists       = false;

                            if (is_array($existingUserList)) {
                                foreach ($existingUserList as $key => $userData) {
                                    if (isset($userData['userId']) && $userData['userId'] == $user->timedoctor_id) {
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
                                'is_active'      => ($task['status'] === 'Active' || $task['active'] ?? false),
                                'last_synced_at' => now(),
                                'user_list'      => $existingUserList,
                            ]);
                        } else {
                            Task::create([
                                'timedoctor_version' => 1,
                                'task_name'          => $task['task_name'],
                                'user_list'          => [$userListData],
                                'is_active'          => ($task['status'] === 'Active' || $task['active'] ?? false),
                                'last_synced_at'     => now(),
                            ]);
                        }

                        $syncCount++;
                    }
                }

                DB::commit();

                ActivityLogService::log('import_excel_time', 'TimeDoctor tasks synced successfully', [
                    'module'          => 'timedoctor_integration',
                    'synced_count'    => $syncCount,
                    'users_processed' => $users->count(),
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
            Log::error('Error syncing TimeDoctor tasks', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            ActivityLogService::log('import_excel_time', 'Failed to sync TimeDoctor tasks', [
                'module' => 'timedoctor_integration',
                'error'  => $e->getMessage(),
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
            SyncTimeDoctorWorklogs::dispatch($startDate->toDateString(), $endDate->toDateString());

            ActivityLogService::log('import_excel_time', 'TimeDoctor worklog sync job queued', [
                'module'     => 'timedoctor_integration',
                'start_date' => $startDate->toDateString(),
                'end_date'   => $endDate->toDateString(),
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
                'stream_url' => url('/api/time-doctor/stream-worklog-sync?start_date=' . $startDate->toDateString() . '&end_date=' . $endDate->toDateString()),
            ]);
        }
    }

    public function getUserCount(): JsonResponse
    {
        $count = TimedoctorV1User::count();

        return response()->json([
            'success' => true,
            'count'   => $count,
        ]);
    }

    public function getProjectCount(): JsonResponse
    {
        $count = Project::count();

        return response()->json([
            'success' => true,
            'count'   => $count,
        ]);
    }

    public function getTaskCount(): JsonResponse
    {
        $count = Task::count();

        return response()->json([
            'success' => true,
            'count'   => $count,
        ]);
    }

    public function getWorklogCount(): JsonResponse
    {
        $count = WorklogsData::where('api_type', 'timedoctor')->count();

        return response()->json([
            'success' => true,
            'count'   => $count,
        ]);
    }
}
