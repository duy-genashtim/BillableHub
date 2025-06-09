<script setup>
import axios from 'axios'
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const router = useRouter()
const route = useRoute()
const regionId = route.params.id

const form = ref({
  name: '',
  description: '',
  region_order: 10,
  is_active: true
})

const loading = ref(true)
const errors = ref({})
const submitting = ref(false)
const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref('success')
const regionName = ref('')
const isMobile = ref(window.innerWidth < 768)

onMounted(() => {
  fetchRegion()
  window.addEventListener('resize', handleResize)
})

function handleResize() {
  isMobile.value = window.innerWidth < 768
}

async function fetchRegion() {
  loading.value = true
  try {
    const response = await axios.get(`/api/admin/regions/${regionId}`)
    const region = response.data.region

    regionName.value = region.name
    form.value = {
      name: region.name,
      description: region.description || '',
      region_order: region.region_order || 10,
      is_active: region.is_active
    }
  } catch (error) {
    console.error('Error fetching region:', error)
    snackbarText.value = 'Failed to load region'
    snackbarColor.value = 'error'
    snackbar.value = true
    router.push({ name: 'regions-list' })
  } finally {
    loading.value = false
  }
}

async function submitForm() {
  submitting.value = true
  errors.value = {}

  try {
    const response = await axios.put(`/api/admin/regions/${regionId}`, form.value)
    snackbarText.value = 'Region updated successfully'
    snackbarColor.value = 'success'
    snackbar.value = true

    // Navigate to the regions list after a short delay
    setTimeout(() => {
      router.push({ name: 'regions-list' })
    }, 1000)
  } catch (error) {
    if (error.response && error.response.data && error.response.data.errors) {
      errors.value = error.response.data.errors
    } else {
      snackbarText.value = error.response?.data?.message || 'Failed to update region'
      snackbarColor.value = 'error'
      snackbar.value = true
    }
  } finally {
    submitting.value = false
  }
}

function cancel() {
  router.push({ name: 'regions-list' })
}
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <VBreadcrumbs :items="[
      { title: 'Dashboard', to: '/dashboard' },
      { title: 'Regions', to: { name: 'regions-list' } },
      { title: loading ? 'Edit Region' : `Edit ${regionName}`, disabled: true }
    ]" class="mb-6" />

    <VCard>
      <VCardText>
        <VForm @submit.prevent="submitForm">
          <div class="d-flex align-center mb-6">
            <h1 class="text-h5 text-md-h4">
              Edit Region
            </h1>
          </div>

          <VRow v-if="loading">
            <VCol cols="12" class="d-flex justify-center">
              <VProgressCircular indeterminate color="primary" :size="50" :width="5" />
            </VCol>
          </VRow>

          <VRow v-else>
            <VCol cols="12">
              <VTextField v-model="form.name" label="Region Name" placeholder="Enter region name"
                :error-messages="errors.name" required density="comfortable" variant="outlined"
                prepend-inner-icon="ri-map-pin-line" />
            </VCol>

            <VCol cols="12">
              <VTextarea v-model="form.description" label="Description" placeholder="Enter region description"
                :error-messages="errors.description" rows="4" density="comfortable" variant="outlined"
                prepend-inner-icon="ri-text-wrap" />
            </VCol>

            <VCol cols="12" :md="isMobile ? 12 : 6">
              <VTextField v-model.number="form.region_order" label="Display Order"
                placeholder="Enter display order (lower numbers appear first)" :error-messages="errors.region_order"
                type="number" min="1" density="comfortable" variant="outlined" prepend-inner-icon="ri-sort-asc" />
            </VCol>

            <VCol cols="12" :md="isMobile ? 12 : 6">
              <VSwitch v-model="form.is_active" label="Active" color="primary" hide-details />
            </VCol>
          </VRow>

          <VDivider class="my-6" />

          <VCardActions class="pl-0">
            <VBtn color="secondary" variant="outlined" prepend-icon="ri-arrow-left-line" @click="cancel"
              :disabled="submitting || loading">
              Cancel
            </VBtn>

            <VSpacer />

            <VBtn color="primary" type="submit" prepend-icon="ri-save-line" :loading="submitting"
              :disabled="submitting || loading">
              Update Region
            </VBtn>
          </VCardActions>
        </VForm>
      </VCardText>
    </VCard>

    <!-- Snackbar for notifications -->
    <VSnackbar v-model="snackbar" :color="snackbarColor" :timeout="3000">
      {{ snackbarText }}
      <template #actions>
        <VBtn icon variant="text" @click="snackbar = false">
          <VIcon>ri-close-line</VIcon>
        </VBtn>
      </template>
    </VSnackbar>
  </div>
</template>

<style scoped>
@media (max-width: 767px) {

  /* Make the UI more compact on mobile */
  :deep(.v-card-text) {
    padding: 16px;
  }

  /* Adjust spacing for small screens */
  .mb-6 {
    margin-block-end: 16px !important;
  }

  .my-6 {
    margin-block: 16px !important;
  }
}
</style>
