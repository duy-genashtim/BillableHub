<script setup>
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

// Data
const cohorts = ref([])
const loading = ref(true)
const router = useRouter()
const searchQuery = ref('')
const deleteDialog = ref(false)
const cohortToDelete = ref(null)
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
      { title: 'Display Order', key: 'cohort_order', sortable: true },
      { title: 'ID', key: 'id', sortable: true },
      { title: 'Name', key: 'name', sortable: true },
      { title: 'Description', key: 'description', sortable: false },
      { title: 'Start Date', key: 'start_date', sortable: true },
      { title: 'IVA Users Count', key: 'iva_users_count', sortable: true },
      { title: 'Status', key: 'is_active', sortable: true },
      { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
    ]
  }
})

// Default sort by cohort_order ASC
const sortBy = ref([{ key: 'cohort_order', order: 'asc' }])

// Load cohorts on component mount
onMounted(() => {
  fetchCohorts()
  window.addEventListener('resize', handleResize)
})

function handleResize() {
  isMobile.value = window.innerWidth < 768
}

// Methods
async function fetchCohorts(page = 1) {
  loading.value = true
  try {
    const params = {
      page,
      per_page: pagination.value.per_page,
      search: searchQuery.value || undefined,
      sort_by: 'cohort_order',
      sort_order: 'asc'
    }

    const response = await axios.get('/api/admin/cohorts', { params })
    cohorts.value = response.data.cohorts
    pagination.value = response.data.pagination
  } catch (error) {
    console.error('Error fetching cohorts:', error)
    showSnackbar('Failed to load cohorts', 'error')
  } finally {
    loading.value = false
  }
}

function editCohort(cohort) {
  router.push({ name: 'cohort-edit', params: { id: cohort.id } })
}

function viewCohort(cohort) {
  router.push({ name: 'cohort-detail', params: { id: cohort.id } })
}

function confirmStatusChange(cohort) {
  cohortToDelete.value = cohort
  deleteDialog.value = true
}

async function changeStatus() {
  try {
    if (cohortToDelete.value.is_active) {
      // Deactivate
      await axios.delete(`/api/admin/cohorts/${cohortToDelete.value.id}`)
      showSnackbar('Cohort marked as inactive successfully', 'success')
    } else {
      // Activate
      await axios.put(`/api/admin/cohorts/${cohortToDelete.value.id}`, {
        ...cohortToDelete.value,
        is_active: true
      })
      showSnackbar('Cohort activated successfully', 'success')
    }

    fetchCohorts(pagination.value.current_page)
  } catch (error) {
    console.error('Error changing cohort status:', error)
    const message = error.response?.data?.message || 'Failed to change cohort status'
    showSnackbar(message, 'error')
  } finally {
    deleteDialog.value = false
    cohortToDelete.value = null
  }
}

function getCohortStatusColor(isActive) {
  return isActive ? 'success' : 'error'
}

function getCohortStatusText(isActive) {
  return isActive ? 'Active' : 'Inactive'
}

function addNewCohort() {
  router.push({ name: 'cohort-create' })
}

function showSnackbar(message, color = 'success') {
  snackbarText.value = message
  snackbarColor.value = color
  snackbar.value = true
}

function formatDate(date) {
  if (!date) return 'N/A'
  return new Date(date).toLocaleDateString()
}

// Watch for search changes
watch(searchQuery, (newValue) => {
  if (newValue.length > 2 || newValue.length === 0) {
    fetchCohorts(1)
  }
})

