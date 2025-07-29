export const routes = [
  { path: '/', redirect: '/dashboard' },
  {
    path: '/connect',
    component: () => import('@/pages/connect.vue'),
    meta: { public: true },
  },
  {
    path: '/',
    component: () => import('@/layouts/default.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: 'dashboard',
        component: () => import('@/pages/dashboard.vue'),
      },
      {
        path: 'account-settings',
        component: () => import('@/pages/account-settings.vue'),
      },
      // Admin routes
      {
        path: 'admin/roles',
        component: () => import('@/pages/admin/roles.vue'),
        meta: { permission: 'manage_roles' },
      },
      {
        path: 'admin/users',
        component: () => import('@/pages/admin/users.vue'),
        meta: { permission: 'manage_users' },
      },
      {
        path: 'admin/activity-logs',
        component: () => import('@/pages/admin/activity-logs.vue'),
        meta: { permission: 'view_activity_logs' },
      },

      // Region Management routes
      {
        path: 'admin/regions',
        name: 'regions-list',
        component: () => import('@/pages/regions/RegionsList.vue'),
        meta: { permission: 'manage_configuration' },
      },
      {
        path: 'admin/regions/create',
        name: 'region-create',
        component: () => import('@/pages/regions/RegionCreate.vue'),
        meta: { permission: 'manage_configuration' },
      },
      {
        path: 'admin/regions/:id/edit',
        name: 'region-edit',
        component: () => import('@/pages/regions/RegionEdit.vue'),
        meta: { permission: 'manage_configuration' },
      },
      {
        path: 'admin/regions/:id',
        name: 'region-detail',
        component: () => import('@/pages/regions/RegionDetail.vue'),
        meta: { permission: 'manage_configuration' },
      },

      // Cohort Management routes
      {
        path: 'admin/cohorts',
        name: 'cohorts-list',
        component: () => import('@/pages/cohorts/CohortsList.vue'),
        meta: { permission: 'manage_configuration' },
      },
      {
        path: 'admin/cohorts/create',
        name: 'cohort-create',
        component: () => import('@/pages/cohorts/CohortCreate.vue'),
        meta: { permission: 'manage_configuration' },
      },
      {
        path: 'admin/cohorts/:id/edit',
        name: 'cohort-edit',
        component: () => import('@/pages/cohorts/CohortEdit.vue'),
        meta: { permission: 'manage_configuration' },
      },
      {
        path: 'admin/cohorts/:id',
        name: 'cohort-detail',
        component: () => import('@/pages/cohorts/CohortDetail.vue'),
        meta: { permission: 'manage_configuration' },
      },

      // Configuration Management routes
      {
        path: 'admin/configuration',
        name: 'configuration-list',
        component: () => import('@/pages/configuration/ConfigurationList.vue'),
        meta: { permission: 'manage_configuration' },
      },
      {
        path: 'admin/configuration/create',
        name: 'configuration-create',
        component: () => import('@/pages/configuration/ConfigurationCreate.vue'),
        meta: { permission: 'manage_configuration' },
      },
      {
        path: 'admin/configuration/:id/edit',
        name: 'configuration-edit',
        component: () => import('@/pages/configuration/ConfigurationEdit.vue'),
        meta: { permission: 'manage_configuration' },
      },
      {
        path: 'admin/configuration/:id',
        name: 'configuration-detail',
        component: () => import('@/pages/configuration/ConfigurationDetail.vue'),
        meta: { permission: 'manage_configuration' },
      },
      {
        path: 'admin/configuration/logs',
        name: 'configuration-logs',
        component: () => import('@/pages/configuration/ActivityLogsList.vue'),
        meta: { permission: 'manage_configuration' },
      },

      // Configuration Setting Types routes
      {
        path: 'admin/configuration/types',
        name: 'configuration-types',
        component: () => import('@/pages/configuration/ConfigurationTypesList.vue'),
        meta: { permission: 'manage_configuration' },
      },
      {
        path: 'admin/configuration/types/create',
        name: 'configuration-type-create',
        component: () => import('@/pages/configuration/ConfigurationTypeCreate.vue'),
        meta: { permission: 'manage_configuration' },
      },
      {
        path: 'admin/configuration/types/:id/edit',
        name: 'configuration-type-edit',
        component: () => import('@/pages/configuration/ConfigurationTypeEdit.vue'),
        meta: { permission: 'manage_configuration' },
      },

      // TimeDoctor Integration routes
      {
        path: 'admin/timedoctor',
        name: 'timedoctor-integration',
        component: () => import('@/pages/timedoctor/TimeDoctorIntegration.vue'),
        meta: { permission: 'manage_configuration' },
      },
      {
        path: 'admin/timedoctor-v2',
        name: 'timedoctor-v2-integration',
        component: () => import('@/pages/timedoctor/TimeDoctorV2Integration.vue'),
        meta: { permission: 'manage_configuration' },
      },

      // Task Categories Management routes
      {
        path: 'admin/categories',
        name: 'categories-list',
        component: () => import('@/pages/categories/CategoriesList.vue'),
        meta: { permission: 'manage_configuration' },
      },
      {
        path: 'admin/categories/create',
        name: 'category-create',
        component: () => import('@/pages/categories/CategoryCreate.vue'),
        meta: { permission: 'manage_configuration' },
      },
      {
        path: 'admin/categories/:id/edit',
        name: 'category-edit',
        component: () => import('@/pages/categories/CategoryEdit.vue'),
        meta: { permission: 'manage_configuration' },
      },
      {
        path: 'admin/categories/:id',
        name: 'category-detail',
        component: () => import('@/pages/categories/CategoryDetail.vue'),
        meta: { permission: 'manage_configuration' },
      },

      // IVA User Management routes
      // IVA Users Management Routes
      {
        path: '/admin/iva-users',
        name: 'iva-users-list',
        component: () => import('@/pages/iva-users/IvaUsersList.vue'),
        meta: {
          requiresAuth: true,
          layout: 'admin',
          breadcrumb: [
            { title: 'Home', url: '/' },
            { title: 'IVA Users', active: true },
          ],
        },
      },
      {
        path: '/admin/iva-users/sync',
        name: 'iva-user-sync',
        component: () => import('@/pages/iva-users/IvaUserSync.vue'),
        meta: {
          requiresAuth: true,
          layout: 'admin',
          breadcrumb: [
            { title: 'Home', url: '/' },
            { title: 'IVA Users', url: '/admin/iva-users' },
            { title: 'Sync Users', active: true },
          ],
        },
      },
      {
        path: '/admin/iva-users/create',
        name: 'iva-user-create',
        component: () => import('@/pages/iva-users/IvaUserCreate.vue'),
        meta: {
          requiresAuth: true,
          layout: 'admin',
          breadcrumb: [
            { title: 'Home', url: '/' },
            { title: 'IVA Users', url: '/admin/iva-users' },
            { title: 'Create', active: true },
          ],
        },
      },
      {
        path: '/admin/iva-users/:id',
        name: 'iva-user-detail',
        component: () => import('@/pages/iva-users/IvaUserDetail.vue'),
        meta: {
          requiresAuth: true,
          layout: 'admin',
          breadcrumb: [
            { title: 'Home', url: '/' },
            { title: 'IVA Users', url: '/admin/iva-users' },
            { title: 'User Details', active: true },
          ],
        },
      },
      {
        path: '/admin/iva-users/:id/edit',
        name: 'iva-user-edit',
        component: () => import('@/pages/iva-users/IvaUserDetail.vue'),
        props: { isEditMode: true },
        meta: {
          requiresAuth: true,
          layout: 'admin',
          breadcrumb: [
            { title: 'Home', url: '/' },
            { title: 'IVA Users', url: '/admin/iva-users' },
            { title: 'Edit User', active: true },
          ],
        },
      },
      {
        path: '/admin/iva-users/:id/worklog-dashboard',
        name: 'iva-user-worklog-dashboard',
        component: () => import('@/pages/iva-users/IvaWorklogDashboard.vue'),
        meta: {
          requiresAuth: true,
          layout: 'admin',
          breadcrumb: [
            { title: 'Home', url: '/' },
            { title: 'IVA Users', url: '/admin/iva-users' },
            { title: 'Working Hours Dashboard', active: true },
          ],
        },
      },
      {
        path: '/admin/iva-users/:id/timedoctor-records',
        name: 'iva-user-timedoctor-records',
        component: () => import('@/pages/iva-users/IvaUserTimeDoctorRecords.vue'),
        meta: {
          requiresAuth: true,
          layout: 'admin',
          breadcrumb: [
            { title: 'Home', url: '/' },
            { title: 'IVA Users', url: '/admin/iva-users' },
            { title: 'Time Doctor Records', active: true },
          ],
        },
      },

      // IVA Manager Management routes
      {
        path: 'admin/iva-managers',
        name: 'iva-managers-list',
        component: () => import('@/pages/iva-managers/IvaManagersList.vue'),
        meta: { permission: 'manage_configuration' },
      },
      {
        path: 'admin/iva-managers/assign',
        name: 'iva-manager-assign',
        component: () => import('@/pages/iva-managers/IvaManagerAssign.vue'),
        meta: { permission: 'manage_configuration' },
      },
      {
        path: 'admin/iva-managers/:id',
        name: 'iva-manager-detail',
        component: () => import('@/pages/iva-managers/IvaManagerDetail.vue'),
        meta: { permission: 'manage_configuration' },
      },
      // IVA Reports routes
      {
        path: 'admin/reports/daily-performance',
        name: 'daily-performance-report',
        component: () => import('@/pages/iva-reports/DailyPerformanceReport.vue'),
        meta: { permission: 'view_reports' },
      },
      {
        path: 'admin/reports/weekly-performance',
        name: 'weekly-performance-report',
        component: () => import('@/pages/iva-reports/WeeklyPerformanceReport.vue'),
        meta: { permission: 'view_reports' },
      },
      {
        path: 'admin/reports/region-performance',
        name: 'region-performance-report',
        component: () => import('@/pages/iva-reports/RegionPerformanceReport.vue'),
        meta: { permission: 'view_reports' },
      },
      {
        path: 'admin/reports/overall-performance',
        name: 'overall-performance-report',
        component: () => import('@/pages/iva-reports/OverallPerformanceReport.vue'),
        meta: { permission: 'view_reports' },
      },
    ],
  },
  {
    path: '/',
    component: () => import('@/layouts/blank.vue'),
    children: [
      {
        path: 'login',
        component: () => import('@/pages/login.vue'),
        meta: { public: true },
      },
      {
        path: '/:pathMatch(.*)*',
        component: () => import('@/pages/[...error].vue'),
        meta: { public: true },
      },
    ],
  },
]
