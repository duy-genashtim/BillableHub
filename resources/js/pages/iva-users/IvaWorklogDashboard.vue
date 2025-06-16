<script setup>
import { WORKLOG_CONFIG } from '@/@core/utils/worklogConfig';
import { formatDate, formatDateTime, formatHours, getWeekRangeForYear } from '@/@core/utils/worklogHelpers';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const userId = route.params.id;

// Data
const user = ref(null);
const dashboardData = ref(null);
const availableWeeks = ref([]);
const availableMonths = ref([]);
const loading = ref(true);
const loadingDashboard = ref(false);
const isMobile = ref(window.innerWidth < 768);

// Date selection
const dateMode = ref('weeks');
const selectedYear = ref(Math.max(WORKLOG_CONFIG.START_YEAR, new Date().getFullYear()));
const selectedWeekNumber = ref(getCurrentWeekNumber());
const selectedWeekCount = ref(1);
const selectedMonth = ref(new Date().getMonth() + 1);
const bimonthlyPart = ref('first'); // 'first' or 'second'
const bimonthlyDate = ref(15);
const customDateFrom = ref('');
const customDateTo = ref('');

// UI state
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');
const activeTab = ref('overview');
const expandedCategories = ref({});
const expandedTasks = ref({});

const tabs = computed(() => {
  const baseTabs = [
    { key: 'overview', title: 'Overview', icon: 'ri-dashboard-line' },
    { key: 'daily', title: 'Daily Breakdown', icon: 'ri-calendar-line' },
    { key: 'categories', title: 'Categories', icon: 'ri-folder-line' }
  ];

  return baseTabs;
});

// Chart colors
const chartColors = {
  primary: '#6366f1',
  success: '#10b981',
  warning: '#f59e0b',
  error: '#ef4444',
  info: '#06b6d4',
  secondary: '#6b7280'
};

// Computed properties
const dateRangeText = computed(() => {
  if (!dashboardData.value) return '';

  if (dateMode.value === 'bimonthly' && dashboardData.value.bimonthly_data) {
    const currentData = bimonthlyPart.value === 'first'
      ? dashboardData.value.bimonthly_data.first_half
      : dashboardData.value.bimonthly_data.second_half;

    if (currentData && currentData.date_range) {
      const start = new Date(currentData.date_range.start);
      const end = new Date(currentData.date_range.end);
      return `${formatDate(start)} - ${formatDate(end)}`;
    }
  }

  const start = new Date(dashboardData.value.date_range.start);
  const end = new Date(dashboardData.value.date_range.end);

  return `${formatDate(start)} - ${formatDate(end)}`;
});

const currentDashboardData = computed(() => {
  if (!dashboardData.value) return null;

  if (dateMode.value === 'bimonthly' && dashboardData.value.bimonthly_data) {
    return bimonthlyPart.value === 'first'
      ? dashboardData.value.bimonthly_data.first_half
      : dashboardData.value.bimonthly_data.second_half;
  }

  return dashboardData.value;
});

const chartData = computed(() => {
  const data = currentDashboardData.value;
  if (!data?.daily_breakdown) return [];

  return data.daily_breakdown.map(day => ({
    date: day.date,
    day_name: day.day_name,
    day_short: day.day_name.substring(0, 3),
    day_number: new Date(day.date).getDate(),
    billable: day.billable_hours,
    nonBillable: day.non_billable_hours,
    total: day.total_hours,
    entries_count: day.entries_count,
  }));
});

const maxDailyHours = computed(() => {
  if (!chartData.value.length) return 8;
  return Math.max(8, Math.ceil(Math.max(...chartData.value.map(d => d.total))));
});

const weekOptions = computed(() => {
  return availableWeeks.value.map(week => ({
    title: `Week ${week.week_number} (${week.start_date} - ${week.end_date})`,
    value: week.week_number,
    subtitle: week.is_current ? 'Current Week' : '',
  }));
});

