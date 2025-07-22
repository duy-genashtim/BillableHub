<script setup>
import { formatDate } from '@/@core/utils/helpers';
import { getPerformanceColor, getPerformanceIcon } from '@/@core/utils/siteConsts';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();

// Data
const performanceData = ref([]);
const summary = ref({});
const selectedDate = ref('');
const isYesterday = ref(false);
const workStatusOptions = ref([]);
const loading = ref(true);
const searchQuery = ref('');
const selectedWorkStatus = ref('');
const sortBy = ref('total');
const sortOrder = ref('desc');
const showDetails = ref(false);
const isMobile = ref(window.innerWidth < 768);
const regionOptions = ref([]);
const selectedRegion = ref('');

// UI state
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

// Computed properties
const dateLabel = computed(() => {
  if (!selectedDate.value) return '';
  const date = new Date(selectedDate.value);
  const formattedDate = formatDate(date);

  if (isYesterday.value) {
    return `Yesterday (${formattedDate})`;
  }

  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const selectedDateObj = new Date(selectedDate.value);
  selectedDateObj.setHours(0, 0, 0, 0);

  if (selectedDateObj.getTime() === today.getTime()) {
    return `Today (${formattedDate})`;
  }

  return formattedDate;
});

const sortOptions = computed(() => [
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

function getWorkStatusDescription(value, options) {
  if (!value || !Array.isArray(options)) return 'Unknown'

  const match = options.find(option => option.value === value)
  return match ? match.description : 'Unknown'
}

onMounted(() => {
  initializeDate();
  fetchPerformanceData();
  window.addEventListener('resize', handleResize);
});

function handleResize() {
  isMobile.value = window.innerWidth < 768;
}

function initializeDate() {
  // Set to yesterday by default
  const yesterday = new Date();
  yesterday.setDate(yesterday.getDate() - 1);
  selectedDate.value = yesterday.toISOString().split('T')[0];
}

async function fetchPerformanceData() {
  loading.value = true;

  try {
    const params = {
      date: selectedDate.value,
      work_status: selectedWorkStatus.value,
      region: selectedRegion.value,
      search: searchQuery.value,
      sort_by: sortBy.value,
      sort_order: sortOrder.value,
    };

    const response = await axios.get('/api/reports/daily-performance', { params });

    performanceData.value = response.data.performance_data;
    summary.value = response.data.summary;
    isYesterday.value = response.data.is_yesterday;
    workStatusOptions.value = response.data.work_status_options || [];
    regionOptions.value = (response.data.region_options || []).map(region => ({
      title: region,
      value: region
    }));

  } catch (error) {
    console.error('Error fetching performance data:', error);
    showSnackbar('Failed to load performance data', 'error');
  } finally {
    loading.value = false;
  }
}

function onDateChange() {
  fetchPerformanceData();
}

function onSearchChange() {
  // Debounce search
  clearTimeout(window.searchTimeout);
  window.searchTimeout = setTimeout(() => {
    fetchPerformanceData();
  }, 300);
}

function onWorkStatusChange() {
  fetchPerformanceData();
}

function onSortChange(newSortBy) {
  if (sortBy.value === newSortBy) {
    // Toggle sort order
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortBy.value = newSortBy;
    sortOrder.value = 'asc';
  }
  fetchPerformanceData();
}

function viewUserDashboard(userId) {
  const params = {
    start_date: selectedDate.value,
    end_date: selectedDate.value,
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

function goToYesterday() {
  const yesterday = new Date();
  yesterday.setDate(yesterday.getDate() - 1);
  selectedDate.value = yesterday.toISOString().split('T')[0];
  fetchPerformanceData();
}

function goToToday() {
  const today = new Date();
  selectedDate.value = today.toISOString().split('T')[0];
  fetchPerformanceData();
}

function exportData() {
  // Prepare CSV data
  const headers = ['Name', 'Email', 'Job Title', 'Work Status', 'Region', 'Billable Hours', 'Non-Billable Hours', 'Uncategorized Hours', 'Total Hours'];
  const rows = performanceData.value.map(user => [
    user.full_name,
    user.email,
    user.job_title || '',
    getWorkStatusDescription(user.work_status, workStatusOptions.value),
    user.region || '',
    user.billable_hours,
    user.non_billable_hours,
    user.uncategorized_hours,
    user.total_hours
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

  link.setAttribute('href', url);
  link.setAttribute('download', `performance_report_${selectedDate.value}.csv`);
  link.style.visibility = 'hidden';

  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);

  showSnackbar('Report exported successfully', 'success');
}

// Watch for changes
watch(showDetails, (newValue) => {
  if (newValue) {
    localStorage.setItem('dailyPerformanceShowDetails', 'true');
  } else {
    localStorage.removeItem('dailyPerformanceShowDetails');
  }
});

// Load saved preferences
onMounted(() => {
  showDetails.value = localStorage.getItem('dailyPerformanceShowDetails') === 'true';
});
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <VBreadcrumbs :items="[
      { title: 'Home', to: '/' },
      { title: 'Reports', disabled: true },
      { title: 'Daily Performance', disabled: true }
    ]" class="mb-6" aria-label="Breadcrumb navigation" />

    <!-- Header Card -->
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex flex-wrap align-center justify-space-between gap-4">
          <div>
            <h1 class="text-h5 text-md-h4 font-weight-bold mb-2" tabindex="0">
              Daily Time & Performance Tracker
            </h1>
            <p class="text-body-1 mb-0">
              Track daily working hours and performance for all active IVA users
            </p>
          </div>

          <div class="d-flex gap-2">
            <VBtn color="primary" variant="outlined" prepend-icon="ri-download-line" @click="exportData"
              :disabled="loading || performanceData.length === 0" aria-label="Export report">
              Export
            </VBtn>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- Filters Card -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <!-- Date -->
          <VCol cols="12" md="3">
            <VTextField v-model="selectedDate" label="Select Date" type="date" density="comfortable" variant="outlined"
              prepend-inner-icon="ri-calendar-line" @update:model-value="onDateChange" />
            <div class="d-flex gap-2 mt-2">
              <VBtn size="small" variant="tonal" :color="isYesterday ? 'primary' : 'secondary'" @click="goToYesterday">
                Yesterday
              </VBtn>
              <VBtn size="small" variant="tonal" @click="goToToday">
                Today
              </VBtn>
            </div>
          </VCol>

          <!-- Search -->
          <VCol cols="12" md="3">
            <VTextField v-model="searchQuery" label="Search by name or email" density="comfortable" variant="outlined"
              prepend-inner-icon="ri-search-line" clearable @update:model-value="onSearchChange" />
          </VCol>

          <!-- Work Status -->
          <VCol cols="12" md="3">
            <VSelect v-model="selectedWorkStatus" :items="filteredWorkStatusOptions" label="Work Status"
              density="comfortable" variant="outlined" @update:model-value="onWorkStatusChange" />
          </VCol>

          <!-- Region Filter -->
          <VCol cols="12" md="3">
            <VSelect v-model="selectedRegion" :items="[{ title: 'All Regions', value: '' }, ...regionOptions]"
              label="Region" density="comfortable" variant="outlined" @update:model-value="fetchPerformanceData" />
          </VCol>

          <!-- Show/Hide Switch Moved Below -->
          <VCol cols="12">
            <VSwitch v-model="showDetails" label="Show Email & Job Title" color="primary" density="comfortable" />
          </VCol>
        </VRow>

      </VCardText>
    </VCard>

    <!-- Summary Card -->
    <VCard v-if="!loading && summary.total_users > 0" class="mb-6">
      <VCardText>
        <h2 class="text-h6 font-weight-medium mb-4">
          Summary for {{ dateLabel }}
        </h2>

        <VRow>
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
              <div class="text-h4 font-weight-bold text-error">{{ summary.users_without_data }}</div>
              <div class="text-body-2">Without Data</div>
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
              <div class="text-h4 font-weight-bold text-warning">{{ summary.total_non_billable_hours.toFixed(1) }}</div>
              <div class="text-body-2">Non-Billable</div>
            </div>
          </VCol>

          <VCol cols="6" md="2">
            <div class="text-center">
              <div class="text-h4 font-weight-bold">{{ summary.total_hours.toFixed(1) }}</div>
              <div class="text-body-2">Total Hours</div>
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Loading State -->
    <div v-if="loading" class="d-flex justify-center align-center py-8">
      <VProgressCircular indeterminate color="primary" :size="50" :width="5" aria-label="Loading performance data" />
    </div>

    <!-- Performance Table -->
    <VCard v-else-if="performanceData.length > 0">
      <VCardText class="pa-0">
        <!-- Table Header with Sort Options -->
        <div class="d-flex align-center justify-space-between px-4 py-3 bg-grey-lighten-4">
          <h3 class="text-subtitle-1 font-weight-medium">
            Performance Data
          </h3>

          <div class="d-flex align-center gap-2">
            <span class="text-body-2">Sort by:</span>
            <VBtnToggle v-model="sortBy" mandatory density="compact" variant="outlined" divided>
              <VTooltip v-for="option in sortOptions" :key="option.value"
                :text="`${option.title} (${sortOrder === 'asc' ? 'Ascending' : 'Descending'})`">
                <template #activator="{ props }">
                  <VBtn v-bind="props" :value="option.value" size="small" @click="onSortChange(option.value)"
                    class="px-4"
                    :aria-label="`${option.title} sort ${sortOrder === 'asc' ? 'ascending' : 'descending'}`">
                    <VIcon :icon="option.icon" size="small" />
                    <VIcon v-if="sortBy === option.value"
                      :icon="sortOrder === 'asc' ? 'ri-arrow-up-line' : 'ri-arrow-down-line'" size="x-small"
                      class="ml-1" />
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
                  <VAvatar :color="getPerformanceColor(user.total_hours, user.work_status)" size="32" variant="tonal">
                    <VIcon :icon="getPerformanceIcon(user.total_hours, user.work_status)" size="small" />
                  </VAvatar>
                  <div>
                    <div class="font-weight-medium">{{ user.full_name }}</div>
                    <div class="text-caption text-disabled">
                      {{ user.region || 'No Region' }}
                      <span v-if="user.cohort"> • {{ user.cohort }}</span>
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
                <VChip :color="getPerformanceColor(user.total_hours, user.work_status)" size="small" variant="flat"
                  text-color="white">
                  {{ user.total_hours.toFixed(1) }}h
                </VChip>
              </td>

              <td class="text-center">
                <VTooltip text="View Details" location="top">
                  <template #activator="{ props }">
                    <VBtn v-bind="props" icon size="small" variant="text" color="primary"
                      @click="viewUserDashboard(user.id)" aria-label="View user dashboard">
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
        <VIcon size="48" color="secondary" icon="ri-user-line" class="mb-4" />
        <h3 class="text-h6 font-weight-regular mb-2">No users found</h3>
        <p class="text-secondary mb-0">
          No active users found matching your filters.
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

  :deep(.v-table) {
    font-size: 0.875rem;
  }

  :deep(.v-table th),
  :deep(.v-table td) {
    padding: 8px;
  }

  .text-h4 {
    font-size: 1.5rem !important;
  }

  .text-h5 {
    font-size: 1.25rem !important;
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
</style>
