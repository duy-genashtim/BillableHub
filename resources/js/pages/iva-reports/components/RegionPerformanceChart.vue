<script setup>
import { computed } from 'vue';

const props = defineProps({
  fullTimeSummary: {
    type: Object,
    required: true
  },
  partTimeSummary: {
    type: Object,
    required: true
  },
  overallSummary: {
    type: Object,
    required: true
  },
  isMobile: {
    type: Boolean,
    default: false
  }
});

// Performance data for charts
const performanceData = computed(() => {
  return [
    {
      label: 'Full-Time',
      exceeded: props.fullTimeSummary?.performance_breakdown?.exceeded || 0,
      meet: props.fullTimeSummary?.performance_breakdown?.meet || 0,
      below: props.fullTimeSummary?.performance_breakdown?.below || 0,
      total: props.fullTimeSummary?.total_users || 0,
      percentage: props.fullTimeSummary?.avg_performance || 0
    },
    {
      label: 'Part-Time',
      exceeded: props.partTimeSummary?.performance_breakdown?.exceeded || 0,
      meet: props.partTimeSummary?.performance_breakdown?.meet || 0,
      below: props.partTimeSummary?.performance_breakdown?.below || 0,
      total: props.partTimeSummary?.total_users || 0,
      percentage: props.partTimeSummary?.avg_performance || 0
    },
    {
      label: 'Overall',
      exceeded: props.overallSummary?.performance_breakdown?.exceeded || 0,
      meet: props.overallSummary?.performance_breakdown?.meet || 0,
      below: props.overallSummary?.performance_breakdown?.below || 0,
      total: props.overallSummary?.total_users || 0,
      percentage: props.overallSummary?.avg_performance || 0
    }
  ];
});

// Hours breakdown data
const hoursBreakdown = computed(() => {
  const fullTimeBillable = props.fullTimeSummary?.total_billable_hours || 0;
  const fullTimeNonBillable = props.fullTimeSummary?.total_non_billable_hours || 0;
  const fullTimeTotal = props.fullTimeSummary?.total_hours || 0;

  const partTimeBillable = props.partTimeSummary?.total_billable_hours || 0;
  const partTimeNonBillable = props.partTimeSummary?.total_non_billable_hours || 0;
  const partTimeTotal = props.partTimeSummary?.total_hours || 0;

  const overallBillable = props.overallSummary?.total_billable_hours || 0;
  const overallNonBillable = props.overallSummary?.total_non_billable_hours || 0;
  const overallTotal = props.overallSummary?.total_hours || 0;

  return {
    fullTime: {
      billable: fullTimeBillable,
      nonBillable: fullTimeNonBillable,
      total: fullTimeTotal,
      billablePercentage: fullTimeTotal > 0 ? (fullTimeBillable / fullTimeTotal) * 100 : 0,
      avgHoursPerUser: (props.fullTimeSummary?.total_users || 0) > 0 ? fullTimeTotal / props.fullTimeSummary.total_users : 0
    },
    partTime: {
      billable: partTimeBillable,
      nonBillable: partTimeNonBillable,
      total: partTimeTotal,
      billablePercentage: partTimeTotal > 0 ? (partTimeBillable / partTimeTotal) * 100 : 0,
      avgHoursPerUser: (props.partTimeSummary?.total_users || 0) > 0 ? partTimeTotal / props.partTimeSummary.total_users : 0
    },
    overall: {
      billable: overallBillable,
      nonBillable: overallNonBillable,
      total: overallTotal,
      billablePercentage: overallTotal > 0 ? (overallBillable / overallTotal) * 100 : 0,
      avgHoursPerUser: (props.overallSummary?.total_users || 0) > 0 ? overallTotal / props.overallSummary.total_users : 0
    }
  };
});

// Chart gradient for donut charts
function getChartGradient(data) {
  const { exceeded, meet, below, total } = data;

  if (total === 0) {
    return 'conic-gradient(from 0deg, #e0e0e0 0deg 360deg)';
  }

  const exceededAngle = (exceeded / total) * 360;
  const meetStartAngle = exceededAngle;
  const meetEndAngle = exceededAngle + (meet / total) * 360;
  const belowStartAngle = meetEndAngle;

  return `conic-gradient(from 0deg,
    rgb(var(--v-theme-success)) 0deg ${exceededAngle}deg,
    rgb(var(--v-theme-warning)) ${meetStartAngle}deg ${meetEndAngle}deg,
    rgb(var(--v-theme-error)) ${belowStartAngle}deg 360deg
  )`;
}

