<script setup>
import {
  SETTING_CATEGORIES
} from '@/@core/utils/siteConsts';
import axios from 'axios';
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
const router = useRouter();
const route = useRoute();
const settingId = route.params.id;

const setting = ref(null);
const loading = ref(true);
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');
const categories = ref(SETTING_CATEGORIES);

onMounted(fetchSettingDetails);

async function fetchSettingDetails() {
  loading.value = true;
  try {
    const response = await axios.get(`/api/configuration/${settingId}`);
    setting.value = response.data.setting;
  } catch (error) {
    console.error('Error fetching setting details:', error);
    snackbarText.value = 'Failed to load setting details';
    snackbarColor.value = 'error';
    snackbar.value = true;
    router.push({ name: 'configuration-list' });
  } finally {
    loading.value = false;
  }
}

function editSetting() {
  router.push({ name: 'configuration-edit', params: { id: settingId } });
}

function goBack() {
  router.push({ name: 'configuration-list' });
}

function getStatusColor(isActive) {
  return isActive ? 'success' : 'error';
}

function getStatusText(isActive) {
  return isActive ? 'Active' : 'Inactive';
}

function getCategoryLabel(category) {
  return categories[category] || category;
}
</script>

<template>
  <div>
    <VBreadcrumbs :items="[
      { title: 'Home', to: '/' },
      { title: 'Configuration Settings', to: { name: 'configuration-list' } },
      { title: 'Setting Details', disabled: true }
    ]" class="mb-6" />

    <VCard v-if="loading">
      <VCardText class="d-flex justify-center align-center pa-6">
        <VProgressCircular indeterminate color="primary" :size="50" :width="5" />
      </VCardText>
    </VCard>

    <template v-else>
      <VCard>
        <VCardText>
          <div class="d-flex align-center mb-4">
            <h1 class="text-h4 mr-auto">
              Setting Details
            </h1>
            <VChip size="small" :color="getStatusColor(setting.is_active)" text-color="white" class="mr-4">
              {{ getStatusText(setting.is_active) }}
            </VChip>
            <VBtn v-if="setting.setting_type && setting.setting_type.allow_edit" color="secondary" variant="outlined"
              prepend-icon="ri-pencil-line" @click="editSetting">
              Edit
            </VBtn>
          </div>

          <VDivider class="mb-4" />

          <VRow>
            <VCol cols="12" md="6">
              <div class="mb-4">
                <div class="text-subtitle-1 font-weight-medium mb-1">Category:</div>
                <VChip color="secondary" variant="flat">
                  {{ getCategoryLabel(setting.setting_type.setting_category) }}
                </VChip>
              </div>
            </VCol>

            <VCol cols="12" md="6">
              <div class="mb-4">
                <div class="text-subtitle-1 font-weight-medium mb-1">Type:</div>
                <VChip color="primary" variant="flat">
                  {{ setting.setting_type.name }}
                </VChip>
              </div>
            </VCol>

            <VCol cols="12" md="6">
              <div class="mb-4">
                <div class="text-subtitle-1 font-weight-medium mb-1">Value:</div>
                <p class="text-body-1 font-weight-medium">{{ setting.setting_value }}</p>
              </div>
            </VCol>

            <VCol cols="12" md="6">
              <div class="mb-4">
                <div class="text-subtitle-1 font-weight-medium mb-1">Display Order:</div>
                <p class="text-body-1">{{ setting.order }}</p>
              </div>
            </VCol>

            <VCol cols="12" md="6">
              <div class="mb-4">
                <div class="text-subtitle-1 font-weight-medium mb-1">Added By:</div>
                <p class="text-body-1">{{ setting.added_by }}</p>
              </div>
            </VCol>

            <VCol cols="12">
              <div class="mb-4">
                <div class="text-subtitle-1 font-weight-medium mb-1">Description:</div>
                <p v-if="setting.description" class="text-body-1">{{ setting.description }}</p>
                <p v-else class="text-body-2 text-disabled">No description provided</p>
              </div>
            </VCol>

            <VCol cols="12" md="6">
              <div class="mb-4">
                <div class="text-subtitle-1 font-weight-medium mb-1">Created At:</div>
                <p class="text-body-1">{{ new Date(setting.created_at).toLocaleString() }}</p>
              </div>
            </VCol>

            <VCol cols="12" md="6">
              <div class="mb-4">
                <div class="text-subtitle-1 font-weight-medium mb-1">Last Updated:</div>
                <p class="text-body-1">{{ new Date(setting.updated_at).toLocaleString() }}</p>
              </div>
            </VCol>

            <!-- System setting badge -->
            <VCol v-if="setting.is_system" cols="12">
              <VAlert type="info" variant="tonal" density="compact" border="start" class="mb-0">
                <div class="d-flex align-center">
                  <VIcon icon="ri-information-line" class="mr-2" />
                  <div>
                    <span class="font-weight-medium">System Setting</span> - This is a default system setting and cannot
                    be deleted.
                  </div>
                </div>
              </VAlert>
            </VCol>

            <!-- Permission alerts -->
            <VCol v-if="!setting.setting_type.allow_edit" cols="12">
              <VAlert type="warning" variant="tonal" density="compact" border="start" class="mb-0">
                <div class="d-flex align-center">
                  <VIcon icon="ri-alert-line" class="mr-2" />
                  <div>
                    <span class="font-weight-medium">Edit Restricted</span> - This type of setting cannot be edited.
                  </div>
                </div>
              </VAlert>
            </VCol>

            <VCol v-if="!setting.setting_type.allow_delete" cols="12">
              <VAlert type="warning" variant="tonal" density="compact" border="start" class="mb-0">
                <div class="d-flex align-center">
                  <VIcon icon="ri-alert-line" class="mr-2" />
                  <div>
                    <span class="font-weight-medium">Delete Restricted</span> - This type of setting cannot be deleted.
                  </div>
                </div>
              </VAlert>
            </VCol>

            <VCol v-if="setting.setting_type.for_user_customize" cols="12">
              <VAlert type="success" variant="tonal" density="compact" border="start" class="mb-0">
                <div class="d-flex align-center">
                  <VIcon icon="ri-user-settings-line" class="mr-2" />
                  <div>
                    <span class="font-weight-medium">User Customizable</span> - Users can customize this setting for
                    their personal preferences.
                  </div>
                </div>
              </VAlert>
            </VCol>
          </VRow>

          <VDivider class="my-4" />

          <div class="d-flex justify-end">
            <VBtn color="secondary" variant="outlined" prepend-icon="ri-arrow-left-line" @click="goBack">
              Back to Settings
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </template>

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
