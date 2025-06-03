import { useAuthStore } from '@/@core/stores/auth'
import { createRouter, createWebHistory } from 'vue-router'
import { routes } from './routes'

export const isLoading = ref(false)

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
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

  if (requiresAuth && !isAuthenticated) {
    // Redirect to login if route requires auth and user is not authenticated
    next('/login')
  } else if (to.path === '/login' && isAuthenticated) {
    // Redirect to dashboard if user is already authenticated and tries to access login
    next('/dashboard')
  } else {
    next()
  }
})

router.afterEach(() => {
  isLoading.value = false
  // setTimeout(() => {
  //   isLoading.value = false // Stop loading indicator after delay
  // }, 10000) // 3000 milliseconds = 3 seconds
})

export default function (app) {
  app.use(router)
}
export { router }
