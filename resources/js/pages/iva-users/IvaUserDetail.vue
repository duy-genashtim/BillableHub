<script setup>
import { formatDateTime, safeJsonParse } from '@/@core/utils/helpers';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import IvaUserForm from './IvaUserForm.vue';

const route = useRoute();
const router = useRouter();
const userId = route.params.id;

const props = defineProps({
  isEditMode: {
    type: Boolean,
    default: false
  }
});

// Data
const user = ref(null);
const regions = ref([]);
const cohorts = ref([]); // Fixed: Changed from batches to cohorts
const workStatusOptions = ref([]);
const timedoctorVersions = ref([]);
const customizationTypes = ref([]);
const managerTypes = ref([]);
const logTypes = ref([]);
const timeDoctorSyncStatus = ref(null);
const loading = ref(true);
const saving = ref(false);
const isMobile = ref(window.innerWidth < 768);
const isEditing = ref(props.isEditMode);

// Form data
const editForm = ref({});
const errors = ref({});
const changeReason = ref('');
const regionChangeInfo = ref({});
const cohortChangeInfo = ref({}); // Fixed: Changed from batchChangeInfo
const workStatusChangeInfo = ref({});

// UI state
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');
const activeTab = ref('overview');

// Dialog states
const managerDialog = ref(false);
const customizationDialog = ref(false);
const logDialog = ref(false);
const syncDialog = ref(false);
const deleteManagerDialog = ref(false);
const deleteCustomizationDialog = ref(false);
const deleteLogDialog = ref(false);

// Items to delete
const managerToDelete = ref(null);
const customizationToDelete = ref(null);
const logToDelete = ref(null);

// Manager form
const managerForm = ref({
  manager_id: null,
  manager_type_id: null,
  region_id: null
});

// Customization form
const customizationForm = ref({
  setting_id: null,
  custom_value: '',
  start_date: null,
  end_date: null
});

// Log form
const logForm = ref({
  log_type: 'note',
  title: '',
  content: '',
  is_private: false
});

// Available managers
const availableManagers = ref([]);

// Log management
const logs = ref([]);
const logsPagination = ref({
  page: 1,
  total: 0,
  perPage: 20
});
const logsFilters = ref({
  log_type: null,
  is_private: null
});
const logsLoading = ref(false);
const editingLog = ref(null);

// Computed property for available settings for customization
const availableCustomizationSettings = computed(() => {
  if (!customizationTypes.value) return [];

  // Get all existing customization setting IDs for this user
  const existingSettingIds = user.value?.customizations?.map(c => c.setting_id) || [];

  return customizationTypes.value.flatMap(type =>
    type.settings
      .filter(setting => !existingSettingIds.includes(setting.id)) // Only show settings not already customized
      .map(setting => ({
        value: setting.id,
        title: `${type.name}: ${setting.setting_value}`,
        subtitle: setting.description
      }))
  );
});

// Computed property for filtered available managers
const filteredAvailableManagers = computed(() => {
  if (!availableManagers.value || !user.value) return [];

  return availableManagers.value.filter(manager =>
    // Only show managers from the same region as the user
    manager.region_id === user.value.region_id &&
    // Exclude the current user (they can't be their own manager)
    manager.id !== user.value.id
  );
});

const tabs = computed(() => [
  { key: 'overview', title: 'Overview', icon: 'ri-user-line' },
  { key: 'managers', title: 'Managers', icon: 'ri-team-line' },
  { key: 'customizations', title: 'Settings', icon: 'ri-settings-line' },
  { key: 'logs', title: 'Logs', icon: 'ri-file-text-line' },
  { key: 'changelog', title: 'History', icon: 'ri-history-line' },
  { key: 'timedoctor', title: 'TimeDoctor', icon: 'ri-time-line' }
]);

onMounted(() => {
  fetchUserDetails();
  window.addEventListener('resize', handleResize);
});

function handleResize() {
  isMobile.value = window.innerWidth < 768;
}

async function fetchUserDetails() {
  loading.value = true;

  try {
    const response = await axios.get(`/api/admin/iva-users/${userId}`);

    user.value = response.data.user;
    regions.value = response.data.regions;
    cohorts.value = response.data.cohorts; // Fixed: Changed from batches to cohorts
    workStatusOptions.value = response.data.workStatusOptions;
    timedoctorVersions.value = response.data.timedoctorVersions;
    customizationTypes.value = response.data.customizationTypes;
    managerTypes.value = response.data.managerTypes;
    logTypes.value = response.data.logTypes;
    timeDoctorSyncStatus.value = response.data.timeDoctorSyncStatus;

    // Initialize edit form
    resetEditForm();

    // Load logs if on logs tab
    if (activeTab.value === 'logs') {
      await fetchLogs();
    }

  } catch (error) {
    console.error('Error fetching user details:', error);
    snackbarText.value = 'Failed to load user details';
    snackbarColor.value = 'error';
    snackbar.value = true;
    router.push({ name: 'iva-users-list' });
  } finally {
    loading.value = false;
  }
}

