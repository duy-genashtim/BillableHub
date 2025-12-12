<script setup>
import { useAuthStore } from '@/@core/stores/auth'
import { getAvatarUrl } from '@/@core/utils/avatarHelper'
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'

const authStore = useAuthStore()

// Check permission
if (!authStore.hasPermission('manage_users')) {
  throw new Error('Unauthorized')
}

// Reactive data
const users = ref([])
const availableRoles = ref([])
const loading = ref(false)
const searchQuery = ref('')
const roleFilter = ref('')
const selectedUser = ref(null)
const showRoleDialog = ref(false)
const pagination = ref({
  current_page: 1,
  per_page: 10,
  total: 0,
  last_page: 1,
})
const snackbar = ref({
  show: false,
  message: '',
  color: 'success',
})

// Delete dialog state
const deleteDialog = ref(false)
const userToDelete = ref(null)

// Avatar URLs cache
const avatarUrls = ref({})

// Form data
const userRoleForm = ref({
  roles: [],
})

// Computed properties
const filteredUsers = computed(() => {
  let filtered = users.value

  // Search filter
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(user =>
      user.name.toLowerCase().includes(query) ||
      user.email.toLowerCase().includes(query)
    )
  }

  // Role filter
  if (roleFilter.value) {
    filtered = filtered.filter(user =>
      user.roles.some(role => role.name === roleFilter.value)
    )
  }

  return filtered
})

