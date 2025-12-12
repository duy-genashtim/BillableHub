<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { getWeekRangeForYear } from '@/@core/utils/worklogHelpers'
import { WORKLOG_CONFIG } from '@/@core/utils/worklogConfig'
import { filterFutureWeeks } from '@/@core/utils/dateValidation'
import axios from 'axios'

// Reactive state
const selectedYear = ref(new Date().getFullYear())
const selectedWeek = ref(null)
const selectedUsers = ref([])
const users = ref([])
const weeks = ref([])
const syncing = ref(false)
const loadingUsers = ref(false)
const streamProgress = ref(0)
const streamMessages = ref([])
const snackbar = ref(false)
const snackbarMessage = ref('')
const snackbarColor = ref('info')
const isMobile = ref(window.innerWidth < 768)

let worklogEventSource = null

// Year options (current year down to START_YEAR)
const yearOptions = computed(() => {
  const currentYear = new Date().getFullYear()
  const startYear = WORKLOG_CONFIG.START_YEAR
  const options = []

for (let year = currentYear; year >= startYear; year--) {
    options.push(year)
  }

return options
})

// Week options for selected year
const weekOptions = computed(() => {
  return weeks.value.map((week, index) => ({
    title: week.label,
    value: index,
    subtitle: `${week.start_date} to ${week.end_date}`,
    raw: week,
  }))
})

// Can sync if year, week, and at least one user is selected
const canSync = computed(() => {
  return selectedYear.value &&
         selectedWeek.value !== null &&
         selectedUsers.value.length > 0 &&
         !syncing.value
})

// Selected week details
const selectedWeekDetails = computed(() => {
  if (selectedWeek.value !== null && weeks.value[selectedWeek.value]) {
    return weeks.value[selectedWeek.value]
  }
  return null
})

// Fetch IVA users from API
const fetchUsers = async () => {
  try {
    loadingUsers.value = true
    const response = await axios.get('/api/admin/iva-users', {
      params: {
        per_page: 1000, // Get all users
        is_active: true,
      },
    })

    // Filter users that have TimeDoctor linkage
    // API returns paginated data in response.data.users.data
    users.value = (response.data.users?.data || [])
      .filter(user => {
        // Check if user has timedoctor sync status and is linked
        return user.timedoctor_sync_status?.is_linked === true
      })
      .map(user => ({
        id: user.id,
        full_name: user.full_name,
        email: user.email,
        work_status: user.work_status,
      }))
  } catch (error) {
    console.error('Error fetching users:', error)
    showSnackbar('Error loading IVA users', 'error')
  } finally {
    loadingUsers.value = false
  }
}

// Generate week options for selected year
const generateWeeks = () => {
  const allWeeks = getWeekRangeForYear(selectedYear.value)
  const availableWeeks = filterFutureWeeks(allWeeks, selectedYear.value)
  weeks.value = availableWeeks

  // Reset selected week if current selection is invalid
  if (selectedWeek.value !== null && selectedWeek.value >= availableWeeks.length) {
    selectedWeek.value = availableWeeks.length > 0 ? availableWeeks.length - 1 : null
  } else if (selectedWeek.value === null && availableWeeks.length > 0) {
    selectedWeek.value = availableWeeks.length - 1 // Default to most recent week
  }
}

// Show snackbar notification
const showSnackbar = (message, color = 'info') => {
  snackbarMessage.value = message
  snackbarColor.value = color
  snackbar.value = true
}

// Sync worklogs for selected users and week
const syncWorklogs = async () => {
  if (!canSync.value) {
    showSnackbar('Please select year, week, and at least one IVA user', 'warning')
    return
  }

  syncing.value = true
  streamProgress.value = 0
  streamMessages.value = []

  const weekData = selectedWeekDetails.value
  const userIds = selectedUsers.value.join(',')

  try {
    const url = `/api/timedoctor/stream-worklog-sync-by-users?start_date=${weekData.start_date}&end_date=${weekData.end_date}&user_ids=${userIds}`

    worklogEventSource = new EventSource(url)

    worklogEventSource.onmessage = event => {
      const data = JSON.parse(event.data)

      streamMessages.value.push({
        type: data.type || 'info',
        message: data.message || '',
        timestamp: new Date().toLocaleTimeString(),
      })

      // Update progress if provided
      if (data.progress !== undefined) {
        streamProgress.value = data.progress
      }

      // Handle completion
      if (data.type === 'complete') {
        worklogEventSource.close()
        syncing.value = false
        showSnackbar('Worklog sync completed successfully!', 'success')
      }

      // Handle errors
      if (data.type === 'error') {
        worklogEventSource.close()
        syncing.value = false
        showSnackbar(data.message || 'Sync failed', 'error')
      }

      // Auto-scroll messages to bottom
      setTimeout(() => {
        const messagesContainer = document.getElementById('stream-messages')
        if (messagesContainer) {
          messagesContainer.scrollTop = messagesContainer.scrollHeight
        }
      }, 100)
    }

    worklogEventSource.onerror = error => {
      console.error('EventSource error:', error)
      worklogEventSource.close()
      syncing.value = false
      showSnackbar('Connection error during sync', 'error')
    }
  } catch (error) {
    console.error('Error starting sync:', error)
    syncing.value = false
    showSnackbar('Failed to start sync', 'error')
  }
}

