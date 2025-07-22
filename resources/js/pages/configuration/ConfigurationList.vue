<script setup>
import {
  SETTING_CATEGORIES
} from '@/@core/utils/siteConsts';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
// Data
const settings = ref([]);
const types = ref([]);
const categories = ref(SETTING_CATEGORIES);
const currentTypeId = ref(null);
const currentCategory = ref(null);
const loading = ref(true);
const router = useRouter();
const route = useRoute();
const searchQuery = ref('');
const deleteDialog = ref(false);
const settingToDelete = ref(null);
const statusDialog = ref(false);
const settingToToggle = ref(null);
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');
const isMobile = ref(window.innerWidth < 768);

// Watch for changes in the route query params
watch(() => route.query, (newQuery) => {
  if (newQuery.type_id) {
    currentTypeId.value = parseInt(newQuery.type_id);
    fetchSettings();
  }
}, { immediate: true });

// Headers for data table
const headers = computed(() => {
  // Base headers that will always be shown
  const baseHeaders = [
    { title: 'Value', key: 'setting_value', sortable: true },
    { title: 'Type', key: 'setting_type_id', sortable: true },
    { title: 'Category', key: 'category', sortable: true },
    { title: 'Status', key: 'is_active', sortable: true },
    { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
  ];

  // Additional headers for desktop view
  if (!isMobile.value) {
    baseHeaders.splice(3, 0, { title: 'Order', key: 'order', sortable: true });
  }

  return baseHeaders;
});

// Watch for window resize to update isMobile
onMounted(() => {
  window.addEventListener('resize', handleResize);
  fetchTypes().then(fetchSettings);
});

function handleResize() {
  isMobile.value = window.innerWidth < 768;
}

// Methods
async function fetchTypes() {
  try {
    const response = await axios.get('/api/configuration/types');
    types.value = response.data.types;
  } catch (error) {
    console.error('Error fetching setting types:', error);
    snackbarText.value = 'Failed to load setting types';
    snackbarColor.value = 'error';
    snackbar.value = true;
  }
}

async function fetchSettings() {
  loading.value = true;
  try {
    const params = {};
    if (currentTypeId.value) {
      params.type_id = currentTypeId.value;
    }

    const response = await axios.get('/api/configuration', { params });
    settings.value = response.data.settings;
  } catch (error) {
    console.error('Error fetching settings:', error);
    snackbarText.value = 'Failed to load settings';
    snackbarColor.value = 'error';
    snackbar.value = true;
  } finally {
    loading.value = false;
  }
}

function filterByType(typeId) {
  currentTypeId.value = typeId;
  // Update URL with the selected type_id without navigating
  router.replace({ query: { ...route.query, type_id: typeId || undefined } });
  fetchSettings();
}

function filterByCategory(category) {
  currentCategory.value = category;
  // Reset type filter when changing category
  currentTypeId.value = null;
  router.replace({ query: {} });
  // No need to fetch settings here as we'll filter locally
}

function editSetting(setting) {
  router.push({ name: 'configuration-edit', params: { id: setting.id } });
}

function viewSetting(setting) {
  router.push({ name: 'configuration-detail', params: { id: setting.id } });
}

function confirmStatusChange(setting) {
  settingToToggle.value = setting;
  statusDialog.value = true;
}

function confirmDelete(setting) {
  settingToDelete.value = setting;
  deleteDialog.value = true;
}

async function toggleStatus() {
  try {
    await axios.put(`/api/configuration/${settingToToggle.value.id}/toggle-status`);

    snackbarText.value = `Setting status changed to ${!settingToToggle.value.is_active ? 'active' : 'inactive'} successfully`;
    snackbarColor.value = 'success';
    snackbar.value = true;
    fetchSettings();
  } catch (error) {
    console.error('Error changing setting status:', error);
    snackbarText.value = 'Failed to change setting status';
    snackbarColor.value = 'error';
    snackbar.value = true;
  } finally {
    statusDialog.value = false;
    settingToToggle.value = null;
  }
}

async function deleteSetting() {
  try {
    await axios.delete(`/api/configuration/${settingToDelete.value.id}`);

    snackbarText.value = 'Setting deleted successfully';
    snackbarColor.value = 'success';
    snackbar.value = true;
    fetchSettings();
  } catch (error) {
    console.error('Error deleting setting:', error);

    // Special handling for system settings that can't be deleted
    if (error.response && error.response.status === 403) {
      snackbarText.value = 'System settings cannot be deleted';
    } else {
      snackbarText.value = 'Failed to delete setting';
    }

    snackbarColor.value = 'error';
    snackbar.value = true;
  } finally {
    deleteDialog.value = false;
    settingToDelete.value = null;
  }
}

const filteredTypes = computed(() => {
  if (!currentCategory.value) return types.value;

  return types.value.filter(type => type.setting_category === currentCategory.value);
});

const filteredSettings = computed(() => {
  let filtered = settings.value;

  // Filter by category if selected
  if (currentCategory.value) {
    const typeIdsInCategory = types.value
      .filter(type => type.setting_category === currentCategory.value)
      .map(type => type.id);

    filtered = filtered.filter(setting => typeIdsInCategory.includes(setting.setting_type_id));
  }

  // Filter by type if selected (applied at the API level)

  // Filter by search query
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(setting => {
      const typeName = types.value.find(t => t.id === setting.setting_type_id)?.name || '';

      return setting.setting_value.toLowerCase().includes(query) ||
        typeName.toLowerCase().includes(query);
    });
  }

  return filtered;
});

