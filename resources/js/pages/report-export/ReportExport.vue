<script setup>
import { WORKLOG_CONFIG } from '@/@core/utils/worklogConfig';
import { getCustomMonthOptionsForSummary, getWeekRangeForYear } from '@/@core/utils/worklogHelpers';
import { filterFutureWeeks, filterFutureMonths, getMaxSelectableDate, getMaxSelectableMonth } from '@/@core/utils/dateValidation';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';

// Data
const loading = ref(false);
const regions = ref([]);
const regionFilter = ref({ applied: false, region_id: null, reason: null });

// Form data
const formData = ref({
  reportType: 'overall', // 'region' or 'overall'
  regionId: null,
  reportPeriod: 'weekly_summary', // 'weekly_summary', 'monthly_summary', 'yearly_summary', 'calendar_month', 'bimonthly', 'custom'
  year: new Date().getFullYear(),
  month: null,
  bimonthlyDate: 15,
  startDate: null,
  endDate: null,
  customStartDate: null,
  customEndDate: null
});

// UI state
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');
const exporting = ref(false);

// Date ranges for pre-defined periods
const dateRanges = ref([]);
const selectedDateRange = ref(null);

// Computed properties
const reportTypeOptions = computed(() => [
  { title: 'Region Report', value: 'region' },
  { title: 'Overall Report', value: 'overall' }
]);

const reportPeriodOptions = computed(() => [
  { title: 'Weekly Summary', value: 'weekly_summary' },
  { title: 'Monthly Summary', value: 'monthly_summary' },
  { title: 'Yearly Summary (52 Weeks)', value: 'yearly_summary' },
  //{ title: 'Calendar Month', value: 'calendar_month' },
  //{ title: 'Bimonthly', value: 'bimonthly' },
  { title: 'Custom Range', value: 'custom' }
]);

const yearOptions = computed(() => {
  const currentYear = new Date().getFullYear();
  const startYear = WORKLOG_CONFIG.START_YEAR;
  const options = [];

  for (let year = currentYear; year >= startYear; year--) {
    options.push({ title: year.toString(), value: year });
  }
  return options;
});

const monthOptions = computed(() => {
  const year = formData.value.year;
  const allMonths = [
    { title: `January (${year}-01-01 to ${year}-01-31)`, value: 1 },
    { title: `February (${year}-02-01 to ${year}-02-${isLeapYear(year) ? '29' : '28'})`, value: 2 },
    { title: `March (${year}-03-01 to ${year}-03-31)`, value: 3 },
    { title: `April (${year}-04-01 to ${year}-04-30)`, value: 4 },
    { title: `May (${year}-05-01 to ${year}-05-31)`, value: 5 },
    { title: `June (${year}-06-01 to ${year}-06-30)`, value: 6 },
    { title: `July (${year}-07-01 to ${year}-07-31)`, value: 7 },
    { title: `August (${year}-08-01 to ${year}-08-31)`, value: 8 },
    { title: `September (${year}-09-01 to ${year}-09-30)`, value: 9 },
    { title: `October (${year}-10-01 to ${year}-10-31)`, value: 10 },
    { title: `November (${year}-11-01 to ${year}-11-30)`, value: 11 },
    { title: `December (${year}-12-01 to ${year}-12-31)`, value: 12 }
  ];

  // Filter future months
  return filterFutureMonths(allMonths, year);
});