function formatHours(hours) {
  return Math.round(hours * 100) / 100;
}
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>
          <VIcon icon="ri-pie-chart-line" color="primary" class="mr-2" />
          Performance Distribution
        </span>
      </VCardTitle>
    </VCardItem>

    <VCardText>
      <VRow>
        <VCol v-for="data in performanceData" :key="data.label" cols="12" md="4">
          <div class="text-center">
            <h3 class="text-h6 font-weight-medium mb-4">{{ data.label }}</h3>

            <!-- Donut Chart -->
            <div class="donut-chart-container mx-auto mb-4">
              <div class="donut-chart" :style="{ background: getChartGradient(data) }">
                <div class="donut-center">
                  <div class="donut-value">{{ data.percentage }}%</div>
                  <div class="donut-label">Average</div>
                </div>
              </div>
            </div>

            <!-- Stats -->
            <div class="performance-stats">
              <div class="d-flex align-center justify-space-between mb-2">
                <div class="d-flex align-center">
                  <VIcon icon="ri-circle-fill" color="success" size="10" class="mr-2" />
                  <span class="text-body-2">Exceeded Target</span>
                </div>
                <span class="text-body-2 font-weight-medium">
                  {{ data.exceeded }} ({{ data.total > 0 ? Math.round((data.exceeded / data.total) * 100) : 0 }}%)
                </span>
              </div>

              <div class="d-flex align-center justify-space-between mb-2">
                <div class="d-flex align-center">
                  <VIcon icon="ri-circle-fill" color="warning" size="10" class="mr-2" />
                  <span class="text-body-2">Met Target</span>
                </div>
                <span class="text-body-2 font-weight-medium">
                  {{ data.meet }} ({{ data.total > 0 ? Math.round((data.meet / data.total) * 100) : 0 }}%)
                </span>
              </div>

              <div class="d-flex align-center justify-space-between">
                <div class="d-flex align-center">
                  <VIcon icon="ri-circle-fill" color="error" size="10" class="mr-2" />
                  <span class="text-body-2">Below Target</span>
                </div>
                <span class="text-body-2 font-weight-medium">
                  {{ data.below }} ({{ data.total > 0 ? Math.round((data.below / data.total) * 100) : 0 }}%)
                </span>
              </div>

              <VDivider class="my-3" />

              <div class="d-flex align-center justify-space-between">
                <span class="text-body-2 font-weight-medium">Total Users</span>
                <VChip color="primary" size="small" variant="flat">
                  {{ data.total }}
                </VChip>
              </div>
            </div>
          </div>
        </VCol>
      </VRow>

      <!-- Hours Analysis Section -->
      <VDivider class="my-6" />

      <h3 class="text-h6 font-weight-medium mb-4">
        <VIcon icon="ri-time-line" class="mr-2" />
        Hours Analysis
      </h3>

      <VRow>
        <!-- Full-Time Hours -->
        <VCol cols="12" md="4">
          <VCard variant="outlined" class="h-100">
            <VCardText class="pa-4">
              <div class="text-center mb-3">
                <VIcon icon="ri-user-fill" color="primary" size="32" class="mb-2" />
                <h4 class="text-subtitle-1 font-weight-bold">Full-Time</h4>
                <div class="text-caption text-disabled">{{ props.fullTimeSummary?.total_users || 0 }} employees</div>
              </div>

              <div class="hours-breakdown">
                <div class="d-flex justify-space-between align-center mb-2">
                  <span class="text-body-2">Billable Hours</span>
                  <span class="text-h6 font-weight-bold text-success">
                    {{ formatHours(hoursBreakdown.fullTime.billable) }}h
                  </span>
                </div>

                <div class="d-flex justify-space-between align-center mb-2">
                  <span class="text-body-2">Non-Billable Hours</span>
                  <span class="text-h6 font-weight-bold text-info">
                    {{ formatHours(hoursBreakdown.fullTime.nonBillable) }}h
                  </span>
                </div>

                <VDivider class="my-2" />

                <div class="d-flex justify-space-between align-center mb-3">
                  <span class="text-body-2 font-weight-medium">Total Hours</span>
                  <span class="text-h6 font-weight-bold">
                    {{ formatHours(hoursBreakdown.fullTime.total) }}h
                  </span>
                </div>

                <div class="text-center">
                  <VChip color="primary" size="small" variant="tonal">
                    {{ formatHours(hoursBreakdown.fullTime.billablePercentage) }}% Billable
                  </VChip>
                  <div class="text-caption text-disabled mt-1">
                    Avg: {{ formatHours(hoursBreakdown.fullTime.avgHoursPerUser) }}h per employee
                  </div>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Part-Time Hours -->
        <VCol cols="12" md="4">
          <VCard variant="outlined" class="h-100">
            <VCardText class="pa-4">
              <div class="text-center mb-3">
                <VIcon icon="ri-user-3-line" color="secondary" size="32" class="mb-2" />
                <h4 class="text-subtitle-1 font-weight-bold">Part-Time</h4>
                <div class="text-caption text-disabled">{{ props.partTimeSummary?.total_users || 0 }} employees</div>
              </div>

              <div class="hours-breakdown">
                <div class="d-flex justify-space-between align-center mb-2">
                  <span class="text-body-2">Billable Hours</span>
                  <span class="text-h6 font-weight-bold text-success">
                    {{ formatHours(hoursBreakdown.partTime.billable) }}h
                  </span>
                </div>

                <div class="d-flex justify-space-between align-center mb-2">
                  <span class="text-body-2">Non-Billable Hours</span>
                  <span class="text-h6 font-weight-bold text-info">
                    {{ formatHours(hoursBreakdown.partTime.nonBillable) }}h
                  </span>
                </div>

                <VDivider class="my-2" />

                <div class="d-flex justify-space-between align-center mb-3">
                  <span class="text-body-2 font-weight-medium">Total Hours</span>
                  <span class="text-h6 font-weight-bold">
                    {{ formatHours(hoursBreakdown.partTime.total) }}h
                  </span>
                </div>

                <div class="text-center">
                  <VChip color="primary" size="small" variant="tonal">
                    {{ formatHours(hoursBreakdown.partTime.billablePercentage) }}% Billable
                  </VChip>
                  <div class="text-caption text-disabled mt-1">
                    Avg: {{ formatHours(hoursBreakdown.partTime.avgHoursPerUser) }}h per employee
                  </div>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Overall Hours Summary -->
        <VCol cols="12" md="4">
          <VCard variant="outlined" class="h-100" color="primary" style="border-width: 2px;">
            <VCardText class="pa-4">
              <div class="text-center mb-3">
                <VIcon icon="ri-group-line" color="primary" size="32" class="mb-2" />
                <h4 class="text-subtitle-1 font-weight-bold">Overall Total</h4>
                <div class="text-caption text-disabled">{{ props.overallSummary?.total_users || 0 }} employees</div>
              </div>

              <div class="hours-breakdown">
                <div class="d-flex justify-space-between align-center mb-2">
                  <span class="text-body-2">Billable Hours</span>
                  <span class="text-h6 font-weight-bold text-success">
                    {{ formatHours(hoursBreakdown.overall.billable) }}h
                  </span>
                </div>

                <div class="d-flex justify-space-between align-center mb-2">
                  <span class="text-body-2">Non-Billable Hours</span>
                  <span class="text-h6 font-weight-bold text-info">
                    {{ formatHours(hoursBreakdown.overall.nonBillable) }}h
                  </span>
                </div>

                <VDivider class="my-2" />

                <div class="d-flex justify-space-between align-center mb-3">
                  <span class="text-body-2 font-weight-medium">Total Hours</span>
                  <span class="text-h6 font-weight-bold">
                    {{ formatHours(hoursBreakdown.overall.total) }}h
                  </span>
                </div>

                <div class="text-center">
                  <VChip color="primary" size="small" variant="flat">
                    {{ formatHours(hoursBreakdown.overall.billablePercentage) }}% Billable
                  </VChip>
                  <div class="text-caption text-disabled mt-1">
                    Avg: {{ formatHours(hoursBreakdown.overall.avgHoursPerUser) }}h per employee
                  </div>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Additional Summary Stats -->
      <VDivider class="my-6" />

      <VRow>
        <VCol cols="12" md="6">
          <div class="text-center pa-4">
            <div class="text-h6 font-weight-medium mb-2">Total Hours Summary</div>
            <div class="d-flex justify-space-around">
              <div class="text-center">
                <div class="text-h6 font-weight-bold text-success">
                  {{ props.fullTimeSummary?.total_hours || 0 }}h
                </div>
                <div class="text-caption">Full-Time Total</div>
              </div>
              <div class="text-center">
                <div class="text-h6 font-weight-bold text-warning">
                  {{ props.partTimeSummary?.total_hours || 0 }}h
                </div>
                <div class="text-caption">Part-Time Total</div>
              </div>
              <div class="text-center">
                <div class="text-h6 font-weight-bold text-primary">
                  {{ props.overallSummary?.total_hours || 0 }}h
                </div>
                <div class="text-caption">Overall Total</div>
              </div>
            </div>
          </div>
        </VCol>

        <VCol cols="12" md="6">
          <div class="text-center pa-4">
            <div class="text-h6 font-weight-medium mb-2">Target vs Actual</div>
            <div class="d-flex justify-space-around">
              <div class="text-center">
                <div class="text-h6 font-weight-bold text-info">
                  {{ props.fullTimeSummary?.total_target_hours || 0 }}h
                </div>
                <div class="text-caption">Full-Time Target</div>
              </div>
              <div class="text-center">
                <div class="text-h6 font-weight-bold text-info">
                  {{ props.partTimeSummary?.total_target_hours || 0 }}h
                </div>
                <div class="text-caption">Part-Time Target</div>
              </div>
              <div class="text-center">
                <div class="text-h6 font-weight-bold text-info">
                  {{ props.overallSummary?.total_target_hours || 0 }}h
                </div>
                <div class="text-caption">Overall Target</div>
              </div>
            </div>
          </div>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>