const weekCountOptions = computed(() => {
  const options = [];
  for (let i = 1; i <= 12; i++) {
    options.push({
      title: `${i} Week${i > 1 ? 's' : ''}`,
      value: i
    });
  }
  return options;
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

const monthOptions = computed(() => {
  const months = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
  ];

  return months.map((month, index) => ({
    title: month,
    value: index + 1
  }));
});

const bimonthlyDateOptions = computed(() => {
  const options = [];
  for (let i = 1; i <= 28; i++) {
    options.push({
      title: `${i}${getOrdinalSuffix(i)}`,
      value: i
    });
  }
  return options;
});

const bimonthlyPartOptions = computed(() => [
  { title: 'First Half', value: 'first' },
  { title: 'Second Half', value: 'second' }
]);

const showPerformance = computed(() => {
  return dateMode.value === 'weeks';
});

function getCurrentWeekNumber() {
  const now = new Date();
  const year = now.getFullYear();
  const weekRanges = getWeekRangeForYear(year);
  console.log('Available week ranges:', weekRanges);

  for (let i = 0; i < weekRanges.length; i++) {
    const weekRange = weekRanges[i];
    const start = new Date(weekRange.start_date);
    const end = new Date(weekRange.end_date);

    if (now >= start && now <= end) {
      return weekRange.week_number;
    }
  }

  return 1; // Default to week 1 if not found
}

function getOrdinalSuffix(day) {
  if (day > 3 && day < 21) return 'th';
  switch (day % 10) {
    case 1: return 'st';
    case 2: return 'nd';
    case 3: return 'rd';
    default: return 'th';
  }
}
let isInitialized = false;
onMounted(() => {
  fetchUserDetails();
  fetchAvailableWeeks();
  fetchDashboardData();
  isInitialized = true;
  window.addEventListener('resize', handleResize);
});

function handleResize() {
  isMobile.value = window.innerWidth < 768;
}

async function fetchUserDetails() {
  try {
    const response = await axios.get(`/api/admin/iva-users/${userId}`);
    user.value = response.data.user;
  } catch (error) {
    console.error('Error fetching user details:', error);
    snackbarText.value = 'Failed to load user details';
    snackbarColor.value = 'error';
    snackbar.value = true;
    router.push({ name: 'iva-users-list' });
  }
}

async function fetchAvailableWeeks() {
  try {
    const weekRanges = getWeekRangeForYear(selectedYear.value);
    availableWeeks.value = weekRanges.map(week => ({
      ...week,
      is_current: false // We'll mark current week separately if needed
    }));
  } catch (error) {
    console.error('Error generating available weeks:', error);
  }
}

async function fetchDashboardData() {
  loadingDashboard.value = true;

  try {
    const params = getDateParams();
    const response = await axios.get(`/api/admin/iva-users/${userId}/worklog-dashboard`, { params });
    dashboardData.value = response.data.dashboard;
  } catch (error) {
    console.error('Error fetching dashboard data:', error);
    snackbarText.value = 'Failed to load dashboard data';
    snackbarColor.value = 'error';
    snackbar.value = true;
  } finally {
    loadingDashboard.value = false;
    loading.value = false;
  }
}

function getDateParams() {
  const params = {};

  switch (dateMode.value) {
    case 'weeks': {
      const weeks = getWeekRangeForYear(selectedYear.value);
      const startWeek = selectedWeekNumber.value;
      const endWeek = selectedWeekNumber.value + selectedWeekCount.value - 1;

      const startWeekObj = weeks.find(w => w.week_number === startWeek);
      const endWeekObj = weeks.find(w => w.week_number === endWeek);

      if (startWeekObj && endWeekObj) {
        params.start_date = startWeekObj.start_date;
        params.end_date = endWeekObj.end_date;
      }

      params.year = selectedYear.value;
      params.week_number = selectedWeekNumber.value;
      params.week_count = selectedWeekCount.value;
      break;
    }

    case 'monthly': {
      const year = selectedYear.value;
      const month = selectedMonth.value;

      // Use UTC dates to avoid timezone issues
      const firstDay = new Date(Date.UTC(year, month - 1, 1));
      const lastDay = new Date(Date.UTC(year, month, 0)); // Last day of the month

      params.year = year;
      params.month = month;
      params.start_date = firstDay.toISOString().split('T')[0];
      params.end_date = lastDay.toISOString().split('T')[0];
      break;
    }

    case 'bimonthly': {
      const year = selectedYear.value;
      const month = selectedMonth.value;
      const split = bimonthlyDate.value;

      params.year = year;
      params.month = month;
      params.bimonthly_date = split;
      params.bimonthly_part = bimonthlyPart.value;

      // Use UTC dates to avoid timezone issues
      if (bimonthlyPart.value === 'first') {
        params.start_date = new Date(Date.UTC(year, month - 1, 1)).toISOString().split('T')[0];
        params.end_date = new Date(Date.UTC(year, month - 1, split)).toISOString().split('T')[0];
      } else {
        params.start_date = new Date(Date.UTC(year, month - 1, split + 1)).toISOString().split('T')[0];
        params.end_date = new Date(Date.UTC(year, month, 0)).toISOString().split('T')[0];
      }
      break;
    }

    case 'custom': {
      params.start_date = customDateFrom.value;
      params.end_date = customDateTo.value;
      break;
    }
  }

  return params;
}

function onDateModeChange() {
  if (dateMode.value === 'custom') {
    const today = new Date();
    const monday = new Date(today);
    monday.setDate(today.getDate() - today.getDay() + 1);
    const sunday = new Date(monday);
    sunday.setDate(monday.getDate() + 6);

    customDateFrom.value = monday.toISOString().split('T')[0];
    customDateTo.value = sunday.toISOString().split('T')[0];
  }

  if (dateMode.value === 'bimonthly') {
    bimonthlyPart.value = 'first';
  }

  //fetchDashboardData();
}

function onWeekSelectionChange() {
  if (dateMode.value === 'weeks') {
    fetchDashboardData();
  }
}

function onMonthlySelectionChange() {
  if (dateMode.value === 'monthly') {
    fetchDashboardData();
  }
}

function onBimonthlySelectionChange() {
  if (dateMode.value === 'bimonthly') {
    fetchDashboardData();
  }
}

function onBimonthlyPartChange() {
  // No need to refetch data, just switch between existing data
  // The computed properties will handle the data switching
}

function onCustomDateChange() {
  if (dateMode.value === 'custom' && customDateFrom.value && customDateTo.value) {
    fetchDashboardData();
  }
}

function selectWeek(week) {
  if (week) {
    selectedYear.value = week.year;
    selectedWeekNumber.value = week.week_number;
    selectedWeekCount.value = 1;
    fetchDashboardData();
  }
}

function goToToday() {
  const today = new Date();
  customDateFrom.value = today.toISOString().split('T')[0];
  customDateTo.value = today.toISOString().split('T')[0];
  dateMode.value = 'custom';
  fetchDashboardData();
}

function goToThisWeek() {
  const today = new Date();
  selectedYear.value = today.getFullYear();
  selectedWeekNumber.value = getCurrentWeekNumber();
  selectedWeekCount.value = 1;
  dateMode.value = 'weeks';
  fetchDashboardData();
}

function goToThisMonth() {
  const today = new Date();
  selectedYear.value = today.getFullYear();
  selectedMonth.value = today.getMonth() + 1;
  dateMode.value = 'monthly';
  fetchDashboardData();
}

function viewTimeDoctorRecords() {
  const params = {};
  if (dateMode.value === 'custom') {
    params.start_date = customDateFrom.value;
    params.end_date = customDateTo.value;
  } else if (currentDashboardData.value && currentDashboardData.value.date_range) {
    params.start_date = currentDashboardData.value.date_range.start;
    params.end_date = currentDashboardData.value.date_range.end;
  } else if (dashboardData.value) {
    params.start_date = dashboardData.value.date_range.start;
    params.end_date = dashboardData.value.date_range.end;
  }

  router.push({
    name: 'iva-user-timedoctor-records',
    params: { id: userId },
    query: params
  });
}

function goBack() {
  router.push({ name: 'iva-user-detail', params: { id: userId } });
}

function getProgressColor(percentage) {
  if (percentage >= 100) return 'success';
  if (percentage >= 90) return 'warning';
  return 'error';
}

function getPerformanceStatus(percentage) {
  if (percentage >= 100) return 'EXCELLENT';
  if (percentage >= 90) return 'WARNING';
  return 'POOR';
}

function getPerformanceColor(status) {
  switch (status) {
    case 'EXCELLENT': return 'success';
    case 'WARNING': return 'warning';
    case 'POOR': return 'error';
    default: return 'grey';
  }
}

function getPerformanceIcon(status) {
  switch (status) {
    case 'EXCELLENT': return 'ri-checkbox-circle-line';
    case 'WARNING': return 'ri-error-warning-line';
    case 'POOR': return 'ri-close-circle-line';
    default: return 'ri-time-line';
  }
}

function toggleCategory(categoryName) {
  expandedCategories.value[categoryName] = !expandedCategories.value[categoryName];
}

function toggleTask(categoryName, taskKey) {
  const key = `${categoryName}-${taskKey}`;
  expandedTasks.value[key] = !expandedTasks.value[key];
}

// Watch for year changes
watch(selectedYear, () => {
  if (dateMode.value === 'weeks') {
    fetchAvailableWeeks();
  }
});

watchEffect(() => {
  if (!isInitialized || loading.value) return;

  if (dateMode.value === 'weeks' && selectedWeekNumber.value && selectedWeekCount.value) {
    fetchDashboardData();
  } else if (dateMode.value === 'monthly' && selectedMonth.value) {
    fetchDashboardData();
  } else if (dateMode.value === 'bimonthly' && selectedMonth.value && bimonthlyDate.value) {
    fetchDashboardData();
  } else if (dateMode.value === 'custom' && customDateFrom.value && customDateTo.value) {
    fetchDashboardData();
  }
});

// Watch for date range changes
// watch(() => [selectedYear.value, selectedWeekNumber.value, selectedWeekCount.value], () => {
//   if (dateMode.value === 'weeks') {
//     fetchDashboardData();
//   }
// }, { deep: true });

// watch(() => [selectedYear.value, selectedMonth.value], () => {
//   if (dateMode.value === 'monthly') {
//     fetchDashboardData();
//   }
// }, { deep: true });

// watch(() => [selectedYear.value, selectedMonth.value, bimonthlyDate.value], () => {
//   if (dateMode.value === 'bimonthly') {
//     fetchDashboardData();
//   }
// }, { deep: true });

// watch(() => [customDateFrom.value, customDateTo.value], () => {
//   if (dateMode.value === 'custom' && customDateFrom.value && customDateTo.value) {
//     fetchDashboardData();
//   }
// }, { deep: true });

// watch(bimonthlyPart, () => {
//   // No need to refetch data, just switch between existing data
// });
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <VBreadcrumbs :items="[
      { title: 'Home', to: '/' },
      { title: 'IVA Users', to: { name: 'iva-users-list' } },
      { title: user ? user.full_name : 'User', to: user ? { name: 'iva-user-detail', params: { id: userId } } : {} },
      { title: 'Working Hours Dashboard', disabled: true }
    ]" class="mb-6" aria-label="Breadcrumb navigation" />

    <div v-if="loading" class="d-flex justify-center align-center py-8">
      <VProgressCircular indeterminate color="primary" :size="50" :width="5" aria-label="Loading dashboard" />
    </div>

    <div v-else>
      <!-- Header Card -->
      <VCard class="mb-6">
        <VCardText>
          <div class="d-flex flex-wrap align-center mb-4">
            <div class="mr-auto mb-2 mb-md-0">
              <h1 class="text-h5 text-md-h4" tabindex="0">
                Working Hours Dashboard: {{ user?.full_name }}
              </h1>
              <div class="d-flex flex-column flex-md-row align-md-center gap-2 mt-2">
                <VChip v-if="user?.work_status" color="secondary" size="small" class="mr-2">
                  {{ user.work_status }}
                </VChip>
                <VChip v-if="user?.region?.name" color="info" size="small">
                  {{ user.region.name }}
                </VChip>
                <VChip color="primary" size="small" prepend-icon="ri-calendar-line">
                  {{ dateRangeText }}
                </VChip>
              </div>
            </div>

            <div class="d-flex gap-2 flex-wrap">
              <VBtn color="info" variant="outlined" prepend-icon="ri-time-line" :size="isMobile ? 'small' : 'default'"
                @click="viewTimeDoctorRecords" aria-label="View detailed records">
                View Records
              </VBtn>

              <VBtn color="secondary" variant="outlined" prepend-icon="ri-arrow-left-line"
                :size="isMobile ? 'small' : 'default'" @click="goBack" aria-label="Back to user details">
                Back
              </VBtn>
            </div>
          </div>
        </VCardText>
      </VCard>

      <!-- Date Selection Card -->
      <VCard class="mb-6">
        <VCardText>
          <h2 class="text-h6 font-weight-medium mb-4">Date Range Selection</h2>

          <!-- Quick Actions -->
          <div class="d-flex gap-2 mb-4 flex-wrap">
            <VBtn size="small" variant="outlined" @click="goToToday" aria-label="View today">
              Today
            </VBtn>
            <VBtn size="small" variant="outlined" @click="goToThisWeek" aria-label="View this week">
              This Week
            </VBtn>
            <VBtn size="small" variant="outlined" @click="goToThisMonth" aria-label="View this month">
              This Month
            </VBtn>
          </div>

          <VRow>
            <VCol cols="12" md="3">
              <VSelect v-model="dateMode" :items="[
                { title: 'Select by Weeks', value: 'weeks' },
                { title: 'Select by Month', value: 'monthly' },
                { title: 'Select by Bimonthly', value: 'bimonthly' },
                { title: 'Custom Date Range', value: 'custom' }
              ]" label="Date Selection Mode" density="comfortable" variant="outlined"
                @update:model-value="onDateModeChange" aria-label="Date selection mode" />
            </VCol>

            <!-- Week Selection -->
            <template v-if="dateMode === 'weeks'">
              <VCol cols="12" md="2">
                <VSelect v-model="selectedYear" :items="yearOptions" label="Year" density="comfortable"
                  variant="outlined" aria-label="Select year" />
              </VCol>

              <VCol cols="12" md="4">
                <VSelect v-model="selectedWeekNumber" :items="weekOptions" label="Week" density="comfortable"
                  variant="outlined" @update:model-value="onWeekSelectionChange" aria-label="Select week">
                  <template v-slot:item="{ item, props }">
                    <VListItem v-bind="props" :title="item.raw.title" :subtitle="item.raw.subtitle">
                      <template v-slot:prepend>
                        <VIcon v-if="item.raw.subtitle" color="primary" size="small">ri-calendar-check-line</VIcon>
                      </template>
                    </VListItem>
                  </template>
                </VSelect>
              </VCol>

              <VCol cols="12" md="3">
                <VSelect v-model="selectedWeekCount" :items="weekCountOptions" label="Number of Weeks"
                  density="comfortable" variant="outlined" @update:model-value="onWeekSelectionChange"
                  aria-label="Number of weeks" />
              </VCol>
            </template>

            <!-- Monthly Selection -->
            <template v-else-if="dateMode === 'monthly'">
              <VCol cols="12" md="3">
                <VSelect v-model="selectedYear" :items="yearOptions" label="Year" density="comfortable"
                  variant="outlined" @update:model-value="onMonthlySelectionChange" aria-label="Select year" />
              </VCol>

              <VCol cols="12" md="6">
                <VSelect v-model="selectedMonth" :items="monthOptions" label="Month" density="comfortable"
                  variant="outlined" @update:model-value="onMonthlySelectionChange" aria-label="Select month" />
              </VCol>
            </template>

            <!-- Bimonthly Selection -->
            <template v-else-if="dateMode === 'bimonthly'">
              <VCol cols="12" md="2">
                <VSelect v-model="selectedYear" :items="yearOptions" label="Year" density="comfortable"
                  variant="outlined" @update:model-value="onBimonthlySelectionChange" aria-label="Select year" />
              </VCol>

              <VCol cols="12" md="3">
                <VSelect v-model="selectedMonth" :items="monthOptions" label="Month" density="comfortable"
                  variant="outlined" @update:model-value="onBimonthlySelectionChange" aria-label="Select month" />
              </VCol>

              <VCol cols="12" md="2">
                <VSelect v-model="bimonthlyDate" :items="bimonthlyDateOptions" label="Split Date" density="comfortable"
                  variant="outlined" @update:model-value="onBimonthlySelectionChange" aria-label="Select split date"
                  hint="Date that separates first and second half of the month" persistent-hint />
              </VCol>

              <VCol cols="12" md="2">
                <VSelect v-model="bimonthlyPart" :items="bimonthlyPartOptions" label="Part" density="comfortable"
                  variant="outlined" @update:model-value="onBimonthlyPartChange" aria-label="Select part of month" />
              </VCol>
            </template>

            <!-- Custom Date Selection -->
            <template v-else-if="dateMode === 'custom'">
              <VCol cols="12" md="4">
                <VTextField v-model="customDateFrom" label="From Date" type="date" density="comfortable"
                  variant="outlined" @change="onCustomDateChange" aria-label="Start date" />
              </VCol>

              <VCol cols="12" md="4">
                <VTextField v-model="customDateTo" label="To Date" type="date" density="comfortable" variant="outlined"
                  @change="onCustomDateChange" aria-label="End date" />
              </VCol>
            </template>
          </VRow>
        </VCardText>
      </VCard>

      <!-- Loading State -->
      <div v-if="loadingDashboard" class="d-flex justify-center align-center py-8">
        <VProgressCircular indeterminate color="primary" :size="40" :width="4" aria-label="Loading dashboard data" />
      </div>

      <!-- Dashboard Content -->
      <div v-else-if="dashboardData">
        <!-- Basic Metrics Summary -->
        <VRow class="mb-6">
          <VCol cols="12" md="4">
            <VCard color="success" variant="tonal" class="h-100">
              <VCardText class="d-flex align-center">
                <VAvatar color="success" variant="flat" class="mr-4">
                  <VIcon icon="ri-money-dollar-circle-line" size="24" />
                </VAvatar>

                <div class="flex-grow-1">
                  <div class="text-h4 font-weight-bold mb-1">
                    {{ formatHours(currentDashboardData?.basic_metrics?.billable_hours || 0) }}
                  </div>
                  <div class="text-body-2 font-weight-medium">
                    Billable Hours
                  </div>
                </div>
              </VCardText>
            </VCard>
          </VCol>

          <VCol cols="12" md="4">
            <VCard color="info" variant="tonal" class="h-100">
              <VCardText class="d-flex align-center">
                <VAvatar color="info" variant="flat" class="mr-4">
                  <VIcon icon="ri-time-line" size="24" />
                </VAvatar>

                <div class="flex-grow-1">
                  <div class="text-h4 font-weight-bold mb-1">
                    {{ formatHours(currentDashboardData?.basic_metrics?.non_billable_hours || 0) }}
                  </div>
                  <div class="text-body-2 font-weight-medium">
                    Non-Billable Hours
                  </div>
                </div>
              </VCardText>
            </VCard>
          </VCol>

          <VCol cols="12" md="4">
            <VCard color="secondary" variant="tonal" class="h-100">
              <VCardText class="d-flex align-center">
                <VAvatar color="secondary" variant="flat" class="mr-4">
                  <VIcon icon="ri-calculator-line" size="24" />
                </VAvatar>

                <div class="flex-grow-1">
                  <div class="text-h4 font-weight-bold mb-1">
                    {{ formatHours((currentDashboardData?.basic_metrics?.billable_hours || 0) +
                      (currentDashboardData?.basic_metrics?.non_billable_hours || 0)) }}
                  </div>
                  <div class="text-body-2 font-weight-medium">
                    Total Hours
                  </div>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>

        <!-- Performance Overview for Weeks Only -->
        <VCard v-if="showPerformance && dashboardData.target_performances?.length" class="mb-6">
          <VCardText>
            <h2 class="text-h6 font-weight-medium mb-4">Performance Overview</h2>

            <VRow>
              <VCol v-for="target in dashboardData.target_performances" :key="target.target_id"
                :cols="dashboardData.target_performances.length === 1 ? 12 : 6">
                <VCard variant="outlined" class="h-100">
                  <VCardText>
                    <div class="d-flex align-center justify-space-between mb-3">
                      <h3 class="text-subtitle-1 font-weight-medium">
                        Target Workweek Hours {{ target.target_hours_per_week }}
                        <VChip size="small" color="primary" variant="tonal" class="ml-2">
                          {{ target.work_status }}
                        </VChip>
                      </h3>
                      <VChip :color="getPerformanceColor(target.status)"
                        :prepend-icon="getPerformanceIcon(target.status)" size="small">
                        {{ target.status }}
                      </VChip>
                    </div>

                    <VProgressLinear :model-value="target.percentage" :color="getProgressColor(target.percentage)"
                      height="20" rounded class="mb-2">
                      <template v-slot:default="{ value }">
                        <div class="text-center text-white font-weight-medium">
                          {{ Math.ceil(value) }}%
                        </div>
                      </template>
                    </VProgressLinear>

                    <div class="d-flex justify-space-between text-body-2 mb-3">
                      <span>0h</span>
                      <span class="font-weight-medium">
                        {{ formatHours(dashboardData.basic_metrics.billable_hours) }} / {{
                          formatHours(target.target_total_hours) }}
                      </span>
                      <span>{{ formatHours(target.target_total_hours) }}</span>
                    </div>

                    <VAlert :type="target.actual_vs_target >= 0 ? 'success' : 'warning'" variant="tonal"
                      class="text-center" density="compact">
                      <template v-if="target.actual_vs_target >= 0">
                        <strong>{{ formatHours(target.actual_vs_target) }} ahead of target!</strong>
                      </template>
                      <template v-else>
                        <strong>{{ formatHours(Math.abs(target.actual_vs_target)) }} behind target</strong>
                      </template>
                    </VAlert>
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <!-- Main Content Tabs -->
        <VTabs v-model="activeTab" class="mb-6">
          <VTab v-for="tab in tabs" :key="tab.key" :value="tab.key" class="text-none">
            <VIcon :icon="tab.icon" class="mr-2" />
            {{ tab.title }}
          </VTab>
        </VTabs>

        <!-- Tab Content -->
        <VWindow v-model="activeTab">
          <!-- Overview Tab -->
          <VWindowItem value="overview">
            <VCard>
              <VCardText>
                <h3 class="text-h6 font-weight-medium mb-4">Daily Hours Chart</h3>

                <div v-if="chartData.length > 0" class="enhanced-chart-container">
                  <div class="chart-grid" :style="{ height: '320px', position: 'relative' }">
                    <!-- Y-axis hour lines -->
                    <div v-for="hour in maxDailyHours" :key="hour" class="hour-line" :style="{
                      position: 'absolute',
                      top: `${((maxDailyHours - hour) / maxDailyHours) * 260 + 30}px`,
                      left: '50px',
                      right: '20px',
                      height: '1px',
                      backgroundColor: hour % 2 === 0 ? '#e0e0e0' : '#f5f5f5',
                      zIndex: 1
                    }">
                      <span class="hour-label" :style="{
                        position: 'absolute',
                        left: '-45px',
                        top: '-8px',
                        fontSize: '11px',
                        color: '#666',
                        fontWeight: hour % 4 === 0 ? '600' : '400'
                      }">
                        {{ hour }}h
                      </span>
                    </div>

                    <!-- Chart bars -->
                    <div class="d-flex justify-space-between align-end chart-bars" :style="{
                      height: '260px',
                      marginTop: '30px',
                      marginLeft: '50px',
                      marginRight: '20px',
                      position: 'relative',
                      zIndex: 2
                    }">
                      <div v-for="day in chartData" :key="day.date" class="chart-bar-container" :style="{
                        flex: 1,
                        margin: '0 3px',
                        position: 'relative',
                        minWidth: '30px'
                      }">
                        <!-- Bar Stack -->
                        <div class="bar-stack" :style="{
                          height: '260px',
                          display: 'flex',
                          flexDirection: 'column-reverse',
                          cursor: 'pointer'
                        }"
                          :title="`${day.day_name} ${day.day_number}: ${formatHours(day.total)} total (${formatHours(day.billable)} billable, ${formatHours(day.nonBillable)} non-billable)`">
                          <!-- Billable Hours Bar -->
                          <div v-if="day.billable > 0" class="bar-segment billable-bar" :style="{
                            height: `${(day.billable / maxDailyHours) * 260}px`,
                            backgroundColor: '#4CAF50',
                            borderRadius: '0 0 6px 6px',
                            marginBottom: '1px',
                            transition: 'all 0.3s ease',
                            boxShadow: '0 2px 4px rgba(76, 175, 80, 0.3)'
                          }" />

                          <!-- Non-Billable Hours Bar -->
                          <div v-if="day.nonBillable > 0" class="bar-segment non-billable-bar" :style="{
                            height: `${(day.nonBillable / maxDailyHours) * 260}px`,
                            backgroundColor: '#2196F3',
                            borderRadius: '6px 6px 0 0',
                            transition: 'all 0.3s ease',
                            boxShadow: '0 2px 4px rgba(33, 150, 243, 0.3)'
                          }" />
                        </div>

                        <!-- Day Label -->
                        <div class="day-info text-center mt-3">
                          <div class="text-subtitle-2 font-weight-bold text-primary">
                            {{ day.day_short }}
                          </div>
                          <div class="text-caption text-medium-emphasis">
                            {{ day.day_number }}
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Legend -->
                  <div class="d-flex justify-center gap-6 mt-6 pt-4 border-t">
                    <div class="d-flex align-center">
                      <div class="legend-color mr-2" style="
                          border-radius: 4px;
                          background-color: #4caf50;
                          block-size: 16px;
                          box-shadow: 0 2px 4px rgba(76, 175, 80, 30%);
                          inline-size: 16px;
