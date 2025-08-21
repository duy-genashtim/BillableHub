<script setup>
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();

// Data
const users = ref([]);
const regions = ref([]);
const cohorts = ref([]);
const workStatusOptions = ref([]);
const timedoctorVersions = ref([]);
const loading = ref(true);
const isMobile = ref(window.innerWidth < 768);

// Pagination
const pagination = ref({
  page: 1,
  total: 0,
  perPage: 15
});

// Filters
const filters = ref({
  search: '',
  region_id: null,
  cohort_id: null,
  work_status: null,
  timedoctor_version: null,
  is_active: null
});

// UI state
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

// Table headers
const headers = computed(() => {
  if (isMobile.value) {
    return [
      { title: 'User', key: 'full_name', sortable: true },
      { title: 'Status', key: 'is_active', sortable: true },
      { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
    ];
  } else {
    return [
      { title: 'Name', key: 'full_name', sortable: true, width: '200px' },
      { title: 'Job Title', key: 'job_title', sortable: true, width: '150px' },
      { title: 'Region', key: 'region.name', sortable: true, width: '120px' },
      { title: 'Cohort', key: 'cohort.name', sortable: true, width: '120px' },
      { title: 'Work Status', key: 'work_status', sortable: true, width: '120px' },
      { title: 'TD Version', key: 'timedoctor_version', sortable: true, width: '100px' },
      { title: 'TD Status', key: 'timedoctor_sync_status', sortable: false, width: '120px' },
      { title: 'Status', key: 'is_active', sortable: true, width: '100px' },
      { title: 'Actions', key: 'actions', sortable: false, align: 'end', width: '280px' },
    ];
  }
});

// Status options for filter
const statusOptions = [
  { title: 'Active', value: true },
  { title: 'Inactive', value: false }
];

onMounted(() => {
  fetchUsers();
  window.addEventListener('resize', handleResize);
});

function handleResize() {
  isMobile.value = window.innerWidth < 768;
}

async function fetchUsers() {
  loading.value = true;

  try {
    const params = {
      page: pagination.value.page,
      per_page: pagination.value.perPage,
      ...filters.value
    };

    // Remove null/empty filters
    Object.keys(params).forEach(key => {
      if (params[key] === null || params[key] === '') {
        delete params[key];
      }
    });

    const response = await axios.get('/api/admin/iva-users', { params });

    users.value = response.data.users.data;
    pagination.value.total = response.data.users.total;
    pagination.value.page = response.data.users.current_page;
    pagination.value.perPage = response.data.users.per_page;

    // Set dropdown options
    regions.value = response.data.regions;
    cohorts.value = response.data.cohorts || [];
    workStatusOptions.value = response.data.work_status_options;
    timedoctorVersions.value = response.data.timedoctor_versions;

  } catch (error) {
    console.error('Error fetching users:', error);
    snackbarText.value = 'Failed to load users';
    snackbarColor.value = 'error';
    snackbar.value = true;
  } finally {
    loading.value = false;
  }
}

function createUser() {
  router.push({ name: 'iva-user-create' });
}

function syncUsers() {
  router.push({ name: 'iva-user-sync' });
}

function calculateDailySummary() {
  router.push({ name: 'daily-worklog-summary-calculation' });
}

function viewUser(user) {
  router.push({ name: 'iva-user-detail', params: { id: user.id } });
}

function viewWorklogDashboard(user) {
  router.push({ name: 'iva-user-worklog-dashboard', params: { id: user.id } });
}

function viewTimeDoctorRecords(user) {
  router.push({ name: 'iva-user-timedoctor-records', params: { id: user.id } });
}

function handlePageChange(page) {
  pagination.value.page = page;
  fetchUsers();
}

function clearFilters() {
  filters.value = {
    search: '',
    region_id: null,
    cohort_id: null,
    work_status: null,
    timedoctor_version: null,
    is_active: null
  };
  pagination.value.page = 1;
  fetchUsers();
}

function formatDate(date) {
  if (!date) return 'N/A';
  return new Date(date).toLocaleDateString();
}

function getStatusColor(isActive) {
  return isActive ? 'success' : 'error';
}

function getStatusText(isActive) {
  return isActive ? 'Active' : 'Inactive';
}

function getTimeDoctorStatusColor(syncStatus) {
  if (!syncStatus.is_linked) return 'warning';
  return syncStatus.can_sync ? 'success' : 'info';
}

function getTimeDoctorStatusText(syncStatus) {
  if (!syncStatus.is_linked) return 'Not Linked';
  return 'Linked';
}

function getWorkStatusColor(workStatus) {
  switch (workStatus) {
    case 'full-time': return 'primary';
    case 'part-time': return 'secondary';
    default: return 'grey';
  }
}

function getCohortColor(cohort) {
  return 'info';
}

// Watch for filter changes with debounce
watch(() => filters.value.search, debounce(() => {
  pagination.value.page = 1;
  fetchUsers();
}, 500));

watch([
  () => filters.value.region_id,
  () => filters.value.cohort_id,
  () => filters.value.work_status,
  () => filters.value.timedoctor_version,
  () => filters.value.is_active
], () => {
  pagination.value.page = 1;
  fetchUsers();
});

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
      { title: 'IVA Users', disabled: true }
    ]" class="mb-6" aria-label="Breadcrumb navigation" />

    <VCard>
      <VCardText>
        <!-- Header -->
        <div class="d-flex flex-wrap align-center mb-6">
          <h1 class="text-h5 text-md-h4 mr-auto mb-2 mb-md-0" tabindex="0">
            IVA Users Management
          </h1>

          <div class="d-flex gap-2 flex-wrap">
            <VBtn color="info" prepend-icon="ri-refresh-line" :size="isMobile ? 'small' : 'default'" @click="syncUsers"
              aria-label="Sync IVA users from API">
              Sync IVA Users
            </VBtn>

            <VBtn color="warning" prepend-icon="ri-calculator-line" :size="isMobile ? 'small' : 'default'"
              @click="calculateDailySummary" aria-label="Calculate daily worklog summaries">
              Calculate Daily Summary
            </VBtn>

            <VBtn color="primary" prepend-icon="ri-add-line" :size="isMobile ? 'small' : 'default'" @click="createUser"
              aria-label="Create new IVA user">
              Create IVA User
            </VBtn>
          </div>
        </div>

        <VDivider class="mb-4" aria-hidden="true" />

        <!-- Filters -->
        <VExpansionPanels class="mb-6">
          <VExpansionPanel title="Filters & Search" expand-icon="ri-filter-line">
            <VExpansionPanelText>
              <VRow>
                <VCol cols="12" md="3">
                  <VTextField v-model="filters.search" label="Search users..." prepend-inner-icon="ri-search-line"
                    density="comfortable" variant="outlined" clearable placeholder="Search by name, email, or job title"
                    aria-label="Search users" />
                </VCol>

                <VCol cols="12" md="2">
                  <VSelect v-model="filters.region_id" :items="regions" item-title="name" item-value="id" label="Region"
                    density="comfortable" variant="outlined" clearable aria-label="Filter by region" />
                </VCol>

                <VCol cols="12" md="2">
                  <VSelect v-model="filters.cohort_id" :items="cohorts" item-title="name" item-value="id" label="Cohort"
                    density="comfortable" variant="outlined" clearable aria-label="Filter by cohort" />
                </VCol>

                <VCol cols="12" md="2">
                  <VSelect v-model="filters.work_status" :items="workStatusOptions" item-title="label"
                    item-value="value" label="Work Status" density="comfortable" variant="outlined" clearable
                    aria-label="Filter by work status" />
                </VCol>

                <VCol cols="12" md="1">
                  <VSelect v-model="filters.timedoctor_version" :items="timedoctorVersions" item-title="label"
                    item-value="value" label="TD Version" density="comfortable" variant="outlined" clearable
                    aria-label="Filter by TimeDoctor version" />
                </VCol>

                <VCol cols="12" md="2">
                  <VSelect v-model="filters.is_active" :items="statusOptions" item-title="title" item-value="value"
                    label="Status" density="comfortable" variant="outlined" clearable aria-label="Filter by status" />
                </VCol>
              </VRow>

              <div class="d-flex justify-end mt-4">
                <VBtn v-if="Object.values(filters).some(v => v !== null && v !== '')" color="secondary"
                  variant="outlined" prepend-icon="ri-filter-off-line" @click="clearFilters"
                  aria-label="Clear all filters">
                  Clear Filters
                </VBtn>
              </div>
            </VExpansionPanelText>
          </VExpansionPanel>
        </VExpansionPanels>

        <!-- Users Table -->
        <VDataTable :headers="headers" :items="users" :loading="loading" :items-per-page="pagination.perPage"
          hide-default-footer density="comfortable" hover class="elevation-1 rounded" aria-label="IVA users table">
          <!-- Full Name Column -->
          <template #[`item.full_name`]="{ item }">
            <div>
              <div class="font-weight-medium">{{ item.full_name }}</div>
              <div v-if="isMobile" class="text-caption text-secondary">
                {{ item.email }}
              </div>
              <div v-if="isMobile && item.job_title" class="text-caption">
                {{ item.job_title }}
              </div>
              <div v-if="isMobile && item.region" class="text-caption">
                {{ item.region.name }}
              </div>
              <div v-if="isMobile && item.cohort" class="text-caption">
                {{ item.cohort.name }}
              </div>
            </div>
          </template>

          <!-- Job Title Column (desktop only) -->
          <template v-if="!isMobile" #[`item.job_title`]="{ item }">
            <span v-if="item.job_title" class="text-body-2">{{ item.job_title }}</span>
            <span v-else class="text-secondary">No Job Title</span>
          </template>

          <!-- Region Column (desktop only) -->
          <template v-if="!isMobile" #[`item.region.name`]="{ item }">
            <VChip v-if="item.region" size="small" color="info" variant="outlined">
              {{ item.region.name }}
            </VChip>
            <span v-else class="text-secondary">No Region</span>
          </template>

          <!-- Cohort Column (desktop only) -->
          <template v-if="!isMobile" #[`item.cohort.name`]="{ item }">
            <VChip v-if="item.cohort" size="small" :color="getCohortColor(item.cohort)" variant="outlined">
              {{ item.cohort.name }}
            </VChip>
            <span v-else class="text-secondary">No Cohort</span>
          </template>

          <!-- Work Status Column (desktop only) -->
          <template v-if="!isMobile" #[`item.work_status`]="{ item }">
            <VChip v-if="item.work_status" size="small" :color="getWorkStatusColor(item.work_status)" variant="flat"
              text-color="white">
              {{ item.work_status }}
            </VChip>
            <span v-else class="text-secondary">Not Set</span>
          </template>

          <!-- TimeDoctor Version Column (desktop only) -->
          <template v-if="!isMobile" #[`item.timedoctor_version`]="{ item }">
            <VChip size="small" color="primary" variant="outlined">
              v{{ item.timedoctor_version }}
            </VChip>
          </template>

          <!-- TimeDoctor Status Column (desktop only) -->
          <template v-if="!isMobile" #[`item.timedoctor_sync_status`]="{ item }">
            <VChip size="small" :color="getTimeDoctorStatusColor(item.timedoctor_sync_status)" variant="flat"
              text-color="white"
              :aria-label="'TimeDoctor status: ' + getTimeDoctorStatusText(item.timedoctor_sync_status)">
              {{ getTimeDoctorStatusText(item.timedoctor_sync_status) }}
            </VChip>
          </template>

          <!-- Status Column -->
          <template #[`item.is_active`]="{ item }">
            <VChip size="small" :color="getStatusColor(item.is_active)" text-color="white"
              :aria-label="'User status: ' + getStatusText(item.is_active)">
              {{ getStatusText(item.is_active) }}
            </VChip>
          </template>

          <!-- Actions Column -->
          <template #[`item.actions`]="{ item }">
            <div class="d-flex justify-end" :class="isMobile ? 'flex-wrap gap-1' : 'gap-1'">
              <VBtn icon size="small" variant="text" color="primary" @click="viewUser(item)"
                aria-label="View user details">
                <VIcon size="20">ri-eye-line</VIcon>
                <VTooltip activator="parent">View Details</VTooltip>
              </VBtn>

              <VBtn icon size="small" variant="text" color="info" @click="viewWorklogDashboard(item)"
                aria-label="View worklog dashboard">
                <VIcon size="20">ri-dashboard-line</VIcon>
                <VTooltip activator="parent">Worklog Dashboard</VTooltip>
              </VBtn>

              <VBtn icon size="small" variant="text" color="secondary" @click="viewTimeDoctorRecords(item)"
                aria-label="View Time Doctor records">
                <VIcon size="20">ri-time-line</VIcon>
                <VTooltip activator="parent">Time Doctor Records</VTooltip>
              </VBtn>
            </div>
          </template>

          <!-- Empty state -->
          <template #no-data>
            <div class="d-flex flex-column align-center pa-6" role="alert" aria-live="polite">
              <VIcon size="48" color="secondary" icon="ri-user-line" class="mb-4" aria-hidden="true" />
              <h3 class="text-h6 font-weight-regular mb-2">No users found</h3>
              <p class="text-secondary text-center mb-4">
                <span v-if="Object.values(filters).some(v => v !== null && v !== '')">
                  No users match your current filters. Try adjusting your search criteria.
                </span>
                <span v-else>
                  Get started by creating your first IVA user or syncing from the API.
                </span>
              </p>
              <div class="d-flex gap-2 flex-wrap">
                <VBtn v-if="Object.values(filters).some(v => v !== null && v !== '')" color="secondary"
                  @click="clearFilters" aria-label="Clear all filters">
                  Clear Filters
                </VBtn>
                <VBtn color="info" @click="syncUsers" aria-label="Sync users from API">
                  Sync Users
                </VBtn>
                <VBtn color="warning" prepend-icon="mdi-function" @click="calculateDailySummary"
                  aria-label="Calculate daily worklog summaries">
                  Calculate Daily Summary
                </VBtn>
                <VBtn color="primary" @click="createUser" aria-label="Create first user">
                  Create IVA User
                </VBtn>
              </div>
            </div>
          </template>
        </VDataTable>

        <!-- Pagination -->
        <div class="d-flex justify-center justify-md-between align-center mt-4">
          <div v-if="!isMobile" class="text-sm text-secondary">
            Showing {{ users.length }} of {{ pagination.total }} users
          </div>

          <VPagination v-model="pagination.page" :length="Math.ceil(pagination.total / pagination.perPage)"
            :total-visible="isMobile ? 3 : 7" @update:model-value="handlePageChange" aria-label="Pagination" />
        </div>
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
}

/* Improve table readability */
:deep(.v-data-table-rows-no-data) {
  padding: 2rem;
  text-align: center;
}

/* Ensure proper chip sizing */
:deep(.v-chip) {
  font-size: 0.75rem;
}
</style>
