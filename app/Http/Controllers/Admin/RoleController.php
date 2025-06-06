<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{

    /**
     * Display a listing of roles
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if (! $user->can('manage_roles')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $query = Role::with('permissions');

            // Search functionality
            if ($request->has('search') && ! empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('display_name', 'like', "%{$search}%");
                });
            }

            // Sorting
            $sortBy        = $request->get('sort_by', 'name');
            $sortDirection = $request->get('sort_direction', 'asc');
            $query->orderBy($sortBy, $sortDirection);

            $roles = $query->paginate($request->get('per_page', 10));

            return response()->json([
                'roles'      => $roles->items(),
                'pagination' => [
                    'current_page' => $roles->currentPage(),
                    'per_page'     => $roles->perPage(),
                    'total'        => $roles->total(),
                    'last_page'    => $roles->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch roles: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created role
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user();

            if (! $user->can('manage_roles')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $request->validate([
                'name'          => 'required|string|max:255|unique:roles,name',
                'permissions'   => 'array',
                'permissions.*' => 'exists:permissions,name',
            ]);

            // Auto-generate display_name as uppercase
            $displayName = strtoupper(str_replace('_', ' ', $request->name));

            $role = Role::create([
                'name'         => $request->name,
                'display_name' => $displayName,
            ]);

            if ($request->has('permissions')) {
                $role->syncPermissions($request->permissions);
            }

            // Log activity
            ActivityLogService::logRoleManagement('create', $role->name, $request->permissions ?? []);

            return response()->json([
                'message' => 'Role created successfully',
                'role'    => $role->load('permissions'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create role: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified role
     */
    public function show(Request $request, Role $role)
    {
        try {
            $user = $request->user();

            if (! $user->can('manage_roles')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            return response()->json([
                'role' => $role->load('permissions'),
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch role: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified role
     */
    public function update(Request $request, Role $role)
    {
        try {
            $user = $request->user();

            if (! $user->can('manage_roles')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $request->validate([
                'name'          => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
                'permissions'   => 'array',
                'permissions.*' => 'exists:permissions,name',
            ]);

            // Auto-generate display_name as uppercase
            $displayName = strtoupper(str_replace('_', ' ', $request->name));

            $role->update([
                'name'         => $request->name,
                'display_name' => $displayName,
            ]);

            if ($request->has('permissions')) {
                $role->syncPermissions($request->permissions);
            }

            // Log activity
            ActivityLogService::logRoleManagement('update', $role->name, $request->permissions ?? []);

            return response()->json([
                'message' => 'Role updated successfully',
                'role'    => $role->fresh()->load('permissions'),
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update role: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified role
     */
    public function destroy(Request $request, Role $role)
    {
        try {
            $user = $request->user();

            if (! $user->can('manage_roles')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Prevent deletion of admin role
            if ($role->name === 'admin') {
                return response()->json(['error' => 'Cannot delete admin role'], 422);
            }

            // Check if any users have this role
            $usersWithRole = $role->users()->count();
            if ($usersWithRole > 0) {
                return response()->json([
                    'error' => "Cannot delete role '{$role->name}'. It is currently assigned to {$usersWithRole} user(s). Please remove the role from all users before deleting it.",
                ], 422);
            }

            $roleName    = $role->name;
            $permissions = $role->permissions->pluck('name')->toArray();

            $role->delete();

            // Log activity
            ActivityLogService::logRoleManagement('delete', $roleName, $permissions);

            return response()->json([
                'message' => 'Role deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete role: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get all permissions
     */
    public function permissions(Request $request)
    {
        try {
            $user = $request->user();

            if (! $user->can('manage_roles')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $permissionsConfig = config('constants.permissions');

            $permissions = Permission::all()->map(function ($permission) use ($permissionsConfig) {
                return [
                    'name'         => $permission->name,
                    'display_name' => $permissionsConfig[$permission->name] ?? $permission->name,
                ];
            });

            return response()->json([
                'permissions' => $permissions,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch permissions: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Assign permissions to role
     */
    public function assignPermissions(Request $request, Role $role)
    {
        try {
            $user = $request->user();

            if (! $user->can('manage_roles')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $request->validate([
                'permissions'   => 'required|array',
                'permissions.*' => 'exists:permissions,name',
            ]);

            $oldPermissions = $role->permissions->pluck('name')->toArray();
            $role->syncPermissions($request->permissions);

            // Log activity
            ActivityLogService::logPermissionAssignment($role->name, $request->permissions);

            return response()->json([
                'message' => 'Permissions assigned successfully',
                'role'    => $role->fresh()->load('permissions'),
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to assign permissions: ' . $e->getMessage()], 500);
        }
    }
}