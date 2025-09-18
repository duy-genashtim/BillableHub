<script setup>
import { formatDateRaw, formatTimeRaw } from '@/@core/utils/helpers';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
const route = useRoute();
const router = useRouter();
const userId = route.params.id;

// Data
const user = ref(null);
const worklogs = ref([]);
const projects = ref([]);
const tasks = ref([]);
const loading = ref(true);
const loadingWorklogs = ref(false);
const isMobile = ref(window.innerWidth < 768);
const timeDoctorConnected = ref(false);
const timeDoctorMessage = ref('');
const timeDoctorVersion = ref('');

// Search and filters
const searchQuery = ref('');
const selectedProject = ref(null);
const selectedTask = ref(null);
const selectedDateRange = ref({
  start: '', // Will be set by initializeDateFromURL()
  end: ''    // Will be set by initializeDateFromURL()
});

// Pagination
const pagination = ref({
  page: 1,
  total: 0,
  perPage: 20
});

// Sorting
const sortBy = ref([]);
const sortDesc = ref([]);

// UI state
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');
const syncConfirmDialog = ref(false);


const syncing = ref(false);

// Tab state
const activeTab = ref('worklogs');

// Daily summaries data
const dailySummaries = ref([]);
const loadingSummaries = ref(false);

// Table headers
const headers = computed(() => {
  if (isMobile.value) {
    return [
      { title: 'Date/Time', key: 'start_time', sortable: true },
      { title: 'Duration', key: 'duration_hours', sortable: true },
    ];
  } else {
    return [
      { title: 'Date', key: 'date', sortable: true, width: '120px' },
      { title: 'Start Time', key: 'start_time', sortable: false, width: '140px' },
      { title: 'End Time', key: 'end_time', sortable: false, width: '140px' },
      { title: 'Duration', key: 'duration_hours', sortable: true, width: '100px' },
      { title: 'Project', key: 'project.project_name', sortable: true },
      { title: 'Task', key: 'task.task_name', sortable: true },
      { title: 'Comment', key: 'comment', sortable: false },
      { title: 'API Type', key: 'api_type', sortable: true, width: '100px' },
    ];
  }
});

// Summary table headers
const summaryHeaders = computed(() => {
  if (isMobile.value) {
    return [
      { title: 'Date', key: 'report_date', sortable: true },
      { title: 'Category', key: 'category_name', sortable: true },
      { title: 'Duration', key: 'duration_hours', sortable: true },
    ];
  } else {
    return [
      { title: 'Date', key: 'report_date', sortable: true, width: '120px' },
      { title: 'Category Name', key: 'category_name', sortable: true, width: '250px' },
      { title: 'Type', key: 'category_type', sortable: true, width: '120px' },
      { title: 'Duration', key: 'formatted_duration', sortable: false, width: '120px' },
      { title: 'Hours', key: 'duration_hours', sortable: true, width: '100px' },
      { title: 'Entries', key: 'entries_count', sortable: true, width: '100px' },
    ];
  }
});

// Computed properties
const filteredAndSortedWorklogs = computed(() => {
  let filtered = [...worklogs.value];

  // Apply filters
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(worklog =>
      (worklog.project?.project_name?.toLowerCase().includes(query)) ||
      (worklog.task?.task_name?.toLowerCase().includes(query)) ||
      (worklog.comment?.toLowerCase().includes(query))
    );
  }

  if (selectedProject.value) {
    filtered = filtered.filter(worklog => worklog.project_id === selectedProject.value);
  }

  if (selectedTask.value) {
    filtered = filtered.filter(worklog => worklog.task_id === selectedTask.value);
  }


  // Apply sorting
  if (sortBy.value.length > 0) {
    const sortKey = sortBy.value[0].key;
    const sortOrder = sortBy.value[0].order;

    filtered.sort((a, b) => {
      let aVal, bVal;

      switch (sortKey) {
        case 'date':
        case 'start_time':
          aVal = new Date(a.start_time);
          bVal = new Date(b.start_time);
          break;
        case 'duration_hours':
          aVal = a.duration_hours;
          bVal = b.duration_hours;
          break;
        case 'project.project_name':
          aVal = a.project?.project_name || '';
          bVal = b.project?.project_name || '';
          break;
        case 'task.task_name':
          aVal = a.task?.task_name || '';
          bVal = b.task?.task_name || '';
          break;
        case 'api_type':
          aVal = a.api_type || '';
          bVal = b.api_type || '';
          break;
        case 'is_active':
          aVal = a.is_active ? 1 : 0;
          bVal = b.is_active ? 1 : 0;
          break;
        default:
          aVal = a[sortKey] || '';
          bVal = b[sortKey] || '';
      }

      if (sortOrder === 'desc') {
        return aVal < bVal ? 1 : aVal > bVal ? -1 : 0;
      } else {
        return aVal > bVal ? 1 : aVal < bVal ? -1 : 0;
      }
    });
  }

  return filtered;
});

