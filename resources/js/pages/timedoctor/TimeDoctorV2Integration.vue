<template>
  <div class="time-doctor-v2-container pb-16">
    <VBreadcrumbs :items="[
      { title: 'Home', to: '/' },
      { title: 'TimeDoctor V2 Integration', disabled: true }
    ]" class="mb-6" aria-label="Breadcrumb navigation" />
    <!-- Header -->
    <VCard class="mb-6">
      <VCardTitle class="d-flex align-center">
        <span>TimeDoctor V2 Integration</span>
        <VSpacer />
        <VChip :color="connected ? 'success' : 'error'" variant="outlined">
          {{ connected ? 'Connected' : 'Disconnected' }}
        </VChip>
      </VCardTitle>

      <VCardText>
        <VAlert v-if="error && showError" type="error" variant="tonal" closable @click:close="showError = false">
          {{ error }}
        </VAlert>

        <div v-if="loading" class="d-flex justify-center align-center" style="min-block-size: 100px;">
          <VProgressCircular indeterminate color="primary" />
          <span class="ml-3">Checking connection status...</span>
        </div>

        <div v-else class="text-center">
          <div v-if="connected">
            <p class="mb-4">{{ message }}</p>
            <div v-if="tokenInfo" class="mb-4">
              <VAlert type="info" variant="tonal" density="compact">
                <div class="d-flex align-center">
                  <VIcon icon="ri-time-line" class="mr-2" />
                  <div>
                    <div class="font-weight-medium">Token Valid Until:</div>
                    <div class="text-caption">{{ formatDateTime(tokenInfo.expires_at) }}</div>
                  </div>
                </div>
              </VAlert>
            </div>
            <div class="d-flex justify-center gap-3">
              <VBtn color="primary" variant="outlined" @click="refreshToken" :loading="refreshing">
                <VIcon start>ri-refresh-line</VIcon>
                Refresh Token
              </VBtn>
              <VBtn color="error" variant="outlined" @click="disconnectTimeDoctor" :loading="loading">
                <VIcon start>ri-logout-circle-r-line</VIcon>
                Disconnect
              </VBtn>
            </div>
          </div>
          <div v-else>
            <p class="mb-4">{{ message }}</p>
            <VBtn color="primary" @click="connectTimeDoctor" :loading="connecting">
              <VIcon start>ri-login-circle-line</VIcon>
              Connect to TimeDoctor V2
            </VBtn>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- Main Content (only shown when connected) -->
    <template v-if="connected && !loading">
      <!-- Stats -->
      <VRow class="mb-6">
        <VCol cols="12" sm="6" md="3">
          <VCard variant="outlined" height="100%">
            <VCardText class="d-flex flex-column align-center text-center">
              <VIcon size="large" color="blue" icon="mdi-account-group" />
              <div class="text-h4 mt-2">{{ userCount }}</div>
              <div class="text-subtitle-1">Users</div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <VCard variant="outlined" height="100%">
            <VCardText class="d-flex flex-column align-center text-center">
              <VIcon size="large" color="orange" icon="mdi-briefcase" />
              <div class="text-h4 mt-2">{{ projectCount }}</div>
              <div class="text-subtitle-1">Projects</div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <VCard variant="outlined" height="100%">
            <VCardText class="d-flex flex-column align-center text-center">
              <VIcon size="large" color="green" icon="mdi-clipboard-text" />
              <div class="text-h4 mt-2">{{ taskCount }}</div>
              <div class="text-subtitle-1">Tasks</div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <VCard variant="outlined" height="100%">
            <VCardText class="d-flex flex-column align-center text-center">
              <VIcon size="large" color="purple" icon="mdi-clock-outline" />
              <div class="text-h4 mt-2">{{ worklogCount }}</div>
              <div class="text-subtitle-1">Worklog Records</div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Action Buttons -->
      <VCard class="mb-6">
        <VCardTitle class="pb-2">Sync Operations</VCardTitle>
        <VCardText class="pt-4">
          <div class="d-flex flex-wrap gap-3 mb-4">
            <VBtn color="primary" :loading="syncing" :disabled="syncing" @click="syncAll" prepend-icon="mdi-sync"
              class="me-2 mb-2">
              Sync All
            </VBtn>

            <VBtn color="blue" variant="outlined" :loading="syncingUsers" :disabled="syncing || syncingUsers"
              @click="syncUsers" prepend-icon="mdi-account-group" class="me-2 mb-2">
              Sync Users
            </VBtn>

            <VBtn color="orange" variant="outlined" :loading="syncingProjects" :disabled="syncing || syncingProjects"
              @click="syncProjects" prepend-icon="mdi-briefcase" class="me-2 mb-2">
              Sync Projects
            </VBtn>

            <VBtn color="green" variant="outlined" :loading="syncingTasks" :disabled="syncing || syncingTasks"
              @click="syncTasks" prepend-icon="mdi-clipboard-text" class="me-2 mb-2">
              Sync Tasks
            </VBtn>
          </div>

          <!-- Status Cards -->
          <VRow class="mb-4">
            <VCol cols="12" sm="6" lg="3">
              <VCard variant="flat" :color="getStatusColor(userSyncStatus)" class="status-card">
                <VCardText class="pa-3">
                  <div class="d-flex align-center">
                    <VIcon :icon="getStatusIcon(userSyncStatus)" size="small" class="mr-2" />
                    <div>
                      <div class="text-caption font-weight-bold">Users</div>
                      <div class="text-caption text-capitalize">{{ userSyncStatus }}</div>
                    </div>
                    <VSpacer />
                    <VProgressCircular v-if="userSyncStatus === 'in_progress'" :model-value="syncProgress.users"
                      :size="24" :width="2" color="white" />
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <VCol cols="12" sm="6" lg="3">
              <VCard variant="flat" :color="getStatusColor(projectSyncStatus)" class="status-card">
                <VCardText class="pa-3">
                  <div class="d-flex align-center">
                    <VIcon :icon="getStatusIcon(projectSyncStatus)" size="small" class="mr-2" />
                    <div>
                      <div class="text-caption font-weight-bold">Projects</div>
                      <div class="text-caption text-capitalize">{{ projectSyncStatus }}</div>
                    </div>
                    <VSpacer />
                    <VProgressCircular v-if="projectSyncStatus === 'in_progress'" :model-value="syncProgress.projects"
                      :size="24" :width="2" color="white" />
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <VCol cols="12" sm="6" lg="3">
              <VCard variant="flat" :color="getStatusColor(taskSyncStatus)" class="status-card">
                <VCardText class="pa-3">
                  <div class="d-flex align-center">
                    <VIcon :icon="getStatusIcon(taskSyncStatus)" size="small" class="mr-2" />
                    <div>
                      <div class="text-caption font-weight-bold">Tasks</div>
                      <div class="text-caption text-capitalize">{{ taskSyncStatus }}</div>
                    </div>
                    <VSpacer />
                    <VProgressCircular v-if="taskSyncStatus === 'in_progress'" :model-value="syncProgress.tasks"
                      :size="24" :width="2" color="white" />
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <VCol cols="12" sm="6" lg="3">
              <VCard variant="flat" :color="getStatusColor(worklogSyncStatus)" class="status-card">
                <VCardText class="pa-3">
                  <div class="d-flex align-center">
                    <VIcon :icon="getStatusIcon(worklogSyncStatus)" size="small" class="mr-2" />
                    <div>
                      <div class="text-caption font-weight-bold">Worklogs</div>
                      <div class="text-caption text-capitalize">{{ worklogSyncStatus }}</div>
                    </div>
                    <VSpacer />
                    <VProgressCircular v-if="worklogSyncStatus === 'in_progress'" :model-value="syncProgress.worklogs"
                      :size="24" :width="2" color="white" />
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>

          <!-- Tabs for Different Operations -->
          <VTabs v-model="activeTab" grow color="primary" class="mb-4">
            <VTab value="0">
              <VIcon start>mdi-clock-outline</VIcon>
              Worklog Sync
            </VTab>
            <VTab value="1">
              <VIcon start>mdi-text-box-outline</VIcon>
              Logs
            </VTab>
          </VTabs>

          <VWindow v-model="activeTab">
            <!-- Worklog Sync Tab -->
            <VWindowItem value="0">
              <VCard variant="outlined" class="mb-4">
                <VCardTitle>Sync Worklogs</VCardTitle>
                <VCardText class="pt-4">
                  <!-- Date Range Shortcuts -->
                  <div class="mb-4">
                    <VBtnGroup variant="outlined" divided>
                      <VBtn @click="setToday" size="small" :disabled="syncingWorklogs" class="px-3">Today</VBtn>
                      <VBtn @click="setYesterday" size="small" :disabled="syncingWorklogs" class="px-3">Yesterday</VBtn>
                      <VBtn @click="setLast7Days" size="small" :disabled="syncingWorklogs" class="px-3">Last 7 Days
                      </VBtn>
                      <VBtn @click="setThisMonth" size="small" :disabled="syncingWorklogs" class="px-3">This Month
                      </VBtn>
                    </VBtnGroup>
                  </div>

                  <!-- Date Range Selector -->
                  <div class="d-flex flex-column flex-md-row gap-4 mb-4">
                    <VTextField v-model="worklogDateRange.start_date" label="Start Date" type="date"
                      :disabled="syncingWorklogs" variant="outlined" />

                    <VTextField v-model="worklogDateRange.end_date" label="End Date" type="date"
                      :disabled="syncingWorklogs" variant="outlined" />
                  </div>

                  <!-- Date Range Warning/Info -->
                  <VAlert :type="dateRangeValid ? 'info' : 'warning'"
                    :icon="dateRangeValid ? 'mdi-information' : 'mdi-alert'" variant="tonal" class="mb-4"
                    density="compact">
                    {{ dateRangeMessage }}
                  </VAlert>

                  <!-- Progress Bar for Worklog Sync -->
                  <div v-if="syncingWorklogs" class="mb-4">
                    <VProgressLinear :model-value="streamProgress" height="20" color="primary" striped>
                      <template v-slot:default>
                        {{ Math.round(streamProgress) }}% Complete
                      </template>
                    </VProgressLinear>
                  </div>

                  <!-- Stream Messages -->
                  <div v-if="streamMessages.length > 0" class="stream-container mb-4">
                    <div v-for="(msg, idx) in streamMessages" :key="idx" class="stream-message mb-1 pa-1">
                      <VIcon :icon="getStatusIcon(msg.type)" :color="getStatusColor(msg.type)" size="small"
                        class="mr-1" />
                      <span>{{ msg.message }}</span>
                    </div>
                  </div>

                  <!-- Action Buttons -->
                  <div class="d-flex">
                    <VBtn color="purple" :loading="syncingWorklogs" :disabled="!dateRangeValid || syncingWorklogs"
                      @click="syncWorklogs" prepend-icon="mdi-clock-sync">
                      Sync Worklogs
                    </VBtn>

                    <VBtn v-if="syncingWorklogs" color="red" variant="outlined" class="ml-2" @click="cancelWorklogSync">
                      Cancel
                    </VBtn>
                  </div>
                </VCardText>
              </VCard>
            </VWindowItem>

            <!-- Logs Tab -->
            <VWindowItem value="1">
              <VCard variant="outlined">
                <VCardTitle class="d-flex align-center">
                  Sync Logs
                  <VSpacer />
                  <VBtn variant="text" density="compact" icon="mdi-refresh" @click="clearLogs" title="Clear logs" />
                </VCardTitle>
                <VCardText>
                  <div v-if="syncLogs.length === 0" class="text-center py-4">
                    <p class="text-disabled">No logs available</p>
                  </div>
                  <div v-else class="log-container">
                    <div v-for="log in syncLogs" :key="log.id" class="log-entry mb-1 pa-1" :class="`log-${log.type}`">
                      <span class="log-timestamp me-2">{{ log.timestamp }}</span>
                      <span class="log-message">{{ log.message }}</span>
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </VWindowItem>
          </VWindow>
        </VCardText>
      </VCard>
    </template>
  </div>
