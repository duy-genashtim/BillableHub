<script setup>
import { formatDateRange, getPerformanceColor, getPerformanceIcon } from '@/@core/utils/helpers';
import { formatHours } from '@/@core/utils/worklogHelpers';

import { computed } from 'vue';

const props = defineProps({
  weeklyBreakdown: {
    type: Array,
    required: true
  },
  isMobile: {
    type: Boolean,
    default: false
  }
});

const tableHeaders = computed(() => [
  { title: 'Week', key: 'week_info', sortable: false },
  { title: 'Date Range', key: 'date_range', sortable: false },
  { title: 'Billable Hours', key: 'billable_hours', sortable: true },
  { title: 'Non-Billable Hours', key: 'non_billable_hours', sortable: true },
  // { title: 'NAD Count', key: 'nad_count', sortable: true },
  { title: 'NAD Hours', key: 'nad_hours', sortable: true },
  { title: 'Performance', key: 'performance', sortable: false },
  { title: 'Entries', key: 'entries_count', sortable: true }
]);

const mobileHeaders = computed(() => [
  { title: 'Week', key: 'week_info', sortable: false },
  { title: 'Hours', key: 'hours_summary', sortable: false },
  { title: 'NAD', key: 'nad_summary', sortable: false },
  { title: 'Performance', key: 'performance', sortable: false }
]);

</script>

<template>
  <VCard>
    <VCardText>
      <h2 class="text-h6 font-weight-medium mb-4">Weekly Breakdown</h2>

      <!-- Desktop Table -->
      <VDataTable v-if="!isMobile" :headers="tableHeaders" :items="weeklyBreakdown" density="comfortable"
        class="elevation-1" :items-per-page="25">
        <template #[`item.week_info`]="{ item }">
          <div class="d-flex align-center">
            <VAvatar color="primary" variant="tonal" size="32" class="mr-3">
              <span class="text-caption font-weight-bold">W{{ item.week_number }}</span>
            </VAvatar>
            <div>
              <div class="text-subtitle-2 font-weight-medium">Week {{ item.week_number }}</div>
              <div class="text-caption text-medium-emphasis">{{ item.week_year || new
                Date(item.start_date).getFullYear() }}</div>
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

        <template #[`item.nad_count`]="{ item }">
          <div class="text-center">
            <VBadge :content="item.nad_count" color="warning" :model-value="item.nad_count > 0">
              <VIcon icon="ri-calendar-close-line" size="20" />
            </VBadge>
          </div>
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
        <VCard v-for="week in weeklyBreakdown" :key="`${week.week_year}-${week.week_number}`" variant="outlined"
          class="mb-4">
          <VCardText>
            <!-- Week Header -->
            <div class="d-flex align-center mb-3">
              <VAvatar color="primary" variant="tonal" size="40" class="mr-3">
                <span class="text-subtitle-2 font-weight-bold">W{{ week.week_number }}</span>
              </VAvatar>
              <div class="flex-grow-1">
                <h3 class="text-subtitle-1 font-weight-medium">Week {{ week.week_number }}, {{ week.week_year || new
                  Date(week.start_date).getFullYear() }}</h3>
                <p class="text-caption text-medium-emphasis mb-0">
                  {{ formatDateRange(week.start_date, week.end_date) }}
                </p>
              </div>
            </div>

            <!-- Hours Summary -->
            <div class="d-flex justify-space-between align-center mb-3">
              <div class="text-center flex-grow-1">
                <div class="text-caption text-medium-emphasis mb-1">Billable</div>
                <VChip size="small" color="success" variant="tonal">
                  {{ formatHours(week.billable_hours) }}
                </VChip>
              </div>
              <div class="text-center flex-grow-1">
                <div class="text-caption text-medium-emphasis mb-1">Non-Billable</div>
                <VChip size="small" color="info" variant="tonal">
                  {{ formatHours(week.non_billable_hours) }}
                </VChip>
              </div>
              <div class="text-center flex-grow-1">
                <div class="text-caption text-medium-emphasis mb-1">NAD</div>
                <VChip size="small" color="warning" variant="tonal">
                  {{ week.nad_count }} ({{ formatHours(week.nad_hours) }})
                </VChip>
              </div>
            </div>

            <!-- Performance -->
            <div v-if="week.performance && week.performance.length > 0" class="mb-3">
              <div class="text-caption text-medium-emphasis mb-2">Performance</div>
              <div class="d-flex flex-wrap gap-1">
                <VChip v-for="perf in week.performance" :key="perf.target_id" size="small"
                  :color="getPerformanceColor(perf.status)" :prepend-icon="getPerformanceIcon(perf.status)">
                  {{ perf.percentage }}%
                </VChip>
              </div>
            </div>

            <!-- Additional Info -->
            <div class="d-flex justify-space-between align-center text-caption text-medium-emphasis">
              <span>{{ week.entries_count }} entries</span>
              <span>Total: {{ formatHours(week.billable_hours + week.non_billable_hours) }}</span>
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
