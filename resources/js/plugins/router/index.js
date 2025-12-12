import { useAuthStore } from '@/@core/stores/auth'
import { createRouter, createWebHistory } from 'vue-router'
import { routes } from './routes'

export const isLoading = ref(false)

const router = createRouter({
  history: createWebHistory('/'),
  routes,
})

// Route guards
router.beforeEach(async (to, from, next) => {
  isLoading.value = true

  const authStore = useAuthStore()

  // Wait for auth initialization if still loading
  let attempts = 0
  while (authStore.isLoading && attempts < 50) {
    await new Promise(resolve => setTimeout(resolve, 100))
    attempts++
  }

  const isAuthenticated = authStore.isAuth
  const requiresAuth = to.matched.some(record => record.meta.requiresAuth)
  const requiredPermission = to.meta.permission

  if (requiresAuth && !isAuthenticated) {
    // Redirect to login if route requires auth and user is not authenticated
    next('/login')
  } else if (to.path === '/login' && isAuthenticated) {
    // Redirect to dashboard if user is already authenticated and tries to access login
    next('/dashboard')
  } else if (requiredPermission && isAuthenticated) {
    // Check permissions for protected routes
    // Support both single permission string and array of permissions
    const hasRequiredPermission = Array.isArray(requiredPermission)
      ? requiredPermission.some(permission => authStore.hasPermission(permission))
      : authStore.hasPermission(requiredPermission)

    if (!hasRequiredPermission) {
      // Redirect to dashboard with error message
      next({
        path: '/dashboard',
        query: { error: 'insufficient_permissions' },
      })
    } else {
      next()
    }
  } else {
    next()
  }
})

router.afterEach(() => {
  isLoading.value = false
})

export default function (app) {
  app.use(router)
}
export { router }