"></div>
                      <span class="text-body-2 font-weight-medium">Billable Hours</span>
                    </div>
                    <div class="d-flex align-center">
                      <div class="legend-color mr-2" style="
                          border-radius: 4px;
                          background-color: #2196f3;
                          block-size: 16px;
                          box-shadow: 0 2px 4px rgba(33, 150, 243, 30%);
                          inline-size: 16px;
"></div>
                      <span class="text-body-2 font-weight-medium">Non-Billable Hours</span>
                    </div>
                  </div>
                </div>
                <div v-else class="text-center py-8">
                  <VIcon size="48" color="secondary" icon="ri-line-chart-line" class="mb-4" />
                  <p class="text-secondary">No data available for the selected period</p>
                </div>
              </VCardText>
            </VCard>
          </VWindowItem>

          <!-- Daily Breakdown Tab -->
          <VWindowItem value="daily">
            <VCard>
              <VCardText>
                <h3 class="text-h6 font-weight-medium mb-4">Daily Hours Breakdown</h3>

                <VDataTable :headers="[
                  { title: 'Date', key: 'date' },
                  { title: 'Day', key: 'day_name' },
                  { title: 'Billable Hours', key: 'billable_hours' },
                  { title: 'Non-Billable Hours', key: 'non_billable_hours' },
                  { title: 'Entries', key: 'entries_count' }
                ]" :items="currentDashboardData?.daily_breakdown || []" density="comfortable" class="elevation-1">
                  <template #[`item.date`]="{ item }">
                    <span>{{ formatDate(new Date(item.date)) }}</span>
                  </template>

                  <template #[`item.billable_hours`]="{ item }">
                    <VChip size="small" color="success" variant="outlined">
                      {{ formatHours(item.billable_hours) }}
                    </VChip>
                  </template>

                  <template #[`item.non_billable_hours`]="{ item }">
                    <VChip size="small" color="info" variant="outlined">
                      {{ formatHours(item.non_billable_hours) }}
                    </VChip>
                  </template>
                </VDataTable>
              </VCardText>
            </VCard>
          </VWindowItem>

          <!-- Categories Tab -->
          <VWindowItem value="categories">
            <VCard>
              <VCardText>
                <h2 class="text-h6 font-weight-medium mb-4">Work Category Breakdown</h2>

                <div v-if="!currentDashboardData?.category_breakdown?.length" class="text-center py-8">
                  <VIcon size="48" icon="ri-folder-open-line" color="grey-lighten-1" class="mb-2" />
                  <p class="text-body-2">No work entries found for the selected period</p>
                </div>

                <div v-else class="category-breakdown">
                  <!-- Main Category Level (Billable/Non-Billable) -->
                  <div v-for="mainCategory in currentDashboardData.category_breakdown" :key="mainCategory.type"
                    class="main-category-section mb-6">
                    <!-- Main Category Header -->
                    <VCard variant="elevated" class="mb-3"
                      :color="mainCategory.type.includes('Billable') ? 'success' : 'info'">
                      <VCardItem class="cursor-pointer text-white" @click="toggleCategory(mainCategory.type)">
                        <template v-slot:prepend>
                          <VAvatar :color="mainCategory.type.includes('Billable') ? 'success' : 'info'" variant="flat"
                            size="32">
                            <VIcon
                              :icon="mainCategory.type.includes('Billable') ? 'ri-money-dollar-circle-line' : 'ri-time-line'"
                              size="18" />
                          </VAvatar>
                        </template>

                        <VCardTitle class="d-flex align-center">
                          <span class="mr-3">{{ mainCategory.type }}</span>
                          <VChip color="white" size="small"
                            :text-color="mainCategory.type.includes('Billable') ? 'success' : 'info'">
                            {{ formatHours(mainCategory.total_hours) }}
                          </VChip>
                          <VChip color="white" size="small" variant="outlined" class="ml-2"
                            :text-color="mainCategory.type.includes('Billable') ? 'success' : 'info'">
                            {{ mainCategory.categories.length }} {{ mainCategory.categories.length === 1 ? 'category' :
                              'categories' }}
                          </VChip>
                        </VCardTitle>

                        <template v-slot:append>
                          <VIcon
                            :icon="expandedCategories[mainCategory.type] ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'"
                            color="white" />
                        </template>
                      </VCardItem>
                    </VCard>

                    <!-- Categories within Main Category -->
                    <VExpandTransition>
                      <div v-show="expandedCategories[mainCategory.type]" class="ml-4">
                        <div v-for="category in mainCategory.categories" :key="category.category_name"
                          class="category-section mb-4">
                          <!-- Category Header -->
                          <VCard variant="outlined" class="mb-2">
                            <VCardItem class="cursor-pointer"
                              @click="toggleCategory(mainCategory.type + '-' + category.category_name)">
                              <template v-slot:prepend>
                                <VAvatar color="primary" variant="tonal" size="24">
                                  <VIcon icon="ri-folder-line" size="12" />
                                </VAvatar>
                              </template>

                              <VCardTitle class="d-flex align-center">
                                <span class="mr-3">{{ category.category_name }}</span>
                                <VChip color="primary" size="small">
                                  {{ formatHours(category.total_hours) }}
                                </VChip>
                                <VChip color="info" size="small" variant="outlined" class="ml-2">
                                  {{ category.tasks.length }} {{ category.tasks.length === 1 ? 'task' : 'tasks' }}
                                </VChip>
                              </VCardTitle>

                              <template v-slot:append>
                                <VIcon
                                  :icon="expandedCategories[mainCategory.type + '-' + category.category_name] ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'" />
                              </template>
                            </VCardItem>
                          </VCard>

                          <!-- Tasks in Category -->
                          <VExpandTransition>
                            <div v-show="expandedCategories[mainCategory.type + '-' + category.category_name]"
                              class="ml-4">
                              <div v-for="task in category.tasks" :key="task.task_name" class="task-section mb-3">
                                <!-- Task Header -->
                                <VCard variant="tonal" color="grey-lighten-4" class="mb-2">
                                  <VCardItem class="cursor-pointer"
                                    @click="toggleTask(mainCategory.type + '-' + category.category_name, task.task_name)">
                                    <template v-slot:prepend>
                                      <VAvatar color="secondary" variant="tonal" size="20">
                                        <VIcon icon="ri-task-line" size="10" />
                                      </VAvatar>
                                    </template>

                                    <VCardTitle class="text-body-1">
                                      <div class="font-weight-medium">{{ task.task_name }}</div>
                                    </VCardTitle>

                                    <template v-slot:append>
                                      <div class="d-flex align-center gap-2">
                                        <VChip color="secondary" size="small">
                                          {{ formatHours(task.total_hours) }}
                                        </VChip>
                                        <VChip color="info" size="small" variant="outlined">
                                          {{ task.entries.length }} {{ task.entries.length === 1 ? 'entry' : 'entries'
                                          }}
                                        </VChip>
                                        <VIcon
                                          :icon="expandedTasks[mainCategory.type + '-' + category.category_name + '-' + task.task_name] ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'"
                                          size="14" />
                                      </div>
                                    </template>
                                  </VCardItem>
                                </VCard>

                                <!-- Task Entries -->
                                <VExpandTransition>
                                  <div
                                    v-show="expandedTasks[mainCategory.type + '-' + category.category_name + '-' + task.task_name]"
                                    class="ml-4">
                                    <div class="entries-list">
                                      <VCard v-for="entry in task.entries" :key="entry.id" variant="outlined"
                                        class="mb-2 entry-card">
                                        <VCardText class="pa-3">
                                          <div class="d-flex justify-space-between align-start">
                                            <div class="entry-details flex-grow-1">
                                              <div class="d-flex align-center mb-2">
                                                <VIcon icon="ri-time-line" size="12" class="mr-2" />
                                                <span class="text-body-2 font-weight-medium">
                                                  {{ formatDateTime(entry.start_time) }}
                                                </span>
                                                <VIcon icon="ri-arrow-right-line" size="10" class="mx-2" />
                                                <span class="text-body-2 font-weight-medium">
                                                  {{ formatDateTime(entry.end_time) }}
                                                </span>
                                              </div>

                                              <div v-if="entry.comment" class="text-caption text-medium-emphasis">
                                                <VIcon icon="ri-chat-3-line" size="10" class="mr-1" />
                                                {{ entry.comment }}
                                              </div>
                                            </div>

                                            <div class="entry-duration text-right">
                                              <VChip color="accent" size="small" variant="tonal">
                                                {{ formatHours(entry.duration_hours) }}
                                              </VChip>
                                            </div>
                                          </div>
                                        </VCardText>
                                      </VCard>
                                    </div>
                                  </div>
                                </VExpandTransition>
                              </div>
                            </div>
                          </VExpandTransition>
                        </div>
                      </div>
                    </VExpandTransition>
                  </div>
                </div>
              </VCardText>
            </VCard>
          </VWindowItem>
        </VWindow>
      </div>

      <!-- No Data State -->
      <div v-else class="text-center py-8">
        <VIcon size="48" color="secondary" icon="ri-calendar-line" class="mb-4" />
        <h3 class="text-h6 font-weight-regular mb-2">No data found</h3>
        <p class="text-secondary mb-4">
          No working hours data found for the selected date range.
        </p>
        <VBtn color="primary" @click="viewTimeDoctorRecords" aria-label="Add time records">
          Add Time Records
        </VBtn>
      </div>
    </div>

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

  /* Stack cards vertically on mobile */
  .d-flex.gap-2 {
    flex-direction: column;
    gap: 8px;
  }

  .enhanced-chart-container {
    padding: 16px;
  }

  .chart-grid {
    block-size: 280px !important;
  }

  .text-h4 {
    font-size: 1.4rem !important;
  }

  .category-section,
  .task-section {
    padding-inline-start: 8px;
  }

  .entry-card {
    margin-block-end: 8px !important;
  }

  .ml-4 {
    margin-inline-start: 8px !important;
  }
}

