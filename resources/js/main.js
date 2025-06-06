import { useAuthStore } from '@/@core/stores/auth'
import App from '@/App.vue'
import { registerPlugins } from '@core/utils/plugins'
import axios from 'axios'
import { createApp } from 'vue'
// Styles
import '@core-scss/template/index.scss'
import '@layouts/styles/index.scss'

// Create vue app
const app = createApp(App)

// Configure axios
axios.defaults.baseURL = window.location.origin
axios.defaults.headers.common['Content-Type'] = 'application/json'
axios.defaults.headers.common['Accept'] = 'application/json'

// Add axios interceptors for better error handling
axios.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Token expired or invalid
      const authStore = useAuthStore()
      authStore.logout()
      window.location.href = '/login'
    } else if (error.response?.status === 403) {
      // Insufficient permissions - let the component handle this
      console.warn('Access denied:', error.response.data?.error || 'Insufficient permissions')
    }
    return Promise.reject(error)
  }
)

// Register plugins
registerPlugins(app)

// Mount vue app
app.mount('#app')

// Initialize auth store
const authStore = useAuthStore()
authStore.initializeAuth()
