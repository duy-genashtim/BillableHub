<script setup>
import { formatHours } from '@/@core/utils/worklogHelpers';
import { computed, ref } from 'vue';

const props = defineProps({
  users: {
    type: Array,
    required: true
  },
  showDetails: {
    type: Boolean,
    default: false
  },
  categories: {
    type: Array,
    default: () => []
  },
  dateMode: {
    type: String,
    default: 'weekly'
  }
});

const emit = defineEmits(['view-dashboard']);

// Reactive data
const sortBy = ref('performance');
const sortOrder = ref('desc');
const expandedRows = ref([]);

// Performance helper functions (matching server response format)
function getPerformanceColor(status) {
  switch (status) {
    case 'EXCEEDED':
      return 'success';
    case 'MEET':
      return 'warning';
    case 'BELOW':
    default:
      return 'error';
  }
}

function getPerformanceIcon(status) {
  switch (status) {
    case 'EXCEEDED':
      return 'ri-trophy-line';
    case 'MEET':
      return 'ri-check-line';
    case 'BELOW':
    default:
      return 'ri-close-circle-line';
  }
}

// All categories to show in table (not just top 5)
const allCategories = computed(() => {
  return props.categories || [];
});

// Sorted users
const sortedUsers = computed(() => {
  const users = [...props.users];

  return users.sort((a, b) => {
    let aValue, bValue;

    switch (sortBy.value) {
      case 'name':
        aValue = a.full_name.toLowerCase();
        bValue = b.full_name.toLowerCase();
        break;
      case 'billable':
        aValue = a.billable_hours || 0;
        bValue = b.billable_hours || 0;
        break;
      case 'non_billable':
        aValue = a.non_billable_hours || 0;
        bValue = b.non_billable_hours || 0;
        break;
      case 'total':
        aValue = a.total_hours || 0;
        bValue = b.total_hours || 0;
        break;
      case 'performance':
        aValue = a.performance?.percentage || 0;
        bValue = b.performance?.percentage || 0;
        break;
      case 'nad':
        aValue = a.nad_count || 0;
        bValue = b.nad_count || 0;
        break;
      default:
        aValue = a.performance?.percentage || 0;
        bValue = b.performance?.percentage || 0;
    }

    if (sortOrder.value === 'asc') {
      return aValue > bValue ? 1 : -1;
    } else {
      return aValue < bValue ? 1 : -1;
    }
  });
});

// Sort options (removed efficiency)
const sortOptions = [
  { title: 'Performance', value: 'performance', icon: 'ri-trophy-line' },
  { title: 'Name', value: 'name', icon: 'ri-user-line' },
  { title: 'Billable', value: 'billable', icon: 'ri-money-dollar-circle-line' },
  { title: 'Non-Billable', value: 'non_billable', icon: 'ri-time-line' },
  { title: 'Total', value: 'total', icon: 'ri-calculator-line' },
  { title: 'NAD', value: 'nad', icon: 'ri-calendar-check-line' }
];

function onSortChange(newSortBy) {
  if (sortBy.value === newSortBy) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortBy.value = newSortBy;
    sortOrder.value = newSortBy === 'name' ? 'asc' : 'desc';
  }
}

function toggleRowExpansion(userId) {
  const index = expandedRows.value.indexOf(userId);
  if (index > -1) {
    expandedRows.value.splice(index, 1);
  } else {
    expandedRows.value.push(userId);
  }
}

function isRowExpanded(userId) {
  return expandedRows.value.includes(userId);
}

function getUserCategoryHours(user, categoryId) {
  const category = user.categories?.find(c => c.category_id === categoryId);
  return category?.hours || 0;
}

function viewDashboard(userId) {
  emit('view-dashboard', userId);
}

function getWorkStatusBadgeColor(status) {
  return status === 'full-time' ? 'primary' : 'secondary';
}

// Calculate the number of columns for responsive colspan
const totalColumns = computed(() => {
  let baseColumns = 7; // Employee, Performance, Billable, Non-Billable, Total, NAD, Actions
  if (props.showDetails) {
    baseColumns += 2; // Contact, Job Title
  }
  baseColumns += allCategories.value.length; // All category columns
  return baseColumns;
});
</script>

