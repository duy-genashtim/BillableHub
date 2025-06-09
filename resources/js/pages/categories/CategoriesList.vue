<script setup>
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

// Data
const categories = ref([]);
const categoryTypes = ref([]);
const loading = ref(true);
const router = useRouter();
const searchQuery = ref('');
const selectedCategoryType = ref(null);
const deleteDialog = ref(false);
const categoryToDelete = ref(null);
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');
const isMobile = ref(window.innerWidth < 768);

// Get pagination settings from constants
const paginationConfig = {
  defaultPerPage: 10,
  mobilePerPage: 5,
};

// Headers for data table
const headers = [
  { title: 'Order', key: 'category_order', sortable: true },
  { title: 'ID', key: 'id', sortable: true },
  { title: 'Name', key: 'cat_name', sortable: true },
  { title: 'Type', key: 'category_type', sortable: true },
  { title: 'Description', key: 'cat_description', sortable: false },
  { title: 'Tasks', key: 'tasks_count', sortable: true },
  { title: 'Status', key: 'is_active', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
];

// Mobile headers (simplified for small screens)
const mobileHeaders = [
  { title: 'Name', key: 'cat_name', sortable: true },
  { title: 'Type', key: 'category_type', sortable: true },
  { title: 'Status', key: 'is_active', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
];

// Default sort by category_order ASC
const sortBy = ref([{ key: 'category_order', order: 'asc' }]);

// Load categories on component mount
onMounted(() => {
  fetchCategories();
  window.addEventListener('resize', handleResize);
});

function handleResize() {
  isMobile.value = window.innerWidth < 768;
}

// Methods
async function fetchCategories() {
  loading.value = true;
  try {
    const response = await axios.get('/api/categories');
    categories.value = response.data.categories;
    categoryTypes.value = response.data.categoryTypes || [];
  } catch (error) {
    console.error('Error fetching categories:', error);
    snackbarText.value = 'Failed to load categories';
    snackbarColor.value = 'error';
    snackbar.value = true;
  } finally {
    loading.value = false;
  }
}

function editCategory(category) {
  router.push({ name: 'category-edit', params: { id: category.id } });
}

function viewCategory(category) {
  router.push({ name: 'category-detail', params: { id: category.id } });
}

function confirmDelete(category) {
  categoryToDelete.value = category;
  deleteDialog.value = true;
}

async function deleteCategory() {
  try {
    await axios.delete(`/api/categories/${categoryToDelete.value.id}`);
    snackbarText.value = 'Category deleted successfully';
    snackbarColor.value = 'success';
    snackbar.value = true;
    fetchCategories();
  } catch (error) {
    console.error('Error deleting category:', error);
    const errorMessage = error.response?.data?.error || 'Failed to delete category';
    snackbarText.value = errorMessage;
    snackbarColor.value = 'error';
    snackbar.value = true;
  } finally {
    deleteDialog.value = false;
    categoryToDelete.value = null;
  }
}

async function toggleStatus(category) {
  try {
    await axios.patch(`/api/categories/${category.id}/status`);
    snackbarText.value = 'Category status updated successfully';
    snackbarColor.value = 'success';
    snackbar.value = true;
    fetchCategories();
  } catch (error) {
    console.error('Error toggling category status:', error);
    snackbarText.value = 'Failed to update category status';
    snackbarColor.value = 'error';
    snackbar.value = true;
  }
}

const filteredCategories = computed(() => {
  let filtered = categories.value;

  // Filter by search query
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(category =>
      category.cat_name.toLowerCase().includes(query) ||
      (category.cat_description && category.cat_description.toLowerCase().includes(query)) ||
      (category.category_type && category.category_type.setting_value &&
        category.category_type.setting_value.toLowerCase().includes(query))
    );
  }

  // Filter by category type
  if (selectedCategoryType.value) {
    filtered = filtered.filter(category =>
      category.category_type && category.category_type.id === selectedCategoryType.value
    );
  }

  return filtered;
});

function getCategoryStatusColor(isActive) {
  return isActive ? 'success' : 'error';
}

function getCategoryStatusText(isActive) {
  return isActive ? 'Active' : 'Inactive';
}

function getCategoryTypeName(category) {
  return category.category_type ? category.category_type.setting_value : 'N/A';
}

function addNewCategory() {
  router.push({ name: 'category-create' });
}

function clearFilters() {
  searchQuery.value = '';
  selectedCategoryType.value = null;
}

function onBeforeUnmount() {
  window.removeEventListener('resize', handleResize);
}
</script>

<template>
  <!-- Breadcrumbs -->
  <VBreadcrumbs :items="[
    { title: 'Home', to: '/' },
    { title: 'Task Categories', disabled: true }
  ]" class="mb-4 mb-md-6" aria-label="Navigation breadcrumbs" />

  <VCard>
    <VCardText>
      <div class="d-flex flex-column flex-md-row align-start align-md-center mb-4 mb-md-6 gap-3">
        <h1 class="text-h5 text-md-h4 mr-auto" tabindex="0" aria-level="1">
          Task Categories
        </h1>
        <VBtn color="primary" :prepend-icon="isMobile ? undefined : 'ri-add-line'"
          :icon="isMobile ? 'ri-add-line' : false" :size="isMobile ? 'default' : 'default'" @click="addNewCategory"
          aria-label="Add new category">
          <span v-if="!isMobile">Add New Category</span>
        </VBtn>
      </div>

      <!-- Filters Section -->
      <VRow class="mb-4 mb-md-6">
        <VCol cols="12" md="6">
          <VTextField v-model="searchQuery" density="comfortable" placeholder="Search categories..."
            prepend-inner-icon="ri-search-line" hide-details single-line variant="outlined"
            aria-label="Search categories" />
        </VCol>
        <VCol cols="12" md="4">
          <VSelect v-model="selectedCategoryType" :items="categoryTypes" item-title="setting_value" item-value="id"
            label="Filter by Type" density="comfortable" variant="outlined" clearable hide-details
            aria-label="Filter by category type" />
        </VCol>
        <VCol cols="12" md="2" class="d-flex align-center">
          <VBtn variant="outlined" color="secondary" @click="clearFilters" :block="isMobile"
            aria-label="Clear all filters">
            Clear Filters
          </VBtn>
        </VCol>
      </VRow>

      <VDataTable :headers="isMobile ? mobileHeaders : headers" :items="filteredCategories" :loading="loading"
        density="comfortable" hover class="elevation-1 rounded"
        :items-per-page="isMobile ? paginationConfig.mobilePerPage : paginationConfig.defaultPerPage" :sort-by="sortBy"
        role="table" aria-label="Categories table">
        <!-- Display Order Column (Desktop only) -->
        <template v-if="!isMobile" #[`item.category_order`]="{ item }">
          <div class="font-weight-bold text-center">
            {{ item.category_order }}
          </div>
        </template>

        <!-- Name Column -->
        <template #[`item.cat_name`]="{ item }">
          <div class="font-weight-medium">
            {{ item.cat_name }}
            <div v-if="isMobile && item.cat_description" class="text-caption text-medium-emphasis mt-1">
              {{ item.cat_description.substring(0, 50) }}{{ item.cat_description.length > 50 ? '...' : '' }}
            </div>
          </div>
        </template>

        <!-- Type Column -->
        <template #[`item.category_type`]="{ item }">
          <VChip size="small" color="info" variant="tonal" :aria-label="`Category type: ${getCategoryTypeName(item)}`">
            {{ getCategoryTypeName(item) }}
          </VChip>
        </template>

        <!-- Description Column (Desktop only) -->
        <template v-if="!isMobile" #[`item.cat_description`]="{ item }">
          <span v-if="item.cat_description">{{ item.cat_description }}</span>
          <span v-else class="text-disabled">No description</span>
        </template>

        <!-- Tasks Count Column (Desktop only) -->
        <template v-if="!isMobile" #[`item.tasks_count`]="{ item }">
          <VChip size="small" color="primary" variant="tonal" :aria-label="`${item.tasks_count} tasks assigned`">
            {{ item.tasks_count }}
          </VChip>
        </template>

        <!-- Status Column -->
        <template #[`item.is_active`]="{ item }">
          <VChip size="small" :color="getCategoryStatusColor(item.is_active)" text-color="white"
            :aria-label="`Status: ${getCategoryStatusText(item.is_active)}`">
            {{ getCategoryStatusText(item.is_active) }}
          </VChip>
        </template>

        <!-- Actions Column -->
        <template #[`item.actions`]="{ item }">
          <div class="d-flex justify-end gap-1">
            <VBtn icon size="small" variant="text" color="primary" @click="viewCategory(item)"
              :aria-label="`View details for ${item.cat_name}`">
              <VIcon size="20">ri-eye-line</VIcon>
              <VTooltip activator="parent" location="top">View Details</VTooltip>
            </VBtn>

            <VBtn icon size="small" variant="text" color="secondary" @click="editCategory(item)"
              :aria-label="`Edit ${item.cat_name}`">
              <VIcon size="20">ri-pencil-line</VIcon>
              <VTooltip activator="parent" location="top">Edit</VTooltip>
            </VBtn>

            <VBtn icon size="small" variant="text" :color="item.is_active ? 'warning' : 'success'"
              @click="toggleStatus(item)"
              :aria-label="`${item.is_active ? 'Deactivate' : 'Activate'} ${item.cat_name}`">
              <VIcon size="20">{{ item.is_active ? 'ri-pause-circle-line' : 'ri-play-circle-line' }}</VIcon>
              <VTooltip activator="parent" location="top">{{ item.is_active ? 'Deactivate' : 'Activate' }}</VTooltip>
            </VBtn>

            <VBtn icon size="small" variant="text" color="error" @click="confirmDelete(item)"
              :aria-label="`Delete ${item.cat_name}`">
              <VIcon size="20">ri-delete-bin-line</VIcon>
              <VTooltip activator="parent" location="top">Delete</VTooltip>
            </VBtn>
          </div>
        </template>

        <!-- Empty state -->
        <template #no-data>
          <div class="d-flex flex-column align-center pa-6">
            <VIcon size="48" color="secondary" icon="ri-file-list-3-line" class="mb-4" aria-hidden="true" />
            <h3 class="text-h6 font-weight-regular mb-2" tabindex="0">No categories found</h3>
            <p class="text-secondary text-center mb-4">
              There are no task categories available. Create one to get started.
            </p>
            <VBtn color="primary" @click="addNewCategory" aria-label="Add new category">
              Add New Category
            </VBtn>
          </div>
        </template>
      </VDataTable>
    </VCardText>
  </VCard>

  <!-- Confirm Delete Dialog -->
  <VDialog v-model="deleteDialog" max-width="500" role="dialog" aria-labelledby="confirm-dialog-title"
    aria-describedby="confirm-dialog-description">
    <VCard>
      <VCardTitle id="confirm-dialog-title" class="text-h5">
        Confirm Deletion
      </VCardTitle>

      <VCardText id="confirm-dialog-description">
        <template v-if="categoryToDelete">
          Are you sure you want to delete this category?
          <strong>{{ categoryToDelete.cat_name }}</strong>
          <p class="mt-2 text-body-2">
            This action cannot be undone. The category will be permanently deleted.
          </p>
        </template>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn color="secondary" variant="outlined" @click="deleteDialog = false" aria-label="Cancel deletion">
          Cancel
        </VBtn>
        <VBtn color="error" @click="deleteCategory" aria-label="Confirm deletion">
          Delete
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
</template>

<style scoped>
@media (max-width: 767px) {

  /* Mobile optimizations */
  :deep(.v-data-table) {
    font-size: 0.875rem;
  }

  :deep(.v-data-table .v-data-table__td) {
    padding: 8px;
  }

  :deep(.v-btn--icon) {
    block-size: 32px;
    inline-size: 32px;
    min-inline-size: 32px;
  }
}

/* Ensure proper focus indicators */
:deep(.v-btn:focus-visible) {
  outline: 2px solid var(--v-theme-primary);
  outline-offset: 2px;
}

:deep(.v-data-table tr:focus-visible) {
  outline: 2px solid var(--v-theme-primary);
  outline-offset: -2px;
}
</style>
