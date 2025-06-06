<script setup>
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

// Data
const logs = ref([]);
const loading = ref(true);
const router = useRouter();
const currentPage = ref(1);
const perPage = ref(15);
const totalLogs = ref(0);
const totalPages = ref(0);
const searchQuery = ref('');
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');
const isMobile = ref(window.innerWidth < 768);
const expandedItems = ref(new Set());

// Headers for data table
const headers = computed(() => {
  const baseHeaders = [
    { title: 'Date & Time', key: 'created_at', sortable: true },
    { title: 'User', key: 'email', sortable: true },
    { title: 'Action', key: 'action', sortable: true },
    { title: 'Description', key: 'description', sortable: false },
    { title: 'Details', key: 'actions', sortable: false, align: 'center' },
  ];

  if (isMobile.value) {
    // On mobile, combine some columns
    return [
      { title: 'Activity', key: 'activity', sortable: false },
      { title: 'Details', key: 'actions', sortable: false, align: 'center' },
    ];
  }
  
  return baseHeaders;
});

// Load logs on component mount
onMounted(() => {
  window.addEventListener('resize', handleResize);
  fetchLogs();
});

function handleResize() {
  isMobile.value = window.innerWidth < 768;
}

// Methods
async function fetchLogs() {
  loading.value = true;
  try {
    const params = {
      page: currentPage.value,
      per_page: perPage.value,
    };
    
    const response = await axios.get('/api/configuration/logs', { params });
    
    // Handle Laravel pagination response
    const paginationData = response.data.logs;
    logs.value = paginationData.data || [];
    currentPage.value = paginationData.current_page || 1;
    totalLogs.value = paginationData.total || 0;
    totalPages.value = paginationData.last_page || 1;
    
  } catch (error) {
    console.error('Error fetching activity logs:', error);
    snackbarText.value = 'Failed to load activity logs';
    snackbarColor.value = 'error';
    snackbar.value = true;
  } finally {
    loading.value = false;
  }
}

function handlePageChange(page) {
  currentPage.value = page;
  fetchLogs();
}

function goBack() {
  router.push({ name: 'configuration-list' });
}

function getActionColor(action) {
  switch (action) {
    case 'create_config':
    case 'create_config_type':
      return 'success';
    case 'update_config':
    case 'update_config_type':
      return 'primary';
    case 'delete_config':
    case 'delete_config_type':
      return 'error';
    case 'toggle_status':
      return 'warning';
    default:
      return 'secondary';
  }
}

function getActionLabel(action) {
  const actionLabels = {
    create_config: 'Configuration Created',
    update_config: 'Configuration Updated',
    delete_config: 'Configuration Deleted',
    create_config_type: 'Type Created',
    update_config_type: 'Type Updated',
    delete_config_type: 'Type Deleted',
    toggle_status: 'Status Changed',
  };
  
  return actionLabels[action] || action.replace('_', ' ').toUpperCase();
}

function toggleExpandLog(logId) {
  if (expandedItems.value.has(logId)) {
    expandedItems.value.delete(logId);
  } else {
    expandedItems.value.add(logId);
  }
}

function isLogExpanded(logId) {
  return expandedItems.value.has(logId);
}

function formatLogDetails(detailLog) {
  try {
    const details = JSON.parse(detailLog);
    return JSON.stringify(details, null, 2);
  } catch (e) {
    return detailLog;
  }
}

const filteredLogs = computed(() => {
  if (!searchQuery.value) return logs.value;
  
  const query = searchQuery.value.toLowerCase();
  return logs.value.filter(log => 
    log.email.toLowerCase().includes(query) ||
    log.action.toLowerCase().includes(query) ||
    log.description.toLowerCase().includes(query)
  );
});
</script>

