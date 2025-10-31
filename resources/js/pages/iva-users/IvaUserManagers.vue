<script setup>
import { useAuthStore } from '@/@core/stores/auth';
import axios from 'axios';
import { ref } from 'vue';

const authStore = useAuthStore();

const props = defineProps({
  user: {
    type: Object,
    required: true
  },
  managerTypes: {
    type: Array,
    default: () => []
  },
  isMobile: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['manager-added', 'manager-removed']);

// Dialog states
const managerDialog = ref(false);
const deleteManagerDialog = ref(false);
const managerToDelete = ref(null);

// Manager form
const managerForm = ref({
  manager_id: null,
  manager_type_id: null,
  region_id: null
});

// Available managers
const availableManagers = ref([]);

function openDeleteManagerDialog(manager) {
  managerToDelete.value = manager;
  deleteManagerDialog.value = true;
}

function confirmDeleteManager() {
  emit('manager-removed', managerToDelete.value);
  deleteManagerDialog.value = false;
  managerToDelete.value = null;
}

function addManager() {
  emit('manager-added', managerForm.value);
  managerDialog.value = false;
  managerForm.value = { manager_id: null, manager_type_id: null, region_id: null };
}

async function openManagerDialog() {
  try {
    const response = await axios.get(`/api/admin/iva-users/${props.user.id}/available-managers`);
    availableManagers.value = response.data.managers;
    managerForm.value.region_id = props.user.region_id;
    managerDialog.value = true;
  } catch (error) {
    console.error('Error fetching available managers:', error);
  }
}

function getFilteredAvailableManagers() {
  if (!availableManagers.value || !props.user) return [];

  return availableManagers.value.filter(manager =>
    manager.region_id === props.user.region_id &&
    manager.id !== props.user.id
  );
}
</script>

<template>
  <VCard>
    <VCardText>
      <div class="d-flex align-center mb-4">
        <h2 class="text-h6 font-weight-medium">Assigned Managers</h2>
        <VSpacer />
        <VBtn v-if="authStore.hasPermission('edit_iva_data')" color="primary" prepend-icon="ri-add-line" @click="openManagerDialog" aria-label="Add manager">
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
                  <VBtn v-if="authStore.hasPermission('edit_iva_data')" icon size="small" color="error" variant="text" @click="openDeleteManagerDialog(manager)"
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
        <VBtn v-if="authStore.hasPermission('edit_iva_data')" color="primary" @click="openManagerDialog" aria-label="Add first manager">
          Add First Manager
        </VBtn>
      </div>
    </VCardText>
  </VCard>

  <!-- Manager Assignment Dialog -->
  <VDialog v-model="managerDialog" max-width="600" persistent>
    <VCard>
      <VCardTitle class="text-h5 bg-primary text-white">
        Assign Manager
      </VCardTitle>

      <VCardText class="pt-4">
        <VRow>
          <VCol cols="12">
            <VSelect v-model="managerForm.manager_id" :items="getFilteredAvailableManagers()" item-title="full_name"
              item-value="id" label="Select Manager" density="comfortable" variant="outlined" required />
            <p v-if="getFilteredAvailableManagers().length === 0" class="text-caption text-warning mt-2">
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
</template>
