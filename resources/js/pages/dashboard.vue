<script setup>
import { useAuthStore } from '@/@core/stores/auth'
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import CategoriesPerformanceCard from './dashboard/components/CategoriesPerformanceCard.vue'
import PerformanceMetricsCards from './dashboard/components/PerformanceMetricsCards.vue'
import PerformanceTrendsChart from './dashboard/components/PerformanceTrendsChart.vue'
import QuickActionsCard from './dashboard/components/QuickActionsCard.vue'
import RecentActivityCard from './dashboard/components/RecentActivityCard.vue'
import RegionalBreakdownCard from './dashboard/components/RegionalBreakdownCard.vue'
import SystemOverviewCards from './dashboard/components/SystemOverviewCards.vue'
import TopPerformersWidget from './dashboard/components/TopPerformersWidget.vue'

const router = useRouter()
const authStore = useAuthStore()

// Data
const dashboardData = ref(null)
const loading = ref(false)
const isMobile = ref(window.innerWidth < 768)
const refreshing = ref(false)

// UI state
const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref('success')

// Cache status
const isCachedData = ref(false)
const cachedAt = ref(null)
const generatedAt = ref(null)

// Computed properties
const userName = computed(() => {
  return authStore.user?.name || authStore.user?.email || 'User'
})

const userGreeting = computed(() => {
  const hour = new Date().getHours()
  let greeting = 'Hello'

  if (hour < 12) greeting = 'Good morning'
  else if (hour < 17) greeting = 'Good afternoon'
  else greeting = 'Good evening'

  return `${greeting}, ${userName.value}!`
})

const cacheStatusText = computed(() => {
  if (isCachedData.value && cachedAt.value) {
    const cacheTime = new Date(cachedAt.value)
    const now = new Date()
    const diffMinutes = Math.floor((now - cacheTime) / (1000 * 60))

    if (diffMinutes < 1) {
      return 'Just updated'
    } else if (diffMinutes < 60) {
      return `Updated ${diffMinutes}m ago`
    } else {
      const diffHours = Math.floor(diffMinutes / 60)
      return `Updated ${diffHours}h ago`
    }
  } else if (generatedAt.value) {
    return 'Live data'
  }
  return ''
})

const cacheStatusColor = computed(() => {
  if (isCachedData.value && cachedAt.value) {
    const cacheTime = new Date(cachedAt.value)
    const now = new Date()
    const diffMinutes = Math.floor((now - cacheTime) / (1000 * 60))

    if (diffMinutes < 5) return 'success'
    if (diffMinutes < 15) return 'warning'
    return 'error'
  }
  return 'info'
})

// Methods
onMounted(() => {
  window.addEventListener('resize', handleResize)
  loadDashboardData()
})

function handleResize() {
  isMobile.value = window.innerWidth < 768
}

async function loadDashboardData() {
  loading.value = true

  try {
    const response = await axios.get('/api/dashboard/overview')

    dashboardData.value = response.data

    // Update cache status
    isCachedData.value = response.data.cached || false
    cachedAt.value = response.data.cached_at || null
    generatedAt.value = response.data.generated_at || null

  } catch (error) {
    console.error('Error loading dashboard data:', error)
    showSnackbar(
      error.response?.data?.message || 'Failed to load dashboard data',
      'error'
    )
  } finally {
    loading.value = false
  }
}

async function refreshDashboard() {
  refreshing.value = true

  try {
    // Clear cache first
    await axios.post('/api/dashboard/clear-cache')

    // Then reload data
    await loadDashboardData()

    showSnackbar('Dashboard refreshed successfully', 'success')
  } catch (error) {
    console.error('Error refreshing dashboard:', error)
    showSnackbar('Failed to refresh dashboard', 'error')
  } finally {
    refreshing.value = false
  }
}

function showSnackbar(message, color = 'success') {
  snackbarText.value = message
  snackbarColor.value = color
  snackbar.value = true
}

// Event handlers
function handleViewRegion(regionId) {
  router.push({
    name: 'region-detail',
    params: { id: regionId }
  })
}

function handleViewUser(userId) {
  router.push({
    name: 'iva-user-worklog-dashboard',
    params: { id: userId }
  })
}

function handleViewCategory(categoryId) {
  router.push({
    name: 'category-detail',
    params: { id: categoryId }
  })
}
</script>