/* Enhanced chart styling */
.enhanced-chart-container {
  padding: 24px;
  border: 1px solid #e0e0e0;
  border-radius: 12px;
  background: linear-gradient(135deg, #fafafa 0%, #f5f5f5 100%);
  inline-size: 100%;
}

.chart-grid {
  padding: 12px;
  border-radius: 8px;
  background: white;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 5%);
  inline-size: 100%;
}

.bar-segment {
  border: 1px solid rgba(255, 255, 255, 20%);
  transition: all 0.3s ease;
}

.bar-segment:hover {
  filter: brightness(1.1);
  opacity: 0.85;
  transform: scaleX(1.02);
}

.day-info {
  min-block-size: 50px;
  padding-block: 8px;
  padding-inline: 4px;
}

.main-category-section {
  border-inline-start: 4px solid #e3f2fd;
  padding-inline-start: 16px;
}

.category-section {
  border-inline-start: 3px solid #e8f5e8;
  padding-inline-start: 12px;
}

.task-section {
  border-inline-start: 2px solid #f5f5f5;
  padding-inline-start: 8px;
}

.entry-card {
  background: #fafafa;
  border-inline-start: 3px solid transparent;
  transition: all 0.2s ease;
}

.entry-card:hover {
  background: #f0f0f0;
  border-inline-start-color: #2196f3;
  transform: translateX(4px);
}

