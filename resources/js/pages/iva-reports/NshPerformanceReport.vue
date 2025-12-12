<script setup>
import { formatDate, formatTimeAgo } from '@/@core/utils/helpers';
import { formatHours } from '@/@core/utils/worklogHelpers';
import { getMaxSelectableDate } from '@/@core/utils/dateValidation';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();

// Data
const nshData = ref([]);
const summary = ref({});
const selectedDate = ref('');
const isYesterday = ref(false);
const loading = ref(true);
const searchQuery = ref('');
const selectedWorkStatus = ref('');
const isMobile = ref(window.innerWidth < 768);
const regionFilter = ref({ applied: false, region_id: null, reason: null });
const regionAccessError = ref(false);
const regionAccessErrorMessage = ref('');

// Pagination
const currentPage = ref(1);
const perPage = ref(50);
const totalItems = ref(0);
const lastPage = ref(1);

// UI state
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

// Computed properties
const dateLabel = computed(() => {
  if (!selectedDate.value) return '';
  const date = new Date(selectedDate.value);
  const formattedDate = formatDate(selectedDate.value);

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

const maxSelectableDate = computed(() => getMaxSelectableDate());

const workStatusOptions = computed(() => [
  { title: 'All Status', value: '' },
  { title: 'Full-Time', value: 'full-time' },
  { title: 'Part-Time', value: 'part-time' },
]);

const paginationInfo = computed(() => {
  const from = (currentPage.value - 1) * perPage.value + 1;
  const to = Math.min(currentPage.value * perPage.value, totalItems.value);
  return `${from}-${to} of ${totalItems.value}`;
});

const regionFilteredRegionName = computed(() => {
  if (!regionFilter.value.applied) return null;
  // Get region name from the first nsh data entry
  return nshData.value.length > 0 ? nshData.value[0].region : 'your region';
});

function getHoursColor(hours, workStatus) {
  if (workStatus === 'full-time' && hours > 10) return 'error';
  if (workStatus === 'part-time' && hours > 6) return 'warning';
  if (hours >= 8) return 'success';
  if (hours >= 4) return 'info';
  return 'secondary';
}

function getWorkStatusColor(workStatus) {
  return workStatus === 'full-time' ? 'primary' : 'secondary';
}

function getWorkStatusIcon(workStatus) {
  return workStatus === 'full-time' ? 'ri-user-fill' : 'ri-user-3-line';
}

onMounted(() => {
  initializeDate();
  fetchNshData();
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

async function fetchNshData() {
  loading.value = true;

  try {
    const params = {
      date: selectedDate.value,
      search: searchQuery.value,
      work_status: selectedWorkStatus.value,
      page: currentPage.value,
      per_page: perPage.value,
    };

    const response = await axios.get('/api/reports/nsh-performance', { params });

    // Check for region access error
    if (response.data.region_access_error) {
      regionAccessError.value = true;
      regionAccessErrorMessage.value = response.data.message;
      return;
    }

    nshData.value = response.data.nsh_data;
    summary.value = response.data.summary;
    isYesterday.value = response.data.is_yesterday;

    // Update pagination
    const pagination = response.data.pagination;
    totalItems.value = pagination.total;
    lastPage.value = pagination.last_page;

    // Handle region filter from backend
    if (response.data.region_filter) {
      regionFilter.value = response.data.region_filter;
    }

  } catch (error) {
    // Check if error response contains region access error
    if (error.response?.data?.region_access_error) {
      regionAccessError.value = true;
      regionAccessErrorMessage.value = error.response.data.message;
      return;
    }

    console.error('Error fetching NSH data:', error);
    showSnackbar('Failed to load NSH data', 'error');
  } finally {
    loading.value = false;
  }
}

function onDateChange() {
  currentPage.value = 1;
  fetchNshData();
}

function onSearchChange() {
  // Debounce search
  clearTimeout(window.searchTimeout);
  window.searchTimeout = setTimeout(() => {
    currentPage.value = 1;
    fetchNshData();
  }, 300);
}

function onWorkStatusChange() {
  currentPage.value = 1;
  fetchNshData();
}

function onPageChange(page) {
  currentPage.value = page;
  fetchNshData();
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
  fetchNshData();
}

function goToToday() {
  const today = new Date();
  selectedDate.value = today.toISOString().split('T')[0];
  fetchNshData();
}
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <VBreadcrumbs :items="[
      { title: 'Home', to: '/' },
      { title: 'Reports', disabled: true },
      { title: 'NSH Performance', disabled: true }
    ]" class="mb-6" aria-label="Breadcrumb navigation" />

    <!-- Region Access Error Alert -->
    <VAlert v-if="regionAccessError" type="error" variant="tonal" prominent class="mb-6">
      <VAlertTitle class="mb-2">
        <VIcon icon="ri-error-warning-line" class="me-2" />
        Region Assignment Required
      </VAlertTitle>
      <p>{{ regionAccessErrorMessage }}</p>
      <p class="mt-3 mb-0">
        <strong>What to do:</strong> Please contact your administrator to have a region assigned to your account.
      </p>
    </VAlert>

    <!-- Region Filter Notice -->
    <VAlert v-if="regionFilter.applied && !regionAccessError" type="info" variant="tonal" class="mb-6">
      <VAlertTitle class="d-flex align-center">
        <VIcon icon="ri-information-line" class="me-2" />
        Filtered View
      </VAlertTitle>
      <p class="mb-0">
        You are viewing NSH performance data for <strong>{{ regionFilteredRegionName }}</strong> only, based on your permissions.
      </p>
    </VAlert>

    <!-- Hide all data when error exists -->
    <template v-if="!regionAccessError">
      <!-- Header Card -->
      <VCard class="mb-6">
        <VCardText>
          <div class="d-flex flex-wrap align-center justify-space-between gap-4">
          <div>
            <h1 class="text-h5 text-md-h4 font-weight-bold mb-2" tabindex="0">
              NSH Performance Report
            </h1>
            <p class="text-body-1 mb-2">
              Non Stop Hour (NSH) tracking - Users ranked by highest total working hours
            </p>
            <div class="d-flex flex-wrap gap-2">
              <VChip v-if="dateLabel" color="primary" size="small" prepend-icon="ri-calendar-line">
                {{ dateLabel }}
              </VChip>
              <VChip v-if="summary.total_users" color="info" size="small" variant="tonal" prepend-icon="ri-group-line">
                {{ summary.total_users }} Users
              </VChip>
              <VChip v-if="summary.users_over_10h" color="error" size="small" variant="tonal" prepend-icon="ri-alarm-warning-line">
                {{ summary.users_over_10h }} Users >10h
              </VChip>
            </div>
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
              prepend-inner-icon="ri-calendar-line" :max="maxSelectableDate" @update:model-value="onDateChange" />
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
          <VCol cols="12" md="4">
            <VTextField v-model="searchQuery" label="Search Users" density="comfortable" variant="outlined"
              prepend-inner-icon="ri-search-line" clearable @update:model-value="onSearchChange" />
          </VCol>

          <!-- Work Status Filter -->
          <VCol cols="12" md="3">
            <VSelect v-model="selectedWorkStatus" :items="workStatusOptions"
              label="Work Status" density="comfortable" variant="outlined"
              @update:model-value="onWorkStatusChange" />
          </VCol>

          <!-- Summary Stats -->
          <VCol cols="12" md="2">
            <div class="text-center">
              <div class="text-h6 font-weight-bold">{{ summary.total_hours?.toFixed(1) || 0 }}h</div>
              <div class="text-caption text-medium-emphasis">Total Hours</div>
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- NSH Data Display -->
    <VCard>
      <VCardText>
        <!-- Loading State -->
        <div v-if="loading" class="text-center py-8">
          <VProgressCircular indeterminate color="primary" class="mb-4" />
          <p class="text-body-1">Loading NSH data...</p>
        </div>

        <!-- No Data State -->
        <div v-else-if="nshData.length === 0" class="text-center py-8">
          <VIcon size="48" color="secondary" icon="ri-time-line" class="mb-4" />
          <h3 class="text-h6 font-weight-regular mb-2">No NSH Data Found</h3>
          <p class="text-secondary">No work entries recorded for the selected date.</p>
        </div>

        <!-- NSH Table Display -->
        <div v-else>
          <!-- Pagination Info -->
          <div class="d-flex justify-space-between align-center mb-4">
            <div class="text-sm text-medium-emphasis">
              Showing {{ paginationInfo }}
            </div>
          </div>

          <!-- Table -->
          <VTable>
            <thead>
              <tr>
                <th class="text-start">Name</th>
                <th class="text-start">Status</th>
                <th class="text-start">Task</th>
                <th class="text-center">Hours</th>
                <th class="text-center">Start Time</th>
                <th class="text-center">End Time</th>
                <th class="text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="entry in nshData" :key="entry.id">
                <td>
                  <div class="d-flex align-center gap-2">
                    <VAvatar :color="getHoursColor(entry.hours, entry.work_status)" size="32" variant="tonal">
                      <VIcon :icon="getWorkStatusIcon(entry.work_status)" size="small" />
                    </VAvatar>
                    <div>
                      <div class="font-weight-medium">{{ entry.full_name }}</div>
                      <div class="text-caption text-disabled">
                        {{ entry.region }}
                        <span v-if="entry.job_title"> • {{ entry.job_title }}</span>
                      </div>
                    </div>
                  </div>
                </td>

                <td>
                  <VChip :color="getWorkStatusColor(entry.work_status)" size="small" variant="tonal">
                    {{ entry.work_status }}
                  </VChip>
                </td>

                <td class="text-body-2">
                  {{ entry.task_name }}
                </td>

                <td class="text-center">
                  <VChip :color="getHoursColor(entry.hours, entry.work_status)" size="small" variant="flat"
                    text-color="white">
                    {{ entry.hours.toFixed(1) }}h
                  </VChip>
                </td>

                <td class="text-center text-body-2">
                  {{ new Date(entry.start_time).toLocaleTimeString('en-US', {
                    hour12: false,
                    hour: '2-digit',
                    minute: '2-digit'
                  }) }}
                </td>

                <td class="text-center text-body-2">
                  {{ new Date(entry.end_time).toLocaleTimeString('en-US', {
                    hour12: false,
                    hour: '2-digit',
                    minute: '2-digit'
                  }) }}
                </td>

                <td class="text-center">
                  <VTooltip text="View Details" location="top">
                    <template #activator="{ props }">
                      <VBtn v-bind="props" icon size="small" variant="text" color="primary"
                        @click="viewUserDashboard(entry.id)" aria-label="View user dashboard">
                        <VIcon icon="ri-dashboard-line" />
                      </VBtn>
                    </template>
                  </VTooltip>
                </td>
              </tr>
            </tbody>
          </VTable>

          <!-- Pagination -->
          <div class="d-flex justify-center mt-6" v-if="lastPage > 1">
            <VPagination
              v-model="currentPage"
              :length="lastPage"
              :total-visible="isMobile ? 5 : 7"
              @update:model-value="onPageChange"
            />
          </div>
        </div>
      </VCardText>
    </VCard>

    </template>

    <!-- Snackbar -->
    <VSnackbar v-model="snackbar" :color="snackbarColor" :timeout="3000">
      {{ snackbarText }}
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
</style>