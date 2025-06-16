<?php
namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\IvaUser;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BatchController extends Controller
{
    /**
     * Display a listing of batches
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', config('constants.pagination.default_per_page', 20));
            $perPage = min($perPage, config('constants.pagination.max_per_page', 100));

            $query = Batch::withCount('ivaUsers');

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
            $sortBy    = $request->get('sort_by', 'batch_order');
            $sortOrder = $request->get('sort_order', 'asc');

            if (in_array($sortBy, ['id', 'name', 'batch_order', 'is_active', 'start_date', 'created_at'])) {
                $query->withoutGlobalScope('ordered')->orderBy($sortBy, $sortOrder);
            }

            $batches = $query->paginate($perPage);

            return response()->json([
                'success'    => true,
                'batches'    => $batches->items(),
                'pagination' => [
                    'current_page' => $batches->currentPage(),
                    'last_page'    => $batches->lastPage(),
                    'per_page'     => $batches->perPage(),
                    'total'        => $batches->total(),
                    'from'         => $batches->firstItem(),
                    'to'           => $batches->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch batches',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created batch
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255|unique:batches,name',
            'description' => 'nullable|string|max:1000',
            'batch_order' => 'nullable|integer|min:1',
            'start_date'  => 'nullable|date',
            'is_active'   => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $batchData                = $request->only(['name', 'description', 'start_date', 'is_active']);
            $batchData['batch_order'] = $request->batch_order ?? Batch::getNextOrder();

            $batch = Batch::create($batchData);

            // Log the activity
            ActivityLogService::log(
                'batch_create',
                "Created batch: {$batch->name}",
                [
                    'batch_id'   => $batch->id,
                    'batch_name' => $batch->name,
                    'batch_data' => $batchData,
                    'module'     => 'batches',
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Batch created successfully',
                'batch'   => $batch,
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create batch',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified batch
     */
    public function show($id)
    {
        try {
            $batch = Batch::with(['ivaUsers' => function ($query) {
                $query->select('id', 'full_name', 'email', 'batch_id', 'is_active', 'timedoctor_version', 'work_status', 'hire_date', 'created_at');
            }])->findOrFail($id);

            return response()->json([
                'success' => true,
                'batch'   => $batch,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found',
                'error'   => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update the specified batch
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255|unique:batches,name,' . $id,
            'description' => 'nullable|string|max:1000',
            'batch_order' => 'nullable|integer|min:1',
            'start_date'  => 'nullable|date',
            'is_active'   => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $batch   = Batch::findOrFail($id);
            $oldData = $batch->toArray();

            $batchData = $request->only(['name', 'description', 'batch_order', 'start_date', 'is_active']);
            $batch->update($batchData);

            // Log the activity
            ActivityLogService::log(
                'batch_update',
                "Updated batch: {$batch->name}",
                [
                    'batch_id'   => $batch->id,
                    'batch_name' => $batch->name,
                    'old_data'   => $oldData,
                    'new_data'   => $batch->fresh()->toArray(),
                    'module'     => 'batches',
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Batch updated successfully',
                'batch'   => $batch,
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update batch',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified batch (soft delete by marking inactive)
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $batch      = Batch::findOrFail($id);
            $batchName  = $batch->name;
            $usersCount = $batch->ivaUsers()->count();

            // Check if batch has IVA users
            if ($usersCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete batch '{$batchName}' because it has {$usersCount} assigned IVA user(s). Please reassign users first.",
                ], 400);
            }

            $batch->update(['is_active' => false]);

            // Log the activity
            ActivityLogService::log(
                'batch_deactivate',
                "Deactivated batch: {$batchName}",
                [
                    'batch_id'   => $batch->id,
                    'batch_name' => $batchName,
                    'module'     => 'batches',
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Batch deactivated successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate batch',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get IVA users available for batch assignment
     */
    public function getAvailableUsers()
    {
        try {
            $users = IvaUser::where('is_active', true)
                ->whereNull('batch_id')
                ->select('id', 'full_name', 'email', 'timedoctor_version', 'work_status', 'hire_date', 'created_at')
                ->orderBy('full_name')
                ->get();

            return response()->json([
                'success' => true,
                'users'   => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch available users',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign IVA users to a batch
     */
    public function assignUsers(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'exists:iva_user,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $batch   = Batch::findOrFail($id);
            $userIds = $request->user_ids;

            // Update IVA users' batch
            IvaUser::whereIn('id', $userIds)->update(['batch_id' => $batch->id]);

            $assignedUsers = IvaUser::whereIn('id', $userIds)->get(['id', 'full_name', 'email']);

            // Log the activity
            ActivityLogService::log(
                'batch_assign_users',
                "Assigned {$assignedUsers->count()} IVA user(s) to batch: {$batch->name}",
                [
                    'batch_id'       => $batch->id,
                    'batch_name'     => $batch->name,
                    'user_ids'       => $userIds,
                    'assigned_users' => $assignedUsers->toArray(),
                    'module'         => 'batches',
                ]
            );

            DB::commit();

            return response()->json([
                'success'        => true,
                'message'        => 'IVA users assigned to batch successfully',
                'assigned_users' => $assignedUsers,
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign IVA users to batch',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove IVA users from a batch
     */
    public function removeUsers(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'exists:iva_user,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $batch   = Batch::findOrFail($id);
            $userIds = $request->user_ids;

            $removedUsers = IvaUser::whereIn('id', $userIds)
                ->where('batch_id', $batch->id)
                ->get(['id', 'full_name', 'email']);

            // Remove IVA users from batch
            IvaUser::whereIn('id', $userIds)->update(['batch_id' => null]);

            // Log the activity
            ActivityLogService::log(
                'batch_remove_users',
                "Removed {$removedUsers->count()} IVA user(s) from batch: {$batch->name}",
                [
                    'batch_id'      => $batch->id,
                    'batch_name'    => $batch->name,
                    'user_ids'      => $userIds,
                    'removed_users' => $removedUsers->toArray(),
                    'module'        => 'batches',
                ]
            );

            DB::commit();

            return response()->json([
                'success'       => true,
                'message'       => 'IVA users removed from batch successfully',
                'removed_users' => $removedUsers,
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove IVA users from batch',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}