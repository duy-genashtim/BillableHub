<script setup>
import { WORKLOG_CONFIG } from '@/@core/utils/worklogConfig';
import { formatDate, getCustomMonthOptionsForSummary, getWeekRangeForYear } from '@/@core/utils/worklogHelpers';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import RegionPerformanceChart from './components/RegionPerformanceChart.vue';
import RegionPerformanceSummary from './components/RegionPerformanceSummary.vue';
import OverallPerformanceTable from './components/OverallPerformanceTable.vue';

const router = useRouter();

// Data
const performanceData = ref(null);
const loading = ref(false);
const showDetails = ref(false);
const isMobile = ref(window.innerWidth < 768);

// Date selection
const dateMode = ref('weekly');
const selectedYear = ref(new Date().getFullYear());
const dateRanges = ref([]);
const selectedDateRange = ref(null);

// UI state
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

// Cache related
const isCachedData = ref(false);
const cachedAt = ref(null);
const generatedAt = ref(null);
const clearingCache = ref(false);

// Computed properties
const dateModeOptions = computed(() => [
  { title: 'Weekly Summary', value: 'weekly', icon: 'ri-time-line' },
  { title: 'Monthly Summary', value: 'monthly', icon: 'ri-calendar-event-line' },
  { title: 'Yearly Summary (52 Weeks)', value: 'yearly', icon: 'ri-calendar-line' }
]);

const yearOptions = computed(() => {
  const currentYear = new Date().getFullYear();
  const startYear = WORKLOG_CONFIG.START_YEAR;
  const options = [];

  for (let year = currentYear; year >= startYear; year--) {
    options.push(year);
  }
  return options;
});

const dateRangeOptions = computed(() => {
  return dateRanges.value.map((range, index) => ({
    title: range.label,
    value: index,
    subtitle: `${range.start_date} to ${range.end_date}`,
    ...range
  }));
});

const cacheStatusText = computed(() => {
  if (isCachedData.value && cachedAt.value) {
    const cacheTime = new Date(cachedAt.value);
    const now = new Date();
    const diffMinutes = Math.floor((now - cacheTime) / (1000 * 60));

    if (diffMinutes < 1) {
      return 'Just cached';
    } else if (diffMinutes < 60) {
      return `Cached ${diffMinutes}m ago`;
    } else {
      const diffHours = Math.floor(diffMinutes / 60);
      return `Cached ${diffHours}h ago`;
    }
  } else if (generatedAt.value) {
    return 'Fresh data';
  }
  return '';
});

const cacheStatusColor = computed(() => {
  if (isCachedData.value) {
    const cacheTime = new Date(cachedAt.value);
    const now = new Date();
    const diffMinutes = Math.floor((now - cacheTime) / (1000 * 60));

    if (diffMinutes < 10) return 'success';
    if (diffMinutes < 30) return 'warning';
    return 'error';
  }
  return 'info';
});

const hasData = computed(() => {
  return performanceData.value && performanceData.value.users_data && performanceData.value.users_data.length > 0;
});

const canLoadData = computed(() => {
  return selectedDateRange.value !== null;
});

// Data from server response
const fullTimeUsers = computed(() => {
  return performanceData.value?.full_time_users || [];
});

const partTimeUsers = computed(() => {
  return performanceData.value?.part_time_users || [];
});

const allUsers = computed(() => {
  return performanceData.value?.users_data || [];
});

const regionsData = computed(() => {
  return performanceData.value?.regions_data || [];
});

const summary = computed(() => {
  return performanceData.value?.summary || {
    full_time: {},
    part_time: {},
    overall: {}
  };
});

const categorySummary = computed(() => {
  return performanceData.value?.category_summary || [];
});

// Computed properties for work-status-specific category summaries
const fullTimeCategorySummary = computed(() => {
  if (!categorySummary.value.length || !fullTimeUsers.value.length) return [];
  
  return calculateWorkStatusCategorySummary(fullTimeUsers.value, categorySummary.value);
});

const partTimeCategorySummary = computed(() => {
  if (!categorySummary.value.length || !partTimeUsers.value.length) return [];
  
  return calculateWorkStatusCategorySummary(partTimeUsers.value, categorySummary.value);
});

const dateRangeInfo = computed(() => {
  return performanceData.value?.date_range || {};
});

// Stats for header
const totalUsersCount = computed(() => allUsers.value.length);
const totalRegionsCount = computed(() => regionsData.value.length);

onMounted(() => {
  window.addEventListener('resize', handleResize);
  generateDateRanges();
  // Load saved preferences
  showDetails.value = localStorage.getItem('overallPerformanceShowDetails') === 'true';
});

function handleResize() {
  isMobile.value = window.innerWidth < 768;
}

