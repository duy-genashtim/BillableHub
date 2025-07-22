<script setup>
import { computed, onMounted, ref } from 'vue'

const props = defineProps({
  trendsData: {
    type: Array,
    required: true
  },
  isMobile: {
    type: Boolean,
    default: false
  }
})

const chartOptions = computed(() => ({
  chart: {
    type: 'line',
    height: 350,
    toolbar: {
      show: false
    },
    sparkline: {
      enabled: false
    }
  },
  stroke: {
    width: [3, 3, 3],
    curve: 'smooth'
  },
  colors: ['#28a745', '#ffc107', '#17a2b8'],
  dataLabels: {
    enabled: false
  },
  legend: {
    position: 'top',
    horizontalAlign: 'left'
  },
  xaxis: {
    categories: props.trendsData.map(item => item.week_label),
    labels: {
      style: {
        fontSize: '12px'
      }
    }
  },
  yaxis: {
    title: {
      text: 'Hours'
    },
    labels: {
      formatter: function (value) {
        return value.toFixed(1) + 'h'
      }
    }
  },
  tooltip: {
    shared: true,
    intersect: false,
    y: {
      formatter: function (value) {
        return value.toFixed(2) + ' hours'
      }
    }
  },
  grid: {
    show: true,
    strokeDashArray: 3
  },
  markers: {
    size: 4,
    hover: {
      size: 6
    }
  }
}))

const chartSeries = computed(() => [
  {
    name: 'Billable Hours',
    data: props.trendsData.map(item => item.billable_hours)
  },
  {
    name: 'Non-Billable Hours',
    data: props.trendsData.map(item => item.non_billable_hours)
  },
  {
    name: 'Total Hours',
    data: props.trendsData.map(item => item.total_hours)
  }
])

// Calculate trend indicators
const currentWeekIndex = computed(() => props.trendsData.length - 1)
const previousWeekIndex = computed(() => Math.max(0, currentWeekIndex.value - 1))

const billableTrend = computed(() => {
  if (props.trendsData.length < 2) return 0
  const current = props.trendsData[currentWeekIndex.value]?.billable_hours || 0
  const previous = props.trendsData[previousWeekIndex.value]?.billable_hours || 0
  
  if (previous === 0) return 0
  return ((current - previous) / previous) * 100
})

const nonBillableTrend = computed(() => {
  if (props.trendsData.length < 2) return 0
  const current = props.trendsData[currentWeekIndex.value]?.non_billable_hours || 0
  const previous = props.trendsData[previousWeekIndex.value]?.non_billable_hours || 0
  
  if (previous === 0) return 0
  return ((current - previous) / previous) * 100
})

const totalHoursTrend = computed(() => {
  if (props.trendsData.length < 2) return 0
  const current = props.trendsData[currentWeekIndex.value]?.total_hours || 0
  const previous = props.trendsData[previousWeekIndex.value]?.total_hours || 0
  
  if (previous === 0) return 0
  return ((current - previous) / previous) * 100
})

function getTrendColor(trend) {
  if (trend > 5) return 'success'
  if (trend < -5) return 'error'
  return 'warning'
}

function getTrendIcon(trend) {
  if (trend > 5) return 'ri-arrow-up-line'
  if (trend < -5) return 'ri-arrow-down-line'
  return 'ri-arrow-right-line'
}

