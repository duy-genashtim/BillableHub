<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConfigurationSettingsController;
use App\Http\Controllers\ConfigurationSettingTypeController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\ReportCategoryController;
use App\Http\Controllers\TimeDoctorAuthController;
use App\Http\Controllers\TimeDoctorController;
use App\Http\Controllers\TimeDoctorLongOperationController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth.jwt')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Admin routes
    Route::prefix('admin')->group(function () {

        // Role management
        Route::get('roles', [RoleController::class, 'index']);
        Route::post('roles', [RoleController::class, 'store']);
        Route::get('roles/{role}', [RoleController::class, 'show']);
        Route::delete('roles/{role}', [RoleController::class, 'destroy']);

        Route::get('roles/{role}/permissions', [RoleController::class, 'permissions']);
        Route::post('roles/{role}/assign-permissions', [RoleController::class, 'assignPermissions']);
        Route::get('permissions', [RoleController::class, 'permissions']);

        // User role management
        Route::get('users', [UserRoleController::class, 'index']);
        Route::get('users/{targetUser}', [UserRoleController::class, 'show']);
        Route::post('users/{targetUser}/assign-role', [UserRoleController::class, 'assignRole']);
        Route::delete('users/{targetUser}/remove-role', [UserRoleController::class, 'removeRole']);
        Route::put('users/{targetUser}/sync-roles', [UserRoleController::class, 'syncRoles']);
        Route::get('available-roles', [UserRoleController::class, 'availableRoles']);

        // Activity logs
        Route::get('activity-logs', [ActivityLogController::class, 'index']);
        Route::get('activity-logs/{activityLog}', [ActivityLogController::class, 'show']);
        Route::get('activity-logs-export', [ActivityLogController::class, 'export']);
        Route::get('activity-logs-filter-options', [ActivityLogController::class, 'filterOptions']);

        // Region Management Routes (require manage_configuration permission)
        Route::prefix('regions')->name('regions.')->group(function () {
            Route::get('/', [RegionController::class, 'index']);
            Route::post('/', [RegionController::class, 'store']);
            Route::get('/available-users', [RegionController::class, 'getAvailableUsers']);
            Route::get('/{region}', [RegionController::class, 'show']);
            Route::put('/{region}', [RegionController::class, 'update']);
            Route::delete('/{region}', [RegionController::class, 'destroy']);
            Route::post('/{region}/assign-users', [RegionController::class, 'assignUsers']);
            Route::delete('/{region}/remove-users', [RegionController::class, 'removeUsers']);
        });
    });

    // Configuration Settings Routes
    Route::prefix('configuration')->name('configuration.')->group(function () {
        Route::get('/', [ConfigurationSettingsController::class, 'index'])->name('index');
        Route::post('/', [ConfigurationSettingsController::class, 'store'])->name('store');
        Route::get('/types', [ConfigurationSettingsController::class, 'getTypes'])->name('types');
        Route::get('/logs', [ConfigurationSettingsController::class, 'getActivityLogs'])->name('logs');
        Route::get('/{id}', [ConfigurationSettingsController::class, 'show'])->name('show');
        Route::put('/{id}', [ConfigurationSettingsController::class, 'update'])->name('update');
        Route::put('/{id}/toggle-status', [ConfigurationSettingsController::class, 'toggleStatus'])->name('toggle-status');
        Route::delete('/{id}', [ConfigurationSettingsController::class, 'destroy'])->name('destroy');
    });

    // Configuration Setting Types Routes
    Route::prefix('configuration/types')->name('configuration.types.')->group(function () {
        Route::get('/', [ConfigurationSettingTypeController::class, 'index'])->name('index');
        Route::post('/', [ConfigurationSettingTypeController::class, 'store'])->name('store');
        Route::get('/{id}', [ConfigurationSettingTypeController::class, 'show'])->name('show');
        Route::put('/{id}', [ConfigurationSettingTypeController::class, 'update'])->name('update');
        Route::delete('/{id}', [ConfigurationSettingTypeController::class, 'destroy'])->name('destroy');
    });

    // TimeDoctor Integration Routes (require manage_configuration permission)
    Route::prefix('timedoctor')->group(function () {
        // Authentication routes
        Route::get('/auth', [TimeDoctorAuthController::class, 'redirect']);
        Route::get('/callback', [TimeDoctorAuthController::class, 'callback']);
        Route::get('/status', [TimeDoctorAuthController::class, 'checkToken']);
        Route::get('/disconnect', [TimeDoctorAuthController::class, 'disconnect']);
        Route::get('/company-info', [TimeDoctorAuthController::class, 'getCompanyInfo']);

        // Sync routes
        Route::post('/sync-users', [TimeDoctorController::class, 'syncUsers']);
        Route::post('/sync-projects', [TimeDoctorController::class, 'syncProjects']);
        Route::post('/sync-tasks', [TimeDoctorController::class, 'syncTasks']);
        Route::post('/sync-worklogs', [TimeDoctorController::class, 'syncWorklogs']);

        // Count routes
        Route::get('/users/count', [TimeDoctorController::class, 'getUserCount']);
        Route::get('/projects/count', [TimeDoctorController::class, 'getProjectCount']);
        Route::get('/tasks/count', [TimeDoctorController::class, 'getTaskCount']);
        Route::get('/worklogs/count', [TimeDoctorController::class, 'getWorklogCount']);

        // Long operation routes
        Route::get('/stream-worklog-sync', [TimeDoctorLongOperationController::class, 'streamWorklogSync']);
    });

    // Report Categories Management
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/', [ReportCategoryController::class, 'index'])->name('index');
        Route::post('/', [ReportCategoryController::class, 'store'])->name('store');
        Route::get('/types', [ReportCategoryController::class, 'getCategoryTypes'])->name('types');
        Route::get('/tasks/available', [ReportCategoryController::class, 'getAvailableTasks'])->name('tasks.available');
        Route::get('/{id}', [ReportCategoryController::class, 'show'])->name('show');
        Route::put('/{id}', [ReportCategoryController::class, 'update'])->name('update');
        Route::delete('/{id}', [ReportCategoryController::class, 'destroy'])->name('destroy');
        Route::patch('/{id}/status', [ReportCategoryController::class, 'toggleStatus'])->name('toggle-status');
        Route::post('/{id}/tasks', [ReportCategoryController::class, 'assignTasks'])->name('assign-tasks');
        Route::delete('/{id}/tasks', [ReportCategoryController::class, 'removeTasks'])->name('remove-tasks');
    });
});
