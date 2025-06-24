<script setup>
import { formatDateTime } from '@/@core/utils/helpers';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import IvaUserForm from './IvaUserForm.vue';
import IvaUserHistory from './IvaUserHistory.vue';
import IvaUserLogs from './IvaUserLogs.vue';
import IvaUserManagers from './IvaUserManagers.vue';
import IvaUserSettings from './IvaUserSettings.vue';

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
const cohorts = ref([]);
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
const cohortChangeInfo = ref({});
const workStatusChangeInfo = ref({});

// UI state
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');
const activeTab = ref('overview');

// Component refs
const logsComponent = ref(null);

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
    cohorts.value = response.data.cohorts;
    workStatusOptions.value = response.data.workStatusOptions;
    timedoctorVersions.value = response.data.timedoctorVersions;
    customizationTypes.value = response.data.customizationTypes;
    managerTypes.value = response.data.managerTypes;
    logTypes.value = response.data.logTypes;
    timeDoctorSyncStatus.value = response.data.timeDoctorSyncStatus;

    // Initialize edit form
    resetEditForm();

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

function resetEditForm() {
  if (user.value) {
    editForm.value = {
      ...user.value,
      original_region_id: user.value.region_id,
      original_cohort_id: user.value.cohort_id,
      original_work_status: user.value.work_status
    };
    changeReason.value = '';
    regionChangeInfo.value = {};
    cohortChangeInfo.value = {};
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
      cohort_change_info: cohortChangeInfo.value,
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

// Manager event handlers
async function handleManagerAdded(managerData) {
  try {
    const response = await axios.post(`/api/admin/iva-users/${userId}/managers`, managerData);
    user.value.managers = response.data.managers;
    showSnackbar('Manager assigned successfully', 'success');
  } catch (error) {
    console.error('Error adding manager:', error);
    showSnackbar(error.response?.data?.message || 'Failed to assign manager', 'error');
  }
}

async function handleManagerRemoved(manager) {
  try {
    await axios.delete(`/api/admin/iva-users/${userId}/managers/${manager.id}`);
    user.value.managers = user.value.managers.filter(m => m.id !== manager.id);
    showSnackbar('Manager removed successfully', 'success');
  } catch (error) {
    console.error('Error removing manager:', error);
    showSnackbar(error.response?.data?.message || 'Failed to remove manager', 'error');
  }
}

// Customization event handlers
async function handleCustomizationAdded(customizationData) {
  try {
    const payload = {
      customizations: [{
        setting_id: customizationData.setting_id,
        custom_value: customizationData.custom_value,
        start_date: customizationData.start_date,
        end_date: customizationData.end_date
      }]
    };

    const response = await axios.post(`/api/admin/iva-users/${userId}/customizations`, payload);
    user.value.customizations = response.data.customizations;
    showSnackbar('Customization added successfully', 'success');
  } catch (error) {
    console.error('Error adding customization:', error);
    showSnackbar(error.response?.data?.message || 'Failed to add customization', 'error');
  }
}

function handleCustomizationUpdated(customizations) {
  user.value.customizations = customizations;
  showSnackbar('Customization updated successfully', 'success');
}

async function handleCustomizationRemoved(customization) {
  try {
    await axios.delete(`/api/admin/iva-users/${userId}/customizations/${customization.id}`);
    user.value.customizations = user.value.customizations.filter(c => c.id !== customization.id);
    showSnackbar('Customization removed successfully', 'success');
  } catch (error) {
    console.error('Error removing customization:', error);
    showSnackbar(error.response?.data?.message || 'Failed to remove customization', 'error');
  }
}

// Utility functions
function showSnackbar(message, color = 'success') {
  snackbarText.value = message;
  snackbarColor.value = color;
  snackbar.value = true;
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

// Watch for tab changes to load logs when needed
watch(() => activeTab.value, (newTab) => {
  if (newTab === 'logs' && logsComponent.value) {
    logsComponent.value.fetchLogs();
  }
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
          <IvaUserManagers :user="user" :manager-types="managerTypes" :is-mobile="isMobile"
            @manager-added="handleManagerAdded" @manager-removed="handleManagerRemoved" />
        </VWindowItem>

        <!-- Customizations Tab -->
        <VWindowItem value="customizations">
          <IvaUserSettings :user="user" :customization-types="customizationTypes" :is-mobile="isMobile"
            @customization-added="handleCustomizationAdded" @customization-updated="handleCustomizationUpdated"
            @customization-removed="handleCustomizationRemoved" />
        </VWindowItem>

        <!-- Logs Tab -->
        <VWindowItem value="logs">
          <IvaUserLogs ref="logsComponent" :user="user" :log-types="logTypes" :is-mobile="isMobile"
            @show-snackbar="showSnackbar" />
        </VWindowItem>

        <!-- Changelog Tab -->
        <VWindowItem value="changelog">
          <IvaUserHistory :user="user" :is-mobile="isMobile" />
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
