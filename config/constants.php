<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Activity Log Actions
    |--------------------------------------------------------------------------
    |
    | These constants define the action types for activity logging.
    | They are used throughout the application to maintain consistency
    | in logging user actions and system events.
    |
    */

    'activity_log_actions' => [
        // Configuration Management Actions
        'create_configuration'            => 'create_configuration',
        'update_configuration'            => 'update_configuration',
        'delete_configuration'            => 'delete_configuration',
        'toggle_configuration_status'     => 'toggle_configuration_status',
        'view_configuration'              => 'view_configuration',

        // Configuration Type Management Actions
        'create_configuration_type'       => 'create_configuration_type',
        'update_configuration_type'       => 'update_configuration_type',
        'delete_configuration_type'       => 'delete_configuration_type',
        'view_configuration_type'         => 'view_configuration_type',

        // User Management Actions
        'create_user'                     => 'create_user',
        'update_user'                     => 'update_user',
        'delete_user'                     => 'delete_user',
        'activate_user'                   => 'activate_user',
        'deactivate_user'                 => 'deactivate_user',
        'view_user'                       => 'view_user',
        'login'                           => 'login',
        'logout'                          => 'logout',

        // IVA User Management Actions
        'create_iva_user'                 => 'create_iva_user',
        'update_iva_user'                 => 'update_iva_user',
        'delete_iva_user'                 => 'delete_iva_user',
        'activate_iva_user'               => 'activate_iva_user',
        'deactivate_iva_user'             => 'deactivate_iva_user',
        'view_iva_user'                   => 'view_iva_user',
        'update_iva_user_customization'   => 'update_iva_user_customization',
        'remove_iva_user_customization'   => 'remove_iva_user_customization',
        'add_iva_user_manager'            => 'add_iva_user_manager',
        'remove_iva_user_manager'         => 'remove_iva_user_manager',

        // Region Management Actions
        'create_region'                   => 'create_region',
        'update_region'                   => 'update_region',
        'delete_region'                   => 'delete_region',
        'view_region'                     => 'view_region',
        'assign_user_to_region'           => 'assign_user_to_region',
        'remove_user_from_region'         => 'remove_user_from_region',

        // Category Management Actions
        'create_category'                 => 'create_category',
        'update_category'                 => 'update_category',
        'delete_category'                 => 'delete_category',
        'view_category'                   => 'view_category',
        'assign_task_to_category'         => 'assign_task_to_category',
        'remove_task_from_category'       => 'remove_task_from_category',

        // Manager Assignment Actions
        'create_manager_assignment'       => 'create_manager_assignment',
        'update_manager_assignment'       => 'update_manager_assignment',
        'delete_manager_assignment'       => 'delete_manager_assignment',
        'view_manager_assignment'         => 'view_manager_assignment',

        // Time Doctor Integration Actions
        'connect_timedoctor'              => 'connect_timedoctor',
        'disconnect_timedoctor'           => 'disconnect_timedoctor',
        'sync_timedoctor_users'           => 'sync_timedoctor_users',
        'sync_timedoctor_projects'        => 'sync_timedoctor_projects',
        'sync_timedoctor_tasks'           => 'sync_timedoctor_tasks',
        'sync_timedoctor_worklogs'        => 'sync_timedoctor_worklogs',

        // NEW: Time Doctor Records Management Actions
        'view_timedoctor_records'         => 'view_timedoctor_records',
        'create_timedoctor_record'        => 'create_timedoctor_record',
        'update_timedoctor_record'        => 'update_timedoctor_record',
        'delete_timedoctor_record'        => 'delete_timedoctor_record',
        'toggle_timedoctor_record_status' => 'toggle_timedoctor_record_status',
        'sync_timedoctor_records'         => 'sync_timedoctor_records',

        // Project Management Actions
        'create_project'                  => 'create_project',
        'update_project'                  => 'update_project',
        'delete_project'                  => 'delete_project',
        'view_project'                    => 'view_project',
        'activate_project'                => 'activate_project',
        'deactivate_project'              => 'deactivate_project',

        // Task Management Actions
        'create_task'                     => 'create_task',
        'update_task'                     => 'update_task',
        'delete_task'                     => 'delete_task',
        'view_task'                       => 'view_task',
        'activate_task'                   => 'activate_task',
        'deactivate_task'                 => 'deactivate_task',

        // Worklog Management Actions
        'create_worklog'                  => 'create_worklog',
        'update_worklog'                  => 'update_worklog',
        'delete_worklog'                  => 'delete_worklog',
        'view_worklog'                    => 'view_worklog',
        'activate_worklog'                => 'activate_worklog',
        'deactivate_worklog'              => 'deactivate_worklog',

        // Report and Dashboard Actions
        'view_dashboard'                  => 'view_dashboard',
        'view_worklog_dashboard'          => 'view_worklog_dashboard',
        'view_iva_report'                 => 'view_iva_report',
        'export_report'                   => 'export_report',
        'generate_report'                 => 'generate_report',

        // System Actions
        'system_backup'                   => 'system_backup',
        'system_restore'                  => 'system_restore',
        'system_maintenance'              => 'system_maintenance',
        'clear_cache'                     => 'clear_cache',
        'update_settings'                 => 'update_settings',

        // Security Actions
        'password_reset'                  => 'password_reset',
        'password_change'                 => 'password_change',
        'failed_login'                    => 'failed_login',
        'account_locked'                  => 'account_locked',
        'account_unlocked'                => 'account_unlocked',
        'permission_denied'               => 'permission_denied',

        // Data Import/Export Actions
        'import_data'                     => 'import_data',
        'export_data'                     => 'export_data',
        'bulk_update'                     => 'bulk_update',
        'bulk_delete'                     => 'bulk_delete',

        // API Actions
        'api_access'                      => 'api_access',
        'api_error'                       => 'api_error',
        'api_rate_limit'                  => 'api_rate_limit',
    ],

    /*
    |--------------------------------------------------------------------------
    | Time Doctor Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration constants for Time Doctor integration
    |
    */

    'timedoctor'           => [
        'api_versions'        => [
            'v1' => 1,
            'v2' => 2,
        ],

        'work_modes'          => [
            'automatic' => '0',
            'manual'    => '1',
            'idle'      => '2',
        ],

        'api_types'           => [
            'timedoctor' => 'timedoctor',
            'manual'     => 'manual',
            'import'     => 'import',
            'other'      => 'other',
        ],

        'sync_batch_size'     => 100,
        'max_date_range_days' => 31,
        'pagination_limit'    => 250,
        'max_retries'         => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | User Work Status Options
    |--------------------------------------------------------------------------
    |
    | Available work status options for IVA users
    |
    */

    'work_status_options'  => [
        'full_time'  => 'Full-time',
        'part_time'  => 'Part-time',
        'contractor' => 'Contractor',
        'intern'     => 'Intern',
        'temporary'  => 'Temporary',
        'consultant' => 'Consultant',
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration Setting Categories
    |--------------------------------------------------------------------------
    |
    | Available categories for configuration settings
    |
    */

    'setting_categories'   => [
        'site'        => 'Site Settings',
        'user'        => 'User Settings',
        'report_time' => 'Report Time Settings',
        'report_cat'  => 'Report Category Settings',
        'report'      => 'Report Settings',
        'system'      => 'System Settings',
        'other'       => 'Other Settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination Defaults
    |--------------------------------------------------------------------------
    |
    | Default pagination settings for various data lists
    |
    */

    'pagination'           => [
        'default_per_page'  => 20,
        'max_per_page'      => 100,
        'users_per_page'    => 15,
        'worklogs_per_page' => 25,
        'reports_per_page'  => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Limits
    |--------------------------------------------------------------------------
    |
    | File upload size limits and allowed types
    |
    */

    'file_uploads'         => [
        'max_size_mb'   => 10,
        'allowed_types' => [
            'csv'  => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls'  => 'application/vnd.ms-excel',
            'pdf'  => 'application/pdf',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Date and Time Formats
    |--------------------------------------------------------------------------
    |
    | Standard date and time formats used throughout the application
    |
    */

    'date_formats'         => [
        'display_date'      => 'M d, Y',
        'display_datetime'  => 'M d, Y g:i A',
        'input_date'        => 'Y-m-d',
        'input_datetime'    => 'Y-m-d\TH:i',
        'database_datetime' => 'Y-m-d H:i:s',
        'api_datetime'      => 'c', // ISO 8601
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Durations
    |--------------------------------------------------------------------------
    |
    | Cache duration settings in minutes
    |
    */

    'cache_durations'      => [
        'short'            => 5,           // 5 minutes
        'medium'           => 60,          // 1 hour
        'long'             => 1440,        // 24 hours
        'timedoctor_token' => 60 * 24 * 7, // 1 week
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Common validation rules used across the application
    |
    */

    'validation'           => [
        'email_max_length'    => 255,
        'name_max_length'     => 150,
        'comment_max_length'  => 500,
        'password_min_length' => 8,
        'username_min_length' => 3,
        'search_min_length'   => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Success Messages
    |--------------------------------------------------------------------------
    |
    | Success messages shown after successful operations.
    | These use placeholders for dynamic insertion (e.g., :resource).
    |
    */

    'success_messages'     => [

        'general'    => [
            'created'        => 'The :resource has been created successfully.',
            'updated'        => 'The :resource has been updated successfully.',
            'deleted'        => 'The :resource has been deleted successfully.',
            'status_changed' => 'The status of :resource has been updated.',
            'synced'         => ':resource data synchronized successfully.',
        ],

        'timedoctor' => [
            'connected'    => 'Time Doctor has been connected successfully.',
            'disconnected' => 'Time Doctor has been disconnected successfully.',
            'synced'       => 'Time Doctor data has been synced successfully.',
        ],

        'user'       => [
            'activated'   => 'The user account has been activated.',
            'deactivated' => 'The user account has been deactivated.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Messages
    |--------------------------------------------------------------------------
    |
    | Clear, actionable error messages to improve user understanding.
    |
    */

    'error_messages'       => [

        'not_found'                => 'The requested :resource was not found.',
        'unauthorized'             => 'You are not authorized to perform this action. Please contact your administrator.',
        'validation_failed'        => 'Some inputs are invalid. Please review the form and try again.',
        'server_error'             => 'An unexpected error occurred. Please try again later.',
        'permission_denied'        => 'You do not have the required permission to access this feature.',

        'timedoctor_not_connected' => 'Time Doctor is not connected. Go to Settings > Integrations to connect your account.',
        'invalid_date_range'       => 'The selected date range exceeds the maximum of :days days.',
        'overlapping_records'      => 'This time period overlaps with an existing record. Please adjust the start/end time.',

        'account_locked'           => 'Your account is temporarily locked due to multiple failed login attempts.',
        'account_unlocked'         => 'Your account has been unlocked. Please try logging in again.',
        'failed_login'             => 'Login failed. Please check your Office 365 credentials.',
    ],
];