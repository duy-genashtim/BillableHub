<script setup>
import axios from 'axios';
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();

// Form data
const syncType = ref('manager');
const specificEmails = ref(['']);
const cooEmail = ref('');
const cooName = ref('');

// UI state
const loading = ref(true);
const syncing = ref(false);
const isMobile = ref(window.innerWidth < 768);
const errors = ref({});
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

// Sync results
const syncResults = ref(null);
const timedoctorResults = ref(null);
const showResults = ref(false);

onMounted(() => {
  fetchCooEmail();
  window.addEventListener('resize', handleResize);
});

function handleResize() {
  isMobile.value = window.innerWidth < 768;
}

async function fetchCooEmail() {
  loading.value = true;

  try {
    const response = await axios.get('/api/admin/iva-users/coo-email');
    cooEmail.value = response.data.email;
    cooName.value = response.data.name;
  } catch (error) {
    console.error('Error fetching COO email:', error);
    snackbarText.value = 'Failed to load COO email configuration';
    snackbarColor.value = 'error';
    snackbar.value = true;
  } finally {
    loading.value = false;
  }
}

function addEmailField() {
  specificEmails.value.push('');
}

function removeEmailField(index) {
  if (specificEmails.value.length > 1) {
    specificEmails.value.splice(index, 1);
  }
}

function validateEmails() {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  const validEmails = specificEmails.value.filter(email => email.trim() !== '');

  for (const email of validEmails) {
    if (!emailRegex.test(email)) {
      return false;
    }
  }

  return validEmails.length > 0;
}

async function syncUsers() {
  errors.value = {};
  showResults.value = false;

  if (syncType.value === 'specific') {
    if (!validateEmails()) {
      errors.value.emails = ['Please provide at least one valid email address'];
      return;
    }
  }

  syncing.value = true;

  try {
    const payload = {
      sync_type: syncType.value
    };

    if (syncType.value === 'specific') {
      payload.emails = specificEmails.value.filter(email => email.trim() !== '');
    }

    const response = await axios.post('/api/admin/iva-users/sync', payload);

    syncResults.value = response.data.results;
    timedoctorResults.value = response.data.timedoctor_results || null;
    showResults.value = true;

    snackbarText.value = response.data.message;
    snackbarColor.value = 'success';
    snackbar.value = true;

  } catch (error) {
    console.error('Error syncing users:', error);

    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors;
    } else {
      snackbarText.value = error.response?.data?.message || 'Failed to sync users';
      snackbarColor.value = 'error';
      snackbar.value = true;
    }
  } finally {
    syncing.value = false;
  }
}

function goBack() {
  router.push({ name: 'iva-users-list' });
}

function goToUsersList() {
  router.push({ name: 'iva-users-list' });
}