function getStatusColor(isActive) {
  return isActive ? 'success' : 'error';
}

function getStatusText(isActive) {
  return isActive ? 'Active' : 'Inactive';
}

function getTypeName(typeId) {
  const type = types.value.find(t => t.id === typeId);
  return type ? type.name : 'Unknown Type';
}

function getCategoryName(typeId) {
  const type = types.value.find(t => t.id === typeId);
  if (!type) return 'Unknown';

  return categories.value[type.setting_category] || type.setting_category;
}

function getCategoryColor(category) {
  switch (category) {
    case 'site': return 'primary';
    case 'user': return 'success';
    case 'report': return 'warning';
    case 'report-time': return 'info';
    case 'report-cat': return 'secondary';
    case 'system': return 'error';
    default: return 'secondary';
  }
}

function getSettingCategory(typeId) {
  const type = types.value.find(t => t.id === typeId);
  return type ? type.setting_category : 'other';
}

function addNewSetting() {
  router.push({
    name: 'configuration-create',
    query: { type_id: currentTypeId.value }
  });
}

function addNewType() {
  router.push({
    name: 'configuration-type-create',
    query: { category: currentCategory.value }
  });
}

// function viewLogs() {
//   router.push({ name: 'configuration-logs' });
// }

function manageTypes() {
  router.push({ name: 'configuration-types' });
}

// Reset filters
function resetFilters() {
  currentCategory.value = null;
  currentTypeId.value = null;
  searchQuery.value = '';
  router.replace({ query: {} });
}
</script>