<template>
  <!-- Breadcrumbs -->
  <VBreadcrumbs :items="[
    { title: 'Home', to: '/' },
    { title: 'Configuration Settings', to: { name: 'configuration-list' } },
    { title: 'Activity Logs', disabled: true }
  ]" class="mb-6" />

  <VCard>
    <VCardText>
      <div class="d-flex flex-wrap align-center mb-6 gap-2">
        <h1 class="text-h5 text-md-h4 mr-auto mb-2 mb-md-0">
          Configuration Activity Logs
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
        </div>
      </div>

      <!-- Search bar -->
      <div class="d-flex flex-column flex-md-row align-md-center mb-6 gap-2">
        <VSpacer class="d-none d-md-block" />
        
        <VTextField
          v-model="searchQuery"
          density="compact"
          placeholder="Search logs..."
          prepend-inner-icon="ri-search-line"
          hide-details
          class="flex-grow-1 max-width-400"
          single-line
        />
      </div>

      <VDataTable
        :headers="headers"
        :items="filteredLogs"
        :loading="loading"
        density="comfortable"
        hover
        class="elevation-1 rounded"
        :items-per-page="perPage"
        hide-default-footer
      >
        <!-- Date & Time Column (Desktop) -->
        <template #[`item.created_at`]="{ item }">
          <div class="text-break">
            {{ new Date(item.created_at).toLocaleString() }}
          </div>
        </template>
        
        <!-- User Column (Desktop) -->
        <template #[`item.email`]="{ item }">
          <div class="font-weight-medium text-break">
            {{ item.email || 'System' }}
          </div>
        </template>
        
        <!-- Action Column (Desktop) -->
        <template #[`item.action`]="{ item }">
          <VChip
            size="small"
            :color="getActionColor(item.action)"
            variant="flat"
          >
            {{ getActionLabel(item.action) }}
          </VChip>
        </template>
        
        <!-- Description Column (Desktop) -->
        <template #[`item.description`]="{ item }">
          <div class="text-break">
            {{ item.description }}
          </div>
        </template>

        <!-- Activity Column (Mobile) -->
        <template #[`item.activity`]="{ item }">
          <div class="d-flex flex-column">
            <div class="d-flex align-center mb-1">
              <VChip
                size="x-small"
                :color="getActionColor(item.action)"
                variant="flat"
                class="mr-2"
              >
                {{ getActionLabel(item.action) }}
              </VChip>
              <small class="text-muted">{{ new Date(item.created_at).toLocaleString() }}</small>
            </div>
            <div class="font-weight-medium text-break mb-1">
              {{ item.email || 'System' }}
            </div>
            <div class="text-body-2 text-break">
              {{ item.description }}
            </div>
          </div>
        </template>

        <!-- Details/Actions Column -->
        <template #[`item.actions`]="{ item }">
          <div class="d-flex justify-center">
            <VBtn
              icon
              size="small"
              variant="text"
              color="primary"
              @click="toggleExpandLog(item.id)"
            >
              <VIcon size="20">
                {{ isLogExpanded(item.id) ? 'ri-eye-off-line' : 'ri-eye-line' }}
              </VIcon>
              <VTooltip activator="parent">
                {{ isLogExpanded(item.id) ? 'Hide Details' : 'Show Details' }}
              </VTooltip>
            </VBtn>
          </div>
        </template>

        <!-- Expanded row template -->
        <template #expanded-row="{ item }">
          <tr v-if="isLogExpanded(item.id)">
            <td :colspan="headers.length" class="pa-4">
              <VCard variant="outlined" class="ma-2">
                <VCardTitle class="text-subtitle-1 font-weight-medium">
                  <VIcon icon="ri-information-line" class="mr-2" />
                  Log Details
                </VCardTitle>
                <VCardText>
                  <pre class="text-body-2" style=" font-family: monospace;white-space: pre-wrap;">{{ formatLogDetails(item.detail_log) }}</pre>
                </VCardText>
              </VCard>
            </td>
          </tr>
        </template>

        <!-- Empty state -->
        <template #no-data>
          <div class="d-flex flex-column align-center pa-6">
            <VIcon
              size="48"
              color="secondary"
              icon="ri-history-line"
              class="mb-4"
            />
            <h3 class="text-h6 font-weight-regular mb-2">No activity logs found</h3>
            <p class="text-secondary text-center mb-4">
              <span v-if="searchQuery">No logs match your search criteria.</span>
              <span v-else>There are no configuration activity logs to display.</span>
            </p>
            <VBtn
              v-if="searchQuery"
              color="secondary"
              @click="searchQuery = ''"
            >
              Clear Search
            </VBtn>
          </div>
        </template>
      </VDataTable>

      <!-- Custom Pagination -->
      <div v-if="totalPages > 1" class="d-flex justify-center mt-4">
        <VPagination
          v-model="currentPage"
          :length="totalPages"
          :total-visible="isMobile ? 5 : 7"
          @update:model-value="handlePageChange"
        />
      </div>

      <!-- Results summary -->
      <div v-if="!loading && logs.length > 0" class="text-center mt-4">
        <p class="text-body-2 text-muted">
          Showing {{ ((currentPage - 1) * perPage) + 1 }} to {{ Math.min(currentPage * perPage, totalLogs) }} 
          of {{ totalLogs }} results
        </p>
      </div>
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
.max-width-400 {
  max-inline-size: 400px;
}

@media (max-width: 767px) {
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

pre {
  padding: 12px;
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  background-color: #f5f5f5;
  max-block-size: 300px;
  overflow-y: auto;
}
</style>
