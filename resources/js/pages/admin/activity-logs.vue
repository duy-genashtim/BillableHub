<script setup>
import { useAuthStore } from '@/@core/stores/auth'
import { getAvatarUrl } from '@/@core/utils/avatarHelper'
import axios from 'axios'
import { computed, onMounted, ref, watch } from 'vue'

const authStore = useAuthStore()

// Check permission
if (!authStore.hasPermission('view_activity_logs')) {
  throw new Error('Unauthorized')
}

// Reactive data
const logs = ref([])
const filterOptions = ref({
  actions: [],
  users: [],
})
const loading = ref(false)
const exporting = ref(false)
const searchQuery = ref('')
const filters = ref({
  action: '',
  email: '',
  user_id: '',
  start_date: '',
  end_date: '',
})
const sorting = ref({
  sort_by: 'created_at',
  sort_direction: 'desc',
})
const selectedLog = ref(null)
const showDetailDialog = ref(false)
const pagination = ref({
  current_page: 1,
  per_page: 20,
  total: 0,
  last_page: 1,
})
const snackbar = ref({
  show: false,
  message: '',
  color: 'success',
})

// Avatar URLs cache
const avatarUrls = ref({})

// Computed properties
const tableHeaders = [
  { title: 'Date & Time', key: 'created_at', sortable: true },
  { title: 'User', key: 'user_info', sortable: false },
  { title: 'Action', key: 'action', sortable: true },
  { title: 'Description', key: 'description', sortable: false },
  { title: 'Module', key: 'module', sortable: false },
  { title: 'IP Address', key: 'ip_address', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
]

const hasActiveFilters = computed(() => {
  return !!(
    filters.value.action ||
    filters.value.email ||
    filters.value.user_id ||
    filters.value.start_date ||
    filters.value.end_date ||
    searchQuery.value
  )
})

const hasData = computed(() => {
  return logs.value.length > 0
})

// Helper functions
const showSnackbar = (message, color = 'success') => {
  snackbar.value = {
    show: true,
    message,
    color,
  }
}

const getActionChipColor = (action) => {
  const colors = {
    create: 'success',
    update: 'primary',
    delete: 'error',
    assign: 'info',
    unassign: 'warning',
    approve: 'success',
    reject: 'error',
    import: 'purple',
    export: 'indigo',
    login: 'green',
    logout: 'orange',
    view: 'blue-grey',
  }
  return colors[action] || 'default'
}

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  })
}

const truncateText = (text, maxLength = 50) => {
  if (!text) return ''
  return text.length > maxLength ? text.substring(0, maxLength) + '...' : text
}