// Cancel ongoing sync
const cancelSync = () => {
  if (worklogEventSource) {
    worklogEventSource.close()
    worklogEventSource = null
  }
  syncing.value = false
  showSnackbar('Sync cancelled', 'warning')
}

// Handle window resize for mobile detection
const handleResize = () => {
  isMobile.value = window.innerWidth < 768
}

// Watch year changes to regenerate weeks
watch(selectedYear, () => {
  generateWeeks()
})

// Lifecycle hooks
onMounted(() => {
  fetchUsers()
  generateWeeks()
  window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
  if (worklogEventSource) {
    worklogEventSource.close()
  }
  window.removeEventListener('resize', handleResize)
})
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <VBreadcrumbs
      :items="[
        { title: 'Home', to: '/dashboard' },
        { title: 'Sync Data', disabled: true },
        { title: 'Sync Worklogs by Week', disabled: true },
      ]"
      class="px-0"
      aria-label="Breadcrumb navigation"
    />

    <!-- Page Header -->
    <VCard variant="outlined" class="mb-6">
      <VCardItem>
        <VCardTitle class="d-flex align-center gap-2">
          <VIcon icon="ri-calendar-check-line" size="24" />
          <span tabindex="0">Sync Worklogs by Week</span>
        </VCardTitle>
      </VCardItem>
      <VCardText>
        <p class="text-body-2 mb-0">
          Select specific IVA users and a week to re-sync their worklog data from TimeDoctor V1.
          This is useful for correcting data or updating records for specific employees during a particular week.
        </p>
      </VCardText>
    </VCard>

    <!-- Selection Form -->
    <VCard variant="outlined" class="mb-6">
      <VCardText>
        <VRow>
          <!-- Year Selection -->
          <VCol cols="12" md="3">
            <VSelect
              v-model="selectedYear"
              :items="yearOptions"
              label="Year"
              density="comfortable"
              variant="outlined"
              :disabled="syncing"
              aria-label="Select year for worklog sync"
            />
          </VCol>

          <!-- Week Selection -->
          <VCol cols="12" md="5">
            <VSelect
              v-model="selectedWeek"
              :items="weekOptions"
              label="Week"
              density="comfortable"
              variant="outlined"
              :disabled="syncing || weeks.length === 0"
              aria-label="Select week for worklog sync"
            >
              <template #item="{ item, props }">
                <VListItem v-bind="props">
                  <template #subtitle>
                    {{ item.raw.subtitle }}
                  </template>
                </VListItem>
              </template>
            </VSelect>
          </VCol>

          <!-- Selected Week Info -->
          <VCol v-if="selectedWeekDetails" cols="12" md="4" class="d-flex align-center">
            <VAlert
              type="info"
              variant="tonal"
              density="compact"
              class="w-100"
            >
              <div class="text-caption">
                <strong>Week {{ selectedWeekDetails.week_number }}</strong>
                <br>
                {{ selectedWeekDetails.start_date }} - {{ selectedWeekDetails.end_date }}
              </div>
            </VAlert>
          </VCol>
        </VRow>

        <VRow>
          <!-- IVA Users Multi-Select -->
          <VCol cols="12">
            <VAutocomplete
              v-model="selectedUsers"
              :items="users"
              item-title="full_name"
              item-value="id"
              label="Select IVA Users"
              placeholder="Choose one or more users to sync"
              multiple
              chips
              closable-chips
              :loading="loadingUsers"
              :disabled="syncing"
              variant="outlined"
              density="comfortable"
              aria-label="Select IVA users to sync worklogs"
            >
              <template #chip="{ item, props }">
                <VChip
                  v-bind="props"
                  closable
                  size="small"
                >
                  {{ item.raw.full_name }}
                </VChip>
              </template>
              <template #item="{ item, props }">
                <VListItem v-bind="props">
                  <template #subtitle>
                    {{ item.raw.email }} • {{ item.raw.work_status }}
                  </template>
                </VListItem>
              </template>
            </VAutocomplete>
          </VCol>
        </VRow>

        <!-- Sync Actions -->
        <VRow>
          <VCol cols="12" class="d-flex gap-3 flex-wrap">
            <VBtn
              color="primary"
              :disabled="!canSync"
              :loading="syncing"
              prepend-icon="ri-refresh-line"
              @click="syncWorklogs"
              aria-label="Start syncing worklogs for selected users and week"
            >
              {{ syncing ? 'Syncing...' : 'Sync Worklogs' }}
            </VBtn>
            <VBtn
              v-if="syncing"
              color="error"
              variant="outlined"
              prepend-icon="ri-close-line"
              @click="cancelSync"
              aria-label="Cancel ongoing sync operation"
            >
              Cancel
            </VBtn>
            <VSpacer />
            <div v-if="selectedUsers.length > 0" class="d-flex align-center text-body-2">
              <VIcon icon="ri-user-line" size="18" class="me-1" />
              {{ selectedUsers.length }} user{{ selectedUsers.length !== 1 ? 's' : '' }} selected
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Progress Display -->
    <VCard v-if="syncing || streamMessages.length > 0" variant="outlined" class="mb-6">
      <VCardItem>
        <VCardTitle>
          <div class="d-flex align-center gap-2">
            <VIcon icon="ri-bar-chart-box-line" size="20" />
            Sync Progress
          </div>
        </VCardTitle>
      </VCardItem>
      <VCardText>
        <!-- Progress Bar -->
        <div v-if="syncing" class="mb-4">
          <div class="d-flex justify-space-between align-center mb-2">
            <span class="text-body-2">Processing...</span>
            <span class="text-body-2 font-weight-medium">{{ Math.round(streamProgress) }}%</span>
          </div>
          <VProgressLinear
            :model-value="streamProgress"
            color="primary"
            height="8"
            striped
            aria-label="Sync progress percentage"
          />
        </div>

        <!-- Stream Messages -->
        <div
          v-if="streamMessages.length > 0"
          id="stream-messages"
          class="stream-messages-container"
          role="log"
          aria-label="Sync process messages"
          aria-live="polite"
        >
          <div
            v-for="(msg, index) in streamMessages"
            :key="index"
            class="message-item"
            :class="`message-${msg.type}`"
          >
            <div class="message-header">
              <VIcon
                :icon="
                  msg.type === 'error' ? 'ri-error-warning-line' :
                    msg.type === 'complete' ? 'ri-checkbox-circle-line' :
                      msg.type === 'progress' ? 'ri-loader-4-line' :
                        'ri-information-line'
                "
                :color="
                  msg.type === 'error' ? 'error' :
                    msg.type === 'complete' ? 'success' :
                      msg.type === 'progress' ? 'primary' :
                        'info'
                "
                size="18"
              />
              <span class="message-timestamp">{{ msg.timestamp }}</span>
            </div>
            <div class="message-content">
              {{ msg.message }}
            </div>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- Snackbar for notifications -->
    <VSnackbar
      v-model="snackbar"
      :color="snackbarColor"
      location="top"
      :timeout="4000"
      role="alert"
      aria-live="assertive"
    >
      {{ snackbarMessage }}
    </VSnackbar>
  </div>
