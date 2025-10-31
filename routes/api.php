<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CohortController;
use App\Http\Controllers\ConfigurationSettingsController;
use App\Http\Controllers\ConfigurationSettingTypeController;
use App\Http\Controllers\DailyWorklogSummaryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IvaDailyReportController;
use App\Http\Controllers\IvaManagerController;
use App\Http\Controllers\IvaNshReportController;
use App\Http\Controllers\IvaOverallReportController;
use App\Http\Controllers\IvaRegionReportController;
use App\Http\Controllers\IvaUserController;
use App\Http\Controllers\IvaUserTimeDoctorRecordsController;
use App\Http\Controllers\IvaWeeklyReportController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\ReportCategoryController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\TimeDoctorAuthController;
use App\Http\Controllers\TimeDoctorController;
use App\Http\Controllers\TimeDoctorLongOperationController;
use App\Http\Controllers\TimeDoctorV2AuthController;
use App\Http\Controllers\TimeDoctorV2Controller;
use App\Http\Controllers\TimeDoctorV2LongOperationController;
use App\Http\Controllers\WorklogDashboardController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth.jwt')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Dashboard routes
    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('/overview', [DashboardController::class, 'getDashboardOverview'])->name('overview');
        Route::post('/clear-cache', [DashboardController::class, 'clearDashboardCache'])->name('clear-cache');
    });

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

        // Cohort Management Routes (require manage_configuration permission)
        Route::prefix('cohorts')->name('cohorts.')->group(function () {
            Route::get('/', [CohortController::class, 'index']);
            Route::post('/', [CohortController::class, 'store']);
            Route::get('/available-users', [CohortController::class, 'getAvailableUsers']);
            Route::get('/{cohort}', [CohortController::class, 'show']);
            Route::put('/{cohort}', [CohortController::class, 'update']);
            Route::delete('/{cohort}', [CohortController::class, 'destroy']);
            Route::post('/{cohort}/assign-users', [CohortController::class, 'assignUsers']);
            Route::delete('/{cohort}/remove-users', [CohortController::class, 'removeUsers']);
        });

        // IVA User Management Routes (require manage_ivas permission)
        Route::prefix('iva-users')->name('iva-users.')->group(function () {
            Route::get('/', [IvaUserController::class, 'index']);
            Route::post('/', [IvaUserController::class, 'store']);
            // User sync functionality
            Route::post('/sync', [IvaUserController::class, 'syncUsers']);
            Route::get('/coo-email', [IvaUserController::class, 'getCooEmail']);

            Route::get('/{id}', [IvaUserController::class, 'show']);
            Route::put('/{id}', [IvaUserController::class, 'update']);

            // User customizations
            Route::post('/{id}/customizations', [IvaUserController::class, 'addCustomizations']);
            Route::put('/{id}/customizations/{customizationId}', [IvaUserController::class, 'updateCustomization']);
            Route::delete('/{id}/customizations/{customizationId}', [IvaUserController::class, 'removeCustomization']);

            // User managers
            Route::get('/{id}/available-managers', [IvaUserController::class, 'getAvailableManagers']);
            Route::post('/{id}/managers', [IvaUserController::class, 'addManager']);
            Route::delete('/{id}/managers/{managerId}', [IvaUserController::class, 'removeManager']);

            // User logs
            Route::get('/{id}/logs', [IvaUserController::class, 'getLogs']);
            Route::post('/{id}/logs', [IvaUserController::class, 'addLog']);
            Route::put('/{id}/logs/{logId}', [IvaUserController::class, 'updateLog']);
            Route::delete('/{id}/logs/{logId}', [IvaUserController::class, 'deleteLog']);

            // Time Doctor Records Management Routes
            Route::get('/{id}/timedoctor-records', [IvaUserTimeDoctorRecordsController::class, 'index']);
            Route::post('/{id}/timedoctor-records/sync', [IvaUserTimeDoctorRecordsController::class, 'syncTimeDoctorRecords']);
            Route::get('/{id}/timedoctor-records/daily-summaries', [IvaUserTimeDoctorRecordsController::class, 'getDailySummaries']);

            // Helper routes for dropdowns
            Route::get('/timedoctor-records/projects', [IvaUserTimeDoctorRecordsController::class, 'getProjects']);
            Route::get('/timedoctor-records/tasks', [IvaUserTimeDoctorRecordsController::class, 'getTasks']);

            // Working Hours Dashboard
            Route::get('/{id}/worklog-dashboard', [WorklogDashboardController::class, 'getDashboardData']);
            Route::get('/{id}/worklog-dashboard/tasks-by-category', [WorklogDashboardController::class, 'getTasksByCategory']);
        });

        // Daily Worklog Summary Calculation Routes
        Route::prefix('daily-worklog-summaries')->name('daily-worklog-summaries.')->group(function () {
            Route::get('/calculation-options', [DailyWorklogSummaryController::class, 'getCalculationOptions']);
            Route::post('/validate-calculation', [DailyWorklogSummaryController::class, 'validateCalculation']);
            Route::post('/start-calculation', [DailyWorklogSummaryController::class, 'startCalculation']);
            Route::get('/calculation-progress', [DailyWorklogSummaryController::class, 'getCalculationProgress']);
        });

        // IVA Manager Management Routes (require manage_ivas permission)
        Route::prefix('iva-managers')->name('iva-managers.')->group(function () {
            Route::get('/', [IvaManagerController::class, 'index']);
            Route::post('/', [IvaManagerController::class, 'store']);
            Route::get('/{id}', [IvaManagerController::class, 'show']);
            Route::delete('/{id}', [IvaManagerController::class, 'destroy']);
            Route::delete('/{id}/users', [IvaManagerController::class, 'removeUser']);
            Route::get('/{id}/available-users', [IvaManagerController::class, 'getAvailableUsers']);
            Route::post('/{id}/users', [IvaManagerController::class, 'addUsers']);
            Route::get('/regions/{regionId}', [IvaManagerController::class, 'getRegionData']);
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
        Route::get('/stream-worklog-sync-by-users', [TimeDoctorLongOperationController::class, 'streamWorklogSyncByUsers']);

        Route::post('/refresh', [TimeDoctorAuthController::class, 'refreshToken']);
    });

    // TimeDoctor V2 Integration Routes (require manage_configuration permission)
    Route::prefix('timedoctor-v2')->group(function () {
        // Authentication routes
        Route::post('/auth', [TimeDoctorV2AuthController::class, 'authenticate']);
        Route::get('/status', [TimeDoctorV2AuthController::class, 'checkToken']);
        Route::post('/refresh', [TimeDoctorV2AuthController::class, 'refreshToken']);
        Route::get('/disconnect', [TimeDoctorV2AuthController::class, 'disconnect']);
        Route::get('/company-info', [TimeDoctorV2AuthController::class, 'getCompanyInfo']);

        // Sync routes
        Route::post('/sync-users', [TimeDoctorV2Controller::class, 'syncUsers']);
        Route::post('/sync-projects', [TimeDoctorV2Controller::class, 'syncProjects']);
        Route::post('/sync-tasks', [TimeDoctorV2Controller::class, 'syncTasks']);
        Route::post('/sync-worklogs', [TimeDoctorV2Controller::class, 'syncWorklogs']);

        // Count routes
        Route::get('/users/count', [TimeDoctorV2Controller::class, 'getUserCount']);
        Route::get('/projects/count', [TimeDoctorV2Controller::class, 'getProjectCount']);
        Route::get('/tasks/count', [TimeDoctorV2Controller::class, 'getTaskCount']);
        Route::get('/worklogs/count', [TimeDoctorV2Controller::class, 'getWorklogCount']);

        // Long operation routes
        Route::get('/stream-worklog-sync', [TimeDoctorV2LongOperationController::class, 'streamWorklogSync']);
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

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/nsh-performance', [IvaNshReportController::class, 'getNshReport'])
            ->name('nsh-performance');
        Route::get('/daily-performance', [IvaDailyReportController::class, 'getDailyPerformanceReport'])
            ->name('daily-performance');
        Route::get('/weekly-performance', [IvaWeeklyReportController::class, 'getWeeklyPerformanceReport'])
            ->name('weekly-performance');
        Route::post('/weekly-performance/clear-cache', [IvaWeeklyReportController::class, 'clearWeeklyReportCache'])
            ->name('weekly-performance.clear-cache');

        Route::get('/region-performance', [IvaRegionReportController::class, 'getRegionPerformanceReport'])
            ->name('region-performance');
        Route::get('/region-performance/regions', [IvaRegionReportController::class, 'getAvailableRegions'])
            ->name('region-performance.regions');

        Route::get('/overall-performance', [IvaOverallReportController::class, 'getOverallPerformanceReport'])
            ->name('overall-performance');

        // Export routes
        Route::post('/export', [ReportExportController::class, 'exportReport'])
            ->name('export');
        Route::get('/available-regions', [ReportExportController::class, 'getAvailableRegions'])
            ->name('available-regions');
    });

});
