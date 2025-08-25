<script setup>
import { enableChartDragScrolling, formatDate, formatDateTime, formatHours, getResponsiveChartSettings, smoothScrollChart } from '@/@core/utils/worklogHelpers';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
  dashboardData: {
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
  },
  dateMode: {
    type: String,
    default: 'weeks'
  }
});

const emit = defineEmits(['show-snackbar']);

// UI state
const activeTab = ref('overview');
const expandedCategories = ref({});
const expandedTasks = ref({});
const categoryTasks = ref({});
const loadingCategoryTasks = ref({});

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
  { key: 'daily', title: 'Daily Breakdown', icon: 'ri-calendar-line' },
  { key: 'categories', title: 'Categories', icon: 'ri-folder-line' },
  { key: 'logs', title: 'User Logs', icon: 'ri-file-text-line' }
]);

const chartData = computed(() => {
  const data = props.dashboardData;
  if (!data?.daily_breakdown) return [];

  return data.daily_breakdown.map(day => ({
    date: day.date,
    day_name: day.day_name,
    day_short: day.day_name.substring(0, 3),
    day_number: new Date(day.date).getDate(),
    billable: day.billable_hours,
    nonBillable: day.non_billable_hours,
    total: day.total_hours,
    entries_count: day.entries_count,
  }));
});

const maxDailyHours = computed(() => {
  if (!chartData.value.length) return 8;
  return Math.max(8, Math.ceil(Math.max(...chartData.value.map(d => d.total))));
});

// Chart settings with responsive design
const chartSettings = computed(() => {
  return getResponsiveChartSettings(props.isMobile, chartData.value.length);
});

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

async function toggleCategory(categoryName, categoryId = null) {
  expandedCategories.value[categoryName] = !expandedCategories.value[categoryName];
  
  // If expanding and category has ID, fetch tasks
  if (expandedCategories.value[categoryName] && categoryId && !categoryTasks.value[categoryId]) {
    await fetchTasksByCategory(categoryId, categoryName);
  }
}

async function fetchTasksByCategory(categoryId, categoryName) {
  loadingCategoryTasks.value[categoryId] = true;
  
  try {
    const params = {
      category_id: categoryId,
      start_date: props.dashboardData.date_range?.start,
      end_date: props.dashboardData.date_range?.end
    };

    // Handle different date range sources
    if (props.dashboardData.bimonthly_data) {
      params.start_date = props.dashboardData.bimonthly_data.first_half?.date_range?.start;
      params.end_date = props.dashboardData.bimonthly_data.second_half?.date_range?.end;
    } else if (props.dashboardData.weekly_summary_data) {
      params.start_date = props.dashboardData.weekly_summary_data.date_range?.start;
      params.end_date = props.dashboardData.weekly_summary_data.date_range?.end;
    } else if (props.dashboardData.monthly_summary_data) {
      params.start_date = props.dashboardData.monthly_summary_data.date_range?.start;
      params.end_date = props.dashboardData.monthly_summary_data.date_range?.end;
    }

    const response = await axios.get(`/api/admin/iva-users/${props.userId}/worklog-dashboard/tasks-by-category`, { params });
    
    categoryTasks.value[categoryId] = response.data.data;
    
  } catch (error) {
    console.error('Error fetching tasks for category:', error);
    emit('show-snackbar', 'Failed to load tasks for category', 'error');
    categoryTasks.value[categoryId] = [];
  } finally {
    loadingCategoryTasks.value[categoryId] = false;
  }
}

