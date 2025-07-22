<script setup>
import { defineAsyncComponent, onMounted, ref } from 'vue'

const showScrollToTop = ref(false)

// Lazy load only when needed
const ScrollToTop = defineAsyncComponent(() =>
  import('@/components/ScrollToTop.vue')
)

// Load component only when user scrolls down
onMounted(() => {
  const handleScroll = () => {
    if (window.scrollY > 200 && !showScrollToTop.value) {
      showScrollToTop.value = true
      // Remove listener after first trigger to avoid unnecessary checks
      window.removeEventListener('scroll', handleScroll)
    }
  }

  window.addEventListener('scroll', handleScroll)
})
</script>

<template>
  <VApp>
    <RouterView />
    <ScrollToTop v-if="showScrollToTop" />
  </VApp>
</template>
