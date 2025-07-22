<script setup>
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const router = useRouter()
const route = useRoute()
const regionId = route.params.id

const region = ref(null)
const users = ref([])
const availableUsers = ref([])
const loading = ref(true)
const usersLoading = ref(false)
const searchQuery = ref('')
const dialogSearchQuery = ref('')

const userDialog = ref(false)
const selectedUsers = ref([])

const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref('success')

const removeUserDialog = ref(false)
const usersToRemove = ref([])

// For responsive design
const isMobile = ref(window.innerWidth < 768)

// Headers for data table
const userHeaders = computed(() => {
  if (isMobile.value) {
    return [
      { title: 'Full Name', key: 'full_name', sortable: true },
      { title: 'Status', key: 'is_active', sortable: true },
      { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
    ]
  } else {
    return [
      { title: 'ID', key: 'id', sortable: true },
      { title: 'Full Name', key: 'full_name', sortable: true },
      { title: 'Email', key: 'email', sortable: true },
      { title: 'TimeDoctor Version', key: 'timedoctor_version', sortable: true },
      { title: 'Work Status', key: 'work_status', sortable: true },
      { title: 'Status', key: 'is_active', sortable: true },
      { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
    ]
  }
})

// Available users headers
const availableUserHeaders = computed(() => {
  if (isMobile.value) {
    return [
      { title: 'Full Name', key: 'full_name', sortable: true },
    ]
  } else {
    return [
      { title: 'ID', key: 'id', sortable: true },
      { title: 'Full Name', key: 'full_name', sortable: true },
      { title: 'Email', key: 'email', sortable: true },
      { title: 'TimeDoctor Version', key: 'timedoctor_version', sortable: true },
      { title: 'Work Status', key: 'work_status', sortable: true },
    ]
  }
})

onMounted(() => {
  window.addEventListener('resize', handleResize)
  fetchRegionDetails()
})

function handleResize() {
  isMobile.value = window.innerWidth < 768
}

async function fetchRegionDetails() {
  loading.value = true
  try {
    const response = await axios.get(`/api/admin/regions/${regionId}`)
    region.value = response.data.region
    users.value = region.value?.iva_users || []
  } catch (error) {
    console.error('Error fetching region details:', error)
    snackbarText.value = 'Failed to load region details'
    snackbarColor.value = 'error'
    snackbar.value = true
    router.push({ name: 'regions-list' })
  } finally {
    loading.value = false
  }
}

async function fetchAvailableUsers() {
  usersLoading.value = true
  try {
    const response = await axios.get('/api/admin/regions/available-users')
    availableUsers.value = response.data.users || []
  } catch (error) {
    console.error('Error fetching available users:', error)
    snackbarText.value = 'Failed to load available users'
    snackbarColor.value = 'error'
    snackbar.value = true
  } finally {
    usersLoading.value = false
  }
}

function openAddUserDialog() {
  fetchAvailableUsers()
  selectedUsers.value = []
  dialogSearchQuery.value = ''
  userDialog.value = true
}

async function addUsersToRegion() {
  if (selectedUsers.value.length === 0) {
    snackbarText.value = 'Please select at least one user'
    snackbarColor.value = 'warning'
    snackbar.value = true
    return
  }

  try {
    await axios.post(`/api/admin/regions/${regionId}/assign-users`, {
      user_ids: selectedUsers.value
    })

    snackbarText.value = 'IVA users assigned to region successfully'
    snackbarColor.value = 'success'
    snackbar.value = true

    // Refresh the region details
    fetchRegionDetails()
    userDialog.value = false
  } catch (error) {
    console.error('Error adding users to region:', error)
    snackbarText.value = error.response?.data?.message || 'Failed to assign users to region'
    snackbarColor.value = 'error'
    snackbar.value = true
  }
}

function confirmRemoveUsers(userIds) {
  usersToRemove.value = Array.isArray(userIds) ? userIds : [userIds]
  removeUserDialog.value = true
}

async function removeUsersFromRegion() {
  try {
    await axios.delete(`/api/admin/regions/${regionId}/remove-users`, {
      data: {
        user_ids: usersToRemove.value
      }
    })

    snackbarText.value = 'IVA users removed from region successfully'
    snackbarColor.value = 'success'
    snackbar.value = true

    // Refresh the region details
    fetchRegionDetails()
    removeUserDialog.value = false
  } catch (error) {
    console.error('Error removing users from region:', error)
    snackbarText.value = error.response?.data?.message || 'Failed to remove users from region'
    snackbarColor.value = 'error'
    snackbar.value = true
  }
}

function editRegion() {
  router.push({ name: 'region-edit', params: { id: regionId } })
}

function goBack() {
  router.push({ name: 'regions-list' })
}

// Computed properties for filtering
const filteredUsers = computed(() => {
  if (!searchQuery.value || !users.value) return users.value

  const query = searchQuery.value.toLowerCase()
  return users.value.filter(user =>
    (user.full_name && user.full_name.toLowerCase().includes(query)) ||
    user.email.toLowerCase().includes(query) ||
    user.id.toString().includes(query)
  )
})

const filteredAvailableUsers = computed(() => {
  if (!dialogSearchQuery.value || !availableUsers.value) return availableUsers.value

  const query = dialogSearchQuery.value.toLowerCase()
  return availableUsers.value.filter(user =>
    (user.full_name && user.full_name.toLowerCase().includes(query)) ||
    user.email.toLowerCase().includes(query) ||
    user.id.toString().includes(query)
  )
})

const regionName = computed(() => {
  return region.value ? region.value.name : 'Region Details'
})

function getUserStatusColor(isActive) {
  return isActive ? 'success' : 'error'
}

function getUserStatusText(isActive) {
  return isActive ? 'Active' : 'Inactive'
}

function getWorkStatusColor(workStatus) {
  const statusColors = {
    'active': 'success',
    'inactive': 'error',
    'pending': 'warning',
    'suspended': 'error'
  }
  return statusColors[workStatus?.toLowerCase()] || 'secondary'
}

function closeUserDialog() {
  userDialog.value = false
}
</script>

<template>
  <div>
    <VBreadcrumbs :items="[
      { title: 'Dashboard', to: '/dashboard' },
      { title: 'Regions', to: { name: 'regions-list' } },
      { title: regionName, disabled: true }
    ]" class="mb-6" aria-label="Breadcrumb navigation" />

    <VCard v-if="loading">
      <VCardText class="d-flex justify-center align-center pa-6">
        <VProgressCircular indeterminate color="primary" :size="50" :width="5" aria-label="Loading region details" />
      </VCardText>
    </VCard>

    <template v-else-if="region">
      <!-- Region Information Card -->
      <VCard class="mb-6">
        <VCardText>
          <div class="d-flex flex-wrap align-center mb-4">
            <h1 class="text-h5 text-md-h4 mr-auto mb-2 mb-md-0" tabindex="0">
              {{ region.name }}
            </h1>
            <div class="d-flex align-center">
              <VChip size="small" :color="region.is_active ? 'success' : 'error'" text-color="white"
                class="mr-2 mr-md-4" aria-label="Region status: {{ region.is_active ? 'Active' : 'Inactive' }}">
                {{ region.is_active ? 'Active' : 'Inactive' }}
              </VChip>
              <VBtn color="secondary" variant="outlined" prepend-icon="ri-pencil-line"
                :size="isMobile ? 'small' : 'default'" @click="editRegion" aria-label="Edit region" class="mr-2">
                Edit
              </VBtn>
              <VBtn color="primary" variant="outlined" prepend-icon="ri-arrow-left-line"
                :size="isMobile ? 'small' : 'default'" @click="goBack" aria-label="Go back to regions list">
                Back
              </VBtn>
            </div>
          </div>

          <VDivider class="mb-4" aria-hidden="true" />

          <div v-if="region.description" class="mb-4">
            <div class="text-subtitle-1 font-weight-medium mb-1" id="region-description-label">Description:</div>
            <p class="text-body-1" tabindex="0" aria-labelledby="region-description-label">{{ region.description }}</p>
          </div>
          <p v-else class="text-body-2 text-disabled mb-4" tabindex="0">No description provided</p>

          <div class="mb-4">
            <div class="text-subtitle-1 font-weight-medium mb-1" id="region-order-label">Display Order:</div>
            <p class="text-body-1" tabindex="0" aria-labelledby="region-order-label">{{ region.region_order }}</p>
          </div>
        </VCardText>
      </VCard>

      <!-- Users Card -->
      <VCard>
        <VCardText>
          <div class="d-flex flex-wrap align-center mb-4">
            <h2 class="text-h5 text-md-h5 mr-auto mb-2 mb-md-0" tabindex="0">
              IVA Users in this Region
            </h2>
            <VBtn color="primary" prepend-icon="ri-user-add-line" :size="isMobile ? 'small' : 'default'"
              @click="openAddUserDialog" aria-label="Add IVA users to this region">
              <span v-if="!isMobile">Add IVA Users</span>
              <span v-else>Add</span>
            </VBtn>
          </div>

          <VTextField v-model="searchQuery" density="compact" placeholder="Search IVA users..."
            prepend-inner-icon="ri-search-line" hide-details class="mb-4" single-line clearable
            aria-label="Search for IVA users" />

          <VDataTable :headers="userHeaders" :items="filteredUsers" :loading="loading" density="comfortable" hover
            class="elevation-1 rounded" aria-label="IVA users table" hide-default-footer>
            <!-- ID Column (desktop only) -->
            <template v-if="!isMobile" #[`item.id`]="{ item }">
              <span>{{ item.id }}</span>
            </template>

            <!-- Full Name Column -->
            <template #[`item.full_name`]="{ item }">
              <div class="font-weight-medium">
                {{ item.full_name || 'No name provided' }}
                <div v-if="isMobile" class="text-caption">
                  {{ item.email }}
                </div>
                <div v-if="isMobile" class="d-flex align-center mt-1">
                  <VChip size="x-small" color="info" class="mr-1">
                    v{{ item.timedoctor_version || 1 }}
                  </VChip>
                  <VChip v-if="item.work_status" size="x-small" :color="getWorkStatusColor(item.work_status)">
                    {{ item.work_status }}
                  </VChip>
                </div>
              </div>
            </template>

            <!-- Email Column (desktop only) -->
            <template v-if="!isMobile" #[`item.email`]="{ item }">
              <div>{{ item.email }}</div>
            </template>

            <!-- TimeDoctor Version Column (desktop only) -->
            <template v-if="!isMobile" #[`item.timedoctor_version`]="{ item }">
              <VChip size="small" color="info" text-color="white"
                aria-label="TimeDoctor version: {{ item.timedoctor_version || 1 }}">
                v{{ item.timedoctor_version || 1 }}
              </VChip>
            </template>

            <!-- Work Status Column (desktop only) -->
            <template v-if="!isMobile" #[`item.work_status`]="{ item }">
              <VChip v-if="item.work_status" size="small" :color="getWorkStatusColor(item.work_status)"
                text-color="white" aria-label="Work status: {{ item.work_status }}">
                {{ item.work_status }}
              </VChip>
              <span v-else class="text-disabled">-</span>
            </template>

            <!-- Status Column -->
            <template #[`item.is_active`]="{ item }">
              <VChip size="small" :color="getUserStatusColor(item.is_active)" text-color="white"
                aria-label="User status: {{ getUserStatusText(item.is_active) }}">
                {{ getUserStatusText(item.is_active) }}
              </VChip>
            </template>

            <!-- Actions Column -->
            <template #[`item.actions`]="{ item }">
              <VBtn icon size="small" variant="text" color="error" @click="confirmRemoveUsers(item.id)"
                aria-label="Remove user from region">
                <VIcon size="20">ri-link-unlink</VIcon>
                <VTooltip activator="parent">Remove from Region</VTooltip>
              </VBtn>
            </template>

            <!-- Empty state -->
            <template #no-data>
              <div class="d-flex flex-column align-center pa-6" role="alert" aria-live="polite">
                <VIcon size="48" color="secondary" icon="ri-user-3-line" class="mb-4" aria-hidden="true" />
                <h3 class="text-h6 font-weight-regular mb-2">No IVA users found</h3>
                <p class="text-secondary text-center mb-4">
                  There are no IVA users assigned to this region. Add some users to get started.
                </p>
                <VBtn color="primary" @click="openAddUserDialog" aria-label="Add IVA users to region">
                  Add IVA Users
                </VBtn>
              </div>
            </template>
          </VDataTable>
        </VCardText>
      </VCard>
    </template>

    <!-- Dialog for adding users -->
    <VDialog v-model="userDialog" max-width="800" scrollable role="dialog" aria-labelledby="add-users-title">
      <VCard>
        <VCardTitle id="add-users-title" class="text-h5 bg-primary text-white d-flex align-center py-3">
          <span>Add IVA Users to {{ region ? region.name : 'Region' }}</span>
          <VSpacer />
          <VBtn icon variant="text" color="white" @click="closeUserDialog" class="ml-2" aria-label="Close dialog">
            <VIcon>ri-close-line</VIcon>
          </VBtn>
        </VCardTitle>

        <VCardText class="pt-4">
          <div v-if="usersLoading" class="d-flex justify-center py-4" aria-live="polite">
            <VProgressCircular indeterminate color="primary" aria-label="Loading available users" />
          </div>

          <div v-else>
            <!-- Search and action buttons at the top -->
            <div class="d-flex flex-wrap align-center mb-4">
              <VTextField v-model="dialogSearchQuery" density="compact" placeholder="Search users..."
                prepend-inner-icon="ri-search-line" hide-details class="flex-grow-1 mr-2 mb-2 mb-md-0" single-line
                clearable aria-label="Search available users" />

              <div class="d-flex">
                <VBtn color="secondary" variant="outlined" class="mr-2" :size="isMobile ? 'small' : 'default'"
                  @click="closeUserDialog" aria-label="Cancel adding users">
                  Cancel
                </VBtn>

                <VBtn color="primary" :disabled="usersLoading || selectedUsers.length === 0"
                  :size="isMobile ? 'small' : 'default'" @click="addUsersToRegion"
                  :aria-label="`Add ${selectedUsers.length} selected users`">
                  <span v-if="!isMobile">Add Selected Users</span>
                  <span v-else>Add Users</span>
                </VBtn>
              </div>
            </div>

            <VDataTable v-model="selectedUsers" :headers="availableUserHeaders" :items="filteredAvailableUsers"
              item-value="id" density="comfortable" show-select class="elevation-1 rounded"
              aria-label="Available users table">
              <!-- ID Column (desktop only) -->
              <template v-if="!isMobile" #[`item.id`]="{ item }">
                <span>{{ item.id }}</span>
              </template>

              <!-- Full Name Column -->
              <template #[`item.full_name`]="{ item }">
                <div class="font-weight-medium">
                  {{ item.full_name || 'No name provided' }}
                  <div v-if="isMobile" class="text-caption">
                    {{ item.email }}
                  </div>
                  <div v-if="isMobile" class="d-flex align-center mt-1">
                    <VChip size="x-small" color="info" class="mr-1">
                      v{{ item.timedoctor_version || 1 }}
                    </VChip>
                    <VChip v-if="item.work_status" size="x-small" :color="getWorkStatusColor(item.work_status)">
                      {{ item.work_status }}
                    </VChip>
                  </div>
                </div>
              </template>

              <!-- Email Column (desktop only) -->
              <template v-if="!isMobile" #[`item.email`]="{ item }">
                <div>{{ item.email }}</div>
              </template>

              <!-- TimeDoctor Version Column (desktop only) -->
              <template v-if="!isMobile" #[`item.timedoctor_version`]="{ item }">
                <VChip size="small" color="info" text-color="white">
                  v{{ item.timedoctor_version || 1 }}
                </VChip>
              </template>

              <!-- Work Status Column (desktop only) -->
              <template v-if="!isMobile" #[`item.work_status`]="{ item }">
                <VChip v-if="item.work_status" size="small" :color="getWorkStatusColor(item.work_status)"
                  text-color="white">
                  {{ item.work_status }}
                </VChip>
                <span v-else class="text-disabled">-</span>
              </template>

              <!-- Empty state -->
              <template #no-data>
                <div class="d-flex flex-column align-center pa-6" role="alert" aria-live="polite">
                  <VIcon size="48" color="secondary" icon="ri-user-3-line" class="mb-4" aria-hidden="true" />
                  <h3 class="text-h6 font-weight-regular mb-2">No available users</h3>
                  <p class="text-secondary text-center">
                    All active IVA users are already assigned to regions or no users are available.
                  </p>
                </div>
              </template>
            </VDataTable>
          </div>
        </VCardText>

        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="closeUserDialog" aria-label="Cancel adding users">
            Cancel
          </VBtn>
          <VBtn color="primary" @click="addUsersToRegion" :disabled="usersLoading || selectedUsers.length === 0"
            :aria-label="`Add ${selectedUsers.length} selected users`">
            Add Selected Users
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog for removing users -->
    <VDialog v-model="removeUserDialog" max-width="500" role="alertdialog" aria-labelledby="remove-users-title">
      <VCard>
        <VCardTitle id="remove-users-title" class="text-h5 bg-error text-white d-flex align-center py-3">
          <span>Remove IVA Users</span>
        </VCardTitle>

        <VCardText class="pt-4">
          Are you sure you want to remove {{ usersToRemove.length > 1 ? 'these IVA users' : 'this IVA user' }} from the
          region?
          <p class="mt-2 text-body-2">
            This will only remove the association between the users and this region. The users themselves will not be
            deleted.
          </p>
        </VCardText>

        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="removeUserDialog = false"
            aria-label="Cancel removing users">
            Cancel
          </VBtn>
          <VBtn color="error" @click="removeUsersFromRegion" aria-label="Confirm removing users">
            Remove
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Snackbar for notifications -->
    <VSnackbar v-model="snackbar" :color="snackbarColor" :timeout="3000" role="alert" aria-live="assertive">
      {{ snackbarText }}
      <template #actions>
        <VBtn icon variant="text" @click="snackbar = false" aria-label="Close notification">
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