const activeFiltersCount = computed(() => {
  let count = 0;
  if (searchQuery.value) count++;
  if (selectedProject.value) count++;
  if (selectedTask.value) count++;
  return count;
});

const activeFiltersText = computed(() => {
  const filters = [];
  if (searchQuery.value) filters.push(`Search: "${searchQuery.value}"`);
  if (selectedProject.value) {
    const project = projects.value.find(p => p.id === selectedProject.value);
    filters.push(`Project: ${project?.project_name || 'Unknown'}`);
  }
  if (selectedTask.value) {
    const task = tasks.value.find(t => t.id === selectedTask.value);
    filters.push(`Task: ${task?.task_name || 'Unknown'}`);
  }
  return filters.join(' • ');
});

// Watchers
watch(() => selectedDateRange.value, () => {
  fetchWorklogs();
  fetchDailySummaries();
}, { deep: true });

// Lifecycle
onMounted(() => {
  initializeDateFromURL();
  checkTimeDoctorConnection();
  fetchUserDetails();
  fetchProjects();
  fetchTasks();
  fetchWorklogs();
  fetchDailySummaries();
  window.addEventListener('resize', handleResize);
});

function initializeDateFromURL() {
  const { start_date, end_date } = route.query;

  // Default values (7 days ago to today)
  const defaultStart = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
  const defaultEnd = new Date().toISOString().split('T')[0];

  if (start_date && end_date) {
    // Validate date format (YYYY-MM-DD)
    const dateRegex = /^\d{4}-\d{2}-\d{2}$/;

    if (dateRegex.test(start_date) && dateRegex.test(end_date)) {
      const startDateObj = new Date(start_date);
      const endDateObj = new Date(end_date);

      // Validate that dates are valid and start_date <= end_date
      if (startDateObj.getTime() && endDateObj.getTime() && startDateObj <= endDateObj) {
        // Use URL parameters
        selectedDateRange.value.start = start_date;
        selectedDateRange.value.end = end_date;

        console.log('TimeDoctor Records initialized from URL:', { start_date, end_date });
        return;
      } else {
        console.warn('Invalid date range in URL parameters:', { start_date, end_date });
      }
    } else {
      console.warn('Invalid date format in URL parameters:', { start_date, end_date });
    }
  }

  // Fall back to default values
  selectedDateRange.value.start = defaultStart;
  selectedDateRange.value.end = defaultEnd;
  console.log('TimeDoctor Records using default date range:', { start: defaultStart, end: defaultEnd });
}

function handleResize() {
  isMobile.value = window.innerWidth < 768;
}