function toggleTask(categoryName, taskKey) {
  const key = `${categoryName}-${taskKey}`;
  expandedTasks.value[key] = !expandedTasks.value[key];
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
              <h3 class="text-h6 font-weight-medium">Daily Hours Chart</h3>

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
                height: `${chartSettings.chartHeight + 80}px`,
                overflowX: chartSettings.needsHorizontalScroll ? 'auto' : 'hidden',
                overflowY: 'hidden',
                cursor: chartSettings.needsHorizontalScroll ? 'grab' : 'default'
              }">
                <div class="chart-grid" :style="{
                  height: `${chartSettings.chartHeight}px`,
                  width: `${chartSettings.chartWidth}px`,
                  position: 'relative',
                  minWidth: '100%'
                }">
                  <!-- Y-axis hour lines -->
                  <div v-for="hour in [...Array(maxDailyHours + 1).keys()]" :key="hour" class="hour-line" :style="{
                    position: 'absolute',
                    top: `${((maxDailyHours - hour) / maxDailyHours) * (chartSettings.chartHeight - 60) + 30}px`,
                    left: `${chartSettings.padding - 10}px`,
                    right: '20px',
                    height: '1px',
                    backgroundColor: hour % 2 === 0 ? '#e0e0e0' : '#f5f5f5',
                    zIndex: 1
                  }">
                    <span class="hour-label" :style="{
                      position: 'absolute',
                      left: `-${chartSettings.padding - 15}px`,
                      top: '-8px',
                      fontSize: `${chartSettings.fontSize}px`,
                      color: '#666',
                      fontWeight: hour % 4 === 0 ? '600' : '400',
                      whiteSpace: 'nowrap'
                    }">
                      {{ hour }}h
                    </span>
                  </div>


                  <!-- Chart bars -->
                  <div class="d-flex justify-space-between align-end chart-bars" :style="{
                    height: `${chartSettings.chartHeight - 60}px`,
                    marginTop: '76px',
                    marginLeft: `${chartSettings.padding}px`,
                    marginRight: '20px',
                    position: 'relative',
                    zIndex: 2,
                    gap: '15px'
                  }">
                    <div v-for="day in chartData" :key="day.date" class="chart-bar-container" :style="{
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
                        :title="`${day.day_name} ${day.day_number}: ${formatHours(day.total)} total (${formatHours(day.billable)} billable, ${formatHours(day.nonBillable)} non-billable)`">
                        <!-- Billable Hours Bar -->
                        <div v-if="day.billable > 0" class="bar-segment billable-bar" :style="{
                          height: `${(day.billable / maxDailyHours) * (chartSettings.chartHeight - 60)}px`,
                          backgroundColor: '#4CAF50',
                          borderRadius: '0 0 6px 6px',
                          marginBottom: '1px',
                          transition: 'all 0.3s ease',
                          boxShadow: '0 2px 4px rgba(76, 175, 80, 0.3)'
                        }" />

                        <!-- Non-Billable Hours Bar -->
                        <div v-if="day.nonBillable > 0" class="bar-segment non-billable-bar" :style="{
                          height: `${(day.nonBillable / maxDailyHours) * (chartSettings.chartHeight - 60)}px`,
                          backgroundColor: '#2196F3',
                          borderRadius: '6px 6px 0 0',
                          transition: 'all 0.3s ease',
                          boxShadow: '0 2px 4px rgba(33, 150, 243, 0.3)'
                        }" />
                      </div>

                      <!-- Day Label -->
                      <div class="day-info text-center ">
                        <div class="text-subtitle-2 font-weight-bold text-primary">
                          {{ day.day_short }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                          {{ day.day_number }}
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Legend -->
              <div class="d-flex justify-center gap-6 mt-6 pt-4 border-t">
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
              <p class="text-secondary">No data available for the selected period</p>
            </div>
          </VCardText>
        </VCard>
      </VWindowItem>

      <!-- Daily Breakdown Tab -->
      <VWindowItem value="daily">
        <VCard>
          <VCardText>
            <h3 class="text-h6 font-weight-medium mb-4">Daily Hours Breakdown</h3>

            <VDataTable :headers="[
              { title: 'Date', key: 'date' },
              { title: 'Day', key: 'day_name' },
              { title: 'Billable Hours', key: 'billable_hours' },
              { title: 'Non-Billable Hours', key: 'non_billable_hours' },
              { title: 'Entries', key: 'entries_count' }
            ]" :items="dashboardData?.daily_breakdown || []" density="comfortable" class="elevation-1">
              <template #[`item.date`]="{ item }">
                <span>{{ formatDate(new Date(item.date)) }}</span>
              </template>

              <template #[`item.billable_hours`]="{ item }">
                <VChip size="small" color="success" variant="outlined">
                  {{ formatHours(item.billable_hours) }}
                </VChip>
              </template>

              <template #[`item.non_billable_hours`]="{ item }">
                <VChip size="small" color="info" variant="outlined">
                  {{ formatHours(item.non_billable_hours) }}
                </VChip>
              </template>
            </VDataTable>
          </VCardText>
        </VCard>
      </VWindowItem>

      <!-- Categories Tab -->
      <VWindowItem value="categories">
        <VCard>
          <VCardText>
            <h2 class="text-h6 font-weight-medium mb-4">Work Category Breakdown</h2>

            <div v-if="!dashboardData?.category_breakdown_cat?.length" class="text-center py-8">
              <VIcon size="48" icon="ri-folder-open-line" color="grey-lighten-1" class="mb-2" />
              <p class="text-body-2">No work entries found for the selected period</p>
            </div>

            <div v-else class="category-breakdown">
              <!-- Main Category Level (Billable/Non-Billable) -->
              <div v-for="mainCategory in dashboardData.category_breakdown_cat" :key="mainCategory.type"
                class="main-category-section mb-6">
                <!-- Main Category Header -->
                <VCard variant="elevated" class="mb-3"
                  :color="mainCategory.type.includes('Billable') ? 'success-light' : 'info'">
                  <VCardItem class="cursor-pointer " @click="toggleCategory(mainCategory.type)">
                    <template v-slot:prepend>
                      <VAvatar :color="mainCategory.type.includes('Billable') ? 'success' : 'info'" variant="flat"
                        size="32">
                        <VIcon
                          :icon="mainCategory.type.includes('Billable') ? 'ri-money-dollar-circle-line' : 'ri-time-line'"
                          size="18" />
                      </VAvatar>
                    </template>

                    <VCardTitle class="d-flex align-center">
                      <span class="mr-3">{{ mainCategory.type }}</span>
                      <VChip size="small" :text-color="mainCategory.type.includes('Billable') ? 'success' : 'info'">
                        {{ formatHours(mainCategory.total_hours) }}
                      </VChip>
                      <VChip size="small" variant="outlined" class="ml-2"
                        :text-color="mainCategory.type.includes('Billable') ? 'success' : 'info'">
                        {{ mainCategory.categories_count }} {{ mainCategory.categories_count === 1 ? 'category' :
                          'categories' }}
                      </VChip>
                    </VCardTitle>

                    <template v-slot:append>
                      <VIcon
                        :icon="expandedCategories[mainCategory.type] ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'"
                        color="white" />
                    </template>
                  </VCardItem>
                </VCard>

                <!-- Categories within Main Category -->
                <VExpandTransition>
                  <div v-show="expandedCategories[mainCategory.type]" class="ml-4">
                    <div v-for="category in mainCategory.categories" :key="category.category_name"
                      class="category-section mb-4">
                      <!-- Category Header -->
                      <VCard variant="outlined" class="mb-2">
                        <VCardItem class="cursor-pointer"
                          @click="toggleCategory(mainCategory.type + '-' + category.category_name, category.category_id)">
                          <template v-slot:prepend>
                            <VAvatar color="primary" variant="tonal" size="24">
                              <VIcon icon="ri-folder-line" size="12" />
                            </VAvatar>
                          </template>

                          <VCardTitle class="d-flex align-center">
                            <span class="mr-3">{{ category.category_name }}</span>
                            <VChip color="primary" size="small">
                              {{ formatHours(category.total_hours) }}
                            </VChip>
                            <VChip color="info" size="small" variant="outlined" class="ml-2">
                              {{ category.entries_count }} {{ category.entries_count === 1 ? 'entry' : 'entries' }}
                            </VChip>
                          </VCardTitle>

                          <template v-slot:append>
                            <VIcon
                              :icon="expandedCategories[mainCategory.type + '-' + category.category_name] ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'" />
                          </template>
                        </VCardItem>
                      </VCard>

                      <!-- Tasks in Category -->
                      <VExpandTransition>
                        <div v-show="expandedCategories[mainCategory.type + '-' + category.category_name]" class="ml-4">
                          <!-- Loading State -->
                          <div v-if="loadingCategoryTasks[category.category_id]" class="text-center py-4">
                            <VProgressCircular indeterminate color="primary" size="24" width="3" />
                            <p class="text-body-2 mt-2">Loading tasks...</p>
                          </div>
                          
                          <!-- No Tasks Found -->
                          <div v-else-if="categoryTasks[category.category_id] && categoryTasks[category.category_id].length === 0" class="text-center py-4">
                            <VIcon size="32" icon="ri-task-line" color="grey-lighten-1" class="mb-2" />
                            <p class="text-body-2">No tasks found for this category</p>
                          </div>

                          <!-- Tasks List -->
                          <div v-else-if="categoryTasks[category.category_id]">
                            <div v-for="task in categoryTasks[category.category_id]" :key="task.task_id" class="task-section mb-3">
                            <!-- Task Header -->
                            <VCard variant="tonal" color="grey-lighten-4" class="mb-2">
                              <VCardItem class="cursor-pointer"
                                @click="toggleTask(mainCategory.type + '-' + category.category_name, task.task_id)">
                                <template v-slot:prepend>
                                  <VAvatar color="secondary" variant="tonal" size="20">
                                    <VIcon icon="ri-task-line" size="10" />
                                  </VAvatar>
                                </template>

                                <VCardTitle class="text-body-1">
                                  <div class="font-weight-medium">{{ task.task_name }}</div>
                                </VCardTitle>

                                <template v-slot:append>
                                  <div class="d-flex align-center gap-2">
                                    <VChip color="secondary" size="small">
                                      {{ formatHours(task.total_hours) }}
                                    </VChip>
                                    <VChip color="info" size="small" variant="outlined">
                                      {{ task.entries_count }} {{ task.entries_count === 1 ? 'entry' : 'entries'
                                      }}
                                    </VChip>
                                    <VIcon
                                      :icon="expandedTasks[mainCategory.type + '-' + category.category_name + '-' + task.task_id] ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'"
                                      size="14" />
                                  </div>
                                </template>
                              </VCardItem>
                            </VCard>

                            <!-- Task Entries -->
                            <VExpandTransition>
                              <div
                                v-show="expandedTasks[mainCategory.type + '-' + category.category_name + '-' + task.task_id]"
                                class="ml-4">
                                <div class="entries-list">
                                  <VCard v-for="entry in task.entries" :key="entry.id" variant="outlined"
                                    class="mb-2 entry-card">
                                    <VCardText class="pa-3">
                                      <div class="d-flex justify-space-between align-start">
                                        <div class="entry-details flex-grow-1">
                                          <div class="d-flex align-center mb-2">
                                            <VIcon icon="ri-time-line" size="12" class="mr-2" />
                                            <span class="text-body-2 font-weight-medium">
                                              {{ formatDateTime(entry.start_time) }}
                                            </span>
                                            <VIcon icon="ri-arrow-right-line" size="10" class="mx-2" />
                                            <span class="text-body-2 font-weight-medium">
                                              {{ formatDateTime(entry.end_time) }}
                                            </span>
                                          </div>

                                          <div v-if="entry.comment" class="text-caption text-medium-emphasis">
                                            <VIcon icon="ri-chat-3-line" size="10" class="mr-1" />
                                            {{ entry.comment }}
                                          </div>
                                        </div>

                                        <div class="entry-duration text-right">
                                          <VChip color="accent" size="small" variant="tonal">
                                            {{ formatHours(entry.duration_hours) }}
                                          </VChip>
                                        </div>
                                      </div>
                                    </VCardText>
                                  </VCard>
                                </div>
                              </div>
                            </VExpandTransition>
                            </div>
                          </div>
                        </div>
                      </VExpandTransition>
                    </div>
                  </div>
                </VExpandTransition>
              </div>
            </div>
          </VCardText>
        </VCard>
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
  border-radius: 8px;
  background: white;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 5%);
  inline-size: 100%;
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