async function fetchLogs() {
  logsLoading.value = true;

  try {
    const params = {
      page: logsPagination.value.page,
      per_page: logsPagination.value.perPage,
      ...logsFilters.value
    };

    // Remove null/empty filters
    Object.keys(params).forEach(key => {
      if (params[key] === null || params[key] === '') {
        delete params[key];
      }
    });

    const response = await axios.get(`/api/admin/iva-users/${userId}/logs`, { params });

    logs.value = response.data.logs.data;
    logsPagination.value.total = response.data.logs.total;
    logsPagination.value.page = response.data.logs.current_page;
    logsPagination.value.perPage = response.data.logs.per_page;

  } catch (error) {
    console.error('Error fetching logs:', error);
    snackbarText.value = 'Failed to load logs';
    snackbarColor.value = 'error';
    snackbar.value = true;
  } finally {
    logsLoading.value = false;
  }
}

function resetEditForm() {
  if (user.value) {
    editForm.value = {
      ...user.value,
      original_region_id: user.value.region_id,
      original_cohort_id: user.value.cohort_id, // Fixed: Changed from batch_id to cohort_id
      original_work_status: user.value.work_status
    };
    changeReason.value = '';
    regionChangeInfo.value = {};
    cohortChangeInfo.value = {}; // Fixed: Changed from batchChangeInfo
    workStatusChangeInfo.value = {};
    errors.value = {};
  }
}

function enableEdit() {
  isEditing.value = true;
  resetEditForm();
}

function cancelEdit() {
  isEditing.value = false;
  resetEditForm();
}

async function saveUser() {
  saving.value = true;
  errors.value = {};

  try {
    const payload = {
      ...editForm.value,
      change_reason: changeReason.value,
      region_change_info: regionChangeInfo.value,
      cohort_change_info: cohortChangeInfo.value, // Fixed: Changed from batch_change_info
      work_status_change_info: workStatusChangeInfo.value
    };

    const response = await axios.put(`/api/admin/iva-users/${userId}`, payload);

    user.value = response.data.user;
    isEditing.value = false;

    snackbarText.value = 'User updated successfully';
    snackbarColor.value = 'success';
    snackbar.value = true;

    // Refresh data
    await fetchUserDetails();

  } catch (error) {
    console.error('Error updating user:', error);

    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors;
    } else {
      snackbarText.value = error.response?.data?.message || 'Failed to update user';
      snackbarColor.value = 'error';
      snackbar.value = true;
    }
  } finally {
    saving.value = false;
  }
}

function openDeleteManagerDialog(manager) {
  managerToDelete.value = manager;
  deleteManagerDialog.value = true;
}

async function confirmDeleteManager() {
  if (!managerToDelete.value) return;

  try {
    await axios.delete(`/api/admin/iva-users/${userId}/managers/${managerToDelete.value.id}`);

    // Remove from local data
    user.value.managers = user.value.managers.filter(m => m.id !== managerToDelete.value.id);

    snackbarText.value = 'Manager removed successfully';
    snackbarColor.value = 'success';
    snackbar.value = true;

    deleteManagerDialog.value = false;
    managerToDelete.value = null;

  } catch (error) {
    console.error('Error removing manager:', error);
    snackbarText.value = error.response?.data?.message || 'Failed to remove manager';
    snackbarColor.value = 'error';
    snackbar.value = true;
  }
}

async function addManager() {
  try {
    const response = await axios.post(`/api/admin/iva-users/${userId}/managers`, managerForm.value);

    user.value.managers = response.data.managers;
    managerDialog.value = false;
    managerForm.value = { manager_id: null, manager_type_id: null, region_id: null };

    snackbarText.value = 'Manager assigned successfully';
    snackbarColor.value = 'success';
    snackbar.value = true;

  } catch (error) {
    console.error('Error adding manager:', error);
    snackbarText.value = error.response?.data?.message || 'Failed to assign manager';
    snackbarColor.value = 'error';
    snackbar.value = true;
  }
}

async function openManagerDialog() {
  try {
    const response = await axios.get(`/api/admin/iva-users/${userId}/available-managers`);
    availableManagers.value = response.data.managers;
    // Pre-fill region with user's region and don't allow changing it
    managerForm.value.region_id = user.value.region_id;
    managerDialog.value = true;
  } catch (error) {
    console.error('Error fetching available managers:', error);
  }
}

function openDeleteCustomizationDialog(customization) {
  customizationToDelete.value = customization;
  deleteCustomizationDialog.value = true;
}

async function confirmDeleteCustomization() {
  if (!customizationToDelete.value) return;

  try {
    await axios.delete(`/api/admin/iva-users/${userId}/customizations/${customizationToDelete.value.id}`);

    // Remove from local data
    user.value.customizations = user.value.customizations.filter(c => c.id !== customizationToDelete.value.id);

    snackbarText.value = 'Customization removed successfully';
    snackbarColor.value = 'success';
    snackbar.value = true;

    deleteCustomizationDialog.value = false;
    customizationToDelete.value = null;

  } catch (error) {
    console.error('Error removing customization:', error);
    snackbarText.value = error.response?.data?.message || 'Failed to remove customization';
    snackbarColor.value = 'error';
    snackbar.value = true;
  }
}

async function addCustomization() {
  try {
    const payload = {
      customizations: [{
        setting_id: customizationForm.value.setting_id,
        custom_value: customizationForm.value.custom_value,
        start_date: customizationForm.value.start_date,
        end_date: customizationForm.value.end_date
      }]
    };

    const response = await axios.post(`/api/admin/iva-users/${userId}/customizations`, payload);

    user.value.customizations = response.data.customizations;
    customizationDialog.value = false;
    customizationForm.value = { setting_id: null, custom_value: '', start_date: null, end_date: null };

    snackbarText.value = 'Customization added successfully';
    snackbarColor.value = 'success';
    snackbar.value = true;

  } catch (error) {
    console.error('Error adding customization:', error);
    snackbarText.value = error.response?.data?.message || 'Failed to add customization';
    snackbarColor.value = 'error';
    snackbar.value = true;
  }
}

