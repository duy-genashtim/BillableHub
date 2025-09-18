<script setup>
import { formatShortDate, getPerformanceColor, getPerformanceIcon, getProgressColor } from '@/@core/utils/helpers';
import { WORKLOG_CONFIG } from '@/@core/utils/worklogConfig';
import { formatDate, getCurrentWeekNumber, getWeekRangeForYear } from '@/@core/utils/worklogHelpers';
import { filterFutureWeeks } from '@/@core/utils/dateValidation';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import PerformanceChart from './components/PerformanceChart.vue';
const router = useRouter();

// Data
const performanceData = ref([]);
const summary = ref({});
const selectedYear = ref(Math.max(WORKLOG_CONFIG.START_YEAR, new Date().getFullYear()));
const selectedWeekNumber = ref(getCurrentWeekNumber());
const availableWeeks = ref([]);
const workStatusOptions = ref([]);
const regionOptions = ref([]);
const loading = ref(true);
const clearingCache = ref(false);
const searchQuery = ref('');
const selectedWorkStatus = ref('');
const selectedRegion = ref('');
const sortBy = ref('performance');
const sortOrder = ref('desc');
const showDetails = ref(false);
const isMobile = ref(window.innerWidth < 768);

// Cache related data
const isCachedData = ref(false);
const cachedAt = ref(null);
const generatedAt = ref(null);
const cacheInfo = ref(null);

// UI state
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

// Computed properties
const weekOptions = computed(() => {
  // Filter future weeks based on selected year
  const filteredWeeks = filterFutureWeeks(availableWeeks.value, selectedYear.value);

  return filteredWeeks.map(week => ({
    title: `Week ${week.week_number} (${formatShortDate(week.start_date)} - ${formatShortDate(week.end_date)})`,
    value: week.week_number,
    subtitle: week.is_current ? 'Current Week' : '',
    start_date: week.start_date,
    end_date: week.end_date,
  }));
});

const currentWeekData = computed(() => {
  return availableWeeks.value.find(w => w.week_number === selectedWeekNumber.value);
});

const dateRangeText = computed(() => {
  if (!currentWeekData.value) return '';
  const start = new Date(currentWeekData.value.start_date);
  const end = new Date(currentWeekData.value.end_date);
  return `${formatDate(start)} - ${formatDate(end)}`;
});

const sortOptions = computed(() => [
  { title: 'Performance', value: 'performance', icon: 'ri-trophy-line' },
  { title: 'Name', value: 'name', icon: 'ri-user-line' },
  { title: 'Billable Hours', value: 'billable', icon: 'ri-money-dollar-circle-line' },
  { title: 'Non-Billable Hours', value: 'non_billable', icon: 'ri-time-line' },
  { title: 'Uncategorized Hours', value: 'uncategorized', icon: 'ri-question-line' },
  { title: 'Total Hours', value: 'total', icon: 'ri-calculator-line' },
]);

const filteredWorkStatusOptions = computed(() => {
  return [
    { title: 'All Status', value: '' },
    ...workStatusOptions.value.map(status => ({
      title: status.description,
      value: status.value,
      subtitle: status.description
    }))
  ];
});

const filteredRegionOptions = computed(() => {
  return [
    { title: 'All Regions', value: '' },
    ...regionOptions.value.map(region => ({
      title: region,
      value: region
    }))
  ];
});

