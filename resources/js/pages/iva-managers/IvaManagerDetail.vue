<script setup>
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const router = useRouter();
const route = useRoute();
const id = route.params.id;

const manager = ref(null);
const users = ref([]);
const availableUsers = ref([]);
const loading = ref(true);
const loadingAvailableUsers = ref(false);
const searchQuery = ref('');
const dialogSearchQuery = ref('');
const isMobile = ref(window.innerWidth < 768);

const removeUserDialog = ref(false);
const userToRemove = ref(null);
const addUsersDialog = ref(false);
const selectedUsers = ref([]);
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

// Headers for data table
const userHeaders = computed(() => {
  if (isMobile.value) {
    return [
      { title: 'Name', key: 'full_name', sortable: true },
      { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
    ];
  } else {
    return [
      { title: 'ID', key: 'id', sortable: true },
      { title: 'Name', key: 'full_name', sortable: true },
      { title: 'Email', key: 'email', sortable: true },
      { title: 'Status', key: 'is_active', sortable: true },
      { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
    ];
  }
});

// Headers for available users dialog
const availableUserHeaders = computed(() => {
  if (isMobile.value) {
    return [
      { title: 'Name', key: 'full_name', sortable: true },
    ];
  } else {
    return [
      { title: 'ID', key: 'id', sortable: true },
      { title: 'Name', key: 'full_name', sortable: true },
      { title: 'Email', key: 'email', sortable: true },
    ];
  }
});

onMounted(() => {
  fetchManagerDetails();
  window.addEventListener('resize', handleResize);
});

function handleResize() {
  isMobile.value = window.innerWidth < 768;
}

async function fetchManagerDetails() {
  loading.value = true;
  try {
    const response = await axios.get(`/api/admin/iva-managers/${id}`);
    manager.value = response.data.manager;
    users.value = response.data.users;
  } catch (error) {
    console.error('Error fetching manager details:', error);
    snackbarText.value = 'Failed to load manager details';
    snackbarColor.value = 'error';
    snackbar.value = true;
    router.push({ name: 'iva-managers-list' });
  } finally {
    loading.value = false;
  }
}

async function fetchAvailableUsers() {
  loadingAvailableUsers.value = true;
  try {
    const response = await axios.get(`/api/admin/iva-managers/${id}/available-users`);
    availableUsers.value = response.data.availableUsers;
  } catch (error) {
    console.error('Error fetching available users:', error);
    snackbarText.value = 'Failed to load available users';
    snackbarColor.value = 'error';
    snackbar.value = true;
  } finally {
    loadingAvailableUsers.value = false;
  }
}

function openAddUsersDialog() {
  fetchAvailableUsers();
  selectedUsers.value = [];
  dialogSearchQuery.value = '';
  addUsersDialog.value = true;
}

async function addUsersToManager() {
  if (selectedUsers.value.length === 0) {
    snackbarText.value = 'Please select at least one user';
    snackbarColor.value = 'warning';
    snackbar.value = true;
    return;
  }

  try {
    await axios.post(`/api/admin/iva-managers/${id}/users`, {
      user_ids: selectedUsers.value
    });

    snackbarText.value = `${selectedUsers.value.length} users added to manager successfully`;
    snackbarColor.value = 'success';
    snackbar.value = true;

    // Refresh the manager details
    fetchManagerDetails();
    addUsersDialog.value = false;
  } catch (error) {
    console.error('Error adding users to manager:', error);
    if (error.response && error.response.data && error.response.data.message) {
      snackbarText.value = error.response.data.message;
    } else {
      snackbarText.value = 'Failed to add users to manager';
    }
    snackbarColor.value = 'error';
    snackbar.value = true;
  }
}

function confirmRemoveUser(user) {
  userToRemove.value = user;
  removeUserDialog.value = true;
}

async function removeUser() {
  try {
    await axios.delete(`/api/admin/iva-managers/${id}/users`, {
      data: { user_id: userToRemove.value.id }
    });

    snackbarText.value = 'User removed from manager successfully';
    snackbarColor.value = 'success';
    snackbar.value = true;
    fetchManagerDetails();
  } catch (error) {
    console.error('Error removing user from manager:', error);
    snackbarText.value = 'Failed to remove user from manager';
    snackbarColor.value = 'error';
    snackbar.value = true;
  } finally {
    removeUserDialog.value = false;
    userToRemove.value = null;
  }
}

function goBack() {
  router.push({ name: 'iva-managers-list' });
}

function closeAddUsersDialog() {
  addUsersDialog.value = false;
  selectedUsers.value = [];
  dialogSearchQuery.value = '';
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

const filteredAvailableUsers = computed(() => {
  if (!dialogSearchQuery.value || !availableUsers.value) return availableUsers.value;

  const query = dialogSearchQuery.value.toLowerCase();
  return availableUsers.value.filter(user =>
    (user.full_name && user.full_name.toLowerCase().includes(query)) ||
    (user.email && user.email.toLowerCase().includes(query)) ||
    (user.id && user.id.toString().includes(query))
  );
});

function getUserStatusColor(isActive) {
  return isActive ? 'success' : 'error';
}

function getUserStatusText(isActive) {
  return isActive ? 'Active' : 'Inactive';
}
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <VBreadcrumbs :items="[
      { title: 'Home', to: '/' },
      { title: 'IVA Managers', to: { name: 'iva-managers-list' } },
      { title: manager ? (manager.manager ? manager.manager.full_name : 'Manager Details') : 'Manager Details', disabled: true }
    ]" class="mb-4 mb-md-6" aria-label="Navigation breadcrumbs" />

    <VCard v-if="loading">
      <VCardText class="d-flex justify-center align-center pa-6">
        <VProgressCircular indeterminate color="primary" :size="50" :width="5" aria-label="Loading manager details" />
      </VCardText>
    </VCard>

    <template v-else-if="manager">
      <!-- Manager Information Card -->
      <VCard class="mb-4 mb-md-6">
        <VCardText>
          <div class="d-flex flex-column flex-md-row align-start align-md-center mb-4 gap-3">
            <div class="flex-grow-1">
              <h1 class="text-h5 text-md-h4 mb-2" tabindex="0" aria-level="1">
                Manager Details
              </h1>
            </div>
            <VBtn color="secondary" variant="outlined" :prepend-icon="isMobile ? undefined : 'ri-arrow-left-line'"
              :icon="isMobile ? 'ri-arrow-left-line' : false" @click="goBack" aria-label="Back to managers list">
              <span v-if="!isMobile">Back to List</span>
            </VBtn>
          </div>

          <VDivider class="mb-4" aria-hidden="true" />

          <VRow>
            <VCol cols="12" md="6">
              <div class="mb-3 mb-md-4">
                <div class="text-subtitle-1 font-weight-medium mb-1" id="manager-name-label">Name:</div>
                <p class="text-body-1 font-weight-medium" tabindex="0" aria-labelledby="manager-name-label">
                  {{ manager.manager ? manager.manager.full_name : 'Unknown' }}
                </p>
              </div>
            </VCol>

            <VCol cols="12" md="6">
              <div class="mb-3 mb-md-4">
                <div class="text-subtitle-1 font-weight-medium mb-1" id="manager-email-label">Email:</div>
                <p class="text-body-1" tabindex="0" aria-labelledby="manager-email-label">
                  {{ manager.manager ? manager.manager.email : 'Unknown' }}
                </p>
              </div>
            </VCol>

            <VCol cols="12" md="6">
              <div class="mb-3 mb-md-4">
                <div class="text-subtitle-1 font-weight-medium mb-1" id="manager-type-label">Manager Type:</div>
                <VChip size="small" color="primary" text-color="white" aria-labelledby="manager-type-label"
                  tabindex="0">
                  {{ manager.managerType ? manager.managerType.setting_value : 'Unknown' }}
                </VChip>
              </div>
            </VCol>

            <VCol cols="12" md="6">
              <div class="mb-3 mb-md-4">
                <div class="text-subtitle-1 font-weight-medium mb-1" id="manager-region-label">Region:</div>
                <VChip size="small" color="secondary" text-color="white" aria-labelledby="manager-region-label"
                  tabindex="0">
                  {{ manager.region ? manager.region.name : 'Unknown' }}
                </VChip>
              </div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- Users Card -->
      <VCard>
        <VCardText>
          <div class="d-flex flex-column flex-md-row align-start align-md-center mb-4 gap-3">
            <h2 class="text-h6 text-md-h5 flex-grow-1" tabindex="0" aria-level="2">
              Managed Users ({{ users.length }})
            </h2>
            <VBtn color="primary" :prepend-icon="isMobile ? undefined : 'ri-user-add-line'"
              :icon="isMobile ? 'ri-user-add-line' : false" @click="openAddUsersDialog"
              aria-label="Add users to manager">
              <span v-if="!isMobile">Add Users</span>
            </VBtn>
          </div>

          <VTextField v-model="searchQuery" density="compact" placeholder="Search users..."
            prepend-inner-icon="ri-search-line" hide-details class="mb-4" single-line variant="outlined"
            aria-label="Search managed users" />

          <VDataTable :headers="userHeaders" :items="filteredUsers" :loading="loading" density="comfortable" hover
            class="elevation-1 rounded" :items-per-page="isMobile ? 5 : 10" role="table"
            aria-label="Managed users table">
            <!-- ID Column (desktop only) -->
            <template v-if="!isMobile" #[`item.id`]="{ item }">
              <span>{{ item.id }}</span>
            </template>

            <!-- Full Name Column -->
            <template #[`item.full_name`]="{ item }">
              <div class="font-weight-medium">
                {{ item.full_name || 'No name provided' }}
                <!-- Show email and status on mobile -->
                <div v-if="isMobile" class="text-caption text-medium-emphasis mt-1">
                  {{ item.email }}
                </div>
                <div v-if="isMobile" class="mt-1">
                  <VChip size="x-small" :color="getUserStatusColor(item.is_active)" text-color="white"
                    :aria-label="`User status: ${getUserStatusText(item.is_active)}`">
                    {{ getUserStatusText(item.is_active) }}
                  </VChip>
                </div>
              </div>
            </template>

            <!-- Email Column (desktop only) -->
            <template v-if="!isMobile" #[`item.email`]="{ item }">
              <div>{{ item.email }}</div>
            </template>

            <!-- Status Column (desktop only) -->
            <template v-if="!isMobile" #[`item.is_active`]="{ item }">
              <VChip size="small" :color="getUserStatusColor(item.is_active)" text-color="white"
                :aria-label="`User status: ${getUserStatusText(item.is_active)}`">
                {{ getUserStatusText(item.is_active) }}
              </VChip>
            </template>

            <!-- Actions Column -->
            <template #[`item.actions`]="{ item }">
              <VBtn icon size="small" variant="text" color="error" @click="confirmRemoveUser(item)"
                :aria-label="`Remove ${item.full_name} from manager`">
                <VIcon size="20">ri-link-unlink</VIcon>
                <VTooltip activator="parent" location="top">Remove User</VTooltip>
              </VBtn>
            </template>

            <!-- Empty state -->
            <template #no-data>
              <div class="d-flex flex-column align-center pa-6" role="alert" aria-live="polite">
                <VIcon size="48" color="secondary" icon="ri-user-3-line" class="mb-4" aria-hidden="true" />
                <h3 class="text-h6 font-weight-regular mb-2" tabindex="0">No users found</h3>
                <p class="text-secondary text-center mb-4">
                  <span v-if="searchQuery">No users match your search criteria.</span>
                  <span v-else>This manager is not assigned to any users.</span>
                </p>
                <div class="d-flex gap-2 flex-wrap">
                  <VBtn v-if="searchQuery" color="secondary" variant="outlined" @click="searchQuery = ''"
                    aria-label="Clear search">
                    Clear Search
                  </VBtn>
                  <VBtn color="primary" @click="openAddUsersDialog" aria-label="Add users to manager">
                    Add Users
                  </VBtn>
                </div>
              </div>
            </template>
          </VDataTable>
        </VCardText>
      </VCard>
    </template>

    <!-- Dialog for adding users -->
    <VDialog v-model="addUsersDialog" :max-width="isMobile ? '95vw' : '800'" :fullscreen="isMobile" role="dialog"
      aria-labelledby="add-users-dialog-title">
      <VCard>
        <VCardTitle id="add-users-dialog-title" class="text-h5 bg-primary text-white d-flex align-center py-3">
          <span>Add Users to {{ manager ? (manager.manager ? manager.manager.full_name : 'Manager') : '' }}</span>
          <VSpacer />
          <VBtn icon variant="text" color="white" @click="closeAddUsersDialog" class="ml-2" aria-label="Close dialog">
            <VIcon>ri-close-line</VIcon>
          </VBtn>
        </VCardTitle>

        <VCardText class="pt-4">
          <div v-if="loadingAvailableUsers" class="d-flex justify-center py-4">
            <VProgressCircular indeterminate color="primary" aria-label="Loading available users" />
          </div>

          <div v-else>
            <!-- Search and action buttons at the top -->
            <div class="d-flex flex-column flex-md-row align-start align-md-center mb-4 gap-3">
              <VTextField v-model="dialogSearchQuery" density="compact" placeholder="Search users..."
                prepend-inner-icon="ri-search-line" hide-details single-line variant="outlined" class="flex-grow-1"
                aria-label="Search available users" />

              <div class="d-flex gap-2" :class="{ 'w-100': isMobile }">
                <VBtn color="secondary" variant="outlined" @click="closeAddUsersDialog" :block="isMobile"
                  aria-label="Cancel adding users">
                  Cancel
                </VBtn>

                <VBtn color="primary" :disabled="loadingAvailableUsers || selectedUsers.length === 0"
                  @click="addUsersToManager" :block="isMobile" aria-label="Add selected users to manager">
                  Add Selected ({{ selectedUsers.length }})
                </VBtn>
              </div>
            </div>

            <VDataTable v-model="selectedUsers" :headers="availableUserHeaders" :items="filteredAvailableUsers"
              item-value="id" density="comfortable" show-select class="elevation-1 rounded"
              :items-per-page="isMobile ? 5 : 10" role="table" aria-label="Available users table">
              <!-- ID Column (desktop only) -->
              <template v-if="!isMobile" #[`item.id`]="{ item }">
                <span>{{ item.id }}</span>
              </template>

              <!-- Full Name Column -->
              <template #[`item.full_name`]="{ item }">
                <div class="font-weight-medium">
                  {{ item.full_name || 'No name provided' }}
                  <div v-if="isMobile" class="text-caption text-medium-emphasis mt-1">
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
                  <h3 class="text-h6 font-weight-regular mb-2" tabindex="0">No available users</h3>
                  <p class="text-secondary text-center">
                    <span v-if="dialogSearchQuery">No users match your search criteria.</span>
                    <span v-else>All users in this region are already assigned to this manager or no additional users
                      are available.</span>
                  </p>
                  <VBtn v-if="dialogSearchQuery" color="secondary" variant="outlined" @click="dialogSearchQuery = ''"
                    aria-label="Clear search" class="mt-3">
                    Clear Search
                  </VBtn>
                </div>
              </template>
            </VDataTable>
          </div>
        </VCardText>

        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="closeAddUsersDialog" aria-label="Cancel adding users">
            Cancel
          </VBtn>
          <VBtn color="primary" @click="addUsersToManager"
            :disabled="loadingAvailableUsers || selectedUsers.length === 0" aria-label="Add selected users to manager">
            Add Selected Users
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog for removing users -->
    <VDialog v-model="removeUserDialog" max-width="500" role="alertdialog" aria-labelledby="remove-user-title">
      <VCard>
        <VCardTitle id="remove-user-title" class="text-h5 bg-error text-white d-flex align-center py-3">
          <span>Remove User</span>
        </VCardTitle>

        <VCardText class="pt-4">
          <p>Are you sure you want to remove this user from the manager?</p>
          <div v-if="userToRemove" class="my-3 pa-3 bg-grey-lighten-4 rounded">
            <p class="mb-1"><strong>User:</strong> {{ userToRemove.full_name }}</p>
            <p class="mb-0"><strong>Email:</strong> {{ userToRemove.email }}</p>
          </div>
          <p class="mt-2 text-body-2">
            This will only remove the management relationship. The user will remain in the region.
          </p>
        </VCardText>

        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="removeUserDialog = false"
            aria-label="Cancel removing user">
            Cancel
          </VBtn>
          <VBtn color="error" @click="removeUser" aria-label="Confirm removing user">
            Remove
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
@media (max-width: 767px) {

  /* Mobile optimizations */
  :deep(.v-data-table) {
    font-size: 0.875rem;
  }

  :deep(.v-data-table .v-data-table__td) {
    padding: 8px;
  }

  :deep(.v-btn--icon) {
    block-size: 32px;
    inline-size: 32px;
    min-inline-size: 32px;
  }

  :deep(.v-card-text) {
    padding: 16px;
  }

  /* Adjust spacing for small screens */
  .mb-4 {
    margin-block-end: 12px !important;
  }

  .mb-md-4 {
    margin-block-end: 12px !important;
  }

  .mb-md-6 {
    margin-block-end: 16px !important;
  }
}

/* Focus indicators */
:deep(.v-btn:focus-visible) {
  outline: 2px solid var(--v-theme-primary);
  outline-offset: 2px;
}

:deep(.v-data-table tr:focus-visible) {
  outline: 2px solid var(--v-theme-primary);
  outline-offset: -2px;
}
</style>