// Log management functions
function openLogDialog() {
  editingLog.value = null;
  logForm.value = {
    log_type: 'note',
    title: '',
    content: '',
    is_private: false
  };
  logDialog.value = true;
}

function openEditLogDialog(log) {
  editingLog.value = log;
  logForm.value = {
    log_type: log.log_type,
    title: log.title || '',
    content: log.content,
    is_private: log.is_private
  };
  logDialog.value = true;
}

async function saveLog() {
  try {
    let response;
    if (editingLog.value) {
      // Update existing log
      response = await axios.put(`/api/admin/iva-users/${userId}/logs/${editingLog.value.id}`, logForm.value);

      // Update local data
      const index = logs.value.findIndex(l => l.id === editingLog.value.id);
      if (index !== -1) {
        logs.value[index] = response.data.log;
      }

      snackbarText.value = 'Log entry updated successfully';
    } else {
      // Create new log
      response = await axios.post(`/api/admin/iva-users/${userId}/logs`, logForm.value);

      // Add to local data
      logs.value.unshift(response.data.log);

      snackbarText.value = 'Log entry added successfully';
    }

    logDialog.value = false;
    snackbarColor.value = 'success';
    snackbar.value = true;

  } catch (error) {
    console.error('Error saving log:', error);
    snackbarText.value = error.response?.data?.message || 'Failed to save log entry';
    snackbarColor.value = 'error';
    snackbar.value = true;
  }
}

function openDeleteLogDialog(log) {
  logToDelete.value = log;
  deleteLogDialog.value = true;
}

async function confirmDeleteLog() {
  if (!logToDelete.value) return;

  try {
    await axios.delete(`/api/admin/iva-users/${userId}/logs/${logToDelete.value.id}`);

    // Remove from local data
    logs.value = logs.value.filter(l => l.id !== logToDelete.value.id);

    snackbarText.value = 'Log entry deleted successfully';
    snackbarColor.value = 'success';
    snackbar.value = true;

    deleteLogDialog.value = false;
    logToDelete.value = null;

  } catch (error) {
    console.error('Error deleting log:', error);
    snackbarText.value = error.response?.data?.message || 'Failed to delete log entry';
    snackbarColor.value = 'error';
    snackbar.value = true;
  }
}

function handleLogsPageChange(page) {
  logsPagination.value.page = page;
  fetchLogs();
}

function clearLogsFilters() {
  logsFilters.value = {
    log_type: null,
    is_private: null
  };
  logsPagination.value.page = 1;
  fetchLogs();
}

function getLogTypeColor(logType) {
  switch (logType) {
    case 'note': return 'primary';
    case 'nad': return 'warning';
    case 'performance': return 'info';
    default: return 'secondary';
  }
}

function getLogTypeDisplay(logType) {
  const type = logTypes.value.find(t => t.value === logType);
  return type ? type.label : logType.charAt(0).toUpperCase() + logType.slice(1);
}

function viewWorklogDashboard() {
  router.push({ name: 'iva-user-worklog-dashboard', params: { id: userId } });
}

function viewTimeDoctorRecords() {
  router.push({ name: 'iva-user-timedoctor-records', params: { id: userId } });
}

function goBack() {
  router.push({ name: 'iva-users-list' });
}

function getStatusColor(isActive) {
  return isActive ? 'success' : 'error';
}

function getStatusText(isActive) {
  return isActive ? 'Active' : 'Inactive';
}

function getTimeDoctorStatusColor(syncStatus) {
  if (!syncStatus?.is_linked) return 'warning';
  return syncStatus.can_sync ? 'success' : 'info';
}

function getTimeDoctorStatusText(syncStatus) {
  if (!syncStatus?.is_linked) return 'Not Linked';
  if (!syncStatus.can_sync) return 'V2 (On Hold)';
  return 'Linked & Active';
}

// Helper function to get setting display name
function getSettingDisplayName(customization) {
  const settingType = customization.setting?.setting_type;
  const settingValue = customization.setting?.setting_value;

  if (settingType?.name && settingValue) {
    return `${settingType.name}: ${settingValue}`;
  }

  return settingValue || 'Custom Setting';
}

// Helper function to get hint for selected setting
function getSelectedSettingHint() {
  if (!customizationForm.value.setting_id) return 'Select a setting to see description';

  const selectedSetting = availableCustomizationSettings.value.find(
    setting => setting.value === customizationForm.value.setting_id
  );

  return selectedSetting?.subtitle || 'Enter your custom value for this setting';
}

// Helper function to get manager type name
function getManagerTypeName(managerTypeId) {
  const managerType = managerTypes.value.find(type => type.id === managerTypeId);
  return managerType?.setting_value || 'Unknown Type';
}

// Helper function to format change values for display
function formatChangeValue(change, valueType) {
  const value = valueType === 'old' ? change.old_value : change.new_value;
  const rawValue = valueType === 'old' ? change.old_value_raw : change.new_value_raw;

  // If we have a formatted display value from backend, use it
  if (change.display_values) {
    const displayValue = valueType === 'old' ? change.display_values.old_display : change.display_values.new_display;
    if (displayValue) return displayValue;
  }

  // Try to parse as JSON in case it's a JSON value
  const parsedValue = safeJsonParse(value);
  if (parsedValue !== null) {
    if (typeof parsedValue === 'object') {
      return JSON.stringify(parsedValue);
    }
    return String(parsedValue);
  }

  // Use raw value if available, otherwise use the original value
  return rawValue || value || 'N/A';
}