const yearOptions = computed(() => {
  const currentYear = new Date().getFullYear();
  const startYear = WORKLOG_CONFIG.START_YEAR;
  const options = [];

  // From current year back to start year
  for (let year = currentYear; year >= startYear; year--) {
    options.push(year);
  }
  return options;
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


function getWorkStatusDescription(value, options) {
  if (!value || !Array.isArray(options)) return 'Unknown';

  const match = options.find(option => option.value === value);
  return match ? match.description : 'Unknown';
}

onMounted(() => {
  fetchAvailableWeeks();
  fetchPerformanceData();
  window.addEventListener('resize', handleResize);

  // Load saved preferences
  showDetails.value = localStorage.getItem('weeklyPerformanceShowDetails') === 'true';
});

function handleResize() {
  isMobile.value = window.innerWidth < 768;
}

async function fetchAvailableWeeks() {
  try {
    const weekRanges = getWeekRangeForYear(selectedYear.value);
    availableWeeks.value = weekRanges.map(week => ({
      ...week,
      is_current: week.week_number === getCurrentWeekNumber() && week.year === new Date().getFullYear()
    }));
  } catch (error) {
    console.error('Error generating available weeks:', error);
    showSnackbar('Error loading available weeks', 'error');
  }
}

async function fetchPerformanceData(forceReload = false) {
  loading.value = true;

  try {
    const weekData = currentWeekData.value;
    if (!weekData) {
      throw new Error('Week data not available');
    }

    const params = {
      year: selectedYear.value,
      week_number: selectedWeekNumber.value,
      start_date: weekData.start_date,
      end_date: weekData.end_date,
      work_status: selectedWorkStatus.value,
      region: selectedRegion.value,
      search: searchQuery.value,
      sort_by: sortBy.value,
      sort_order: sortOrder.value,
      force_reload: forceReload,
    };

    const response = await axios.get('/api/reports/weekly-performance', { params });

    performanceData.value = response.data.performance_data;
    summary.value = response.data.summary;
    workStatusOptions.value = response.data.work_status_options || [];
    regionOptions.value = response.data.region_options || [];

    // Update cache status
    isCachedData.value = response.data.cached || false;
    cachedAt.value = response.data.cached_at || null;
    generatedAt.value = response.data.generated_at || null;

    if (forceReload) {
      showSnackbar('Data reloaded successfully', 'success');
    }

  } catch (error) {
    console.error('Error fetching performance data:', error);
    showSnackbar(error.response?.data?.message || 'Failed to load performance data', 'error');
  } finally {
    loading.value = false;
  }
}

async function reloadCache() {
  clearingCache.value = true;

  try {
    // Clear cache first
    await clearCache();

    // Then fetch fresh data
    await fetchPerformanceData(true);

    showSnackbar('Cache reloaded successfully', 'success');
  } catch (error) {
    console.error('Error reloading cache:', error);
    showSnackbar('Failed to reload cache', 'error');
  } finally {
    clearingCache.value = false;
  }
}

async function clearCache() {
  try {
    const weekData = currentWeekData.value;
    if (!weekData) return;

    const params = {
      cache_type: 'weekly',
      year: selectedYear.value,
      week_number: selectedWeekNumber.value,
    };

    await axios.post('/api/reports/weekly-performance/clear-cache', params);
  } catch (error) {
    console.error('Error clearing cache:', error);
    throw error;
  }
}

function onYearChange() {
  fetchAvailableWeeks();

  // When year changes, ensure selected week is valid for the new year
  // If switching to current year and selected week is beyond current week, reset to current week
  const currentYear = new Date().getFullYear();
  if (selectedYear.value === currentYear) {
    const currentWeek = getCurrentWeekNumber();
    if (selectedWeekNumber.value > currentWeek) {
      selectedWeekNumber.value = currentWeek;
    }
  }

  fetchPerformanceData();
}

function onWeekChange() {
  fetchPerformanceData();
}

function onSearchChange() {
  // Debounce search
  clearTimeout(window.searchTimeout);
  window.searchTimeout = setTimeout(() => {
    fetchPerformanceData();
  }, 300);
}

function onFilterChange() {
  fetchPerformanceData();
}

function onSortChange(newSortBy) {
  if (sortBy.value === newSortBy) {
    // Toggle sort order
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortBy.value = newSortBy;
    sortOrder.value = newSortBy === 'name' ? 'asc' : 'desc';
  }
  fetchPerformanceData();
}

function viewUserDashboard(userId) {
  const weekData = currentWeekData.value;
  if (!weekData) return;

  const params = {
    year: selectedYear.value,
    week_number: selectedWeekNumber.value,
    week_count: 1,
    start_date: weekData.start_date,
    end_date: weekData.end_date,
  };

  router.push({
    name: 'iva-user-worklog-dashboard',
    params: { id: userId },
    query: params
  });
}

function showSnackbar(message, color = 'success') {
  snackbarText.value = message;
  snackbarColor.value = color;
  snackbar.value = true;
}

function goToCurrentWeek() {
  const currentYear = new Date().getFullYear();
  const currentWeek = getCurrentWeekNumber();

  selectedYear.value = currentYear;
  selectedWeekNumber.value = currentWeek;

  if (selectedYear.value !== currentYear) {
    fetchAvailableWeeks();
  } else {
    fetchPerformanceData();
  }
}

function exportData() {
  // Prepare CSV data
  const headers = [
    'Name',
    'Email',
    'Job Title',
    'Work Status',
    'Region',
    'Billable Hours',
    'Non-Billable Hours',
    'Uncategorized Hours',
    'Total Hours',
    'Target Hours',
    'Performance %',
    'Performance Status'
  ];

  const rows = performanceData.value.map(user => [
    user.full_name,
    user.email,
    user.job_title || '',
    getWorkStatusDescription(user.work_status, workStatusOptions.value),
    user.region || '',
    user.billable_hours,
    user.non_billable_hours,
    user.uncategorized_hours,
    user.total_hours,
    user.target_hours,
    user.performance_percentage,
    user.performance_status
  ]);

  // Create CSV content
  const csvContent = [
    headers.join(','),
    ...rows.map(row => row.map(cell => `"${cell}"`).join(','))
  ].join('\n');

  // Download CSV
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  const url = URL.createObjectURL(blob);

  const weekData = currentWeekData.value;
  const filename = `weekly_performance_${selectedYear.value}_week_${selectedWeekNumber.value}_${weekData?.start_date || 'unknown'}.csv`;

  link.setAttribute('href', url);
  link.setAttribute('download', filename);
  link.style.visibility = 'hidden';

  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);

  showSnackbar('Report exported successfully', 'success');
}

// Watch for changes in showDetails to save preference
watch(showDetails, (newValue) => {
  if (newValue) {
    localStorage.setItem('weeklyPerformanceShowDetails', 'true');
  } else {
    localStorage.removeItem('weeklyPerformanceShowDetails');
  }
});
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <VBreadcrumbs :items="[
      { title: 'Home', to: '/' },
      { title: 'Reports', disabled: true },
      { title: 'Weekly Performance', disabled: true }
    ]" class="mb-6" aria-label="Breadcrumb navigation" />

    <!-- Header Card -->
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex flex-wrap align-center justify-space-between gap-4">
          <div>
            <h1 class="text-h5 text-md-h4 font-weight-bold mb-2" tabindex="0">
              Weekly Performance Report
            </h1>
            <p class="text-body-1 mb-2">
              Track weekly working hours and performance for all active IVA users
            </p>
            <div class="d-flex flex-wrap gap-2">
              <VChip v-if="dateRangeText" color="primary" size="small" prepend-icon="ri-calendar-week-line">
                Week {{ selectedWeekNumber }} - {{ dateRangeText }}
              </VChip>
              <VChip v-if="cacheStatusText" :color="cacheStatusColor" size="small" variant="tonal"
                prepend-icon="ri-database-line">
                {{ cacheStatusText }}
              </VChip>
            </div>
          </div>

          <div class="d-flex gap-2 flex-wrap">
            <VBtn color="primary" variant="outlined" prepend-icon="ri-download-line" @click="exportData"
              :disabled="loading || performanceData.length === 0" aria-label="Export report">
              Export
            </VBtn>

            <VTooltip text="Reload fresh data and clear cache" location="top">
              <template #activator="{ props }">
                <VBtn v-bind="props" color="warning" variant="outlined" prepend-icon="ri-refresh-line"
                  @click="reloadCache" :loading="clearingCache" :disabled="loading" aria-label="Reload cache">
                  Reload
                </VBtn>
              </template>
            </VTooltip>

            <VBtn color="secondary" variant="outlined" prepend-icon="ri-calendar-check-line" @click="goToCurrentWeek"
              :disabled="loading" aria-label="Go to current week">
              Current Week
            </VBtn>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- Filters Card -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <!-- Year -->
          <VCol cols="12" md="2">
            <VSelect v-model="selectedYear" :items="yearOptions" label="Year" density="comfortable" variant="outlined"
              @update:model-value="onYearChange" aria-label="Select year" />
          </VCol>

          <!-- Week -->
          <VCol cols="12" md="4">
            <VSelect v-model="selectedWeekNumber" :items="weekOptions" label="Week" density="comfortable"
              variant="outlined" @update:model-value="onWeekChange" aria-label="Select week">
              <template v-slot:item="{ item, props }">
                <VListItem v-bind="props" :title="item.raw.title" :subtitle="item.raw.subtitle">
                  <template v-slot:prepend>
                    <VIcon v-if="item.raw.subtitle" color="primary" size="small">ri-calendar-check-line</VIcon>
                  </template>
                </VListItem>
              </template>
            </VSelect>
          </VCol>

          <!-- Search -->
          <VCol cols="12" md="3">
            <VTextField v-model="searchQuery" label="Search by name" density="comfortable" variant="outlined"
              prepend-inner-icon="ri-search-line" clearable @update:model-value="onSearchChange" />
          </VCol>

          <!-- Work Status -->
          <VCol cols="12" md="3">
            <VSelect v-model="selectedWorkStatus" :items="filteredWorkStatusOptions" label="Work Status"
              density="comfortable" variant="outlined" @update:model-value="onFilterChange" />
          </VCol>

          <!-- Region Filter -->
          <VCol cols="12" md="3">
            <VSelect v-model="selectedRegion" :items="filteredRegionOptions" label="Region" density="comfortable"
              variant="outlined" @update:model-value="onFilterChange" />
          </VCol>

          <!-- Show/Hide Switch -->
          <VCol cols="12" md="9">
            <VSwitch v-model="showDetails" label="Show Email & Job Title" color="primary" density="comfortable" />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>
    <!-- Summary Card -->
    <VCard v-if="!loading && summary.total_users > 0" class="mb-6">
      <VCardText>
        <div class="d-flex align-center justify-space-between mb-4">
          <h2 class="text-h6 font-weight-medium">
            Weekly Summary - {{ dateRangeText }}
          </h2>

          <!-- Cache status indicator -->
          <VChip v-if="cacheStatusText" :color="cacheStatusColor" size="small" variant="tonal">
            <VIcon icon="ri-database-line" size="x-small" class="mr-1" />
            {{ cacheStatusText }}
          </VChip>
        </div>

        <!-- Summary Grid -->
        <VRow class="mb-6">
          <VCol cols="6" md="2">
            <div class="text-center">
              <div class="text-h4 font-weight-bold text-primary">{{ summary.total_users }}</div>
              <div class="text-body-2">Total Users</div>
            </div>
          </VCol>

          <VCol cols="6" md="2">
            <div class="text-center">
              <div class="text-h4 font-weight-bold text-success">{{ summary.users_with_data }}</div>
              <div class="text-body-2">With Data</div>
            </div>
          </VCol>

          <VCol cols="6" md="2">
            <div class="text-center">
              <div class="text-h4 font-weight-bold text-info">{{ summary.total_billable_hours.toFixed(1) }}</div>
              <div class="text-body-2">Billable Hours</div>
            </div>
          </VCol>

          <VCol cols="6" md="2">
            <div class="text-center">
              <div class="text-h4 font-weight-bold text-warning">{{ summary.total_target_hours.toFixed(1) }}</div>
              <div class="text-body-2">Target Hours</div>
            </div>
          </VCol>

          <VCol cols="6" md="2">
            <div class="text-center">
              <div class="text-h4 font-weight-bold"
                :class="`text-${getProgressColor(summary.overall_performance_percentage)}`">
                {{ summary.overall_performance_percentage }}%
              </div>
              <div class="text-body-2">Overall Performance</div>
            </div>
          </VCol>

          <VCol cols="6" md="2">
            <div class="text-center">
              <div class="d-flex justify-center gap-1">
                <VChip color="success" size="x-small" text-color="white">{{ summary.users_excellent_performance }}
                </VChip>
                <VChip color="warning" size="x-small" text-color="white">{{ summary.users_warning_performance }}</VChip>
                <VChip color="error" size="x-small" text-color="white">{{ summary.users_poor_performance }}</VChip>
              </div>
              <div class="text-body-2">Performance Split</div>
            </div>
          </VCol>
        </VRow>

        <!-- Performance Chart Section -->
        <VRow>
          <VCol cols="12" lg="6">
            <PerformanceChart :excellent-count="summary.users_excellent_performance"
              :meet-count="summary.users_warning_performance" :below-count="summary.users_poor_performance"
              :total-users="summary.total_users" :loading="loading" />
          </VCol>

          <VCol cols="12" lg="6">
            <!-- Enhanced Performance Insights -->
            <VCard>


              <VCardText>
                <div class="d-flex flex-column gap-4">
                  <!-- Team Productivity Score -->
                  <div class="insight-metric pt-1">
                    <div class="d-flex align-center justify-space-between mb-2">
                      <div class="d-flex align-center">
                        <VIcon icon="ri-trophy-line" color="warning" size="small" class="me-2" />
                        <span class="text-body-2 font-weight-medium">Team Productivity Score</span>
                      </div>
                      <VChip :color="getProgressColor(summary.overall_performance_percentage)" size="small"
                        variant="flat" text-color="white">
                        {{ summary.overall_performance_percentage }}%
                      </VChip>
                    </div>
                    <VProgressLinear :model-value="summary.overall_performance_percentage"
                      :color="getProgressColor(summary.overall_performance_percentage)" height="8" rounded striped />
                    <div class="text-caption mt-1 text-disabled">
                      {{ summary.total_billable_hours.toFixed(0) }}h of {{ summary.total_target_hours.toFixed(0) }}h
                      target
                      achieved
                    </div>
                  </div>

                  <!-- Active Participation -->
                  <div class="insight-metric">
                    <div class="d-flex align-center justify-space-between mb-2">
                      <div class="d-flex align-center">
                        <VIcon icon="ri-user-check-line" color="info" size="small" class="me-2" />
                        <span class="text-body-2 font-weight-medium">Active Participation</span>
                      </div>
                      <span class="text-caption">
                        {{ summary.users_with_data }}/{{ summary.total_users }}
                      </span>
                    </div>
                    <VProgressLinear :model-value="(summary.users_with_data / summary.total_users) * 100" color="info"
                      height="6" rounded />
                    <div class="text-caption mt-1 text-disabled">
                      {{ Math.round((summary.users_with_data / summary.total_users) * 100) }}% of team logged hours
                    </div>
                  </div>

                  <!-- Key Metrics -->
                  <VDivider />

                  <div class="d-flex justify-space-around text-center">
                    <div class="metric-item">
                      <VIcon icon="ri-time-line" color="primary" size="small" class="mb-1" />
                      <div class="text-h6 font-weight-bold">{{ summary.total_hours.toFixed(0) }}h</div>
                      <div class="text-caption">Total Hours</div>
                    </div>
                    <div class="metric-item">
                      <VIcon icon="ri-money-dollar-circle-line" color="success" size="small" class="mb-1" />
                      <div class="text-h6 font-weight-bold">{{ summary.total_billable_hours.toFixed(0) }}h</div>
                      <div class="text-caption">Billable</div>
                    </div>
                    <div class="metric-item">
                      <VIcon icon="ri-user-line" color="info" size="small" class="mb-1" />
                      <div class="text-h6 font-weight-bold">{{ (summary.total_billable_hours /
                        summary.users_with_data).toFixed(1) }}</div>
                      <div class="text-caption">Avg/User</div>
                    </div>
                  </div>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Loading State -->
    <div v-if="loading" class="d-flex justify-center align-center py-12">
      <div class="text-center">
        <VProgressCircular indeterminate color="primary" :size="60" :width="6" class="mb-4"
          aria-label="Loading performance data" />
        <h3 class="text-h6 font-weight-regular mb-2">
          {{ clearingCache ? 'Reloading Fresh Data...' : 'Loading Weekly Performance Data' }}
        </h3>
        <p class="text-secondary">
          {{ clearingCache ? 'Clearing cache and fetching fresh data...' : `Analyzing ${selectedYear} Week
          ${selectedWeekNumber} performance metrics...` }}
        </p>
      </div>
    </div>

    <!-- Performance Table -->
    <VCard v-else-if="performanceData.length > 0">
      <VCardText class="pa-0">
        <!-- Table Header with Sort Options -->
        <div class="d-flex flex-wrap align-center justify-space-between px-4 py-3 bg-grey-lighten-4 gap-2">
          <h3 class="text-subtitle-1 font-weight-medium">
            Performance Data ({{ performanceData.length }} users)
          </h3>

          <div class="d-flex align-center gap-2 flex-wrap">
            <span class="text-body-2">Sort by:</span>
            <VBtnToggle v-model="sortBy" mandatory density="compact" variant="outlined" divided>
              <VTooltip v-for="option in sortOptions" :key="option.value"
                :text="`${option.title} (${sortOrder === 'asc' ? 'Ascending' : 'Descending'})`">
                <template #activator="{ props }">
                  <VBtn v-bind="props" :value="option.value" size="small" @click="onSortChange(option.value)"
                    class="px-2"
                    :aria-label="`${option.title} sort ${sortOrder === 'asc' ? 'ascending' : 'descending'}`">
                    <VIcon :icon="option.icon" size="small" class="" />
                    <VIcon v-if="sortBy === option.value"
                      :icon="sortOrder === 'asc' ? 'ri-arrow-up-line' : 'ri-arrow-down-line'" size="x-small"
                      class="" />
                  </VBtn>
                </template>
              </VTooltip>
            </VBtnToggle>
          </div>

        </div>

        <!-- Table -->
        <VTable>
          <thead>
            <tr>
              <th class="text-start">Name</th>
              <th v-if="showDetails" class="text-start">Email</th>
              <th v-if="showDetails" class="text-start">Job Title</th>
              <th class="text-start">Status</th>
              <th class="text-center">Performance</th>
              <th class="text-center">Billable</th>
              <th class="text-center">Non-Billable</th>
              <th class="text-center">Uncategorized</th>
              <th class="text-center">Total</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in performanceData" :key="user.id" :class="{ 'bg-grey-lighten-5': !user.has_data }">
              <td>
                <div class="d-flex align-center gap-2">
                  <VAvatar :color="getPerformanceColor(user.performance_status)" size="32" variant="tonal">
                    <VIcon :icon="getPerformanceIcon(user.performance_status)" size="small" />
                  </VAvatar>
                  <div>
                    <div class="font-weight-medium">{{ user.full_name }}</div>
                    <div class="text-caption text-disabled">
                      {{ user.region || 'No Region' }}
                      <span v-if="user.cohort"> • {{ user.cohort }}</span>
                    </div>
                    <div v-if="user.has_adjusted_date" class="text-caption text-warning">
                      <VIcon icon="ri-information-line" size="x-small" class="mr-1" />
                      Adjusted Start
                    </div>
                  </div>
                </div>
              </td>

              <td v-if="showDetails" class="text-body-2">
                {{ user.email }}
              </td>

              <td v-if="showDetails" class="text-body-2">
                {{ user.job_title || '-' }}
              </td>

              <td>
                <VChip color="default" size="small" variant="tonal">
                  {{ getWorkStatusDescription(user.work_status, workStatusOptions) }}
                </VChip>
              </td>

              <td class="text-center">
                <VChip :color="getPerformanceColor(user.performance_status)" size="small" variant="flat"
                  text-color="white">
                  {{ user.performance_display }}
                </VChip>
              </td>

              <td class="text-center">
                <span :class="{ 'font-weight-medium': user.billable_hours > 0 }">
                  {{ user.billable_hours.toFixed(1) }}h
                </span>
              </td>

              <td class="text-center">
                <span :class="{ 'font-weight-medium': user.non_billable_hours > 0 }">
                  {{ user.non_billable_hours.toFixed(1) }}h
                </span>
              </td>

              <td class="text-center">
                <span :class="{ 'font-weight-medium': user.uncategorized_hours > 0 }">
                  {{ user.uncategorized_hours.toFixed(1) }}h
                </span>
              </td>

              <td class="text-center">
                <span class="font-weight-medium">
                  {{ user.total_hours.toFixed(1) }}h
                </span>
              </td>

              <td class="text-center">
                <VTooltip text="View Weekly Dashboard" location="top">
                  <template #activator="{ props }">
                    <VBtn v-bind="props" icon size="small" variant="text" color="primary"
                      @click="viewUserDashboard(user.id)" aria-label="View user weekly dashboard">
                      <VIcon icon="ri-dashboard-line" />
                    </VBtn>
                  </template>
                </VTooltip>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
    </VCard>

    <!-- No Data State -->
    <VCard v-else>
      <VCardText class="text-center py-8">
        <VIcon size="48" color="secondary" icon="ri-calendar-week-line" class="mb-4" />
        <h3 class="text-h6 font-weight-regular mb-2">No users found</h3>
        <p class="text-secondary mb-4">
          No active users found matching your filters for the selected week.
        </p>
        <VBtn color="primary" @click="goToCurrentWeek" aria-label="Go to current week">
          Go to Current Week
        </VBtn>
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

  :deep(.v-table) {
    font-size: 0.875rem;
  }

  :deep(.v-table th),
  :deep(.v-table td) {
    padding-block: 8px;
    padding-inline: 4px;
  }

  .text-h4 {
    font-size: 1.5rem !important;
  }

  .text-h5 {
    font-size: 1.25rem !important;
  }

  /* Make button group stack on mobile */
  :deep(.v-btn-toggle) {
    flex-wrap: wrap;
  }

  :deep(.v-btn-toggle .v-btn) {
    min-inline-size: auto;
    padding-block: 0;
    padding-inline: 8px;
  }
}

