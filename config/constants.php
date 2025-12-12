<?php

return [
    'start_year'                        => 2024,
    'week_start'                        => '2024-01-15',
    'week_per_year'                     => 52,
    'performance_status'                => [
        'BELOW'    => 'BELOW',
        'MEET'     => 'MEET',
        'EXCEEDED' => 'EXCEEDED',
    ],
    'performance_percentage_thresholds' => [
        'EXCEEDED' => 101,
        'MEET'     => 99,
    ],
    'roles'                             => [
        'admin' => 'Admin (Developer)',
        'hr'    => 'HR',
        // 'finance' => 'Finance',
        // 'rtl'     => 'RTL',
        // 'artl'    => 'ARTL',
        // 'iva'     => 'IVA',
    ],

    'permissions'                       => [
        // System Management
        'manage_users'         => 'Manage Users',
        'manage_roles'         => 'Manage Roles',
        'view_activity_logs'   => 'View Activity Logs',
        'manage_configuration' => 'Manage Configuration',

        // Time Management
        'sync_timedoctor_data' => 'Sync TimeDoctor Data',
        'approve_manual_time'  => 'Approve Manual Time',      // should remove
        'create_manual_entry'  => 'Create Manual Time Entry', // should remove
        'edit_manual_entry'    => 'Edit Manual Time Entry',   // should remove
        'delete_manual_entry'  => 'Delete Manual Time Entry', // should remove

                                                   // IVA Management
        'manage_ivas'          => 'Manage IVAs',   // used
        'edit_iva_data'        => 'Edit IVA Data', // used

                                                      // Reports
        'generate_reports'     => 'Generate Reports', // should remove
        'view_reports'         => 'View Reports',
        'export_reports'       => 'Export Reports',

        // Team Management
        'view_team_data'       => 'View Team Data',
    ],

    'role_permissions'                  => [
        'admin' => [
            'manage_users',
            'manage_roles',
            'view_activity_logs',
            'manage_configuration',
            'sync_timedoctor_data',
            'approve_manual_time', // should remove
            'create_manual_entry', // should remove
            'edit_manual_entry',   // should remove
            'delete_manual_entry', // should remove
            'manage_ivas',
            'edit_iva_data',
            'generate_reports',
            'view_reports',
            'export_reports',
            'view_team_data',
        ],
        'hr'    => [
            'sync_timedoctor_data',
            'approve_manual_time', // should remove
            'manage_ivas',
            'edit_iva_data',
            'generate_reports',
            'view_reports',
            'export_reports',
            'manage_configuration',
        ],
        // 'finance' => [
        //     'view_reports',
        //     'export_reports',
        // ],
        // 'rtl'     => [
        //     'view_team_data',
        //     'approve_manual_time',
        // ],
        // 'artl'    => [
        //     'view_team_data',
        //     'approve_manual_time',
        // ],
    ],

    'activity_log_actions'              => [
        'create'                            => 'Role Created',
        'update'                            => 'Role Updated',
        'delete'                            => 'Role Deleted',
        'assign'                            => 'Role Assigned',
        'unassign'                          => 'Role Unassigned',
        'approve'                           => 'Approved',
        'reject'                            => 'Rejected',
        'import'                            => 'Imported',
        'export'                            => 'Exported Activity Log',
        'login'                             => 'Logged In',
        'logout'                            => 'Logged Out',
        'view'                              => 'Viewed',
        'download'                          => 'Downloaded',
        'upload'                            => 'Uploaded',
        'toggle_status'                     => 'Status Changed',
        'create_config'                     => 'Configuration Created',
        'update_config'                     => 'Configuration Updated',
        'delete_config'                     => 'Configuration Deleted',
        'create_config_type'                => 'Configuration Type Created',
        'update_config_type'                => 'Configuration Type Updated',
        'delete_config_type'                => 'Configuration Type Deleted',
        'region_create'                     => 'Region Created',
        'region_update'                     => 'Region Updated',
        'region_delete'                     => 'Region Deleted',
        'region_deactivate'                 => 'Region Deactivated',
        'region_assign_users'               => 'Users Assigned to Region',
        'region_remove_users'               => 'Users Removed from Region',
        'timedoctor_auth_redirect'          => 'TimeDoctor Auth Redirect',
        'timedoctor_auth_error'             => 'TimeDoctor Auth Error',
        'timedoctor_auth_token_error'       => 'TimeDoctor Token Error',
        'timedoctor_auth_success'           => 'TimeDoctor Connected',
        'timedoctor_auth_callback_error'    => 'TimeDoctor Callback Error',
        'timedoctor_token_refresh'          => 'TimeDoctor Token Refreshed',
        'timedoctor_disconnect'             => 'TimeDoctor Disconnected',

        // Task Categories Management
        'create_category'                   => 'Create Category',
        'update_category'                   => 'Update Category',
        'delete_category'                   => 'Delete Category',
        'view_category'                     => 'View Category',
        'view_categories_list'              => 'View Categories List',
        'assign_tasks_to_category'          => 'Assign Tasks to Category',
        'remove_tasks_from_category'        => 'Remove Tasks from Category',
        'toggle_category_status'            => 'Toggle Category Status',
        'view_available_tasks'              => 'View Available Tasks',

        // IVA User Management
        'create_iva_user'                   => 'Create IVA User',
        'update_iva_user'                   => 'Update IVA User',
        'delete_iva_user'                   => 'Delete IVA User',
        'view_iva_user'                     => 'View IVA User',
        'view_iva_users_list'               => 'View IVA Users List',
        'toggle_iva_user_status'            => 'Toggle IVA User Status',
        'update_iva_user_customization'     => 'Update IVA User Customization',
        'remove_iva_user_customization'     => 'Remove IVA User Customization',
        'sync_iva_user_timedoctor'          => 'Sync IVA User with TimeDoctor',

        // IVA Manager Management
        'create_iva_manager'                => 'Create IVA Manager Assignment',
        'update_iva_manager'                => 'Update IVA Manager Assignment',
        'delete_iva_manager'                => 'Delete IVA Manager Assignment',
        'view_iva_manager'                  => 'View IVA Manager',
        'view_iva_managers_list'            => 'View IVA Managers List',
        'assign_iva_manager'                => 'Assign IVA Manager',
        'remove_iva_manager'                => 'Remove IVA Manager',
        'add_users_to_iva_manager'          => 'Add Users to IVA Manager',
        'remove_user_from_iva_manager'      => 'Remove User from IVA Manager',

        // Time Doctor Records Management
        'view_timedoctor_records'           => 'View Time Doctor Records',
        'create_timedoctor_record'          => 'Create Time Doctor Record',
        'update_timedoctor_record'          => 'Update Time Doctor Record',
        'delete_timedoctor_record'          => 'Delete Time Doctor Record',
        'toggle_timedoctor_record_status'   => 'Toggle Time Doctor Record Status',
        'sync_timedoctor_records'           => 'Sync Time Doctor Records',

        // Worklog Dashboard
        'view_worklog_dashboard'            => 'View Worklog Dashboard',

        // cohort management
        'cohort_create'                     => 'Create Cohort',
        'cohort_update'                     => 'Update Cohort',
        'cohort_deactivate'                 => 'Deactivate Cohort',
        'cohort_assign_users'               => 'Assign Users to Cohort',
        'cohort_remove_users'               => 'Remove Users from Cohort',

        'timedoctor_v2_auth_error'          => 'TimeDoctor V2 Auth Error',
        'timedoctor_v2_auth_success'        => 'TimeDoctor V2 Connected',
        'timedoctor_v2_auth_callback_error' => 'TimeDoctor V2 Callback Error',
        'timedoctor_v2_token_refresh'       => 'TimeDoctor V2 Token Refreshed',
        'timedoctor_v2_disconnect'          => 'TimeDoctor V2 Disconnected',
        'sync_timedoctor_v2_data'           => 'Sync TimeDoctor V2 Data',
    ],

    'pagination'                        => [
        'default_per_page'            => 20,
        'max_per_page'                => 100,
        'users_per_page'              => 15,
        'worklogs_per_page'           => 25,
        'reports_per_page'            => 10,
        'regions_per_page'            => 15,
        'timedoctor_per_page'         => 20,
        'iva_users_per_page'          => 15,
        'iva_managers_per_page'       => 15,
        'timedoctor_records_per_page' => 20,
        'options'                     => [15, 25, 50, 100],
        'mobile_per_page'             => 5,
        'worklog_dashboard_per_page'  => 30,
    ],

    'setting_categories'                => [
        'site'        => 'Site Settings',
        'user'        => 'IVA User Settings',
        'report_time' => 'Report Time Settings',
        'report_cat'  => 'Report Category Settings',
        'report'      => 'Report Settings',
        'system'      => 'System Settings',
        'other'       => 'Other Settings',
    ],
];
