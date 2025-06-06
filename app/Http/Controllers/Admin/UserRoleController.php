<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class UserRoleController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('auth.jwt');
    // }

    /**
     * Display a listing of users with their roles
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if (! $user->can('manage_users')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $query = User::with(['roles', 'permissions']);

            // Search functionality
            if ($request->has('search') && ! empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Filter by role
            if ($request->has('role') && ! empty($request->role)) {
                $query->whereHas('roles', function ($q) use ($request) {
                    $q->where('name', $request->role);
                });
            }

            // Sorting
            $sortBy        = $request->get('sort_by', 'name');
            $sortDirection = $request->get('sort_direction', 'asc');
            $query->orderBy($sortBy, $sortDirection);

            $users = $query->paginate($request->get('per_page', 10));

            $usersData = $users->getCollection()->map(function ($user) {
                return [
                    'id'             => $user->id,
                    'name'           => $user->name,
                    'email'          => $user->email,
                    'avatar'         => $user->avatar ? asset('storage/' . $user->avatar) : null,
                    'roles'          => $user->roles->map(function ($role) {
                        return [
                            'name'         => $role->name,
                            'display_name' => strtoupper($role->display_name ?? $role->name),
                        ];
                    }),
                    'permissions'    => $user->getAllPermissions()->map(function ($permission) {
                        $displayName = config("constants.permissions.{$permission->name}", $permission->name);
                        return [
                            'name'         => $permission->name,
                            'display_name' => $displayName, //$permission->display_name ?? $permission->name,
                        ];
                    }),
                    'is_super_admin' => $user->isSuperAdmin(),
                    'created_at'     => $user->created_at,
                ];
            });

            return response()->json([
                'users'      => $usersData,
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'per_page'     => $users->perPage(),
                    'total'        => $users->total(),
                    'last_page'    => $users->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch users: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Assign role to user
     */
    public function assignRole(Request $request, User $targetUser)
    {
        try {
            $user = $request->user();

            if (! $user->can('manage_users')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Prevent modifying super admin user
            if ($targetUser->isSuperAdmin() && ! $user->isSuperAdmin()) {
                return response()->json(['error' => 'Cannot modify super admin user'], 403);
            }

            $request->validate([
                'role' => 'required|exists:roles,name',
            ]);

            $role = Role::where('name', $request->role)->first();
            $targetUser->assignRole($role);

            // Log activity
            ActivityLogService::logRoleAssignment($targetUser, $role->name, 'assign');

            return response()->json([
                'message' => 'Role assigned successfully',
                'user'    => [
                    'id'    => $targetUser->id,
                    'name'  => $targetUser->name,
                    'email' => $targetUser->email,
                    'roles' => $targetUser->fresh()->roles->map(function ($role) {
                        return [
                            'name'         => $role->name,
                            'display_name' => strtoupper($role->display_name ?? $role->name),
                        ];
                    }),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to assign role: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove role from user
     */
    public function removeRole(Request $request, User $targetUser)
    {
        try {
            $user = $request->user();

            if (! $user->can('manage_users')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Prevent modifying super admin user
            if ($targetUser->isSuperAdmin()) {
                return response()->json(['error' => 'Cannot modify super admin user'], 403);
            }

            $request->validate([
                'role' => 'required|exists:roles,name',
            ]);

            $role = Role::where('name', $request->role)->first();
            $targetUser->removeRole($role);

            // Log activity
            ActivityLogService::logRoleAssignment($targetUser, $role->name, 'unassign');

            return response()->json([
                'message' => 'Role removed successfully',
                'user'    => [
                    'id'    => $targetUser->id,
                    'name'  => $targetUser->name,
                    'email' => $targetUser->email,
                    'roles' => $targetUser->fresh()->roles->map(function ($role) {
                        return [
                            'name'         => $role->name,
                            'display_name' => strtoupper($role->display_name ?? $role->name),
                        ];
                    }),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to remove role: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Sync user roles
     */
    public function syncRoles(Request $request, User $targetUser)
    {
        try {
            $user = $request->user();

            if (! $user->can('manage_users')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Prevent modifying super admin user
            if ($targetUser->isSuperAdmin() && ! $user->isSuperAdmin()) {
                return response()->json(['error' => 'Cannot modify super admin user'], 403);
            }

            $request->validate([
                'roles'   => 'required|array',
                'roles.*' => 'exists:roles,name',
            ]);

            $oldRoles = $targetUser->roles->pluck('name')->toArray();
            $targetUser->syncRoles($request->roles);

            // Log activity for each role change
            foreach ($oldRoles as $oldRole) {
                if (! in_array($oldRole, $request->roles)) {
                    ActivityLogService::logRoleAssignment($targetUser, $oldRole, 'unassign');
                }
            }

            foreach ($request->roles as $newRole) {
                if (! in_array($newRole, $oldRoles)) {
                    ActivityLogService::logRoleAssignment($targetUser, $newRole, 'assign');
                }
            }

            return response()->json([
                'message' => 'User roles updated successfully',
                'user'    => [
                    'id'          => $targetUser->id,
                    'name'        => $targetUser->name,
                    'email'       => $targetUser->email,
                    'roles'       => $targetUser->fresh()->roles->map(function ($role) {
                        return [
                            'name'         => $role->name,
                            'display_name' => strtoupper($role->display_name ?? $role->name),
                        ];
                    }),
                    'permissions' => $targetUser->fresh()->getAllPermissions()->map(function ($permission) {
                        $displayName = config("constants.permissions.{$permission->name}", $permission->name);
                        return [
                            'name'         => $permission->name,
                            'display_name' => $displayName, //$permission->display_name ?? $permission->name,
                        ];
                    }),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to sync roles: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get available roles
     */
    public function availableRoles(Request $request)
    {
        try {
            $user = $request->user();

            if (! $user->can('manage_users')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $roles = Role::all()->map(function ($role) {
                return [
                    'name'         => $role->name,
                    'display_name' => strtoupper($role->display_name ?? $role->name),
                ];
            });

            return response()->json([
                'roles' => $roles,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch roles: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get user details with roles and permissions
     */
    public function show(Request $request, User $targetUser)
    {
        try {
            $user = $request->user();

            if (! $user->can('manage_users')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            return response()->json([
                'user' => [
                    'id'             => $targetUser->id,
                    'name'           => $targetUser->name,
                    'email'          => $targetUser->email,
                    'avatar'         => $targetUser->avatar ? asset('storage/' . $targetUser->avatar) : null,
                    'roles'          => $targetUser->roles->map(function ($role) {
                        return [
                            'name'         => $role->name,
                            'display_name' => strtoupper($role->display_name ?? $role->name),
                        ];
                    }),
                    'permissions'    => $targetUser->getAllPermissions()->map(function ($permission) {
                        $displayName = config("constants.permissions.{$permission->name}", $permission->name);
                        return [
                            'name'         => $permission->name,
                            'display_name' => $displayName, //$permission->display_name ?? $permission->name,
                        ];
                    }),
                    'is_super_admin' => $targetUser->isSuperAdmin(),
                    'created_at'     => $targetUser->created_at,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch user: ' . $e->getMessage()], 500);
        }
    }
}