<template>
  <div>
    <!-- Sort Controls -->
    <div class="d-flex align-center justify-space-between mb-4">
      <h3 class="text-subtitle-1 font-weight-medium">
        Performance Data ({{ users.length }} users)
      </h3>

      <div class="d-flex align-center gap-2">
        <span class="text-body-2">Sort by:</span>
        <VBtnToggle v-model="sortBy" mandatory density="comfortable" variant="outlined" divided>
          <VTooltip v-for="option in sortOptions" :key="option.value"
            :text="`${option.title} (${sortOrder === 'asc' ? 'Ascending' : 'Descending'})`">
            <template #activator="{ props }">
              <VBtn v-bind="props" :value="option.value" size="default" @click="onSortChange(option.value)" class="px-3"
                :aria-label="`${option.title} sort ${sortOrder === 'asc' ? 'ascending' : 'descending'}`">
                <VIcon :icon="option.icon" size="small" class="" />
                <VIcon v-if="sortBy === option.value"
                  :icon="sortOrder === 'asc' ? 'ri-arrow-up-line' : 'ri-arrow-down-line'" size="x-small" />
              </VBtn>
            </template>
          </VTooltip>
        </VBtnToggle>
      </div>
    </div>

    <!-- Categories Info Banner -->
    <VAlert v-if="allCategories.length > 0" type="info" variant="tonal" class="mb-4">
      <VIcon icon="ri-information-line" class="mr-2" />
      Showing all {{ allCategories.length }} billable categories. Table scrolls horizontally on smaller screens.
    </VAlert>

    <!-- Responsive Table Container -->
    <div class="table-container">
      <VTable>
        <thead>
          <tr>
            <th class="text-start sticky-col employee-col">Employee</th>
            <th v-if="showDetails" class="text-start sticky-col contact-col">Contact</th>
            <th v-if="showDetails" class="text-start sticky-col job-title-col">Job Title</th>
            <th class="text-center">Performance</th>
            <th class="text-center">Billable</th>
            <th class="text-center">Non-Billable</th>
            <th class="text-center">Total</th>
            <th class="text-center">NAD</th>

            <!-- All Category Columns -->
            <th v-for="category in allCategories" :key="category.category_id" class="text-center category-col">
              <VTooltip
                :text="`${category.category_name} (${formatHours(category.total_hours)} total by ${category.user_count} users)`">
                <template #activator="{ props }">
                  <div v-bind="props" class="category-header">
                    <div class="category-name">
                      {{ category.category_name
                      }}
                    </div>
                    <div class="category-stats">
                      {{ formatHours(category.total_hours) }}
                    </div>
                  </div>
                </template>
              </VTooltip>
            </th>

            <th class="text-center sticky-col-right actions-col">Actions</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="user in sortedUsers" :key="user.id">
            <tr :class="{ 'bg-grey-lighten-5': !user.total_hours }">
              <td class="sticky-col employee-col">
                <div class="d-flex align-center gap-2">
                  <VAvatar :color="getPerformanceColor(user.performance?.status || 'BELOW')" size="32" variant="tonal">
                    <VIcon :icon="getPerformanceIcon(user.performance?.status || 'BELOW')" size="small" />
                  </VAvatar>
                  <div>
                    <div class="font-weight-medium">{{ user.full_name }}</div>
                    <VChip :color="getWorkStatusBadgeColor(user.work_status)" size="x-small" variant="tonal">
                      {{ user.work_status }}
                    </VChip>
                  </div>
                </div>
              </td>

              <td v-if="showDetails" class="sticky-col contact-col">
                <div>
                  <div class="text-body-2">{{ user.email }}</div>
                  <div class="text-caption text-disabled">ID: {{ user.id }}</div>
                </div>
              </td>

              <td v-if="showDetails" class="text-body-2 sticky-col job-title-col">
                {{ user.job_title || '-' }}
              </td>

              <td class="text-center">
                <div v-if="user.performance">
                  <VChip :color="getPerformanceColor(user.performance.status)" size="small" variant="flat"
                    text-color="white">
                    {{ user.performance.percentage.toFixed(1) }}%
                  </VChip>
                  <div class="text-caption text-disabled mt-1">
                    {{ user.performance.actual_vs_target > 0 ? '+' : '' }}{{
                      formatHours(user.performance.actual_vs_target) }}
                  </div>
                </div>
                <span v-else class="text-disabled">-</span>
              </td>

              <td class="text-center">
                <span :class="{ 'font-weight-medium text-success': user.billable_hours > 0 }">
                  {{ formatHours(user.billable_hours) }}
                </span>
              </td>

              <td class="text-center">
                <span :class="{ 'font-weight-medium': user.non_billable_hours > 0 }">
                  {{ formatHours(user.non_billable_hours) }}
                </span>
              </td>

              <td class="text-center">
                <div>
                  <span class="font-weight-medium">
                    {{ formatHours(user.total_hours) }}
                  </span>
                  <div class="text-caption text-disabled">
                    / {{ formatHours(user.target_hours) }}
                  </div>
                </div>
              </td>

              <td class="text-center">
                <div v-if="user.nad_count > 0">
                  <div class="font-weight-medium">{{ user.nad_count }}</div>
                  <div class="text-caption text-disabled">{{ formatHours(user.nad_hours) }}</div>
                </div>
                <span v-else class="text-disabled">-</span>
              </td>

              <!-- All Category Columns -->
              <td v-for="category in allCategories" :key="category.category_id" class="text-center category-col">
                <div v-if="getUserCategoryHours(user, category.category_id) > 0" class="category-hours">
                  <div class="font-weight-medium">
                    {{ formatHours(getUserCategoryHours(user, category.category_id)) }}
                  </div>
                </div>
                <span v-else class="text-disabled">-</span>
              </td>

              <td class="text-center sticky-col-right actions-col">
                <VBtn icon size="small" variant="text" @click="toggleRowExpansion(user.id)"
                  :aria-label="isRowExpanded(user.id) ? 'Collapse details' : 'Expand details'">
                  <VIcon :icon="isRowExpanded(user.id) ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'" />
                </VBtn>

                <VTooltip text="View Dashboard" location="top">
                  <template #activator="{ props }">
                    <VBtn v-bind="props" icon size="small" variant="text" color="primary"
                      @click="viewDashboard(user.id)" aria-label="View user dashboard">
                      <VIcon icon="ri-dashboard-line" />
                    </VBtn>
                  </template>
                </VTooltip>
              </td>
            </tr>

            <!-- Expanded Row Details -->
            <tr v-if="isRowExpanded(user.id)">
              <td :colspan="totalColumns" class="pa-0">
                <VCard variant="elevated" class="ma-2">
                  <VCardText>
                    <div class="pa-2">
                      <!-- Performance Details -->
                      <div class="mb-4">
                        <h4 class="text-subtitle-2 font-weight-medium mb-2 d-flex align-center">
                          <VIcon icon="ri-trophy-line" class="mr-2" />
                          Performance Details
                        </h4>
                        <VRow>
                          <VCol cols="12" md="4">
                            <VList density="compact">
                              <VListItem>
                                <VListItemTitle class="text-caption">Target Hours</VListItemTitle>
                                <template #append>
                                  <span class="font-weight-medium">{{ formatHours(user.target_hours) }}</span>
                                </template>
                              </VListItem>
                              <VListItem>
                                <VListItemTitle class="text-caption">Actual Hours</VListItemTitle>
                                <template #append>
                                  <span class="font-weight-medium">{{ formatHours(user.performance?.actual_hours || 0)
                                  }}</span>
                                </template>
                              </VListItem>
                              <VListItem>
                                <VListItemTitle class="text-caption">Variance</VListItemTitle>
                                <template #append>
                                  <span :class="user.performance?.actual_vs_target >= 0 ? 'text-success' : 'text-error'"
                                    class="font-weight-medium">
                                    {{ user.performance?.actual_vs_target > 0 ? '+' : '' }}{{
                                      formatHours(user.performance?.actual_vs_target || 0) }}
                                  </span>
                                </template>
                              </VListItem>
                            </VList>
                          </VCol>
                          <VCol cols="12" md="4">
                            <div class="text-center">
                              <div class="text-h6 font-weight-bold mb-2">Work Schedule</div>
                              <VChip :color="getWorkStatusBadgeColor(user.work_status)" variant="flat"
                                text-color="white">
                                {{ user.work_status }}
                              </VChip>
                              <div class="text-caption mt-2">
                                {{ user.performance?.target_hours_per_week || 0 }}h/week target
                              </div>
                            </div>
                          </VCol>
                          <VCol cols="12" md="4">
                            <div class="text-center">
                              <div class="text-h6 font-weight-bold mb-2">Billable Hours</div>
                              <div class="text-h5 font-weight-bold text-success">
                                {{ formatHours(user.billable_hours) }}
                              </div>
                              <div class="text-caption mt-1">
                                of {{ formatHours(user.total_hours) }} total
                              </div>
                            </div>
                          </VCol>
                        </VRow>
                      </div>

                      <!-- Weekly/Monthly Breakdown -->
                      <div v-if="dateMode === 'weekly' && user.weekly_breakdown" class="mb-4">
                        <h4 class="text-subtitle-2 font-weight-medium mb-2 d-flex align-center">
                          <VIcon icon="ri-calendar-line" class="mr-2" />
                          Weekly Breakdown
                        </h4>
                        <VTable density="compact">
                          <thead>
                            <tr>
                              <th>Week</th>
                              <th class="text-center">Billable</th>
                              <th class="text-center">Non-Billable</th>
                              <th class="text-center">Total</th>
                              <th class="text-center">Performance</th>
                            </tr>
                          </thead>
        <tbody>
          <tr v-for="week in user.weekly_breakdown" :key="week.week_number">
            <td class="text-caption">Week {{ week.week_number }}</td>
            <td class="text-center text-caption">{{ formatHours(week.billable_hours) }}</td>
            <td class="text-center text-caption">{{ formatHours(week.non_billable_hours) }}</td>
            <td class="text-center text-caption">{{ formatHours(week.total_hours) }}</td>
            <td class="text-center">
              <VChip v-if="week.performance" :color="getPerformanceColor(week.performance.status)" size="x-small">
                {{ week.performance.percentage.toFixed(1) }}%
              </VChip>
            </td>
          </tr>
        </tbody>
      </VTable>
    </div>

    <div v-else-if="dateMode === 'monthly' && user.monthly_breakdown" class="mb-4">
      <h4 class="text-subtitle-2 font-weight-medium mb-2 d-flex align-center">
        <VIcon icon="ri-calendar-event-line" class="mr-2" />
        Monthly Breakdown
      </h4>
      <VTable density="compact">
        <thead>
          <tr>
            <th>Month</th>
            <th class="text-center">Billable</th>
            <th class="text-center">Non-Billable</th>
            <th class="text-center">Total</th>
            <th class="text-center">Performance</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="month in user.monthly_breakdown" :key="month.month_number">
            <td class="text-caption">{{ month.label }}</td>
            <td class="text-center text-caption">{{ formatHours(month.billable_hours) }}</td>
            <td class="text-center text-caption">{{ formatHours(month.non_billable_hours) }}</td>
            <td class="text-center text-caption">{{ formatHours(month.total_hours) }}</td>
            <td class="text-center">
              <VChip v-if="month.performance" :color="getPerformanceColor(month.performance.status)" size="x-small">
                {{ month.performance.percentage.toFixed(1) }}%
              </VChip>
            </td>
          </tr>
        </tbody>
      </VTable>
    </div>

    <!-- All Categories Detailed View -->
    <div v-if="user.categories && user.categories.length > 0">
      <h4 class="text-subtitle-2 font-weight-medium mb-2 d-flex align-center">
        <VIcon icon="ri-folder-chart-line" class="mr-2" />
        All Categories Breakdown
      </h4>
      <VRow>
        <VCol v-for="category in user.categories" :key="category.category_id" cols="12" sm="6" md="4">
          <VCard variant="tonal" :color="category.hours > 0 ? 'primary' : 'default'">
            <VCardText class="pa-3">
              <div class="text-caption font-weight-medium mb-1">
                {{ category.category_name }}
              </div>
              <div class="text-h6 font-weight-bold mb-1">
                {{ formatHours(category.hours) }}
              </div>
              <div class="text-caption text-disabled">
                {{ category.hours > 0 && user.billable_hours > 0
                  ? ((category.hours / user.billable_hours) * 100).toFixed(1) + '% of billable'
                  : category.hours > 0 ? 'Billable work' : 'No hours logged'
                }}
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>
  </div>
  </VCardText>
  </VCard>
  </td>
  </tr>
