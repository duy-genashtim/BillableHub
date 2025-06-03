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

// Register plugins
registerPlugins(app)



// Mount vue app
app.mount('#app')

// Initialize auth store
const authStore = useAuthStore()
authStore.initializeAuth()
