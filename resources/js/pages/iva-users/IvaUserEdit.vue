<script setup>
import axios from 'axios';
import { onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const router = useRouter();
const route = useRoute();
const userId = route.params.id;

// Form data
const form = ref({
  full_name: '',
  job_title: '',
  email: '',
  hire_date: null,
  end_date: null,
  region_id: null,
  work_status: null,
  timedoctor_version: '1',
  is_active: true,
  timedoctor_id: '',
});

// Change tracking
const regionChangeInfo = ref({
  reason: '',
  effectiveDate: null,
});

const workStatusChangeInfo = ref({
  reason: '',
  effectiveDate: null,
});

// Original data for comparison
const originalData = ref({});

// Options data
const regions = ref([]);
const workStatusOptions = ref([
  { value: 'Full-time', label: 'Full-time' },
  { value: 'Part-time', label: 'Part-time' },
]);
const timedoctorVersions = ref([
  { value: '1', label: 'Version 1' },
  { value: '2', label: 'Version 2' },
]);

// UI state
const loading = ref(true);
const submitting = ref(false);
const errors = ref({});
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');
const isMobile = ref(window.innerWidth < 768);

// Change dialogs
const regionChangeDialog = ref(false);
const workStatusChangeDialog = ref(false);
const confirmSaveDialog = ref(false);

onMounted(() => {
  fetchUserData();
  window.addEventListener('resize', handleResize);
});

function handleResize() {
  isMobile.value = window.innerWidth < 768;
}

async function fetchUserData() {
  loading.value = true;
  try {
    const response = await axios.get(`/api/admin/iva-users/${userId}`);
    const userData = response.data.user;

    // Store original data
    originalData.value = { ...userData };

    // Populate form
    form.value = {
      full_name: userData.full_name || '',
      job_title: userData.job_title || '',
      email: userData.email || '',
      hire_date: userData.hire_date || null,
      end_date: userData.end_date || null,
      region_id: userData.region_id || null,
      work_status: userData.work_status || null,
      timedoctor_version: String(userData.timedoctor_version || '1'),
      is_active: userData.is_active ?? true,
      timedoctor_id: userData.timedoctor_user?.timedoctor_id || '',
    };

    regions.value = response.data.regions;

    if (response.data.timedoctorVersions && response.data.timedoctorVersions.length > 0) {
      timedoctorVersions.value = response.data.timedoctorVersions.map(v => ({
        value: v.setting_value,
        label: v.description || `Version ${v.setting_value}`,
      }));
    }
  } catch (error) {
    console.error('Error fetching user data:', error);
    snackbarText.value = 'Failed to load user data';
    snackbarColor.value = 'error';
    snackbar.value = true;
    router.push({ name: 'iva-users-list' });
  } finally {
    loading.value = false;
  }
}

// Watch for region changes
watch(() => form.value.region_id, (newValue, oldValue) => {
  if (originalData.value.region_id && newValue !== originalData.value.region_id && oldValue !== undefined) {
    regionChangeDialog.value = true;
  }
});

// Watch for work status changes
watch(() => form.value.work_status, (newValue, oldValue) => {
  if (originalData.value.work_status && newValue !== originalData.value.work_status && oldValue !== undefined) {
    workStatusChangeDialog.value = true;
  }
});

function openSaveConfirmation() {
  confirmSaveDialog.value = true;
}

async function submitForm() {
  errors.value = {};
  submitting.value = true;
  confirmSaveDialog.value = false;

  try {
    const payload = { ...form.value };

    // Add change info if regions changed
    if (form.value.region_id !== originalData.value.region_id) {
      payload.region_change_info = regionChangeInfo.value;
    }

    // Add change info if work status changed
    if (form.value.work_status !== originalData.value.work_status) {
      payload.work_status_change_info = workStatusChangeInfo.value;
    }

    const response = await axios.put(`/api/admin/iva-users/${userId}`, payload);

    snackbarText.value = 'User updated successfully';
    snackbarColor.value = 'success';
    snackbar.value = true;

    // Navigate to user detail page
    setTimeout(() => {
      router.push({ name: 'iva-user-detail', params: { id: userId } });
    }, 1000);
  } catch (error) {
    console.error('Error updating user:', error);

    if (error.response && error.response.data && error.response.data.errors) {
      errors.value = error.response.data.errors;
    } else if (error.response && error.response.data && error.response.data.message) {
      snackbarText.value = error.response.data.message;
      snackbarColor.value = 'error';
      snackbar.value = true;
    } else {
      snackbarText.value = 'Failed to update user';
      snackbarColor.value = 'error';
      snackbar.value = true;
    }
  } finally {
    submitting.value = false;
  }
}

function cancel() {
  router.push({ name: 'iva-user-detail', params: { id: userId } });
}

function confirmRegionChange() {
  regionChangeDialog.value = false;
}

function cancelRegionChange() {
  form.value.region_id = originalData.value.region_id;
  regionChangeInfo.value = { reason: '', effectiveDate: null };
  regionChangeDialog.value = false;
}

function confirmWorkStatusChange() {
  workStatusChangeDialog.value = false;
}

function cancelWorkStatusChange() {
  form.value.work_status = originalData.value.work_status;
  workStatusChangeInfo.value = { reason: '', effectiveDate: null };
  workStatusChangeDialog.value = false;
}
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <VBreadcrumbs :items="[
      { title: 'Home', to: '/' },
      { title: 'IVA Users', to: { name: 'iva-users-list' } },
      { title: 'Edit User', disabled: true }
    ]" class="mb-6" aria-label="Breadcrumb navigation" />

    <VCard v-if="loading">
      <VCardText class="d-flex justify-center align-center pa-6">
        <VProgressCircular indeterminate color="primary" :size="50" :width="5" aria-label="Loading user data" />
      </VCardText>
    </VCard>

    <VCard v-else>
      <VCardText>
        <VForm @submit.prevent="openSaveConfirmation">
          <div class="d-flex flex-wrap align-center mb-6">
            <h1 class="text-h5 text-md-h4 mr-auto mb-2 mb-md-0" tabindex="0">
              Edit IVA User
            </h1>
          </div>

          <VDivider class="mb-6" aria-hidden="true" />

          <VRow>
            <!-- Full Name -->
            <VCol cols="12" md="6">
              <VTextField v-model="form.full_name" label="Full Name" :error-messages="errors.full_name" required
                density="comfortable" variant="outlined" prepend-inner-icon="ri-user-line" :disabled="submitting"
                aria-label="Enter full name" />
            </VCol>

            <!-- Job Title -->
            <VCol cols="12" md="6">
              <VTextField v-model="form.job_title" label="Job Title" :error-messages="errors.job_title"
                density="comfortable" variant="outlined" prepend-inner-icon="ri-briefcase-line" :disabled="submitting"
                aria-label="Enter job title" />
            </VCol>

            <!-- Email -->
            <VCol cols="12" md="6">
              <VTextField v-model="form.email" label="Email Address" type="email" :error-messages="errors.email"
                required density="comfortable" variant="outlined" prepend-inner-icon="ri-mail-line"
                :disabled="submitting" aria-label="Enter email address" />
            </VCol>

            <!-- Hire Date -->
            <VCol cols="12" md="6">
              <VTextField v-model="form.hire_date" label="Hire Date" type="date" :error-messages="errors.hire_date"
                density="comfortable" variant="outlined" prepend-inner-icon="ri-calendar-line" :disabled="submitting"
                aria-label="Select hire date" />
            </VCol>

            <!-- End Date -->
            <VCol cols="12" md="6">
              <VTextField v-model="form.end_date" label="End Date" type="date" :error-messages="errors.end_date"
                density="comfortable" variant="outlined" prepend-inner-icon="ri-calendar-event-line"
                :disabled="submitting" aria-label="Select end date" hint="Leave empty if user is still active"
                persistent-hint />
            </VCol>

            <!-- Region -->
            <VCol cols="12" md="6">
              <VSelect v-model="form.region_id" :items="regions" item-title="name" item-value="id" label="Region"
                :error-messages="errors.region_id" density="comfortable" variant="outlined"
                prepend-inner-icon="ri-map-pin-line" :disabled="submitting" clearable aria-label="Select region"
                hint="Changing region may require additional information" persistent-hint />

              <!-- Region Change Info -->
              <div v-if="form.region_id !== originalData.region_id && form.region_id !== null" class="mt-3">
                <VAlert type="info" variant="tonal" density="compact" class="mb-3">
                  Region change detected. Please provide details below.
                </VAlert>

                <VRow>
                  <VCol cols="12" md="6">
                    <VTextField v-model="regionChangeInfo.reason" label="Reason for Region Change" density="comfortable"
                      variant="outlined" :error-messages="errors['region_change_info.reason']" required
                      aria-label="Reason for region change" />
                  </VCol>
                  <VCol cols="12" md="6">
                    <VTextField v-model="regionChangeInfo.effectiveDate" label="Effective Date" type="date"
                      density="comfortable" variant="outlined"
                      :error-messages="errors['region_change_info.effectiveDate']" required
                      aria-label="Effective date for region change" />
                  </VCol>
                </VRow>
              </div>
            </VCol>

            <!-- Work Status -->
            <VCol cols="12" md="6">
              <VSelect v-model="form.work_status" :items="workStatusOptions" item-title="label" item-value="value"
                label="Work Status" :error-messages="errors.work_status" density="comfortable" variant="outlined"
                prepend-inner-icon="ri-briefcase-line" :disabled="submitting" clearable aria-label="Select work status"
                hint="Changing work status may require additional information" persistent-hint />

              <!-- Work Status Change Info -->
              <div v-if="form.work_status !== originalData.work_status && form.work_status !== null" class="mt-3">
                <VAlert type="info" variant="tonal" density="compact" class="mb-3">
                  Work status change detected. Please provide details below.
                </VAlert>

                <VRow>
                  <VCol cols="12" md="6">
                    <VTextField v-model="workStatusChangeInfo.reason" label="Reason for Work Status Change"
                      density="comfortable" variant="outlined"
                      :error-messages="errors['work_status_change_info.reason']" required
                      aria-label="Reason for work status change" />
                  </VCol>
                  <VCol cols="12" md="6">
                    <VTextField v-model="workStatusChangeInfo.effectiveDate" label="Effective Date" type="date"
                      density="comfortable" variant="outlined"
                      :error-messages="errors['work_status_change_info.effectiveDate']" required
                      aria-label="Effective date for work status change" />
                  </VCol>
                </VRow>
              </div>
            </VCol>

            <!-- TimeDoctor Version -->
            <VCol cols="12" md="6">
              <VSelect v-model="form.timedoctor_version" :items="timedoctorVersions" item-title="label"
                item-value="value" label="TimeDoctor Version" :error-messages="errors.timedoctor_version" required
                density="comfortable" variant="outlined" prepend-inner-icon="ri-time-line" :disabled="submitting"
                aria-label="Select TimeDoctor version" />
            </VCol>

            <!-- TimeDoctor ID (only show if version 1) -->
            <VCol v-if="form.timedoctor_version === '1'" cols="12" md="6">
              <VTextField v-model="form.timedoctor_id" label="TimeDoctor ID" :error-messages="errors.timedoctor_id"
                density="comfortable" variant="outlined" prepend-inner-icon="ri-hashtag" :disabled="submitting"
                aria-label="Enter TimeDoctor ID" hint="TimeDoctor ID for version 1" persistent-hint />
            </VCol>

            <!-- Active Status -->
            <VCol cols="12">
              <VCheckbox v-model="form.is_active" label="Active User" :error-messages="errors.is_active"
                :disabled="submitting" aria-label="Set user as active"
                hint="Active users will be displayed in the region and overall report." persistent-hint />
            </VCol>
          </VRow>

          <VDivider class="my-6" aria-hidden="true" />

          <VCardActions class="pl-0">
            <VBtn color="secondary" variant="outlined" prepend-icon="ri-arrow-left-line" @click="cancel"
              :disabled="submitting" aria-label="Cancel and go back">
              Cancel
            </VBtn>

            <VSpacer />

            <VBtn color="primary" type="submit" prepend-icon="ri-save-line" :loading="submitting" :disabled="submitting"
              aria-label="Update user">
              Update User
            </VBtn>
          </VCardActions>
        </VForm>
      </VCardText>
    </VCard>

    <!-- Region Change Dialog -->
    <VDialog v-model="regionChangeDialog" max-width="500" persistent role="dialog"
      aria-labelledby="region-change-title">
      <VCard>
        <VCardTitle id="region-change-title" class="text-h5 bg-warning text-white d-flex align-center py-3">
          <span>Region Change Information</span>
        </VCardTitle>

        <VCardText class="pt-4">
          <p class="mb-4">You are changing the user's region. Please provide additional information:</p>

          <VTextField v-model="regionChangeInfo.reason" label="Reason for Region Change" required density="comfortable"
            variant="outlined" class="mb-4" aria-label="Enter reason for region change" />

          <VTextField v-model="regionChangeInfo.effectiveDate" label="Effective Date" type="date" required
            density="comfortable" variant="outlined" aria-label="Select effective date for region change" />
        </VCardText>

        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="cancelRegionChange" aria-label="Cancel region change">
            Cancel
          </VBtn>
          <VBtn color="primary" @click="confirmRegionChange"
            :disabled="!regionChangeInfo.reason || !regionChangeInfo.effectiveDate" aria-label="Confirm region change">
            Confirm
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Work Status Change Dialog -->
    <VDialog v-model="workStatusChangeDialog" max-width="500" persistent role="dialog"
      aria-labelledby="work-status-change-title">
      <VCard>
        <VCardTitle id="work-status-change-title" class="text-h5 bg-warning text-white d-flex align-center py-3">
          <span>Work Status Change Information</span>
        </VCardTitle>

        <VCardText class="pt-4">
          <p class="mb-4">You are changing the user's work status. Please provide additional information:</p>

          <VTextField v-model="workStatusChangeInfo.reason" label="Reason for Work Status Change" required
            density="comfortable" variant="outlined" class="mb-4" aria-label="Enter reason for work status change" />

          <VTextField v-model="workStatusChangeInfo.effectiveDate" label="Effective Date" type="date" required
            density="comfortable" variant="outlined" aria-label="Select effective date for work status change" />
        </VCardText>

        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="cancelWorkStatusChange"
            aria-label="Cancel work status change">
            Cancel
          </VBtn>
          <VBtn color="primary" @click="confirmWorkStatusChange"
            :disabled="!workStatusChangeInfo.reason || !workStatusChangeInfo.effectiveDate"
            aria-label="Confirm work status change">
            Confirm
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Save Confirmation Dialog -->
    <VDialog v-model="confirmSaveDialog" max-width="500" persistent role="alertdialog"
      aria-labelledby="confirm-save-title">
      <VCard>
        <VCardTitle id="confirm-save-title" class="text-h5 bg-primary text-white d-flex align-center py-3">
          <span>Confirm Changes</span>
        </VCardTitle>

        <VCardText class="pt-4">
          <p class="mb-4">Are you sure you want to save these changes to the user?</p>

          <div class="mb-3">
            <h4 class="text-subtitle-1 font-weight-medium mb-2">Changes Summary:</h4>

            <ul class="text-body-2">
              <li v-if="form.full_name !== originalData.full_name">
                Name: {{ originalData.full_name }} → {{ form.full_name }}
              </li>
              <li v-if="form.job_title !== originalData.job_title">
                Job Title: {{ originalData.job_title || 'None' }} → {{ form.job_title || 'None' }}
              </li>
              <li v-if="form.email !== originalData.email">
                Email: {{ originalData.email }} → {{ form.email }}
              </li>
              <li v-if="form.region_id !== originalData.region_id">
                Region changed (requires reason and effective date)
              </li>
              <li v-if="form.work_status !== originalData.work_status">
                Work Status changed (requires reason and effective date)
              </li>
              <li v-if="form.is_active !== originalData.is_active">
                Status: {{ originalData.is_active ? 'Active' : 'Inactive' }} → {{ form.is_active ? 'Active' : 'Inactive'
                }}
              </li>
            </ul>
          </div>

          <p class="text-body-2 text-secondary mb-0">This action will update the user's information and create a
            changelog entry.</p>
        </VCardText>

        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="confirmSaveDialog = false" aria-label="Cancel save">
            Cancel
          </VBtn>
          <VBtn color="primary" @click="submitForm" :loading="submitting" aria-label="Confirm save">
            Save Changes
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
  :deep(.v-card-text) {
    padding: 16px;
  }

  /* Adjust spacing for small screens */
  .mb-6 {
    margin-block-end: 16px !important;
  }

  .my-6 {
    margin-block: 16px !important;
  }
}
</style>