function generateDateRanges() {
  dateRanges.value = [];

  switch (dateMode.value) {
    case 'weekly': {
      const weeks = getWeekRangeForYear(selectedYear.value);

      dateRanges.value = weeks.map(week => ({
        label: `Week ${week.week_number} (${formatDate(week.start_date)} - ${formatDate(week.end_date)})`,
        start_date: week.start_date,
        end_date: week.end_date,
        week_number: week.week_number
      }));
      break;
    }

    case 'monthly': {
      const monthOptions = getCustomMonthOptionsForSummary(selectedYear.value);

      dateRanges.value = monthOptions.map(month => ({
        label: month.title,
        start_date: month.start_date,
        end_date: month.end_date,
        subtitle: month.subtitle
      }));
      break;
    }

    case 'yearly': {
      const weeks = getWeekRangeForYear(selectedYear.value);
      if (weeks.length >= 52) {
        const firstWeek = weeks[0];
        const lastWeek = weeks[51];

        dateRanges.value.push({
          label: `Year ${selectedYear.value} (52 weeks)`,
          start_date: firstWeek.start_date,
          end_date: lastWeek.end_date
        });
      }
      break;
    }
  }

  // Reset selection
  selectedDateRange.value = dateRanges.value.length > 0 ? 0 : null;
}

async function loadPerformanceData(forceReload = false) {
  if (!canLoadData.value) {
    showSnackbar('Please select a date range', 'warning');
    return;
  }

  loading.value = true;
  performanceData.value = null;

  try {
    const range = dateRanges.value[selectedDateRange.value];

    const params = {
      year: selectedYear.value,
      start_date: range.start_date,
      end_date: range.end_date,
      mode: dateMode.value,
      show_details: showDetails.value,
      force_reload: forceReload
    };

    const response = await axios.get('/api/reports/overall-performance', { params });

    performanceData.value = response.data;

    // Update cache status
    isCachedData.value = response.data.cached || false;
    cachedAt.value = response.data.cached_at || null;
    generatedAt.value = response.data.generated_at || null;

    if (forceReload) {
      showSnackbar('Data reloaded successfully', 'success');
    }

  } catch (error) {
    console.error('Error loading performance data:', error);
    showSnackbar(error.response?.data?.message || 'Failed to load performance data', 'error');
  } finally {
    loading.value = false;
  }
}

async function reloadCache() {
  clearingCache.value = true;

  try {
    // Clear cache first
    const params = {
      year: selectedYear.value,
      mode: dateMode.value
    };

    await axios.post('/api/reports/overall-performance/clear-cache', params);

    // Then fetch fresh data
    await loadPerformanceData(true);

    showSnackbar('Cache reloaded successfully', 'success');
  } catch (error) {
    console.error('Error reloading cache:', error);
    showSnackbar('Failed to reload cache', 'error');
  } finally {
    clearingCache.value = false;
  }
}

function viewUserDashboard(userId) {
  if (!performanceData.value) return;

  const range = dateRanges.value[selectedDateRange.value];
  const params = {
    start_date: range.start_date,
    end_date: range.end_date
  };

  // Add mode-specific params
  if (dateMode.value === 'weekly') {
    params.mode = 'weekly_summary';
    params.year = selectedYear.value;

    // Calculate week numbers
    const weeks = getWeekRangeForYear(selectedYear.value);
    const startWeek = weeks.find(w => w.start_date === range.start_date);
    if (startWeek) {
      params.week_number = startWeek.week_number;
      params.week_count = 1;
    }
  } else if (dateMode.value === 'monthly') {
    params.mode = 'month_summary';
    params.year = selectedYear.value;
    params.month = 1; // Will be calculated from start_date
    params.month_count = 1;
  }

  router.push({
    name: 'iva-user-worklog-dashboard',
    params: { id: userId },
    query: params
  });
}

function exportData() {
  if (!hasData.value) return;

  // Prepare CSV data
  const headers = [
    'Region',
    'Name',
    'Email',
    'Job Title',
    'Work Status',
    'Billable Hours',
    'Non-Billable Hours',
    'Total Hours',
    'Target Hours',
    'Performance %',
    'Performance Status',
    'NAD Count',
    'NAD Hours'
  ];

  // Add category headers
  const categoryNames = categorySummary.value.map(cat => cat.category_name);
  headers.push(...categoryNames);

  // Process user data
  const rows = allUsers.value.map(user => {
    const row = [
      user.region_name || '',
      user.full_name,
      user.email,
      user.job_title || '',
      user.work_status,
      user.billable_hours || 0,
      user.non_billable_hours || 0,
      user.total_hours || 0,
      user.target_hours || 0,
      user.performance?.percentage || 0,
      user.performance?.status || 'BELOW',
      user.nad_count || 0,
      user.nad_hours || 0
    ];

    // Add category hours
    categoryNames.forEach(catName => {
      const category = user.categories?.find(c => c.category_name === catName);
      row.push(category?.hours || 0);
    });

    return row;
  });

  // Create CSV content
  const csvContent = [
    headers.join(','),
    ...rows.map(row => row.map(cell => `"${cell}"`).join(','))
  ].join('\n');

  // Download CSV
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  const url = URL.createObjectURL(blob);

  const filename = `overall_performance_${selectedYear.value}_${dateMode.value}.csv`;

  link.setAttribute('href', url);
  link.setAttribute('download', filename);
  link.style.visibility = 'hidden';

  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);

  showSnackbar('Report exported successfully', 'success');
}

