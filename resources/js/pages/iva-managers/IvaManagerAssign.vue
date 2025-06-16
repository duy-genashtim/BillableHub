<script setup>
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();

// Form data
const selectedRegionId = ref(null);
const selectedManagerId = ref(null);
const selectedManagerTypeId = ref(null);
const selectedUserIds = ref([]);

// Data for dropdowns
const regions = ref([]);
const users = ref([]);
const managerTypes = ref([]);

// UI state
const loading = ref(true);
const loadingRegionData = ref(false);
const searchQuery = ref('');
const isMobile = ref(window.innerWidth < 768);
const errors = ref({});
const submitting = ref(false);
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

// Available users headers
const userHeaders = computed(() => {
  if (isMobile.value) {
    return [
      { title: 'Full Name', key: 'full_name', sortable: true },
    ];
  } else {
    return [
      { title: 'ID', key: 'id', sortable: true },
      { title: 'Full Name', key: 'full_name', sortable: true },
      { title: 'Email', key: 'email', sortable: true },
    ];
  }
});

onMounted(() => {
  fetchRegions();
  window.addEventListener('resize', handleResize);
});

function handleResize() {
  isMobile.value = window.innerWidth < 768;
}

async function fetchRegions() {
  loading.value = true;
  try {
    const response = await axios.get('/api/admin/iva-managers');
    regions.value = response.data.regions;
  } catch (error) {
    console.error('Error fetching regions:', error);
    snackbarText.value = 'Failed to load regions';
    snackbarColor.value = 'error';
    snackbar.value = true;
  } finally {
    loading.value = false;
  }
}

async function fetchRegionData() {
  if (!selectedRegionId.value) return;

  loadingRegionData.value = true;
  selectedManagerId.value = null;
  selectedManagerTypeId.value = null;
  selectedUserIds.value = [];

  try {
    const response = await axios.get(`/api/admin/iva-managers/regions/${selectedRegionId.value}`);
    users.value = response.data.users;
    managerTypes.value = response.data.managerTypes;
  } catch (error) {
    console.error('Error fetching region data:', error);
    snackbarText.value = 'Failed to load region data';
    snackbarColor.value = 'error';
    snackbar.value = true;
  } finally {
    loadingRegionData.value = false;
  }
}

// Watch for changes in selectedRegionId and fetch data
watch(selectedRegionId, (newValue) => {
  if (newValue) {
    fetchRegionData();
  } else {
    users.value = [];
    managerTypes.value = [];
  }
});

watch(selectedManagerId, (newManagerId) => {
  if (newManagerId && selectedUserIds.value.includes(newManagerId)) {
    selectedUserIds.value = selectedUserIds.value.filter(id => id !== newManagerId);
  }
});

async function submitForm() {
  // Validate form
  errors.value = {};

  if (!selectedRegionId.value) {
    errors.value.region = 'Please select a region';
    return;
  }

  if (!selectedManagerId.value) {
    errors.value.manager = 'Please select a manager';
    return;
  }

  if (!selectedManagerTypeId.value) {
    errors.value.managerType = 'Please select a manager type';
    return;
  }

  if (selectedUserIds.value.length === 0) {
    errors.value.users = 'Please select at least one user to be managed';
    return;
  }

  // Prevent self-management
  if (selectedUserIds.value.includes(selectedManagerId.value)) {
    errors.value.users = 'A manager cannot manage themselves. Please adjust your selection.';
    return;
  }

  submitting.value = true;

  try {
    // Get manager and manager type for logging
    const manager = users.value.find(u => u.id === selectedManagerId.value);
    const managerType = managerTypes.value.find(t => t.id === selectedManagerTypeId.value);
    const region = regions.value.find(r => r.id === selectedRegionId.value);

    const payload = {
      region_id: selectedRegionId.value,
      manager_id: selectedManagerId.value,
      manager_type_id: selectedManagerTypeId.value,
      user_ids: selectedUserIds.value,
      log_description: `Assigned ${manager ? manager.full_name : 'manager'} as ${managerType ? managerType.setting_value : ''} manager to ${selectedUserIds.value.length} users in region: ${region ? region.name : ''}`
    };

    const response = await axios.post('/api/admin/iva-managers', payload);

    snackbarText.value = 'Manager assigned successfully';
    snackbarColor.value = 'success';
    snackbar.value = true;

    // Navigate back to the managers list
    setTimeout(() => {
      router.push({ name: 'iva-managers-list' });
    }, 1000);
  } catch (error) {
    console.error('Error assigning manager:', error);

    if (error.response && error.response.data && error.response.data.errors) {
      errors.value = error.response.data.errors;
    } else if (error.response && error.response.data && error.response.data.message) {
      snackbarText.value = error.response.data.message;
      snackbarColor.value = 'error';
      snackbar.value = true;
    } else {
      snackbarText.value = 'Failed to assign manager';
      snackbarColor.value = 'error';
      snackbar.value = true;
    }
  } finally {
    submitting.value = false;
  }
}

function cancel() {
  router.push({ name: 'iva-managers-list' });
}

// Computed properties for filtering
const filteredUsers = computed(() => {
  if (!searchQuery.value || !users.value) return users.value;

  const query = searchQuery.value.toLowerCase();
  return users.value.filter(user =>
    (user.full_name && user.full_name.toLowerCase().includes(query)) ||
    (user.email && user.email.toLowerCase().includes(query)) ||
    (user.id && user.id.toString().includes(query))
  );
});

