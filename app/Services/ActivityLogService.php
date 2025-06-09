<?php
namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogService
{
    /**
     * Log user activity
     *
     * @param string $action
     * @param string|null $description
     * @param array $details
     * @param User|null $user
     * @return ActivityLog
     */
    public static function log(string $action, ?string $description = null, array $details = [], ?User $user = null): ActivityLog
    {
        $user = $user ?? request()->user(); //Auth::user();

        $actionLabel = config('constants.activity_log_actions.' . $action, ucfirst($action));

        $logData = [
            'action'       => $action,
            'action_label' => $actionLabel,
            'timestamp'    => now()->toISOString(),
            'ip_address'   => request()->ip(),
            'user_agent'   => request()->userAgent(),
            'details'      => $details,
        ];

        $encodedLog = json_encode($logData, JSON_UNESCAPED_UNICODE);
        $maxBytes   = 60000; // Safe buffer below MySQL TEXT limit

        if (strlen($encodedLog) > $maxBytes) {
            // Replace `details` with summary/truncation notice
            $logData['details'] = [
                'truncated'     => true,
                'reason'        => 'Log data too large',
                'size'          => strlen($encodedLog),
                'original_keys' => array_keys($details),
            ];

            // Re-encode the reduced version
            $encodedLog = json_encode($logData, JSON_UNESCAPED_UNICODE);
        }

        return ActivityLog::create([
            'user_id'     => $user?->id,
            'email'       => $user?->email,
            'action'      => $action,
            'description' => $description ?? $actionLabel,
            'detail_log'  => $encodedLog,
        ]);
    }

    /**
     * Log role assignment
     */
    public static function logRoleAssignment(User $targetUser, string $roleName, string $action = 'assign'): ActivityLog
    {
        $description = $action === 'assign'
        ? "Assigned role '{$roleName}' to user {$targetUser->name}"
        : "Removed role '{$roleName}' from user {$targetUser->name}";

        return self::log($action, $description, [
            'target_user_id'    => $targetUser->id,
            'target_user_email' => $targetUser->email,
            'target_user_name'  => $targetUser->name,
            'role_name'         => $roleName,
            'module'            => 'user_roles',
        ]);
    }

    /**
     * Log role creation/update/deletion
     */
    public static function logRoleManagement(string $action, string $roleName, array $permissions = []): ActivityLog
    {
        $description = match ($action) {
            'create' => "Created role '{$roleName}'",
            'update' => "Updated role '{$roleName}'",
            'delete' => "Deleted role '{$roleName}'",
            default => "Managed role '{$roleName}'"
        };

        return self::log($action, $description, [
            'role_name'   => $roleName,
            'permissions' => $permissions,
            'module'      => 'roles',
        ]);
    }

    /**
     * Log permission assignment to role
     */
    public static function logPermissionAssignment(string $roleName, array $permissions, string $action = 'assign'): ActivityLog
    {
        $description = $action === 'assign'
        ? "Assigned permissions to role '{$roleName}'"
        : "Removed permissions from role '{$roleName}'";

        return self::log($action, $description, [
            'role_name'   => $roleName,
            'permissions' => $permissions,
            'module'      => 'role_permissions',
        ]);
    }

    /**
     * Log data import
     */
    public static function logDataImport(string $type, array $details = []): ActivityLog
    {
        $description = "Imported {$type} data";

        return self::log('import', $description, array_merge([
            'import_type' => $type,
            'module'      => 'data_import',
        ], $details));
    }

    /**
     * Log data export
     */
    public static function logDataExport(string $type, array $details = []): ActivityLog
    {
        $description = "Exported {$type} data";

        return self::log('export', $description, array_merge([
            'export_type' => $type,
            'module'      => 'data_export',
        ], $details));
    }

    /**
     * Log configuration changes
     */
    public static function logConfigurationChange(string $action, string $configKey, $oldValue = null, $newValue = null): ActivityLog
    {
        $description = match ($action) {
            'create' => "Created configuration '{$configKey}'",
            'update' => "Updated configuration '{$configKey}'",
            'delete' => "Deleted configuration '{$configKey}'",
            default => "Modified configuration '{$configKey}'"
        };

        return self::log($action, $description, [
            'config_key' => $configKey,
            'old_value'  => $oldValue,
            'new_value'  => $newValue,
            'module'     => 'configuration',
        ]);
    }

    /**
     * Log configuration setting creation
     */
    public static function logConfigurationCreate($setting, array $additionalDetails = []): ActivityLog
    {
        $settingType = $setting->settingType ?? null;
        $description = "Created configuration setting: {$setting->setting_value}";

        if ($settingType) {
            $description .= " (Type: {$settingType->name})";
        }

        return self::log('create_config', $description, array_merge([
            'setting_id'      => $setting->id,
            'setting_value'   => $setting->setting_value,
            'setting_type_id' => $setting->setting_type_id,
            'setting_type'    => $settingType?->name,
            'description'     => $setting->description,
            'is_active'       => $setting->is_active,
            'order'           => $setting->order,
            'module'          => 'configuration_settings',
        ], $additionalDetails));
    }

    /**
     * Log configuration setting update
     */
    public static function logConfigurationUpdate($setting, array $oldValues = [], array $additionalDetails = []): ActivityLog
    {
        $settingType = $setting->settingType ?? null;
        $description = "Updated configuration setting: {$setting->setting_value}";

        if ($settingType) {
            $description .= " (Type: {$settingType->name})";
        }

        return self::log('update_config', $description, array_merge([
            'setting_id'      => $setting->id,
            'setting_value'   => $setting->setting_value,
            'setting_type_id' => $setting->setting_type_id,
            'setting_type'    => $settingType?->name,
            'description'     => $setting->description,
            'is_active'       => $setting->is_active,
            'order'           => $setting->order,
            'old_values'      => $oldValues,
            'module'          => 'configuration_settings',
        ], $additionalDetails));
    }

    /**
     * Log configuration setting deletion
     */
    public static function logConfigurationDelete($setting, array $additionalDetails = []): ActivityLog
    {
        $settingType = $setting->settingType ?? null;
        $description = "Deleted configuration setting: {$setting->setting_value}";

        if ($settingType) {
            $description .= " (Type: {$settingType->name})";
        }

        return self::log('delete_config', $description, array_merge([
            'setting_id'      => $setting->id,
            'setting_value'   => $setting->setting_value,
            'setting_type_id' => $setting->setting_type_id,
            'setting_type'    => $settingType?->name,
            'description'     => $setting->description,
            'is_active'       => $setting->is_active,
            'order'           => $setting->order,
            'module'          => 'configuration_settings',
        ], $additionalDetails));
    }

    /**
     * Log configuration setting status toggle
     */
    public static function logConfigurationStatusToggle($setting, bool $oldStatus, array $additionalDetails = []): ActivityLog
    {
        $settingType   = $setting->settingType ?? null;
        $newStatus     = $setting->is_active ? 'Active' : 'Inactive';
        $oldStatusText = $oldStatus ? 'Active' : 'Inactive';

        $description = "Changed status of configuration setting '{$setting->setting_value}' from {$oldStatusText} to {$newStatus}";

        if ($settingType) {
            $description .= " (Type: {$settingType->name})";
        }

        return self::log('toggle_status', $description, array_merge([
            'setting_id'      => $setting->id,
            'setting_value'   => $setting->setting_value,
            'setting_type_id' => $setting->setting_type_id,
            'setting_type'    => $settingType?->name,
            'old_status'      => $oldStatus,
            'new_status'      => $setting->is_active,
            'module'          => 'configuration_settings',
        ], $additionalDetails));
    }

    /**
     * Log configuration type creation
     */
    public static function logConfigurationTypeCreate($settingType, array $additionalDetails = []): ActivityLog
    {
        $description = "Created configuration type: {$settingType->name} (Key: {$settingType->key})";

        return self::log('create_config_type', $description, array_merge([
            'type_id'            => $settingType->id,
            'type_key'           => $settingType->key,
            'type_name'          => $settingType->name,
            'setting_category'   => $settingType->setting_category,
            'description'        => $settingType->description,
            'for_user_customize' => $settingType->for_user_customize,
            'allow_edit'         => $settingType->allow_edit,
            'allow_delete'       => $settingType->allow_delete,
            'allow_create'       => $settingType->allow_create,
            'module'             => 'configuration_types',
        ], $additionalDetails));
    }

    /**
     * Log configuration type update
     */
    public static function logConfigurationTypeUpdate($settingType, array $oldValues = [], array $additionalDetails = []): ActivityLog
    {
        $description = "Updated configuration type: {$settingType->name} (Key: {$settingType->key})";

        return self::log('update_config_type', $description, array_merge([
            'type_id'            => $settingType->id,
            'type_key'           => $settingType->key,
            'type_name'          => $settingType->name,
            'setting_category'   => $settingType->setting_category,
            'description'        => $settingType->description,
            'for_user_customize' => $settingType->for_user_customize,
            'allow_edit'         => $settingType->allow_edit,
            'allow_delete'       => $settingType->allow_delete,
            'allow_create'       => $settingType->allow_create,
            'old_values'         => $oldValues,
            'module'             => 'configuration_types',
        ], $additionalDetails));
    }

    /**
     * Log configuration type deletion
     */
    public static function logConfigurationTypeDelete($settingType, array $additionalDetails = []): ActivityLog
    {
        $description = "Deleted configuration type: {$settingType->name} (Key: {$settingType->key})";

        return self::log('delete_config_type', $description, array_merge([
            'type_id'            => $settingType->id,
            'type_key'           => $settingType->key,
            'type_name'          => $settingType->name,
            'setting_category'   => $settingType->setting_category,
            'description'        => $settingType->description,
            'for_user_customize' => $settingType->for_user_customize,
            'allow_edit'         => $settingType->allow_edit,
            'allow_delete'       => $settingType->allow_delete,
            'allow_create'       => $settingType->allow_create,
            'module'             => 'configuration_types',
        ], $additionalDetails));
    }

    /**
     * Log task category creation
     */
    public static function logCategoryCreate($category, array $additionalDetails = []): ActivityLog
    {
        $categoryType = $category->categoryType ?? null;
        $description  = "Created task category: {$category->cat_name}";

        if ($categoryType) {
            $description .= " (Type: {$categoryType->setting_value})";
        }

        return self::log('create_category', $description, array_merge([
            'category_id'      => $category->id,
            'category_name'    => $category->cat_name,
            'category_type_id' => $category->category_type,
            'category_type'    => $categoryType?->setting_value,
            'description'      => $category->cat_description,
            'is_active'        => $category->is_active,
            'category_order'   => $category->category_order,
            'module'           => 'task_categories',
        ], $additionalDetails));
    }

    /**
     * Log task category update
     */
    public static function logCategoryUpdate($category, array $oldValues = [], array $additionalDetails = []): ActivityLog
    {
        $categoryType = $category->categoryType ?? null;
        $description  = "Updated task category: {$category->cat_name}";

        if ($categoryType) {
            $description .= " (Type: {$categoryType->setting_value})";
        }

        return self::log('update_category', $description, array_merge([
            'category_id'      => $category->id,
            'category_name'    => $category->cat_name,
            'category_type_id' => $category->category_type,
            'category_type'    => $categoryType?->setting_value,
            'description'      => $category->cat_description,
            'is_active'        => $category->is_active,
            'category_order'   => $category->category_order,
            'old_values'       => $oldValues,
            'module'           => 'task_categories',
        ], $additionalDetails));
    }

    /**
     * Log task category deletion
     */
    public static function logCategoryDelete($category, array $additionalDetails = []): ActivityLog
    {
        $categoryType = $category->categoryType ?? null;
        $description  = "Deleted task category: {$category->cat_name}";

        if ($categoryType) {
            $description .= " (Type: {$categoryType->setting_value})";
        }

        return self::log('delete_category', $description, array_merge([
            'category_id'      => $category->id,
            'category_name'    => $category->cat_name,
            'category_type_id' => $category->category_type,
            'category_type'    => $categoryType?->setting_value,
            'description'      => $category->cat_description,
            'is_active'        => $category->is_active,
            'category_order'   => $category->category_order,
            'module'           => 'task_categories',
        ], $additionalDetails));
    }
}
