<script setup>
import { getPerformanceColor } from '@/@core/utils/helpers';
import { computed } from 'vue';

const props = defineProps({
  excellentCount: {
    type: Number,
    default: 0
  },
  meetCount: {
    type: Number,
    default: 0
  },
  belowCount: {
    type: Number,
    default: 0
  },
  totalUsers: {
    type: Number,
    default: 0
  },
  loading: {
    type: Boolean,
    default: false
  }
});

// Performance stats computed with correct calculations
const performanceStats = computed(() => {
  const { excellentCount, meetCount, belowCount, totalUsers } = props;

  if (totalUsers === 0) {
    return [
      { label: 'Exceeded Target', count: 0, percentage: 0, color: getPerformanceColor('EXCEEDED'), angle: 0 },
      { label: 'Meet Target', count: 0, percentage: 0, color: getPerformanceColor('MEET'), angle: 0 },
      { label: 'Below Target', count: 0, percentage: 0, color: getPerformanceColor('BELOW'), angle: 0 }
    ];
  }

  const exceededPercentage = Math.round((excellentCount / totalUsers) * 100);
  const meetPercentage = Math.round((meetCount / totalUsers) * 100);
  const belowPercentage = Math.round((belowCount / totalUsers) * 100);

  // Calculate angles for the donut chart
  const exceededAngle = (excellentCount / totalUsers) * 360;
  const meetAngle = (meetCount / totalUsers) * 360;
  const belowAngle = (belowCount / totalUsers) * 360;

  return [
    {
      label: 'Exceeded Target',
      count: excellentCount,
      percentage: exceededPercentage,
      color: getPerformanceColor('EXCEEDED'),
      angle: exceededAngle
    },
    {
      label: 'Meet Target',
      count: meetCount,
      percentage: meetPercentage,
      color: getPerformanceColor('MEET'),
      angle: meetAngle
    },
    {
      label: 'Below Target',
      count: belowCount,
      percentage: belowPercentage,
      color: getPerformanceColor('BELOW'),
      angle: belowAngle
    }
  ];
});

// Top performance category for center display
const topPerformance = computed(() => {
  const stats = performanceStats.value;
  if (stats.every(s => s.count === 0)) {
    return { percentage: 0, label: 'No Data' };
  }

  // Find the category with highest percentage
  const topStat = stats.reduce((max, current) =>
    current.percentage > max.percentage ? current : max
  );

  return {
    percentage: topStat.percentage,
    label: topStat.label.replace(' Target', '')
  };
});

// Chart gradient angles for conic-gradient
const chartGradient = computed(() => {
  const { excellentCount, meetCount, belowCount, totalUsers } = props;

  if (totalUsers === 0) {
    return 'conic-gradient(from 0deg, #e0e0e0 0deg 360deg)';
  }

  const exceededAngle = (excellentCount / totalUsers) * 360;
  const meetStartAngle = exceededAngle;
  const meetEndAngle = exceededAngle + (meetCount / totalUsers) * 360;
  const belowStartAngle = meetEndAngle;

  return `conic-gradient(from 0deg,
    rgb(var(--v-theme-success)) 0deg ${exceededAngle}deg,
    rgb(var(--v-theme-warning)) ${meetStartAngle}deg ${meetEndAngle}deg,
    rgb(var(--v-theme-error)) ${belowStartAngle}deg 360deg
  )`;
});
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>Performance Distribution</span>
        <VIcon icon="ri-pie-chart-line" color="primary" size="small" />
      </VCardTitle>
    </VCardItem>

    <VCardText class="">
      <!-- Loading State -->
      <div v-if="loading" class="d-flex justify-center align-center" style="block-size: 200px;">
        <VProgressCircular indeterminate color="primary" :size="40" :width="4" />
      </div>

      <!-- Chart and Stats -->
      <div v-else class="d-flex gap-6 flex-md-row flex-column">
        <!-- CSS Donut Chart -->
        <div class="mx-auto">
          <div class="donut-chart-container">
            <div class="donut-chart" :style="{ background: chartGradient }">
              <div class="donut-center">
                <div class="donut-value">{{ topPerformance.percentage }}%</div>
                <div class="donut-label">{{ topPerformance.label }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Stats -->
        <div class="flex-grow-1">
          <div class="d-flex align-center mb-4">
            <VAvatar color="primary" variant="tonal" size="36" class="me-3">
              <VIcon icon="ri-group-line" />
            </VAvatar>
            <div>
              <p class="mb-0 text-body-2">Team Performance</p>
              <h6 class="text-h6">{{ totalUsers.toLocaleString() }} Users</h6>
            </div>
          </div>

          <VDivider class="my-4" />

          <!-- Performance Breakdown -->
          <div class="performance-stats">
            <VRow dense>
              <VCol v-for="stat in performanceStats" :key="stat.label" cols="12" class="py-1">
                <div class="d-flex align-center justify-space-between mb-2">
                  <div class="d-flex align-center">
                    <VIcon icon="ri-circle-fill" :color="stat.color" size="10" class="me-2" />
                    <span class="text-body-2">{{ stat.label }}</span>
                  </div>
                  <div class="text-end">
                    <span class="text-body-2 font-weight-medium">
                      {{ stat.count }} ({{ stat.percentage }}%)
                    </span>
                  </div>
                </div>

                <!-- Progress bar for visual representation -->
                <VProgressLinear :model-value="stat.percentage" :color="stat.color" height="4" rounded class="mb-1" />
              </VCol>
            </VRow>
          </div>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
/* Smooth transitions */
.donut-chart,
.v-progress-linear {
  transition: all 0.3s ease;
}

.donut-chart-container {
  position: relative;
  block-size: 180px;
  inline-size: 180px;
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
  min-inline-size: 220px;
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

  .performance-stats {
    margin-block-start: 1rem;
    min-inline-size: auto;
  }
}

/* Enhanced progress bars */
:deep(.v-progress-linear) {
  overflow: hidden;
  border-radius: 6px;
}

:deep(.v-progress-linear__background) {
  opacity: 0.1;
}
</style>