</template>

<script setup>
import axios from 'axios'
import { computed, onMounted, onUnmounted, ref } from 'vue'

// Connection state
const loading = ref(true)
const connected = ref(false)
const connecting = ref(false)
const refreshing = ref(false)
const message = ref('')
const tokenInfo = ref(null)

// Action states
const syncing = ref(false)
const syncingUsers = ref(false)
const syncingProjects = ref(false)
const syncingTasks = ref(false)
const syncingWorklogs = ref(false)

// Data counts
const userCount = ref(0)
const projectCount = ref(0)
const taskCount = ref(0)
const worklogCount = ref(0)

// Error tracking
const error = ref(null)
const showError = ref(false)

// Tabs
const activeTab = ref(0)

// Worklog date range
const worklogDateRange = ref({
  start_date: new Date().toISOString().slice(0, 10),
  end_date: new Date().toISOString().slice(0, 10)
})

// Progress tracking
const syncProgress = ref({
  users: 0,
  projects: 0,
  tasks: 0,
  worklogs: 0
})

// Logs/results from operations
const syncLogs = ref([])
const maxLogEntries = 50

// Progress streams for long-running operations
const streamActive = ref(false)
const streamProgress = ref(0)
const streamMessages = ref([])

// Sync statuses
const userSyncStatus = ref('idle')
const projectSyncStatus = ref('idle')
const taskSyncStatus = ref('idle')
const worklogSyncStatus = ref('idle')

