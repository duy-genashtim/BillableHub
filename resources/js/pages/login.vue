<script setup>
import { appLongName, appName } from '@/@core/config/siteConfigs'
import { useAuthStore } from '@/@core/stores/auth'
import logo from '@images/logo.svg?raw'
import authV1MaskDark from '@images/pages/auth-v1-mask-dark.png'
import authV1MaskLight from '@images/pages/auth-v1-mask-light.png'
import authV1Tree2 from '@images/pages/auth-v1-tree-2.png'
import authV1Tree from '@images/pages/auth-v1-tree.png'
import { useRouter } from 'vue-router'
import { useTheme } from 'vuetify'

const authStore = useAuthStore()
const router = useRouter()
const vuetifyTheme = useTheme()

const authThemeMask = computed(() => {
  return vuetifyTheme.global.name.value === 'light' ? authV1MaskLight : authV1MaskDark
})

const isLoading = ref(false)
const errorMessage = ref('')
const isAuthenticating = ref(false)
const lastClickTime = ref(0)

// Check if user is already authenticated
onMounted(() => {
  if (authStore.isAuth) {
    router.push('/dashboard')
  }
})

const handleMicrosoftLogin = async () => {
  // Prevent rapid successive clicks
  const now = Date.now()
  if (now - lastClickTime.value < 2000) { // 2 second cooldown
    console.warn('Please wait before trying again')
    return
  }
  lastClickTime.value = now

  // Prevent double call
  if (isAuthenticating.value || authStore.isInteractionInProgress) {
    console.warn('Authentication already in progress')
    return
  }

  isAuthenticating.value = true
  isLoading.value = true
  errorMessage.value = ''

  try {
    await authStore.loginWithMicrosoft()

    // Wait a moment for state to update
    await nextTick()

    if (authStore.isAuth) {
      // Add a small delay to ensure navigation works properly
      setTimeout(() => {
        router.push('/dashboard')
      }, 100)
    } else {
      throw new Error('Authentication was not successful')
    }
  } catch (error) {
    console.error('Login failed:', error)

    // Handle specific error types
    let displayMessage = 'Login failed. Please try again.'

    if (error.message?.includes('interaction_in_progress')) {
      displayMessage = 'Another login is already in progress. Please wait a moment and try again.'
    } else if (error.message?.includes('popup')) {
      displayMessage = 'Popup was blocked. Please allow popups for this site and try again.'
    } else if (error.message?.includes('cancelled')) {
      displayMessage = 'Login was cancelled. Please try again.'
    } else if (error.message?.includes('network')) {
      displayMessage = 'Network error. Please check your connection and try again.'
    } else if (error.message) {
      displayMessage = error.message
    }

    errorMessage.value = displayMessage

    // Auto-clear error after 10 seconds
    setTimeout(() => {
      if (errorMessage.value === displayMessage) {
        errorMessage.value = ''
      }
    }, 10000)
  } finally {
    isLoading.value = false
    // Add delay before allowing next authentication attempt
    setTimeout(() => {
      isAuthenticating.value = false
    }, 1000)
  }
}

// Clear error when component unmounts
onUnmounted(() => {
  errorMessage.value = ''
})
</script>

<template>
  <div class="auth-wrapper d-flex align-center justify-center pa-4">
    <VCard class="auth-card pa-4 pt-7" max-width="448">
      <VCardItem class="justify-center">
        <RouterLink to="/" class="d-flex align-center gap-3">
          <div class="d-flex" v-html="logo" />
          <h2 class="font-weight-medium text-2xl text-uppercase">
            {{ appName }}
          </h2>
        </RouterLink>
      </VCardItem>

      <VCardText class="pt-2">
        <h4 class="text-h4 mb-1">
          Welcome to {{ appLongName }}! 👋🏻
        </h4>
        <p class="mb-0">
          Please sign-in with your Genashtim email to start the adventure
        </p>
      </VCardText>

      <VCardText>
        <VAlert v-if="errorMessage" type="error" class="mb-4" closable @click:close="errorMessage = ''">
          {{ errorMessage }}
        </VAlert>

        <VBtn block color="primary" size="large" :loading="isLoading"
          :disabled="isLoading || isAuthenticating || authStore.isInteractionInProgress" @click="handleMicrosoftLogin">
          <VIcon icon="ri-microsoft-line" class="me-2" />
          {{ isLoading ? 'Signing in...' : 'Login with Genashtim Email' }}
        </VBtn>

        <div class="mt-6 text-center">
          <p class="text-sm text-medium-emphasis">
            Use your Office 365 Genashtim email to sign in
          </p>

          <!-- Add helpful message when interaction is in progress -->
          <p v-if="authStore.isInteractionInProgress || isAuthenticating" class="text-xs text-warning mt-2">
            Authentication in progress... Please wait.
          </p>
        </div>
      </VCardText>
    </VCard>

    <VImg class="auth-footer-start-tree d-none d-md-block" :src="authV1Tree" :width="250" />

    <VImg :src="authV1Tree2" class="auth-footer-end-tree d-none d-md-block" :width="350" />

    <VImg class="auth-footer-mask d-none d-md-block" :src="authThemeMask" />
  </div>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth";
</style>