function resetForm() {
  syncType.value = 'manager';
  specificEmails.value = [''];
  errors.value = {};
  showResults.value = false;
  syncResults.value = null;
  timedoctorResults.value = null;
}
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <VBreadcrumbs :items="[
      { title: 'Home', to: '/' },
      { title: 'IVA Users', to: { name: 'iva-users-list' } },
      { title: 'Sync Users', disabled: true }
    ]" class="mb-6" aria-label="Breadcrumb navigation" />

    <VCard>
      <VCardText>
        <div class="d-flex flex-wrap align-center mb-6">
          <h1 class="text-h5 text-md-h4 mr-auto mb-2 mb-md-0" tabindex="0">
            Sync IVA Users from API
          </h1>
        </div>

        <VDivider class="mb-4" aria-hidden="true" />

        <div v-if="loading" class="d-flex justify-center align-center py-8">
          <VProgressCircular indeterminate color="primary" :size="50" :width="5" aria-label="Loading configuration" />
        </div>

        <div v-else>
          <!-- Sync Options -->
          <div class="mb-6">
            <h2 class="text-h6 mb-4">Select Sync Option</h2>

            <VRadioGroup v-model="syncType" :disabled="syncing">
              <VRadio value="manager" label="Get Manager + All Subordinates" />
              <VRadio value="specific" label="Get Specific Employees Only" />
            </VRadioGroup>
          </div>

          <!-- Manager Option Details -->
          <div v-if="syncType === 'manager'" class="mb-6">
            <VCard variant="outlined" class="pa-4">
              <h3 class="text-subtitle-1 mb-3">Manager Details</h3>

              <VRow>
                <VCol cols="12" md="6">
                  <VTextField :model-value="cooEmail" label="COO Email" readonly density="comfortable"
                    variant="outlined" prepend-inner-icon="ri-mail-line" />
                </VCol>
                <VCol cols="12" md="6">
                  <VTextField :model-value="cooName" label="COO Name" readonly density="comfortable" variant="outlined"
                    prepend-inner-icon="ri-user-line" />
                </VCol>
              </VRow>

              <VAlert type="info" variant="tonal" density="compact" class="mt-3">
                <template #prepend>
                  <VIcon icon="ri-information-line" />
                </template>
                This will use the COO email of ToCert. Please change the COO email in system settings if it's not
                correct.
              </VAlert>
            </VCard>
          </div>

          <!-- Specific Employees Option -->
          <div v-if="syncType === 'specific'" class="mb-6">
            <VCard variant="outlined" class="pa-4">
              <h3 class="text-subtitle-1 mb-3">Employee Email Addresses</h3>

              <div class="mb-4">
                <VRow v-for="(email, index) in specificEmails" :key="index" class="mb-2">
                  <VCol cols="12" md="10">
                    <VTextField v-model="specificEmails[index]" :label="`Email ${index + 1}`" type="email"
                      density="comfortable" variant="outlined" prepend-inner-icon="ri-mail-line" :disabled="syncing"
                      :error-messages="errors.emails && index === 0 ? errors.emails : []" />
                  </VCol>
                  <VCol cols="12" md="2" class="d-flex align-center">
                    <VBtn v-if="specificEmails.length > 1" icon variant="text" color="error" size="small"
                      @click="removeEmailField(index)" :disabled="syncing" aria-label="Remove email field">
                      <VIcon>ri-delete-bin-line</VIcon>
                    </VBtn>
                    <VBtn v-if="index === specificEmails.length - 1" icon variant="text" color="primary" size="small"
                      @click="addEmailField" :disabled="syncing" aria-label="Add email field">
                      <VIcon>ri-add-line</VIcon>
                    </VBtn>
                  </VCol>
                </VRow>
              </div>

              <VAlert type="warning" variant="tonal" density="compact">
                <template #prepend>
                  <VIcon icon="ri-alert-line" />
                </template>
                Please ensure all email addresses are valid and formatted correctly.
              </VAlert>
            </VCard>
          </div>

          <!-- Sync Results -->
          <div v-if="showResults && syncResults" class="mb-6">
            <VCard variant="outlined" class="pa-4">
              <h3 class="text-subtitle-1 mb-3">Sync Results</h3>

              <!-- Summary -->
              <VRow class="mb-4">
                <VCol cols="12" md="4">
                  <VCard variant="tonal" color="success">
                    <VCardText class="text-center">
                      <h4 class="text-h4 font-weight-bold">{{ syncResults.total_created }}</h4>
                      <p class="text-body-2 mb-0">Users Created</p>
                    </VCardText>
                  </VCard>
                </VCol>
                <VCol cols="12" md="4">
                  <VCard variant="tonal" color="info">
                    <VCardText class="text-center">
                      <h4 class="text-h4 font-weight-bold">{{ syncResults.total_updated }}</h4>
                      <p class="text-body-2 mb-0">Users Updated</p>
                    </VCardText>
                  </VCard>
                </VCol>
                <VCol cols="12" md="4">
                  <VCard variant="tonal" color="warning">
                    <VCardText class="text-center">
                      <h4 class="text-h4 font-weight-bold">{{ syncResults.total_warnings }}</h4>
                      <p class="text-body-2 mb-0">Warnings</p>
                    </VCardText>
                  </VCard>
                </VCol>
              </VRow>

              <!-- Created Users -->
              <div v-if="syncResults.created.length > 0" class="mb-4">
                <h4 class="text-subtitle-2 mb-2 text-success">
                  <VIcon icon="ri-add-circle-line" class="mr-2" />
                  Created Users ({{ syncResults.created.length }})
                </h4>
                <VCard variant="tonal" color="success" class="pa-3">
                  <VList density="compact">
                    <VListItem v-for="user in syncResults.created" :key="user.id" class="px-0">
                      <VListItemTitle>{{ user.full_name }}</VListItemTitle>
                      <VListItemSubtitle>{{ user.email }} • {{ user.job_title || 'No Job Title' }}</VListItemSubtitle>
                    </VListItem>
                  </VList>
                </VCard>
              </div>

              <!-- Updated Users -->
              <div v-if="syncResults.updated.length > 0" class="mb-4">
                <h4 class="text-subtitle-2 mb-2 text-info">
                  <VIcon icon="ri-refresh-line" class="mr-2" />
                  Updated Users ({{ syncResults.updated.length }})
                </h4>
                <VCard variant="tonal" color="info" class="pa-3">
                  <VList density="compact">
                    <VListItem v-for="user in syncResults.updated" :key="user.id" class="px-0">
                      <VListItemTitle>{{ user.full_name }}</VListItemTitle>
                      <VListItemSubtitle>{{ user.email }} • {{ user.job_title || 'No Job Title' }}</VListItemSubtitle>
                    </VListItem>
                  </VList>
                </VCard>
              </div>

              <!-- Work Status Warnings -->
              <div v-if="syncResults.work_status_warnings.length > 0" class="mb-4">
                <h4 class="text-subtitle-2 mb-2 text-warning">
                  <VIcon icon="ri-alert-line" class="mr-2" />
                  Work Status Update Required ({{ syncResults.work_status_warnings.length }})
                </h4>
                <VCard variant="tonal" color="warning" class="pa-3">
                  <VList density="compact">
                    <VListItem v-for="warning in syncResults.work_status_warnings" :key="warning.email" class="px-0">
                      <VListItemTitle>{{ warning.user }}</VListItemTitle>
                      <VListItemSubtitle>
                        {{ warning.email }} • {{ warning.message }}
                      </VListItemSubtitle>
                    </VListItem>
                  </VList>
                </VCard>
                <VAlert type="info" variant="tonal" density="compact" class="mt-3">
                  <template #prepend>
                    <VIcon icon="ri-information-line" />
                  </template>
                  Please manually update the work status for these users in the IVA User Management section with the
                  appropriate
                  change date.
                </VAlert>
              </div>
            </VCard>
          </div>

          <!-- TimeDoctor Task Sync Results -->
          <div v-if="showResults && timedoctorResults && timedoctorResults.length > 0" class="mb-6">
            <VCard variant="outlined" class="pa-4">
              <h3 class="text-subtitle-1 mb-3">
                <VIcon icon="ri-task-line" class="mr-2" />
                TimeDoctor Task Sync Results
              </h3>

              <!-- TimeDoctor Summary -->
              <VRow class="mb-4">
                <VCol cols="12" md="4">
                  <VCard variant="tonal" color="success">
                    <VCardText class="text-center">
                      <h4 class="text-h4 font-weight-bold">{{ timedoctorResults.filter(r => r.found_in_timedoctor).length }}</h4>
                      <p class="text-body-2 mb-0">Found in TimeDoctor</p>
                    </VCardText>
                  </VCard>
                </VCol>
                <VCol cols="12" md="4">
                  <VCard variant="tonal" color="warning">
                    <VCardText class="text-center">
                      <h4 class="text-h4 font-weight-bold">{{ timedoctorResults.filter(r => !r.found_in_timedoctor).length }}</h4>
                      <p class="text-body-2 mb-0">Not Found</p>
                    </VCardText>
                  </VCard>
                </VCol>
                <VCol cols="12" md="4">
                  <VCard variant="tonal" color="info">
                    <VCardText class="text-center">
                      <h4 class="text-h4 font-weight-bold">{{ timedoctorResults.reduce((sum, r) => sum + (r.tasks_synced || 0), 0) }}</h4>
                      <p class="text-body-2 mb-0">Total Tasks Synced</p>
                    </VCardText>
                  </VCard>
                </VCol>
              </VRow>

              <!-- Successfully Synced Users -->
              <div v-if="timedoctorResults.filter(r => r.found_in_timedoctor && r.sync_success).length > 0" class="mb-4">
                <h4 class="text-subtitle-2 mb-2 text-success">
                  <VIcon icon="ri-check-circle-line" class="mr-2" />
                  Successfully Synced ({{ timedoctorResults.filter(r => r.found_in_timedoctor && r.sync_success).length }})
                </h4>
                <VCard variant="tonal" color="success" class="pa-3">
                  <VList density="compact">
                    <VListItem v-for="result in timedoctorResults.filter(r => r.found_in_timedoctor && r.sync_success)" :key="result.email" class="px-0">
                      <VListItemTitle>{{ result.email }}</VListItemTitle>
                      <VListItemSubtitle>
                        <VIcon icon="ri-check-line" size="small" class="mr-1" />
                        {{ result.tasks_synced }} tasks synced • {{ result.message }}
                      </VListItemSubtitle>
                    </VListItem>
                  </VList>
                </VCard>
              </div>

              <!-- Users Not Found in TimeDoctor -->
              <div v-if="timedoctorResults.filter(r => !r.found_in_timedoctor).length > 0" class="mb-4">
                <h4 class="text-subtitle-2 mb-2 text-warning">
                  <VIcon icon="ri-alert-line" class="mr-2" />
                  Not Found in TimeDoctor V1 ({{ timedoctorResults.filter(r => !r.found_in_timedoctor).length }})
                </h4>
                <VCard variant="tonal" color="warning" class="pa-3">
                  <VList density="compact">
                    <VListItem v-for="result in timedoctorResults.filter(r => !r.found_in_timedoctor)" :key="result.email" class="px-0">
                      <VListItemTitle>{{ result.email }}</VListItemTitle>
                      <VListItemSubtitle>
                        <VIcon icon="ri-close-line" size="small" class="mr-1" />
                        {{ result.message }}
                      </VListItemSubtitle>
                    </VListItem>
                  </VList>
                </VCard>
                <VAlert type="info" variant="tonal" density="compact" class="mt-3">
                  <template #prepend>
                    <VIcon icon="ri-information-line" />
                  </template>
                  These users need to be synced in TimeDoctor V1 first before their tasks can be synced. Please check the TimeDoctor Integration page.
                </VAlert>
              </div>

              <!-- Sync Errors -->
              <div v-if="timedoctorResults.filter(r => r.found_in_timedoctor && !r.sync_success).length > 0" class="mb-4">
                <h4 class="text-subtitle-2 mb-2 text-error">
                  <VIcon icon="ri-error-warning-line" class="mr-2" />
                  Sync Errors ({{ timedoctorResults.filter(r => r.found_in_timedoctor && !r.sync_success).length }})
                </h4>
                <VCard variant="tonal" color="error" class="pa-3">
                  <VList density="compact">
                    <VListItem v-for="result in timedoctorResults.filter(r => r.found_in_timedoctor && !r.sync_success)" :key="result.email" class="px-0">
                      <VListItemTitle>{{ result.email }}</VListItemTitle>
                      <VListItemSubtitle>
                        <VIcon icon="ri-error-warning-line" size="small" class="mr-1" />
                        {{ result.message }}
                      </VListItemSubtitle>
                    </VListItem>
                  </VList>
                </VCard>
              </div>
            </VCard>
          </div>

          <!-- Action Buttons -->
          <VCardActions class="pl-0">
            <VBtn color="secondary" variant="outlined" prepend-icon="ri-arrow-left-line" @click="goBack"
              :disabled="syncing" aria-label="Go back to users list">
              Back to Users
            </VBtn>

            <VBtn v-if="showResults" color="info" variant="outlined" prepend-icon="ri-refresh-line" @click="resetForm"
              :disabled="syncing" aria-label="Reset form">
              Sync Again
            </VBtn>

            <VSpacer />

            <VBtn v-if="showResults" color="primary" prepend-icon="ri-eye-line" @click="goToUsersList"
              aria-label="View users list">
              View Users List
            </VBtn>

            <VBtn v-if="!showResults" color="primary" prepend-icon="ri-refresh-line" @click="syncUsers"
              :loading="syncing" :disabled="syncing" aria-label="Start sync">
              Start Sync
            </VBtn>
          </VCardActions>
        </div>
      </VCardText>
    </VCard>

    <!-- Snackbar for notifications -->
    <VSnackbar v-model="snackbar" :color="snackbarColor" :timeout="5000" role="alert" aria-live="assertive">
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

  .mb-6 {
    margin-block-end: 16px !important;
  }

  .mb-4 {
    margin-block-end: 12px !important;
  }
}
</style>