function formatTrend(trend) {
  return `${trend > 0 ? '+' : ''}${trend.toFixed(1)}%`
}
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle class="d-flex align-center">
        <VIcon icon="ri-line-chart-line" color="primary" class="mr-2" />
        Performance Trends (4 Weeks)
      </VCardTitle>
    </VCardItem>

    <VCardText>
      <div v-if="trendsData.length === 0" class="text-center py-8">
        <VIcon size="48" color="secondary" icon="ri-line-chart-line" class="mb-4" />
        <h3 class="text-h6 font-weight-regular mb-2">No Trend Data</h3>
        <p class="text-secondary">No performance data available for trend analysis.</p>
      </div>

      <div v-else>
        <!-- Trend Indicators -->
        <VRow class="mb-4">
          <VCol cols="4">
            <VCard variant="tonal" color="success" class="text-center">
              <VCardText class="pa-3">
                <div class="d-flex align-center justify-center mb-2">
                  <VIcon 
                    :icon="getTrendIcon(billableTrend)" 
                    :color="getTrendColor(billableTrend)" 
                    class="mr-2" 
                  />
                  <VChip 
                    :color="getTrendColor(billableTrend)" 
                    size="small" 
                    variant="flat"
                  >
                    {{ formatTrend(billableTrend) }}
                  </VChip>
                </div>
                <div class="text-subtitle-2 font-weight-medium">Billable Trend</div>
                <div class="text-caption">Week over week</div>
              </VCardText>
            </VCard>
          </VCol>
          
          <VCol cols="4">
            <VCard variant="tonal" color="warning" class="text-center">
              <VCardText class="pa-3">
                <div class="d-flex align-center justify-center mb-2">
                  <VIcon 
                    :icon="getTrendIcon(nonBillableTrend)" 
                    :color="getTrendColor(nonBillableTrend)" 
                    class="mr-2" 
                  />
                  <VChip 
                    :color="getTrendColor(nonBillableTrend)" 
                    size="small" 
                    variant="flat"
                  >
                    {{ formatTrend(nonBillableTrend) }}
                  </VChip>
                </div>
                <div class="text-subtitle-2 font-weight-medium">Non-Billable Trend</div>
                <div class="text-caption">Week over week</div>
              </VCardText>
            </VCard>
          </VCol>

          <VCol cols="4">
            <VCard variant="tonal" color="info" class="text-center">
              <VCardText class="pa-3">
                <div class="d-flex align-center justify-center mb-2">
                  <VIcon 
                    :icon="getTrendIcon(totalHoursTrend)" 
                    :color="getTrendColor(totalHoursTrend)" 
                    class="mr-2" 
                  />
                  <VChip 
                    :color="getTrendColor(totalHoursTrend)" 
                    size="small" 
                    variant="flat"
                  >
                    {{ formatTrend(totalHoursTrend) }}
                  </VChip>
                </div>
                <div class="text-subtitle-2 font-weight-medium">Total Hours Trend</div>
                <div class="text-caption">Week over week</div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>

        <!-- Chart -->
        <div class="chart-container">
          <VueApexCharts
            type="line"
            :height="isMobile ? 280 : 350"
            :options="chartOptions"
            :series="chartSeries"
          />
        </div>

        <!-- Summary Stats -->
        <VRow class="mt-4">
          <VCol cols="3" class="text-center">
            <div class="text-h6 font-weight-bold text-success">
              {{ trendsData.reduce((sum, item) => sum + item.billable_hours, 0).toFixed(1) }}h
            </div>
            <div class="text-caption">4-Week Billable</div>
          </VCol>
          <VCol cols="3" class="text-center">
            <div class="text-h6 font-weight-bold text-warning">
              {{ trendsData.reduce((sum, item) => sum + item.non_billable_hours, 0).toFixed(1) }}h
            </div>
            <div class="text-caption">4-Week Non-Billable</div>
          </VCol>
          <VCol cols="3" class="text-center">
            <div class="text-h6 font-weight-bold text-info">
              {{ trendsData.reduce((sum, item) => sum + item.total_hours, 0).toFixed(1) }}h
            </div>
            <div class="text-caption">4-Week Total</div>
          </VCol>
          <VCol cols="3" class="text-center">
            <div class="text-h6 font-weight-bold text-primary">
              {{ trendsData.reduce((sum, item) => sum + item.total_entries, 0) }}
            </div>
            <div class="text-caption">Total Entries</div>
          </VCol>
        </VRow>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.chart-container {
  margin: 0 -8px;
}

:deep(.apexcharts-toolbar) {
  display: none !important;
}

:deep(.apexcharts-legend) {
  justify-content: flex-start !important;
}

@media (max-width: 767px) {
  .text-h6 {
    font-size: 1.125rem !important;
  }
  
  :deep(.v-card-text) {
    padding: 12px !important;
  }
}
</style>