<template>
  <div>
    <!-- Page Header -->
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex flex-wrap align-center justify-space-between gap-4">
          <div>
            <h1 class="text-h4 text-md-h3 font-weight-bold mb-2">
              {{ userGreeting }}
            </h1>
            <p class="text-body-1 text-medium-emphasis mb-2">
              Welcome to HOURS - Hours Tracking and Reporting System dashboard. Monitor IVA performance, manage
              users, and track billable hours.
            </p>
            <div class="d-flex flex-wrap gap-2">
              <VChip v-if="cacheStatusText" :color="cacheStatusColor" size="small" variant="tonal"
                prepend-icon="ri-database-line">
                {{ cacheStatusText }}
              </VChip>
              <VChip color="primary" size="small" variant="tonal" prepend-icon="ri-calendar-line">
                {{ new Date().toLocaleDateString('en-US', {
                  weekday: 'long',
                  year: 'numeric',
                  month: 'long',
                  day: 'numeric'
                }) }}
              </VChip>
            </div>
          </div>

          <div class="d-flex gap-2 flex-wrap">
            <VBtn color="primary" variant="outlined" prepend-icon="ri-refresh-line" @click="refreshDashboard"
              :loading="refreshing" :disabled="loading">
              Refresh
            </VBtn>
            <VBtn color="success" variant="flat" prepend-icon="ri-bar-chart-line"
              @click="router.push('/admin/reports/daily-performance')">
              View Reports
            </VBtn>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- Loading State -->
    <div v-if="loading" class="d-flex justify-center align-center py-12">
      <div class="text-center">
        <VProgressCircular indeterminate color="primary" :size="60" :width="6" class="mb-4" />
        <h3 class="text-h6 font-weight-regular mb-2">Loading Dashboard</h3>
        <p class="text-secondary">
          Fetching the latest performance data and metrics...
        </p>
      </div>
    </div>

    <!-- Dashboard Content -->
    <div v-else-if="dashboardData">
      <!-- System Overview -->
      <SystemOverviewCards :system-overview="dashboardData.system_overview" :is-mobile="isMobile" class="mb-6" />

      <!-- Performance Metrics -->
      <PerformanceMetricsCards :current-week="dashboardData.current_week_performance"
        :current-month="dashboardData.current_month_performance" :is-mobile="isMobile" class="mb-6" />

      <!-- Main Content Grid -->
      <VRow class="match-height">
        <!-- Left Column -->
        <VCol cols="12" lg="8">
          <VRow class="match-height">
            <!-- Performance Trends Chart -->
            <VCol cols="12">
              <PerformanceTrendsChart :trends-data="dashboardData.performance_trends" :is-mobile="isMobile"
                class="mb-6" />
            </VCol>

            <!-- Categories Performance -->
            <VCol cols="12" md="6">
              <CategoriesPerformanceCard :categories-data="dashboardData.categories_performance" :is-mobile="isMobile"
                @view-category="handleViewCategory" />
            </VCol>

            <!-- Regional Breakdown -->
            <VCol cols="12" md="6">
              <RegionalBreakdownCard :regional-data="dashboardData.regional_breakdown" :is-mobile="isMobile"
                @view-region="handleViewRegion" />
            </VCol>

            <!-- Quick Actions -->
            <VCol cols="12">
              <QuickActionsCard :is-mobile="isMobile" />
            </VCol>
          </VRow>
        </VCol>

        <!-- Right Column -->
        <VCol cols="12" lg="4">
          <VRow class="match-height">
            <!-- Recent Activity -->
            <VCol cols="12">
              <RecentActivityCard :recent-activity="dashboardData.recent_activity" :is-mobile="isMobile"
                @view-user="handleViewUser" class="mb-6" />
            </VCol>

            <!-- Top Performers - Name it Highest Time Logged -->
            <VCol cols="12">
              <TopPerformersWidget :top-performers="dashboardData.top_performers || []" :is-mobile="isMobile"
                @view-user="handleViewUser" class="mb-6" />
            </VCol>


          </VRow>
        </VCol>
      </VRow>
    </div>

    <!-- Error State -->
    <VCard v-else class="text-center py-12">
      <VCardText>
        <VIcon size="64" color="error" icon="ri-error-warning-line" class="mb-4" />
        <h2 class="text-h5 font-weight-bold mb-2">Failed to Load Dashboard</h2>
        <p class="text-body-1 text-medium-emphasis mb-6">
          There was an error loading the dashboard data. Please try refreshing the page.
        </p>
        <VBtn color="primary" size="large" prepend-icon="ri-refresh-line" @click="loadDashboardData">
          Try Again
        </VBtn>
      </VCardText>
    </VCard>

    <!-- Snackbar for notifications -->
    <VSnackbar v-model="snackbar" :color="snackbarColor" :timeout="3000" location="top right">
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
/* Enhanced responsive design */
@media (max-width: 767px) {
  :deep(.v-card-text) {
    padding: 16px;
  }

  .text-h3 {
    font-size: 1.75rem !important;
  }

  .text-h4 {
    font-size: 1.5rem !important;
  }
}

/* Enhanced focus states for accessibility */
:deep(.v-btn:focus-visible) {
  outline: 2px solid var(--v-theme-primary);
  outline-offset: 2px;
}

/* Progress styling */
:deep(.v-progress-circular) {
  font-weight: 600;
}

/* Card hover effects */
:deep(.v-card) {
  transition: box-shadow 0.3s ease;
}

/* Match height utility for equal column heights */
.match-height {
  display: flex;
  flex-wrap: wrap;
}

.match-height>.v-col {
  display: flex;
  flex-direction: column;
}

.match-height .v-card {
  flex: 1;
}

/* Loading animation */
@keyframes pulse {
  0% {
    opacity: 1;
  }

  50% {
    opacity: 0.5;
  }

  100% {
    opacity: 1;
  }
}

.loading-pulse {
  animation: pulse 1.5s ease-in-out infinite;
}
</style>
