<script setup>
import { getOrdinalSuffix } from '@/@core/utils/helpers';
import { WORKLOG_CONFIG } from '@/@core/utils/worklogConfig';
import { formatDate, getCurrentWeekNumber, getCustomMonthOptionsForSummary, getWeekRangeForYear } from '@/@core/utils/worklogHelpers';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import MonthlySummaryMetricsCards from './components/MonthlySummaryMetricsCards.vue';
import MonthlySummaryTabs from './components/MonthlySummaryTabs.vue';
import WeeklySummaryMetricsCards from './components/WeeklySummaryMetricsCards.vue';
import WeeklySummaryTabs from './components/WeeklySummaryTabs.vue';
import WorklogMetricsCards from './components/WorklogMetricsCards.vue';
import WorklogTabs from './components/WorklogTabs.vue';

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
const selectedMonthCount = ref(1);
const bimonthlyDate = ref(15);
const customDateFrom = ref('');
const customDateTo = ref('');

// UI state
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

// Computed properties
const dateRangeText = computed(() => {
  if (!dashboardData.value) return '';

  if (dateMode.value === 'bimonthly' && dashboardData.value.bimonthly_data) {
    const firstHalf = dashboardData.value.bimonthly_data.first_half;
    const secondHalf = dashboardData.value.bimonthly_data.second_half;

    if (firstHalf && firstHalf.date_range && secondHalf && secondHalf.date_range) {
      const start = new Date(firstHalf.date_range.start);
      const end = new Date(secondHalf.date_range.end);
      return `${formatDate(start)} - ${formatDate(end)}`;
    }
  }

  if (dateMode.value === 'weekly_summary' && dashboardData.value.weekly_summary_data) {
    const dateRange = dashboardData.value.weekly_summary_data.date_range;
    if (dateRange) {
      const start = new Date(dateRange.start);
      const end = new Date(dateRange.end);
      return `${formatDate(start)} - ${formatDate(end)}`;
    }
  }

  if (dateMode.value === 'month_summary' && dashboardData.value.monthly_summary_data) {
    const dateRange = dashboardData.value.monthly_summary_data.date_range;
    if (dateRange) {
      const start = new Date(dateRange.start);
      const end = new Date(dateRange.end);
      return `${formatDate(start)} - ${formatDate(end)}`;
    }
  }

  // Add null check for date_range
  if (!dashboardData.value.date_range) return '';

  const start = new Date(dashboardData.value.date_range.start);
  const end = new Date(dashboardData.value.date_range.end);

  return `${formatDate(start)} - ${formatDate(end)}`;
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

const monthOptions = computed(() => {
  if (dateMode.value === 'month_summary') {
    return getMonthOptionsForSummary();
  }

  const months = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
  ];

  return months.map((month, index) => ({
    title: month,
    value: index + 1
  }));
});

const monthCountOptions = computed(() => {
  const options = [];
  for (let i = 1; i <= 12; i++) {
    options.push({
      title: `${i} Month${i > 1 ? 's' : ''}`,
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

const showPerformance = computed(() => {
  return dateMode.value === 'weeks' || dateMode.value === 'weekly_summary' || dateMode.value === 'month_summary';
});

const isWeeklySummaryMode = computed(() => {
  return dateMode.value === 'weekly_summary';
});

const isMonthlySummaryMode = computed(() => {
  return dateMode.value === 'month_summary';
});


function getMonthOptionsForSummary() {
  return getCustomMonthOptionsForSummary(selectedYear.value)
  // const weeks = getWeekRangeForYear(selectedYear.value);
  // const monthGroups = [];
  // const monthNames = [
  //   'January', 'February', 'March', 'April', 'May', 'June',
  //   'July', 'August', 'September', 'October', 'November', 'December'
  // ];

  // // Group weeks into 4-week cycles (months)
  // for (let i = 0; i < weeks.length; i += 4) {
  //   const monthWeeks = weeks.slice(i, i + 4);
  //   if (monthWeeks.length === 4) {
  //     const firstWeek = monthWeeks[0];
  //     const lastWeek = monthWeeks[3];

  //     // Determine month name based on first week's start date
  //     const firstWeekStartDate = new Date(firstWeek.start_date);
  //     const monthIndex = firstWeekStartDate.getMonth();
  //     const monthName = monthNames[monthIndex];
  //     const year = firstWeekStartDate.getFullYear();

  //     // Create readable date range
  //     const startDate = new Date(firstWeek.start_date);
  //     const endDate = new Date(lastWeek.end_date);

  //     const startStr = startDate.toLocaleDateString('en-US', {
  //       month: 'short',
  //       day: 'numeric',
  //       timeZone: 'UTC'
  //     });
  //     const endStr = endDate.toLocaleDateString('en-US', {
  //       month: 'short',
  //       day: 'numeric',
  //       timeZone: 'UTC'
  //     });

  //     monthGroups.push({
  //       title: `${monthName} ${year} (${startStr} - ${endStr})`,
  //       value: monthIndex + 1, // 1-based month number
  //       subtitle: `Weeks ${firstWeek.week_number}-${lastWeek.week_number}`,
  //       weeks: monthWeeks,
  //       start_date: firstWeek.start_date,
  //       end_date: lastWeek.end_date
  //     });
  //   }
  // }
  // console.log('Month groups:', monthGroups);

  // return monthGroups;
}

onMounted(() => {
  fetchUserDetails();
  fetchAvailableWeeks();
  fetchDashboardData();
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
    case 'weeks':
    case 'weekly_summary': {
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

      // Add mode parameter to differentiate between weeks and weekly_summary
      if (dateMode.value === 'weekly_summary') {
        params.mode = 'weekly_summary';
      }
      break;
    }

    case 'month_summary': {
      const monthOptions = getMonthOptionsForSummary();
      const startMonthIndex = selectedMonth.value - 1; // Convert to 0-based
      const endMonthIndex = startMonthIndex + selectedMonthCount.value - 1;

      if (monthOptions[startMonthIndex] && monthOptions[endMonthIndex]) {
        params.start_date = monthOptions[startMonthIndex].start_date;
        params.end_date = monthOptions[endMonthIndex].end_date;
      }

      params.year = selectedYear.value;
      params.month = selectedMonth.value;
      params.month_count = selectedMonthCount.value;
      params.mode = 'month_summary';
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

      // Use UTC dates to avoid timezone issues - full month range
      params.start_date = new Date(Date.UTC(year, month - 1, 1)).toISOString().split('T')[0];
      params.end_date = new Date(Date.UTC(year, month, 0)).toISOString().split('T')[0];
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

  fetchDashboardData();
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

  if (dateMode.value === 'bimonthly' && dashboardData.value?.bimonthly_data) {
    // For bimonthly, use the full date range from first half start to second half end
    const firstHalf = dashboardData.value.bimonthly_data.first_half;
    const secondHalf = dashboardData.value.bimonthly_data.second_half;

    if (firstHalf && firstHalf.date_range && secondHalf && secondHalf.date_range) {
      params.start_date = firstHalf.date_range.start;
      params.end_date = secondHalf.date_range.end;
    }
  } else if (dateMode.value === 'weekly_summary' && dashboardData.value?.weekly_summary_data) {
    // For weekly summary, use the date range from summary data
    const dateRange = dashboardData.value.weekly_summary_data.date_range;
    if (dateRange) {
      params.start_date = dateRange.start;
      params.end_date = dateRange.end;
    }
  } else if (dateMode.value === 'month_summary' && dashboardData.value?.monthly_summary_data) {
    // For monthly summary, use the date range from summary data
    const dateRange = dashboardData.value.monthly_summary_data.date_range;
    if (dateRange) {
      params.start_date = dateRange.start;
      params.end_date = dateRange.end;
    }
  } else if (dateMode.value === 'custom') {
    params.start_date = customDateFrom.value;
    params.end_date = customDateTo.value;
  } else if (dashboardData.value?.date_range) {
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

function showSnackbar(message, color = 'success') {
  snackbarText.value = message;
  snackbarColor.value = color;
  snackbar.value = true;
}

// Watch for changes in date selection
watch(selectedYear, () => {
  if (dateMode.value === 'weeks' || dateMode.value === 'weekly_summary') {
    fetchAvailableWeeks();
  }
  fetchDashboardData();
});

watch(selectedWeekNumber, () => {
  if (dateMode.value === 'weeks' || dateMode.value === 'weekly_summary') {
    fetchDashboardData();
  }
});

watch(selectedWeekCount, () => {
  if (dateMode.value === 'weeks' || dateMode.value === 'weekly_summary') {
    fetchDashboardData();
  }
});

watch(selectedMonth, () => {
  if (dateMode.value === 'monthly' || dateMode.value === 'bimonthly' || dateMode.value === 'month_summary') {
    fetchDashboardData();
  }
});

watch(selectedMonthCount, () => {
  if (dateMode.value === 'month_summary') {
    fetchDashboardData();
  }
});

watch(bimonthlyDate, () => {
  if (dateMode.value === 'bimonthly') {
    fetchDashboardData();
  }
});

watch(customDateFrom, () => {
  if (dateMode.value === 'custom' && customDateFrom.value && customDateTo.value) {
    fetchDashboardData();
  }
});

watch(customDateTo, () => {
  if (dateMode.value === 'custom' && customDateFrom.value && customDateTo.value) {
    fetchDashboardData();
  }
});
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
                <VChip v-if="dateRangeText" color="primary" size="small" prepend-icon="ri-calendar-line">
                  {{ dateRangeText }}
                </VChip>
                <VChip v-if="isWeeklySummaryMode" color="warning" size="small" prepend-icon="ri-calendar-week-line">
                  Weekly Summary
                </VChip>
                <VChip v-if="isMonthlySummaryMode" color="info" size="small" prepend-icon="ri-calendar-month-line">
                  Monthly Summary
                </VChip>
              </div>
            </div>

            <div class="d-flex gap-2 flex-wrap">
              <VBtn color="info" variant="outlined" prepend-icon="ri-time-line" :size="isMobile ? 'small' : 'default'"
                @click="viewTimeDoctorRecords" aria-label="View detailed records">
                View Records
              </VBtn>

              <VBtn color="primary" variant="outlined" prepend-icon="ri-eye-line" :size="isMobile ? 'small' : 'default'"
                @click="goBack" aria-label="Back to IVA user details">
                IVA details
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
                { title: 'Week (Daily View)', value: 'weeks' },
                { title: 'Week (Summary View)', value: 'weekly_summary' },
                { title: 'Month (Summary View)', value: 'month_summary' },
                { title: 'Month', value: 'monthly' },
                { title: 'Bimonthly', value: 'bimonthly' },
                { title: 'Custom Range', value: 'custom' }
              ]" label="Date Selection Mode" density="comfortable" variant="outlined"
                @update:model-value="onDateModeChange" aria-label="Date selection mode" />
            </VCol>

            <!-- Week Selection -->
            <template v-if="dateMode === 'weeks' || dateMode === 'weekly_summary'">
              <VCol cols="12" md="2">
                <VSelect v-model="selectedYear" :items="yearOptions" label="Year" density="comfortable"
                  variant="outlined" aria-label="Select year" />
              </VCol>

              <VCol cols="12" md="4">
                <VSelect v-model="selectedWeekNumber" :items="weekOptions" label="Week" density="comfortable"
                  variant="outlined" aria-label="Select week">
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
                  density="comfortable" variant="outlined" aria-label="Number of weeks" />
              </VCol>
            </template>

            <!-- Month Summary Selection -->
            <template v-else-if="dateMode === 'month_summary'">
              <VCol cols="12" md="2">
                <VSelect v-model="selectedYear" :items="yearOptions" label="Year" density="comfortable"
                  variant="outlined" aria-label="Select year" />
              </VCol>

              <VCol cols="12" md="4">
                <VSelect v-model="selectedMonth" :items="monthOptions" label="Starting Month" density="comfortable"
                  variant="outlined" aria-label="Select starting month">
                  <template v-slot:item="{ item, props }">
                    <VListItem v-bind="props" :title="item.raw.title" :subtitle="item.raw.subtitle">
                      <template v-slot:prepend>
                        <VIcon color="primary" size="small">ri-calendar-month-line</VIcon>
                      </template>
                    </VListItem>
                  </template>
                </VSelect>
              </VCol>

              <VCol cols="12" md="3">
                <VSelect v-model="selectedMonthCount" :items="monthCountOptions" label="Number of Months"
                  density="comfortable" variant="outlined" aria-label="Number of months" />
              </VCol>
            </template>

            <!-- Monthly Selection -->
            <template v-else-if="dateMode === 'monthly'">
              <VCol cols="12" md="3">
                <VSelect v-model="selectedYear" :items="yearOptions" label="Year" density="comfortable"
                  variant="outlined" aria-label="Select year" />
              </VCol>

              <VCol cols="12" md="6">
                <VSelect v-model="selectedMonth" :items="monthOptions" label="Month" density="comfortable"
                  variant="outlined" aria-label="Select month" />
              </VCol>
            </template>

            <!-- Bimonthly Selection -->
            <template v-else-if="dateMode === 'bimonthly'">
              <VCol cols="12" md="2">
                <VSelect v-model="selectedYear" :items="yearOptions" label="Year" density="comfortable"
                  variant="outlined" aria-label="Select year" />
              </VCol>

              <VCol cols="12" md="3">
                <VSelect v-model="selectedMonth" :items="monthOptions" label="Month" density="comfortable"
                  variant="outlined" aria-label="Select month" />
              </VCol>

              <VCol cols="12" md="3">
                <VSelect v-model="bimonthlyDate" :items="bimonthlyDateOptions" label="Split Date" density="comfortable"
                  variant="outlined" aria-label="Select split date"
                  hint="Date that separates first and second half of the month" persistent-hint />
              </VCol>
            </template>

            <!-- Custom Date Selection -->
            <template v-else-if="dateMode === 'custom'">
              <VCol cols="12" md="4">
                <VTextField v-model="customDateFrom" label="From Date" type="date" density="comfortable"
                  variant="outlined" aria-label="Start date" />
              </VCol>

              <VCol cols="12" md="4">
                <VTextField v-model="customDateTo" label="To Date" type="date" density="comfortable" variant="outlined"
                  aria-label="End date" />
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
        <!-- Monthly Summary Layout -->
        <div v-if="isMonthlySummaryMode && dashboardData.monthly_summary_data">
          <!-- Add adjusted start date notification card for monthly summary -->
          <VCard v-if="dashboardData?.adjusted_start_date?.is_adjusted" class="mb-6" color="info" variant="tonal">
            <VCardText>
              <div class="d-flex align-center">
                <VIcon icon="ri-information-line" color="info" class="mr-3" />
                <div>
                  <h3 class="text-subtitle-1 font-weight-medium mb-1">Date Range Adjusted</h3>
                  <p class="text-body-2 mb-0">
                    {{ dashboardData.adjusted_start_date.message }}
                  </p>
                </div>
              </div>
            </VCardText>
          </VCard>
          <MonthlySummaryMetricsCards :summary-data="dashboardData.monthly_summary_data" :is-mobile="isMobile"
            class="mb-6" />

          <MonthlySummaryTabs :summary-data="dashboardData.monthly_summary_data" :user="user" :user-id="userId"
            :is-mobile="isMobile" @show-snackbar="showSnackbar" />
        </div>

        <!-- Weekly Summary Layout -->
        <div v-else-if="isWeeklySummaryMode && dashboardData.weekly_summary_data">
          <!-- Add adjusted start date notification card for weekly summary -->
          <VCard v-if="dashboardData?.adjusted_start_date?.is_adjusted" class="mb-6" color="info" variant="tonal">
            <VCardText>
              <div class="d-flex align-center">
                <VIcon icon="ri-information-line" color="info" class="mr-3" />
                <div>
                  <h3 class="text-subtitle-1 font-weight-medium mb-1">Date Range Adjusted</h3>
                  <p class="text-body-2 mb-0">
                    {{ dashboardData.adjusted_start_date.message }}
                  </p>
                </div>
              </div>
            </VCardText>
          </VCard>
          <WeeklySummaryMetricsCards :summary-data="dashboardData.weekly_summary_data" :is-mobile="isMobile"
            class="mb-6" />

          <WeeklySummaryTabs :summary-data="dashboardData.weekly_summary_data" :user="user" :user-id="userId"
            :is-mobile="isMobile" @show-snackbar="showSnackbar" />
        </div>

        <!-- Bimonthly Layout -->
        <div v-else-if="dateMode === 'bimonthly' && dashboardData.bimonthly_data">
          <!-- First Half -->
          <div class="mb-8">
            <VCard class="mb-4" color="primary" variant="tonal">
              <VCardText class="text-center">
                <h2 class="text-h6 font-weight-bold">First Half</h2>
                <p class="text-body-2 mb-0">
                  {{ formatDate(new Date(dashboardData.bimonthly_data.first_half.date_range.start)) }} -
                  {{ formatDate(new Date(dashboardData.bimonthly_data.first_half.date_range.end)) }}
                </p>
              </VCardText>
            </VCard>

            <WorklogMetricsCards :dashboard-data="dashboardData.bimonthly_data.first_half" :show-performance="false"
              :is-mobile="isMobile" class="mb-6" />

            <WorklogTabs :dashboard-data="dashboardData.bimonthly_data.first_half" :user="user" :user-id="userId"
              :is-mobile="isMobile" :date-mode="dateMode" @show-snackbar="showSnackbar" />
          </div>

          <!-- Second Half -->
          <div class="mb-8">
            <VCard class="mb-4" color="secondary" variant="tonal">
              <VCardText class="text-center">
                <h2 class="text-h6 font-weight-bold">Second Half</h2>
                <p class="text-body-2 mb-0">
                  {{ formatDate(new Date(dashboardData.bimonthly_data.second_half.date_range.start)) }} -
                  {{ formatDate(new Date(dashboardData.bimonthly_data.second_half.date_range.end)) }}
                </p>
              </VCardText>
            </VCard>

            <WorklogMetricsCards :dashboard-data="dashboardData.bimonthly_data.second_half" :show-performance="false"
              :is-mobile="isMobile" class="mb-6" />

            <WorklogTabs :dashboard-data="dashboardData.bimonthly_data.second_half" :user="user" :user-id="userId"
              :is-mobile="isMobile" :date-mode="dateMode" @show-snackbar="showSnackbar" />
          </div>
        </div>

        <!-- Non-Bimonthly Layout -->
        <div v-else>
          <WorklogMetricsCards :dashboard-data="dashboardData" :show-performance="showPerformance"
            :performance-data="dashboardData.target_performances" :is-mobile="isMobile" class="mb-6" />

          <WorklogTabs :dashboard-data="dashboardData" :user="user" :user-id="userId" :is-mobile="isMobile"
            :date-mode="dateMode" @show-snackbar="showSnackbar" />
        </div>
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

  .text-h4 {
    font-size: 1.4rem !important;
  }
}

/* Enhanced focus states for accessibility */
:deep(.v-btn:focus-visible) {
  outline: 2px solid var(--v-theme-primary);
  outline-offset: 2px;
}

/* Ensure proper chip sizing */
:deep(.v-chip) {
  font-size: 0.75rem;
}

/* Progress styling */
:deep(.v-progress-linear) {
  border-radius: 4px;
}

:deep(.v-progress-circular) {
  font-weight: 600;
}

/* List styling */
:deep(.v-list-item) {
  border-radius: 4px;
  margin-block-end: 4px;
}

:deep(.v-list-item:hover) {
  background-color: rgba(var(--v-theme-primary), 0.04);
}
</style>