// Validate date range
const dateRangeValid = computed(() => {
  if (!worklogDateRange.value.start_date || !worklogDateRange.value.end_date) {
    return false
  }

  const start = new Date(worklogDateRange.value.start_date)
  const end = new Date(worklogDateRange.value.end_date)
  const diffDays = Math.floor((end - start) / (1000 * 60 * 60 * 24))

  return start <= end && diffDays <= 31
})

const dateRangeMessage = computed(() => {
  if (!worklogDateRange.value.start_date || !worklogDateRange.value.end_date) {
    return 'Please select both start and end dates'
  }

  const start = new Date(worklogDateRange.value.start_date)
  const end = new Date(worklogDateRange.value.end_date)

  if (start > end) {
    return 'End date must be after start date'
  }

  const diffDays = Math.floor((end - start) / (1000 * 60 * 60 * 24)) + 1

  if (diffDays > 31) {
    return 'Date range cannot exceed 31 days'
  }

  return `Selected range: ${diffDays} day${diffDays > 1 ? 's' : ''}`
})

// Format timestamp for logs
const formatTimestamp = (date = null) => {
  const d = date ? new Date(date) : new Date()
  return d.toLocaleTimeString('en-US', { hour12: false }) + '.' + d.getMilliseconds().toString().padStart(3, '0')
}

