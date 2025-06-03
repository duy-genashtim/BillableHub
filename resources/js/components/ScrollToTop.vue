<template>
  <VBtn
    icon
    class="scroll-to-top d-print-none"
    color="primary"
    variant="elevated"
    :style="{ display: isVisible ? 'inline-flex' : 'none' }"
    @click="scrollToTop"
  >
    <VIcon icon="ri-arrow-up-line" size="22" />
  </VBtn>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

const isVisible = ref(false)

const toggleVisibility = () => {
  isVisible.value = window.scrollY > 200
}

const scrollToTop = () => {
  window.scrollTo({
    top: 0,
    behavior: 'smooth'
  })
}

onMounted(() => {
  window.addEventListener('scroll', toggleVisibility)
})

onBeforeUnmount(() => {
  window.removeEventListener('scroll', toggleVisibility)
})
</script>

<style scoped>
.scroll-to-top {
  position: fixed;
  z-index: 999;
  inset-block-end: 5%;
  inset-inline-end: 79px;
  transition: opacity 0.3s ease-in-out;
}
</style>
