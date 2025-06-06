<script setup>
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const router = useRouter();
const route = useRoute();
const types = ref([]);
const categories = ref({
  site: 'Site Settings',
  user: 'User Settings',
  report: 'Report Settings',
  'report-time': 'Report Time Settings',
  'report-cat': 'Report Category Settings',
  system: 'System Settings',
  other: 'Other Settings'
});
const currentCategory = ref(null);

const form = ref({
  setting_type_id: route.query.type_id || '',
  setting_value: '',
  description: '',
  order: 0
});

const errors = ref({});
const submitting = ref(false);
const loading = ref(true);
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

onMounted(fetchTypes);

async function fetchTypes() {
  loading.value = true;
  try {
    const params = {};

    const typeIdFromQuery = route.query.type_id ? Number(route.query.type_id) : null;
    const categoryFromQuery = route.query.category || null;

    if (categoryFromQuery) {
      params.category = categoryFromQuery;
    }

    const response = await axios.get('/api/configuration/types', { params });
    types.value = response.data.types;

    // Determine selected type
    const matchedType = types.value.find(type => type.id === typeIdFromQuery);

    if (matchedType) {
      form.value.setting_type_id = matchedType.id;
      currentCategory.value = matchedType.setting_category; // Set category based on type
    } else if (types.value.length > 0) {
      form.value.setting_type_id = types.value[0].id;
      currentCategory.value = types.value[0].setting_category;
    }
  } catch (error) {
    console.error('Error fetching setting types:', error);
    snackbarText.value = 'Failed to load setting types';
    snackbarColor.value = 'error';
    snackbar.value = true;
  } finally {
    loading.value = false;
  }
}

function filterTypesByCategory(category) {
  currentCategory.value = category;
  // Reset selected type
  form.value.setting_type_id = '';
}

const filteredTypes = computed(() => {
  if (!currentCategory.value) return types.value;
  
  return types.value.filter(type => type.setting_category === currentCategory.value);
});

async function submitForm() {
  submitting.value = true;
  errors.value = {};
  
  try {
    const response = await axios.post('/api/configuration', form.value);
    snackbarText.value = 'Setting created successfully';
    snackbarColor.value = 'success';
    snackbar.value = true;
    
    // Navigate to the settings list after a short delay
    setTimeout(() => {
      router.push({ name: 'configuration-list' });
    }, 1000);
  } catch (error) {
    if (error.response && error.response.data && error.response.data.errors) {
      errors.value = error.response.data.errors;
    } else {
      snackbarText.value = error.response?.data?.error || 'Failed to create setting';
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
</script>

<template>
  <!-- Breadcrumbs -->
  <VBreadcrumbs :items="[
    { title: 'Home', to: '/' },
    { title: 'Configuration Settings', to: { name: 'configuration-list' } },
    { title: 'Create Setting', disabled: true }
  ]" class="mb-6" />
  
  <VCard>
    <VCardText>
      <VForm @submit.prevent="submitForm">
        <div class="d-flex align-center mb-6">
          <h1 class="text-h4">
            Create New Setting
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
            <VSelect
              v-model="currentCategory"
              label="Setting Category"
              :items="Object.entries(categories).map(([value, title]) => ({ title, value }))"
              item-title="title"
              item-value="value"
              clearable
              density="comfortable"
              variant="outlined"
              prepend-inner-icon="ri-folder-line"
              @update:model-value="filterTypesByCategory"
            />
          </VCol>
          
          <VCol cols="12" md="6">
            <VSelect
              v-model="form.setting_type_id"
              label="Setting Type"
              :items="filteredTypes.map(type => ({ title: type.name, value: type.id }))"
              item-title="title"
              item-value="value"
              :error-messages="errors.setting_type_id"
              required
              density="comfortable"
              variant="outlined"
              prepend-inner-icon="ri-list-settings-line"
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
            :disabled="submitting || types.length === 0"
          >
            Create Setting
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
