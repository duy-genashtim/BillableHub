<script setup>
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

// Data
const types = ref([]);
const categories = ref({
  'site': 'Site Settings',
  'user': 'User Settings',
  'report': 'Report Settings',
  'report-time': 'Report Time Settings',
  'report-cat': 'Report Category Settings',
  'system': 'System Settings',
  'other': 'Other Settings'
});
const currentCategory = ref(null);
const loading = ref(true);
const router = useRouter();
const searchQuery = ref('');
const deleteDialog = ref(false);
const typeToDelete = ref(null);
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');
const isMobile = ref(window.innerWidth < 768);

// Headers for data table - responsive based on screen size
const headers = computed(() => {
  // Base headers that will always be shown
  const baseHeaders = [
    { title: 'Name', key: 'name', sortable: true },
    { title: 'Category', key: 'setting_category', sortable: true },
    { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
  ];
  
  // Additional headers for desktop view
  if (!isMobile.value) {
    baseHeaders.splice(1, 0, { title: 'Key', key: 'key', sortable: true });
    baseHeaders.splice(3, 0, { title: 'User Customizable', key: 'for_user_customize', sortable: true });
    baseHeaders.splice(4, 0, { title: 'Permissions', key: 'permissions', sortable: false });
  }
  
  return baseHeaders;
});

// Load types on component mount
onMounted(() => {
  window.addEventListener('resize', handleResize);
  fetchTypes();
});

function handleResize() {
  isMobile.value = window.innerWidth < 768;
}

// Methods
async function fetchTypes() {
  loading.value = true;
  try {
    const params = {};
    if (currentCategory.value) {
      params.category = currentCategory.value;
    }
    
    const response = await axios.get('/api/configuration/types', { params });
    types.value = response.data.types;
  } catch (error) {
    console.error('Error fetching setting types:', error);
    snackbarText.value = 'Failed to load setting types';
    snackbarColor.value = 'error';
    snackbar.value = true;
  } finally {
    loading.value = false;
  }
}

function filterByCategory(category) {
  currentCategory.value = category;
  fetchTypes();
}

function editType(type) {
  router.push({ name: 'configuration-type-edit', params: { id: type.id } });
}

function viewSettings(type) {
  // Navigate to configuration list with type_id as a query parameter
  router.push({ 
    name: 'configuration-list',
    query: { type_id: type.id }
  });
}

function confirmDelete(type) {
  typeToDelete.value = type;
  deleteDialog.value = true;
}

async function deleteType() {
  try {
    await axios.delete(`/api/configuration/types/${typeToDelete.value.id}`);
    
    snackbarText.value = 'Setting type deleted successfully';
    snackbarColor.value = 'success';
    snackbar.value = true;
    fetchTypes();
  } catch (error) {
    console.error('Error deleting setting type:', error);
    
    if (error.response && error.response.data && error.response.data.message) {
      snackbarText.value = error.response.data.message;
    } else {
      snackbarText.value = 'Failed to delete setting type';
    }
    
    snackbarColor.value = 'error';
    snackbar.value = true;
  } finally {
    deleteDialog.value = false;
    typeToDelete.value = null;
  }
}

const filteredTypes = computed(() => {
  let filtered = types.value;
  
  // Filter by search query
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(type => 
      type.key.toLowerCase().includes(query) || 
      type.name.toLowerCase().includes(query) ||
      (type.description && type.description.toLowerCase().includes(query)) ||
      categories.value[type.setting_category].toLowerCase().includes(query)
    );
  }
  
  return filtered;
});

function getCategoryColor(category) {
  switch (category) {
    case 'site': return 'primary';
    case 'user': return 'success';
    case 'report': return 'info';
    case 'report-time': return 'info';
    case 'report-cat': return 'info';
    case 'system': return 'error';
    default: return 'secondary';
  }
}

function addNewType() {
  router.push({ 
    name: 'configuration-type-create',
    query: { category: currentCategory.value }
  });
}

function goBack() {
  router.push({ name: 'configuration-list' });
}

// Reset category filter
function resetCategoryFilter() {
  currentCategory.value = null;
  fetchTypes();
}
</script>