const tableHeaders = [
  { title: 'User', key: 'user_info', sortable: false },
  { title: 'Email', key: 'email', sortable: true },
  { title: 'Roles', key: 'roles', sortable: false },
  { title: 'Permissions', key: 'permissions_count', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
]

// Helper functions
const showSnackbar = (message, color = 'success') => {
  snackbar.value = {
    show: true,
    message,
    color,
  }
}

const getUserRoleChipColor = (roleName) => {
  const colors = {
    admin: 'error',
    hr: 'primary',
    finance: 'success',
    rtl: 'warning',
    artl: 'info',
    iva: 'secondary',
  }
  return colors[roleName] || 'default'
}

// Load avatar URLs for users
const loadAvatarUrls = async () => {
  const urls = {}
  for (const user of users.value) {
    if (user.email) {
      urls[user.email] = await getAvatarUrl(user.email)
    }
  }
  avatarUrls.value = urls
}

// Generate initials from name for fallback
const getUserInitials = (name) => {
  if (!name) return '?'
  return name
    .split(' ')
    .map(word => word.charAt(0))
    .join('')
    .toUpperCase()
    .slice(0, 2)
}

// Generate a consistent color based on user name
const getAvatarColor = (name) => {
  if (!name) return 'primary'

  const colors = ['primary', 'secondary', 'success', 'info', 'warning', 'error']
  const hash = name.split('').reduce((a, b) => {
    a = ((a << 5) - a) + b.charCodeAt(0)
    return a & a
  }, 0)

  return colors[Math.abs(hash) % colors.length]
}

// Methods
const fetchUsers = async (page = 1) => {
  try {
    loading.value = true
    const params = {
      page,
      per_page: pagination.value.per_page,
    }

    if (searchQuery.value) {
      params.search = searchQuery.value
    }

    if (roleFilter.value) {
      params.role = roleFilter.value
    }

    const response = await axios.get('/api/admin/users', { params })
    users.value = response.data.users
    pagination.value = response.data.pagination

    // Load avatar URLs after fetching users
    await loadAvatarUrls()
  } catch (error) {
    console.error('Failed to fetch users:', error)
    showSnackbar('Failed to fetch users', 'error')
  } finally {
    loading.value = false
  }
}

const fetchAvailableRoles = async () => {
  try {
    const response = await axios.get('/api/admin/available-roles')
    availableRoles.value = response.data.roles
    console.log('Available roles fetched:', availableRoles.value);

  } catch (error) {
    console.error('Failed to fetch available roles:', error)
    showSnackbar('Failed to fetch available roles', 'error')
  }
}

const openRoleDialog = async (user) => {
  selectedUser.value = user
  userRoleForm.value = {
    roles: user.roles.map(role => role.name),
  }
  showRoleDialog.value = true
}

const closeRoleDialog = () => {
  showRoleDialog.value = false
  selectedUser.value = null
}

const saveUserRoles = async () => {
  try {
    loading.value = true

    await axios.put(`/api/admin/users/${selectedUser.value.id}/sync-roles`, {
      roles: userRoleForm.value.roles,
    })

    showSnackbar('User roles updated successfully')
    await fetchUsers(pagination.value.current_page)
    closeRoleDialog()
  } catch (error) {
    console.error('Failed to update user roles:', error)
    const message = error.response?.data?.error || 'Failed to update user roles'
    showSnackbar(message, 'error')
  } finally {
    loading.value = false
  }
}

const onPageChange = (page) => {
  fetchUsers(page)
}

const onSearch = () => {
  pagination.value.current_page = 1
  fetchUsers(1)
}

const onRoleFilterChange = () => {
  pagination.value.current_page = 1
  fetchUsers(1)
}

// Delete user methods
const confirmDelete = (user) => {
  userToDelete.value = user
  deleteDialog.value = true
}

const deleteUser = async () => {
  try {
    loading.value = true

    await axios.delete(`/api/admin/users/${userToDelete.value.id}`)

    showSnackbar('User deleted successfully')

    // Refresh users list
    await fetchUsers(pagination.value.current_page)

    // Close dialog
    deleteDialog.value = false
    userToDelete.value = null
  } catch (error) {
    console.error('Failed to delete user:', error)
    const message = error.response?.data?.error || 'Failed to delete user'
    showSnackbar(message, 'error')
  } finally {
    loading.value = false
  }
}

const closeDeleteDialog = () => {
  deleteDialog.value = false
  userToDelete.value = null
}

// Lifecycle hooks
onMounted(async () => {
  await Promise.all([fetchUsers(), fetchAvailableRoles()])
})
</script>

<template>
  <div>
    <!-- Page Header -->
    <VCard class="mb-6">
      <VCardTitle class="pa-6">
        <div class="d-flex justify-space-between align-center">
          <div>
            <h2 class="text-h4 mb-2">User Management</h2>
            <p class="text-body-1 ma-0">Manage user roles and permissions</p>
          </div>
          <VChip color="info" variant="tonal">
            {{ pagination.total }} users
          </VChip>
        </div>
      </VCardTitle>
    </VCard>

    <!-- Search and Filters -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" md="6">
            <VTextField v-model="searchQuery" prepend-inner-icon="ri-search-line"
              placeholder="Search users by name or email..." clearable variant="outlined" density="compact"
              @input="onSearch" />
          </VCol>
          <VCol cols="12" md="4">
            <VSelect v-model="roleFilter" :items="[
              { title: 'All Roles', value: '' },
              ...availableRoles.map(role => ({
                title: role.display_name,
                value: role.name
              }))
            ]" label="Filter by Role" variant="outlined" density="compact" clearable
              @update:model-value="onRoleFilterChange" />
          </VCol>
          <VCol cols="12" md="2">
            <VBtn block variant="outlined" prepend-icon="ri-refresh-line" @click="fetchUsers(pagination.current_page)"
              :loading="loading">
              Refresh
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Users Table -->
    <VCard>
      <VCardText>
        <VProgressLinear v-if="loading" indeterminate color="primary" class="mb-4" />

        <VDataTable :headers="tableHeaders" :items="filteredUsers" :loading="loading" item-key="id" class="elevation-0"
          :items-per-page="pagination.per_page" hide-default-footer>
          <template #item.user_info="{ item }">
            <div class="d-flex align-center gap-3">
              <VAvatar :color="avatarUrls[item.email] ? undefined : getAvatarColor(item.name)" size="40">
                <VImg v-if="avatarUrls[item.email]" :src="avatarUrls[item.email]" :alt="item.name" />
                <span v-else class="text-white font-weight-bold">
                  {{ getUserInitials(item.name) }}
                </span>
              </VAvatar>
              <div>
                <div class="font-weight-medium">{{ item.name }}</div>
                <VChip v-if="item.is_super_admin" color="error" variant="tonal" size="x-small" class="mt-1">
                  Super Admin
                </VChip>
              </div>
            </div>
          </template>

          <template #item.email="{ item }">
            <span class="text-body-2">{{ item.email }}</span>
          </template>

          <template #item.roles="{ item }">
            <div class="d-flex flex-wrap gap-1">
              <VChip v-for="role in item.roles" :key="role.name" :color="getUserRoleChipColor(role.name)"
                variant="tonal" size="small">
                {{ role.display_name }}
              </VChip>
              <VChip v-if="item.roles.length === 0" color="warning" variant="outlined" size="small">
                No roles
              </VChip>
            </div>
          </template>

          <template #item.permissions_count="{ item }">
            <VTooltip location="top">
              <template #activator="{ props }">
                <VChip v-bind="props" color="info" variant="outlined" size="small">
                  {{ item.permissions?.length || 0 }} permissions
                </VChip>
              </template>
              <VCard class="pa-3" max-width="400" elevation="8">
                <div class="font-weight-bold mb-2 text-body-1">Permissions:</div>
                <div v-if="item.permissions?.length" class="permission-list">
                  <div v-for="permission in item.permissions.slice(0, 10)" :key="permission.name"
                    class="text-body-2 mb-1">
                    • {{ permission.display_name }}
                  </div>
                  <div v-if="item.permissions.length > 10" class="text-body-2 font-italic text-medium-emphasis">
                    ... and {{ item.permissions.length - 10 }} more
                  </div>
                </div>
                <div v-else class="text-body-2 text-medium-emphasis">
                  No permissions assigned
                </div>
              </VCard>
            </VTooltip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <!-- Manage Roles Button -->
              <VTooltip text="Manage Roles">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    icon="ri-user-settings-line"
                    size="small"
                    variant="text"
                    color="primary"
                    @click="openRoleDialog(item)"
                    :disabled="item.is_super_admin && !authStore.isSuperAdmin"
                  />
                </template>
              </VTooltip>

              <!-- Delete Button -->
              <VTooltip :text="item.is_super_admin ? 'Cannot delete Super Admin' : 'Delete User'">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    icon="ri-delete-bin-line"
                    size="small"
                    variant="text"
                    color="error"
                    @click="confirmDelete(item)"
                    :disabled="item.is_super_admin"
                  />
                </template>
              </VTooltip>
            </div>
          </template>

          <template #no-data>
            <div class="text-center pa-8">
              <VIcon icon="ri-user-line" size="48" color="disabled" class="mb-4" />
              <div class="text-h6 mb-2">No users found</div>
              <div class="text-body-2">
                {{ searchQuery || roleFilter ? 'Try adjusting your search or filters' : 'No users available' }}
              </div>
            </div>
          </template>
        </VDataTable>

        <!-- Pagination -->
        <div class="d-flex justify-center mt-6" v-if="pagination.last_page > 1">
          <VPagination v-model="pagination.current_page" :length="pagination.last_page"
            @update:model-value="onPageChange" :disabled="loading" />
        </div>
      </VCardText>
    </VCard>

    <!-- User Role Management Dialog -->
    <VDialog v-model="showRoleDialog" max-width="600" persistent>
      <VCard v-if="selectedUser">
        <VCardTitle class="pa-6">
          <div class="d-flex align-center gap-3">
            <VAvatar :color="avatarUrls[selectedUser.email] ? undefined : getAvatarColor(selectedUser.name)" size="40">
              <VImg v-if="avatarUrls[selectedUser.email]" :src="avatarUrls[selectedUser.email]"
                :alt="selectedUser.name" />
              <span v-else class="text-white font-weight-bold">
                {{ getUserInitials(selectedUser.name) }}
              </span>
            </VAvatar>
            <div>
              <h3>{{ selectedUser.name }}</h3>
              <p class="text-body-2 ma-0">{{ selectedUser.email }}</p>
            </div>
          </div>
        </VCardTitle>

        <VDivider />

        <VCardText class="pa-6">
          <div class="mb-4">
            <h4 class="text-h6 mb-3">Assign Roles</h4>
            <p class="text-body-2 mb-4">
              Select the roles you want to assign to this user. Each role comes with its own set of permissions.
            </p>

            <VAlert v-if="selectedUser.is_super_admin" type="info" variant="tonal" class="mb-4">
              This is a Super Admin user. Role changes are restricted.
            </VAlert>
          </div>

          <VRow>
            <VCol v-for="role in availableRoles" :key="role.name" cols="12" md="6">
              <VCheckbox v-model="userRoleForm.roles" :value="role.name" :label="role.display_name"
                :disabled="selectedUser.is_super_admin && !authStore.isSuperAdmin" density="compact" hide-details>
                <template #label>
                  <div>
                    <div class="font-weight-medium">{{ role.display_name }}</div>
                    <VChip :color="getUserRoleChipColor(role.name)" variant="tonal" size="x-small" class="mt-1">
                      {{ role.name }}
                    </VChip>
                  </div>
                </template>
              </VCheckbox>
            </VCol>
          </VRow>

          <VAlert v-if="userRoleForm.roles.length === 0" type="warning" variant="tonal" class="mt-4">
            No roles selected. User will have no system access.
          </VAlert>

          <VAlert v-else type="info" variant="tonal" class="mt-4">
            {{ userRoleForm.roles.length }} role(s) selected
          </VAlert>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-6">
          <VSpacer />
          <VBtn variant="outlined" @click="closeRoleDialog" :disabled="loading">
            Cancel
          </VBtn>
          <VBtn color="primary" @click="saveUserRoles" :loading="loading"
            :disabled="selectedUser.is_super_admin && !authStore.isSuperAdmin">
            Save Changes
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Confirmation Dialog -->
    <VDialog v-model="deleteDialog" max-width="600" persistent>
      <VCard v-if="userToDelete">
        <VCardTitle class="text-h5 bg-error text-white pa-6">
          <VIcon icon="ri-error-warning-line" class="me-2" />
          Delete User
        </VCardTitle>

        <VDivider />

        <VCardText class="pa-6">
          <VAlert type="error" variant="tonal" class="mb-4">
            <VAlertTitle class="mb-2">
              Warning: This action cannot be undone!
            </VAlertTitle>
            <p class="mb-0">
              Are you sure you want to permanently delete this user? This will remove:
            </p>
          </VAlert>

          <!-- User Details -->
          <div class="mb-4 pa-4 bg-grey-lighten-4 rounded">
            <div class="d-flex align-center gap-3 mb-3">
              <VAvatar
                :color="avatarUrls[userToDelete.email] ? undefined : getAvatarColor(userToDelete.name)"
                size="48"
              >
                <VImg
                  v-if="avatarUrls[userToDelete.email]"
                  :src="avatarUrls[userToDelete.email]"
                  :alt="userToDelete.name"
                />
                <span v-else class="text-white font-weight-bold text-h6">
                  {{ getUserInitials(userToDelete.name) }}
                </span>
              </VAvatar>
              <div>
                <div class="font-weight-bold text-h6">{{ userToDelete.name }}</div>
                <div class="text-body-2 text-medium-emphasis">{{ userToDelete.email }}</div>
              </div>
            </div>

            <!-- Roles Info -->
            <div class="mb-2">
              <span class="font-weight-medium">Roles:</span>
              <div class="d-flex flex-wrap gap-1 mt-1">
                <VChip
                  v-for="role in userToDelete.roles"
                  :key="role.name"
                  :color="getUserRoleChipColor(role.name)"
                  variant="tonal"
                  size="small"
                >
                  {{ role.display_name }}
                </VChip>
                <VChip v-if="userToDelete.roles.length === 0" color="warning" variant="outlined" size="small">
                  No roles
                </VChip>
              </div>
            </div>

            <!-- Permissions Info -->
            <div>
              <span class="font-weight-medium">Permissions:</span>
              <VChip color="info" variant="tonal" size="small" class="ml-2">
                {{ userToDelete.permissions?.length || 0 }} permissions
              </VChip>
            </div>
          </div>

          <!-- Warning Message -->
          <VAlert type="warning" variant="outlined" density="compact">
            <p class="text-body-2 mb-0">
              This will permanently delete:
            </p>
            <ul class="text-body-2 mb-0 mt-2">
              <li>The user account</li>
              <li>All assigned roles</li>
              <li>All granted permissions</li>
            </ul>
            <p class="text-body-2 font-weight-bold mb-0 mt-2">
              This action cannot be undone.
            </p>
          </VAlert>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-6">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="outlined"
            @click="closeDeleteDialog"
            :disabled="loading"
          >
            Cancel
          </VBtn>
          <VBtn
            color="error"
            @click="deleteUser"
            :loading="loading"
          >
            Delete User
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Snackbar -->
    <VSnackbar v-model="snackbar.show" :color="snackbar.color" timeout="4000">
      {{ snackbar.message }}

      <template #actions>
        <VBtn variant="text" @click="snackbar.show = false">
          Close
        </VBtn>
      </template>
    </VSnackbar>
  </div>
</template>

<style scoped>
.v-data-table {
  border-radius: 8px;
}

.v-chip {
  font-weight: 500;
}

.v-avatar {
  border: 2px solid rgba(var(--v-theme-surface), 0.12);
}

.permission-list {
  max-block-size: 300px;
  overflow-y: auto;
}

.text-caption {
  line-height: 1.4;
}

/* Tooltip card styling for better visibility */
:deep(.v-overlay__content .v-card) {
  border: 1px solid rgba(var(--v-border-color), 0.12);
  background: rgb(var(--v-theme-surface)) !important;
  color: rgb(var(--v-theme-on-surface)) !important;
}

:deep(.v-overlay__content .v-card .text-medium-emphasis) {
  color: rgba(var(--v-theme-on-surface), 0.6) !important;
}
</style>