</template>
</tbody>
</VTable>
</div>
</div>
</template>

<style scoped>
/* Responsive table container */
.table-container {
  overflow: auto visible;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 4px;
  inline-size: 100%;
}

/* Table styling */
:deep(.v-table) {
  border-radius: 0;
  min-inline-size: max-content;
}

:deep(.v-table tbody tr) {
  transition: background-color 0.2s;
}

:deep(.v-table tbody tr:hover) {
  background-color: rgba(var(--v-theme-on-surface), 0.04);
}

/* Sticky columns */
.sticky-col {
  position: sticky;
  z-index: 2;
  background: rgb(var(--v-theme-surface));
}

.sticky-col-right {
  position: sticky;
  z-index: 2;
  background: rgb(var(--v-theme-surface));
  inset-inline-end: 0;
}

.employee-col {
  inset-inline-start: 0;
  max-inline-size: 200px;
  min-inline-size: 200px;
}

.contact-col {
  inset-inline-start: 200px;
  max-inline-size: 180px;
  min-inline-size: 180px;
}

.job-title-col {
  inset-inline-start: 380px;
  max-inline-size: 200px;
  min-inline-size: 200px;
}

.actions-col {
  max-inline-size: 100px;
  min-inline-size: 100px;
}

/* Category columns */
.category-col {
  max-inline-size: 120px;
  min-inline-size: 120px;
  padding-block: 8px !important;
  padding-inline: 4px !important;
}

