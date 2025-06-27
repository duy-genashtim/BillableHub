<script setup>
import { formatHours } from '@/@core/utils/worklogHelpers';
import { computed } from 'vue';

const props = defineProps({
  monthlyBreakdown: {
    type: Array,
    required: true
  },
  isMobile: {
    type: Boolean,
    default: false
  }
});

const tableHeaders = computed(() => [
  { title: 'Month', key: 'month_info', sortable: false },
  { title: 'Date Range', key: 'date_range', sortable: false },
  { title: 'Billable Hours', key: 'billable_hours', sortable: true },
  { title: 'Non-Billable Hours', key: 'non_billable_hours', sortable: true },
  { title: 'NAD Hours', key: 'nad_hours', sortable: true },
  { title: 'Performance', key: 'performance', sortable: false },
  { title: 'Entries', key: 'entries_count', sortable: true }
]);

const mobileHeaders = computed(() => [
  { title: 'Month', key: 'month_info', sortable: false },
  { title: 'Hours', key: 'hours_summary', sortable: false },
  { title: 'NAD', key: 'nad_summary', sortable: false },
  { title: 'Performance', key: 'performance', sortable: false }
]);

function getPerformanceColor(status) {
  switch (status) {
    case 'EXCELLENT': return 'success';
    case 'WARNING': return 'warning';
    case 'POOR': return 'error';
    default: return 'grey';
  }
}

function getPerformanceIcon(status) {
  switch (status) {
    case 'EXCELLENT': return 'ri-checkbox-circle-line';
    case 'WARNING': return 'ri-error-warning-line';
    case 'POOR': return 'ri-close-circle-line';
    default: return 'ri-time-line';
  }
}

function formatDateRange(startDate, endDate) {
  const start = new Date(startDate);
  const end = new Date(endDate);

  const startStr = start.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    timeZone: 'UTC'
  });
  const endStr = end.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    timeZone: 'UTC'
  });

  if (start.getMonth() === end.getMonth()) {
    return `${start.toLocaleDateString('en-US', { month: 'long', year: 'numeric', timeZone: 'UTC' })}`;
  } else {
    return `${startStr} - ${endStr}`;
  }
}

function getMonthAbbreviation(monthNumber) {
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  return months[monthNumber - 1] || monthNumber.toString();
}
</script>

<template>
  <VCard>
    <VCardText>
      <h2 class="text-h6 font-weight-medium mb-4">Monthly Breakdown</h2>

      <!-- Desktop Table -->
      <VDataTable v-if="!isMobile" :headers="tableHeaders" :items="monthlyBreakdown" density="comfortable"
        class="elevation-1" :items-per-page="25">
        <template #[`item.month_info`]="{ item }">
          <div class="d-flex align-center">
            <VAvatar color="primary" variant="tonal" size="32" class="mr-3">
              <span class="text-caption font-weight-bold">{{ getMonthAbbreviation(item.month_number) }}</span>
            </VAvatar>
            <div>
              <div class="text-subtitle-2 font-weight-medium">{{ item.label }}</div>
              <div class="text-caption text-medium-emphasis">{{ item.weekly_breakdown?.length || 0 }} weeks</div>
            </div>
          </div>
        </template>

        <template #[`item.date_range`]="{ item }">
          <div class="text-body-2">
            {{ formatDateRange(item.start_date, item.end_date) }}
          </div>
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

        <template #[`item.nad_hours`]="{ item }">
          <VChip size="small" color="warning" variant="outlined">
            {{ formatHours(item.nad_hours) }}
          </VChip>
        </template>

        <template #[`item.performance`]="{ item }">
          <div v-if="item.performance && item.performance.length > 0">
            <VChip v-for="perf in item.performance" :key="perf.target_id" size="small"
              :color="getPerformanceColor(perf.status)" :prepend-icon="getPerformanceIcon(perf.status)"
              class="mb-1 mr-1">
              {{ perf.percentage }}%
            </VChip>
          </div>
          <div v-else class="text-caption text-medium-emphasis">
            No targets
          </div>
        </template>

        <template #[`item.entries_count`]="{ item }">
          <VChip size="small" variant="outlined">
            {{ item.entries_count }}
          </VChip>
        </template>
      </VDataTable>

      <!-- Mobile Cards -->
      <div v-else class="mobile-breakdown">
        <VCard v-for="month in monthlyBreakdown" :key="`${month.year}-${month.month_number}`" variant="outlined"
          class="mb-4">
          <VCardText>
            <!-- Month Header -->
            <div class="d-flex align-center mb-3">
              <VAvatar color="primary" variant="tonal" size="40" class="mr-3">
                <span class="text-subtitle-2 font-weight-bold">{{ getMonthAbbreviation(month.month_number) }}</span>
              </VAvatar>
              <div class="flex-grow-1">
                <h3 class="text-subtitle-1 font-weight-medium">{{ month.label }}</h3>
                <p class="text-caption text-medium-emphasis mb-0">
                  {{ formatDateRange(month.start_date, month.end_date) }}
                </p>
              </div>
            </div>

            <!-- Hours Summary -->
            <div class="d-flex justify-space-between align-center mb-3">
              <div class="text-center flex-grow-1">
                <div class="text-caption text-medium-emphasis mb-1">Billable</div>
                <VChip size="small" color="success" variant="tonal">
                  {{ formatHours(month.billable_hours) }}
                </VChip>
              </div>
              <div class="text-center flex-grow-1">
                <div class="text-caption text-medium-emphasis mb-1">Non-Billable</div>
                <VChip size="small" color="info" variant="tonal">
                  {{ formatHours(month.non_billable_hours) }}
                </VChip>
              </div>
              <div class="text-center flex-grow-1">
                <div class="text-caption text-medium-emphasis mb-1">NAD</div>
                <VChip size="small" color="warning" variant="tonal">
                  {{ month.nad_count }} ({{ formatHours(month.nad_hours) }})
                </VChip>
              </div>
            </div>

            <!-- Performance -->
            <div v-if="month.performance && month.performance.length > 0" class="mb-3">
              <div class="text-caption text-medium-emphasis mb-2">Performance</div>
              <div class="d-flex flex-wrap gap-1">
                <VChip v-for="perf in month.performance" :key="perf.target_id" size="small"
                  :color="getPerformanceColor(perf.status)" :prepend-icon="getPerformanceIcon(perf.status)">
                  {{ perf.percentage }}%
                </VChip>
              </div>
            </div>

            <!-- Additional Info -->
            <div class="d-flex justify-space-between align-center text-caption text-medium-emphasis">
              <span>{{ month.entries_count }} entries</span>
              <span>{{ month.weekly_breakdown?.length || 0 }} weeks</span>
              <span>Total: {{ formatHours(month.billable_hours + month.non_billable_hours) }}</span>
            </div>
          </VCardText>
        </VCard>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
/* Table styling */
:deep(.v-data-table) {
  border-radius: 8px;
}

.mobile-breakdown {
  display: flex;
  flex-direction: column;
}

@media (max-width: 767px) {
  :deep(.v-card-text) {
    padding: 16px;
  }

  .mobile-breakdown .v-card {
    margin-block-end: 12px;
  }
}
</style>