<style scoped>
.donut-chart-container {
  position: relative;
  block-size: 160px;
  inline-size: 160px;
}

.donut-chart {
  position: relative;
  border-radius: 50%;
  block-size: 100%;
  inline-size: 100%;
  transition: transform 0.3s ease;
}

.donut-chart:hover {
  transform: scale(1.05);
}

.donut-chart::before {
  position: absolute;
  border-radius: 50%;
  background: rgb(var(--v-theme-surface));
  block-size: 65%;
  box-shadow: 0 2px 8px rgba(var(--v-theme-on-surface), 0.1);
  content: "";
  inline-size: 65%;
  inset-block-start: 50%;
  inset-inline-start: 50%;
  transform: translate(-50%, -50%);
}

.donut-center {
  position: absolute;
  z-index: 1;
  inset-block-start: 50%;
  inset-inline-start: 50%;
  text-align: center;
  transform: translate(-50%, -50%);
}

.donut-value {
  color: rgb(var(--v-theme-on-surface));
  font-size: 22px;
  font-weight: 700;
  line-height: 1;
}

.donut-label {
  color: rgba(var(--v-theme-on-surface), 0.7);
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.5px;
  margin-block-start: 2px;
  text-transform: uppercase;
}

.performance-stats {
  margin-block: 0;
  margin-inline: auto;
  max-inline-size: 280px;
}

.hours-breakdown {
  min-block-size: 160px;
}

/* Mobile responsiveness */
@media (max-width: 767px) {
  .donut-chart-container {
    block-size: 140px;
    inline-size: 140px;
  }

  .donut-value {
    font-size: 18px;
  }

  .donut-label {
    font-size: 10px;
  }

  .text-h4 {
    font-size: 1.5rem !important;
  }

  .hours-breakdown {
    min-block-size: 140px;
  }
}

/* Card hover effects */
:deep(.v-card--variant-outlined) {
  transition: all 0.3s ease;
}

:deep(.v-card--variant-outlined:hover) {
  box-shadow: 0 4px 12px rgba(var(--v-theme-on-surface), 0.1);
  transform: translateY(-2px);
}
</style>