// Computed property to filter out selected manager from selectable users
const selectableUsers = computed(() => {
  if (!selectedManagerId.value || !users.value) return users.value;

  // Remove the manager from the list of users they can manage
  return users.value.filter(user => user.id !== selectedManagerId.value);
});
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <VBreadcrumbs :items="[
      { title: 'Home', to: '/' },
      { title: 'IVA Managers', to: { name: 'iva-managers-list' } },
      { title: 'Assign Manager', disabled: true }
    ]" class="mb-6" aria-label="Breadcrumb navigation" />

    <VCard>
      <VCardText>
        <VForm @submit.prevent="submitForm">
          <div class="d-flex flex-wrap align-center mb-6">
            <h1 class="text-h5 text-md-h4 mr-auto mb-2 mb-md-0" tabindex="0">
              Assign Manager
            </h1>
          </div>

          <VDivider class="mb-4" aria-hidden="true" />

          <VRow>
            <VCol cols="12">
              <VSelect v-model="selectedRegionId" :items="regions" item-title="name" item-value="id"
                label="Select Region" :error-messages="errors.region" required density="comfortable" variant="outlined"
                prepend-inner-icon="ri-map-pin-line" :loading="loading" :disabled="loading || submitting"
                aria-label="Select a region" />
            </VCol>
          </VRow>

          <div v-if="loadingRegionData" class="d-flex justify-center align-center py-4" aria-live="polite">
            <VProgressCircular indeterminate color="primary" aria-label="Loading region data" />
          </div>

          <div v-else-if="selectedRegionId">
            <VRow>
              <VCol cols="12" md="6">
                <VSelect v-model="selectedManagerId" :items="users" item-title="full_name" item-value="id"
                  label="Select Manager" :error-messages="errors.manager" required density="comfortable"
                  variant="outlined" prepend-inner-icon="ri-user-line" :disabled="submitting"
                  aria-label="Select a manager" />
              </VCol>

              <VCol cols="12" md="6">
                <VSelect v-model="selectedManagerTypeId" :items="managerTypes" item-title="setting_value"
                  item-value="id" label="Select Manager Type" :error-messages="errors.managerType" required
                  density="comfortable" variant="outlined" prepend-inner-icon="ri-settings-line" :disabled="submitting"
                  aria-label="Select a manager type" />
              </VCol>
            </VRow>

            <div class="my-4">
              <h3 class="text-subtitle-1 font-weight-medium mb-2" id="select-users-heading">Select Users to Manage:</h3>

              <p v-if="selectedManagerId" class="text-caption mb-3" role="alert">
                <span class="text-red">Note:</span> A manager cannot manage themselves. The selected manager has been
                excluded from the list.
              </p>

              <div v-if="errors.users" class="text-error mb-2" role="alert">
                {{ errors.users }}
              </div>

              <VTextField v-model="searchQuery" density="compact" placeholder="Search users..."
                prepend-inner-icon="ri-search-line" hide-details class="mb-4" single-line :disabled="submitting"
                aria-label="Search for users" />

              <VDataTable v-model="selectedUserIds" :headers="userHeaders" :items="selectableUsers" item-value="id"
                density="comfortable" show-select class="elevation-1 rounded" :disabled="submitting"
                aria-label="Users table" aria-describedby="select-users-heading">
                <!-- ID Column (desktop only) -->
                <template v-if="!isMobile" #[`item.id`]="{ item }">
                  <span>{{ item.id }}</span>
                </template>

                <!-- Full Name Column -->
                <template #[`item.full_name`]="{ item }">
                  <div class="font-weight-medium">
                    {{ item.full_name || 'No name provided' }}
                    <div v-if="isMobile" class="text-caption">
                      {{ item.email }}
                    </div>
                  </div>
                </template>

                <!-- Email Column (desktop only) -->
                <template v-if="!isMobile" #[`item.email`]="{ item }">
                  <div>{{ item.email }}</div>
                </template>

                <!-- Empty state -->
                <template #no-data>
                  <div class="d-flex flex-column align-center pa-6" role="alert" aria-live="polite">
                    <VIcon size="48" color="secondary" icon="ri-user-3-line" class="mb-4" aria-hidden="true" />
                    <h3 class="text-h6 font-weight-regular mb-2">No users available</h3>
                    <p class="text-secondary text-center">
                      <span v-if="searchQuery">No users match your search criteria.</span>
                      <span v-else>There are no users in this region that can be assigned to this manager.</span>
                    </p>
                    <VBtn v-if="searchQuery" color="secondary" @click="searchQuery = ''" aria-label="Clear search"
                      :disabled="submitting">
                      Clear Search
                    </VBtn>
                  </div>
                </template>
              </VDataTable>
            </div>
          </div>

          <VDivider class="my-6" aria-hidden="true" />

          <VCardActions class="pl-0">
            <VBtn color="secondary" variant="outlined" prepend-icon="ri-arrow-left-line" @click="cancel"
              :disabled="submitting" aria-label="Cancel and go back to list">
              Cancel
            </VBtn>

            <VSpacer />

            <VBtn color="primary" type="submit" prepend-icon="ri-save-line" :loading="submitting"
              :disabled="submitting || !selectedRegionId || !selectedManagerId || !selectedManagerTypeId || selectedUserIds.length === 0"
              aria-label="Assign manager to selected users">
              Assign Manager
            </VBtn>
          </VCardActions>
        </VForm>
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

  /* Adjust spacing for small screens */
  .mb-6 {
    margin-block-end: 16px !important;
  }

  .mb-4 {
    margin-block-end: 12px !important;
  }

  .my-6 {
    margin-block: 16px !important;
  }
}
</style>