// API calls
async function checkTimeDoctorConnection() {
  try {
    const response = await axios.get('/api/timedoctor/status');
    timeDoctorConnected.value = response.data.connected;
    timeDoctorMessage.value = response.data.message;
    
    // Set TimeDoctorVersion based on user's timedoctor_version
    if (user.value?.timedoctor_version === 2) {
      timeDoctorVersion.value = 'Time Doctor V2';
    } else {
      timeDoctorVersion.value = 'Time Doctor V1 Classic';
    }

    if (!timeDoctorConnected.value) {
      snackbarText.value = 'Time Doctor is not connected. Some features may be limited.';
      snackbarColor.value = 'warning';
      snackbar.value = true;
    }
  } catch (error) {
    console.error('Error checking Time Doctor connection:', error);
    timeDoctorConnected.value = false;
    timeDoctorMessage.value = 'Failed to check connection status';
    timeDoctorVersion.value = 'Time Doctor V1 Classic'; // Default fallback
  }
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

async function fetchProjects() {
  try {
    const response = await axios.get('/api/admin/iva-users/timedoctor-records/projects');
    projects.value = response.data.projects;
  } catch (error) {
    console.error('Error fetching projects:', error);
  }
}

async function fetchTasks() {
  try {
    const response = await axios.get('/api/admin/iva-users/timedoctor-records/tasks');
    tasks.value = response.data.tasks;
  } catch (error) {
    console.error('Error fetching tasks:', error);
  }
}

async function fetchWorklogs() {
  loadingWorklogs.value = true;

  try {
    const params = {
      start_date: selectedDateRange.value.start,
      end_date: selectedDateRange.value.end
    };

    const response = await axios.get(`/api/admin/iva-users/${userId}/timedoctor-records`, { params });

    worklogs.value = response.data.worklogs.data;
    pagination.value.total = response.data.worklogs.total;
  } catch (error) {
    console.error('Error fetching worklogs:', error);
    snackbarText.value = 'Failed to load worklog records';
    snackbarColor.value = 'error';
    snackbar.value = true;
  } finally {
    loadingWorklogs.value = false;
    loading.value = false;
  }
}

async function fetchDailySummaries() {
  loadingSummaries.value = true;

  try {
    const params = {
      start_date: selectedDateRange.value.start,
      end_date: selectedDateRange.value.end
    };

    const response = await axios.get(`/api/admin/iva-users/${userId}/timedoctor-records/daily-summaries`, { params });

    if (response.data.success) {
      dailySummaries.value = response.data.summaries;
    } else {
      throw new Error(response.data.message || 'Failed to fetch daily summaries');
    }
  } catch (error) {
    console.error('Error fetching daily summaries:', error);
    snackbarText.value = 'Failed to load daily summaries';
    snackbarColor.value = 'error';
    snackbar.value = true;
    dailySummaries.value = [];
  } finally {
    loadingSummaries.value = false;
  }
}

function openSyncConfirmDialog() {
  if (!timeDoctorConnected.value) {
    snackbarText.value = 'Time Doctor is not connected. Please connect first.';
    snackbarColor.value = 'error';
    snackbar.value = true;
    return;
  }

  syncConfirmDialog.value = true;
}

async function syncTimeDoctorRecords() {
  syncing.value = true;
  syncConfirmDialog.value = false;

  try {
    snackbarText.value = 'Starting Time Doctor records sync...';
    snackbarColor.value = 'info';
    snackbar.value = true;

    const response = await axios.post(`/api/admin/iva-users/${userId}/timedoctor-records/sync`, {
      start_date: selectedDateRange.value.start,
      end_date: selectedDateRange.value.end
    });

    if (response.data.success) {
      snackbarText.value = `Sync completed! ${response.data.synced_count} records synced.`;
      snackbarColor.value = 'success';
      snackbar.value = true;
      await fetchWorklogs();
      await fetchDailySummaries(); // Refresh daily summaries after sync
    } else {
      snackbarText.value = response.data.message || 'Sync failed';
      snackbarColor.value = 'error';
      snackbar.value = true;
    }
  } catch (error) {
    console.error('Error syncing Time Doctor records:', error);
    snackbarText.value = error.response?.data?.message || 'Failed to sync records';
    snackbarColor.value = 'error';
    snackbar.value = true;
  } finally {
    syncing.value = false;
  }
}


// Utility functions
function formatDateTimeForInput(dateTime) {
  if (!dateTime) return '';

  const date = new Date(dateTime);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');

  return `${year}-${month}-${day}T${hours}:${minutes}`;
}

// function formatDateRaw(dateTime) {
//   if (!dateTime) return 'N/A';
//   return new Date(dateTime).toLocaleDateString();
// }

// function formatTimeRaw(dateTime) {
//   if (!dateTime) return 'N/A';
//   return new Date(dateTime).toLocaleTimeString('en-US', {
//     hour: '2-digit',
//     minute: '2-digit',
//     hour12: false
//   });
// }

function formatDuration(hours) {
  if (!hours) return '0h 0m';

  const wholeHours = Math.floor(hours);
  const minutes = Math.round((hours - wholeHours) * 60);

  return `${wholeHours}h ${minutes}m`;
}


function getStatusColor(isActive) {
  return isActive ? 'success' : 'error';
}

function getStatusText(isActive) {
  return isActive ? 'Active' : 'Inactive';
}

function getApiTypeColor(apiType) {
  switch (apiType) {
    case 'timedoctor': return 'primary';
    case 'manual': return 'secondary';
    default: return 'grey';
  }
}

function clearFilters() {
  searchQuery.value = '';
  selectedProject.value = null;
  selectedTask.value = null;
  fetchWorklogs();
}

function goBack() {
  router.push({ name: 'iva-user-detail', params: { id: userId } });
}

function viewWorklogDashboard() {
  router.push({ name: 'iva-user-worklog-dashboard', params: { id: userId } });
}

// Watch for date range changes
watch(() => selectedDateRange.value, () => {
  fetchWorklogs();
}, { deep: true });

// Watch for search query changes with debounce
watch(searchQuery, debounce(() => {
  // The filtering is done client-side in computed property
}, 300));

function debounce(fn, delay) {
  let timeoutId;
  return function (...args) {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => fn.apply(this, args), delay);
  };
}
</script>
<template>
  <div>
    <!-- Breadcrumbs -->
    <VBreadcrumbs :items="[
      { title: 'Home', to: '/' },
      { title: 'IVA Users', to: { name: 'iva-users-list' } },
      { title: user ? user.full_name : 'User', to: user ? { name: 'iva-user-detail', params: { id: userId } } : {} },
      { title: 'Time Doctor Records', disabled: true }
    ]" class="mb-6" aria-label="Breadcrumb navigation" />

    <VCard v-if="loading" class="mb-6">
      <VCardText class="d-flex justify-center align-center pa-6">
        <VProgressCircular indeterminate color="primary" :size="50" :width="5" aria-label="Loading user details" />
      </VCardText>
    </VCard>

    <div v-else>
      <!-- Header Card -->
      <VCard class="mb-6">
        <VCardText>
          <div class="d-flex flex-wrap align-center mb-4">
            <div class="mr-auto mb-2 mb-md-0">
              <h1 class="text-h5 text-md-h4" tabindex="0">
                Time Doctor Records: {{ user?.full_name }}
              </h1>
              <div class="d-flex align-center mt-2">
                <VChip size="small" :color="timeDoctorConnected ? 'success' : 'error'" text-color="white" class="mr-2">
                  {{ timeDoctorVersion }}: {{ timeDoctorConnected ? 'Connected' : 'Disconnected' }}
                </VChip>
                <span class="text-caption">{{ timeDoctorMessage }}</span>
              </div>
            </div>

            <div class="d-flex gap-2">
              <VBtn color="primary" variant="outlined" prepend-icon="ri-refresh-line"
                :size="isMobile ? 'small' : 'default'" :loading="syncing" :disabled="!timeDoctorConnected"
                @click="openSyncConfirmDialog" aria-label="Sync Time Doctor records">
                Sync Records
              </VBtn>


              <VBtn color="info" variant="outlined" prepend-icon="ri-dashboard-line"
                :size="isMobile ? 'small' : 'default'" @click="viewWorklogDashboard"
                aria-label="View Worklog Dashboard">
                View Dashboard
              </VBtn>

              <VBtn color="primary" variant="outlined" prepend-icon="ri-eye-line" :size="isMobile ? 'small' : 'default'"
                @click="goBack" aria-label="Back to IVA user details">
                IVA details
              </VBtn>
            </div>
          </div>
        </VCardText>
      </VCard>

      <!-- Filters and Search -->
      <VCard class="mb-6">
        <VCardText>
          <h2 class="text-h6 font-weight-medium mb-4" tabindex="0">Filters & Search</h2>

          <!-- Date Range -->
          <VRow class="mb-4">
            <VCol cols="12" md="3">
              <VTextField v-model="selectedDateRange.start" label="Start Date" type="date" density="comfortable"
                variant="outlined" aria-label="Start date for filtering records" />
            </VCol>

            <VCol cols="12" md="3">
              <VTextField v-model="selectedDateRange.end" label="End Date" type="date" density="comfortable"
                variant="outlined" aria-label="End date for filtering records" />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField v-model="searchQuery" label="Search records..." prepend-inner-icon="ri-search-line"
                density="comfortable" variant="outlined" clearable placeholder="Search by project, task, or comment"
                aria-label="Search worklog records" />
            </VCol>
          </VRow>

          <!-- Filter Dropdowns -->
          <VRow>
            <VCol cols="12" md="3">
              <VSelect v-model="selectedProject" :items="projects" item-title="project_name" item-value="id"
                label="Filter by Project" density="comfortable" variant="outlined" clearable
                aria-label="Filter by project" />
            </VCol>

            <VCol cols="12" md="3">
              <VSelect v-model="selectedTask" :items="tasks" item-title="task_name" item-value="id"
                label="Filter by Task" density="comfortable" variant="outlined" clearable aria-label="Filter by task" />
            </VCol>


            <VCol cols="12" md="3" class="d-flex align-center">
              <VBtn v-if="searchQuery || selectedProject || selectedTask" color="secondary"
                variant="outlined" prepend-icon="ri-filter-off-line" @click="clearFilters"
                aria-label="Clear all filters" class="w-100">
                Clear Filters
              </VBtn>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- Tabbed Content -->
      <VCard>
        <VTabs v-model="activeTab" bg-color="grey-lighten-5" color="primary" align-tabs="start">
          <VTab value="worklogs">
            <VIcon icon="mdi-clock-outline" class="me-2" />
            Time Entries
          </VTab>
          <VTab value="summaries">
            <VIcon icon="mdi-chart-bar" class="me-2" />
            Daily Summaries
          </VTab>
        </VTabs>

        <VTabsWindow v-model="activeTab">
          <!-- Worklogs Tab -->
          <VTabsWindowItem value="worklogs">
            <VCardText>
              <div class="d-flex align-center mb-4">
                <div class="mr-auto">
                  <h2 class="text-h6 font-weight-medium" tabindex="0">
                    {{ pagination.total }} records ({{ selectedDateRange.start }} to {{ selectedDateRange.end }})
                  </h2>
                  <div v-if="activeFiltersCount > 0" class="text-caption text-secondary mt-1">
                    <VIcon icon="ri-filter-line" size="12" class="mr-1" />
                    Filtered by: {{ activeFiltersText }}
                  </div>
                </div>
                <VBtn variant="text" density="compact" icon="ri-refresh-line" @click="fetchWorklogs"
                  :loading="loadingWorklogs" aria-label="Refresh worklog records" />
              </div>

          <VDataTable v-model:sort-by="sortBy" :headers="headers" :items="filteredAndSortedWorklogs"
            :loading="loadingWorklogs" density="comfortable" hover class="elevation-1 rounded"
            aria-label="Worklog records table" :items-per-page="20" :page="1">
            <!-- Date Column (desktop only) -->
            <template v-if="!isMobile" #[`item.date`]="{ item }">
              <span>{{ formatDateRaw(item.start_time) }}</span>
            </template>

            <!-- Start Time Column -->
            <template #[`item.start_time`]="{ item }">
              <div v-if="isMobile">
                <div class="font-weight-medium">{{ formatDateRaw(item.start_time) }}</div>
                <div class="text-caption">{{ formatTimeRaw(item.start_time) }} - {{ formatTimeRaw(item.end_time) }}
                </div>
              </div>
              <span v-else>{{ formatTimeRaw(item.start_time) }}</span>
            </template>

            <!-- End Time Column (desktop only) -->
            <template v-if="!isMobile" #[`item.end_time`]="{ item }">
              <span>{{ formatTimeRaw(item.end_time) }}</span>
            </template>

            <!-- Duration Column -->
            <template #[`item.duration_hours`]="{ item }">
              <VChip size="small" color="info" variant="outlined">
                {{ formatDuration(item.duration_hours) }}
              </VChip>
            </template>

            <!-- Project Column (desktop only) -->
            <template v-if="!isMobile" #[`item.project.project_name`]="{ item }">
              <span>{{ item.project?.project_name || 'No Project' }}</span>
            </template>

            <!-- Task Column (desktop only) -->
            <template v-if="!isMobile" #[`item.task.task_name`]="{ item }">
              <span>{{ item.task?.task_name || 'No Task' }}</span>
            </template>

            <!-- Comment Column (desktop only) -->
            <template v-if="!isMobile" #[`item.comment`]="{ item }">
              <span class="text-truncate" style="max-inline-size: 200px;">
                {{ item.comment || '' }}
              </span>
            </template>

            <!-- API Type Column (desktop only) -->
            <template v-if="!isMobile" #[`item.api_type`]="{ item }">
              <VChip size="small" :color="getApiTypeColor(item.api_type)" variant="flat" text-color="white">
                {{ item.api_type }}
              </VChip>
            </template>

            <!-- Status Column -->
            <template #[`item.is_active`]="{ item }">
              <VChip size="small" :color="getStatusColor(item.is_active)" text-color="white"
                :aria-label="'Record status: ' + getStatusText(item.is_active)">
                {{ getStatusText(item.is_active) }}
              </VChip>
            </template>


            <!-- Empty state -->
            <template #no-data>
              <div class="d-flex flex-column align-center pa-6" role="alert" aria-live="polite">
                <VIcon size="48" color="secondary" icon="ri-time-line" class="mb-4" aria-hidden="true" />
                <h3 class="text-h6 font-weight-regular mb-2">No worklog records found</h3>
                <p class="text-secondary text-center mb-4">
                  <span v-if="searchQuery || selectedProject || selectedTask">
                    Try changing your filters or date range.
                  </span>
                  <span v-else>
                    No records found for the selected date range. Try syncing from Time Doctor or add records manually.
                  </span>
                </p>
                <div class="d-flex gap-2 flex-wrap">
                  <VBtn v-if="searchQuery || selectedProject || selectedTask"
                    color="secondary" @click="clearFilters" aria-label="Clear all filters">
                    Clear Filters
                  </VBtn>
                  <VBtn v-if="timeDoctorConnected" color="primary" @click="openSyncConfirmDialog"
                    aria-label="Sync from Time Doctor">
                    Sync from Time Doctor
                  </VBtn>
                </div>
              </div>
            </template>
              </VDataTable>
            </VCardText>
          </VTabsWindowItem>

          <!-- Daily Summaries Tab -->
          <VTabsWindowItem value="summaries">
            <VCardText>
              <div class="d-flex align-center mb-4">
                <div class="mr-auto">
                  <h2 class="text-h6 font-weight-medium" tabindex="0">
                    Daily Summaries ({{ selectedDateRange.start }} to {{ selectedDateRange.end }})
                  </h2>
                  <p class="text-caption text-secondary mt-1 mb-0">
                    Aggregated by task categories (independent of project/task filters)
                  </p>
                </div>
                <VBtn variant="text" density="compact" icon="ri-refresh-line" @click="fetchDailySummaries"
                  :loading="loadingSummaries" aria-label="Refresh daily summaries" />
              </div>

              <VDataTable
                :headers="summaryHeaders"
                :items="dailySummaries"
                :loading="loadingSummaries"
                density="comfortable"
                hover
                class="elevation-1 rounded"
                aria-label="Daily summaries table"
                :items-per-page="20"
                :page="1"
              >
                <!-- Category Type Column -->
                <template #[`item.category_type`]="{ item }">
                  <VChip 
                    size="small" 
                    :color="item.category_type === 'billable' ? 'success' : 'warning'" 
                    variant="flat" 
                    text-color="white"
                  >
                    {{ item.category_type }}
                  </VChip>
                </template>

                <!-- Duration Column -->
                <template #[`item.formatted_duration`]="{ item }">
                  <VChip size="small" color="info" variant="outlined">
                    {{ item.formatted_duration }}
                  </VChip>
                </template>

                <!-- Duration Hours Column -->
                <template #[`item.duration_hours`]="{ item }">
                  <span class="font-weight-medium">{{ item.duration_hours }}h</span>
                </template>

                <!-- Empty state -->
                <template #no-data>
                  <div class="d-flex flex-column align-center pa-6" role="alert" aria-live="polite">
                    <VIcon size="48" color="secondary" icon="mdi-chart-bar" class="mb-4" aria-hidden="true" />
                    <h3 class="text-h6 font-weight-regular mb-2">No daily summaries found</h3>
                    <p class="text-secondary text-center mb-4">
                      No summary data available for the selected date range. 
                      Try selecting a different date range or sync Time Doctor records first.
                    </p>
                    <VBtn v-if="timeDoctorConnected" color="primary" @click="openSyncConfirmDialog"
                      aria-label="Sync from Time Doctor">
                      Sync Time Doctor Records
                    </VBtn>
                  </div>
                </template>
              </VDataTable>
            </VCardText>
          </VTabsWindowItem>
        </VTabsWindow>
      </VCard>
    </div>

    <!-- Sync Confirmation Dialog -->
    <VDialog v-model="syncConfirmDialog" max-width="500" persistent role="alertdialog">
      <VCard>
        <VCardTitle class="text-h5 bg-info text-white d-flex align-center py-3">
          Confirm Time Doctor Sync
        </VCardTitle>

        <VCardText class="pt-4">
          <p>This will sync {{ timeDoctorVersion }} records for <strong>{{ user?.full_name }}</strong> from:</p>
          <div class="my-3 pa-3 bg-grey-lighten-4 rounded">
            <p class="mb-1"><strong>Start Date:</strong> {{ selectedDateRange.start }}</p>
            <p class="mb-0"><strong>End Date:</strong> {{ selectedDateRange.end }}</p>
          </div>
          <div class="my-3 pa-3 bg-orange-lighten-4 rounded">
            <VIcon icon="ri-alert-line" size="18" class="mr-2" color="orange" />
            <strong>Warning:</strong> All existing records for the selected date range will be removed and replaced with new data from the TimeDoctor server.
          </div>
          <p class="text-body-2 mb-0">This process may take a few moments to complete.</p>
        </VCardText>

        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="syncConfirmDialog = false" aria-label="Cancel sync">
            Cancel
          </VBtn>
          <VBtn color="primary" variant="flat" @click="syncTimeDoctorRecords" aria-label="Start sync">
            Start Sync
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>


    <!-- Snackbar for notifications -->
    <VSnackbar v-model="snackbar" :color="snackbarColor" :timeout="5000" role="alert" aria-live="assertive">
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
  :deep(.v-data-table) {
    font-size: 0.9rem;
  }

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

  /* Make dialogs responsive */
  :deep(.v-dialog .v-card) {
    margin: 16px;
    max-inline-size: calc(100vw - 32px);
  }
}

/* Text truncation utility */
.text-truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Improve table readability */
:deep(.v-data-table-rows-no-data) {
  padding: 2rem;
  text-align: center;
}

/* Ensure proper spacing in dialogs */
:deep(.v-dialog .v-card-text) {
  padding-block-end: 0;
}

:deep(.v-dialog .v-card-actions) {
  padding-block-start: 0;
}
</style>
