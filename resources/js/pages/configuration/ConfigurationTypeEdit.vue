<script setup>
import axios from 'axios';
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const router = useRouter();
const route = useRoute();
const typeId = route.params.id;
const categories = ref({
  'site': 'Site Settings',
  'user': 'User Settings',
  'report': 'Report Settings',
  'report-time': 'Report Time Settings',
  'report-cat': 'Report Category Settings',
  'system': 'System Settings',
  'other': 'Other Settings'
});

const settingType = ref(null);
const form = ref({
  key: '',
  name: '',
  description: '',
  setting_category: '',
  for_user_customize: false,
  allow_edit: false,     // Default to false instead of true
  allow_delete: false,   // Default to false instead of true
  allow_create: false,   // Default to false instead of true
});

const loading = ref(true);
const errors = ref({});
const submitting = ref(false);
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');
const isMobile = ref(window.innerWidth < 768);

onMounted(() => {
  fetchType();
  window.addEventListener('resize', handleResize);
});

function handleResize() {
  isMobile.value = window.innerWidth < 768;
}

async function fetchType() {
  loading.value = true;
  try {
    const response = await axios.get(`/api/configuration/types/${typeId}`);
    settingType.value = response.data.settingType;
    
    // Populate the form with exact values from the database
    form.value = {
      key: settingType.value.key,
      name: settingType.value.name,
      description: settingType.value.description || '',
      setting_category: settingType.value.setting_category,
      for_user_customize: Boolean(settingType.value.for_user_customize),
      allow_edit: Boolean(settingType.value.allow_edit),
      allow_delete: Boolean(settingType.value.allow_delete),
      allow_create: Boolean(settingType.value.allow_create),
    };
  } catch (error) {
    console.error('Error fetching setting type:', error);
    snackbarText.value = 'Failed to load setting type';
    snackbarColor.value = 'error';
    snackbar.value = true;
    router.push({ name: 'configuration-types' });
  } finally {
    loading.value = false;
  }
}

async function submitForm() {
  submitting.value = true;
  errors.value = {};
  
  try {
    const response = await axios.put(`/api/configuration/types/${typeId}`, form.value);
    snackbarText.value = 'Setting type updated successfully';
    snackbarColor.value = 'success';
    snackbar.value = true;
    
    // Navigate to the types list after a short delay
    setTimeout(() => {
      router.push({ name: 'configuration-types' });
    }, 1000);
  } catch (error) {
    if (error.response && error.response.data && error.response.data.errors) {
      errors.value = error.response.data.errors;
    } else {
      snackbarText.value = 'Failed to update setting type';
      snackbarColor.value = 'error';
      snackbar.value = true;
    }
  } finally {
    submitting.value = false;
  }
}

function cancel() {
  router.push({ name: 'configuration-types' });
}
</script>

<template>
  <!-- Breadcrumbs -->
  <VBreadcrumbs :items="[
    { title: 'Home', to: '/' },
    { title: 'Configuration Settings', to: { name: 'configuration-list' } },
    { title: 'Setting Types', to: { name: 'configuration-types' } },
    { title: 'Edit Type', disabled: true }
  ]" class="mb-6" />
  
  <VCard>
    <VCardText>
      <VForm @submit.prevent="submitForm">
        <div class="d-flex align-center mb-6">
          <h1 class="text-h5 text-md-h4">
            Edit Setting Type
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
        
        <VRow v-else>
          <VCol cols="12" md="6">
            <VTextField
              v-model="form.name"
              label="Display Name"
              placeholder="Enter the display name for this setting type"
              :error-messages="errors.name"
              required
              density="comfortable"
              variant="outlined"
              prepend-inner-icon="ri-text-wrap"
            />
          </VCol>
          
          <VCol cols="12" md="6">
            <VTextField
              v-model="form.key"
              label="Key"
              placeholder="Enter a unique key for this setting type"
              :error-messages="errors.key"
              required
              density="comfortable"
              variant="outlined"
              prepend-inner-icon="ri-code-line"
              hint="A unique identifier for this setting type (snake_case)"
              persistent-hint
            />
          </VCol>
          
          <VCol cols="12" md="6">
            <VSelect
              v-model="form.setting_category"
              label="Category"
              :items="Object.entries(categories).map(([value, title]) => ({ title, value }))"
              item-title="title"
              item-value="value"
              :error-messages="errors.setting_category"
              required
              density="comfortable"
              variant="outlined"
              prepend-inner-icon="ri-folder-line"
            />
          </VCol>
          
          <VCol cols="12" md="6">
            <VSwitch
              v-model="form.for_user_customize"
              label="Allow User Customization"
              color="primary"
              :error-messages="errors.for_user_customize"
              hint="When enabled, users can customize values of this setting type for their personal preferences"
              persistent-hint
            />
          </VCol>
          
          <VCol cols="12">
            <VTextarea
              v-model="form.description"
              label="Description"
              placeholder="Enter a description for this setting type"
              :error-messages="errors.description"
              rows="3"
              density="comfortable"
              variant="outlined"
              prepend-inner-icon="ri-information-line"
            />
          </VCol>
          
          <VCol cols="12">
            <div class="d-flex flex-column">
              <div class="text-subtitle-1 font-weight-medium mb-3">Permissions</div>
              
              <VRow class="ml-1 ml-md-2">
                <VCol cols="12" :md="isMobile ? 12 : 4">
                  <VSwitch
                    v-model="form.allow_create"
                    label="Allow Creating New Settings"
                    color="success"
                    :error-messages="errors.allow_create"
                  />
                </VCol>
                
                <VCol cols="12" :md="isMobile ? 12 : 4">
                  <VSwitch
                    v-model="form.allow_edit"
                    label="Allow Editing Settings"
                    color="success"
                    :error-messages="errors.allow_edit"
                  />
                </VCol>
                
                <VCol cols="12" :md="isMobile ? 12 : 4">
                  <VSwitch
                    v-model="form.allow_delete"
                    label="Allow Deleting Settings"
                    color="success"
                    :error-messages="errors.allow_delete"
                  />
                </VCol>
              </VRow>
            </div>
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
            :disabled="submitting"
          >
            Update Setting Type
          </VBtn>
        </VCardActions>
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

<style scoped>
@media (max-width: 767px) {
  :deep(.v-card-text) {
    padding-block: 16px;
    padding-inline: 12px;
  }

  :deep(.v-input) {
    margin-block-end: 8px;
  }
}
</style>