// Format date time
const formatDateTime = (dateTime) => {
  if (!dateTime) return 'N/A'
  return new Date(dateTime).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false
  })
}

// Add log entry with timestamp
const addLog = (message, type = 'info') => {
  syncLogs.value.unshift({
    id: Date.now(),
    timestamp: formatTimestamp(),
    message,
    type
  })

  if (syncLogs.value.length > maxLogEntries) {
    syncLogs.value = syncLogs.value.slice(0, maxLogEntries)
  }
}

// Set error with timeout for auto-dismiss
const setError = (errorMessage) => {
  error.value = errorMessage
  showError.value = true
  addLog(errorMessage, 'error')

  setTimeout(() => {
    showError.value = false
  }, 10000)
}

// Connection handling
const checkConnection = async () => {
  loading.value = true
  error.value = null

  try {
    const res = await axios.get('/api/timedoctor-v2/status')
    connected.value = res.data.connected
    message.value = res.data.message
    tokenInfo.value = {
      expires_at: res.data.expires_at
    }

    if (connected.value) {
      await fetchCounts()
    }
  } catch (e) {
    console.error('V2 Connection check error:', e)
    setError('Failed to check TimeDoctor V2 connection status')
  } finally {
    loading.value = false
  }
}

// Fetch entity counts
const fetchCounts = async () => {
  try {
    const usersRes = await axios.get('/api/timedoctor-v2/users/count')
    userCount.value = usersRes.data.count || 0

    const projectsRes = await axios.get('/api/timedoctor-v2/projects/count')
    projectCount.value = projectsRes.data.count || 0

    const tasksRes = await axios.get('/api/timedoctor-v2/tasks/count')
    taskCount.value = tasksRes.data.count || 0

    const worklogsRes = await axios.get('/api/timedoctor-v2/worklogs/count')
    worklogCount.value = worklogsRes.data.count || 0
  } catch (e) {
    console.error('Error fetching V2 counts:', e)
  }
}

// Connect to TimeDoctor V2
const connectTimeDoctor = async () => {
  connecting.value = true
  error.value = null

  try {
    addLog('Attempting to connect to TimeDoctor V2...')

    const res = await axios.post('/api/timedoctor-v2/auth')

    if (res.data.success) {
      connected.value = true
      message.value = res.data.message
      tokenInfo.value = {
        expires_at: res.data.expires_at
      }
      addLog('Successfully connected to TimeDoctor V2', 'success')
      await fetchCounts()
    } else {
      setError('Failed to connect to TimeDoctor V2: ' + res.data.message)
    }
  } catch (e) {
    console.error('Connect error:', e)
    setError('Error connecting to TimeDoctor V2: ' + (e.response?.data?.message || e.message))
  } finally {
    connecting.value = false
  }
}

