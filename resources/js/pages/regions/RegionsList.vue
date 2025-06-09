<script setup>
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

// Data
const regions = ref([])
const loading = ref(true)
const router = useRouter()
const searchQuery = ref('')
const deleteDialog = ref(false)
const regionToDelete = ref(null)
const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref('success')
const isMobile = ref(window.innerWidth < 768)

// Pagination
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 20,
  total: 0
})

// Headers for data table
const headers = computed(() => {
  if (isMobile.value) {
    return [
      { title: 'Name', key: 'name', sortable: true },
      { title: 'Status', key: 'is_active', sortable: true },
      { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
    ]
  } else {
    return [
      { title: 'Display Order', key: 'region_order', sortable: true },
      { title: 'ID', key: 'id', sortable: true },
      { title: 'Name', key: 'name', sortable: true },
      { title: 'Description', key: 'description', sortable: false },
      { title: 'IVA Users Count', key: 'iva_users_count', sortable: true },
      { title: 'Status', key: 'is_active', sortable: true },
      { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
    ]
  }
})

// Default sort by region_order ASC
const sortBy = ref([{ key: 'region_order', order: 'asc' }])

// Load regions on component mount
onMounted(() => {
  fetchRegions()
  window.addEventListener('resize', handleResize)
})

function handleResize() {
  isMobile.value = window.innerWidth < 768
}

// Methods
async function fetchRegions(page = 1) {
  loading.value = true
  try {
    const params = {
      page,
      per_page: pagination.value.per_page,
      search: searchQuery.value || undefined,
      sort_by: 'region_order',
      sort_order: 'asc'
    }

    const response = await axios.get('/api/admin/regions', { params })
    regions.value = response.data.regions
    pagination.value = response.data.pagination
  } catch (error) {
    console.error('Error fetching regions:', error)
    showSnackbar('Failed to load regions', 'error')
  } finally {
    loading.value = false
  }
}

function editRegion(region) {
  router.push({ name: 'region-edit', params: { id: region.id } })
}

function viewRegion(region) {
  router.push({ name: 'region-detail', params: { id: region.id } })
}

function confirmStatusChange(region) {
  regionToDelete.value = region
  deleteDialog.value = true
}

async function changeStatus() {
  try {
    if (regionToDelete.value.is_active) {
      // Deactivate
      await axios.delete(`/api/admin/regions/${regionToDelete.value.id}`)
      showSnackbar('Region marked as inactive successfully', 'success')
    } else {
      // Activate
      await axios.put(`/api/admin/regions/${regionToDelete.value.id}`, {
        ...regionToDelete.value,
        is_active: true
      })
      showSnackbar('Region activated successfully', 'success')
    }

    fetchRegions(pagination.value.current_page)
  } catch (error) {
    console.error('Error changing region status:', error)
    const message = error.response?.data?.message || 'Failed to change region status'
    showSnackbar(message, 'error')
  } finally {
    deleteDialog.value = false
    regionToDelete.value = null
  }
}

function getRegionStatusColor(isActive) {
  return isActive ? 'success' : 'error'
}

function getRegionStatusText(isActive) {
  return isActive ? 'Active' : 'Inactive'
}

function addNewRegion() {
  router.push({ name: 'region-create' })
}

function showSnackbar(message, color = 'success') {
  snackbarText.value = message
  snackbarColor.value = color
  snackbar.value = true
}

// Watch for search changes
watch(searchQuery, (newValue) => {
  if (newValue.length > 2 || newValue.length === 0) {
    fetchRegions(1)
  }
})

function onPageChange(page) {
  fetchRegions(page)
}
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <VBreadcrumbs :items="[
      { title: 'Dashboard', to: '/dashboard' },
      { title: 'Regions', disabled: true }
    ]" class="mb-6" />

    <VCard>
      <VCardText>
        <div class="d-flex flex-wrap align-center mb-6">
          <h1 class="text-h5 text-md-h4 mr-auto mb-2 mb-md-0">
            Regions Management
          </h1>
          <VBtn color="primary" prepend-icon="ri-add-line" :size="isMobile ? 'small' : 'default'" @click="addNewRegion">
            <span v-if="!isMobile">Add New Region</span>
            <span v-else>Add</span>
          </VBtn>
        </div>

        <VTextField v-model="searchQuery" density="compact" placeholder="Search regions..."
          prepend-inner-icon="ri-search-line" hide-details class="mb-6" single-line clearable />

        <VDataTable :headers="headers" :items="regions" :loading="loading" :sort-by="sortBy" density="comfortable" hover
          class="elevation-1 rounded" :items-per-page="pagination.per_page" hide-default-footer>
          <!-- Display Order Column (desktop only) -->
          <template v-if="!isMobile" #[`item.region_order`]="{ item }">
            <div class="font-weight-bold text-center">
              {{ item.region_order }}
            </div>
          </template>

          <!-- Region Name Column -->
          <template #[`item.name`]="{ item }">
            <div class="font-weight-medium">
              {{ item.name }}
              <!-- Show additional info on mobile -->
              <div v-if="isMobile" class="d-flex align-center mt-1">
                <VChip size="x-small" color="secondary" class="mr-2">
                  Order: {{ item.region_order }}
                </VChip>
                <VChip size="x-small" color="info">
                  IVAs: {{ item.iva_users_count }}
                </VChip>
              </div>
              <!-- Show description on mobile -->
              <div v-if="isMobile && item.description" class="text-caption text-truncate mt-1">
                {{ item.description }}
              </div>
            </div>
          </template>

          <!-- Description Column (desktop only) -->
          <template v-if="!isMobile" #[`item.description`]="{ item }">
            <span v-if="item.description" class="text-truncate d-block" style="max-inline-size: 250px;">
              {{ item.description }}
            </span>
            <span v-else class="text-disabled">No description</span>
          </template>

          <!-- IVA Users Count Column (desktop only) -->
          <template v-if="!isMobile" #[`item.iva_users_count`]="{ item }">
            <div class="text-center">
              {{ item.iva_users_count }}
            </div>
          </template>

          <!-- Status Column -->
          <template #[`item.is_active`]="{ item }">
            <VChip size="small" :color="getRegionStatusColor(item.is_active)" text-color="white">
              {{ getRegionStatusText(item.is_active) }}
            </VChip>
          </template>

          <!-- Actions Column -->
          <template #[`item.actions`]="{ item }">
            <div class="d-flex justify-end">
              <VBtn v-if="!isMobile" icon size="small" variant="text" color="primary" class="me-1"
                @click="viewRegion(item)">
                <VIcon size="20">ri-eye-line</VIcon>
                <VTooltip activator="parent">View Details</VTooltip>
              </VBtn>

              <VBtn v-if="!isMobile" icon size="small" variant="text" color="secondary" class="me-1"
                @click="editRegion(item)">
                <VIcon size="20">ri-pencil-line</VIcon>
                <VTooltip activator="parent">Edit</VTooltip>
              </VBtn>

              <VBtn icon size="small" variant="text" :color="item.is_active ? 'error' : 'success'" class="me-1"
                @click="confirmStatusChange(item)">
                <VIcon size="20">{{ item.is_active ? 'ri-close-circle-line' : 'ri-checkbox-circle-line' }}</VIcon>
                <VTooltip activator="parent">{{ item.is_active ? 'Deactivate' : 'Activate' }}</VTooltip>
              </VBtn>

              <!-- Menu for mobile -->
              <VMenu v-if="isMobile">
                <template v-slot:activator="{ props }">
                  <VBtn icon size="small" variant="text" color="secondary" v-bind="props">
                    <VIcon size="20">ri-more-2-fill</VIcon>
                  </VBtn>
                </template>
                <VList density="compact">
                  <VListItem @click="viewRegion(item)">
                    <template v-slot:prepend>
                      <VIcon size="small">ri-eye-line</VIcon>
                    </template>
                    <VListItemTitle>View Details</VListItemTitle>
                  </VListItem>

                  <VListItem @click="editRegion(item)">
                    <template v-slot:prepend>
                      <VIcon size="small">ri-pencil-line</VIcon>
                    </template>
                    <VListItemTitle>Edit</VListItemTitle>
                  </VListItem>
                </VList>
              </VMenu>
            </div>
          </template>

          <!-- Empty state -->
          <template #no-data>
            <div class="d-flex flex-column align-center pa-6">
              <VIcon size="48" color="secondary" icon="ri-map-pin-line" class="mb-4" />
              <h3 class="text-h6 font-weight-regular mb-2">No regions found</h3>
              <p class="text-secondary text-center mb-4">
                There are no regions available. Create one to get started.
              </p>
              <VBtn color="primary" @click="addNewRegion">
                Add New Region
              </VBtn>
            </div>
          </template>
        </VDataTable>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="d-flex justify-center mt-6">
          <VPagination :model-value="pagination.current_page" :length="pagination.last_page"
            @update:model-value="onPageChange" :total-visible="isMobile ? 5 : 7" />
        </div>
      </VCardText>
    </VCard>

    <!-- Confirm Status Change Dialog -->
    <VDialog v-model="deleteDialog" max-width="500">
      <VCard>
        <VCardTitle class="text-h5">
          {{ regionToDelete && regionToDelete.is_active ? 'Confirm Deactivation' : 'Confirm Activation' }}
        </VCardTitle>

        <VCardText>
          <template v-if="regionToDelete && regionToDelete.is_active">
            Are you sure you want to deactivate this region?
            <strong>{{ regionToDelete.name }}</strong>
            <p class="mt-2 text-body-2">
              This action will mark the region as inactive. You can reactivate it later if needed.
            </p>
          </template>
          <template v-else-if="regionToDelete">
            Are you sure you want to activate this region?
            <strong>{{ regionToDelete.name }}</strong>
            <p class="mt-2 text-body-2">
              This action will make the region active again.
            </p>
          </template>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="deleteDialog = false">
            Cancel
          </VBtn>
          <VBtn :color="regionToDelete && regionToDelete.is_active ? 'error' : 'success'" @click="changeStatus">
            {{ regionToDelete && regionToDelete.is_active ? 'Deactivate' : 'Activate' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

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
  :deep(.v-data-table) {
    font-size: 0.9rem;
  }

  :deep(.v-card-text) {
    padding: 16px;
  }

  :deep(.v-card-title) {
    font-size: 1.2rem;
  }

  /* Adjust spacing for small screens */
  .mb-6 {
    margin-block-end: 16px !important;
  }

  .mb-4 {
    margin-block-end: 12px !important;
  }
}
</style>
