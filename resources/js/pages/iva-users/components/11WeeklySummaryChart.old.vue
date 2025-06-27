<script setup>
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

const chartData = computed(() => {
  return props.weeklyBreakdown.map(week => ({
    week_number: week.week_number,
    week_year: week.week_year || new Date(week.start_date).getFullYear(),
    label: `W${week.week_number}`,
    full_label: week.label,
    date_range: formatDateRange(week.start_date, week.end_date),
    billable: week.billable_hours,
    nonBillable: week.non_billable_hours,
    nad: week.nad_hours,
    total: week.billable_hours + week.non_billable_hours,
    nad_count: week.nad_count,
    performance: week.performance?.[0] || null
  }));
});

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
    timeZone: 'UTC'
  });

  return `${startStr} - ${endStr}`;
}

const maxHours = computed(() => {
  if (!chartData.value.length) return 50;
  const maxTotal = Math.max(...chartData.value.map(d => d.total + d.nad));
  return Math.max(40, Math.ceil(maxTotal / 10) * 10);
});

const chartHeight = computed(() => props.isMobile ? 300 : 400);
const barWidth = computed(() => props.isMobile ? 60 : 80);
const chartPadding = computed(() => props.isMobile ? 60 : 80);

function getPerformanceColor(status) {
  switch (status) {
    case 'EXCELLENT': return 'success';
    case 'WARNING': return 'warning';
    case 'POOR': return 'error';
    default: return 'grey';
  }
}
</script>

<template>
  <VCard>
    <VCardText>
      <h2 class="text-h6 font-weight-medium mb-4">Weekly Hours Overview</h2>

      <div v-if="chartData.length > 0" class="enhanced-chart-container">
        <div class="chart-wrapper" :style="{
          height: `${chartHeight + 80}px`,
          overflowX: 'auto',
          overflowY: 'hidden'
        }">
          <div class="chart-grid" :style="{
            height: `${chartHeight}px`,
            width: `${Math.max(800, chartData.length * (barWidth + 20) + chartPadding * 2)}px`,
            position: 'relative',
            minWidth: '100%'
          }">
            <!-- Y-axis hour lines -->
            <div v-for="hour in Math.ceil(maxHours / 10)" :key="hour * 10" class="hour-line" :style="{
              position: 'absolute',
              top: `${((maxHours - (hour * 10)) / maxHours) * (chartHeight - 60) + 30}px`,
              left: `${chartPadding - 10}px`,
              right: '20px',
              height: '1px',
              backgroundColor: hour % 2 === 0 ? '#e0e0e0' : '#f5f5f5',
              zIndex: 1
            }">
              <span class="hour-label" :style="{
                position: 'absolute',
                left: `-${chartPadding - 15}px`,
                top: '-8px',
                fontSize: '11px',
                color: '#666',
                fontWeight: hour % 2 === 0 ? '600' : '400',
                whiteSpace: 'nowrap'
              }">
                {{ hour * 10 }}h
              </span>
            </div>

            <!-- Chart bars -->
            <div class="d-flex align-end chart-bars" :style="{
              height: `${chartHeight - 60}px`,
              marginTop: '30px',
              marginLeft: `${chartPadding}px`,
              marginRight: '20px',
              position: 'relative',
              zIndex: 2,
              gap: '20px'
            }">
              <div v-for="week in chartData" :key="`${week.week_year}-${week.week_number}`" class="chart-bar-container"
                :style="{
                  width: `${barWidth}px`,
                  position: 'relative',
                  flexShrink: 0
                }">
                <!-- Bar Stack -->
                <div class="bar-stack" :style="{
                  height: `${chartHeight - 60}px`,
                  display: 'flex',
                  flexDirection: 'column-reverse',
                  cursor: 'pointer'
                }"
                  :title="`${week.full_label}: ${formatHours(week.total)} work hours + ${formatHours(week.nad)} NAD hours (${week.nad_count} NADs)`">
                  <!-- Billable Hours Bar -->
                  <div v-if="week.billable > 0" class="bar-segment billable-bar" :style="{
                    height: `${(week.billable / maxHours) * (chartHeight - 60)}px`,
                    backgroundColor: '#4CAF50',
                    borderRadius: '0 0 6px 6px',
                    marginBottom: '1px',
                    transition: 'all 0.3s ease',
                    boxShadow: '0 2px 4px rgba(76, 175, 80, 0.3)'
                  }" />

                  <!-- Non-Billable Hours Bar -->
                  <div v-if="week.nonBillable > 0" class="bar-segment non-billable-bar" :style="{
                    height: `${(week.nonBillable / maxHours) * (chartHeight - 60)}px`,
                    backgroundColor: '#2196F3',
                    transition: 'all 0.3s ease',
                    boxShadow: '0 2px 4px rgba(33, 150, 243, 0.3)',
                    marginBottom: '1px'
                  }" />

                  <!-- NAD Hours Bar -->
                  <div v-if="week.nad > 0" class="bar-segment nad-bar" :style="{
                    height: `${(week.nad / maxHours) * (chartHeight - 60)}px`,
                    backgroundColor: '#FF9800',
                    borderRadius: '6px 6px 0 0',
                    transition: 'all 0.3s ease',
                    boxShadow: '0 2px 4px rgba(255, 152, 0, 0.3)'
                  }" />
                </div>

                <!-- Performance Indicator -->
                <div v-if="week.performance" class="performance-indicator" :style="{
                  position: 'absolute',
                  top: '-25px',
                  left: '50%',
                  transform: 'translateX(-50%)',
                  fontSize: '10px',
                  fontWeight: '600',
                  padding: '2px 6px',
                  borderRadius: '10px',
                  whiteSpace: 'nowrap'
                }" :class="`bg-${getPerformanceColor(week.performance.status)} text-white`">
                  {{ week.performance.percentage }}%
                </div>

                <!-- Week Label -->
                <div class="week-info text-center mt-3">
                  <div class="text-subtitle-2 font-weight-bold text-primary">
                    {{ week.label }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ week.week_year }}
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
      </div>

      <div v-else class="text-center py-8">
        <VIcon size="48" color="secondary" icon="ri-line-chart-line" class="mb-4" />
        <p class="text-secondary">No data available for the selected weeks</p>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.enhanced-chart-container {
  padding: 24px;
  border: 1px solid #e0e0e0;
  border-radius: 12px;
  background: linear-gradient(135deg, #fafafa 0%, #f5f5f5 100%);
  inline-size: 100%;
}

.chart-wrapper {
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

.week-info {
  min-block-size: 50px;
  padding-block: 8px;
  padding-inline: 4px;
}

.chart-bar-container:hover .bar-segment {
  filter: brightness(1.05);
}

.chart-bar-container:hover .week-info {
  color: var(--v-theme-primary);
}

.performance-indicator {
  font-size: 10px !important;
}

/* Scrollbar styling */
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
}
</style>