// Refresh Token
const refreshToken = async () => {
  refreshing.value = true
  error.value = null

  try {
    addLog('Refreshing TimeDoctor V2 token...')

    const res = await axios.post('/api/timedoctor-v2/refresh')

    if (res.data.success) {
      tokenInfo.value = {
        expires_at: res.data.expires_at
      }
      addLog('TimeDoctor V2 token refreshed successfully', 'success')
    } else {
      setError('Failed to refresh token: ' + res.data.message)
    }
  } catch (e) {
    console.error('Refresh error:', e)
    setError('Error refreshing token: ' + (e.response?.data?.message || e.message))
  } finally {
    refreshing.value = false
  }
}

// Disconnect from TimeDoctor V2
const disconnectTimeDoctor = async () => {
  try {
    loading.value = true
    const res = await axios.get('/api/timedoctor-v2/disconnect')

    if (res.data.success) {
      connected.value = false
      message.value = res.data.message
      tokenInfo.value = null
      addLog('Disconnected from TimeDoctor V2')
    } else {
      setError('Failed to disconnect from TimeDoctor V2')
    }
  } catch (e) {
    console.error('Disconnect error:', e)
    setError('Error disconnecting from TimeDoctor V2: ' + e.message)
  } finally {
    loading.value = false
  }
}

// Sync Users
const syncUsers = async () => {
  if (syncingUsers.value) return

  syncingUsers.value = true
  userSyncStatus.value = 'in_progress'
  syncProgress.value.users = 0
  error.value = null

  try {
    addLog('Starting TimeDoctor V2 user sync...')

    const res = await axios.post('/api/timedoctor-v2/sync-users')

    if (res.data.success) {
      addLog(`V2 User sync complete: ${res.data.synced_count} users synced`, 'success')
      userCount.value = res.data.synced_count || userCount.value
      userSyncStatus.value = 'completed'
    } else {
      setError('V2 User sync failed: ' + res.data.message)
      userSyncStatus.value = 'failed'
    }
  } catch (e) {
    console.error('V2 User sync error:', e)
    setError('Error syncing V2 users: ' + (e.response?.data?.message || e.message))
    userSyncStatus.value = 'failed'
  } finally {
    syncingUsers.value = false
  }
}

// Sync Projects
const syncProjects = async () => {
  if (syncingProjects.value) return

  syncingProjects.value = true
  projectSyncStatus.value = 'in_progress'
  syncProgress.value.projects = 0
  error.value = null

  try {
    addLog('Starting TimeDoctor V2 project sync...')

    const res = await axios.post('/api/timedoctor-v2/sync-projects')

    if (res.data.success) {
      addLog(`V2 Project sync complete: ${res.data.synced_count} projects synced`, 'success')
      projectCount.value = res.data.synced_count || projectCount.value
      projectSyncStatus.value = 'completed'
    } else {
      setError('V2 Project sync failed: ' + res.data.message)
      projectSyncStatus.value = 'failed'
    }
  } catch (e) {
    console.error('V2 Project sync error:', e)
    setError('Error syncing V2 projects: ' + (e.response?.data?.message || e.message))
    projectSyncStatus.value = 'failed'
  } finally {
    syncingProjects.value = false
  }
}

// Sync Tasks
const syncTasks = async () => {
  if (syncingTasks.value) return

  syncingTasks.value = true
  taskSyncStatus.value = 'in_progress'
  syncProgress.value.tasks = 0
  error.value = null

  try {
    addLog('Starting TimeDoctor V2 task sync...')

    const res = await axios.post('/api/timedoctor-v2/sync-tasks')

    if (res.data.success) {
      addLog(`V2 Task sync complete: ${res.data.synced_count} tasks synced`, 'success')
      taskCount.value = res.data.synced_count || taskCount.value
      taskSyncStatus.value = 'completed'
    } else {
      setError('V2 Task sync failed: ' + res.data.message)
      taskSyncStatus.value = 'failed'
    }
  } catch (e) {
    console.error('V2 Task sync error:', e)
    setError('Error syncing V2 tasks: ' + (e.response?.data?.message || e.message))
    taskSyncStatus.value = 'failed'
  } finally {
    syncingTasks.value = false
  }
}

// EventSource for worklog sync
let worklogEventSource = null

