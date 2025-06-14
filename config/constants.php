<?php

return [
    'roles'                => [
        'admin'   => 'Admin (Developer)',
        'hr'      => 'HR',
        'finance' => 'Finance',
        'rtl'     => 'RTL',
        'artl'    => 'ARTL',
        'iva'     => 'IVA',
    ],

    'permissions'          => [
        // System Management
        'manage_users'         => 'Manage Users',
        'manage_roles'         => 'Manage Roles',
        'manage_permissions'   => 'Manage Permissions',
        'view_activity_logs'   => 'View Activity Logs',
        'manage_configuration' => 'Manage Configuration',

        // Time Management
        'import_excel_time'    => 'Import Excel Time Data',
        'approve_manual_time'  => 'Approve Manual Time',
        'create_manual_entry'  => 'Create Manual Time Entry',
        'edit_manual_entry'    => 'Edit Manual Time Entry',
        'delete_manual_entry'  => 'Delete Manual Time Entry',

        // IVA Management
        'manage_ivas'          => 'Manage IVAs',
        'view_iva_data'        => 'View IVA Data',
        'edit_iva_data'        => 'Edit IVA Data',

        // Reports
        'generate_reports'     => 'Generate Reports',
        'view_reports'         => 'View Reports',
        'export_reports'       => 'Export Reports',

        // Team Management
        'view_team_data'       => 'View Team Data',
        'manage_team_data'     => 'Manage Team Data',

        // Own Data
        'view_own_data'        => 'View Own Data',
        'edit_own_data'        => 'Edit Own Data',
    ],

    'role_permissions'     => [
        'admin'   => [
            'manage_users',
            'manage_roles',
            'manage_permissions',
            'view_activity_logs',
            'manage_configuration',
            'import_excel_time',
            'approve_manual_time',
            'create_manual_entry',
            'edit_manual_entry',
            'delete_manual_entry',
            'manage_ivas',
            'view_iva_data',
            'edit_iva_data',
            'generate_reports',
            'view_reports',
            'export_reports',
            'view_team_data',
            'manage_team_data',
            'view_own_data',
            'edit_own_data',
        ],
        'hr'      => [
            'import_excel_time',
            'approve_manual_time',
            'manage_ivas',
            'view_iva_data',
            'edit_iva_data',
            'generate_reports',
            'view_reports',
            'export_reports',
            'manage_configuration',
        ],
        'finance' => [
            'view_reports',
            'export_reports',
        ],
        'rtl'     => [
            'view_team_data',
            'approve_manual_time',
        ],
        'artl'    => [
            'view_team_data',
            'approve_manual_time',
        ],
        'iva'     => [
            'view_own_data',
        ],
    ],

    'activity_log_actions' => [
        'create'                         => 'Created',
        'update'                         => 'Updated',
        'delete'                         => 'Deleted',
        'assign'                         => 'Assigned',
        'unassign'                       => 'Unassigned',
        'approve'                        => 'Approved',
        'reject'                         => 'Rejected',
        'import'                         => 'Imported',
        'export'                         => 'Exported',
        'login'                          => 'Logged In',
        'logout'                         => 'Logged Out',
        'view'                           => 'Viewed',
        'download'                       => 'Downloaded',
        'upload'                         => 'Uploaded',
        'toggle_status'                  => 'Status Changed',
        'create_config'                  => 'Configuration Created',
        'update_config'                  => 'Configuration Updated',
        'delete_config'                  => 'Configuration Deleted',
        'create_config_type'             => 'Configuration Type Created',
        'update_config_type'             => 'Configuration Type Updated',
        'delete_config_type'             => 'Configuration Type Deleted',
        'region_create'                  => 'Region Created',
        'region_update'                  => 'Region Updated',
        'region_delete'                  => 'Region Deleted',
        'region_deactivate'              => 'Region Deactivated',
        'region_assign_users'            => 'Users Assigned to Region',
        'region_remove_users'            => 'Users Removed from Region',
        'timedoctor_auth_redirect'       => 'TimeDoctor Auth Redirect',
        'timedoctor_auth_error'          => 'TimeDoctor Auth Error',
        'timedoctor_auth_token_error'    => 'TimeDoctor Token Error',
        'timedoctor_auth_success'        => 'TimeDoctor Connected',
        'timedoctor_auth_callback_error' => 'TimeDoctor Callback Error',
        'timedoctor_token_refresh'       => 'TimeDoctor Token Refreshed',
        'timedoctor_disconnect'          => 'TimeDoctor Disconnected',

        // Task Categories Management
        'create_category'                => 'Create Category',
        'update_category'                => 'Update Category',
        'delete_category'                => 'Delete Category',
        'view_category'                  => 'View Category',
        'view_categories_list'           => 'View Categories List',
        'assign_tasks_to_category'       => 'Assign Tasks to Category',
        'remove_tasks_from_category'     => 'Remove Tasks from Category',
        'toggle_category_status'         => 'Toggle Category Status',
        'view_available_tasks'           => 'View Available Tasks',
    ],

    'pagination'           => [
        'default_per_page'    => 20,
        'max_per_page'        => 100,
        'users_per_page'      => 15,
        'worklogs_per_page'   => 25,
        'reports_per_page'    => 10,
        'regions_per_page'    => 15,
        'timedoctor_per_page' => 20,
        'options'             => [15, 25, 50, 100],
        'mobile_per_page'     => 5,
    ],

    'setting_categories'   => [
        'site'        => 'Site Settings',
        'user'        => 'User Settings',
        'report_time' => 'Report Time Settings',
        'report_cat'  => 'Report Category Settings',
        'report'      => 'Report Settings',
        'system'      => 'System Settings',
        'other'       => 'Other Settings',
    ],
];