.cursor-pointer {
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.cursor-pointer:hover {
  background: rgba(0, 0, 0, 2%);
}

/* Chart container */
.chart-container {
  position: relative;
  inline-size: 100%;
  min-block-size: 300px;
}

/* Progress styling */
:deep(.v-progress-linear) {
  border-radius: 4px;
}

:deep(.v-progress-circular) {
  font-weight: 600;
}

/* Table styling */
:deep(.v-data-table) {
  border-radius: 8px;
}

/* List styling */
:deep(.v-list-item) {
  border-radius: 4px;
  margin-block-end: 4px;
}

:deep(.v-list-item:hover) {
  background-color: rgba(var(--v-theme-primary), 0.04);
}

/* Ensure proper chip sizing */
:deep(.v-chip) {
  font-size: 0.75rem;
}

/* Enhanced focus states for accessibility */
:deep(.v-btn:focus-visible) {
  outline: 2px solid var(--v-theme-primary);
  outline-offset: 2px;
}

.cursor-pointer:focus-visible {
  border-radius: 4px;
  outline: 2px solid var(--v-theme-primary);
  outline-offset: 2px;
}

/* Chart bar hover effects */
.chart-bar-container:hover .bar-segment {
  filter: brightness(1.05);
}

.chart-bar-container:hover .day-info {
  color: var(--v-theme-primary);
}
</style>
