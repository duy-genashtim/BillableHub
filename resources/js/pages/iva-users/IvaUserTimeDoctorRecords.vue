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

// Search and filters
const searchQuery = ref('');
const selectedProject = ref(null);
const selectedTask = ref(null);
const selectedStatus = ref(null);
const selectedDateRange = ref({
  start: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], // 7 days ago
  end: new Date().toISOString().split('T')[0] // today
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
const editDialog = ref(false);
const addDialog = ref(false);
const deleteDialog = ref(false);
const syncConfirmDialog = ref(false);
const statusToggleDialog = ref(false);
const itemToDelete = ref(null);
const itemToToggle = ref(null);

// Edit form
const editForm = ref({
  id: null,
  project_id: null,
  task_id: null,
  start_time: '',
  end_time: '',
  comment: '',
  is_active: true
});

// Add form
const addForm = ref({
  project_id: null,
  task_id: null,
  start_time: '',
  end_time: '',
  comment: '',
  work_mode: 'manual',
  api_type: 'manual'
});

// Form validation
const errors = ref({});
const autoSaving = ref(false);
const syncing = ref(false);

// Table headers
const headers = computed(() => {
  if (isMobile.value) {
    return [
      { title: 'Date/Time', key: 'start_time', sortable: true },
      { title: 'Duration', key: 'duration_hours', sortable: true },
      { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
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
      { title: 'Type', key: 'api_type', sortable: true, width: '100px' },
      { title: 'Status', key: 'is_active', sortable: true, width: '100px' },
      { title: 'Actions', key: 'actions', sortable: false, align: 'end', width: '120px' },
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

  if (selectedStatus.value !== null) {
    filtered = filtered.filter(worklog => worklog.is_active === selectedStatus.value);
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
  if (selectedStatus.value !== null) count++;
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
  if (selectedStatus.value !== null) {
    filters.push(`Status: ${selectedStatus.value ? 'Active' : 'Inactive'}`);
  }
  return filters.join(' • ');
});

const editFormValid = computed(() => {
  return editForm.value.start_time &&
    editForm.value.end_time &&
    editForm.value.project_id &&
    editForm.value.task_id &&
    new Date(editForm.value.start_time) < new Date(editForm.value.end_time);
});

const addFormValid = computed(() => {
  return addForm.value.start_time &&
    addForm.value.end_time &&
    addForm.value.project_id &&
    addForm.value.task_id &&
    new Date(addForm.value.start_time) < new Date(addForm.value.end_time);
});

// Lifecycle
onMounted(() => {
  checkTimeDoctorConnection();
  fetchUserDetails();
  fetchProjects();
  fetchTasks();
  fetchWorklogs();
  window.addEventListener('resize', handleResize);
});

function handleResize() {
  isMobile.value = window.innerWidth < 768;
}

// API calls
async function checkTimeDoctorConnection() {
  try {
    const response = await axios.get('/api/timedoctor/status');
    timeDoctorConnected.value = response.data.connected;
    timeDoctorMessage.value = response.data.message;

    if (!timeDoctorConnected.value) {
      snackbarText.value = 'Time Doctor is not connected. Some features may be limited.';
      snackbarColor.value = 'warning';
      snackbar.value = true;
    }
  } catch (error) {
    console.error('Error checking Time Doctor connection:', error);
    timeDoctorConnected.value = false;
    timeDoctorMessage.value = 'Failed to check connection status';
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

// CRUD operations
function openEditDialog(worklog) {
  editForm.value = {
    id: worklog.id,
    project_id: worklog.project_id,
    task_id: worklog.task_id,
    start_time: formatDateTimeForInput(worklog.start_time),
    end_time: formatDateTimeForInput(worklog.end_time),
    comment: worklog.comment || '',
    is_active: worklog.is_active
  };
  errors.value = {};
  editDialog.value = true;
}

function openAddDialog() {
  addForm.value = {
    project_id: null,
    task_id: null,
    start_time: '',
    end_time: '',
    comment: '',
    work_mode: 'manual',
    api_type: 'manual'
  };
  errors.value = {};
  addDialog.value = true;
}

function openDeleteDialog(worklog) {
  itemToDelete.value = worklog;
  deleteDialog.value = true;
}

function openStatusToggleDialog(worklog) {
  itemToToggle.value = worklog;
  statusToggleDialog.value = true;
}

async function saveWorklog() {
  if (!editFormValid.value) {
    snackbarText.value = 'Please fill all required fields correctly';
    snackbarColor.value = 'error';
    snackbar.value = true;
    return;
  }

  autoSaving.value = true;
  errors.value = {};

  try {
    const response = await axios.put(`/api/admin/iva-users/${userId}/timedoctor-records/${editForm.value.id}`, editForm.value);

    if (response.data.success) {
      snackbarText.value = 'Worklog updated successfully';
      snackbarColor.value = 'success';
      snackbar.value = true;
      editDialog.value = false;
      await fetchWorklogs();
    }
  } catch (error) {
    console.error('Error updating worklog:', error);

    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors;
    } else {
      snackbarText.value = error.response?.data?.message || 'Failed to update worklog';
      snackbarColor.value = 'error';
      snackbar.value = true;
    }
  } finally {
    autoSaving.value = false;
  }
}

async function addWorklog() {
  if (!addFormValid.value) {
    snackbarText.value = 'Please fill all required fields correctly';
    snackbarColor.value = 'error';
    snackbar.value = true;
    return;
  }

  autoSaving.value = true;
  errors.value = {};

  try {
    const response = await axios.post(`/api/admin/iva-users/${userId}/timedoctor-records`, addForm.value);

    if (response.data.success) {
      snackbarText.value = 'Worklog added successfully';
      snackbarColor.value = 'success';
      snackbar.value = true;
      addDialog.value = false;
      await fetchWorklogs();
    }
  } catch (error) {
    console.error('Error adding worklog:', error);

    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors;
    } else {
      snackbarText.value = error.response?.data?.message || 'Failed to add worklog';
      snackbarColor.value = 'error';
      snackbar.value = true;
    }
  } finally {
    autoSaving.value = false;
  }
}

async function deleteWorklog() {
  try {
    const response = await axios.delete(`/api/admin/iva-users/${userId}/timedoctor-records/${itemToDelete.value.id}`);

    if (response.data.success) {
      snackbarText.value = 'Worklog deleted successfully';
      snackbarColor.value = 'success';
      snackbar.value = true;
      deleteDialog.value = false;
      await fetchWorklogs();
    }
  } catch (error) {
    console.error('Error deleting worklog:', error);
    snackbarText.value = error.response?.data?.message || 'Failed to delete worklog';
    snackbarColor.value = 'error';
    snackbar.value = true;
  }
}

async function confirmToggleWorklogStatus() {
  try {
    const response = await axios.patch(`/api/admin/iva-users/${userId}/timedoctor-records/${itemToToggle.value.id}/toggle-status`);

    if (response.data.success) {
      snackbarText.value = `Worklog ${itemToToggle.value.is_active ? 'deactivated' : 'activated'} successfully`;
      snackbarColor.value = 'success';
      snackbar.value = true;
      statusToggleDialog.value = false;
      await fetchWorklogs();
    }
  } catch (error) {
    console.error('Error toggling worklog status:', error);
    snackbarText.value = error.response?.data?.message || 'Failed to update worklog status';
    snackbarColor.value = 'error';
    snackbar.value = true;
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

function calculateDuration() {
  if (editForm.value.start_time && editForm.value.end_time) {
    const start = new Date(editForm.value.start_time);
    const end = new Date(editForm.value.end_time);
    const diffMs = end - start;
    const diffHours = diffMs / (1000 * 60 * 60);
    return diffHours > 0 ? formatDuration(diffHours) : 'Invalid duration';
  }
  return 'Select times';
}

function calculateAddDuration() {
  if (addForm.value.start_time && addForm.value.end_time) {
    const start = new Date(addForm.value.start_time);
    const end = new Date(addForm.value.end_time);
    const diffMs = end - start;
    const diffHours = diffMs / (1000 * 60 * 60);
    return diffHours > 0 ? formatDuration(diffHours) : 'Invalid duration';
  }
  return 'Select times';
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
  selectedStatus.value = null;
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
                  Time Doctor: {{ timeDoctorConnected ? 'Connected' : 'Disconnected' }}
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

              <VBtn color="success" prepend-icon="ri-add-line" :size="isMobile ? 'small' : 'default'"
                @click="openAddDialog" aria-label="Add new worklog record">
                Add Record
              </VBtn>

              <VBtn color="info" variant="outlined" prepend-icon="ri-dashboard-line"
                :size="isMobile ? 'small' : 'default'" @click="viewWorklogDashboard"
                aria-label="View Worklog Dashboard">
                View Dashboard
              </VBtn>

              <VBtn color="secondary" variant="outlined" prepend-icon="ri-arrow-left-line"
                :size="isMobile ? 'small' : 'default'" @click="goBack" aria-label="Back to user details">
                Back
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

            <VCol cols="12" md="3">
              <VSelect v-model="selectedStatus" :items="[
                { title: 'Active', value: true },
                { title: 'Inactive', value: false }
              ]" item-title="title" item-value="value" label="Filter by Status" density="comfortable"
                variant="outlined" clearable aria-label="Filter by status" />
            </VCol>

            <VCol cols="12" md="3" class="d-flex align-center">
              <VBtn v-if="searchQuery || selectedProject || selectedTask || selectedStatus !== null" color="secondary"
                variant="outlined" prepend-icon="ri-filter-off-line" @click="clearFilters"
                aria-label="Clear all filters" class="w-100">
                Clear Filters
              </VBtn>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- Worklogs Table -->
      <VCard>
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
                {{ item.comment || 'No comment' }}
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

            <!-- Actions Column -->
            <template #[`item.actions`]="{ item }">
              <div class="d-flex justify-end" :class="isMobile ? 'flex-wrap gap-1' : ''">
                <VBtn icon size="small" variant="text" color="primary" class="me-1" @click="openEditDialog(item)"
                  aria-label="Edit worklog record">
                  <VIcon size="20">ri-edit-line</VIcon>
                  <VTooltip activator="parent">Edit</VTooltip>
                </VBtn>

                <VBtn icon size="small" variant="text" :color="item.is_active ? 'warning' : 'success'" class="me-1"
                  @click="openStatusToggleDialog(item)"
                  :aria-label="item.is_active ? 'Deactivate record' : 'Activate record'">
                  <VIcon size="20">{{ item.is_active ? 'ri-pause-line' : 'ri-play-line' }}</VIcon>
                  <VTooltip activator="parent">{{ item.is_active ? 'Deactivate' : 'Activate' }}</VTooltip>
                </VBtn>

                <VBtn icon size="small" variant="text" color="error" @click="openDeleteDialog(item)"
                  aria-label="Delete worklog record">
                  <VIcon size="20">ri-delete-bin-line</VIcon>
                  <VTooltip activator="parent">Delete</VTooltip>
                </VBtn>
              </div>
            </template>

            <!-- Empty state -->
            <template #no-data>
              <div class="d-flex flex-column align-center pa-6" role="alert" aria-live="polite">
                <VIcon size="48" color="secondary" icon="ri-time-line" class="mb-4" aria-hidden="true" />
                <h3 class="text-h6 font-weight-regular mb-2">No worklog records found</h3>
                <p class="text-secondary text-center mb-4">
                  <span v-if="searchQuery || selectedProject || selectedTask || selectedStatus !== null">
                    Try changing your filters or date range.
                  </span>
                  <span v-else>
                    No records found for the selected date range. Try syncing from Time Doctor or add records manually.
                  </span>
                </p>
                <div class="d-flex gap-2 flex-wrap">
                  <VBtn v-if="searchQuery || selectedProject || selectedTask || selectedStatus !== null"
                    color="secondary" @click="clearFilters" aria-label="Clear all filters">
                    Clear Filters
                  </VBtn>
                  <VBtn v-if="timeDoctorConnected" color="primary" @click="openSyncConfirmDialog"
                    aria-label="Sync from Time Doctor">
                    Sync from Time Doctor
                  </VBtn>
                  <VBtn color="success" @click="openAddDialog" aria-label="Add new record manually">
                    Add Record
                  </VBtn>
                </div>
              </div>
            </template>
          </VDataTable>
        </VCardText>
      </VCard>
    </div>

    <!-- Sync Confirmation Dialog -->
    <VDialog v-model="syncConfirmDialog" max-width="500" persistent role="alertdialog">
      <VCard>
        <VCardTitle class="text-h5 bg-info text-white d-flex align-center py-3">
          Confirm Time Doctor Sync
        </VCardTitle>

        <VCardText class="pt-4">
          <p>This will sync Time Doctor records for <strong>{{ user?.full_name }}</strong> from:</p>
          <div class="my-3 pa-3 bg-grey-lighten-4 rounded">
            <p class="mb-1"><strong>Start Date:</strong> {{ selectedDateRange.start }}</p>
            <p class="mb-0"><strong>End Date:</strong> {{ selectedDateRange.end }}</p>
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

    <!-- Status Toggle Confirmation Dialog -->
    <VDialog v-model="statusToggleDialog" max-width="500" persistent role="alertdialog">
      <VCard>
        <VCardTitle class="text-h5 bg-warning text-white d-flex align-center py-3">
          {{ itemToToggle?.is_active ? 'Deactivate' : 'Activate' }} Record
        </VCardTitle>

        <VCardText class="pt-4">
          <p>Are you sure you want to {{ itemToToggle?.is_active ? 'deactivate' : 'activate' }} this worklog record?</p>
          <div v-if="itemToToggle" class="my-3 pa-3 bg-grey-lighten-4 rounded">
            <p class="mb-1"><strong>Date:</strong> {{ formatDateRaw(itemToToggle.start_time) }}</p>
            <p class="mb-1"><strong>Time:</strong> {{ formatTimeRaw(itemToToggle.start_time) }} - {{
              formatTimeRaw(itemToToggle.end_time) }}</p>
            <p class="mb-1"><strong>Duration:</strong> {{ formatDuration(itemToToggle.duration_hours) }}</p>
            <p class="mb-1"><strong>Project:</strong> {{ itemToToggle.project?.project_name || 'No Project' }}</p>
            <p class="mb-0"><strong>Task:</strong> {{ itemToToggle.task?.task_name || 'No Task' }}</p>
          </div>
          <p class="text-body-2 mb-0">
            {{ itemToToggle?.is_active ? 'Deactivating' : 'Activating' }} this record will {{ itemToToggle?.is_active ?
              'exclude it from' : 'include it in' }} reports and calculations.
          </p>
        </VCardText>

        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="statusToggleDialog = false" aria-label="Cancel">
            Cancel
          </VBtn>
          <VBtn :color="itemToToggle?.is_active ? 'warning' : 'success'" variant="flat"
            @click="confirmToggleWorklogStatus" aria-label="Confirm status change">
            {{ itemToToggle?.is_active ? 'Deactivate' : 'Activate' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Edit Dialog -->
    <VDialog v-model="editDialog" max-width="700" persistent role="dialog" aria-labelledby="edit-dialog-title">
      <VCard>
        <VCardTitle id="edit-dialog-title" class="text-h5 bg-primary text-white d-flex align-center py-3">
          Edit Worklog Record
        </VCardTitle>

        <VCardText class="pt-4">
          <VRow>
            <VCol cols="12" md="6">
              <VSelect v-model="editForm.project_id" :items="projects" item-title="project_name" item-value="id"
                label="Project" density="comfortable" variant="outlined" :error-messages="errors.project_id"
                aria-label="Select project" required />
            </VCol>

            <VCol cols="12" md="6">
              <VSelect v-model="editForm.task_id" :items="tasks" item-title="task_name" item-value="id" label="Task"
                density="comfortable" variant="outlined" :error-messages="errors.task_id" aria-label="Select task"
                required />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField v-model="editForm.start_time" label="Start Time" type="datetime-local" density="comfortable"
                variant="outlined" :error-messages="errors.start_time" required aria-label="Start time" />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField v-model="editForm.end_time" label="End Time" type="datetime-local" density="comfortable"
                variant="outlined" :error-messages="errors.end_time" required aria-label="End time" />
            </VCol>

            <VCol cols="12">
              <VTextField v-model="editForm.comment" label="Comment" density="comfortable" variant="outlined"
                :error-messages="errors.comment" aria-label="Comment" />
            </VCol>

            <VCol cols="12" md="6">
              <VSwitch v-model="editForm.is_active" label="Active" color="success"
                aria-label="Toggle record active status" />
            </VCol>

            <VCol cols="12" md="6">
              <VAlert type="info" variant="tonal" density="compact" class="mb-0">
                Duration: {{ calculateDuration() }}
              </VAlert>
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="editDialog = false" aria-label="Cancel editing">
            Cancel
          </VBtn>
          <VBtn color="primary" variant="flat" :loading="autoSaving" :disabled="!editFormValid" @click="saveWorklog"
            aria-label="Save changes">
            Save Changes
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Add Dialog -->
    <VDialog v-model="addDialog" max-width="700" persistent role="dialog" aria-labelledby="add-dialog-title">
      <VCard>
        <VCardTitle id="add-dialog-title" class="text-h5 bg-success text-white d-flex align-center py-3">
          Add New Worklog Record
        </VCardTitle>

        <VCardText class="pt-4">
          <VRow>
            <VCol cols="12" md="6">
              <VSelect v-model="addForm.project_id" :items="projects" item-title="project_name" item-value="id"
                label="Project" density="comfortable" variant="outlined" :error-messages="errors.project_id"
                aria-label="Select project" required />
            </VCol>

            <VCol cols="12" md="6">
              <VSelect v-model="addForm.task_id" :items="tasks" item-title="task_name" item-value="id" label="Task"
                density="comfortable" variant="outlined" :error-messages="errors.task_id" aria-label="Select task"
                required />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField v-model="addForm.start_time" label="Start Time" type="datetime-local" density="comfortable"
                variant="outlined" :error-messages="errors.start_time" required aria-label="Start time" />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField v-model="addForm.end_time" label="End Time" type="datetime-local" density="comfortable"
                variant="outlined" :error-messages="errors.end_time" required aria-label="End time" />
            </VCol>

            <VCol cols="12">
              <VTextField v-model="addForm.comment" label="Comment" density="comfortable" variant="outlined"
                :error-messages="errors.comment" aria-label="Comment" />
            </VCol>

            <VCol cols="12">
              <VAlert type="info" variant="tonal" density="compact" class="mb-0">
                Duration: {{ calculateAddDuration() }}
              </VAlert>
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="addDialog = false" aria-label="Cancel adding">
            Cancel
          </VBtn>
          <VBtn color="success" variant="flat" :loading="autoSaving" :disabled="!addFormValid" @click="addWorklog"
            aria-label="Add record">
            Add Record
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Confirmation Dialog -->
    <VDialog v-model="deleteDialog" max-width="500" role="alertdialog" aria-labelledby="delete-dialog-title">
      <VCard>
        <VCardTitle id="delete-dialog-title" class="text-h5 bg-error text-white d-flex align-center py-3">
          Delete Worklog Record
        </VCardTitle>

        <VCardText class="pt-4">
          <p>Are you sure you want to delete this worklog record?</p>
          <div v-if="itemToDelete" class="my-3 pa-3 bg-grey-lighten-4 rounded">
            <p class="mb-1"><strong>Date:</strong> {{ formatDateRaw(itemToDelete.start_time) }}</p>
            <p class="mb-1"><strong>Time:</strong> {{ formatTimeRaw(itemToDelete.start_time) }} - {{
              formatTimeRaw(itemToDelete.end_time) }}</p>
            <p class="mb-1"><strong>Duration:</strong> {{ formatDuration(itemToDelete.duration_hours) }}</p>
            <p class="mb-1"><strong>Project:</strong> {{ itemToDelete.project?.project_name || 'No Project' }}</p>
            <p class="mb-0"><strong>Task:</strong> {{ itemToDelete.task?.task_name || 'No Task' }}</p>
          </div>
          <p class="text-body-2 mb-0">This action cannot be undone.</p>
        </VCardText>

        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="deleteDialog = false" aria-label="Cancel deletion">
            Cancel
          </VBtn>
          <VBtn color="error" variant="flat" @click="deleteWorklog" aria-label="Confirm deletion">
            Delete
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