<template>
  <!-- Breadcrumbs -->
  <VBreadcrumbs :items="[
    { title: 'Home', to: '/' },
    { title: 'Configuration Settings', to: { name: 'configuration-list' } },
    { title: 'Setting Types', disabled: true }
  ]" class="mb-6" />

  <VCard>
    <VCardText>
      <div class="d-flex flex-wrap align-center mb-6 gap-2">
        <h1 class="text-h5 text-md-h4 mr-auto mb-2 mb-md-0">
          Setting Types
        </h1>
        <div class="d-flex gap-2">
          <VBtn
            color="secondary"
            variant="outlined"
            prepend-icon="ri-arrow-left-line"
            :size="isMobile ? 'small' : 'default'"
            @click="goBack"
          >
            <span v-if="!isMobile">Back to Settings</span>
            <span v-else>Back</span>
          </VBtn>
          <VBtn
            color="primary"
            prepend-icon="ri-add-line"
            :size="isMobile ? 'small' : 'default'"
            @click="addNewType"
          >
            <span v-if="!isMobile">Add New Type</span>
            <span v-else>Add</span>
          </VBtn>
        </div>
      </div>

      <!-- Category filter and search bar -->
      <div class="d-flex flex-column flex-md-row flex-wrap align-md-center mb-6 gap-2">
        <div class="filter-container flex-grow-1 flex-md-grow-0 mb-2 mb-md-0">
          <VSelect
            v-model="currentCategory"
            :items="Object.entries(categories).map(([value, title]) => ({ title, value }))"
            item-title="title"
            item-value="value"
            label="Filter by Category"
            clearable
            hide-details
            density="compact"
            class="max-width-300"
            @update:model-value="filterByCategory"
          >
            <template v-slot:prepend-inner>
              <VIcon size="small">ri-filter-line</VIcon>
            </template>
          </VSelect>
        </div>
        
        <VSpacer class="d-none d-md-block" />
        
        <VTextField
          v-model="searchQuery"
          density="compact"
          placeholder="Search setting types..."
          prepend-inner-icon="ri-search-line"
          hide-details
          class="flex-grow-1 max-width-400"
          single-line
        />
      </div>

      <VDataTable
        :headers="headers"
        :items="filteredTypes"
        :loading="loading"
        density="comfortable"
        hover
        class="elevation-1 rounded"
      >
        <!-- Key Column (desktop only) -->
        <template #[`item.key`]="{ item }">
          <div class="font-weight-medium text-break">
            {{ item.key }}
          </div>
        </template>
        
        <!-- Name Column -->
        <template #[`item.name`]="{ item }">
          <div class="font-weight-medium text-break">
            {{ item.name }}
            <div v-if="isMobile" class="mt-1">
              <small class="text-muted">{{ item.key }}</small>
            </div>
          </div>
        </template>
        
        <!-- Category Column -->
        <template #[`item.setting_category`]="{ item }">
          <VChip
            size="small"
            :color="getCategoryColor(item.setting_category)"
            variant="flat"
          >
            {{ categories[item.setting_category] }}
          </VChip>
          
          <!-- On mobile, show user customizable and permissions -->
          <div v-if="isMobile" class="mt-2">
            <VChip
              size="x-small"
              :color="item.for_user_customize ? 'success' : 'grey'"
              variant="outlined"
              class="mr-1"
            >
              <VIcon v-if="item.for_user_customize" size="x-small" class="mr-1">ri-check-line</VIcon>
              <VIcon v-else size="x-small" class="mr-1">ri-close-line</VIcon>
              User Custom
            </VChip>
            
            <VChip
              size="x-small"
              :color="item.allow_create || item.allow_edit || item.allow_delete ? 'success' : 'error'"
              variant="outlined"
            >
              <template v-if="item.allow_create && item.allow_edit && item.allow_delete">
                Full Access
              </template>
              <template v-else>
                Limited Access
              </template>
            </VChip>
          </div>
        </template>

        <!-- User Customizable Column (desktop only) -->
        <template #[`item.for_user_customize`]="{ item }">
          <VIcon v-if="item.for_user_customize" color="success">ri-check-line</VIcon>
          <VIcon v-else color="error">ri-close-line</VIcon>
        </template>

        <!-- Permissions Column (desktop only) -->
        <template #[`item.permissions`]="{ item }">
          <div class="d-flex gap-1 flex-wrap">
            <VChip
              size="x-small"
              :color="item.allow_create ? 'success' : 'error'"
              variant="outlined"
            >
              <VIcon size="x-small" class="mr-1">
                {{ item.allow_create ? 'ri-add-circle-line' : 'ri-forbid-line' }}
              </VIcon>
              Create
            </VChip>
            
            <VChip
              size="x-small"
              :color="item.allow_edit ? 'success' : 'error'"
              variant="outlined"
            >
              <VIcon size="x-small" class="mr-1">
                {{ item.allow_edit ? 'ri-edit-line' : 'ri-forbid-line' }}
              </VIcon>
              Edit
            </VChip>
            
            <VChip
              size="x-small"
              :color="item.allow_delete ? 'success' : 'error'"
              variant="outlined"
            >
              <VIcon size="x-small" class="mr-1">
                {{ item.allow_delete ? 'ri-delete-bin-line' : 'ri-forbid-line' }}
              </VIcon>
              Delete
            </VChip>
          </div>
        </template>

        <!-- Actions Column -->
        <template #[`item.actions`]="{ item }">
          <div class="d-flex justify-end">
            <VBtn
              v-if="!isMobile"
              icon
              size="small"
              variant="text"
              color="primary"
              class="me-1"
              @click="viewSettings(item)"
            >
              <VIcon size="20">ri-list-settings-line</VIcon>
              <VTooltip activator="parent">View Settings</VTooltip>
            </VBtn>
            
            <VBtn
              v-if="!isMobile"
              icon
              size="small"
              variant="text"
              color="secondary"
              class="me-1"
              @click="editType(item)"
            >
              <VIcon size="20">ri-pencil-line</VIcon>
              <VTooltip activator="parent">Edit</VTooltip>
            </VBtn>
            
            <VBtn
              v-if="!isMobile"
              icon
              size="small"
              variant="text"
              color="error"
              @click="confirmDelete(item)"
            >
              <VIcon size="20">ri-delete-bin-line</VIcon>
              <VTooltip activator="parent">Delete</VTooltip>
            </VBtn>
            
            <!-- On mobile, use a menu for actions to save space -->
            <VMenu>
              <template v-slot:activator="{ props }">
                <VBtn
                  icon
                  size="small"
                  variant="text"
                  color="secondary"
                  v-bind="props"
                >
                  <VIcon size="20">ri-more-2-fill</VIcon>
                </VBtn>
              </template>
              <VList density="compact">
                <VListItem @click="viewSettings(item)">
                  <template v-slot:prepend>
                    <VIcon size="small">ri-list-settings-line</VIcon>
                  </template>
                  <VListItemTitle>View Settings</VListItemTitle>
                </VListItem>
                
                <VListItem @click="editType(item)">
                  <template v-slot:prepend>
                    <VIcon size="small">ri-pencil-line</VIcon>
                  </template>
                  <VListItemTitle>Edit</VListItemTitle>
                </VListItem>
                
                <VListItem @click="confirmDelete(item)">
                  <template v-slot:prepend>
                    <VIcon size="small" color="error">ri-delete-bin-line</VIcon>
                  </template>
                  <VListItemTitle class="text-error">Delete</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
          </div>
        </template>

        <!-- Empty state -->
        <template #no-data>
          <div class="d-flex flex-column align-center pa-6">
            <VIcon
              size="48"
              color="secondary"
              icon="ri-list-settings-line"
              class="mb-4"
            />
            <h3 class="text-h6 font-weight-regular mb-2">No setting types found</h3>
            <p class="text-secondary text-center mb-4">
              There are no setting types matching your criteria. 
              <span v-if="currentCategory || searchQuery">Try changing your filters.</span>
              <span v-else>Create one to get started.</span>
            </p>
            <div class="d-flex gap-2 flex-wrap justify-center">
              <VBtn
                v-if="currentCategory || searchQuery"
                color="secondary"
                @click="resetCategoryFilter(); searchQuery = ''"
              >
                Clear Filters
              </VBtn>
              <VBtn
                color="primary"
                @click="addNewType"
              >
                Add New Type
              </VBtn>
            </div>
          </div>
        </template>
      </VDataTable>
    </VCardText>
  </VCard>

  <!-- Confirm Delete Dialog -->
  <VDialog
    v-model="deleteDialog"
    max-width="500"
  >
    <VCard>
      <VCardTitle class="text-h5 bg-error text-white d-flex align-center py-3">
        <span>Delete Setting Type</span>
      </VCardTitle>
      
      <VCardText class="pt-4">
        <p>Are you sure you want to delete this setting type?</p>
        <p v-if="typeToDelete" class="font-weight-bold">{{ typeToDelete.name }}</p>
        <p class="mt-2 text-body-2 text-error">
          This action cannot be undone. The setting type will be permanently removed from the system.
          All settings associated with this type will also be deleted.
        </p>
      </VCardText>
      
      <VCardActions>
        <VSpacer />
        <VBtn
          color="secondary"
          variant="outlined"
          @click="deleteDialog = false"
        >
          Cancel
        </VBtn>
        <VBtn
          color="error"
          @click="deleteType"
        >
          Delete
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

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
.max-width-300 {
  max-inline-size: 300px;
}

.max-width-400 {
  max-inline-size: 400px;
}

@media (max-width: 767px) {
  .max-width-300,
  .max-width-400 {
    max-inline-size: 100%;
  }

  /* Add responsive spacing */
  :deep(.v-card-text) {
    padding-block: 16px;
    padding-inline: 12px;
  }

  /* Make table more compact on mobile */
  :deep(.v-data-table) {
    font-size: 0.85rem;
  }
}
</style>
