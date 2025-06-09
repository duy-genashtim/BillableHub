<script setup>
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const router = useRouter();
const route = useRoute();
const categoryId = route.params.id;

const category = ref(null);
const tasks = ref([]);
const availableTasks = ref([]);
const loading = ref(true);
const tasksLoading = ref(false);
const searchQuery = ref('');
const dialogSearchQuery = ref('');
const isMobile = ref(window.innerWidth < 768);

// Get pagination settings from constants
const paginationConfig = {
  defaultPerPage: 10,
  mobilePerPage: 5,
};

const taskDialog = ref(false);
const selectedTasks = ref([]);
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

const removeTaskDialog = ref(false);
const tasksToRemove = ref([]);

// Table headers - Updated to display ID before Task Name
const taskHeaders = [
  { title: 'ID', key: 'id', sortable: true },
  { title: 'Task Name', key: 'task_name', sortable: true },
  { title: 'Slug', key: 'slug', sortable: true },
  { title: 'Status', key: 'is_active', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
];

// Mobile headers
const mobileTaskHeaders = [
  { title: 'Task', key: 'task_name', sortable: true },
  { title: 'Status', key: 'is_active', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
];

// Updated dialog table headers
const availableTaskHeaders = [
  { title: 'ID', key: 'id', sortable: true },
  { title: 'Task Name', key: 'task_name', sortable: true },
  { title: 'Slug', key: 'slug', sortable: true },
];

onMounted(() => {
  fetchCategoryDetails();
  window.addEventListener('resize', handleResize);
});

function handleResize() {
  isMobile.value = window.innerWidth < 768;
}

async function fetchCategoryDetails() {
  loading.value = true;
  try {
    const response = await axios.get(`/api/categories/${categoryId}`);
    category.value = response.data.category;
    tasks.value = category.value.tasks || [];
  } catch (error) {
    console.error('Error fetching category details:', error);
    snackbarText.value = 'Failed to load category details';
    snackbarColor.value = 'error';
    snackbar.value = true;
    router.push({ name: 'categories-list' });
  } finally {
    loading.value = false;
  }
}

async function fetchAvailableTasks() {
  tasksLoading.value = true;
  try {
    const response = await axios.get('/api/categories/tasks/available');
    // Filter out tasks that are already assigned to this category
    const currentTaskIds = tasks.value.map(task => task.id);
    availableTasks.value = response.data.tasks.filter(task =>
      !currentTaskIds.includes(task.id) && task.is_active
    );
  } catch (error) {
    console.error('Error fetching available tasks:', error);
    snackbarText.value = 'Failed to load available tasks';
    snackbarColor.value = 'error';
    snackbar.value = true;
  } finally {
    tasksLoading.value = false;
  }
}

function openAddTaskDialog() {
  fetchAvailableTasks();
  selectedTasks.value = [];
  dialogSearchQuery.value = '';
  taskDialog.value = true;
}

async function addTasksToCategory() {
  if (selectedTasks.value.length === 0) {
    snackbarText.value = 'Please select at least one task';
    snackbarColor.value = 'warning';
    snackbar.value = true;
    return;
  }

  try {
    await axios.post(`/api/categories/${categoryId}/tasks`, {
      task_ids: selectedTasks.value
    });

    snackbarText.value = 'Tasks assigned to category successfully';
    snackbarColor.value = 'success';
    snackbar.value = true;

    // Refresh the category details
    fetchCategoryDetails();
    taskDialog.value = false;
  } catch (error) {
    console.error('Error adding tasks to category:', error);
    snackbarText.value = 'Failed to assign tasks to category';
    snackbarColor.value = 'error';
    snackbar.value = true;
  }
}

function confirmRemoveTasks(taskIds) {
  tasksToRemove.value = Array.isArray(taskIds) ? taskIds : [taskIds];
  removeTaskDialog.value = true;
}

async function removeTasksFromCategory() {
  try {
    await axios.delete(`/api/categories/${categoryId}/tasks`, {
      data: { task_ids: tasksToRemove.value }
    });

    snackbarText.value = 'Tasks removed from category successfully';
    snackbarColor.value = 'success';
    snackbar.value = true;

    // Refresh the category details
    fetchCategoryDetails();
    removeTaskDialog.value = false;
  } catch (error) {
    console.error('Error removing tasks from category:', error);
    snackbarText.value = 'Failed to remove tasks from category';
    snackbarColor.value = 'error';
    snackbar.value = true;
  }
}

function editCategory() {
  router.push({ name: 'category-edit', params: { id: categoryId } });
}

function goBack() {
  router.push({ name: 'categories-list' });
}

const filteredTasks = computed(() => {
  if (!searchQuery.value || !tasks.value) return tasks.value;

  const query = searchQuery.value.toLowerCase();
  return tasks.value.filter(task =>
    task.task_name.toLowerCase().includes(query) ||
    (task.id && task.id.toString().includes(query)) ||
    (task.slug && task.slug.toLowerCase().includes(query))
  );
});

const filteredAvailableTasks = computed(() => {
  if (!dialogSearchQuery.value || !availableTasks.value) return availableTasks.value;

  const query = dialogSearchQuery.value.toLowerCase();
  return availableTasks.value.filter(task =>
    task.task_name.toLowerCase().includes(query) ||
    (task.id && task.id.toString().includes(query)) ||
    (task.slug && task.slug.toLowerCase().includes(query))
  );
});

function getTaskStatusColor(isActive) {
  return isActive ? 'success' : 'error';
}

function getTaskStatusText(isActive) {
  return isActive ? 'Active' : 'Inactive';
}

function getCategoryTypeName(category) {
  return category.category_type ? category.category_type.setting_value : 'N/A';
}

function closeTaskDialog() {
  taskDialog.value = false;
}

function onBeforeUnmount() {
  window.removeEventListener('resize', handleResize);
}
</script>

<template>
  <div>
    <VBreadcrumbs :items="[
      { title: 'Home', to: '/' },
      { title: 'Task Categories', to: { name: 'categories-list' } },
      { title: category ? category.cat_name : 'Category Details', disabled: true }
    ]" class="mb-4 mb-md-6" aria-label="Navigation breadcrumbs" />

    <VCard v-if="loading">
      <VCardText class="d-flex justify-center align-center pa-6">
        <VProgressCircular indeterminate color="primary" :size="50" :width="5" aria-label="Loading category details" />
      </VCardText>
    </VCard>

    <template v-else>
      <VCard class="mb-4 mb-md-6">
        <VCardText>
          <div class="d-flex flex-column flex-md-row align-start align-md-center mb-4 gap-3">
            <div class="flex-grow-1">
              <h1 class="text-h5 text-md-h4 mb-2" tabindex="0" aria-level="1">
                {{ category.cat_name }}
              </h1>
              <div class="d-flex flex-wrap gap-2">
                <VChip size="small" :color="category.is_active ? 'success' : 'error'" text-color="white"
                  :aria-label="`Category status: ${category.is_active ? 'Active' : 'Inactive'}`">
                  {{ category.is_active ? 'Active' : 'Inactive' }}
                </VChip>
                <VChip size="small" color="info" variant="tonal"
                  :aria-label="`Category type: ${getCategoryTypeName(category)}`">
                  {{ getCategoryTypeName(category) }}
                </VChip>
              </div>
            </div>
            <div class="d-flex gap-2">
              <VBtn color="secondary" variant="outlined" :prepend-icon="isMobile ? undefined : 'ri-arrow-left-line'"
                :icon="isMobile ? 'ri-arrow-left-line' : false" @click="goBack" aria-label="Go back to categories list">
                <span v-if="!isMobile">Back</span>
              </VBtn>
              <VBtn color="primary" variant="outlined" :prepend-icon="isMobile ? undefined : 'ri-pencil-line'"
                :icon="isMobile ? 'ri-pencil-line' : false" @click="editCategory" aria-label="Edit category">
                <span v-if="!isMobile">Edit</span>
              </VBtn>
            </div>
          </div>

          <VDivider class="mb-4" />

          <VRow>
            <VCol v-if="category.cat_description" cols="12" md="8">
              <div class="text-subtitle-1 font-weight-medium mb-1">Description:</div>
              <p class="text-body-1">{{ category.cat_description }}</p>
            </VCol>
            <VCol v-else cols="12" md="8">
              <p class="text-body-2 text-disabled">No description provided</p>
            </VCol>
            <VCol cols="12" md="4">
              <div class="text-subtitle-1 font-weight-medium mb-1">Display Order:</div>
              <p class="text-body-1">{{ category.category_order }}</p>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <VCard>
        <VCardText>
          <div class="d-flex flex-column flex-md-row align-start align-md-center mb-4 gap-3">
            <h2 class="text-h6 text-md-h5 flex-grow-1" tabindex="0" aria-level="2">
              Tasks in this Category ({{ tasks.length }})
            </h2>
            <VBtn color="primary" :prepend-icon="isMobile ? undefined : 'ri-add-line'"
              :icon="isMobile ? 'ri-add-line' : false" @click="openAddTaskDialog" aria-label="Add tasks to category">
              <span v-if="!isMobile">Add Tasks</span>
            </VBtn>
          </div>

          <VTextField v-model="searchQuery" density="compact" placeholder="Search tasks..."
            prepend-inner-icon="ri-search-line" hide-details class="mb-4" single-line variant="outlined"
            aria-label="Search tasks in category" />

          <VDataTable :headers="isMobile ? mobileTaskHeaders : taskHeaders" :items="filteredTasks" :loading="loading"
            density="comfortable" hover class="elevation-1 rounded"
            :items-per-page="isMobile ? paginationConfig.mobilePerPage : paginationConfig.defaultPerPage" role="table"
            aria-label="Tasks in category table">
            <!-- ID Column (Desktop only) -->
            <template v-if="!isMobile" #[`item.id`]="{ item }">
              <span>{{ item.id }}</span>
            </template>

            <!-- Task Name Column -->
            <template #[`item.task_name`]="{ item }">
              <div class="font-weight-medium">
                {{ item.task_name }}
                <div v-if="isMobile" class="text-caption text-medium-emphasis mt-1">
                  ID: {{ item.id }} • {{ item.slug }}
                </div>
              </div>
            </template>

            <!-- Slug Column (Desktop only) -->
            <template v-if="!isMobile" #[`item.slug`]="{ item }">
              <span>{{ item.slug }}</span>
            </template>

            <!-- Status Column -->
            <template #[`item.is_active`]="{ item }">
              <VChip size="small" :color="getTaskStatusColor(item.is_active)" text-color="white"
                :aria-label="`Task status: ${getTaskStatusText(item.is_active)}`">
                {{ getTaskStatusText(item.is_active) }}
              </VChip>
            </template>

            <!-- Actions Column -->
            <template #[`item.actions`]="{ item }">
              <VBtn icon size="small" variant="text" color="error" @click="confirmRemoveTasks(item.id)"
                :aria-label="`Remove ${item.task_name} from category`">
                <VIcon size="20">ri-link-unlink</VIcon>
                <VTooltip activator="parent" location="top">Remove from Category</VTooltip>
              </VBtn>
            </template>

            <!-- Empty state -->
            <template #no-data>
              <div class="d-flex flex-column align-center pa-6">
                <VIcon size="48" color="secondary" icon="ri-file-list-2-line" class="mb-4" aria-hidden="true" />
                <h3 class="text-h6 font-weight-regular mb-2" tabindex="0">No tasks found</h3>
                <p class="text-secondary text-center mb-4">
                  There are no tasks assigned to this category. Add some tasks to get started.
                </p>
                <VBtn color="primary" @click="openAddTaskDialog" aria-label="Add tasks to category">
                  Add Tasks
                </VBtn>
              </div>
            </template>
          </VDataTable>
        </VCardText>
      </VCard>
    </template>

    <!-- Dialog for adding tasks -->
    <VDialog v-model="taskDialog" :max-width="isMobile ? '95vw' : '800'" :fullscreen="isMobile" role="dialog"
      aria-labelledby="add-tasks-dialog-title">
      <VCard>
        <VCardTitle id="add-tasks-dialog-title" class="text-h5 bg-primary text-white d-flex align-center py-3">
          <span>Add Tasks to {{ category ? category.cat_name : '' }}</span>
          <VSpacer />
          <VBtn icon variant="text" color="white" @click="closeTaskDialog" class="ml-2" aria-label="Close dialog">
            <VIcon>ri-close-line</VIcon>
          </VBtn>
        </VCardTitle>

        <VCardText class="pt-4">
          <div v-if="tasksLoading" class="d-flex justify-center py-4">
            <VProgressCircular indeterminate color="primary" aria-label="Loading available tasks" />
          </div>

          <div v-else>
            <!-- Search and action buttons at the top -->
            <div class="d-flex flex-column flex-md-row align-start align-md-center mb-4 gap-3">
              <VTextField v-model="dialogSearchQuery" density="compact" placeholder="Search tasks..."
                prepend-inner-icon="ri-search-line" hide-details single-line variant="outlined" class="flex-grow-1"
                aria-label="Search available tasks" />

              <div class="d-flex gap-2" :class="{ 'w-100': isMobile }">
                <VBtn color="secondary" variant="outlined" @click="closeTaskDialog" :block="isMobile"
                  aria-label="Cancel adding tasks">
                  Cancel
                </VBtn>

                <VBtn color="primary" :disabled="tasksLoading || selectedTasks.length === 0" @click="addTasksToCategory"
                  :block="isMobile" aria-label="Add selected tasks to category">
                  Add Selected ({{ selectedTasks.length }})
                </VBtn>
              </div>
            </div>

            <VDataTable v-model="selectedTasks" :headers="availableTaskHeaders" :items="filteredAvailableTasks"
              item-value="id" density="comfortable" show-select class="elevation-1 rounded"
              :items-per-page="isMobile ? paginationConfig.mobilePerPage : paginationConfig.defaultPerPage" role="table"
              aria-label="Available tasks table">
              <!-- ID Column -->
              <template #[`item.id`]="{ item }">
                <span>{{ item.id }}</span>
              </template>

              <!-- Task Name Column -->
              <template #[`item.task_name`]="{ item }">
                <div class="font-weight-medium">
                  {{ item.task_name }}
                </div>
              </template>

              <!-- Slug Column -->
              <template #[`item.slug`]="{ item }">
                <span>{{ item.slug }}</span>
              </template>

              <!-- Empty state -->
              <template #no-data>
                <div class="d-flex flex-column align-center pa-6">
                  <VIcon size="48" color="secondary" icon="ri-file-list-2-line" class="mb-4" aria-hidden="true" />
                  <h3 class="text-h6 font-weight-regular mb-2" tabindex="0">No available tasks</h3>
                  <p class="text-secondary text-center">
                    All active tasks are already assigned to this category or no tasks are available.
                  </p>
                </div>
              </template>
            </VDataTable>
          </div>
        </VCardText>

        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="closeTaskDialog" aria-label="Cancel adding tasks">
            Cancel
          </VBtn>
          <VBtn color="primary" @click="addTasksToCategory" :disabled="tasksLoading || selectedTasks.length === 0"
            aria-label="Add selected tasks to category">
            Add Selected Tasks
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog for removing tasks -->
    <VDialog v-model="removeTaskDialog" max-width="500" role="dialog" aria-labelledby="remove-tasks-dialog-title">
      <VCard>
        <VCardTitle id="remove-tasks-dialog-title" class="text-h5 bg-error text-white d-flex align-center py-3">
          <span>Remove Tasks</span>
        </VCardTitle>

        <VCardText class="pt-4">
          Are you sure you want to remove {{ tasksToRemove.length > 1 ? 'these tasks' : 'this task' }} from the
          category?
          <p class="mt-2 text-body-2">
            This will only remove the association between the tasks and this category. The tasks themselves will not be
            deleted.
          </p>
        </VCardText>

        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="removeTaskDialog = false"
            aria-label="Cancel removing tasks">
            Cancel
          </VBtn>
          <VBtn color="error" @click="removeTasksFromCategory" aria-label="Confirm removing tasks">
            Remove
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

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

  :deep(.v-card-text) {
    padding: 16px;
  }
}

/* Focus indicators */
:deep(.v-btn:focus-visible) {
  outline: 2px solid var(--v-theme-primary);
  outline-offset: 2px;
}

:deep(.v-data-table tr:focus-visible) {
  outline: 2px solid var(--v-theme-primary);
  outline-offset: -2px;
}
</style>