function calculateWorkStatusCategorySummary(users, allCategorySummary) {
  // Initialize category summary with structure from all categories
  const workStatusSummary = allCategorySummary.map(category => ({
    ...category,
    total_hours: 0,
    user_count: 0,
    avg_hours_per_user: 0
  }));

  // Calculate totals for this work status group
  workStatusSummary.forEach(category => {
    let totalHours = 0;
    let userCount = 0;

    users.forEach(user => {
      if (user.categories) {
        const userCategory = user.categories.find(c => c.category_id === category.category_id);
        if (userCategory && userCategory.hours > 0) {
          totalHours += userCategory.hours;
          userCount++;
        }
      }
    });

    category.total_hours = Math.round(totalHours * 100) / 100; // Round to 2 decimal places
    category.user_count = userCount;
    category.avg_hours_per_user = userCount > 0 ? Math.round((totalHours / userCount) * 100) / 100 : 0;
  });

  // Sort by total hours descending
  return workStatusSummary.sort((a, b) => b.total_hours - a.total_hours);
}

function showSnackbar(message, color = 'success') {
  snackbarText.value = message;
  snackbarColor.value = color;
  snackbar.value = true;
}

// Watch for changes
watch(dateMode, () => {
  generateDateRanges();
});

watch(selectedYear, () => {
  generateDateRanges();
});