</template>

<style scoped>
.stream-messages-container {
  max-height: 400px;
  overflow-y: auto;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 4px;
  padding: 12px;
  background-color: rgba(var(--v-theme-surface), 0.5);
}

.message-item {
  padding: 8px 12px;
  margin-bottom: 8px;
  border-radius: 4px;
  border-left: 3px solid;
  background-color: rgba(var(--v-theme-surface), 1);
}

.message-item:last-child {
  margin-bottom: 0;
}

.message-error {
  border-left-color: rgb(var(--v-theme-error));
  background-color: rgba(var(--v-theme-error), 0.05);
}

.message-complete {
  border-left-color: rgb(var(--v-theme-success));
  background-color: rgba(var(--v-theme-success), 0.05);
}

.message-progress {
  border-left-color: rgb(var(--v-theme-primary));
  background-color: rgba(var(--v-theme-primary), 0.05);
}

.message-info {
  border-left-color: rgb(var(--v-theme-info));
  background-color: rgba(var(--v-theme-info), 0.05);
}

.message-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.message-timestamp {
  font-size: 0.75rem;
  opacity: 0.7;
  font-family: monospace;
}

.message-content {
  font-size: 0.875rem;
  line-height: 1.5;
  padding-left: 26px;
}

/* Focus visible styles for accessibility */
:deep(.v-btn:focus-visible) {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

:deep(.v-select:focus-visible),
:deep(.v-autocomplete:focus-visible) {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

/* Mobile responsiveness */
@media (max-width: 768px) {
  .stream-messages-container {
    max-height: 300px;
  }

  .message-content {
    font-size: 0.8125rem;
  }
}
</style>
