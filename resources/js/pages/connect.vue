<script setup>
import { useAuthStore } from '@core/stores/auth'
import { useRouter } from 'vue-router'

const authStore = useAuthStore()
const router = useRouter()

const isLoading = ref(true)
const message = ref('Processing authentication...')

onMounted(async () => {
  try {
    const success = await authStore.handleRedirectCallback()

    if (success) {
      message.value = 'Authentication successful! Redirecting...'
      setTimeout(() => {
        router.push('/dashboard')
      }, 1500)
    } else {
      throw new Error('Authentication failed')
    }
  } catch (error) {
    console.error('Authentication callback failed:', error)
    message.value = 'Authentication failed. Redirecting to login...'

    setTimeout(() => {
      router.push('/login')
    }, 3000)
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <div class="d-flex align-center justify-center min-h-screen">
    <VCard class="pa-8 text-center" max-width="400">
      <VCardText>
        <VProgressCircular v-if="isLoading" indeterminate color="primary" size="64" class="mb-4" />

        <VIcon v-else :icon="message.includes('successful') ? 'ri-check-line' : 'ri-error-warning-line'"
          :color="message.includes('successful') ? 'success' : 'error'" size="64" class="mb-4" />

        <h3 class="text-h5 mb-2">
          {{ isLoading ? 'Processing...' : (message.includes('successful') ? 'Success!' : 'Error') }}
        </h3>

        <p class="text-body-1">
          {{ message }}
        </p>
      </VCardText>
    </VCard>
  </div>
</template>
