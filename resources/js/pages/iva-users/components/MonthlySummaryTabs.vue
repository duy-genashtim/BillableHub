<script setup>
import { enableChartDragScrolling, formatDateTime, formatHours, getPerformanceColor, getResponsiveChartSettings, smoothScrollChart } from '@/@core/utils/worklogHelpers';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import MonthlySummaryBreakdown from './MonthlySummaryBreakdown.vue';
import MonthlySummaryCategoryBreakdown from './MonthlySummaryCategoryBreakdown.vue';

const props = defineProps({
  summaryData: {
    type: Object,
    required: true
  },
  user: {
    type: Object,
    required: true
  },
  userId: {
    type: [String, Number],
    required: true
  },
  isMobile: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['show-snackbar']);

// UI state
const activeTab = ref('overview');

// Chart refs and scrolling
const chartContainer = ref(null);
const chartCleanup = ref(null);

// User Logs
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
const logDialog = ref(false);
const deleteLogDialog = ref(false);
const logToDelete = ref(null);
const editingLog = ref(null);
const logForm = ref({
  log_type: 'note',
  title: '',
  content: '',
  is_private: false
});

const logTypes = ref([
  { label: 'Note', value: 'note' },
  { label: 'NAD', value: 'nad' },
  { label: 'Performance', value: 'performance' }
]);

const tabs = computed(() => [
  { key: 'overview', title: 'Overview', icon: 'ri-dashboard-line' },
  { key: 'monthly', title: 'Monthly Breakdown', icon: 'ri-calendar-month-line' },
  { key: 'categories', title: 'Categories', icon: 'ri-folder-line' },
  { key: 'logs', title: 'User Logs', icon: 'ri-file-text-line' }
]);

// Chart data for overview
const chartData = computed(() => {
  const monthlyBreakdown = props.summaryData?.monthly_breakdown || [];
  return monthlyBreakdown.map(month => ({
    month_number: month.month_number,
    year: month.year,
    label: getMonthAbbreviation(month.month_number),
    full_label: month.label,
    date_range: `${formatShortDate(month.start_date)} - ${formatShortDate(month.end_date)}`,
    billable: month.billable_hours,
    nonBillable: month.non_billable_hours,
    nad: month.nad_hours,
    total: month.billable_hours + month.non_billable_hours,
    nad_count: month.nad_count,
    performance: month.performance?.[0] || null,
    entries_count: month.entries_count,
    weeks_count: month.weekly_breakdown?.length || 0
  }));
});

const maxHours = computed(() => {
  if (!chartData.value.length) return 50;
  const maxTotal = Math.max(...chartData.value.map(d => d.total + d.nad));
  return Math.max(50, Math.ceil(maxTotal / 10) * 10);
});

// Chart settings with responsive design
const chartSettings = computed(() => {
  return getResponsiveChartSettings(props.isMobile, chartData.value.length);
});

function formatShortDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    timeZone: 'UTC'
  });
}

function getMonthAbbreviation(monthNumber) {
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  return months[monthNumber - 1] || monthNumber.toString();
}

function getMonthName(monthNumber) {
  const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  return months[monthNumber - 1] || monthNumber.toString();
}

// Setup chart drag scrolling
onMounted(() => {
  if (chartContainer.value) {
    chartCleanup.value = enableChartDragScrolling(chartContainer.value);
  }
});

onUnmounted(() => {
  if (chartCleanup.value) {
    chartCleanup.value();
  }
});

// Chart navigation functions
function scrollChartLeft() {
  if (chartContainer.value) {
    smoothScrollChart(chartContainer.value, 'left', 200);
  }
}

function scrollChartRight() {
  if (chartContainer.value) {
    smoothScrollChart(chartContainer.value, 'right', 200);
  }
}