const bimonthlyDateOptions = computed(() => {
  const options = [];
  for (let i = 1; i <= 28; i++) {
    options.push({ title: `${i}${getOrdinalSuffix(i)}`, value: i });
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

const regionOptions = computed(() => {
  return regions.value.map(region => ({
    title: region.name,
    value: region.id,
    subtitle: region.cohort_count > 0
      ? `Cohort ${region.cohort_count} | ${region.user_count} users`
      : `${region.user_count} users`,
    cohortNames: region.cohort_names,
    ...region
  }));
});

const showRegionSelect = computed(() => {
  return formData.value.reportType === 'region';
});

const showYearSelect = computed(() => {
  return ['weekly_summary', 'monthly_summary', 'yearly_summary', 'calendar_month', 'bimonthly'].includes(formData.value.reportPeriod);
});

const showMonthSelect = computed(() => {
  return ['calendar_month', 'bimonthly'].includes(formData.value.reportPeriod);
});

const showBimonthlyDate = computed(() => {
  return formData.value.reportPeriod === 'bimonthly';
});

const showDateRangeSelect = computed(() => {
  return ['weekly_summary', 'monthly_summary', 'yearly_summary'].includes(formData.value.reportPeriod);
});

const showCustomDates = computed(() => {
  return formData.value.reportPeriod === 'custom';
});

const maxSelectableDate = computed(() => getMaxSelectableDate());

const canExport = computed(() => {
  if (formData.value.reportType === 'region' && !formData.value.regionId) {
    return false;
  }

  if (showDateRangeSelect.value && selectedDateRange.value === null) {
    return false;
  }

  if (showCustomDates.value && (!formData.value.customStartDate || !formData.value.customEndDate)) {
    return false;
  }

  if (showMonthSelect.value && !formData.value.month) {
    return false;
  }

  return true;
});

const isReportTypeDisabled = computed(() => {
  return regionFilter.value.applied;
});

const isRegionSelectorDisabled = computed(() => {
  return regionFilter.value.applied;
});

const regionFilteredRegionName = computed(() => {
  if (!regionFilter.value.applied || !formData.value.regionId) return '';
  const region = regions.value.find(r => r.id === formData.value.regionId);
  return region ? region.name : '';
});

// Helper functions
function getOrdinalSuffix(num) {
  const j = num % 10;
  const k = num % 100;
  if (j == 1 && k != 11) return "st";
  if (j == 2 && k != 12) return "nd";
  if (j == 3 && k != 13) return "rd";
  return "th";
}

function isLeapYear(year) {
  return (year % 4 === 0 && year % 100 !== 0) || (year % 400 === 0);
}

// Watchers
watch([() => formData.value.reportPeriod, () => formData.value.year], async () => {
  if (showDateRangeSelect.value) {
    await loadDateRanges();
  }
});

watch(() => formData.value.year, () => {
  // Reset month selection if current month is beyond max allowed for the year
  if (formData.value.month) {
    const maxMonth = getMaxSelectableMonth(formData.value.year);
    if (formData.value.month > maxMonth) {
      formData.value.month = null;
    }
  }
});

watch(() => formData.value.reportType, () => {
  // Reset region selection when switching report types
  formData.value.regionId = null;
});

// Methods
async function loadRegions() {
  try {
    const response = await axios.get('/api/reports/available-regions');
    if (response.data.success) {
      regions.value = response.data.regions;

      // Handle region filter from backend
      if (response.data.region_filter) {
        regionFilter.value = response.data.region_filter;
        // If region filter is applied, force report type and region
        if (regionFilter.value.applied && regionFilter.value.region_id) {
          formData.value.reportType = 'region';
          formData.value.regionId = regionFilter.value.region_id;
        }
      }
    }
  } catch (error) {
    console.error('Error loading regions:', error);
    showSnackbar('Error loading regions', 'error');
  }
}

async function loadDateRanges() {
  if (!showDateRangeSelect.value) return;

  try {
    dateRanges.value = [];

    switch (formData.value.reportPeriod) {
      case 'weekly_summary': {
        const weeks = getWeekRangeForYear(formData.value.year);
        // Filter future weeks
        const availableWeeks = filterFutureWeeks(weeks, formData.value.year);

        dateRanges.value = availableWeeks.map(week => ({
          label: week.label,
          start_date: week.start_date,
          end_date: week.end_date,
          subtitle: `${week.start_date} to ${week.end_date}`
        }));
        break;
      }

      case 'monthly_summary': {
        const monthOptions = getCustomMonthOptionsForSummary(formData.value.year);
        // Filter future months
        const availableMonths = filterFutureMonths(monthOptions, formData.value.year);

        dateRanges.value = availableMonths.map(month => ({
          label: month.title,
          start_date: month.start_date,
          end_date: month.end_date,
          subtitle: `${month.start_date} to ${month.end_date}`
        }));
        break;
      }

      case 'yearly_summary': {
        const weeks = getWeekRangeForYear(formData.value.year);
        // For yearly summary, use all weeks without filtering
        const availableWeeks = filterFutureWeeks(weeks, formData.value.year, true);

        // Always show yearly option with full 52 weeks for complete year data
        if (weeks.length >= 52) {
          const firstWeek = weeks[0];
          const lastWeek = weeks[51];

          dateRanges.value.push({
            label: `Year ${formData.value.year} (52 weeks)`,
            start_date: firstWeek.start_date,
            end_date: lastWeek.end_date,
            subtitle: `${firstWeek.start_date} to ${lastWeek.end_date}`
          });
        }
        break;
      }
    }

    // Reset selection
    selectedDateRange.value = dateRanges.value.length > 0 ? 0 : null;
  } catch (error) {
    console.error('Error loading date ranges:', error);
    showSnackbar('Error loading date ranges', 'error');
  }
}

function showSnackbar(message, color = 'success') {
  snackbarText.value = message;
  snackbarColor.value = color;
  snackbar.value = true;
}

async function exportReport() {
  if (!canExport.value) {
    showSnackbar('Please complete all required fields', 'warning');
    return;
  }

  try {
    exporting.value = true;

    // Build export parameters
    const exportParams = {
      report_type: formData.value.reportType,
      report_period: formData.value.reportPeriod,
      year: formData.value.year
    };

    // Add region if selected
    if (formData.value.reportType === 'region' && formData.value.regionId) {
      exportParams.region_id = formData.value.regionId;
    }

    // Add date parameters based on report period
    if (showDateRangeSelect.value && selectedDateRange.value !== null) {
      const selectedRange = dateRanges.value[selectedDateRange.value];
      exportParams.start_date = selectedRange.start_date;
      exportParams.end_date = selectedRange.end_date;
    } else if (formData.value.reportPeriod === 'calendar_month') {
      exportParams.month = formData.value.month;
    } else if (formData.value.reportPeriod === 'bimonthly') {
      exportParams.month = formData.value.month;
      exportParams.bimonthly_date = formData.value.bimonthlyDate;
    } else if (formData.value.reportPeriod === 'custom') {
      exportParams.start_date = formData.value.customStartDate;
      exportParams.end_date = formData.value.customEndDate;
    }

    // Make the export request
    const response = await axios.post('/api/reports/export', exportParams, {
      responseType: 'blob'
    });

    // Create download link
    const blob = new Blob([response.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;

    // Extract filename from headers (try custom header first, then content-disposition)
    let filename = 'report.xlsx'; // fallback
    
    // Try custom X-Filename header first
    const customFilename = response.headers['x-filename'] || response.headers['X-Filename'];
    if (customFilename) {
      filename = customFilename;
    } else {
      // Fallback to content-disposition header
      const contentDisposition = response.headers['content-disposition'] || response.headers['Content-Disposition'];
      
      if (contentDisposition) {
        // Try different patterns for filename extraction
        let filenameMatch = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
        if (!filenameMatch) {
          filenameMatch = contentDisposition.match(/filename="(.+)"/);
        }
        if (!filenameMatch) {
          filenameMatch = contentDisposition.match(/filename=([^;\n]*)/);
        }
        
        if (filenameMatch && filenameMatch[1]) {
          filename = filenameMatch[1].replace(/['"]/g, ''); // Remove quotes
        }
      }
    }
    
    link.download = filename;

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);

    showSnackbar('Report exported successfully!', 'success');

  } catch (error) {
    console.error('Error exporting report:', error);
    const errorMessage = error.response?.data?.message || 'Error exporting report';
    showSnackbar(errorMessage, 'error');
  } finally {
    exporting.value = false;
  }
}


// Lifecycle
onMounted(async () => {
  loading.value = true;
  try {
    await Promise.all([
      loadRegions(),
      loadDateRanges()
    ]);
  } catch (error) {
    console.error('Error initializing page:', error);
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <VBreadcrumbs :items="[
      { title: 'Home', to: '/' },
      { title: 'Reports', disabled: true },
      { title: 'Export', disabled: true }
    ]" class="mb-6" aria-label="Breadcrumb navigation" />

    <!-- Region Filter Notice -->
    <VAlert v-if="regionFilter.applied" type="info" variant="tonal" class="mb-6" prominent>
      <VAlertTitle class="d-flex align-center">
        <VIcon icon="ri-lock-line" class="me-2" />
        Region-Filtered Export
      </VAlertTitle>
      <p class="mb-0">
        You can only export data for <strong>{{ regionFilteredRegionName }}</strong> based on your permissions.
        Report type is locked to "Region Report" and region selection is restricted to your assigned region.
      </p>
    </VAlert>

    <VRow>
      <VCol cols="12">
        <VCard>
          <VCardTitle class="d-flex align-center">
            <VIcon icon="ri-download-line" class="me-3" />
            Report Export
          </VCardTitle>
          <VCardText>
            Export performance reports to Excel format with customizable date ranges and filtering options.
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12">
        <VCard>
          <VCardText>
            <VForm @submit.prevent="exportReport">
              <VRow>
                <!-- Report Type Selection -->
                <VCol cols="12" md="6">
                  <VSelect v-model="formData.reportType" :items="reportTypeOptions" item-title="title"
                    item-value="value" label="Report Type" placeholder="Select report type" variant="outlined"
                    :loading="loading" :disabled="isReportTypeDisabled"
                    :prepend-inner-icon="isReportTypeDisabled ? 'ri-lock-line' : undefined" />
                  <VCardText class="text-body-2 text-medium-emphasis pa-0 mt-1">
                    📍 Region: Single region performance | Overall: All regions combined
                  </VCardText>
                </VCol>

                <!-- Region Selection (only for region reports) -->
                <VCol v-if="showRegionSelect" cols="12" md="6">
                  <VSelect v-model="formData.regionId" :items="regionOptions" item-title="title" item-value="value"
                    label="Region" placeholder="Select region" variant="outlined" :loading="loading"
                    :disabled="isRegionSelectorDisabled" :prepend-inner-icon="isRegionSelectorDisabled ? 'ri-lock-line' : undefined">
                    <template #item="{ item, props }">
                      <VListItem v-bind="props">
                        <template #prepend>
                          <VIcon icon="ri-map-pin-line" color="primary" />
                        </template>
                        <template #subtitle>
                          {{ item.raw.subtitle }}
                        </template>
                        <template #append v-if="item.raw.cohortNames">
                          <VChip size="x-small" color="info" variant="tonal">
                            {{ item.raw.cohortNames }}
                          </VChip>
                        </template>
                      </VListItem>
                    </template>
                  </VSelect>
                  <VCardText class="text-body-2 text-medium-emphasis pa-0 mt-1">
                    🗺️ Choose which region to generate report for
                  </VCardText>
                </VCol>

                <!-- Report Period Selection -->
                <VCol cols="12">
                  <VSelect v-model="formData.reportPeriod" :items="reportPeriodOptions" item-title="title"
                    item-value="value" label="Report Period Type" placeholder="Select report period type"
                    variant="outlined" />
                  <VCardText class="text-body-2 text-medium-emphasis pa-0 mt-1">
                    📅 Weekly: Week-based | Monthly: Month-based | Yearly: 52-week report | Calendar: Full calendar
                    month | Bimonthly:
                    Split month | Custom: Date range
                  </VCardText>
                </VCol>

                <!-- Year Selection -->
                <VCol v-if="showYearSelect" cols="12" :md="showDateRangeSelect ? 4 : showMonthSelect ? 4 : 12">
                  <VSelect v-model="formData.year" :items="yearOptions" item-title="title" item-value="value"
                    label="Year" placeholder="Select year" variant="outlined" />
                  <VCardText class="text-body-2 text-medium-emphasis pa-0 mt-1">
                    🗓️ Select the year for your report
                  </VCardText>
                </VCol>

                <!-- Date Range Selection (same row as year for weekly/monthly/yearly) -->
                <VCol v-if="showDateRangeSelect" cols="12" md="8">
                  <VSelect v-model="selectedDateRange" :items="dateRangeOptions" item-title="title" item-value="value"
                    label="Date Range" placeholder="Select date range" variant="outlined" :loading="loading" />
                  <VCardText class="text-body-2 text-medium-emphasis pa-0 mt-1">
                    📊 Choose specific weeks/months for the report
                  </VCardText>
                </VCol>

                <!-- Month Selection (for calendar month and bimonthly) -->
                <VCol v-if="showMonthSelect" cols="12" :md="showBimonthlyDate ? 4 : 8">
                  <VSelect v-model="formData.month" :items="monthOptions" item-title="title" item-value="value"
                    label="Month" placeholder="Select month" variant="outlined" />
                  <VCardText class="text-body-2 text-medium-emphasis pa-0 mt-1">
                    📆 Select the month with date ranges shown
                  </VCardText>
                </VCol>

                <!-- Bimonthly Split Date -->
                <VCol v-if="showBimonthlyDate" cols="12" md="4">
                  <VSelect v-model="formData.bimonthlyDate" :items="bimonthlyDateOptions" item-title="title"
                    item-value="value" label="Split Date" placeholder="Select split date" variant="outlined" />
                  <VCardText class="text-body-2 text-medium-emphasis pa-0 mt-1">
                    ✂️ Date that separates first and second half of the month
                  </VCardText>
                </VCol>

                <!-- Custom Date Range -->
                <VCol v-if="showCustomDates" cols="12" md="6">
                  <VTextField v-model="formData.customStartDate" type="date" label="Start Date" variant="outlined" :max="maxSelectableDate" />
                </VCol>

                <VCol v-if="showCustomDates" cols="12" md="6">
                  <VTextField v-model="formData.customEndDate" type="date" label="End Date" variant="outlined" :max="maxSelectableDate" />
                </VCol>

                <!-- Export Button -->
                <VCol cols="12">
                  <VBtn type="submit" color="primary" size="large" :loading="exporting"
                    :disabled="!canExport || exporting" block>
                    <VIcon icon="ri-download-line" start />
                    {{ exporting ? 'Exporting...' : 'Export Report' }}
                  </VBtn>
                </VCol>
              </VRow>
            </VForm>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>

  <!-- Snackbar -->
  <VSnackbar v-model="snackbar" :color="snackbarColor" :timeout="4000" location="top end">
    {{ snackbarText }}
    <template #actions>
      <VBtn variant="text" @click="snackbar = false">
        Close
      </VBtn>
    </template>
  </VSnackbar>
</template>
