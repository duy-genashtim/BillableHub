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

// Check if user is already authenticated
onMounted(() => {
  if (authStore.isAuth) {
    router.push('/dashboard')
  }
})

const handleMicrosoftLogin = async () => {
  try {
    isLoading.value = true
    errorMessage.value = ''
    
    await authStore.loginWithMicrosoft()
    
    // Wait a moment for state to update
    await nextTick()
    
    if (authStore.isAuth) {
      router.push('/dashboard')
    } else {
      throw new Error('Authentication was not successful')
    }
  } catch (error) {
    console.error('Login failed:', error)
    errorMessage.value = error.message || 'Login failed. Please try again.'
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="auth-wrapper d-flex align-center justify-center pa-4">
    <VCard
      class="auth-card pa-4 pt-7"
      max-width="448"
    >
      <VCardItem class="justify-center">
        <RouterLink
          to="/"
          class="d-flex align-center gap-3"
        >
          <div
            class="d-flex"
            v-html="logo"
          />
          <h2 class="font-weight-medium text-2xl text-uppercase">
             {{ appName }}
          </h2>
        </RouterLink>
      </VCardItem>

      <VCardText class="pt-2">
        <h4 class="text-h4 mb-1">
          Welcome to {{appLongName}}! 👋🏻
        </h4>
        <p class="mb-0">
          Please sign-in with your Genashtim email to start the adventure
        </p>
      </VCardText>

      <VCardText>
        <VAlert
          v-if="errorMessage"
          type="error"
          class="mb-4"
          closable
          @click:close="errorMessage = ''"
        >
          {{ errorMessage }}
        </VAlert>

        <VBtn
          block
          color="primary"
          size="large"
          :loading="isLoading"
          :disabled="isLoading"
          @click="handleMicrosoftLogin"
        >
          <VIcon icon="ri-microsoft-line" class="me-2" />
          Login with Genashtim Email
        </VBtn>

        <div class="mt-6 text-center">
          <p class="text-sm text-medium-emphasis">
            Use your Office 365 Genashtim email to sign in
          </p>
        </div>
      </VCardText>
    </VCard>

    <VImg
      class="auth-footer-start-tree d-none d-md-block"
      :src="authV1Tree"
      :width="250"
    />

    <VImg
      :src="authV1Tree2"
      class="auth-footer-end-tree d-none d-md-block"
      :width="350"
    />

    <VImg
      class="auth-footer-mask d-none d-md-block"
      :src="authThemeMask"
    />
  </div>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth";
</style>