// User Logs Functions
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

    const response = await axios.get(`/api/admin/iva-users/${props.userId}/logs`, { params });

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
      response = await axios.put(`/api/admin/iva-users/${props.userId}/logs/${editingLog.value.id}`, logForm.value);

      // Update local data
      const index = logs.value.findIndex(l => l.id === editingLog.value.id);
      if (index !== -1) {
        logs.value[index] = response.data.log;
      }

      emit('show-snackbar', 'Log entry updated successfully', 'success');
    } else {
      // Create new log
      response = await axios.post(`/api/admin/iva-users/${props.userId}/logs`, logForm.value);

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
    await axios.delete(`/api/admin/iva-users/${props.userId}/logs/${logToDelete.value.id}`);

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
  const type = logTypes.value.find(t => t.value === logType);
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

// Watch for tab changes to load logs when needed
watch(() => activeTab.value, (newTab) => {
  if (newTab === 'logs') {
    fetchLogs();
  }
});
</script>

<template>
  <div>
    <!-- Main Content Tabs -->
    <VTabs v-model="activeTab" class="mb-6">
      <VTab v-for="tab in tabs" :key="tab.key" :value="tab.key" class="text-none">
        <VIcon :icon="tab.icon" class="mr-2" />
        {{ tab.title }}
      </VTab>
    </VTabs>

    <!-- Tab Content -->
    <VWindow v-model="activeTab">
      <!-- Overview Tab -->
      <VWindowItem value="overview">
        <VCard>
          <VCardText>
            <div class="d-flex justify-space-between align-center mb-4">
              <h3 class="text-h6 font-weight-medium">Monthly Hours Chart</h3>

              <!-- Chart Navigation Controls -->
              <div v-if="chartSettings.needsHorizontalScroll" class="d-flex gap-2">
                <VBtn icon="ri-arrow-left-s-line" size="small" variant="outlined" @click="scrollChartLeft"
                  aria-label="Scroll chart left" />
                <VBtn icon="ri-arrow-right-s-line" size="small" variant="outlined" @click="scrollChartRight"
                  aria-label="Scroll chart right" />
              </div>
            </div>

            <div v-if="chartData.length > 0" class="enhanced-chart-container">
              <div ref="chartContainer" class="chart-wrapper" :style="{
                height: `${chartSettings.chartHeight + 140}px`,
                overflowX: chartSettings.needsHorizontalScroll ? 'auto' : 'hidden',
                overflowY: 'hidden',
                cursor: chartSettings.needsHorizontalScroll ? 'grab' : 'default'
              }">
                <div class="chart-grid" :style="{
                  height: `${chartSettings.chartHeight + 60}px`,
                  width: `${Math.max(800, chartData.length * (chartSettings.barWidth + 20) + chartSettings.padding * 2)}px`,
                  position: 'relative',
                  minWidth: '100%'
                }">
                  <!-- Y-axis hour lines starting from 0 -->
                  <div v-for="hour in Math.ceil(maxHours / 10) + 1" :key="(hour - 1) * 10" class="hour-line" :style="{
                    position: 'absolute',
                    top: `${((maxHours - ((hour - 1) * 10)) / maxHours) * (chartSettings.chartHeight - 60) + 30}px`,
                    left: `${chartSettings.padding - 10}px`,
                    right: '20px',
                    height: '1px',
                    backgroundColor: (hour - 1) % 2 === 0 ? '#e0e0e0' : '#f5f5f5',
                    zIndex: 1
                  }">
                    <span class="hour-label" :style="{
                      position: 'absolute',
                      left: `-${chartSettings.padding - 15}px`,
                      top: '-8px',
                      fontSize: `${chartSettings.fontSize}px`,
                      color: '#666',
                      fontWeight: (hour - 1) % 2 === 0 ? '600' : '400',
                      whiteSpace: 'nowrap'
                    }">
                      {{ (hour - 1) * 10 }}h
                    </span>
                  </div>

                  <!-- Chart bars -->
                  <div class="d-flex align-end chart-bars" :style="{
                    height: `${chartSettings.chartHeight - 60}px`,
                    marginTop: '19px',
                    marginLeft: `${chartSettings.padding}px`,
                    marginRight: '20px',
                    position: 'relative',
                    zIndex: 2,
                    gap: '20px'
                  }">
                    <div v-for="month in chartData" :key="`${month.year}-${month.month_number}`"
                      class="chart-bar-container" :style="{
                        width: `${chartSettings.barWidth}px`,
                        position: 'relative',
                        flexShrink: 0
                      }">
                      <!-- Bar Stack -->
                      <div class="bar-stack" :style="{
                        height: `${chartSettings.chartHeight - 60}px`,
                        display: 'flex',
                        flexDirection: 'column-reverse',
                        cursor: 'pointer'
                      }"
                        :title="`${month.full_label}: ${formatHours(month.total)} work hours + ${formatHours(month.nad)} NAD hours (${month.nad_count} NADs) - ${month.weeks_count} weeks`">
                        <!-- Billable Hours Bar -->
                        <div v-if="month.billable > 0" class="bar-segment billable-bar" :style="{
                          height: `${(month.billable / maxHours) * (chartSettings.chartHeight - 60)}px`,
                          backgroundColor: '#4CAF50',
                          borderRadius: '0 0 6px 6px',
                          marginBottom: '1px',
                          transition: 'all 0.3s ease',
                          boxShadow: '0 2px 4px rgba(76, 175, 80, 0.3)'
                        }" />

                        <!-- Non-Billable Hours Bar -->
                        <div v-if="month.nonBillable > 0" class="bar-segment non-billable-bar" :style="{
                          height: `${(month.nonBillable / maxHours) * (chartSettings.chartHeight - 60)}px`,
                          backgroundColor: '#2196F3',
                          transition: 'all 0.3s ease',
                          boxShadow: '0 2px 4px rgba(33, 150, 243, 0.3)',
                          marginBottom: '1px'
                        }" />

                        <!-- NAD Hours Bar -->
                        <div v-if="month.nad > 0" class="bar-segment nad-bar" :style="{
                          height: `${(month.nad / maxHours) * (chartSettings.chartHeight - 60)}px`,
                          backgroundColor: '#FF9800',
                          borderRadius: '6px 6px 0 0',
                          transition: 'all 0.3s ease',
                          boxShadow: '0 2px 4px rgba(255, 152, 0, 0.3)'
                        }" />
                      </div>

                      <!-- Performance Indicator -->
                      <div v-if="month.performance" class="performance-indicator" :style="{
                        position: 'absolute',
                        top: '-25px',
                        left: '50%',
                        transform: 'translateX(-50%)',
                        fontSize: '10px',
                        fontWeight: '600',
                        padding: '2px 6px',
                        borderRadius: '10px',
                        whiteSpace: 'nowrap'
                      }" :class="`bg-${getPerformanceColor(month.performance.status)} text-white`">
                        {{ month.performance.percentage }}%
                      </div>

                      <!-- Month Label -->
                      <div class="month-info text-center" :style="{
                        position: 'absolute',
                        top: `${chartSettings.chartHeight - 30}px`,
                        left: '0',
                        right: '0',
                        height: '60px',
                        display: 'flex',
                        flexDirection: 'column',
                        justifyContent: 'center',
                        alignItems: 'center',
                        padding: '8px 4px'
                      }">
                        <div class="text-subtitle-2 font-weight-bold text-primary">
                          {{ month.label }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                          {{ month.year }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                          {{ month.weeks_count }}w
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Legend -->
              <div class="d-flex justify-center gap-6 mt-6 pt-4 border-t flex-wrap">
                <div class="d-flex align-center">
                  <div class="legend-color mr-2" style="
                      border-radius: 4px;
                      background-color: #4caf50;
                      block-size: 16px;
                      box-shadow: 0 2px 4px rgba(76, 175, 80, 30%);
                      inline-size: 16px;
"></div>
                  <span class="text-body-2 font-weight-medium">Billable Hours</span>
                </div>
                <div class="d-flex align-center">
                  <div class="legend-color mr-2" style="
                      border-radius: 4px;
                      background-color: #2196f3;
                      block-size: 16px;
                      box-shadow: 0 2px 4px rgba(33, 150, 243, 30%);
                      inline-size: 16px;
"></div>
                  <span class="text-body-2 font-weight-medium">Non-Billable Hours</span>
                </div>
                <div class="d-flex align-center">
                  <div class="legend-color mr-2" style="
                      border-radius: 4px;
                      background-color: #ff9800;
                      block-size: 16px;
                      box-shadow: 0 2px 4px rgba(255, 152, 0, 30%);
                      inline-size: 16px;
"></div>
                  <span class="text-body-2 font-weight-medium">NAD Hours</span>
                </div>
              </div>

              <!-- Scroll Hint -->
              <div v-if="chartSettings.needsHorizontalScroll" class="text-center mt-4">
                <VChip size="small" color="info" variant="outlined" prepend-icon="ri-drag-move-line">
                  Drag to scroll or use arrow buttons
                </VChip>
              </div>
            </div>

            <div v-else class="text-center py-8">
              <VIcon size="48" color="secondary" icon="ri-line-chart-line" class="mb-4" />
              <p class="text-secondary">No data available for the selected months</p>
            </div>
          </VCardText>
        </VCard>
      </VWindowItem>

      <!-- Monthly Breakdown Tab -->
      <VWindowItem value="monthly">
        <MonthlySummaryBreakdown :monthly-breakdown="summaryData?.monthly_breakdown || []" :is-mobile="isMobile" />
      </VWindowItem>

      <!-- Categories Tab -->
      <VWindowItem value="categories">
        <MonthlySummaryCategoryBreakdown :category-breakdown="summaryData?.category_breakdown || []"
          :is-mobile="isMobile" />
      </VWindowItem>

      <!-- User Logs Tab -->
      <VWindowItem value="logs">
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
                    <span v-if="log.updated_at !== log.created_at"> • Updated: {{ formatDateTime(log.updated_at)
                    }}</span>
                  </div>
                </VCardText>
              </VCard>

              <!-- Pagination -->
              <div class="d-flex justify-center mt-4">
                <VPagination v-model="logsPagination.page"
                  :length="Math.ceil(logsPagination.total / logsPagination.perPage)" :total-visible="isMobile ? 3 : 7"
                  @update:model-value="handleLogsPageChange" />
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
      </VWindowItem>
    </VWindow>

    <!-- Log Dialog -->
    <VDialog v-model="logDialog" max-width="700" persistent>
      <VCard>
        <VCardTitle class="text-h5 bg-primary text-white">
          {{ editingLog ? 'Edit Log Entry' : 'Add Log Entry' }}
        </VCardTitle>

        <VCardText class="pt-4">
          <VRow>
            <VCol cols="12" md="6">
              <VSelect v-model="logForm.log_type" :items="logTypes" item-title="label" item-value="value"
                label="Log Type" density="comfortable" variant="outlined" required />
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
  </div>
</template>

<style scoped>
/* Enhanced chart styling */
.enhanced-chart-container {
  padding: 24px;
  border: 1px solid #e0e0e0;
  border-radius: 12px;
  background: linear-gradient(135deg, #fafafa 0%, #f5f5f5 100%);
  inline-size: 100%;
}

.chart-wrapper {
  position: relative;
  padding: 12px;
  border-radius: 8px;
  background: white;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 5%);
}

.chart-grid {
  padding: 12px;
}

.bar-segment {
  border: 1px solid rgba(255, 255, 255, 20%);
  transition: all 0.3s ease;
}

.bar-segment:hover {
  filter: brightness(1.1);
  opacity: 0.85;
  transform: scaleX(1.02);
}

.month-info {
  margin: 0;
  padding-block: 8px;
  padding-inline: 4px;
}

.chart-bar-container:hover .bar-segment {
  filter: brightness(1.05);
}

.chart-bar-container:hover .month-info {
  color: var(--v-theme-primary);
}

.performance-indicator {
  font-size: 10px !important;
}

/* Scrollbar styling for chart */
.chart-wrapper::-webkit-scrollbar {
  block-size: 8px;
}

.chart-wrapper::-webkit-scrollbar-track {
  border-radius: 4px;
  background: #f1f1f1;
}

.chart-wrapper::-webkit-scrollbar-thumb {
  border-radius: 4px;
  background: #c1c1c1;
}

.chart-wrapper::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}

/* Chart navigation */
.chart-wrapper[style*="cursor: grab"]:active {
  cursor: grabbing !important;
}

/* Enhanced focus states for accessibility */
:deep(.v-btn:focus-visible) {
  outline: 2px solid var(--v-theme-primary);
  outline-offset: 2px;
}

@media (max-width: 767px) {
  .enhanced-chart-container {
    padding: 16px;
  }

  .legend-color {
    block-size: 12px !important;
    inline-size: 12px !important;
  }

  .text-body-2 {
    font-size: 0.75rem;
  }

  .performance-indicator {
    font-size: 8px !important;
    padding-block: 1px !important;
    padding-inline: 4px !important;
  }

  :deep(.v-card-text) {
    padding: 16px;
  }
}
</style>
