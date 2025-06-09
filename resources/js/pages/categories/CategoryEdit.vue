<script setup>
import axios from 'axios';
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const router = useRouter();
const route = useRoute();
const categoryId = route.params.id;

const form = ref({
  cat_name: '',
  cat_description: '',
  is_active: true,
  category_order: 20,
  category_type: null
});

const categoryTypes = ref([]);
const loading = ref(true);
const typesLoading = ref(true);
const errors = ref({});
const submitting = ref(false);
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');
const categoryName = ref('');
const isMobile = ref(window.innerWidth < 768);

onMounted(() => {
  Promise.all([fetchCategory(), fetchCategoryTypes()]);
  window.addEventListener('resize', handleResize);
});

function handleResize() {
  isMobile.value = window.innerWidth < 768;
}

async function fetchCategory() {
  loading.value = true;
  try {
    const response = await axios.get(`/api/categories/${categoryId}`);
    const category = response.data.category;

    categoryName.value = category.cat_name;
    form.value = {
      cat_name: category.cat_name,
      cat_description: category.cat_description || '',
      is_active: category.is_active,
      category_order: category.category_order || 20,
      category_type: category.category_type ? category.category_type.id : null
    };
  } catch (error) {
    console.error('Error fetching category:', error);
    snackbarText.value = 'Failed to load category';
    snackbarColor.value = 'error';
    snackbar.value = true;
    router.push({ name: 'categories-list' });
  } finally {
    loading.value = false;
  }
}

async function fetchCategoryTypes() {
  typesLoading.value = true;
  try {
    const response = await axios.get('/api/categories/types');
    categoryTypes.value = response.data.categoryTypes;
  } catch (error) {
    console.error('Error fetching category types:', error);
    snackbarText.value = 'Failed to load category types';
    snackbarColor.value = 'error';
    snackbar.value = true;
  } finally {
    typesLoading.value = false;
  }
}

async function submitForm() {
  submitting.value = true;
  errors.value = {};

  try {
    const response = await axios.put(`/api/categories/${categoryId}`, form.value);
    snackbarText.value = 'Category updated successfully';
    snackbarColor.value = 'success';
    snackbar.value = true;

    // Navigate to the categories list after a short delay
    setTimeout(() => {
      router.push({ name: 'categories-list' });
    }, 1000);
  } catch (error) {
    if (error.response && error.response.data && error.response.data.errors) {
      errors.value = error.response.data.errors;
    } else {
      snackbarText.value = 'Failed to update category';
      snackbarColor.value = 'error';
      snackbar.value = true;
    }
  } finally {
    submitting.value = false;
  }
}

function cancel() {
  router.push({ name: 'categories-list' });
}

function onBeforeUnmount() {
  window.removeEventListener('resize', handleResize);
}
</script>

<template>
  <!-- Breadcrumbs -->
  <VBreadcrumbs :items="[
    { title: 'Home', to: '/' },
    { title: 'Task Categories', to: { name: 'categories-list' } },
    { title: loading ? 'Edit Category' : `Edit ${categoryName}`, disabled: true }
  ]" class="mb-4 mb-md-6" aria-label="Navigation breadcrumbs" />

  <VCard>
    <VCardText>
      <VForm @submit.prevent="submitForm" role="form" aria-label="Edit category form">
        <div class="d-flex align-center mb-4 mb-md-6">
          <h1 class="text-h5 text-md-h4" tabindex="0" aria-level="1">
            Edit Category
          </h1>
        </div>

        <VRow v-if="loading || typesLoading">
          <VCol cols="12" class="d-flex justify-center">
            <VProgressCircular indeterminate color="primary" :size="50" :width="5" aria-label="Loading category data" />
          </VCol>
        </VRow>

        <VRow v-else>
          <VCol cols="12">
            <VTextField v-model="form.cat_name" label="Category Name" placeholder="Enter category name"
              :error-messages="errors.cat_name" required density="comfortable" variant="outlined"
              prepend-inner-icon="ri-price-tag-3-line" aria-describedby="cat-name-help" />
            <div id="cat-name-help" class="sr-only">
              Enter a unique name for the category
            </div>
          </VCol>

          <VCol cols="12" md="6">
            <VSelect v-model="form.category_type" :items="categoryTypes" item-title="setting_value" item-value="id"
              label="Category Type" placeholder="Select category type" :error-messages="errors.category_type" required
              density="comfortable" variant="outlined" prepend-inner-icon="ri-bookmark-line"
              aria-describedby="category-type-help">
              <template #item="{ item, props }">
                <VListItem v-bind="props" :title="item.raw.setting_value" :subtitle="item.raw.description">
                </VListItem>
              </template>
            </VSelect>
            <div id="category-type-help" class="sr-only">
              Select the type of category from available options
            </div>
          </VCol>

          <VCol cols="12" md="6">
            <VTextField v-model.number="form.category_order" label="Display Order"
              placeholder="Enter display order (lower numbers appear first)" :error-messages="errors.category_order"
              type="number" min="1" density="comfortable" variant="outlined" prepend-inner-icon="ri-sort-asc"
              aria-describedby="category-order-help" />
            <div id="category-order-help" class="sr-only">
              Enter a number to determine display order. Lower numbers appear first.
            </div>
          </VCol>

          <VCol cols="12">
            <VTextarea v-model="form.cat_description" label="Description" placeholder="Enter category description"
              :error-messages="errors.cat_description" rows="4" density="comfortable" variant="outlined"
              prepend-inner-icon="ri-text-wrap" aria-describedby="description-help" />
            <div id="description-help" class="sr-only">
              Provide an optional description for the category
            </div>
          </VCol>

          <VCol cols="12" class="pb-0">
            <VSwitch v-model="form.is_active" label="Active" color="primary" hide-details
              aria-describedby="active-help" />
            <div id="active-help" class="text-caption text-medium-emphasis mt-1">
              Determines if the category is available for use
            </div>
          </VCol>
        </VRow>

        <VDivider class="my-4 my-md-6" />

        <VCardActions class="px-0 flex-column flex-md-row gap-2">
          <VBtn color="secondary" variant="outlined" :prepend-icon="isMobile ? undefined : 'ri-arrow-left-line'"
            @click="cancel" :disabled="submitting || loading || typesLoading" :block="isMobile"
            aria-label="Cancel and return to categories list">
            Cancel
          </VBtn>

          <VSpacer v-if="!isMobile" />

          <VBtn color="primary" type="submit" :prepend-icon="isMobile ? undefined : 'ri-save-line'"
            :loading="submitting" :disabled="submitting || loading || typesLoading" :block="isMobile"
            aria-label="Update category">
            Update Category
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
</template>

<style scoped>
/* Screen reader only class */
.sr-only {
  position: absolute;
  overflow: hidden;
  padding: 0;
  border: 0;
  margin: -1px;
  block-size: 1px;
  clip: rect(0, 0, 0, 0);
  inline-size: 1px;
  white-space: nowrap;
}

@media (max-width: 767px) {

  /* Mobile optimizations */
  :deep(.v-card-text) {
    padding: 16px;
  }

  :deep(.v-text-field) {
    margin-block-end: 8px;
  }
}

/* Focus indicators */
:deep(.v-btn:focus-visible) {
  outline: 2px solid var(--v-theme-primary);
  outline-offset: 2px;
}

:deep(.v-field:focus-within) {
  outline: 2px solid var(--v-theme-primary);
  outline-offset: 2px;
}
</style>
