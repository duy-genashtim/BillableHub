<script setup>
import { formatHours } from '@/@core/utils/worklogHelpers';
import { computed } from 'vue';

const props = defineProps({
  dashboardData: {
    type: Object,
    required: true
  },
  showPerformance: {
    type: Boolean,
    default: false
  },
  performanceData: {
    type: Array,
    default: () => []
  },
  isMobile: {
    type: Boolean,
    default: false
  }
});

const basicMetrics = computed(() => props.dashboardData?.basic_metrics || {});
const nadData = computed(() => props.dashboardData?.nad_data || {});

function getProgressColor(percentage) {
  if (percentage >= 100) return 'success';
  if (percentage >= 90) return 'warning';
  return 'error';
}

function getPerformanceStatus(percentage) {
  if (percentage >= 100) return 'EXCELLENT';
  if (percentage >= 90) return 'WARNING';
  return 'POOR';
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
</script>

<template>
  <div>
    <!-- Basic Metrics Summary -->
    <VRow class="mb-6">
      <VCol cols="12" md="3">
        <VCard color="success" variant="tonal" class="h-100">
          <VCardText class="d-flex align-center">
            <VAvatar color="success" variant="flat" class="mr-4">
              <VIcon icon="ri-money-dollar-circle-line" size="24" />
            </VAvatar>

            <div class="flex-grow-1">
              <div class="text-h4 font-weight-bold mb-1">
                {{ formatHours(basicMetrics?.billable_hours || 0) }}
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
                {{ formatHours(basicMetrics?.non_billable_hours || 0) }}
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
                {{ formatHours((basicMetrics?.billable_hours || 0) + (basicMetrics?.non_billable_hours || 0)) }}
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
                {{ nadData?.nad_count || 0 }}
              </div>
              <div class="text-body-2 font-weight-medium">
                Total NAD
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Performance Overview for Weeks Only -->
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
                      {{ Math.ceil(value) }}%
                    </div>
                  </template>
                </VProgressLinear>

                <div class="d-flex justify-space-between text-body-2 mb-3">
                  <span>0h</span>
                  <span class="font-weight-medium">
                    {{ formatHours(basicMetrics.billable_hours) }} / {{
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
