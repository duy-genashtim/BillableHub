<script setup>
import { formatDate } from '@/@core/utils/helpers';
import axios from 'axios';
import { computed, ref } from 'vue';

const props = defineProps({
  user: {
    type: Object,
    required: true
  },
  customizationTypes: {
    type: Array,
    default: () => []
  },
  isMobile: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['customization-added', 'customization-updated', 'customization-removed']);

// Dialog states
const customizationDialog = ref(false);
const editCustomizationDialog = ref(false);
const deleteCustomizationDialog = ref(false);
const customizationToDelete = ref(null);
const customizationToEdit = ref(null);

// Customization forms
const customizationForm = ref({
  setting_id: null,
  custom_value: '',
  start_date: null,
  end_date: null
});

const editCustomizationForm = ref({
  id: null,
  setting_id: null,
  custom_value: '',
  start_date: null,
  end_date: null
});

// Computed property for available settings for customization
const availableCustomizationSettings = computed(() => {
  if (!props.customizationTypes) return [];

  // Get all existing customization setting IDs for this user
  const existingSettingIds = props.user?.customizations?.map(c => c.setting_id) || [];

  return props.customizationTypes.flatMap(type =>
    type.settings
      .filter(setting => !existingSettingIds.includes(setting.id))
      .map(setting => ({
        value: setting.id,
        title: `${type.name}: ${setting.setting_value}`,
        subtitle: setting.description
      }))
  );
});

function openDeleteCustomizationDialog(customization) {
  customizationToDelete.value = customization;
  deleteCustomizationDialog.value = true;
}

function openEditCustomizationDialog(customization) {
  customizationToEdit.value = customization;
  editCustomizationForm.value = {
    id: customization.id,
    setting_id: customization.setting_id,
    custom_value: customization.custom_value,
    start_date: customization.start_date,
    end_date: customization.end_date
  };
  editCustomizationDialog.value = true;
}

function confirmDeleteCustomization() {
  emit('customization-removed', customizationToDelete.value);
  deleteCustomizationDialog.value = false;
  customizationToDelete.value = null;
}

function addCustomization() {
  emit('customization-added', customizationForm.value);
  customizationDialog.value = false;
  customizationForm.value = { setting_id: null, custom_value: '', start_date: null, end_date: null };
}

async function updateCustomization() {
  try {
    const payload = {
      customizations: [{
        setting_id: editCustomizationForm.value.setting_id,
        custom_value: editCustomizationForm.value.custom_value,
        start_date: editCustomizationForm.value.start_date,
        end_date: editCustomizationForm.value.end_date
      }]
    };

    const response = await axios.post(`/api/admin/iva-users/${props.user.id}/customizations`, payload);

    emit('customization-updated', response.data.customizations);
    editCustomizationDialog.value = false;
    customizationToEdit.value = null;
  } catch (error) {
    console.error('Error updating customization:', error);
  }
}

function getSettingDisplayName(customization) {
  const settingType = customization.setting?.setting_type;
  const settingValue = customization.setting?.setting_value;

  if (settingType?.name && settingValue) {
    return `${settingType.name}: ${settingValue}`;
  }

  return settingValue || 'Custom Setting';
}

function getSelectedSettingHint() {
  if (!customizationForm.value.setting_id) return 'Select a setting to see description';

  const selectedSetting = availableCustomizationSettings.value.find(
    setting => setting.value === customizationForm.value.setting_id
  );

  return selectedSetting?.subtitle || 'Enter your custom value for this setting';
}

function getEditSettingHint() {
  if (!customizationToEdit.value) return '';
  return customizationToEdit.value.setting?.description || 'Enter your custom value for this setting';
}

function formatDateRange(startDate, endDate) {
  if (!startDate && !endDate) return 'No date restrictions';

  const start = startDate ? formatDate(startDate) : 'No start';
  const end = endDate ? formatDate(endDate) : 'No end';

  return `${start} - ${end}`;
}

function isCustomizationActive(customization) {
  const now = new Date();
  const startDate = customization.start_date ? new Date(customization.start_date) : null;
  const endDate = customization.end_date ? new Date(customization.end_date) : null;

  if (startDate && now < startDate) return false;
  if (endDate && now > endDate) return false;

  return true;
}
</script>

<template>
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
        <VRow>
          <VCol v-for="customization in user.customizations" :key="customization.id" cols="12" md="6" lg="4">
            <VCard variant="outlined"
              :class="{ 'border-success': isCustomizationActive(customization), 'border-warning': !isCustomizationActive(customization) }">
              <VCardText>
                <div class="d-flex align-center mb-2">
                  <VAvatar :color="isCustomizationActive(customization) ? 'success' : 'warning'" variant="tonal"
                    class="mr-3">
                    <VIcon icon="ri-settings-line" />
                  </VAvatar>
                  <div class="flex-grow-1">
                    <h3 class="text-subtitle-1 font-weight-medium mb-1">
                      {{ getSettingDisplayName(customization) }}
                    </h3>
                    <VChip :color="isCustomizationActive(customization) ? 'success' : 'warning'" size="small"
                      variant="flat" text-color="white">
                      {{ isCustomizationActive(customization) ? 'Active' : 'Inactive' }}
                    </VChip>
                  </div>
                  <VMenu>
                    <template #activator="{ props }">
                      <VBtn icon size="small" variant="text" v-bind="props">
                        <VIcon size="20">ri-more-2-line</VIcon>
                      </VBtn>
                    </template>
                    <VList density="compact">
                      <VListItem prepend-icon="ri-edit-line" title="Edit"
                        @click="openEditCustomizationDialog(customization)" />
                      <VListItem prepend-icon="ri-delete-bin-line" title="Delete"
                        @click="openDeleteCustomizationDialog(customization)" />
                    </VList>
                  </VMenu>
                </div>

                <VDivider class="my-3" />

                <div class="text-body-2">
                  <p class="mb-2"><strong>Custom Value:</strong> {{ customization.custom_value }}</p>
                  <p class="mb-2"><strong>Period:</strong></p>
                  <p class="text-caption mb-2">{{ formatDateRange(customization.start_date, customization.end_date) }}
                  </p>
                  <p v-if="customization.setting?.description" class="text-caption text-secondary mb-0">
                    {{ customization.setting.description }}
                  </p>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
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

  <!-- Add Customization Dialog -->
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

  <!-- Edit Customization Dialog -->
  <VDialog v-model="editCustomizationDialog" max-width="600" persistent>
    <VCard>
      <VCardTitle class="text-h5 bg-info text-white">
        Edit Custom Setting
      </VCardTitle>

      <VCardText class="pt-4">
        <VRow>
          <VCol cols="12">
            <VTextField :model-value="getSettingDisplayName(customizationToEdit)" label="Setting" density="comfortable"
              variant="outlined" readonly hint="Setting type cannot be changed" persistent-hint />
          </VCol>

          <VCol cols="12">
            <VTextField v-model="editCustomizationForm.custom_value" label="Custom Value" density="comfortable"
              variant="outlined" required :hint="getEditSettingHint()" persistent-hint />
          </VCol>

          <VCol cols="12" md="6">
            <VTextField v-model="editCustomizationForm.start_date" label="Start Date" type="date" density="comfortable"
              variant="outlined" hint="Optional - when this setting becomes effective" persistent-hint />
          </VCol>

          <VCol cols="12" md="6">
            <VTextField v-model="editCustomizationForm.end_date" label="End Date" type="date" density="comfortable"
              variant="outlined" hint="Optional - when this setting expires" persistent-hint />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn color="secondary" variant="outlined" @click="editCustomizationDialog = false">
          Cancel
        </VBtn>
        <VBtn color="info" @click="updateCustomization" :disabled="!editCustomizationForm.custom_value">
          Update Setting
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
          <p class="mb-1"><strong>Period:</strong> {{ formatDateRange(customizationToDelete.start_date,
            customizationToDelete.end_date) }}</p>
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
</template>

<style scoped>
.border-success {
  border: 2px solid rgb(var(--v-theme-success)) !important;
}

.border-warning {
  border: 2px solid rgb(var(--v-theme-warning)) !important;
}
</style>