// Watch for tab changes to load logs when needed
watch(() => activeTab.value, (newTab) => {
  if (newTab === 'logs' && logs.value.length === 0) {
    fetchLogs();
  }
});

// Watch for logs filters changes
watch([
  () => logsFilters.value.log_type,
  () => logsFilters.value.is_private
], () => {
  logsPagination.value.page = 1;
  fetchLogs();
});

// Watch for route changes to handle edit mode
watch(() => route.name, (newName) => {
  isEditing.value = newName === 'iva-user-edit';
});
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <VBreadcrumbs :items="[
      { title: 'Home', to: '/' },
      { title: 'IVA Users', to: { name: 'iva-users-list' } },
      { title: user ? user.full_name : 'User Details', disabled: true }
    ]" class="mb-6" aria-label="Breadcrumb navigation" />

    <div v-if="loading" class="d-flex justify-center align-center py-8">
      <VProgressCircular indeterminate color="primary" :size="50" :width="5" aria-label="Loading user details" />
    </div>

    <div v-else-if="user">
      <!-- Header Card -->
      <VCard class="mb-6">
        <VCardText>
          <div class="d-flex flex-wrap align-center mb-4">
            <div class="mr-auto mb-2 mb-md-0">
              <h1 class="text-h5 text-md-h4 d-flex align-center" tabindex="0">
                {{ user.full_name }}
                <VChip :color="getStatusColor(user.is_active)" size="small" text-color="white" class="ml-3">
                  {{ getStatusText(user.is_active) }}
                </VChip>
              </h1>
              <p class="text-secondary mb-0">{{ user.email }}</p>
              <div class="d-flex align-center mt-2">
                <VChip v-if="user.region" size="small" color="info" variant="outlined" class="mr-2">
                  {{ user.region.name }}
                </VChip>
                <VChip v-if="user.cohort" size="small" color="secondary" variant="outlined" class="mr-2">
                  {{ user.cohort.name }}
                </VChip>
                <VChip v-if="user.work_status" size="small" color="primary" variant="outlined" class="mr-2">
                  {{ user.work_status }}
                </VChip>
                <VChip size="small" :color="getTimeDoctorStatusColor(timeDoctorSyncStatus)" variant="flat"
                  text-color="white">
                  TD {{ getTimeDoctorStatusText(timeDoctorSyncStatus) }}
                </VChip>
              </div>
            </div>

            <div class="d-flex gap-2 flex-wrap">
              <VBtn v-if="!isEditing" color="primary" variant="outlined" prepend-icon="ri-edit-line"
                :size="isMobile ? 'small' : 'default'" @click="enableEdit" aria-label="Edit user">
                Edit
              </VBtn>

              <VBtn v-if="isEditing" color="success" prepend-icon="ri-save-line" :size="isMobile ? 'small' : 'default'"
                :loading="saving" @click="saveUser" aria-label="Save changes">
                Save
              </VBtn>

              <VBtn v-if="isEditing" color="secondary" variant="outlined" prepend-icon="ri-close-line"
                :size="isMobile ? 'small' : 'default'" @click="cancelEdit" aria-label="Cancel editing">
                Cancel
              </VBtn>

              <VMenu v-if="!isEditing">
                <template #activator="{ props }">
                  <VBtn color="info" variant="outlined" prepend-icon="ri-more-line"
                    :size="isMobile ? 'small' : 'default'" v-bind="props" aria-label="More actions">
                    Actions
                  </VBtn>
                </template>

                <VList density="compact">
                  <VListItem prepend-icon="ri-dashboard-line" title="Worklog Dashboard" @click="viewWorklogDashboard" />
                  <VListItem prepend-icon="ri-time-line" title="Time Doctor Records" @click="viewTimeDoctorRecords" />
                </VList>
              </VMenu>

              <VBtn color="secondary" variant="outlined" prepend-icon="ri-arrow-left-line"
                :size="isMobile ? 'small' : 'default'" @click="goBack" aria-label="Back to users list">
                Back
              </VBtn>
            </div>
          </div>
        </VCardText>
      </VCard>

      <!-- Tabs -->
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
              <h2 class="text-h6 font-weight-medium mb-4">User Information</h2>

              <IvaUserForm v-model:user="editForm" :regions="regions" :cohorts="cohorts"
                :work-status-options="workStatusOptions" :timedoctor-versions="timedoctorVersions"
                :is-editing="isEditing" :errors="errors" v-model:region-change-info="regionChangeInfo"
                v-model:cohort-change-info="cohortChangeInfo" v-model:work-status-change-info="workStatusChangeInfo" />
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- Managers Tab -->
        <VWindowItem value="managers">
          <VCard>
            <VCardText>
              <div class="d-flex align-center mb-4">
                <h2 class="text-h6 font-weight-medium">Assigned Managers</h2>
                <VSpacer />
                <VBtn color="primary" prepend-icon="ri-add-line" @click="openManagerDialog" aria-label="Add manager">
                  Add Manager
                </VBtn>
              </div>

              <div v-if="user.managers && user.managers.length > 0">
                <VRow>
                  <VCol v-for="manager in user.managers" :key="manager.id" cols="12" md="6" lg="4">
                    <VCard variant="outlined">
                      <VCardText>
                        <div class="d-flex align-center mb-2">
                          <VAvatar color="primary" class="mr-3">
                            <VIcon icon="ri-user-line" />
                          </VAvatar>
                          <div class="flex-grow-1">
                            <h3 class="text-subtitle-1 font-weight-medium">
                              {{ manager.manager?.full_name }}
                            </h3>
                            <p class="text-caption text-secondary mb-0">
                              {{ manager.manager_type?.setting_value }}
                            </p>
                          </div>
                          <VBtn icon size="small" color="error" variant="text" @click="openDeleteManagerDialog(manager)"
                            aria-label="Remove manager">
                            <VIcon size="20">ri-delete-bin-line</VIcon>
                          </VBtn>
                        </div>

                        <VDivider class="my-3" />

                        <div class="text-body-2">
                          <p><strong>Email:</strong> {{ manager.manager?.email }}</p>
                          <p><strong>Region:</strong> {{ manager.region?.name }}</p>
                          <p><strong>Type:</strong> {{ manager.manager_type?.setting_value }}</p>
                        </div>
                      </VCardText>
                    </VCard>
                  </VCol>
                </VRow>
              </div>

              <div v-else class="text-center py-8">
                <VIcon size="48" color="secondary" icon="ri-team-line" class="mb-4" />
                <h3 class="text-h6 font-weight-regular mb-2">No managers assigned</h3>
                <p class="text-secondary mb-4">
                  This user doesn't have any managers assigned yet.
                </p>
                <VBtn color="primary" @click="openManagerDialog" aria-label="Add first manager">
                  Add First Manager
                </VBtn>
              </div>
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- Customizations Tab -->
        <VWindowItem value="customizations">
          <VCard>
            <VCardText>
              <div class="d-flex align-center mb-4">
                <h2 class="text-h6 font-weight-medium">Custom Settings</h2>
                <VSpacer />
                <VBtn color="primary" prepend-icon="ri-add-line" @click="customizationDialog = true"
                  :disabled="availableCustomizationSettings.length === 0" aria-label="Add custom setting">
                  Add Setting
                </VBtn>
              </div>

              <div v-if="user.customizations && user.customizations.length > 0">
                <VList>
                  <VListItem v-for="customization in user.customizations" :key="customization.id" class="px-0">
                    <template #prepend>
                      <VAvatar color="info" variant="tonal">
                        <VIcon icon="ri-settings-line" />
                      </VAvatar>
                    </template>

                    <VListItemTitle>
                      {{ getSettingDisplayName(customization) }}
                    </VListItemTitle>

                    <VListItemSubtitle>
                      <strong>Custom Value:</strong> {{ customization.custom_value }}
                      <br>
                      <span v-if="customization.start_date || customization.end_date" class="text-caption">
                        <strong>Period:</strong>
                        {{ customization.start_date ? formatDateTime(customization.start_date) : 'No start' }}
                        -
                        {{ customization.end_date ? formatDateTime(customization.end_date) : 'No end' }}
                      </span>
                      <br v-if="customization.start_date || customization.end_date">
                      <span v-if="customization.setting?.description" class="text-caption">
                        {{ customization.setting.description }}
                      </span>
                    </VListItemSubtitle>

                    <template #append>
                      <VBtn icon size="small" color="error" variant="text"
                        @click="openDeleteCustomizationDialog(customization)" aria-label="Remove custom setting">
                        <VIcon size="20">ri-delete-bin-line</VIcon>
                      </VBtn>
                    </template>
                  </VListItem>
                </VList>
              </div>

              <div v-else class="text-center py-8">
                <VIcon size="48" color="secondary" icon="ri-settings-line" class="mb-4" />
                <h3 class="text-h6 font-weight-regular mb-2">No custom settings</h3>
                <p class="text-secondary mb-4">
                  This user doesn't have any custom settings configured.
                </p>
                <VBtn color="primary" @click="customizationDialog = true"
                  :disabled="availableCustomizationSettings.length === 0" aria-label="Add first custom setting">
                  Add First Setting
                </VBtn>
              </div>

              <div v-if="availableCustomizationSettings.length === 0 && user.customizations?.length > 0" class="mt-4">
                <VAlert type="info" variant="tonal" density="compact">
                  All available settings have been customized for this user.
                </VAlert>
              </div>
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- Logs Tab -->
        <VWindowItem value="logs">
          <VCard>
            <VCardText>
              <div class="d-flex align-center mb-4">
                <h2 class="text-h6 font-weight-medium">User Logs</h2>
                <VSpacer />
                <VBtn color="primary" prepend-icon="ri-add-line" @click="openLogDialog" aria-label="Add log entry">
                  Add Log
                </VBtn>
              </div>

              <!-- Filters -->
              <VRow class="mb-4">
                <VCol cols="12" md="3">
                  <VSelect v-model="logsFilters.log_type" :items="logTypes" item-title="label" item-value="value"
                    label="Log Type" density="comfortable" variant="outlined" clearable />
                </VCol>
                <VCol cols="12" md="3">
                  <VSelect v-model="logsFilters.is_private" :items="[
                    { title: 'Public', value: false },
                    { title: 'Private', value: true }
                  ]" label="Privacy" density="comfortable" variant="outlined" clearable />
                </VCol>
                <VCol cols="12" md="3">
                  <VBtn v-if="Object.values(logsFilters).some(v => v !== null)" color="secondary" variant="outlined"
                    @click="clearLogsFilters">
                    Clear Filters
                  </VBtn>
                </VCol>
              </VRow>

              <!-- Logs List -->
              <div v-if="logsLoading" class="d-flex justify-center py-4">
                <VProgressCircular indeterminate color="primary" />
              </div>

              <div v-else-if="logs && logs.length > 0">
                <VCard v-for="log in logs" :key="log.id" variant="outlined" class="mb-4">
                  <VCardText>
                    <div class="d-flex align-center mb-2">
                      <VChip :color="getLogTypeColor(log.log_type)" size="small" class="mr-2">
                        {{ getLogTypeDisplay(log.log_type) }}
                      </VChip>
                      <VChip v-if="log.is_private" color="warning" size="small" class="mr-2">
                        Private
                      </VChip>
                      <VSpacer />
                      <VBtn icon size="small" variant="text" @click="openEditLogDialog(log)">
                        <VIcon size="18">ri-edit-line</VIcon>
                      </VBtn>
                      <VBtn icon size="small" variant="text" color="error" @click="openDeleteLogDialog(log)">
                        <VIcon size="18">ri-delete-bin-line</VIcon>
                      </VBtn>
                    </div>

                    <h3 v-if="log.title" class="text-subtitle-1 font-weight-medium mb-2">{{ log.title }}</h3>
                    <p class="text-body-2 mb-3" style="white-space: pre-wrap;">{{ log.content }}</p>

                    <div class="text-caption text-secondary">
                      <span>By: {{ log.creator?.name || 'Unknown' }} • </span>
                      <span>{{ formatDateTime(log.created_at) }}</span>
                      <span v-if="log.updated_at !== log.created_at"> • Updated: {{ formatDateTime(log.updated_at)
                      }}</span>
                    </div>
                  </VCardText>
                </VCard>

                <!-- Pagination -->
                <div class="d-flex justify-center mt-4">
                  <VPagination v-model="logsPagination.page"
                    :length="Math.ceil(logsPagination.total / logsPagination.perPage)" :total-visible="isMobile ? 3 : 7"
                    @update:model-value="handleLogsPageChange" />
                </div>
              </div>

              <div v-else class="text-center py-8">
                <VIcon size="48" color="secondary" icon="ri-file-text-line" class="mb-4" />
                <h3 class="text-h6 font-weight-regular mb-2">No logs found</h3>
                <p class="text-secondary mb-4">
                  <span v-if="Object.values(logsFilters).some(v => v !== null)">
                    No logs match your current filters.
                  </span>
                  <span v-else>
                    No logs have been created for this user yet.
                  </span>
                </p>
                <VBtn color="primary" @click="openLogDialog">
                  Add First Log
                </VBtn>
              </div>
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- Changelog Tab -->
        <VWindowItem value="changelog">
          <VCard>
            <VCardText>
              <h2 class="text-h6 font-weight-medium mb-4">Change History</h2>

              <div v-if="user.changelogs && user.changelogs.length > 0">
                <VTimeline>
                  <VTimelineItem v-for="change in user.changelogs" :key="change.id" dot-color="primary" size="small">
                    <template #opposite>
                      <div class="text-caption text-secondary">
                        {{ formatDateTime(change.created_at) }}
                      </div>
                    </template>

                    <VCard variant="outlined">
                      <VCardText>
                        <h3 class="text-subtitle-1 font-weight-medium mb-2">
                          {{ change.field_changed.replace('_', ' ').toUpperCase() }} Change
                        </h3>

                        <div class="mb-3">
                          <p><strong>Reason:</strong> {{ change.change_reason }}</p>
                          <p v-if="formatChangeValue(change, 'old') !== 'N/A'">
                            <strong>From:</strong> {{ formatChangeValue(change, 'old') }}
                          </p>
                          <p v-if="formatChangeValue(change, 'new') !== 'N/A'">
                            <strong>To:</strong> {{ formatChangeValue(change, 'new') }}
                          </p>
                        </div>

                        <div class="text-caption text-secondary">
                          <p>Changed by: {{ change.changed_by_name }} ({{ change.changed_by_email }})</p>
                          <p v-if="change.effective_date">
                            Effective: {{ formatDateTime(change.effective_date) }}
                          </p>
                        </div>
                      </VCardText>
                    </VCard>
                  </VTimelineItem>
                </VTimeline>
              </div>

              <div v-else class="text-center py-8">
                <VIcon size="48" color="secondary" icon="ri-history-line" class="mb-4" />
                <h3 class="text-h6 font-weight-regular mb-2">No change history</h3>
                <p class="text-secondary">
                  No changes have been recorded for this user yet.
                </p>
              </div>
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- TimeDoctor Tab -->
        <VWindowItem value="timedoctor">
          <VCard>
            <VCardText>
              <h2 class="text-h6 font-weight-medium mb-4">TimeDoctor Integration</h2>

              <VRow>
                <VCol cols="12" md="6">
                  <VCard variant="outlined">
                    <VCardText>
                      <h3 class="text-subtitle-1 font-weight-medium mb-3">Connection Status</h3>

                      <div class="d-flex align-center mb-3">
                        <VChip :color="getTimeDoctorStatusColor(timeDoctorSyncStatus)" variant="flat" text-color="white"
                          class="mr-3">
                          {{ getTimeDoctorStatusText(timeDoctorSyncStatus) }}
                        </VChip>
                        <span class="text-body-2">
                          Version {{ timeDoctorSyncStatus?.td_version }}
                        </span>
                      </div>

                      <div v-if="timeDoctorSyncStatus?.is_linked" class="text-body-2">
                        <p><strong>TD User ID:</strong> {{ timeDoctorSyncStatus.td_user_id }}</p>
                        <p><strong>Last Synced:</strong> {{ formatDateTime(timeDoctorSyncStatus.last_synced) }}</p>
                      </div>

                      <div v-else class="text-body-2">
                        <p class="text-warning">This user is not linked to a TimeDoctor account.</p>
                      </div>
                    </VCardText>
                  </VCard>
                </VCol>

                <VCol cols="12" md="6">
                  <VCard variant="outlined">
                    <VCardText>
                      <h3 class="text-subtitle-1 font-weight-medium mb-3">Quick Actions</h3>

                      <div class="d-flex flex-column gap-3">
                        <VBtn color="primary" variant="outlined" prepend-icon="ri-dashboard-line"
                          @click="viewWorklogDashboard" block>
                          View Dashboard
                        </VBtn>

                        <VBtn color="info" variant="outlined" prepend-icon="ri-time-line" @click="viewTimeDoctorRecords"
                          block>
                          Manage Records
                        </VBtn>

                        <VAlert v-if="!timeDoctorSyncStatus?.can_sync && timeDoctorSyncStatus?.td_version === 2"
                          type="info" variant="tonal" density="compact">
                          TimeDoctor v2 sync is currently on hold
                        </VAlert>
                      </div>
                    </VCardText>
                  </VCard>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VWindowItem>
      </VWindow>
    </div>

    <!-- Manager Assignment Dialog -->
    <VDialog v-model="managerDialog" max-width="600" persistent>
      <VCard>
        <VCardTitle class="text-h5 bg-primary text-white">
          Assign Manager
        </VCardTitle>

        <VCardText class="pt-4">
          <VRow>
            <VCol cols="12">
              <VSelect v-model="managerForm.manager_id" :items="filteredAvailableManagers" item-title="full_name"
                item-value="id" label="Select Manager" density="comfortable" variant="outlined" required />
              <p v-if="filteredAvailableManagers.length === 0" class="text-caption text-warning mt-2">
                No available managers in this region.
              </p>
            </VCol>

            <VCol cols="12">
              <VSelect v-model="managerForm.manager_type_id" :items="managerTypes" item-title="setting_value"
                item-value="id" label="Manager Type" density="comfortable" variant="outlined" required />
            </VCol>

            <VCol cols="12">
              <VTextField :model-value="user?.region?.name" label="Region" density="comfortable" variant="outlined"
                readonly hint="Manager will be assigned in the same region as the user" persistent-hint />
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="managerDialog = false">
            Cancel
          </VBtn>
          <VBtn color="primary" @click="addManager" :disabled="!managerForm.manager_id || !managerForm.manager_type_id">
            Assign Manager
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Manager Confirmation Dialog -->
    <VDialog v-model="deleteManagerDialog" max-width="500" persistent role="alertdialog">
      <VCard>
        <VCardTitle class="text-h5 bg-error text-white">
          Remove Manager
        </VCardTitle>

        <VCardText class="pt-4">
          <p class="mb-4">Are you sure you want to remove this manager assignment?</p>

          <div v-if="managerToDelete" class="mb-3 pa-3 bg-grey-lighten-4 rounded">
            <p class="mb-1"><strong>Manager:</strong> {{ managerToDelete.manager?.full_name }}</p>
            <p class="mb-1"><strong>Type:</strong> {{ managerToDelete.manager_type?.setting_value }}</p>
            <p class="mb-1"><strong>Region:</strong> {{ managerToDelete.region?.name }}</p>
            <p class="mb-0"><strong>Email:</strong> {{ managerToDelete.manager?.email }}</p>
          </div>

          <p class="text-body-2 text-secondary mb-0">This action cannot be undone.</p>
        </VCardText>

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="deleteManagerDialog = false">
            Cancel
          </VBtn>
          <VBtn color="error" @click="confirmDeleteManager">
            Remove Manager
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Customization Dialog -->
    <VDialog v-model="customizationDialog" max-width="600" persistent>
      <VCard>
        <VCardTitle class="text-h5 bg-primary text-white">
          Add Custom Setting
        </VCardTitle>

        <VCardText class="pt-4">
          <VRow>
            <VCol cols="12">
              <VSelect v-model="customizationForm.setting_id" :items="availableCustomizationSettings"
                label="Select Setting" density="comfortable" variant="outlined" required>
                <template #item="{ props, item }">
                  <VListItem v-bind="props">
                    <VListItemTitle>{{ item.title }}</VListItemTitle>
                    <VListItemSubtitle>{{ item.subtitle }}</VListItemSubtitle>
                  </VListItem>
                </template>
              </VSelect>
              <p v-if="availableCustomizationSettings.length === 0" class="text-caption text-warning mt-2">
                All available settings have been customized for this user.
              </p>
            </VCol>

            <VCol cols="12">
              <VTextField v-model="customizationForm.custom_value" label="Custom Value" density="comfortable"
                variant="outlined" required :hint="getSelectedSettingHint()" persistent-hint />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField v-model="customizationForm.start_date" label="Start Date" type="date" density="comfortable"
                variant="outlined" hint="Optional - when this setting becomes effective" persistent-hint />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField v-model="customizationForm.end_date" label="End Date" type="date" density="comfortable"
                variant="outlined" hint="Optional - when this setting expires" persistent-hint />
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="customizationDialog = false">
            Cancel
          </VBtn>
          <VBtn color="primary" @click="addCustomization"
            :disabled="!customizationForm.setting_id || !customizationForm.custom_value">
            Add Setting
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Customization Confirmation Dialog -->
    <VDialog v-model="deleteCustomizationDialog" max-width="500" persistent role="alertdialog">
      <VCard>
        <VCardTitle class="text-h5 bg-error text-white">
          Remove Custom Setting
        </VCardTitle>

        <VCardText class="pt-4">
          <p class="mb-4">Are you sure you want to remove this custom setting?</p>

          <div v-if="customizationToDelete" class="mb-3 pa-3 bg-grey-lighten-4 rounded">
            <p class="mb-1"><strong>Setting:</strong> {{ getSettingDisplayName(customizationToDelete) }}</p>
            <p class="mb-1"><strong>Custom Value:</strong> {{ customizationToDelete.custom_value }}</p>
            <p v-if="customizationToDelete.start_date || customizationToDelete.end_date" class="mb-1">
              <strong>Period:</strong>
              {{ customizationToDelete.start_date ? formatDateTime(customizationToDelete.start_date) : 'No start' }}
              -
              {{ customizationToDelete.end_date ? formatDateTime(customizationToDelete.end_date) : 'No end' }}
            </p>
            <p v-if="customizationToDelete.setting?.description" class="mb-0">
              <strong>Description:</strong> {{ customizationToDelete.setting.description }}
            </p>
          </div>

          <p class="text-body-2 text-secondary mb-0">This action cannot be undone.</p>
        </VCardText>

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="deleteCustomizationDialog = false">
            Cancel
          </VBtn>
          <VBtn color="error" @click="confirmDeleteCustomization">
            Remove Setting
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Log Dialog -->
    <VDialog v-model="logDialog" max-width="700" persistent>
      <VCard>
        <VCardTitle class="text-h5 bg-primary text-white">
          {{ editingLog ? 'Edit Log Entry' : 'Add Log Entry' }}
        </VCardTitle>

        <VCardText class="pt-4">
          <VRow>
            <VCol cols="12" md="6">
              <VSelect v-model="logForm.log_type" :items="logTypes" item-title="label" item-value="value"
                label="Log Type" density="comfortable" variant="outlined" required />
            </VCol>

            <VCol cols="12" md="6">
              <VCheckbox v-model="logForm.is_private" label="Private Log"
                hint="Private logs are only visible to administrators" persistent-hint />
            </VCol>

            <VCol cols="12">
              <VTextField v-model="logForm.title" label="Title (Optional)" density="comfortable" variant="outlined" />
            </VCol>

            <VCol cols="12">
              <VTextarea v-model="logForm.content" label="Content" density="comfortable" variant="outlined" required
                rows="5" hint="Enter the log content here" persistent-hint />
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="logDialog = false">
            Cancel
          </VBtn>
          <VBtn color="primary" @click="saveLog" :disabled="!logForm.content">
            {{ editingLog ? 'Update Log' : 'Add Log' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Log Confirmation Dialog -->
    <VDialog v-model="deleteLogDialog" max-width="500" persistent role="alertdialog">
      <VCard>
        <VCardTitle class="text-h5 bg-error text-white">
          Delete Log Entry
        </VCardTitle>

        <VCardText class="pt-4">
          <p class="mb-4">Are you sure you want to delete this log entry?</p>

          <div v-if="logToDelete" class="mb-3 pa-3 bg-grey-lighten-4 rounded">
            <p class="mb-1"><strong>Type:</strong> {{ getLogTypeDisplay(logToDelete.log_type) }}</p>
            <p v-if="logToDelete.title" class="mb-1"><strong>Title:</strong> {{ logToDelete.title }}</p>
            <p class="mb-1"><strong>Content:</strong> {{ logToDelete.content.substring(0, 100) }}{{
              logToDelete.content.length > 100 ? '...' : '' }}</p>
            <p class="mb-0"><strong>Created:</strong> {{ formatDateTime(logToDelete.created_at) }}</p>
          </div>

          <p class="text-body-2 text-secondary mb-0">This action cannot be undone.</p>
        </VCardText>

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="deleteLogDialog = false">
            Cancel
          </VBtn>
          <VBtn color="error" @click="confirmDeleteLog">
            Delete Log
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Sync Dialog -->
    <VDialog v-model="syncDialog" max-width="500">
      <VCard>
        <VCardTitle class="text-h5 bg-success text-white">
          Sync with TimeDoctor
        </VCardTitle>

        <VCardText class="pt-4">
          <p>This will sync the user's information with their TimeDoctor account.</p>
          <p class="text-body-2 text-secondary">
            This may take a few moments to complete.
          </p>
        </VCardText>

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="syncDialog = false">
            Cancel
          </VBtn>
          <VBtn color="success" @click="syncDialog = false">
            Start Sync
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

  /* Make dialogs responsive */
  :deep(.v-dialog .v-card) {
    margin: 16px;
    max-inline-size: calc(100vw - 32px);
  }
}

/* Improve timeline readability */
:deep(.v-timeline-item) {
  padding-block-end: 1.5rem;
}

/* Ensure proper chip sizing */
:deep(.v-chip) {
  font-size: 0.75rem;
}
</style>
