<script setup>
import { useAuthStore } from '@/@core/stores/auth'
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'

const authStore = useAuthStore()

// Check permission
if (!authStore.hasPermission('manage_roles')) {
  throw new Error('Unauthorized')
}

// Reactive data
const roles = ref([])
const permissions = ref([])
const loading = ref(false)
const searchQuery = ref('')
const selectedRole = ref(null)
const showDialog = ref(false)
const showPermissionDialog = ref(false)
const snackbar = ref({
  show: false,
  message: '',
  color: 'success',
})

// Form data
const roleForm = ref({
  name: '',
  permissions: [],
})

// Validation rules
const nameRules = [
  v => !!v || 'Role name is required',
  v => (v && v.length >= 3) || 'Role name must be at least 3 characters',
  v => /^[a-z_]+$/.test(v) || 'Role name must contain only lowercase letters and underscores',
]

// Computed properties
const filteredRoles = computed(() => {
  if (!searchQuery.value) return roles.value
  return roles.value.filter(role => 
    role.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
    role.display_name?.toLowerCase().includes(searchQuery.value.toLowerCase())
  )
})

const permissionsByCategory = computed(() => {
  const categories = {}
  permissions.value.forEach(permission => {
    const category = getCategoryFromPermission(permission.name)
    if (!categories[category]) {
      categories[category] = []
    }
    categories[category].push(permission)
  })
  return categories
})

// Generate display name from role name
const generatedDisplayName = computed(() => {
  if (!roleForm.value.name) return ''
  return roleForm.value.name.toUpperCase().replace(/_/g, ' ')
})

// Helper functions
const getCategoryFromPermission = (permissionName) => {
  if (permissionName.includes('manage_')) return 'Management'
  if (permissionName.includes('view_')) return 'View'
  if (permissionName.includes('create_') || permissionName.includes('edit_') || permissionName.includes('delete_')) return 'CRUD'
  if (permissionName.includes('approve_')) return 'Approval'
  if (permissionName.includes('import_') || permissionName.includes('export_')) return 'Import/Export'
  if (permissionName.includes('team_')) return 'Team'
  if (permissionName.includes('own_')) return 'Personal'
  return 'Other'
}

const showSnackbar = (message, color = 'success') => {
  snackbar.value = {
    show: true,
    message,
    color,
  }
}

// Methods
const fetchRoles = async () => {
  try {
    loading.value = true
    const response = await axios.get('/api/admin/roles')
    roles.value = response.data.roles
  } catch (error) {
    console.error('Failed to fetch roles:', error)
    showSnackbar('Failed to fetch roles', 'error')
  } finally {
    loading.value = false
  }
}

const fetchPermissions = async () => {
  try {
    const response = await axios.get('/api/admin/permissions')
    permissions.value = response.data.permissions
  } catch (error) {
    console.error('Failed to fetch permissions:', error)
    showSnackbar('Failed to fetch permissions', 'error')
  }
}

const openCreateDialog = () => {
  roleForm.value = {
    name: '',
    permissions: [],
  }
  showDialog.value = true
}

const openPermissionDialog = (role) => {
  selectedRole.value = role
  roleForm.value = {
    permissions: role.permissions.map(p => p.name),
  }
  showPermissionDialog.value = true
}

const closeDialog = () => {
  showDialog.value = false
  showPermissionDialog.value = false
  selectedRole.value = null
}

const saveRole = async () => {
  try {
    loading.value = true
    
    await axios.post('/api/admin/roles', roleForm.value)
    showSnackbar('Role created successfully')
    
    await fetchRoles()
    closeDialog()
  } catch (error) {
    console.error('Failed to save role:', error)
    const message = error.response?.data?.error || 'Failed to save role'
    showSnackbar(message, 'error')
  } finally {
    loading.value = false
  }
}

const savePermissions = async () => {
  try {
    loading.value = true
    
    await axios.post(`/api/admin/roles/${selectedRole.value.id}/assign-permissions`, {
      permissions: roleForm.value.permissions,
    })
    
    showSnackbar('Permissions updated successfully')
    await fetchRoles()
    closeDialog()
  } catch (error) {
    console.error('Failed to save permissions:', error)
    const message = error.response?.data?.error || 'Failed to save permissions'
    showSnackbar(message, 'error')
  } finally {
    loading.value = false
  }
}

