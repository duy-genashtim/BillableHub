import { PublicClientApplication } from '@azure/msal-browser'
import axios from 'axios'
import { defineStore } from 'pinia'

const msalConfig = {
  auth: {
    clientId: import.meta.env.VITE_AZURE_CLIENT_ID,
    authority: `https://login.microsoftonline.com/${import.meta.env.VITE_AZURE_TENANT_ID}`,
    redirectUri: window.location.origin + '/connect', //  import.meta.env.VITE_AZURE_REDIRECT_URI,
  },
  cache: {
    cacheLocation: 'localStorage',
    storeAuthStateInCookie: false,
  },
}

let msalInstance = null

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: null,
    isAuthenticated: false,
    isLoading: false,
    msalInstance: null,
    // Add interaction tracking
    isInteractionInProgress: false,
  }),

  getters: {
    getUser: state => state.user,
    getToken: state => state.token,
    isAuth: state => state.isAuthenticated,
    userPermissions: state => state.user?.permissions || [],
    userRoles: state => state.user?.roles || [],
    hasPermission: state => permission => {
      return state.user?.permissions?.some(p => p.name === permission) || false
    },
    hasRole: state => role => {
      return state.user?.roles?.some(r => r.name === role) || false
    },
    isSuperAdmin: state => state.user?.is_super_admin || false,
  },

  actions: {
    async initializeAuth() {
      this.isLoading = true

      try {
        if (!import.meta.env.VITE_AZURE_CLIENT_ID || !import.meta.env.VITE_AZURE_TENANT_ID) {
          throw new Error('Azure configuration missing')
        }

        if (!msalInstance) {
          msalInstance = new PublicClientApplication(msalConfig)
          await msalInstance.initialize()
        }

        this.msalInstance = msalInstance

        // Check if user is already authenticated
        const token = localStorage.getItem('auth_token')
        if (token) {
          this.token = token
          this.setAuthHeader(token)
          await this.fetchUser()
        }
      } catch (error) {
        console.error('Auth initialization failed:', error)
      } finally {
        this.isLoading = false
      }
    },

    async loginWithMicrosoft() {
      // Prevent multiple simultaneous login attempts
      if (this.isInteractionInProgress) {
        console.warn('Authentication already in progress')
        return
      }

      try {
        this.isInteractionInProgress = true
        this.isLoading = true

        if (!this.msalInstance) {
          throw new Error('MSAL not initialized')
        }

        // Check if there's already an ongoing interaction
        const inProgress =
          this.msalInstance.getActiveAccount() && this.msalInstance.getConfiguration().auth.navigateToLoginRequestUrl
        if (inProgress) {
          throw new Error('Another authentication is already in progress')
        }

        const loginRequest = {
          scopes: ['User.Read'],
          prompt: 'select_account',
        }

        let accounts = []
        try {
          accounts = this.msalInstance.getAllAccounts() || []
        } catch (accountError) {
          console.warn('Failed to get accounts:', accountError)
          accounts = []
        }

        let response = null

        if (accounts.length > 0) {
          // Try silent token acquisition first
          try {
            response = await this.msalInstance.acquireTokenSilent({
              ...loginRequest,
              account: accounts[0],
            })
          } catch (silentError) {
            console.warn('Silent token acquisition failed:', silentError)

            // Only proceed with popup if it's not an interaction_required error during an ongoing interaction
            if (silentError.errorCode !== 'interaction_in_progress') {
              try {
                response = await this.msalInstance.loginPopup(loginRequest)
              } catch (popupError) {
                throw popupError
              }
            } else {
              throw silentError
            }
          }
        } else {
          // No accounts, use popup login
          try {
            response = await this.msalInstance.loginPopup(loginRequest)
          } catch (popupError) {
            throw popupError
          }
        }

        if (response && response.accessToken) {
          await this.authenticateWithBackend(response.accessToken)
        } else {
          throw new Error('No access token received from Microsoft')
        }
      } catch (error) {
        let errorMessage = 'Login failed. Please try again.'

        if (error.errorCode === 'user_cancelled') {
          errorMessage = 'Login was cancelled'
        } else if (error.errorCode === 'interaction_in_progress') {
          errorMessage = 'Another login is already in progress. Please wait and try again.'
        } else if (error.errorCode === 'popup_window_error' || error.message?.includes('popup')) {
          errorMessage = 'Popup was blocked. Please allow popups for this site.'
        } else if (error.message?.includes('network')) {
          errorMessage = 'Network error. Please check your connection.'
        } else if (error.message) {
          errorMessage = error.message
        }

        throw new Error(errorMessage)
      } finally {
        this.isLoading = false
        this.isInteractionInProgress = false
      }
    },

    async handleRedirectCallback() {
      // Prevent multiple callback handling
      if (this.isInteractionInProgress) {
        console.warn('Redirect callback already in progress')
        return false
      }

      try {
        this.isInteractionInProgress = true
        this.isLoading = true

        if (!this.msalInstance) {
          throw new Error('MSAL not initialized')
        }

        const response = await this.msalInstance.handleRedirectPromise()

        if (response && response.accessToken) {
          await this.authenticateWithBackend(response.accessToken)
          return true
        }

        let accounts = []
        try {
          accounts = this.msalInstance.getAllAccounts() || []
        } catch (accountError) {
          console.warn('Failed to get accounts in callback:', accountError)
          return false
        }

        if (accounts.length > 0) {
          try {
            const tokenResponse = await this.msalInstance.acquireTokenSilent({
              scopes: ['User.Read'],
              account: accounts[0],
            })

            if (tokenResponse && tokenResponse.accessToken) {
              await this.authenticateWithBackend(tokenResponse.accessToken)
              return true
            }
          } catch (silentError) {
            console.error('Silent token acquisition failed in callback:', silentError)
          }
        }

        return false
      } catch (error) {
        console.error('Redirect callback failed:', error)
        throw error
      } finally {
        this.isLoading = false
        this.isInteractionInProgress = false
      }
    },

    async authenticateWithBackend(accessToken) {
      try {
        const response = await axios.post('/api/auth/login', {
          access_token: accessToken,
        })
        console.log('Backend authentication response:', response.data)

        if (response.data && response.data.token) {
          this.token = response.data.token
          this.user = {
            ...response.data.user,
            roles: [],
            permissions: [],
            is_super_admin: false,
          }
          this.isAuthenticated = true

          localStorage.setItem('auth_token', this.token)
          this.setAuthHeader(this.token)

          // Fetch user details with roles and permissions
          await this.fetchUser()
        } else {
          throw new Error('Invalid response from backend')
        }
      } catch (error) {
        let errorMessage = 'Authentication failed'

        if (error.response) {
          if (error.response.data && error.response.data.error) {
            errorMessage = error.response.data.error
          } else if (error.response.status === 401) {
            errorMessage = 'Invalid credentials'
          } else if (error.response.status >= 500) {
            errorMessage = 'Server error. Please try again later.'
          }
        } else if (error.request) {
          errorMessage = 'Network error. Please check your connection.'
        }

        throw new Error(errorMessage)
      }
    },

    async fetchUser() {
      try {
        const response = await axios.get('/api/auth/me')
        console.log('Fetch user response:', response.data)
        if (response.data && response.data.user) {
          this.user = {
            ...response.data.user,
            roles: response.data.user.roles || [],
            permissions: response.data.user.permissions || [],
            is_super_admin: response.data.user.is_super_admin || false,
          }
          this.isAuthenticated = true
        } else {
          throw new Error('Invalid user data')
        }
      } catch (error) {
        console.error('Fetch user failed:', error)
        this.logout()
      }
    },

    async fetchUserDetails() {
      try {
        if (!this.user) return

        const response = await axios.get(`/api/admin/users/${this.user.id}`)
        if (response.data && response.data.user) {
          this.user = {
            ...this.user,
            roles: response.data.user.roles || [],
            permissions: response.data.user.permissions || [],
            is_super_admin: response.data.user.is_super_admin || false,
          }
        }
      } catch (error) {
        console.warn('Failed to fetch user details:', error)
        // Don't logout on this error, just log warning
      }
    },

    setAuthHeader(token) {
      if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
      }
    },

    async logout() {
      try {
        if (this.token) {
          try {
            await axios.post('/api/auth/logout')
          } catch (logoutError) {
            console.warn('Backend logout failed:', logoutError)
          }
        }
      } catch (error) {
        console.error('Logout error:', error)
      } finally {
        this.user = null
        this.token = null
        this.isAuthenticated = false
        this.isInteractionInProgress = false // Reset interaction flag

        localStorage.removeItem('auth_token')
        delete axios.defaults.headers.common['Authorization']

        if (this.msalInstance) {
          try {
            const accounts = this.msalInstance.getAllAccounts() || []
            if (accounts.length > 0) {
              await this.msalInstance.logoutPopup({
                account: accounts[0],
                postLogoutRedirectUri: window.location.origin + '/login',
              })
            }
          } catch (msalLogoutError) {
            console.warn('Microsoft logout failed:', msalLogoutError)
          }
        }
      }
    },
  },
})