// Load avatar URLs for users in logs
const loadAvatarUrls = async () => {
  const urls = {}
  const uniqueEmails = [...new Set(logs.value.map(log => log.email).filter(Boolean))]

  for (const email of uniqueEmails) {
    urls[email] = await getAvatarUrl(email)
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
const fetchLogs = async (page = 1) => {
  try {
    loading.value = true
    const params = {
      page,
      per_page: pagination.value.per_page,
      ...sorting.value,
    }

    // Add filters only if they have values
    if (searchQuery.value?.trim()) params.search = searchQuery.value.trim()
    if (filters.value.action) params.action = filters.value.action
    if (filters.value.email?.trim()) params.email = filters.value.email.trim()
    if (filters.value.user_id) params.user_id = filters.value.user_id
    if (filters.value.start_date) params.start_date = filters.value.start_date
    if (filters.value.end_date) params.end_date = filters.value.end_date

    const response = await axios.get('/api/admin/activity-logs', { params })
    logs.value = response.data.logs
    pagination.value = response.data.pagination

    // Load avatar URLs after fetching logs
    await loadAvatarUrls()
  } catch (error) {
    console.error('Failed to fetch activity logs:', error)
    showSnackbar('Failed to fetch activity logs', 'error')
    logs.value = []
  } finally {
    loading.value = false
  }
}

const fetchFilterOptions = async () => {
  try {
    const response = await axios.get('/api/admin/activity-logs-filter-options')
    filterOptions.value = response.data
  } catch (error) {
    console.error('Failed to fetch filter options:', error)
    filterOptions.value = { actions: [], users: [] }
  }
}

const exportLogs = async () => {
  try {
    exporting.value = true

    const params = {
      ...sorting.value,
    }

    // Add current filters to export
    if (searchQuery.value?.trim()) params.search = searchQuery.value.trim()
    if (filters.value.action) params.action = filters.value.action
    if (filters.value.email?.trim()) params.email = filters.value.email.trim()
    if (filters.value.user_id) params.user_id = filters.value.user_id
    if (filters.value.start_date) params.start_date = filters.value.start_date
    if (filters.value.end_date) params.end_date = filters.value.end_date

    const response = await axios.get('/api/admin/activity-logs-export', {
      params,
      responseType: 'blob',
    })

    // Create download link
    const blob = new Blob([response.data], { type: 'text/csv' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url

    // Get filename from response headers or generate one
    const contentDisposition = response.headers['content-disposition']
    const filename = contentDisposition
      ? contentDisposition.split('filename="')[1].split('"')[0]
      : `activity_logs_${new Date().toISOString().split('T')[0]}.csv`

    link.download = filename
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)

    showSnackbar('Activity logs exported successfully')
  } catch (error) {
    console.error('Failed to export logs:', error)
    showSnackbar('Failed to export logs', 'error')
  } finally {
    exporting.value = false
  }
}

const openDetailDialog = (log) => {
  selectedLog.value = log
  showDetailDialog.value = true
}

const closeDetailDialog = () => {
  showDetailDialog.value = false
  selectedLog.value = null
}

const clearFilters = () => {
  filters.value = {
    action: '',
    email: '',
    user_id: '',
    start_date: '',
    end_date: '',
  }
  searchQuery.value = ''
  pagination.value.current_page = 1
  fetchLogs(1)
}

const onPageChange = (page) => {
  fetchLogs(page)
}

const onSearch = () => {
  pagination.value.current_page = 1
  fetchLogs(1)
}

const onSort = ({ key, order }) => {
  if (key) {
    sorting.value.sort_by = key
    sorting.value.sort_direction = order === 'desc' ? 'desc' : 'asc'
    pagination.value.current_page = 1
    fetchLogs(1)
  }
}

const getSelectedActionLabel = () => {
  if (!filters.value.action) return ''
  const action = filterOptions.value.actions.find(a => a.value === filters.value.action)
  return action ? action.title : ''
}

const getSelectedUserLabel = () => {
  if (!filters.value.user_id) return ''
  const user = filterOptions.value.users.find(u => u.value == filters.value.user_id)
  return user ? user.title : ''
}

// Watch for filter changes
watch([filters], () => {
  pagination.value.current_page = 1
  fetchLogs(1)
}, { deep: true })

// Lifecycle hooks
onMounted(async () => {
  await Promise.all([fetchFilterOptions(), fetchLogs()])
})
</script>

<template>
  <div>
    <VBreadcrumbs :items="[
      { title: 'Home', to: '/' },
      { title: 'Activity Logs', disabled: true }
    ]" class="mb-6" aria-label="Breadcrumb navigation" />
    <!-- Page Header -->
    <VCard class="mb-6">
      <VCardTitle class="pa-6">
        <div class="d-flex justify-space-between align-center">
          <div>
            <h2 class="text-h4 mb-2">Activity Logs</h2>
            <p class="text-body-1 ma-0">View and export system activity logs</p>
          </div>
          <div class="d-flex gap-3 align-center">
            <VChip color="info" variant="tonal">
              {{ pagination.total }} records
            </VChip>
            <VBtn color="primary" prepend-icon="ri-download-line" @click="exportLogs" :loading="exporting"
              :disabled="!hasData" variant="outlined">
              Export CSV
            </VBtn>
          </div>
        </div>
      </VCardTitle>
    </VCard>

    <!-- Search and Filters -->
    <VCard class="mb-6">
      <VCardTitle class="pa-4 pb-2">
        <h4 class="text-h6">Search & Filters</h4>
      </VCardTitle>
      <VCardText class="pt-2">
        <VRow>
          <VCol cols="12" lg="4">
            <VTextField v-model="searchQuery" prepend-inner-icon="ri-search-line" placeholder="Search logs..." clearable
              variant="outlined" density="compact" @update:model-value="onSearch" />
          </VCol>
          <VCol cols="12" md="6" lg="2">
            <VSelect v-model="filters.action" :items="[
              { title: 'All Actions', value: '' },
              ...filterOptions.actions
            ]" label="Action" variant="outlined" density="compact" clearable />
          </VCol>
          <VCol cols="12" md="6" lg="2">
            <VSelect v-model="filters.user_id" :items="[
              { title: 'All Users', value: '' },
              ...filterOptions.users
            ]" label="User" variant="outlined" density="compact" clearable />
          </VCol>
          <VCol cols="12" md="6" lg="2">
            <VTextField v-model="filters.start_date" type="date" label="Start Date" variant="outlined" density="compact"
              clearable />
          </VCol>
          <VCol cols="12" md="6" lg="2">
            <VTextField v-model="filters.end_date" type="date" label="End Date" variant="outlined" density="compact"
              clearable />
          </VCol>
        </VRow>

        <!-- Active Filters Display -->
        <VRow v-if="hasActiveFilters" class="mt-2">
          <VCol cols="12">
            <div class="d-flex gap-2 align-center flex-wrap">
              <span class="text-body-2 font-weight-medium">Active filters:</span>

              <VChip v-if="searchQuery" closable size="small" color="primary" variant="tonal"
                @click:close="searchQuery = ''; onSearch()">
                Search: "{{ searchQuery }}"
              </VChip>

              <VChip v-if="filters.action" closable size="small" color="info" variant="tonal"
                @click:close="filters.action = ''">
                Action: {{ getSelectedActionLabel() }}
              </VChip>

              <VChip v-if="filters.user_id" closable size="small" color="success" variant="tonal"
                @click:close="filters.user_id = ''">
                User: {{ getSelectedUserLabel() }}
              </VChip>

              <VChip v-if="filters.start_date" closable size="small" color="warning" variant="tonal"
                @click:close="filters.start_date = ''">
                From: {{ filters.start_date }}
              </VChip>

              <VChip v-if="filters.end_date" closable size="small" color="warning" variant="tonal"
                @click:close="filters.end_date = ''">
                To: {{ filters.end_date }}
              </VChip>

              <VBtn variant="text" size="small" color="error" @click="clearFilters">
                Clear All
              </VBtn>
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Activity Logs Table -->
    <VCard>
      <VCardText>
        <VProgressLinear v-if="loading" indeterminate color="primary" class="mb-4" />

        <VDataTable :headers="tableHeaders" :items="logs" :loading="loading" item-key="id" class="elevation-0"
          :items-per-page="pagination.per_page" :server-items-length="pagination.total" hide-default-footer
          @update:sort-by="onSort">
          <template #item.created_at="{ item }">
            <div class="text-body-2">
              <div>{{ formatDate(item.created_at).split(',')[0] }}</div>
              <div class="text-caption text-medium-emphasis">
                {{ formatDate(item.created_at).split(',')[1] }}
              </div>
            </div>
          </template>

          <template #item.user_info="{ item }">
            <div v-if="item.user_name" class="d-flex align-center gap-2">
              <VAvatar :color="avatarUrls[item.email] ? undefined : getAvatarColor(item.user_name)" size="32">
                <VImg v-if="avatarUrls[item.email]" :src="avatarUrls[item.email]" :alt="item.user_name" />
                <span v-else class="text-white text-caption font-weight-bold">
                  {{ getUserInitials(item.user_name) }}
                </span>
              </VAvatar>
              <div>
                <div class="text-body-2 font-weight-medium">{{ item.user_name }}</div>
                <div class="text-caption text-medium-emphasis">{{ item.email }}</div>
              </div>
            </div>
            <div v-else class="text-body-2">
              <div class="text-medium-emphasis">Unknown User</div>
              <div class="text-caption">{{ item.email || 'N/A' }}</div>
            </div>
          </template>

          <template #item.action="{ item }">
            <VChip :color="getActionChipColor(item.action)" variant="tonal" size="small">
              {{ item.action_label || item.action }}
            </VChip>
          </template>

          <template #item.description="{ item }">
            <VTooltip v-if="item.description && item.description.length > 50">
              <template #activator="{ props }">
                <span v-bind="props" class="text-body-2">
                  {{ truncateText(item.description) }}
                </span>
              </template>
              <div class="pa-2 max-width-300">
                {{ item.description }}
              </div>
            </VTooltip>
            <span v-else class="text-body-2">{{ item.description || '—' }}</span>
          </template>

          <template #item.module="{ item }">
            <VChip v-if="item.module" variant="outlined" size="small">
              {{ item.module }}
            </VChip>
            <span v-else class="text-medium-emphasis">—</span>
          </template>

          <template #item.ip_address="{ item }">
            <span class="text-body-2 font-mono">
              {{ item.ip_address || '—' }}
            </span>
          </template>

          <template #item.actions="{ item }">
            <VTooltip text="View Details">
              <template #activator="{ props }">
                <VBtn v-bind="props" icon="ri-eye-line" size="small" variant="text" color="primary"
                  @click="openDetailDialog(item)" />
              </template>
            </VTooltip>
          </template>

          <template #no-data>
            <div class="text-center pa-8">
              <VIcon icon="ri-file-list-3-line" size="48" color="disabled" class="mb-4" />
              <div class="text-h6 mb-2">No activity logs found</div>
              <div class="text-body-2">
                {{ hasActiveFilters ? 'Try adjusting your search or filters' : 'No activity logs available' }}
              </div>
            </div>
          </template>
        </VDataTable>

        <!-- Pagination -->
        <div class="d-flex justify-space-between align-center mt-6" v-if="pagination.last_page > 1">
          <div class="text-body-2 text-medium-emphasis">
            Showing {{ ((pagination.current_page - 1) * pagination.per_page) + 1 }} to
            {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} of
            {{ pagination.total }} entries
          </div>
          <VPagination v-model="pagination.current_page" :length="pagination.last_page"
            @update:model-value="onPageChange" :disabled="loading" />
        </div>
      </VCardText>
    </VCard>

    <!-- Log Detail Dialog -->
    <VDialog v-model="showDetailDialog" max-width="800" scrollable>
      <VCard v-if="selectedLog">
        <VCardTitle class="pa-6">
          <div class="d-flex align-center gap-3">
            <VIcon icon="ri-file-list-3-line" size="24" />
            <div>
              <h3>Activity Log Details</h3>
              <p class="text-body-2 ma-0">{{ formatDate(selectedLog.created_at) }}</p>
            </div>
          </div>
        </VCardTitle>

        <VDivider />

        <VCardText class="pa-6">
          <VRow>
            <VCol cols="12" md="6">
              <VCard variant="outlined" class="mb-4">
                <VCardTitle class="pa-4">
                  <VIcon icon="ri-user-line" class="me-2" />
                  User Information
                </VCardTitle>
                <VCardText>
                  <div class="d-flex align-center gap-3 mb-3" v-if="selectedLog.user_name">
                    <VAvatar :color="avatarUrls[selectedLog.email] ? undefined : getAvatarColor(selectedLog.user_name)"
                      size="40">
                      <VImg v-if="avatarUrls[selectedLog.email]" :src="avatarUrls[selectedLog.email]"
                        :alt="selectedLog.user_name" />
                      <span v-else class="text-white font-weight-bold">
                        {{ getUserInitials(selectedLog.user_name) }}
                      </span>
                    </VAvatar>
                    <div>
                      <div class="font-weight-medium">{{ selectedLog.user_name }}</div>
                      <div class="text-caption text-medium-emphasis">{{ selectedLog.email }}</div>
                    </div>
                  </div>
                  <div class="d-flex flex-column gap-2">
                    <div>
                      <strong>Name:</strong> {{ selectedLog.user_name || 'Unknown' }}
                    </div>
                    <div>
                      <strong>Email:</strong> {{ selectedLog.email || 'N/A' }}
                    </div>
                    <div>
                      <strong>User ID:</strong> {{ selectedLog.user_id || 'N/A' }}
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <VCol cols="12" md="6">
              <VCard variant="outlined" class="mb-4">
                <VCardTitle class="pa-4">
                  <VIcon icon="ri-settings-line" class="me-2" />
                  Action Details
                </VCardTitle>
                <VCardText>
                  <div class="d-flex flex-column gap-2">
                    <div>
                      <strong>Action:</strong>
                      <VChip :color="getActionChipColor(selectedLog.action)" variant="tonal" size="small" class="ml-2">
                        {{ selectedLog.action_label || selectedLog.action }}
                      </VChip>
                    </div>
                    <div>
                      <strong>Module:</strong> {{ selectedLog.module || 'N/A' }}
                    </div>
                    <div>
                      <strong>Description:</strong> {{ selectedLog.description }}
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <VCol cols="12">
              <VCard variant="outlined" class="mb-4">
                <VCardTitle class="pa-4">
                  <VIcon icon="ri-global-line" class="me-2" />
                  Technical Information
                </VCardTitle>
                <VCardText>
                  <VRow>
                    <VCol cols="12" md="4">
                      <div><strong>IP Address:</strong></div>
                      <div class="font-mono">{{ selectedLog.ip_address || 'N/A' }}</div>
                    </VCol>
                    <VCol cols="12" md="4">
                      <div><strong>Timestamp:</strong></div>
                      <div>{{ formatDate(selectedLog.created_at) }}</div>
                    </VCol>
                    <VCol cols="12" md="4">
                      <div><strong>Log ID:</strong></div>
                      <div class="font-mono">{{ selectedLog.id }}</div>
                    </VCol>
                  </VRow>

                  <div v-if="selectedLog.detail_log.user_agent" class="mt-3">
                    <div><strong>User Agent:</strong></div>
                    <div class="text-caption font-mono pa-2 bg-grey-lighten-4 rounded mt-1">
                      {{ selectedLog.detail_log.user_agent }}
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <VCol v-if="selectedLog.detail_log.details" cols="12">
              <VCard variant="outlined">
                <VCardTitle class="pa-4">
                  <VIcon icon="ri-code-line" class="me-2" />
                  Additional Details
                </VCardTitle>
                <VCardText>
                  <pre class="text-caption font-mono pa-3 bg-grey-lighten-5 rounded overflow-auto">{{
                    JSON.stringify(selectedLog.detail_log.details, null, 2) }}</pre>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-6">
          <VSpacer />
          <VBtn variant="outlined" @click="closeDetailDialog">
            Close
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

.font-mono {
  font-family: Monaco, Menlo, "Ubuntu Mono", monospace;
}

.max-width-300 {
  max-inline-size: 300px;
}

.overflow-auto {
  overflow: auto;
  max-block-size: 300px;
}

.text-caption {
  line-height: 1.4;
}

pre {
  white-space: pre-wrap;
  word-wrap: break-word;
}
</style>
