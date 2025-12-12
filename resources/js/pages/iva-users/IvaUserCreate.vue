<script setup>
import { useAuthStore } from '@/@core/stores/auth';
import axios from 'axios';
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

const authStore = useAuthStore();
const router = useRouter();

// Form data
const user = ref({
  full_name: '',
  job_title: '',
  email: '',
  hire_date: null,
  region_id: null,
  cohort_id: null,
  work_status: null,
  timedoctor_version: null,
  is_active: true
});

// Data for dropdowns
const regions = ref([]);
const cohorts = ref([]);
const workStatusOptions = ref([]);
const timedoctorOptions = ref([]);

// UI state
const loading = ref(true);
const submitting = ref(false);
const isMobile = ref(window.innerWidth < 768);
const errors = ref({});
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

onMounted(() => {
  fetchFormData();
  window.addEventListener('resize', handleResize);
});

function handleResize() {
  isMobile.value = window.innerWidth < 768;
}

async function fetchFormData() {
  loading.value = true;

  try {
    // Get regions
    const regionsResponse = await axios.get('/api/admin/regions');
    regions.value = regionsResponse.data.regions;

    // Get cohorts
    const cohortsResponse = await axios.get('/api/admin/cohorts');
    if (cohortsResponse.data.cohorts) {
      cohorts.value = cohortsResponse.data.cohorts;
    }
    // get TimeDoctor versions
    const configTimedoctor = await axios.get('/api/configuration', {
      params: { type_key: 'timedoctor_version' }
    });

    if (configTimedoctor.data.settings && configTimedoctor.data.settings.length > 0) {
      timedoctorOptions.value = configTimedoctor.data.settings.map(setting => ({
        value: setting.setting_value,
        label: setting.description || setting.setting_value
      }));
    } else {
      // Fallback to default options
      timedoctorOptions.value = [
        { value: 1, title: 'TimeDoctor v1' },
        { value: 2, title: 'TimeDoctor v2' }
      ];
    }

    // Get work status options from configuration
    const configResponse = await axios.get('/api/configuration', {
      params: { type_key: 'work_status' }
    });

    if (configResponse.data.settings && configResponse.data.settings.length > 0) {
      workStatusOptions.value = configResponse.data.settings.map(setting => ({
        value: setting.setting_value,
        label: setting.description || setting.setting_value
      }));
    } else {
      // Fallback to default options
      workStatusOptions.value = [
        { value: 'full-time', label: 'Full Time' },
        { value: 'part-time', label: 'Part Time' }
      ];
    }
  } catch (error) {
    console.error('Error fetching form data:', error);
    snackbarText.value = 'Failed to load form data';
    snackbarColor.value = 'error';
    snackbar.value = true;

  } finally {
    loading.value = false;
  }
}

async function submitForm() {
  // Basic validation
  errors.value = {};

  if (!user.value.full_name) {
    errors.value.full_name = ['Name is required'];
    return;
  }

  if (!user.value.email) {
    errors.value.email = ['Email is required'];
    return;
  }

  if (!user.value.timedoctor_version) {
    errors.value.timedoctor_version = ['TimeDoctor version is required'];
    return;
  }

  submitting.value = true;

  try {
    const response = await axios.post('/api/admin/iva-users', user.value);

    snackbarText.value = 'User created successfully';
    snackbarColor.value = 'success';
    snackbar.value = true;

    // Navigate to the user detail page
    setTimeout(() => {
      router.push({
        name: 'iva-user-detail',
        params: { id: response.data.user.id }
      });
    }, 1000);
  } catch (error) {
    console.error('Error creating user:', error);

    if (error.response && error.response.data && error.response.data.errors) {
      errors.value = error.response.data.errors;
    } else {
      snackbarText.value = error.response?.data?.message || 'Failed to create user';
      snackbarColor.value = 'error';
      snackbar.value = true;
    }
  } finally {
    submitting.value = false;
  }
}

function cancel() {
  router.push({ name: 'iva-users-list' });
}
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <VBreadcrumbs :items="[
      { title: 'Home', to: '/' },
      { title: 'IVA Users', to: { name: 'iva-users-list' } },
      { title: 'Create User', disabled: true }
    ]" class="mb-6" aria-label="Breadcrumb navigation" />

    <VCard>
      <VCardText>
        <div class="d-flex flex-wrap align-center mb-6">
          <h1 class="text-h5 text-md-h4 mr-auto mb-2 mb-md-0" tabindex="0">
            Create New User
          </h1>
        </div>

        <VDivider class="mb-4" aria-hidden="true" />

        <div v-if="loading" class="d-flex justify-center align-center py-8">
          <VProgressCircular indeterminate color="primary" :size="50" :width="5" aria-label="Loading form data" />
        </div>

        <VForm v-else @submit.prevent="submitForm">
          <VRow>
            <VCol cols="12" md="6">
              <VTextField v-model="user.full_name" density="comfortable" label="Full Name" variant="outlined"
                :error-messages="errors.full_name" required autofocus aria-label="User's full name" />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField v-model="user.job_title" density="comfortable" label="Job Title" variant="outlined"
                :error-messages="errors.job_title" aria-label="User's job title" />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField v-model="user.email" density="comfortable" label="Email" variant="outlined"
                :error-messages="errors.email" required type="email" aria-label="User's email address" />
            </VCol>

            <VCol cols="12" md="6">
              <VSelect v-model="user.region_id" :items="regions" item-title="name" item-value="id" label="Region"
                density="comfortable" variant="outlined" :error-messages="errors.region_id" clearable
                aria-label="User's region" />
            </VCol>

            <VCol cols="12" md="6">
              <VSelect v-model="user.cohort_id" :items="cohorts" item-title="name" item-value="id" label="Cohort"
                density="comfortable" variant="outlined" :error-messages="errors.cohort_id" clearable
                aria-label="User's cohort" />
            </VCol>

            <VCol cols="12" md="6">
              <VSelect v-model="user.work_status" :items="workStatusOptions" item-title="label" item-value="value"
                label="Work Status" density="comfortable" variant="outlined" :error-messages="errors.work_status"
                clearable aria-label="User's work status" />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField v-model="user.hire_date" density="comfortable" label="Hire Date" variant="outlined"
                type="date" :error-messages="errors.hire_date" aria-label="User's hire date" />
            </VCol>

            <VCol cols="12" md="6">
              <VSelect v-model="user.timedoctor_version" :items="timedoctorOptions" item-title="label"
                item-value="value" label="TimeDoctor Version" density="comfortable" variant="outlined"
                :error-messages="errors.timedoctor_version" required aria-label="User's TimeDoctor version" />
            </VCol>

            <VCol cols="12" md="6">
              <VSwitch v-model="user.is_active" color="success" label="User is Active" inset hide-details
                aria-label="User status" :false-value="false" :true-value="true" />
            </VCol>
          </VRow>

          <VDivider class="my-6" aria-hidden="true" />

          <VCardActions class="pl-0">
            <VBtn color="secondary" variant="outlined" prepend-icon="ri-arrow-left-line" @click="cancel"
              :disabled="submitting" aria-label="Cancel and go back to list">
              Cancel
            </VBtn>

            <VSpacer />

            <VBtn color="primary" type="submit" prepend-icon="ri-save-line" :loading="submitting" :disabled="submitting || !authStore.hasPermission('edit_iva_data')"
              aria-label="Create new user">
              Create User
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
}
</style>
