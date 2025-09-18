<?php

namespace App\Http\Controllers;

use App\Models\Cohort;
use App\Models\IvaUser;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CohortController extends Controller
{
    /**
     * Display a listing of cohorts
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', config('constants.pagination.default_per_page', 20));
            $perPage = min($perPage, config('constants.pagination.max_per_page', 100));

            $query = Cohort::withCount('ivaUsers');

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
            $sortBy = $request->get('sort_by', 'cohort_order');
            $sortOrder = $request->get('sort_order', 'asc');

            if (in_array($sortBy, ['id', 'name', 'cohort_order', 'is_active', 'start_date', 'created_at'])) {
                $query->withoutGlobalScope('ordered')->orderBy($sortBy, $sortOrder);
            }

            $cohorts = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'cohorts' => $cohorts->items(),
                'pagination' => [
                    'current_page' => $cohorts->currentPage(),
                    'last_page' => $cohorts->lastPage(),
                    'per_page' => $cohorts->perPage(),
                    'total' => $cohorts->total(),
                    'from' => $cohorts->firstItem(),
                    'to' => $cohorts->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch cohorts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created cohort
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:cohorts,name',
            'description' => 'nullable|string|max:1000',
            'cohort_order' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',
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
            $cohortData = $request->only(['name', 'description', 'start_date', 'is_active']);
            $cohortData['cohort_order'] = $request->cohort_order ?? Cohort::getNextOrder();

            $cohort = Cohort::create($cohortData);

            // Log the activity
            ActivityLogService::log(
                'cohort_create',
                "Created cohort: {$cohort->name}",
                [
                    'cohort_id' => $cohort->id,
                    'cohort_name' => $cohort->name,
                    'cohort_data' => $cohortData,
                    'module' => 'cohorts',
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cohort created successfully',
                'cohort' => $cohort,
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create cohort',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified cohort
     */
    public function show($id)
    {
        try {
            $cohort = Cohort::with(['ivaUsers' => function ($query) {
                $query->select('id', 'full_name', 'email', 'cohort_id', 'is_active', 'timedoctor_version', 'work_status', 'hire_date', 'created_at');
            }])->findOrFail($id);

            return response()->json([
                'success' => true,
                'cohort' => $cohort,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cohort not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update the specified cohort
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:cohorts,name,'.$id,
            'description' => 'nullable|string|max:1000',
            'cohort_order' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',
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
            $cohort = Cohort::findOrFail($id);
            $oldData = $cohort->toArray();

            $cohortData = $request->only(['name', 'description', 'cohort_order', 'start_date', 'is_active']);
            $cohort->update($cohortData);

            // Log the activity
            ActivityLogService::log(
                'cohort_update',
                "Updated cohort: {$cohort->name}",
                [
                    'cohort_id' => $cohort->id,
                    'cohort_name' => $cohort->name,
                    'old_data' => $oldData,
                    'new_data' => $cohort->fresh()->toArray(),
                    'module' => 'cohorts',
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cohort updated successfully',
                'cohort' => $cohort,
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update cohort',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified cohort (soft delete by marking inactive)
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $cohort = Cohort::findOrFail($id);
            $cohortName = $cohort->name;
            $usersCount = $cohort->ivaUsers()->count();

            // Check if cohort has IVA users
            if ($usersCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete cohort '{$cohortName}' because it has {$usersCount} assigned IVA user(s). Please reassign users first.",
                ], 400);
            }

            $cohort->update(['is_active' => false]);

            // Log the activity
            ActivityLogService::log(
                'cohort_deactivate',
                "Deactivated cohort: {$cohortName}",
                [
                    'cohort_id' => $cohort->id,
                    'cohort_name' => $cohortName,
                    'module' => 'cohorts',
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cohort deactivated successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate cohort',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get IVA users available for cohort assignment
     */
    public function getAvailableUsers()
    {
        try {
            $users = IvaUser::where('is_active', true)
                ->whereNull('cohort_id')
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
     * Assign IVA users to a cohort
     */
    public function assignUsers(Request $request, $id)
    {
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
            $cohort = Cohort::findOrFail($id);
            $userIds = $request->user_ids;

            // Update IVA users' cohort
            IvaUser::whereIn('id', $userIds)->update(['cohort_id' => $cohort->id]);

            $assignedUsers = IvaUser::whereIn('id', $userIds)->get(['id', 'full_name', 'email']);

            // Log the activity
            ActivityLogService::log(
                'cohort_assign_users',
                "Assigned {$assignedUsers->count()} IVA user(s) to cohort: {$cohort->name}",
                [
                    'cohort_id' => $cohort->id,
                    'cohort_name' => $cohort->name,
                    'user_ids' => $userIds,
                    'assigned_users' => $assignedUsers->toArray(),
                    'module' => 'cohorts',
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'IVA users assigned to cohort successfully',
                'assigned_users' => $assignedUsers,
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign IVA users to cohort',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove IVA users from a cohort
     */
    public function removeUsers(Request $request, $id)
    {
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
            $cohort = Cohort::findOrFail($id);
            $userIds = $request->user_ids;

            $removedUsers = IvaUser::whereIn('id', $userIds)
                ->where('cohort_id', $cohort->id)
                ->get(['id', 'full_name', 'email']);

            // Remove IVA users from cohort
            IvaUser::whereIn('id', $userIds)->update(['cohort_id' => null]);

            // Log the activity
            ActivityLogService::log(
                'cohort_remove_users',
                "Removed {$removedUsers->count()} IVA user(s) from cohort: {$cohort->name}",
                [
                    'cohort_id' => $cohort->id,
                    'cohort_name' => $cohort->name,
                    'user_ids' => $userIds,
                    'removed_users' => $removedUsers->toArray(),
                    'module' => 'cohorts',
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'IVA users removed from cohort successfully',
                'removed_users' => $removedUsers,
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove IVA users from cohort',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