.category-header {
  text-align: center;
}

.category-name {
  font-size: 0.75rem;
  font-weight: 500;
  line-height: 1.2;
  margin-block-end: 2px;
}

.category-stats {
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 0.65rem;
  font-weight: 400;
}

.category-hours {
  text-align: center;
}

/* Avatar styling */
.v-avatar {
  font-weight: 600;
}

/* Enhanced focus states for accessibility */
:deep(.v-btn:focus-visible) {
  outline: 2px solid var(--v-theme-primary);
  outline-offset: 2px;
}

/* Disabled row styling */
.bg-grey-lighten-5 {
  opacity: 0.7;
}

/* Mobile responsiveness */
@media (max-width: 767px) {
  .employee-col {
    max-inline-size: 160px;
    min-inline-size: 160px;
  }

  .contact-col {
    inset-inline-start: 160px;
    max-inline-size: 140px;
    min-inline-size: 140px;
  }

  .job-title-col {
    inset-inline-start: 300px;
    max-inline-size: 160px;
    min-inline-size: 160px;
  }

  .category-col {
    max-inline-size: 100px;
    min-inline-size: 100px;
  }

  .category-name {
    font-size: 0.7rem;
  }

  .category-stats {
    font-size: 0.6rem;
  }

  :deep(.v-table) {
    font-size: 0.875rem;
  }

  :deep(.v-table th),
  :deep(.v-table td) {
    padding-block: 6px;
    padding-inline: 2px;
  }

  :deep(.v-btn-toggle .v-btn) {
    min-inline-size: auto;
    padding-block: 4px;
    padding-inline: 8px;
  }
}

@media (max-width: 480px) {
  .employee-col {
    max-inline-size: 140px;
    min-inline-size: 140px;
  }

  .contact-col {
    inset-inline-start: 140px;
    max-inline-size: 120px;
    min-inline-size: 120px;
  }

  .job-title-col {
    inset-inline-start: 260px;
    max-inline-size: 140px;
    min-inline-size: 140px;
  }

  .category-col {
    max-inline-size: 80px;
    min-inline-size: 80px;
  }
}

/* Table scroll indicator */
.table-container::after {
  position: absolute;
  background: linear-gradient(to left, rgba(var(--v-theme-surface), 0.8), transparent);
  content: "";
  inline-size: 20px;
  inset-block: 0;
  inset-inline-end: 0;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.3s ease;
}

.table-container:hover::after {
  opacity: 1;
}

/* Better button group styling */
:deep(.v-btn-group) {
  box-shadow: none;
}

/* Category card in expanded view */
:deep(.v-card--variant-tonal) {
  transition: all 0.2s ease;
}

:deep(.v-card--variant-tonal:hover) {
  box-shadow: 0 4px 8px rgba(0, 0, 0, 10%);
  transform: translateY(-1px);
}
</style>
