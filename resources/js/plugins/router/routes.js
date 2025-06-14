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
      {
        path: 'typography',
        component: () => import('@/pages/typography.vue'),
      },
      {
        path: 'icons',
        component: () => import('@/pages/icons.vue'),
      },
      {
        path: 'cards',
        component: () => import('@/pages/cards.vue'),
      },
      {
        path: 'tables',
        component: () => import('@/pages/tables.vue'),
      },
      {
        path: 'form-layouts',
        component: () => import('@/pages/form-layouts.vue'),
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
        path: 'register',
        component: () => import('@/pages/register.vue'),
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
