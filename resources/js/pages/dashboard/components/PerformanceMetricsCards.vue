<script setup>
import { formatHours } from '@/@core/utils/worklogHelpers'
import { computed } from 'vue'

const props = defineProps({
  currentWeek: {
    type: Object,
    required: true
  },
  currentMonth: {
    type: Object,
    required: true
  },
  isMobile: {
    type: Boolean,
    default: false
  }
})

const weeklyMetrics = computed(() => [
  {
    title: 'Billable Hours',
    value: formatHours(props.currentWeek.billable_hours || 0),
    icon: 'ri-money-dollar-circle-line',
    color: 'success',
    subtitle: 'This week'
  },
  {
    title: 'Non-Billable Hours', 
    value: formatHours(props.currentWeek.non_billable_hours || 0),
    icon: 'ri-time-line',
    color: 'warning',
    subtitle: 'This week'
  },
  {
    title: 'Total Hours',
    value: formatHours(props.currentWeek.total_hours || 0),
    icon: 'ri-calculator-line',
    color: 'info',
    subtitle: 'This week'
  },
  {
    title: 'NAD Count',
    value: props.currentWeek.nad_count || 0,
    icon: 'ri-calendar-close-line',
    color: 'error',
    subtitle: 'This week'
  }
])

const monthlyMetrics = computed(() => [
  {
    title: 'Billable Hours',
    value: formatHours(props.currentMonth.billable_hours || 0),
    icon: 'ri-money-dollar-circle-line',
    color: 'success',
    subtitle: 'This month'
  },
  {
    title: 'Non-Billable Hours',
    value: formatHours(props.currentMonth.non_billable_hours || 0),
    icon: 'ri-time-line',
    color: 'warning',
    subtitle: 'This month'
  },
  {
    title: 'Total Hours',
    value: formatHours(props.currentMonth.total_hours || 0),
    icon: 'ri-calculator-line',
    color: 'info',
    subtitle: 'This month'
  },
  {
    title: 'NAD Hours',
    value: `${props.currentMonth.nad_count || 0} (${formatHours(props.currentMonth.nad_hours || 0)})`,
    icon: 'ri-calendar-close-line',
    color: 'error',
    subtitle: 'Count and hours'
  }
])

function calculateChange(current, estimated) {
  if (!estimated || estimated === 0) return 0
  return Math.round(((current - estimated) / estimated) * 100)
}

function getChangeColor(change) {
  if (change > 0) return 'success'
  if (change < 0) return 'error'
  return 'secondary'
}

function getChangeIcon(change) {
  if (change > 0) return 'ri-arrow-up-line'
  if (change < 0) return 'ri-arrow-down-line'
  return 'ri-subtract-line'
}
</script>

<template>
  <div>
    <!-- Current Week Performance -->
    <VCard class="mb-6">
      <VCardItem>
        <VCardTitle class="d-flex align-center">
          <VIcon icon="ri-calendar-week-line" color="primary" class="mr-2" />
          Current Week Performance
        </VCardTitle>
      </VCardItem>

      <VCardText>
        <VRow>
          <VCol 
            v-for="metric in weeklyMetrics" 
            :key="metric.title"
            cols="6" 
            :md="3"
          >
            <VCard variant="tonal" :color="metric.color" class="h-100">
              <VCardText class="text-center pa-4">
                <VAvatar 
                  :color="metric.color" 
                  variant="flat" 
                  size="40" 
                  class="mb-3"
                >
                  <VIcon :icon="metric.icon" size="20" />
                </VAvatar>

                <div class="text-h5 font-weight-bold mb-1">
                  {{ metric.value }}
                </div>
                <div class="text-body-2 font-weight-medium mb-1">
                  {{ metric.title }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ metric.subtitle }}
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Current Month Performance -->
    <VCard>
      <VCardItem>
        <VCardTitle class="d-flex align-center">
          <VIcon icon="ri-calendar-month-line" color="secondary" class="mr-2" />
          Current Month Performance
        </VCardTitle>
      </VCardItem>

      <VCardText>
        <VRow>
          <VCol 
            v-for="metric in monthlyMetrics" 
            :key="metric.title"
            cols="6" 
            :md="3"
          >
            <VCard variant="tonal" :color="metric.color" class="h-100">
              <VCardText class="text-center pa-4">
                <VAvatar 
                  :color="metric.color" 
                  variant="flat" 
                  size="40" 
                  class="mb-3"
                >
                  <VIcon :icon="metric.icon" size="20" />
                </VAvatar>

                <div class="text-h5 font-weight-bold mb-1">
                  {{ metric.value }}
                </div>
                <div class="text-body-2 font-weight-medium mb-1">
                  {{ metric.title }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ metric.subtitle }}
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
@media (max-width: 767px) {
  .text-h5 {
    font-size: 1.25rem !important;
  }
  
  .text-h6 {
    font-size: 1.125rem !important;
  }
  
  :deep(.v-card-text) {
    padding: 12px !important;
  }
}
</style>