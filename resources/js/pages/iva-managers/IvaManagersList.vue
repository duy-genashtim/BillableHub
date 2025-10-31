<script setup>
import { useAuthStore } from '@/@core/stores/auth';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

const authStore = useAuthStore();
const router = useRouter();
const managers = ref([]);
const regions = ref([]);
const managerTypes = ref([]);
const loading = ref(true);
const searchQuery = ref('');
const selectedRegionId = ref(null);
const selectedManagerTypeId = ref(null);
const isMobile = ref(window.innerWidth < 768);
const regionFilter = ref({ applied: false, region_id: null, reason: null });

const deleteDialog = ref(false);
const managerToDelete = ref(null);
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

// Pagination
const page = ref(1);
const itemsPerPage = ref(15);
const totalItems = ref(0);

// Headers for data table with responsive design
const headers = computed(() => {
  if (isMobile.value) {
    return [
      { title: 'Manager', key: 'manager.full_name', sortable: true },
      { title: 'Region', key: 'region.name', sortable: true },
      { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
    ];
  } else {
    return [
      { title: 'Manager', key: 'manager.full_name', sortable: true },
      { title: 'Email', key: 'manager.email', sortable: true },
      { title: 'Region', key: 'region.name', sortable: true },
      { title: 'Type', key: 'manager_type.setting_value', sortable: true },
      { title: 'Employees', key: 'managed_users_count', sortable: true },
      { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
    ];
  }
});

onMounted(() => {
  fetchManagers();
  window.addEventListener('resize', handleResize);
});

function handleResize() {
  isMobile.value = window.innerWidth < 768;
}

async function fetchManagers() {
  loading.value = true;
  try {
    const params = {
      page: page.value,
      per_page: itemsPerPage.value,
    };

    if (selectedRegionId.value) {
      params.region_id = selectedRegionId.value;
    }
    if (selectedManagerTypeId.value) {
      params.manager_type_id = selectedManagerTypeId.value;
    }

    const response = await axios.get('/api/admin/iva-managers', { params });
    managers.value = response.data.managers.data;
    totalItems.value = response.data.managers.total;
    page.value = response.data.managers.current_page;
    regions.value = response.data.regions;
    managerTypes.value = response.data.managerTypes;

    // Handle region filter from backend
    if (response.data.region_filter) {
      regionFilter.value = response.data.region_filter;
    }
  } catch (error) {
    console.error('Error fetching managers:', error);
    snackbarText.value = 'Failed to load managers';
    snackbarColor.value = 'error';
    snackbar.value = true;
  } finally {
    loading.value = false;
  }
}

function viewManager(manager) {
  router.push({ name: 'iva-manager-detail', params: { id: manager.id } });
}

function addNewManager() {
  router.push({ name: 'iva-manager-assign' });
}

function confirmDelete(manager) {
  managerToDelete.value = manager;
  deleteDialog.value = true;
}

async function deleteManager() {
  try {
    await axios.delete(`/api/admin/iva-managers/${managerToDelete.value.id}`);

    snackbarText.value = 'Manager assignment removed successfully';
    snackbarColor.value = 'success';
    snackbar.value = true;
    fetchManagers();
  } catch (error) {
    console.error('Error removing manager assignment:', error);
    snackbarText.value = 'Failed to remove manager assignment';
    snackbarColor.value = 'error';
    snackbar.value = true;
  } finally {
    deleteDialog.value = false;
    managerToDelete.value = null;
  }
}

function filterByRegion() {
  page.value = 1;
  fetchManagers();
}

function filterByManagerType() {
  page.value = 1;
  fetchManagers();
}

function resetFilters() {
  selectedRegionId.value = null;
  selectedManagerTypeId.value = null;
  searchQuery.value = '';
  page.value = 1;
  fetchManagers();
}

function onPageChange(newPage) {
  page.value = newPage;
  fetchManagers();
}

const regionFilteredRegionName = computed(() => {
  if (!regionFilter.value.applied) return null;
  // Find the region from regions array
  const region = regions.value.find(r => r.id === regionFilter.value.region_id);
  return region ? region.name : 'your region';
});

// Computed properties for filtering
const filteredManagers = computed(() => {
  if (!searchQuery.value || !managers.value) return managers.value;

  const query = searchQuery.value.toLowerCase();
  return managers.value.filter(manager =>
    (manager.manager && manager.manager.full_name && manager.manager.full_name.toLowerCase().includes(query)) ||
    (manager.manager && manager.manager.email && manager.manager.email.toLowerCase().includes(query)) ||
    (manager.region && manager.region.name && manager.region.name.toLowerCase().includes(query)) ||
    (manager.manager_type && manager.manager_type.setting_value && manager.manager_type.setting_value.toLowerCase().includes(query))
  );
});
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <VBreadcrumbs :items="[
      { title: 'Home', to: '/' },
      { title: 'IVA Managers', disabled: true }
    ]" class="mb-6" aria-label="Breadcrumb navigation" />

    <!-- Region Filter Notice -->
    <VAlert v-if="regionFilter.applied" type="info" variant="tonal" class="mb-6">
      <VAlertTitle class="d-flex align-center">
        <VIcon icon="ri-information-line" class="me-2" />
        Filtered View
      </VAlertTitle>
      <p class="mb-0">
        You are viewing IVA managers from <strong>{{ regionFilteredRegionName }}</strong> only, based on your permissions.
      </p>
    </VAlert>

    <VCard>
      <VCardText>
        <div class="d-flex flex-wrap align-center mb-6">
          <h1 class="text-h5 text-md-h4 mr-auto mb-2 mb-md-0" tabindex="0">
            IVA Managers
          </h1>
          <VBtn v-if="authStore.hasPermission('edit_iva_data')" color="primary" prepend-icon="ri-user-add-line" :size="isMobile ? 'small' : 'default'"
            @click="addNewManager" aria-label="Assign new manager">
            <span v-if="!isMobile">Assign New Manager</span>
            <span v-else>Assign</span>
          </VBtn>
        </div>

        <!-- Filters -->
        <div class="d-flex flex-column flex-md-row flex-wrap align-md-center mb-6 gap-2">
          <div class="filter-container flex-grow-1 flex-md-grow-0 mb-2 mb-md-0">
            <VSelect v-model="selectedRegionId" :items="regions" item-title="name" item-value="id"
              label="Filter by Region" clearable hide-details density="compact" class="max-width-300"
              @update:model-value="filterByRegion" aria-label="Filter by region">
              <template v-slot:prepend-inner>
                <VIcon size="small">ri-map-pin-line</VIcon>
              </template>
            </VSelect>
          </div>

          <div class="filter-container flex-grow-1 flex-md-grow-0 ml-0 ml-md-2 mb-2 mb-md-0">
            <VSelect v-model="selectedManagerTypeId" :items="managerTypes" item-title="setting_value" item-value="id"
              label="Filter by Type" clearable hide-details density="compact" class="max-width-300"
              @update:model-value="filterByManagerType" aria-label="Filter by manager type">
              <template v-slot:prepend-inner>
                <VIcon size="small">ri-filter-line</VIcon>
              </template>
            </VSelect>
          </div>

          <VSpacer class="d-none d-md-block" />

          <VTextField v-model="searchQuery" density="compact" placeholder="Search managers..."
            prepend-inner-icon="ri-search-line" hide-details class="flex-grow-1 max-width-400" single-line
            aria-label="Search for managers" />
        </div>

        <VDataTableServer v-model:page="page" :headers="headers" :items="filteredManagers" :items-length="totalItems"
          :items-per-page="itemsPerPage" :loading="loading" density="comfortable" hover class="elevation-1 rounded"
          aria-label="Managers table" @update:page="onPageChange">
          <!-- Manager Column -->
          <template #[`item.manager.full_name`]="{ item }">
            <div class="font-weight-medium">
              {{ item.manager ? item.manager.full_name : 'Unknown' }}
              <div v-if="isMobile" class="d-flex align-center flex-wrap gap-1 mt-1">
                <!-- On mobile, show manager type and count here -->
                <VChip size="x-small" color="primary" variant="flat" class="mr-1">
                  {{ item.manager_type ? item.manager_type.setting_value : 'Unknown' }}
                </VChip>
                <VChip size="x-small" color="info" variant="flat">
                  {{ item.managed_users_count }} employees
                </VChip>
              </div>
            </div>
          </template>

          <!-- Email Column (desktop only) -->
          <template v-if="!isMobile" #[`item.manager.email`]="{ item }">
            <div>{{ item.manager ? item.manager.email : '' }}</div>
          </template>

          <!-- Region Column -->
          <template #[`item.region.name`]="{ item }">
            <VChip size="small" color="secondary" variant="flat">
              {{ item.region ? item.region.name : 'Unknown' }}
            </VChip>
          </template>

          <!-- Manager Type Column (desktop only) -->
          <template v-if="!isMobile" #[`item.manager_type.setting_value`]="{ item }">
            <VChip size="small" color="primary" variant="flat">
              {{ item.manager_type ? item.manager_type.setting_value : 'Unknown' }}
            </VChip>
          </template>

          <!-- Employee Count Column (desktop only) -->
          <template v-if="!isMobile" #[`item.managed_users_count`]="{ item }">
            <div class="text-center">
              {{ item.managed_users_count }}
            </div>
          </template>

          <!-- Actions Column -->
          <template #[`item.actions`]="{ item }">
            <div class="d-flex justify-end">
              <VBtn icon size="small" variant="text" color="primary" class="me-1" @click="viewManager(item)"
                aria-label="View manager details">
                <VIcon size="20">ri-eye-line</VIcon>
                <VTooltip activator="parent">View Details</VTooltip>
              </VBtn>

              <VBtn v-if="authStore.hasPermission('edit_iva_data')" icon size="small" variant="text" color="error" @click="confirmDelete(item)"
                aria-label="Remove manager assignment">
                <VIcon size="20">ri-delete-bin-line</VIcon>
                <VTooltip activator="parent">Remove</VTooltip>
              </VBtn>
            </div>
          </template>

          <!-- Empty state -->
          <template #no-data>
            <div class="d-flex flex-column align-center pa-6" role="alert" aria-live="polite">
              <VIcon size="48" color="secondary" icon="ri-user-settings-line" class="mb-4" aria-hidden="true" />
              <h3 class="text-h6 font-weight-regular mb-2">No managers found</h3>
              <p class="text-secondary text-center mb-4">
                <span v-if="selectedRegionId || selectedManagerTypeId || searchQuery">Try changing your filters.</span>
                <span v-else>There are no managers assigned. Assign a manager to get started.</span>
              </p>
              <div class="d-flex gap-2 flex-wrap">
                <VBtn v-if="selectedRegionId || selectedManagerTypeId || searchQuery" color="secondary"
                  @click="resetFilters" aria-label="Clear all filters">
                  Clear Filters
                </VBtn>
                <VBtn v-if="authStore.hasPermission('edit_iva_data')" color="primary" @click="addNewManager" aria-label="Assign a new manager">
                  Assign Manager
                </VBtn>
              </div>
            </div>
          </template>
        </VDataTableServer>
      </VCardText>
    </VCard>

    <!-- Confirm Delete Dialog -->
    <VDialog v-model="deleteDialog" max-width="500" role="alertdialog" aria-labelledby="delete-manager-title">
      <VCard>
        <VCardTitle id="delete-manager-title" class="text-h5 bg-error text-white d-flex align-center py-3">
          <span>Remove Manager Assignment</span>
        </VCardTitle>

        <VCardText class="pt-4">
          <p>Are you sure you want to remove this manager assignment?</p>
          <div v-if="managerToDelete" class="my-3 pa-3 bg-grey-lighten-4 rounded">
            <p class="mb-1"><strong>Manager:</strong> {{ managerToDelete.manager ? managerToDelete.manager.full_name :
              'Unknown' }}</p>
            <p class="mb-1"><strong>Type:</strong> {{ managerToDelete.manager_type ?
              managerToDelete.manager_type.setting_value : 'Unknown' }}</p>
            <p class="mb-0"><strong>Region:</strong> {{ managerToDelete.region ? managerToDelete.region.name : 'Unknown'
              }}
            </p>
          </div>
          <p class="mt-2 text-body-2 text-error">
            This action will remove all user assignments for this manager in this region with this manager type.
            This action cannot be undone.
          </p>
        </VCardText>

        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="deleteDialog = false" aria-label="Cancel deletion">
            Cancel
          </VBtn>
          <VBtn color="error" @click="deleteManager" aria-label="Confirm deletion">
            Remove Assignment
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

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
.max-width-300 {
  max-inline-size: 300px;
}

.max-width-400 {
  max-inline-size: 400px;
}

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

  /* Full width on mobile */
  .max-width-300,
  .max-width-400 {
    max-inline-size: 100%;
  }

  /* Adjust spacing for small screens */
  .mb-6 {
    margin-block-end: 16px !important;
  }

  .mb-4 {
    margin-block-end: 12px !important;
  }
}
</style>