/* Enhanced table styling */
:deep(.v-table) {
  border-radius: 0;
}

:deep(.v-table tbody tr) {
  transition: background-color 0.2s;
}

:deep(.v-table tbody tr:hover) {
  background-color: rgba(var(--v-theme-on-surface), 0.04);
}

/* Performance indicator styling */
.v-avatar {
  font-weight: 600;
}

/* Ensure proper button group styling */
:deep(.v-btn-group) {
  box-shadow: none;
}

:deep(.v-btn-group .v-btn) {
  min-inline-size: 48px;
}

/* Summary card styling */
.text-center {
  padding: 0.5rem;
}

/* Disabled row styling */
.bg-grey-lighten-5 {
  opacity: 0.7;
}

/* Loading state enhancement */
.py-12 {
  padding-block: 3rem;
}

/* Performance chip styling */
:deep(.v-chip) {
  font-size: 0.75rem;
  font-weight: 500;
}

/* Button toggle responsive */
:deep(.v-btn-toggle .v-btn) {
  font-size: 0.75rem;
}

/* Enhanced focus states for accessibility */
:deep(.v-btn:focus-visible) {
  outline: 2px solid var(--v-theme-primary);
  outline-offset: 2px;
}

/* Cache status styling */
.v-chip {
  transition: all 0.3s ease;
}

/* Loading overlay improvements */
.v-progress-circular {
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0% {
    opacity: 1;
  }

  50% {
    opacity: 0.7;
  }

  100% {
    opacity: 1;
  }
}
</style>