function onPageChange(page) {
  fetchCohorts(page)
}
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <VBreadcrumbs :items="[
      { title: 'Dashboard', to: '/dashboard' },
      { title: 'Cohorts', disabled: true }
    ]" class="mb-6" />

    <VCard>
      <VCardText>
        <div class="d-flex flex-wrap align-center mb-6">
          <h1 class="text-h5 text-md-h4 mr-auto mb-2 mb-md-0">
            Cohorts Management
          </h1>
          <VBtn color="primary" prepend-icon="ri-add-line" :size="isMobile ? 'small' : 'default'" @click="addNewCohort">
            <span v-if="!isMobile">Add New Cohort</span>
            <span v-else>Add</span>
          </VBtn>
        </div>

        <VTextField v-model="searchQuery" density="compact" placeholder="Search cohorts..."
          prepend-inner-icon="ri-search-line" hide-details class="mb-6" single-line clearable />

        <VDataTable :headers="headers" :items="cohorts" :loading="loading" :sort-by="sortBy" density="comfortable" hover
          class="elevation-1 rounded" :items-per-page="pagination.per_page" hide-default-footer>
          <!-- Display Order Column (desktop only) -->
          <template v-if="!isMobile" #[`item.cohort_order`]="{ item }">
            <div class="font-weight-bold text-center">
              {{ item.cohort_order }}
            </div>
          </template>

          <!-- Cohort Name Column -->
          <template #[`item.name`]="{ item }">
            <div class="font-weight-medium">
              {{ item.name }}
              <!-- Show additional info on mobile -->
              <div v-if="isMobile" class="d-flex align-center mt-1">
                <VChip size="x-small" color="secondary" class="mr-2">
                  Order: {{ item.cohort_order }}
                </VChip>
                <VChip size="x-small" color="info">
                  IVAs: {{ item.iva_users_count }}
                </VChip>
              </div>
              <!-- Show description on mobile -->
              <div v-if="isMobile && item.description" class="text-caption text-truncate mt-1">
                {{ item.description }}
              </div>
              <!-- Show start date on mobile -->
              <div v-if="isMobile && item.start_date" class="text-caption mt-1">
                Started: {{ formatDate(item.start_date) }}
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

          <!-- Start Date Column (desktop only) -->
          <template v-if="!isMobile" #[`item.start_date`]="{ item }">
            <span v-if="item.start_date">{{ formatDate(item.start_date) }}</span>
            <span v-else class="text-disabled">Not set</span>
          </template>

          <!-- IVA Users Count Column (desktop only) -->
          <template v-if="!isMobile" #[`item.iva_users_count`]="{ item }">
            <div class="text-center">
              {{ item.iva_users_count }}
            </div>
          </template>

          <!-- Status Column -->
          <template #[`item.is_active`]="{ item }">
            <VChip size="small" :color="getCohortStatusColor(item.is_active)" text-color="white">
              {{ getCohortStatusText(item.is_active) }}
            </VChip>
          </template>

          <!-- Actions Column -->
          <template #[`item.actions`]="{ item }">
            <div class="d-flex justify-end">
              <VBtn v-if="!isMobile" icon size="small" variant="text" color="primary" class="me-1"
                @click="viewCohort(item)">
                <VIcon size="20">ri-eye-line</VIcon>
                <VTooltip activator="parent">View Details</VTooltip>
              </VBtn>

              <VBtn v-if="!isMobile" icon size="small" variant="text" color="secondary" class="me-1"
                @click="editCohort(item)">
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
                  <VListItem @click="viewCohort(item)">
                    <template v-slot:prepend>
                      <VIcon size="small">ri-eye-line</VIcon>
                    </template>
                    <VListItemTitle>View Details</VListItemTitle>
                  </VListItem>

                  <VListItem @click="editCohort(item)">
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
              <VIcon size="48" color="secondary" icon="ri-team-line" class="mb-4" />
              <h3 class="text-h6 font-weight-regular mb-2">No cohorts found</h3>
              <p class="text-secondary text-center mb-4">
                There are no cohorts available. Create one to get started.
              </p>
              <VBtn color="primary" @click="addNewCohort">
                Add New Cohort
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
          {{ cohortToDelete && cohortToDelete.is_active ? 'Confirm Deactivation' : 'Confirm Activation' }}
        </VCardTitle>

        <VCardText>
          <template v-if="cohortToDelete && cohortToDelete.is_active">
            Are you sure you want to deactivate this cohort?
            <strong>{{ cohortToDelete.name }}</strong>
            <p class="mt-2 text-body-2">
              This action will mark the cohort as inactive. You can reactivate it later if needed.
            </p>
          </template>
          <template v-else-if="cohortToDelete">
            Are you sure you want to activate this cohort?
            <strong>{{ cohortToDelete.name }}</strong>
            <p class="mt-2 text-body-2">
              This action will make the cohort active again.
            </p>
          </template>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="deleteDialog = false">
            Cancel
          </VBtn>
          <VBtn :color="cohortToDelete && cohortToDelete.is_active ? 'error' : 'success'" @click="changeStatus">
            {{ cohortToDelete && cohortToDelete.is_active ? 'Deactivate' : 'Activate' }}
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