.day-info {
  min-block-size: 50px;
  padding-block: 8px;
  padding-inline: 4px;
}

.main-category-section {
  border-inline-start: 4px solid #e3f2fd;
  padding-inline-start: 16px;
}

.category-section {
  border-inline-start: 3px solid #e8f5e8;
  padding-inline-start: 12px;
}

.task-section {
  border-inline-start: 2px solid #f5f5f5;
  padding-inline-start: 8px;
}

.entry-card {
  background: #fafafa;
  border-inline-start: 3px solid transparent;
  transition: all 0.2s ease;
}

.entry-card:hover {
  background: #f0f0f0;
  border-inline-start-color: #2196f3;
  transform: translateX(4px);
}

.cursor-pointer {
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.cursor-pointer:hover {
  background: rgba(0, 0, 0, 2%);
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

/* Enhanced focus states for accessibility */
.cursor-pointer:focus-visible {
  border-radius: 4px;
  outline: 2px solid var(--v-theme-primary);
  outline-offset: 2px;
}

/* Chart bar hover effects */
.chart-bar-container:hover .bar-segment {
  filter: brightness(1.05);
}

.chart-bar-container:hover .day-info {
  color: var(--v-theme-primary);
}

/* Chart navigation */
.chart-wrapper[style*="cursor: grab"]:active {
  cursor: grabbing !important;
}

/* Table styling */
:deep(.v-data-table) {
  border-radius: 8px;
}

@media (max-width: 767px) {
  .enhanced-chart-container {
    padding: 16px;
  }

  .chart-grid {
    block-size: 280px !important;
  }

  .category-section,
  .task-section {
    padding-inline-start: 8px;
  }

  .entry-card {
    margin-block-end: 8px !important;
  }

  .ml-4 {
    margin-inline-start: 8px !important;
  }

  .legend-color {
    block-size: 12px !important;
    inline-size: 12px !important;
  }

  .text-body-2 {
    font-size: 0.75rem;
  }
}
</style>
