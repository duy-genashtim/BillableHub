<script setup>
import axios from 'axios';
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const router = useRouter();
const route = useRoute();
const settingId = route.params.id;

const setting = ref(null);
const form = ref({
  setting_value: '',
  description: '',
  order: 0
});

const loading = ref(true);
const errors = ref({});
const submitting = ref(false);
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

onMounted(fetchSetting);

async function fetchSetting() {
  loading.value = true;
  try {
    const response = await axios.get(`/api/configuration/${settingId}`);
    setting.value = response.data.setting;
    
    // Populate the form
    form.value = {
      setting_value: setting.value.setting_value,
      description: setting.value.description || '',
      order: setting.value.order || 0
    };
  } catch (error) {
    console.error('Error fetching setting:', error);
    snackbarText.value = 'Failed to load setting';
    snackbarColor.value = 'error';
    snackbar.value = true;
    router.push({ name: 'configuration-list' });
  } finally {
    loading.value = false;
  }
}

async function submitForm() {
  submitting.value = true;
  errors.value = {};
  
  try {
    const response = await axios.put(`/api/configuration/${settingId}`, form.value);
    snackbarText.value = 'Setting updated successfully';
    snackbarColor.value = 'success';
    snackbar.value = true;
    
    // Navigate to the settings list after a short delay
    setTimeout(() => {
      router.push({ name: 'configuration-list' });
    }, 1000);
  } catch (error) {
    if (error.response && error.response.data && error.response.data.errors) {
      errors.value = error.response.data.errors;
    } else if (error.response && error.response.data && error.response.data.error) {
      snackbarText.value = error.response.data.error;
      snackbarColor.value = 'error';
      snackbar.value = true;
    } else {
      snackbarText.value = 'Failed to update setting';
      snackbarColor.value = 'error';
      snackbar.value = true;
    }
  } finally {
    submitting.value = false;
  }
}

function cancel() {
  router.push({ name: 'configuration-list' });
}

function getCategoryLabel(category) {
  const categories = {
    site: 'Site Settings',
    user: 'User Settings',
    report: 'Report Settings',
    'report-time': 'Report Time Settings',
    'report-cat': 'Report Category Settings',
    system: 'System Settings',
    other: 'Other Settings'
  };
  
  return categories[category] || category;
}
</script>

<template>
  <!-- Breadcrumbs -->
  <VBreadcrumbs :items="[
    { title: 'Home', to: '/' },
    { title: 'Configuration Settings', to: { name: 'configuration-list' } },
    { title: 'Edit Setting', disabled: true }
  ]" class="mb-6" />
  
  <VCard>
    <VCardText>
      <VForm @submit.prevent="submitForm">
        <div class="d-flex align-center mb-6">
          <h1 class="text-h4">
            Edit Setting
          </h1>
        </div>
        
        <VRow v-if="loading">
          <VCol cols="12" class="d-flex justify-center">
            <VProgressCircular
              indeterminate
              color="primary"
              :size="50"
              :width="5"
            />
          </VCol>
        </VRow>
        
        <div v-else>
          <VRow>
            <VCol cols="12" md="6">
              <VTextField
                :model-value="getCategoryLabel(setting.setting_type.setting_category)"
                label="Category"
                density="comfortable"
                variant="outlined"
                prepend-inner-icon="ri-folder-line"
                disabled
                readonly
              />
            </VCol>
            
            <VCol cols="12" md="6">
              <VTextField
                :model-value="setting.setting_type.name"
                label="Setting Type"
                density="comfortable"
                variant="outlined"
                prepend-inner-icon="ri-list-settings-line"
                disabled
                readonly
              />
            </VCol>
            
            <VCol cols="12" md="6">
              <VTextField
                v-model.number="form.order"
                label="Display Order"
                placeholder="Enter display order (lower numbers appear first)"
                :error-messages="errors.order"
                type="number"
                min="0"
                density="comfortable"
                variant="outlined"
                prepend-inner-icon="ri-sort-asc"
              />
            </VCol>
            
            <VCol cols="12" md="6">
              <VTextField
                v-model="form.setting_value"
                label="Value"
                placeholder="Enter setting value"
                :error-messages="errors.setting_value"
                required
                density="comfortable"
                variant="outlined"
                prepend-inner-icon="ri-input-method-line"
              />
            </VCol>
            
            <VCol cols="12">
              <VTextarea
                v-model="form.description"
                label="Description"
                placeholder="Enter setting description"
                :error-messages="errors.description"
                rows="4"
                density="comfortable"
                variant="outlined"
                prepend-inner-icon="ri-text-wrap"
              />
            </VCol>
            
            <!-- System setting badge -->
            <VCol v-if="setting.is_system" cols="12">
              <VAlert
                type="info"
                variant="tonal"
                density="compact"
                border="start"
                class="mb-0"
              >
                <div class="d-flex align-center">
                  <VIcon icon="ri-information-line" class="mr-2" />
                  <div>
                    <span class="font-weight-medium">System Setting</span> - This is a default system setting and cannot be deleted.
                  </div>
                </div>
              </VAlert>
            </VCol>
            
            <!-- User customizable badge -->
            <VCol v-if="setting.setting_type.for_user_customize" cols="12">
              <VAlert
                type="success"
                variant="tonal"
                density="compact"
                border="start"
                class="mb-0"
              >
                <div class="d-flex align-center">
                  <VIcon icon="ri-user-settings-line" class="mr-2" />
                  <div>
                    <span class="font-weight-medium">User Customizable</span> - Users can customize this setting for their personal preferences.
                  </div>
                </div>
              </VAlert>
            </VCol>
          </VRow>

          <VDivider class="my-6" />
          
          <VCardActions class="pl-0">
            <VBtn
              color="secondary"
              variant="outlined"
              prepend-icon="ri-arrow-left-line"
              @click="cancel"
              :disabled="submitting"
            >
              Cancel
            </VBtn>
            
            <VSpacer />
            
            <VBtn
              color="primary"
              type="submit"
              prepend-icon="ri-save-line"
              :loading="submitting"
              :disabled="submitting || !setting.setting_type.allow_edit"
            >
              Update Setting
            </VBtn>
          </VCardActions>
        </div>
      </VForm>
    </VCardText>
  </VCard>
  
  <!-- Snackbar for notifications -->
  <VSnackbar
    v-model="snackbar"
    :color="snackbarColor"
    :timeout="3000"
  >
    {{ snackbarText }}
    <template #actions>
      <VBtn
        icon
        variant="text"
        @click="snackbar = false"
      >
        <VIcon>ri-close-line</VIcon>
      </VBtn>
    </template>
  </VSnackbar>
</template>
