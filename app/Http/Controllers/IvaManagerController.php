<?php
namespace App\Http\Controllers;

use App\Models\ConfigurationSetting;
use App\Models\IvaManager;
use App\Models\IvaUser;
use App\Models\Region;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class IvaManagerController extends Controller
{
    /**
     * Display a listing of all managers.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', config('constants.pagination.iva_managers_per_page'));
        $perPage = min($perPage, config('constants.pagination.max_per_page'));

        // First, get representative IDs for each unique manager/region/type combination
        $subquery = DB::table('iva_manager')
            ->select(
                'iva_manager_id',
                'region_id',
                'manager_type_id',
                DB::raw('MIN(id) as id') // Get a representative record ID for each combination
            )
            ->groupBy('iva_manager_id', 'region_id', 'manager_type_id');

        // Apply filters if provided
        if ($request->has('region_id') && $request->region_id) {
            $subquery->where('region_id', $request->region_id);
        }

        if ($request->has('manager_id') && $request->manager_id) {
            $subquery->where('iva_manager_id', $request->manager_id);
        }

        if ($request->has('manager_type_id') && $request->manager_type_id) {
            $subquery->where('manager_type_id', $request->manager_type_id);
        }

        // Now get the full details for those representative records
        $query = DB::table('iva_manager')
            ->joinSub($subquery, 'unique_managers', function ($join) {
                $join->on('iva_manager.id', '=', 'unique_managers.id');
            })
            ->join('regions', 'iva_manager.region_id', '=', 'regions.id')
            ->join('iva_user as manager', 'iva_manager.iva_manager_id', '=', 'manager.id')
            ->join('configuration_settings as type', 'iva_manager.manager_type_id', '=', 'type.id')
            ->select(
                'iva_manager.id',
                'iva_manager.iva_manager_id',
                'iva_manager.region_id',
                'iva_manager.manager_type_id',
                'manager.full_name as manager_full_name',
                'manager.email as manager_email',
                'regions.name as region_name',
                'type.setting_value as manager_type_value'
            )
            ->orderBy('regions.name')
            ->orderBy('manager.full_name');

        // Also get the count of users for each manager/region/type combination
        $countsQuery = DB::table('iva_manager')
            ->select(
                'iva_manager_id',
                'region_id',
                'manager_type_id',
                DB::raw('COUNT(DISTINCT iva_id) as managed_users_count')
            )
            ->groupBy('iva_manager_id', 'region_id', 'manager_type_id');

        $managers = $query->paginate($perPage);
        $counts   = $countsQuery->get()->keyBy(function ($item) {
            return $item->iva_manager_id . '-' . $item->region_id . '-' . $item->manager_type_id;
        });

        // Transform data to match expected structure
        $managers->getCollection()->transform(function ($item) use ($counts) {
            $key   = $item->iva_manager_id . '-' . $item->region_id . '-' . $item->manager_type_id;
            $count = isset($counts[$key]) ? $counts[$key]->managed_users_count : 0;

            return [
                'id'                  => $item->id,
                'iva_manager_id'      => $item->iva_manager_id,
                'region_id'           => $item->region_id,
                'manager_type_id'     => $item->manager_type_id,
                'managed_users_count' => $count,
                'manager'             => [
                    'full_name' => $item->manager_full_name,
                    'email'     => $item->manager_email,
                ],
                'region'              => [
                    'name' => $item->region_name,
                ],
                'manager_type'        => [
                    'setting_value' => $item->manager_type_value,
                ],
            ];
        });

        // Get all regions for filtering
        $regions = Region::where('is_active', true)->orderBy('name')->get();

        // Get all manager types
        $managerTypes = ConfigurationSetting::where('setting_type_id', function ($query) {
            $query->select('id')
                ->from('configuration_settings_type')
                ->where('key', 'manager_type');
        })->where('is_active', true)
            ->get();

        // Log the activity
        ActivityLogService::log(
            'view_iva_managers_list',
            'Viewed IVA managers list',
            ['total_managers' => $managers->total()]
        );

        return response()->json([
            'managers'     => $managers,
            'regions'      => $regions,
            'managerTypes' => $managerTypes,
        ]);
    }

    /**
     * Display the specified manager assignment with users.
     */
    public function show($id)
    {
        // Get the manager assignment record
        $assignment = IvaManager::findOrFail($id);

        // Get all the data we need
        $managerData = DB::table('iva_manager')
            ->join('iva_user as manager', 'iva_manager.iva_manager_id', '=', 'manager.id')
            ->join('regions', 'iva_manager.region_id', '=', 'regions.id')
            ->join('configuration_settings as type', 'iva_manager.manager_type_id', '=', 'type.id')
            ->where('iva_manager.id', $id)
            ->select(
                'iva_manager.*',
                'manager.id as manager_id',
                'manager.full_name as manager_full_name',
                'manager.email as manager_email',
                'type.id as manager_type_id',
                'type.setting_value as manager_type_value',
                'regions.id as region_id',
                'regions.name as region_name'
            )
            ->first();

        if (! $managerData) {
            return response()->json(['error' => 'Manager assignment not found'], 404);
        }

        // Structure the manager data as expected by the frontend
        $managerResponse = [
            'id'              => $managerData->id,
            'iva_manager_id'  => $managerData->iva_manager_id,
            'manager_type_id' => $managerData->manager_type_id,
            'region_id'       => $managerData->region_id,
            'manager'         => [
                'id'        => $managerData->manager_id,
                'full_name' => $managerData->manager_full_name,
                'email'     => $managerData->manager_email,
            ],
            'managerType'     => [
                'id'            => $managerData->manager_type_id,
                'setting_value' => $managerData->manager_type_value,
            ],
            'region'          => [
                'id'   => $managerData->region_id,
                'name' => $managerData->region_name,
            ],
        ];

        // Get all users assigned to this manager in this region with this manager type
        $users = IvaUser::whereIn('id', function ($query) use ($assignment) {
            $query->select('iva_id')
                ->from('iva_manager')
                ->where('iva_manager_id', $assignment->iva_manager_id)
                ->where('region_id', $assignment->region_id)
                ->where('manager_type_id', $assignment->manager_type_id);
        })->get();

        // Log the activity
        ActivityLogService::log(
            'view_iva_manager',
            'Viewed manager details: ' . $managerData->manager_full_name,
            ['manager_id' => $id, 'users_count' => $users->count()]
        );

        return response()->json([
            'manager' => $managerResponse,
            'users'   => $users,
        ]);
    }

    /**
     * Store a newly created manager assignment.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'region_id'       => 'required|exists:regions,id',
            'manager_id'      => 'required|exists:iva_user,id',
            'manager_type_id' => 'required|exists:configuration_settings,id',
            'user_ids'        => 'required|array',
            'user_ids.*'      => 'exists:iva_user,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $regionId      = $request->region_id;
        $managerId     = $request->manager_id;
        $managerTypeId = $request->manager_type_id;
        $userIds       = $request->user_ids;

        // Check that manager isn't self-managing
        if (in_array($managerId, $userIds)) {
            return response()->json([
                'message' => 'A user cannot be their own manager',
                'error'   => 'Invalid manager assignment',
            ], 422);
        }

        // Get details for logging
        $region      = Region::find($regionId);
        $manager     = IvaUser::find($managerId);
        $managerType = ConfigurationSetting::find($managerTypeId);

        // Begin transaction
        DB::beginTransaction();
        try {
            $assignments = [];

            // Create manager assignments
            foreach ($userIds as $userId) {
                // Check if this user already has this type of manager in this region
                $existingAssignment = IvaManager::where('iva_id', $userId)
                    ->where('region_id', $regionId)
                    ->where('manager_type_id', $managerTypeId)
                    ->first();

                if ($existingAssignment) {
                    // Update existing assignment
                    $existingAssignment->update([
                        'iva_manager_id' => $managerId,
                    ]);
                    $assignments[] = $existingAssignment->id;
                } else {
                    // Create new assignment
                    $newAssignment = IvaManager::create([
                        'iva_id'          => $userId,
                        'iva_manager_id'  => $managerId,
                        'manager_type_id' => $managerTypeId,
                        'region_id'       => $regionId,
                    ]);
                    $assignments[] = $newAssignment->id;
                }
            }

            // Log the activity
            ActivityLogService::log(
                'create_iva_manager',
                'Assigned ' . $manager->full_name . ' as ' . $managerType->setting_value . ' manager to ' . count($userIds) . ' users in region: ' . $region->name,
                [
                    'region_id'       => $regionId,
                    'manager_id'      => $managerId,
                    'manager_type_id' => $managerTypeId,
                    'user_ids'        => $userIds,
                    'assignments'     => $assignments,
                ]
            );

            DB::commit();

            return response()->json([
                'message'     => 'Manager assigned successfully',
                'assignments' => $assignments,
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Failed to assign manager',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a manager assignment.
     */
    public function destroy($id)
    {
        try {
            // Get details for logging before deletion
            $assignment = IvaManager::findOrFail($id);

            // Get manager, type, and region data
            $managerData = DB::table('iva_manager')
                ->join('iva_user as manager', 'iva_manager.iva_manager_id', '=', 'manager.id')
                ->join('regions', 'iva_manager.region_id', '=', 'regions.id')
                ->join('configuration_settings as type', 'iva_manager.manager_type_id', '=', 'type.id')
                ->where('iva_manager.id', $id)
                ->select(
                    'manager.full_name as manager_name',
                    'type.setting_value as manager_type',
                    'regions.name as region_name'
                )
                ->first();

            // Get all users managed by this manager in this assignment
            $users = IvaUser::whereIn('id', function ($query) use ($assignment) {
                $query->select('iva_id')
                    ->from('iva_manager')
                    ->where('iva_manager_id', $assignment->iva_manager_id)
                    ->where('region_id', $assignment->region_id)
                    ->where('manager_type_id', $assignment->manager_type_id);
            })->get();

            // Delete the assignment
            IvaManager::where('iva_manager_id', $assignment->iva_manager_id)
                ->where('region_id', $assignment->region_id)
                ->where('manager_type_id', $assignment->manager_type_id)
                ->delete();

            // Log the activity
            ActivityLogService::log(
                'delete_iva_manager',
                'Removed ' . $managerData->manager_name . ' as ' . $managerData->manager_type . ' manager from region: ' . $managerData->region_name,
                [
                    'assignment'   => $assignment,
                    'manager_name' => $managerData->manager_name,
                    'manager_type' => $managerData->manager_type,
                    'region_name'  => $managerData->region_name,
                    'users'        => $users,
                ]
            );

            return response()->json([
                'message' => 'Manager assignment removed successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove manager assignment',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a user from a manager.
     */
    public function removeUser(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:iva_user,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Get assignment details
            $assignment = IvaManager::findOrFail($id);

            // Get related data
            $relationData = DB::table('iva_manager')
                ->join('iva_user as manager', 'iva_manager.iva_manager_id', '=', 'manager.id')
                ->join('regions', 'iva_manager.region_id', '=', 'regions.id')
                ->join('configuration_settings as type', 'iva_manager.manager_type_id', '=', 'type.id')
                ->join('iva_user as user', 'user.id', '=', DB::raw($request->user_id))
                ->where('iva_manager.id', $id)
                ->select(
                    'manager.full_name as manager_name',
                    'user.full_name as user_name',
                    'type.setting_value as manager_type',
                    'regions.name as region_name'
                )
                ->first();

            if (! $relationData) {
                return response()->json(['error' => 'User or manager assignment not found'], 404);
            }

            // Delete the specific user assignment
            IvaManager::where('iva_manager_id', $assignment->iva_manager_id)
                ->where('region_id', $assignment->region_id)
                ->where('manager_type_id', $assignment->manager_type_id)
                ->where('iva_id', $request->user_id)
                ->delete();

            // Log the activity
            ActivityLogService::log(
                'remove_user_from_iva_manager',
                'Removed user ' . $relationData->user_name . ' from ' . $relationData->manager_name . ' as ' . $relationData->manager_type . ' manager in region: ' . $relationData->region_name,
                [
                    'assignment_id' => $id,
                    'user_id'       => $request->user_id,
                    'manager_name'  => $relationData->manager_name,
                    'user_name'     => $relationData->user_name,
                    'manager_type'  => $relationData->manager_type,
                    'region_name'   => $relationData->region_name,
                ]
            );

            return response()->json([
                'message' => 'User removed from manager successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove user from manager',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add users to an existing manager assignment.
     */
    public function addUsers(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'user_ids'   => 'required|array',
            'user_ids.*' => 'exists:iva_user,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Get assignment details
            $assignment = IvaManager::findOrFail($id);

            // Get manager, type, and region data for logging
            $managerData = DB::table('iva_manager')
                ->join('iva_user as manager', 'iva_manager.iva_manager_id', '=', 'manager.id')
                ->join('regions', 'iva_manager.region_id', '=', 'regions.id')
                ->join('configuration_settings as type', 'iva_manager.manager_type_id', '=', 'type.id')
                ->where('iva_manager.id', $id)
                ->select(
                    'manager.full_name as manager_name',
                    'type.setting_value as manager_type',
                    'regions.name as region_name'
                )
                ->first();

            if (! $managerData) {
                return response()->json(['error' => 'Manager assignment not found'], 404);
            }

            // Check that manager isn't trying to manage themselves
            if (in_array($assignment->iva_manager_id, $request->user_ids)) {
                return response()->json([
                    'message' => 'A manager cannot manage themselves',
                    'error'   => 'Invalid user assignment',
                ], 422);
            }

            DB::beginTransaction();
            try {
                $newAssignments = [];
                $addedUserNames = [];

                foreach ($request->user_ids as $userId) {
                    // Check if this user already has this type of manager in this region
                    $existingAssignment = IvaManager::where('iva_id', $userId)
                        ->where('region_id', $assignment->region_id)
                        ->where('manager_type_id', $assignment->manager_type_id)
                        ->first();

                    if ($existingAssignment) {
                        // Update existing assignment to this manager
                        $existingAssignment->update([
                            'iva_manager_id' => $assignment->iva_manager_id,
                        ]);
                    } else {
                        // Create new assignment
                        IvaManager::create([
                            'iva_id'          => $userId,
                            'iva_manager_id'  => $assignment->iva_manager_id,
                            'manager_type_id' => $assignment->manager_type_id,
                            'region_id'       => $assignment->region_id,
                        ]);
                    }

                    $newAssignments[] = $userId;

                    // Get user name for logging
                    $user = IvaUser::find($userId);
                    if ($user) {
                        $addedUserNames[] = $user->full_name;
                    }
                }

                // Log the activity
                ActivityLogService::log(
                    'add_users_to_iva_manager',
                    'Added ' . count($newAssignments) . ' users to ' . $managerData->manager_name . ' as ' . $managerData->manager_type . ' manager in region: ' . $managerData->region_name,
                    [
                        'assignment_id'    => $id,
                        'manager_name'     => $managerData->manager_name,
                        'manager_type'     => $managerData->manager_type,
                        'region_name'      => $managerData->region_name,
                        'added_user_ids'   => $newAssignments,
                        'added_user_names' => $addedUserNames,
                    ]
                );

                DB::commit();

                return response()->json([
                    'message' => count($newAssignments) . ' users added to manager successfully',
                ]);
            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to add users to manager',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available users that can be added to a manager.
     */
    public function getAvailableUsers($id)
    {
        try {
            // Get assignment details
            $assignment = IvaManager::findOrFail($id);

            // Get all users in the same region
            $allUsersInRegion = IvaUser::where('region_id', $assignment->region_id)
                ->where('is_active', true)
                ->get();

            // Get users already assigned to this manager with this manager type in this region
            $assignedUserIds = IvaManager::where('iva_manager_id', $assignment->iva_manager_id)
                ->where('region_id', $assignment->region_id)
                ->where('manager_type_id', $assignment->manager_type_id)
                ->pluck('iva_id')
                ->toArray();

            // Filter out already assigned users and the manager themselves
            $availableUsers = $allUsersInRegion->filter(function ($user) use ($assignedUserIds, $assignment) {
                return ! in_array($user->id, $assignedUserIds) && $user->id !== $assignment->iva_manager_id;
            })->values();

            return response()->json([
                'availableUsers' => $availableUsers,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get available users',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available managers and users for a region.
     */
    public function getRegionData($regionId)
    {
        $region = Region::findOrFail($regionId);

        // Get active users in this region
        $users = IvaUser::where('region_id', $regionId)
            ->where('is_active', true)
            ->get();

        // Get all available manager types
        $managerTypes = ConfigurationSetting::where('setting_type_id', function ($query) {
            $query->select('id')
                ->from('configuration_settings_type')
                ->where('key', 'manager_type');
        })->where('is_active', true)
            ->get();

        return response()->json([
            'region'       => $region,
            'users'        => $users,
            'managerTypes' => $managerTypes,
        ]);
    }
}