// Sync Worklogs
const syncWorklogs = async () => {
  if (syncingWorklogs.value || !dateRangeValid.value) return

  syncingWorklogs.value = true
  worklogSyncStatus.value = 'in_progress'
  syncProgress.value.worklogs = 0
  streamProgress.value = 0
  error.value = null
  streamActive.value = true
  streamMessages.value = []

  try {
    const startDate = worklogDateRange.value.start_date
    const endDate = worklogDateRange.value.end_date

    addLog(`Starting TimeDoctor V2 worklog sync for date range ${startDate} to ${endDate}...`)

    if (worklogEventSource) {
      worklogEventSource.close()
      worklogEventSource = null
    }

    const url = `/api/timedoctor-v2/stream-worklog-sync?start_date=${startDate}&end_date=${endDate}`
    console.log(`Opening V2 EventSource to: ${url}`)
    worklogEventSource = new EventSource(url)

    worklogEventSource.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data)
        console.log("V2 EventSource message received:", data)

        streamMessages.value.push(data)

        if (data.progress !== undefined) {
          const progressValue = Number(data.progress)
          if (!isNaN(progressValue)) {
            console.log(`Updating V2 progress to ${progressValue}%`)
            streamProgress.value = progressValue
            syncProgress.value.worklogs = progressValue
          }
        }

        addLog(data.message, data.type)

        if (data.type === 'complete') {
          console.log("V2 Sync complete, cleaning up EventSource")
          if (worklogEventSource) {
            worklogEventSource.close()
            worklogEventSource = null
          }
          streamActive.value = false
          syncingWorklogs.value = false
          worklogSyncStatus.value = 'completed'
          streamProgress.value = 100
          syncProgress.value.worklogs = 100
          addLog('TimeDoctor V2 worklog sync completed', 'success')

          fetchCounts()
        }

        if (data.type === 'error') {
          console.log("V2 Sync error received:", data.message)
          setError(data.message)
          worklogSyncStatus.value = 'failed'
          syncingWorklogs.value = false
          if (worklogEventSource) {
            worklogEventSource.close()
            worklogEventSource = null
          }
        }
      } catch (e) {
        console.error('Error processing V2 stream data:', e)
        syncingWorklogs.value = false
        if (worklogEventSource) {
          worklogEventSource.close()
          worklogEventSource = null
        }
      }
    }

    worklogEventSource.onerror = (error) => {
      console.error('V2 Stream error:', error)
      setError('Error in TimeDoctor V2 worklog sync stream')
      if (worklogEventSource) {
        worklogEventSource.close()
        worklogEventSource = null
      }
      streamActive.value = false
      syncingWorklogs.value = false
      worklogSyncStatus.value = 'failed'
    }

  } catch (e) {
    console.error('V2 Worklog sync error:', e)
    setError('Error initiating V2 worklog sync: ' + (e.response?.data?.message || e.message))
    streamActive.value = false
    syncingWorklogs.value = false
    worklogSyncStatus.value = 'failed'
    if (worklogEventSource) {
      worklogEventSource.close()
      worklogEventSource = null
    }
  }
}

// Cancel worklog sync
const cancelWorklogSync = () => {
  if (worklogEventSource) {
    worklogEventSource.close()
    worklogEventSource = null
  }

  streamActive.value = false
  syncingWorklogs.value = false
  worklogSyncStatus.value = 'cancelled'
  addLog('TimeDoctor V2 worklog sync cancelled by user', 'warning')
}

// Sync all
const syncAll = async () => {
  if (syncing.value) return

  syncing.value = true
  error.value = null

  try {
    addLog('Starting full TimeDoctor V2 sync...')

    await syncUsers()
    await syncProjects()
    await syncTasks()

    if (dateRangeValid.value) {
      await syncWorklogs()
    }

    addLog('Full V2 sync completed', 'success')
  } catch (e) {
    console.error('Full V2 sync error:', e)
    setError('Error during full V2 sync: ' + e.message)
  } finally {
    syncing.value = false
  }
}

// Get status icon
const getStatusIcon = (status) => {
  switch (status) {
    case 'completed': return 'mdi-check-circle'
    case 'complete': return 'mdi-check-circle'
    case 'in_progress': return 'mdi-sync'
    case 'failed': return 'mdi-alert-circle'
    case 'error': return 'mdi-alert-circle'
    case 'cancelled': return 'mdi-cancel'
    case 'warning': return 'mdi-alert'
    case 'info': return 'mdi-information'
    case 'success': return 'mdi-check-circle'
    default: return 'mdi-circle-outline'
  }
}