const deleteRole = async (role) => {
  if (role.name === 'admin') {
    showSnackbar('Cannot delete admin role', 'error')
    return
  }
  
  if (!confirm(`Are you sure you want to delete the role "${role.display_name || role.name}"?`)) {
    return
  }
  
  try {
    loading.value = true
    await axios.delete(`/api/admin/roles/${role.id}`)
    showSnackbar('Role deleted successfully')
    await fetchRoles()
  } catch (error) {
    console.error('Failed to delete role:', error)
    const message = error.response?.data?.error || 'Failed to delete role'
    showSnackbar(message, 'error')
  } finally {
    loading.value = false
  }
}

// Lifecycle hooks
onMounted(async () => {
  await Promise.all([fetchRoles(), fetchPermissions()])
})
</script>

<template>
  <div>
    <!-- Page Header -->
    <VCard class="mb-6">
      <VCardTitle class="pa-6">
        <div class="d-flex justify-space-between align-center">
          <div>
            <h2 class="text-h4 mb-2">Role Management</h2>
            <p class="text-body-1 ma-0">Manage system roles and their permissions</p>
          </div>
          <VBtn
            color="primary"
            prepend-icon="ri-add-line"
            @click="openCreateDialog"
            :loading="loading"
          >
            Create Role
          </VBtn>
        </div>
      </VCardTitle>
    </VCard>

    <!-- Search and Filters -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" md="6">
            <VTextField
              v-model="searchQuery"
              prepend-inner-icon="ri-search-line"
              placeholder="Search roles..."
              clearable
              variant="outlined"
              density="compact"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Roles Table -->
    <VCard>
      <VCardText>
        <VProgressLinear
          v-if="loading"
          indeterminate
          color="primary"
          class="mb-4"
        />
        
        <VDataTable
          :headers="[
            { title: 'Role Name', key: 'name', sortable: true },
            { title: 'Display Name', key: 'display_name', sortable: true },
            { title: 'Permissions', key: 'permissions_count', sortable: false },
            { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
          ]"
          :items="filteredRoles"
          :loading="loading"
          item-key="id"
          class="elevation-0"
        >
          <template #item.name="{ item }">
            <VChip
              :color="item.name === 'admin' ? 'error' : 'primary'"
              variant="outlined"
              size="small"
            >
              {{ item.name }}
            </VChip>
          </template>

          <template #item.display_name="{ item }">
            <span class="font-weight-medium">{{ (item.display_name || item.name).toUpperCase() }}</span>
          </template>

          <template #item.permissions_count="{ item }">
            <VChip
              color="info"
              variant="tonal"
              size="small"
            >
              {{ item.permissions?.length || 0 }} permissions
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-2 justify-end">
              <VTooltip text="Manage Permissions">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    icon="ri-shield-user-line"
                    size="small"
                    variant="text"
                    color="info"
                    @click="openPermissionDialog(item)"
                  />
                </template>
              </VTooltip>
              
              <VTooltip text="Delete Role">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    icon="ri-delete-bin-line"
                    size="small"
                    variant="text"
                    color="error"
                    :disabled="item.name === 'admin'"
                    @click="deleteRole(item)"
                  />
                </template>
              </VTooltip>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <!-- Create Role Dialog -->
    <VDialog
      v-model="showDialog"
      max-width="600"
      persistent
    >
      <VCard>
        <VCardTitle class="pa-6">
          <div class="d-flex align-center gap-3">
            <VIcon
              icon="ri-add-line"
              size="24"
            />
            <span>Create Role</span>
          </div>
        </VCardTitle>

        <VDivider />

        <VCardText class="pa-6">
          <VForm @submit.prevent="saveRole">
            <VRow>
              <VCol cols="12">
                <VTextField
                  v-model="roleForm.name"
                  label="Role Name"
                  placeholder="e.g., hr_manager"
                  :rules="nameRules"
                  variant="outlined"
                  hint="Lowercase letters and underscores only"
                  persistent-hint
                  required
                />
              </VCol>

              <VCol cols="12" v-if="roleForm.name">
                <VAlert
                  type="info"
                  variant="tonal"
                  class="mb-4"
                >
                  <div>
                    <strong>Display Name Preview:</strong> {{ generatedDisplayName }}
                  </div>
                  <div class="text-caption mt-1">
                    The display name will be automatically generated as uppercase with spaces
                  </div>
                </VAlert>
              </VCol>

              <VCol cols="12">
                <h4 class="text-h6 mb-4">Permissions</h4>
                <VExpansionPanels multiple>
                  <VExpansionPanel
                    v-for="(categoryPermissions, category) in permissionsByCategory"
                    :key="category"
                    :title="category"
                  >
                    <VExpansionPanelText>
                      <div class="d-flex flex-wrap gap-3">
                        <VCheckbox
                          v-for="permission in categoryPermissions"
                          :key="permission.name"
                          v-model="roleForm.permissions"
                          :label="permission.display_name"
                          :value="permission.name"
                          density="compact"
                          hide-details
                        />
                      </div>
                    </VExpansionPanelText>
                  </VExpansionPanel>
                </VExpansionPanels>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-6">
          <VSpacer />
          <VBtn
            variant="outlined"
            @click="closeDialog"
            :disabled="loading"
          >
            Cancel
          </VBtn>
          <VBtn
            color="primary"
            @click="saveRole"
            :loading="loading"
          >
            Create
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Manage Permissions Dialog -->
    <VDialog
      v-model="showPermissionDialog"
      max-width="800"
      persistent
    >
      <VCard>
        <VCardTitle class="pa-6">
          <div class="d-flex align-center gap-3">
            <VIcon icon="ri-shield-user-line" size="24" />
            <div>
              <h3>Manage Permissions</h3>
              <p class="text-body-2 ma-0">{{ selectedRole?.display_name || selectedRole?.name }}</p>
            </div>
          </div>
        </VCardTitle>

        <VDivider />

        <VCardText class="pa-6">
          <VExpansionPanels multiple>
            <VExpansionPanel
              v-for="(categoryPermissions, category) in permissionsByCategory"
              :key="category"
              :title="`${category} (${categoryPermissions.filter(p => roleForm.permissions.includes(p.name)).length}/${categoryPermissions.length})`"
            >
              <VExpansionPanelText>
                <div class="d-flex justify-space-between align-center mb-4">
                  <VBtn
                    variant="outlined"
                    size="small"
                    @click="roleForm.permissions.push(...categoryPermissions.filter(p => !roleForm.permissions.includes(p.name)).map(p => p.name))"
                  >
                    Select All
                  </VBtn>
                  <VBtn
                    variant="outlined"
                    size="small"
                    @click="roleForm.permissions = roleForm.permissions.filter(p => !categoryPermissions.map(cp => cp.name).includes(p))"
                  >
                    Deselect All
                  </VBtn>
                </div>
                
                <VRow>
                  <VCol
                    v-for="permission in categoryPermissions"
                    :key="permission.name"
                    cols="12"
                    md="6"
                  >
                    <VCheckbox
                      v-model="roleForm.permissions"
                      :label="permission.display_name"
                      :value="permission.name"
                      density="compact"
                      hide-details
                    />
                  </VCol>
                </VRow>
              </VExpansionPanelText>
            </VExpansionPanel>
          </VExpansionPanels>

          <VAlert
            v-if="roleForm.permissions.length === 0"
            type="warning"
            variant="tonal"
            class="mt-4"
          >
            No permissions selected. Users with this role will have no access.
          </VAlert>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-6">
          <div class="text-body-2">
            {{ roleForm.permissions.length }} permissions selected
          </div>
          <VSpacer />
          <VBtn
            variant="outlined"
            @click="closeDialog"
            :disabled="loading"
          >
            Cancel
          </VBtn>
          <VBtn
            color="primary"
            @click="savePermissions"
            :loading="loading"
          >
            Save Permissions
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Snackbar -->
    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      timeout="3000"
    >
      {{ snackbar.message }}
      
      <template #actions>
        <VBtn
          variant="text"
          @click="snackbar.show = false"
        >
          Close
        </VBtn>
      </template>
    </VSnackbar>
  </div>
</template>

<style scoped>
.v-expansion-panel-title {
  font-weight: 500;
}

.v-data-table {
  border-radius: 8px;
}

.v-chip {
  font-weight: 500;
}
</style>
