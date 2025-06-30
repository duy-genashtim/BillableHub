<script setup>
import { formatHours } from '@/@core/utils/worklogHelpers';
import { computed } from 'vue';

const props = defineProps({
  summaryData: {
    type: Object,
    required: true
  },
  isMobile: {
    type: Boolean,
    default: false
  }
});

const summary = computed(() => props.summaryData?.summary || {});

function getProgressColor(percentage) {
  if (percentage >= 100) return 'success';
  if (percentage >= 90) return 'warning';
  return 'error';
}

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

// Calculate average performance across all weeks
const averagePerformance = computed(() => {
  const weeklyBreakdown = props.summaryData?.weekly_breakdown || [];
  if (!weeklyBreakdown.length) return null;

  let totalPercentage = 0;
  let performanceCount = 0;

  weeklyBreakdown.forEach(week => {
    if (week.performance && week.performance.length > 0) {
      week.performance.forEach(perf => {
        totalPercentage += perf.percentage;
        performanceCount++;
      });
    }
  });

  if (performanceCount === 0) return null;

  const avgPercentage = totalPercentage / performanceCount;
  let status = 'POOR';
  if (avgPercentage >= 100) {
    status = 'EXCELLENT';
  } else if (avgPercentage >= 90) {
    status = 'WARNING';
  }

  return {
    percentage: Math.round(avgPercentage),
    status: status
  };
});

// Show performance data (matching WorklogMetricsCards logic)
const showPerformance = computed(() => true);

// Get performance data from target_performances
const performanceData = computed(() => {
  return props.summaryData?.target_performances || [];
});
</script>

<template>
  <div>

    <!-- Basic Metrics Summary (matching WorklogMetricsCards layout) -->
    <VRow class="mb-6">
      <VCol cols="12" md="3">
        <VCard color="success" variant="tonal" class="h-100">
          <VCardText class="d-flex align-center">
            <VAvatar color="success" variant="flat" class="mr-4">
              <VIcon icon="ri-money-dollar-circle-line" size="24" />
            </VAvatar>

            <div class="flex-grow-1">
              <div class="text-h4 font-weight-bold mb-1">
                {{ formatHours(summary?.total_billable_hours || 0) }}
              </div>
              <div class="text-body-2 font-weight-medium">
                Billable Hours
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" md="3">
        <VCard color="info" variant="tonal" class="h-100">
          <VCardText class="d-flex align-center">
            <VAvatar color="info" variant="flat" class="mr-4">
              <VIcon icon="ri-time-line" size="24" />
            </VAvatar>

            <div class="flex-grow-1">
              <div class="text-h4 font-weight-bold mb-1">
                {{ formatHours(summary?.total_non_billable_hours || 0) }}
              </div>
              <div class="text-body-2 font-weight-medium">
                Non-Billable Hours
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" md="3">
        <VCard color="secondary" variant="tonal" class="h-100">
          <VCardText class="d-flex align-center">
            <VAvatar color="secondary" variant="flat" class="mr-4">
              <VIcon icon="ri-calculator-line" size="24" />
            </VAvatar>

            <div class="flex-grow-1">
              <div class="text-h4 font-weight-bold mb-1">
                {{ formatHours((summary?.total_billable_hours || 0) + (summary?.total_non_billable_hours || 0)) }}
              </div>
              <div class="text-body-2 font-weight-medium">
                Total Hours
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" md="3">
        <VCard color="warning" variant="tonal" class="h-100">
          <VCardText class="d-flex align-center">
            <VAvatar color="warning" variant="flat" class="mr-4">
              <VIcon icon="ri-calendar-close-line" size="24" />
            </VAvatar>

            <div class="flex-grow-1">
              <div class="text-h4 font-weight-bold mb-1">
                {{ summary?.total_nad_count || 0 }}
              </div>
              <div class="text-body-2 font-weight-medium">
                Total NAD
              </div>
              <div class="text-caption text-warning-darken-1">
                {{ formatHours(summary?.total_nad_hours || 0) }} NAD hours
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Performance Overview for Weeks (matching WorklogMetricsCards logic) -->
    <VCard v-if="showPerformance && performanceData?.length" class="mb-6">
      <VCardText>
        <h2 class="text-h6 font-weight-medium mb-4">Performance Overview</h2>

        <VRow>
          <VCol v-for="target in performanceData" :key="target.target_id" :cols="performanceData.length === 1 ? 12 : 6">
            <VCard variant="outlined" class="h-100">
              <VCardText>
                <div class="d-flex align-center justify-space-between mb-3">
                  <h3 class="text-subtitle-1 font-weight-medium">
                    Target Billable Hours
                    <VChip size="small" color="primary" variant="tonal" class="ml-2">
                      {{ target.work_status }}
                    </VChip>
                  </h3>
                  <VChip :color="getPerformanceColor(target.status)" :prepend-icon="getPerformanceIcon(target.status)"
                    size="small">
                    {{ target.status }}
                  </VChip>
                </div>

                <VProgressLinear :model-value="target.percentage" :color="getProgressColor(target.percentage)"
                  height="20" rounded class="mb-2">
                  <template v-slot:default="{ value }">
                    <div class="text-center text-white font-weight-medium">
                      <!-- {{ Math.ceil(value) }}% rounds a number up -->
                      {{ value.toFixed(2) }}%
                    </div>
                  </template>
                </VProgressLinear>

                <div class="d-flex justify-space-between text-body-2 mb-3">
                  <span>0h</span>
                  <span class="font-weight-medium">
                    {{ formatHours(target.actual_hours || summary.total_billable_hours || 0) }} / {{
                      formatHours(target.target_total_hours) }}
                  </span>
                  <span>{{ formatHours(target.target_total_hours) }}</span>
                </div>

                <VAlert :type="target.actual_vs_target >= 0 ? 'success' : 'warning'" variant="tonal" class="text-center"
                  density="compact">
                  <template v-if="target.actual_vs_target >= 0">
                    <strong>{{ formatHours(target.actual_vs_target) }} ahead of target!</strong>
                  </template>
                  <template v-else>
                    <strong>{{ formatHours(Math.abs(target.actual_vs_target)) }} behind target</strong>
                  </template>
                </VAlert>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- NAD Information Card -->
    <!-- <VCard v-if="summary?.nad_hour_rate" class="mb-6" color="orange-lighten-5" variant="tonal">
      <VCardText>
        <div class="d-flex align-center">
          <VIcon icon="ri-information-line" color="orange" class="mr-3" />
          <div>
            <h3 class="text-subtitle-1 font-weight-medium mb-1">NAD Calculation Information</h3>
            <p class="text-body-2 mb-0">
              NAD hours are calculated using a rate of <strong>{{ summary.nad_hour_rate }} hours per NAD count</strong>.
              Total NAD hours: {{ summary?.total_nad_count || 0 }} × {{ summary.nad_hour_rate }} = {{
                formatHours(summary?.total_nad_hours || 0) }}
            </p>
          </div>
        </div>
      </VCardText>
    </VCard> -->
  </div>
</template>

<style scoped>
/* Progress styling */
:deep(.v-progress-linear) {
  border-radius: 4px;
}

/* Ensure proper chip sizing */
:deep(.v-chip) {
  font-size: 0.75rem;
}

@media (max-width: 767px) {
  .text-h4 {
    font-size: 1.4rem !important;
  }

  :deep(.v-card-text) {
    padding: 16px;
  }
}
</style>