// Get status color
const getStatusColor = (status) => {
  switch (status) {
    case 'completed': return 'success'
    case 'complete': return 'success'
    case 'in_progress': return 'info'
    case 'failed': return 'error'
    case 'error': return 'error'
    case 'cancelled': return 'warning'
    case 'warning': return 'warning'
    case 'info': return 'info'
    case 'success': return 'success'
    default: return 'grey'
  }
}

// Set worklog date range to today
const setToday = () => {
  const today = new Date().toISOString().slice(0, 10)
  worklogDateRange.value.start_date = today
  worklogDateRange.value.end_date = today
}

// Set worklog date range to yesterday
const setYesterday = () => {
  const yesterday = new Date()
  yesterday.setDate(yesterday.getDate() - 1)
  const formattedYesterday = yesterday.toISOString().slice(0, 10)

  worklogDateRange.value.start_date = formattedYesterday
  worklogDateRange.value.end_date = formattedYesterday
}

// Set worklog date range to last 7 days
const setLast7Days = () => {
  const today = new Date()
  const last7Days = new Date()
  last7Days.setDate(today.getDate() - 6)

  worklogDateRange.value.start_date = last7Days.toISOString().slice(0, 10)
  worklogDateRange.value.end_date = today.toISOString().slice(0, 10)
}

// Set worklog date range to current month
const setThisMonth = () => {
  const today = new Date()
  const firstDay = new Date(today.getFullYear(), today.getMonth(), 1)

  worklogDateRange.value.start_date = firstDay.toISOString().slice(0, 10)
  worklogDateRange.value.end_date = today.toISOString().slice(0, 10)
}

// Clear all logs
const clearLogs = () => {
  syncLogs.value = []
  addLog('V2 Logs cleared')
}

// Watch function to cleanup EventSource on component unmount
const cleanup = () => {
  if (worklogEventSource) {
    worklogEventSource.close()
    worklogEventSource = null
  }
}

onMounted(() => {
  checkConnection()
  setToday()
  window.addEventListener('beforeunload', cleanup)
})

onUnmounted(() => {
  cleanup()
  window.removeEventListener('beforeunload', cleanup)
})
</script>

<style scoped>
.time-doctor-v2-container {
  margin-block: 0;
  margin-inline: auto;
  max-inline-size: 1200px;
}

.status-card {
  border-radius: 8px;
  block-size: 100%;
  transition: all 0.3s ease;
}

.status-card:hover {
  box-shadow: 0 4px 8px rgba(0, 0, 0, 10%);
  transform: translateY(-2px);
}

.stream-container {
  padding: 8px;
  border: 1px solid rgba(0, 0, 0, 12%);
  border-radius: 8px;
  background-color: #f9f9f9;
  max-block-size: 300px;
  overflow-y: auto;
}

.stream-message {
  border-block-end: 1px solid rgba(0, 0, 0, 5%);
  padding-block: 4px;
  padding-inline: 8px;
}

.stream-message:last-child {
  border-block-end: none;
}

.log-container {
  padding: 8px;
  border: 1px solid rgba(0, 0, 0, 12%);
  border-radius: 8px;
  background-color: #f5f5f5;
  font-family: monospace;
  max-block-size: 400px;
  overflow-y: auto;
}

.log-entry {
  border-radius: 4px;
  line-height: 1.5;
  padding-block: 2px;
  padding-inline: 8px;
  white-space: pre-wrap;
  word-break: break-word;
}

.log-timestamp {
  color: #888;
  font-size: 0.85em;
}

.log-info {
  background-color: #f0f8ff;
}

.log-success {
  background-color: #f0fff0;
  color: #2e7d32;
}

.log-error {
  background-color: #fff0f0;
  color: #c62828;
}

.log-warning {
  background-color: #fffde7;
  color: #ff8f00;
}

.v-card-title {
  background: linear-gradient(90deg, rgba(245, 245, 245, 100%) 0%, rgba(254, 254, 254, 100%) 100%);
  border-block-end: 1px solid rgba(0, 0, 0, 5%);
}

.v-btn-group button {
  block-size: 36px !important;
  min-inline-size: 80px;
}

@media (max-width: 600px) {
  .log-timestamp {
    display: block;
  }
}
</style>