<template>
  <!-- Breadcrumbs -->
  <VBreadcrumbs :items="[
    { title: 'Home', to: '/' },
    { title: 'Configuration Settings', disabled: true }
  ]" class="mb-6" />

  <VCard>
    <VCardText>
      <div class="d-flex flex-wrap align-center mb-6 gap-2">
        <h1 class="text-h5 text-md-h4 mr-auto mb-2 mb-md-0">
          Configuration Settings
        </h1>
        <div class="d-flex flex-wrap gap-2">
          <!-- <VBtn
            color="info"
            prepend-icon="ri-history-line"
            :size="isMobile ? 'small' : 'default'"
            @click="viewLogs"
          >
            <span v-if="!isMobile">View Logs</span>
            <span v-else>Logs</span>
          </VBtn> -->
          <VBtn color="secondary" prepend-icon="ri-list-settings-line" :size="isMobile ? 'small' : 'default'"
            @click="manageTypes">
            <span v-if="!isMobile">Manage Types</span>
            <span v-else>Types</span>
          </VBtn>
          <VBtn color="primary" prepend-icon="ri-add-line" :size="isMobile ? 'small' : 'default'"
            @click="addNewSetting">
            <span v-if="!isMobile">Add Setting</span>
            <span v-else>Add</span>
          </VBtn>
        </div>
      </div>

      <!-- Category and Type filters -->
      <div class="d-flex flex-column flex-md-row flex-wrap align-md-center mb-6 gap-2">
        <div class="filter-container flex-grow-1 flex-md-grow-0 mb-2 mb-md-0">
          <VSelect v-model="currentCategory"
            :items="Object.entries(categories).map(([value, title]) => ({ title, value }))" item-title="title"
            item-value="value" label="Filter by Category" clearable hide-details density="compact" class="max-width-300"
            @update:model-value="filterByCategory">
            <template v-slot:prepend-inner>
              <VIcon size="small">ri-folder-line</VIcon>
            </template>
          </VSelect>
        </div>

        <div class="filter-container flex-grow-1 flex-md-grow-0 ml-0 ml-md-2 mb-2 mb-md-0">
          <VSelect v-model="currentTypeId" :items="filteredTypes.map(type => ({ title: type.name, value: type.id }))"
            item-title="title" item-value="value" label="Filter by Type" clearable hide-details density="compact"
            class="max-width-300" @update:model-value="filterByType">
            <template v-slot:prepend-inner>
              <VIcon size="small">ri-filter-line</VIcon>
            </template>
          </VSelect>
        </div>

        <VSpacer class="d-none d-md-block" />

        <VTextField v-model="searchQuery" density="compact" placeholder="Search settings..."
          prepend-inner-icon="ri-search-line" hide-details class="flex-grow-1 max-width-400 ml-0 ml-md-2" single-line />
      </div>

      <VDataTable :headers="headers" :items="filteredSettings" :loading="loading" density="comfortable" hover
        class="elevation-1 rounded">
        <!-- Setting Value Column -->
        <template #[`item.setting_value`]="{ item }">
          <div class="font-weight-medium text-break">
            {{ item.setting_value }}
          </div>
        </template>

        <!-- Type Column -->
        <template #[`item.setting_type_id`]="{ item }">
          <VChip size="small" color="primary" variant="flat" class="text-truncate">
            {{ getTypeName(item.setting_type_id) }}
          </VChip>
        </template>

        <!-- Category Column -->
        <template #[`item.category`]="{ item }">
          <VChip size="small" :color="getCategoryColor(getSettingCategory(item.setting_type_id))" variant="flat"
            class="text-truncate">
            {{ getCategoryName(item.setting_type_id) }}
          </VChip>
        </template>

        <!-- Order Column (only on desktop) -->
        <template #[`item.order`]="{ item }">
          <div class="text-center">
            {{ item.order }}
          </div>
        </template>

        <!-- Status Column -->
        <template #[`item.is_active`]="{ item }">
          <VChip size="small" :color="getStatusColor(item.is_active)" text-color="white">
            {{ getStatusText(item.is_active) }}
          </VChip>
        </template>

        <!-- Actions Column -->
        <template #[`item.actions`]="{ item }">
          <div class="d-flex justify-end">
            <VBtn v-if="!isMobile" icon size="small" variant="text" color="primary" class="me-1"
              @click="viewSetting(item)">
              <VIcon size="20">ri-eye-line</VIcon>
              <VTooltip activator="parent">View Details</VTooltip>
            </VBtn>

            <VBtn v-if="!isMobile && item.setting_type && item.setting_type.allow_edit" icon size="small" variant="text"
              color="secondary" class="me-1" @click="editSetting(item)">
              <VIcon size="20">ri-pencil-line</VIcon>
              <VTooltip activator="parent">Edit</VTooltip>
            </VBtn>

            <VBtn icon size="small" variant="text" :color="item.is_active ? 'error' : 'success'" class="me-1"
              @click="confirmStatusChange(item)">
              <VIcon size="20">{{ item.is_active ? 'ri-close-circle-line' : 'ri-checkbox-circle-line' }}</VIcon>
              <VTooltip activator="parent">{{ item.is_active ? 'Deactivate' : 'Activate' }}</VTooltip>
            </VBtn>

            <!-- Only show delete button for non-system settings that are allowed to be deleted -->
            <VBtn v-if="!item.is_system && item.setting_type && item.setting_type.allow_delete" icon size="small"
              variant="text" color="error" @click="confirmDelete(item)">
              <VIcon size="20">ri-delete-bin-line</VIcon>
              <VTooltip activator="parent">Delete</VTooltip>
            </VBtn>

            <!-- On mobile, use a menu for actions to save space -->
            <VMenu v-if="isMobile">
              <template v-slot:activator="{ props }">
                <VBtn icon size="small" variant="text" color="secondary" v-bind="props">
                  <VIcon size="20">ri-more-2-fill</VIcon>
                </VBtn>
              </template>
              <VList density="compact">
                <VListItem @click="viewSetting(item)">
                  <template v-slot:prepend>
                    <VIcon size="small">ri-eye-line</VIcon>
                  </template>
                  <VListItemTitle>View Details</VListItemTitle>
                </VListItem>

                <VListItem v-if="item.setting_type && item.setting_type.allow_edit" @click="editSetting(item)">
                  <template v-slot:prepend>
                    <VIcon size="small">ri-pencil-line</VIcon>
                  </template>
                  <VListItemTitle>Edit</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
          </div>
        </template>

        <!-- Empty state -->
        <template #no-data>
          <div class="d-flex flex-column align-center pa-6">
            <VIcon size="48" color="secondary" icon="ri-settings-3-line" class="mb-4" />
            <h3 class="text-h6 font-weight-regular mb-2">No settings found</h3>
            <p class="text-secondary text-center mb-4">
              There are no settings matching your criteria.
              <span v-if="currentTypeId || currentCategory || searchQuery">Try changing your filters.</span>
              <span v-else>Create one to get started.</span>
            </p>
            <div class="d-flex gap-2 flex-wrap justify-center">
              <VBtn v-if="currentTypeId || currentCategory || searchQuery" color="secondary" @click="resetFilters">
                Clear Filters
              </VBtn>
              <VBtn color="secondary" @click="addNewType">
                Add Setting Type
              </VBtn>
              <VBtn color="primary" @click="addNewSetting">
                Add New Setting
              </VBtn>
            </div>
          </div>
        </template>
      </VDataTable>
    </VCardText>
  </VCard>

  <!-- Confirm Status Change Dialog -->
  <VDialog v-model="statusDialog" max-width="500">
    <VCard>
      <VCardTitle class="text-h5">
        {{ settingToToggle && settingToToggle.is_active ? 'Confirm Deactivation' : 'Confirm Activation' }}
      </VCardTitle>

      <VCardText>
        <template v-if="settingToToggle && settingToToggle.is_active">
          Are you sure you want to deactivate this setting?
          <strong>{{ settingToToggle.setting_value }}</strong>
          <p class="mt-2 text-body-2">
            This action will mark the setting as inactive. You can reactivate it later if needed.
          </p>
        </template>
        <template v-else-if="settingToToggle">
          Are you sure you want to activate this setting?
          <strong>{{ settingToToggle.setting_value }}</strong>
          <p class="mt-2 text-body-2">
            This action will make the setting active again.
          </p>
        </template>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn color="secondary" variant="outlined" @click="statusDialog = false">
          Cancel
        </VBtn>
        <VBtn :color="settingToToggle && settingToToggle.is_active ? 'error' : 'success'" @click="toggleStatus">
          {{ settingToToggle && settingToToggle.is_active ? 'Deactivate' : 'Activate' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Confirm Delete Dialog -->
  <VDialog v-model="deleteDialog" max-width="500">
    <VCard>
      <VCardTitle class="text-h5 bg-error text-white d-flex align-center py-3">
        <span>Delete Setting</span>
      </VCardTitle>

      <VCardText class="pt-4">
        <p>Are you sure you want to delete this setting?</p>
        <p v-if="settingToDelete" class="font-weight-bold">{{ settingToDelete.setting_value }}</p>
        <p class="mt-2 text-body-2 text-error">
          This action cannot be undone. The setting will be permanently removed from the system.
        </p>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn color="secondary" variant="outlined" @click="deleteDialog = false">
          Cancel
        </VBtn>
        <VBtn color="error" @click="deleteSetting">
          Delete
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Snackbar for notifications -->
  <VSnackbar v-model="snackbar" :color="snackbarColor" :timeout="3000">
    {{ snackbarText }}
    <template #actions>
      <VBtn icon variant="text" @click="snackbar = false">
        <VIcon>ri-close-line</VIcon>
      </VBtn>
    </template>
  </VSnackbar>
</template>

<style scoped>
.max-width-300 {
  max-inline-size: 300px;
}

.max-width-400 {
  max-inline-size: 400px;
}

@media (max-width: 767px) {

  .max-width-300,
  .max-width-400 {
    max-inline-size: 100%;
  }

  /* Add responsive spacing */
  :deep(.v-card-text) {
    padding-block: 16px;
    padding-inline: 12px;
  }

  /* Make table more compact on mobile */
  :deep(.v-data-table) {
    font-size: 0.85rem;
  }
}
</style>
