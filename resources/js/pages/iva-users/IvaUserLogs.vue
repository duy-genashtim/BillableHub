<script setup>
import { formatDateTime } from '@/@core/utils/helpers';
import axios from 'axios';
import { ref, watch } from 'vue';

const props = defineProps({
  user: {
    type: Object,
    required: true
  },
  logTypes: {
    type: Array,
    default: () => []
  },
  isMobile: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['show-snackbar']);

// Dialog states
const logDialog = ref(false);
const deleteLogDialog = ref(false);
const logToDelete = ref(null);

// Log form
const logForm = ref({
  log_type: 'note',
  title: '',
  content: '',
  is_private: false
});

// Log management
const logs = ref([]);
const logsPagination = ref({
  page: 1,
  total: 0,
  perPage: 20
});
const logsFilters = ref({
  log_type: null,
  is_private: null
});
const logsLoading = ref(false);
const editingLog = ref(null);

async function fetchLogs() {
  logsLoading.value = true;

  try {
    const params = {
      page: logsPagination.value.page,
      per_page: logsPagination.value.perPage,
      ...logsFilters.value
    };

    // Remove null/empty filters
    Object.keys(params).forEach(key => {
      if (params[key] === null || params[key] === '') {
        delete params[key];
      }
    });

    const response = await axios.get(`/api/admin/iva-users/${props.user.id}/logs`, { params });

    logs.value = response.data.logs.data;
    logsPagination.value.total = response.data.logs.total;
    logsPagination.value.page = response.data.logs.current_page;
    logsPagination.value.perPage = response.data.logs.per_page;

  } catch (error) {
    console.error('Error fetching logs:', error);
    emit('show-snackbar', 'Failed to load logs', 'error');
  } finally {
    logsLoading.value = false;
  }
}

function openLogDialog() {
  editingLog.value = null;
  logForm.value = {
    log_type: 'note',
    title: '',
    content: '',
    is_private: false
  };
  logDialog.value = true;
}

function openEditLogDialog(log) {
  editingLog.value = log;
  logForm.value = {
    log_type: log.log_type,
    title: log.title || '',
    content: log.content,
    is_private: log.is_private
  };
  logDialog.value = true;
}

async function saveLog() {
  try {
    let response;
    if (editingLog.value) {
      // Update existing log
      response = await axios.put(`/api/admin/iva-users/${props.user.id}/logs/${editingLog.value.id}`, logForm.value);

      // Update local data
      const index = logs.value.findIndex(l => l.id === editingLog.value.id);
      if (index !== -1) {
        logs.value[index] = response.data.log;
      }

      emit('show-snackbar', 'Log entry updated successfully', 'success');
    } else {
      // Create new log
      response = await axios.post(`/api/admin/iva-users/${props.user.id}/logs`, logForm.value);

      // Add to local data
      logs.value.unshift(response.data.log);

      emit('show-snackbar', 'Log entry added successfully', 'success');
    }

    logDialog.value = false;
  } catch (error) {
    console.error('Error saving log:', error);
    emit('show-snackbar', error.response?.data?.message || 'Failed to save log entry', 'error');
  }
}

function openDeleteLogDialog(log) {
  logToDelete.value = log;
  deleteLogDialog.value = true;
}

async function confirmDeleteLog() {
  if (!logToDelete.value) return;

  try {
    await axios.delete(`/api/admin/iva-users/${props.user.id}/logs/${logToDelete.value.id}`);

    // Remove from local data
    logs.value = logs.value.filter(l => l.id !== logToDelete.value.id);

    emit('show-snackbar', 'Log entry deleted successfully', 'success');

    deleteLogDialog.value = false;
    logToDelete.value = null;

  } catch (error) {
    console.error('Error deleting log:', error);
    emit('show-snackbar', error.response?.data?.message || 'Failed to delete log entry', 'error');
  }
}

function handleLogsPageChange(page) {
  logsPagination.value.page = page;
  fetchLogs();
}

function clearLogsFilters() {
  logsFilters.value = {
    log_type: null,
    is_private: null
  };
  logsPagination.value.page = 1;
  fetchLogs();
}

function getLogTypeColor(logType) {
  switch (logType) {
    case 'note': return 'primary';
    case 'nad': return 'warning';
    case 'performance': return 'info';
    default: return 'secondary';
  }
}

function getLogTypeDisplay(logType) {
  const type = props.logTypes.find(t => t.value === logType);
  return type ? type.label : logType.charAt(0).toUpperCase() + logType.slice(1);
}

// Watch for logs filters changes
watch([
  () => logsFilters.value.log_type,
  () => logsFilters.value.is_private
], () => {
  logsPagination.value.page = 1;
  fetchLogs();
});

// Initialize logs when component is mounted
fetchLogs();

defineExpose({
  fetchLogs
});
</script>

<template>
  <VCard>
    <VCardText>
      <div class="d-flex align-center mb-4">
        <h2 class="text-h6 font-weight-medium">User Logs</h2>
        <VSpacer />
        <VBtn color="primary" prepend-icon="ri-add-line" @click="openLogDialog" aria-label="Add log entry">
          Add Log
        </VBtn>
      </div>

      <!-- Filters -->
      <VRow class="mb-4">
        <VCol cols="12" md="3">
          <VSelect v-model="logsFilters.log_type" :items="logTypes" item-title="label" item-value="value"
            label="Log Type" density="comfortable" variant="outlined" clearable />
        </VCol>
        <VCol cols="12" md="3">
          <VSelect v-model="logsFilters.is_private" :items="[
            { title: 'Public', value: false },
            { title: 'Private', value: true }
          ]" label="Privacy" density="comfortable" variant="outlined" clearable />
        </VCol>
        <VCol cols="12" md="3">
          <VBtn v-if="Object.values(logsFilters).some(v => v !== null)" color="secondary" variant="outlined"
            @click="clearLogsFilters">
            Clear Filters
          </VBtn>
        </VCol>
      </VRow>

      <!-- Logs List -->
      <div v-if="logsLoading" class="d-flex justify-center py-4">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <div v-else-if="logs && logs.length > 0">
        <VCard v-for="log in logs" :key="log.id" variant="outlined" class="mb-4">
          <VCardText>
            <div class="d-flex align-center mb-2">
              <VChip :color="getLogTypeColor(log.log_type)" size="small" class="mr-2">
                {{ getLogTypeDisplay(log.log_type) }}
              </VChip>
              <VChip v-if="log.is_private" color="warning" size="small" class="mr-2">
                Private
              </VChip>
              <VSpacer />
              <VBtn icon size="small" variant="text" @click="openEditLogDialog(log)">
                <VIcon size="18">ri-edit-line</VIcon>
              </VBtn>
              <VBtn icon size="small" variant="text" color="error" @click="openDeleteLogDialog(log)">
                <VIcon size="18">ri-delete-bin-line</VIcon>
              </VBtn>
            </div>

            <h3 v-if="log.title" class="text-subtitle-1 font-weight-medium mb-2">{{ log.title }}</h3>
            <p class="text-body-2 mb-3" style="white-space: pre-wrap;">{{ log.content }}</p>

            <div class="text-caption text-secondary">
              <span>By: {{ log.creator?.name || 'Unknown' }} • </span>
              <span>{{ formatDateTime(log.created_at) }}</span>
              <span v-if="log.updated_at !== log.created_at"> • Updated: {{ formatDateTime(log.updated_at) }}</span>
            </div>
          </VCardText>
        </VCard>

        <!-- Pagination -->
        <div class="d-flex justify-center mt-4">
          <VPagination v-model="logsPagination.page" :length="Math.ceil(logsPagination.total / logsPagination.perPage)"
            :total-visible="isMobile ? 3 : 7" @update:model-value="handleLogsPageChange" />
        </div>
      </div>

      <div v-else class="text-center py-8">
        <VIcon size="48" color="secondary" icon="ri-file-text-line" class="mb-4" />
        <h3 class="text-h6 font-weight-regular mb-2">No logs found</h3>
        <p class="text-secondary mb-4">
          <span v-if="Object.values(logsFilters).some(v => v !== null)">
            No logs match your current filters.
          </span>
          <span v-else>
            No logs have been created for this user yet.
          </span>
        </p>
        <VBtn color="primary" @click="openLogDialog">
          Add First Log
        </VBtn>
      </div>
    </VCardText>
  </VCard>

  <!-- Log Dialog -->
  <VDialog v-model="logDialog" max-width="700" persistent>
    <VCard>
      <VCardTitle class="text-h5 bg-primary text-white">
        {{ editingLog ? 'Edit Log Entry' : 'Add Log Entry' }}
      </VCardTitle>

      <VCardText class="pt-4">
        <VRow>
          <VCol cols="12" md="6">
            <VSelect v-model="logForm.log_type" :items="logTypes" item-title="label" item-value="value" label="Log Type"
              density="comfortable" variant="outlined" required />
          </VCol>

          <VCol cols="12" md="6">
            <VCheckbox v-model="logForm.is_private" label="Private Log"
              hint="Private logs are only visible to administrators" persistent-hint />
          </VCol>

          <VCol cols="12">
            <VTextField v-model="logForm.title" label="Title (Optional)" density="comfortable" variant="outlined" />
          </VCol>

          <VCol cols="12">
            <VTextarea v-model="logForm.content" label="Content" density="comfortable" variant="outlined" required
              rows="5" hint="Enter the log content here" persistent-hint />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn color="secondary" variant="outlined" @click="logDialog = false">
          Cancel
        </VBtn>
        <VBtn color="primary" @click="saveLog" :disabled="!logForm.content">
          {{ editingLog ? 'Update Log' : 'Add Log' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Delete Log Confirmation Dialog -->
  <VDialog v-model="deleteLogDialog" max-width="500" persistent role="alertdialog">
    <VCard>
      <VCardTitle class="text-h5 bg-error text-white">
        Delete Log Entry
      </VCardTitle>

      <VCardText class="pt-4">
        <p class="mb-4">Are you sure you want to delete this log entry?</p>

        <div v-if="logToDelete" class="mb-3 pa-3 bg-grey-lighten-4 rounded">
          <p class="mb-1"><strong>Type:</strong> {{ getLogTypeDisplay(logToDelete.log_type) }}</p>
          <p v-if="logToDelete.title" class="mb-1"><strong>Title:</strong> {{ logToDelete.title }}</p>
          <p class="mb-1"><strong>Content:</strong> {{ logToDelete.content.substring(0, 100) }}{{
            logToDelete.content.length > 100 ? '...' : '' }}</p>
          <p class="mb-0"><strong>Created:</strong> {{ formatDateTime(logToDelete.created_at) }}</p>
        </div>

        <p class="text-body-2 text-secondary mb-0">This action cannot be undone.</p>
      </VCardText>

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn color="secondary" variant="outlined" @click="deleteLogDialog = false">
          Cancel
        </VBtn>
        <VBtn color="error" @click="confirmDeleteLog">
          Delete Log
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
