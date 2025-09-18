<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = config('constants.permissions');
        $roles = config('constants.roles');
        $rolePermissions = config('constants.role_permissions');

        // Create permissions
        foreach ($permissions as $key => $name) {
            Permission::firstOrCreate([
                'name' => $key,
                'guard_name' => 'web',
            ]);
        }

        // Create roles and assign permissions
        foreach ($roles as $roleKey => $roleName) {
            $role = Role::firstOrCreate([
                'name' => $roleKey,
                'guard_name' => 'web',
            ]);

            // Assign permissions to role
            if (isset($rolePermissions[$roleKey])) {
                $permissions = $rolePermissions[$roleKey];
                $role->syncPermissions($permissions);
            }
        }

        // Assign admin role to user ID 1 (Super Admin)
        $superAdmin = User::find(1);
        if ($superAdmin) {
            $superAdmin->assignRole('admin');
            echo "Super Admin role assigned to user ID 1: {$superAdmin->email}\n";
        } else {
            echo "User ID 1 not found. Super Admin role not assigned.\n";
        }
    }
}