watch(showDetails, (newValue) => {
  if (newValue) {
    localStorage.setItem('overallPerformanceShowDetails', 'true');
  } else {
    localStorage.removeItem('overallPerformanceShowDetails');
  }
});
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <VBreadcrumbs :items="[
      { title: 'Home', to: '/' },
      { title: 'Reports', disabled: true },
      { title: 'Overall Performance', disabled: true }
    ]" class="mb-6" aria-label="Breadcrumb navigation" />

    <!-- Header Card -->
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex flex-wrap align-center justify-space-between gap-4">
          <div>
            <h1 class="text-h5 text-md-h4 font-weight-bold mb-2" tabindex="0">
              Overall Performance Report
            </h1>
            <p class="text-body-1 mb-2">
              Track performance metrics for all IVA users across all regions
            </p>
            <div class="d-flex flex-wrap gap-2">
              <VChip v-if="totalUsersCount > 0" color="primary" size="small" prepend-icon="ri-group-line">
                {{ totalUsersCount }} IVA Users
              </VChip>
              <VChip v-if="totalRegionsCount > 0" color="info" size="small" variant="tonal" prepend-icon="ri-map-pin-line">
                {{ totalRegionsCount }} Regions
              </VChip>
              <VChip v-if="cacheStatusText" :color="cacheStatusColor" size="small" variant="tonal"
                prepend-icon="ri-database-line">
                {{ cacheStatusText }}
              </VChip>
              <VChip v-if="dateRangeInfo.start" color="secondary" size="small" variant="tonal"
                prepend-icon="ri-calendar-line">
                {{ formatDate(dateRangeInfo.start) }} - {{ formatDate(dateRangeInfo.end) }}
              </VChip>
            </div>
          </div>

          <div class="d-flex gap-2 flex-wrap">
            <VBtn color="primary" variant="outlined" prepend-icon="ri-download-line" @click="exportData"
              :disabled="loading || !hasData" aria-label="Export report">
              Export CSV
            </VBtn>

            <VTooltip text="Reload fresh data and clear cache" location="top">
              <template #activator="{ props }">
                <VBtn v-bind="props" color="warning" variant="outlined" prepend-icon="ri-refresh-line"
                  @click="reloadCache" :loading="clearingCache" :disabled="loading || !hasData"
                  aria-label="Reload cache">
                  Reload
                </VBtn>
              </template>
            </VTooltip>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- Date Selection Card -->
    <VCard class="mb-6">
      <VCardText>
        <h2 class="text-h6 font-weight-medium mb-4">Selection Parameters</h2>

        <VRow>
          <!-- Date Mode -->
          <VCol cols="12" md="4">
            <VSelect v-model="dateMode" :items="dateModeOptions" label="Report Type" density="comfortable"
              variant="outlined" aria-label="Select report type">
              <template #item="{ item, props }">
                <VListItem v-bind="props">
                  <template #prepend>
                    <VIcon :icon="item.raw.icon" />
                  </template>
                </VListItem>
              </template>
            </VSelect>
          </VCol>

          <!-- Year -->
          <VCol cols="12" md="3">
            <VSelect v-model="selectedYear" :items="yearOptions" label="Year" density="comfortable" variant="outlined"
              aria-label="Select year" />
          </VCol>

          <!-- Date Range -->
          <VCol cols="12" md="5">
            <VSelect v-model="selectedDateRange" :items="dateRangeOptions" label="Date Range" density="comfortable"
              variant="outlined" aria-label="Select date range">
              <template #item="{ item, props }">
                <VListItem v-bind="props">
                  <template #subtitle v-if="item.raw.subtitle">
                    {{ item.raw.subtitle }}
                  </template>
                </VListItem>
              </template>
            </VSelect>
          </VCol>

          <!-- Show Details Switch -->
          <VCol cols="12">
            <VSwitch v-model="showDetails" label="Show Email & Job Title" color="primary" density="comfortable" />
          </VCol>
        </VRow>

        <!-- Load Data Button -->
        <div class="d-flex justify-center mt-4">
          <VBtn color="primary" size="large" prepend-icon="ri-bar-chart-line" @click="loadPerformanceData()"
            :disabled="!canLoadData" :loading="loading" aria-label="Load performance data">
            Load Performance Data
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <!-- Loading State -->
    <div v-if="loading" class="d-flex justify-center align-center py-12">
      <div class="text-center">
        <VProgressCircular indeterminate color="primary" :size="60" :width="6" class="mb-4"
          aria-label="Loading performance data" />
        <h3 class="text-h6 font-weight-regular mb-2">
          {{ clearingCache ? 'Reloading Fresh Data...' : 'Loading Overall Performance Data' }}
        </h3>
        <p class="text-secondary">
          {{ clearingCache ? 'Clearing cache and fetching fresh data...' : 'Analyzing all IVA users across regions...' }}
        </p>
      </div>
    </div>

    <!-- Performance Data -->
    <div v-else-if="hasData">
      <!-- Summary Cards -->
      <RegionPerformanceSummary :summary="summary" :category-summary="categorySummary" :is-mobile="isMobile"
        class="mb-6" />

      <!-- Performance Chart -->
      <RegionPerformanceChart :full-time-summary="summary.full_time" :part-time-summary="summary.part_time"
        :overall-summary="summary.overall" :is-mobile="isMobile" class="mb-6" />

      <!-- Full-Time Users Section -->
      <VCard v-if="fullTimeUsers.length > 0" class="mb-6">
        <VCardItem>
          <VCardTitle class="d-flex align-center">
            <VIcon icon="ri-user-fill" color="primary" class="mr-2" />
            Full-Time Employees ({{ fullTimeUsers.length }})
          </VCardTitle>
        </VCardItem>

        <VCardText>
          <OverallPerformanceTable :regions-data="regionsData" :users="fullTimeUsers" :show-details="showDetails" 
            :categories="fullTimeCategorySummary" :date-mode="dateMode" @view-dashboard="viewUserDashboard" />
        </VCardText>
      </VCard>

      <!-- Part-Time Users Section -->
      <VCard v-if="partTimeUsers.length > 0">
        <VCardItem>
          <VCardTitle class="d-flex align-center">
            <VIcon icon="ri-user-3-line" color="secondary" class="mr-2" />
            Part-Time Employees ({{ partTimeUsers.length }})
          </VCardTitle>
        </VCardItem>

        <VCardText>
          <OverallPerformanceTable :regions-data="regionsData" :users="partTimeUsers" :show-details="showDetails" 
            :categories="partTimeCategorySummary" :date-mode="dateMode" @view-dashboard="viewUserDashboard" />
        </VCardText>
      </VCard>
    </div>

    <!-- No Data State -->
    <VCard v-else-if="!loading && selectedDateRange !== null">
      <VCardText class="text-center py-8">
        <VIcon size="48" color="secondary" icon="ri-bar-chart-line" class="mb-4" />
        <h3 class="text-h6 font-weight-regular mb-2">No data to display</h3>
        <p class="text-secondary mb-4">
          Click "Load Performance Data" to fetch the overall report for the selected date range.
        </p>
      </VCardText>
    </VCard>

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
/* Mobile responsiveness */
@media (max-width: 767px) {
  :deep(.v-card-text) {
    padding: 16px;
  }

  .text-h4 {
    font-size: 1.5rem !important;
  }

  .text-h5 {
    font-size: 1.25rem !important;
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

/* Card shadow enhancement */
:deep(.v-card) {
  transition: box-shadow 0.3s ease;
}

:deep(.v-card:hover) {
  box-shadow: 0 8px 16px rgba(0, 0, 0, 10%);
}
</style>