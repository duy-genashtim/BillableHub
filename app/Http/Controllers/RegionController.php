<?php

namespace App\Http\Controllers;

use App\Models\IvaUser;
use App\Models\Region;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RegionController extends Controller
{
    /**
     * Display a listing of regions
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', config('constants.pagination.default_per_page', 20));
            $perPage = min($perPage, config('constants.pagination.max_per_page', 100));

            $query = Region::withCount('ivaUsers');

            // Search functionality
            if ($request->has('search') && ! empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Status filter
            if ($request->has('status') && $request->status !== '') {
                $query->where('is_active', $request->status === 'active');
            }

            // Sort
            $sortBy = $request->get('sort_by', 'region_order');
            $sortOrder = $request->get('sort_order', 'asc');

            if (in_array($sortBy, ['id', 'name', 'region_order', 'is_active', 'created_at'])) {
                $query->withoutGlobalScope('ordered')->orderBy($sortBy, $sortOrder);
            }

            $regions = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'regions' => $regions->items(),
                'pagination' => [
                    'current_page' => $regions->currentPage(),
                    'last_page' => $regions->lastPage(),
                    'per_page' => $regions->perPage(),
                    'total' => $regions->total(),
                    'from' => $regions->firstItem(),
                    'to' => $regions->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch regions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created region
     */
    public function store(Request $request)
    {
        // Check if user has permission to edit IVA data
        if (! $request->user()->can('edit_iva_data')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:regions,name',
            'description' => 'nullable|string|max:1000',
            'region_order' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $regionData = $request->only(['name', 'description', 'is_active']);
            $regionData['region_order'] = $request->region_order ?? Region::getNextOrder();

            $region = Region::create($regionData);

            // Log the activity
            ActivityLogService::log(
                'region_create',
                "Created region: {$region->name}",
                [
                    'region_id' => $region->id,
                    'region_name' => $region->name,
                    'region_data' => $regionData,
                    'module' => 'regions',
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Region created successfully',
                'region' => $region,
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create region',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified region
     */
    public function show($id)
    {
        try {
            $region = Region::with(['ivaUsers' => function ($query) {
                $query->select('id', 'full_name', 'email', 'region_id', 'is_active', 'timedoctor_version', 'work_status', 'hire_date', 'created_at');
            }])->findOrFail($id);

            return response()->json([
                'success' => true,
                'region' => $region,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Region not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update the specified region
     */
    public function update(Request $request, $id)
    {
        // Check if user has permission to edit IVA data
        if (! $request->user()->can('edit_iva_data')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:regions,name,'.$id,
            'description' => 'nullable|string|max:1000',
            'region_order' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $region = Region::findOrFail($id);
            $oldData = $region->toArray();

            $regionData = $request->only(['name', 'description', 'region_order', 'is_active']);
            $region->update($regionData);

            // Log the activity
            ActivityLogService::log(
                'region_update',
                "Updated region: {$region->name}",
                [
                    'region_id' => $region->id,
                    'region_name' => $region->name,
                    'old_data' => $oldData,
                    'new_data' => $region->fresh()->toArray(),
                    'module' => 'regions',
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Region updated successfully',
                'region' => $region,
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update region',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified region (soft delete by marking inactive)
     */
    public function destroy(Request $request, $id)
    {
        // Check if user has permission to edit IVA data
        if (! $request->user()->can('edit_iva_data')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();
        try {
            $region = Region::findOrFail($id);
            $regionName = $region->name;
            $usersCount = $region->ivaUsers()->count();

            // Check if region has IVA users
            if ($usersCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete region '{$regionName}' because it has {$usersCount} assigned IVA user(s). Please reassign users first.",
                ], 400);
            }

            $region->update(['is_active' => false]);

            // Log the activity
            ActivityLogService::log(
                'region_deactivate',
                "Deactivated region: {$regionName}",
                [
                    'region_id' => $region->id,
                    'region_name' => $regionName,
                    'module' => 'regions',
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Region deactivated successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate region',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get IVA users available for region assignment
     */
    public function getAvailableUsers()
    {
        try {
            $users = IvaUser::where('is_active', true)
                ->whereNull('region_id')
                ->select('id', 'full_name', 'email', 'timedoctor_version', 'work_status', 'hire_date', 'created_at')
                ->orderBy('full_name')
                ->get();

            return response()->json([
                'success' => true,
                'users' => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch available users',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign IVA users to a region
     */
    public function assignUsers(Request $request, $id)
    {
        // Check if user has permission to edit IVA data
        if (! $request->user()->can('edit_iva_data')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:iva_user,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $region = Region::findOrFail($id);
            $userIds = $request->user_ids;

            // Update IVA users' region
            IvaUser::whereIn('id', $userIds)->update(['region_id' => $region->id]);

            $assignedUsers = IvaUser::whereIn('id', $userIds)->get(['id', 'full_name', 'email']);

            // Log the activity
            ActivityLogService::log(
                'region_assign_users',
                "Assigned {$assignedUsers->count()} IVA user(s) to region: {$region->name}",
                [
                    'region_id' => $region->id,
                    'region_name' => $region->name,
                    'user_ids' => $userIds,
                    'assigned_users' => $assignedUsers->toArray(),
                    'module' => 'regions',
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'IVA users assigned to region successfully',
                'assigned_users' => $assignedUsers,
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign IVA users to region',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove IVA users from a region
     */
    public function removeUsers(Request $request, $id)
    {
        // Check if user has permission to edit IVA data
        if (! $request->user()->can('edit_iva_data')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:iva_user,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $region = Region::findOrFail($id);
            $userIds = $request->user_ids;

            $removedUsers = IvaUser::whereIn('id', $userIds)
                ->where('region_id', $region->id)
                ->get(['id', 'full_name', 'email']);

            // Remove IVA users from region
            IvaUser::whereIn('id', $userIds)->update(['region_id' => null]);

            // Log the activity
            ActivityLogService::log(
                'region_remove_users',
                "Removed {$removedUsers->count()} IVA user(s) from region: {$region->name}",
                [
                    'region_id' => $region->id,
                    'region_name' => $region->name,
                    'user_ids' => $userIds,
                    'removed_users' => $removedUsers->toArray(),
                    'module' => 'regions',
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'IVA users removed from region successfully',
                'removed_users' => $removedUsers,
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove IVA users from region',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
