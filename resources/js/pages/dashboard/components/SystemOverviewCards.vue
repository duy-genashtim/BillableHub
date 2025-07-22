<script setup>
import { computed } from 'vue'

const props = defineProps({
  systemOverview: {
    type: Object,
    required: true
  },
  isMobile: {
    type: Boolean,
    default: false
  }
})

const metrics = computed(() => [
  {
    title: 'Total IVA Users',
    value: props.systemOverview.total_users || 0,
    icon: 'ri-group-line',
    color: 'primary',
    subtitle: `${props.systemOverview.full_time_users || 0} Full-time • ${props.systemOverview.part_time_users || 0} Part-time`
  },
  {
    title: 'Active Regions',
    value: props.systemOverview.total_regions || 0,
    icon: 'ri-map-pin-line',
    color: 'info',
    subtitle: 'Operational regions'
  },
  {
    title: 'Task Categories',
    value: (props.systemOverview.billable_categories || 0) + (props.systemOverview.non_billable_categories || 0),
    icon: 'ri-folder-chart-line',
    color: 'secondary',
    subtitle: `${props.systemOverview.billable_categories || 0} Billable • ${props.systemOverview.non_billable_categories || 0} Non-billable`
  },
  {
    title: 'Active Cohorts',
    value: props.systemOverview.total_cohorts || 0,
    icon: 'ri-team-line',
    color: 'success',
    subtitle: `${props.systemOverview.total_tasks || 0} Tasks • ${props.systemOverview.total_projects || 0} Projects`
  }
])
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle class="d-flex align-center">
        <VIcon icon="ri-dashboard-line" color="primary" class="mr-2" />
        System Overview
      </VCardTitle>
    </VCardItem>

    <VCardText>
      <VRow>
        <VCol 
          v-for="metric in metrics" 
          :key="metric.title"
          cols="12" 
          :sm="isMobile ? 12 : 6"
          :md="6"
        >
          <VCard variant="tonal" :color="metric.color" class="h-100">
            <VCardText class="d-flex align-center">
              <VAvatar 
                :color="metric.color" 
                variant="flat" 
                size="48" 
                class="mr-4"
              >
                <VIcon :icon="metric.icon" size="24" />
              </VAvatar>

              <div class="flex-grow-1">
                <div class="text-h4 font-weight-bold mb-1">
                  {{ metric.value.toLocaleString() }}
                </div>
                <div class="text-subtitle-2 font-weight-medium mb-1">
                  {{ metric.title }}
                </div>
                <div class="text-caption">
                  {{ metric.subtitle }}
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>

<style scoped>
@media (max-width: 767px) {
  .text-h4 {
    font-size: 1.75rem !important;
  }
  
  :deep(.v-card-text) {
    padding: 16px;
  }
}
</style>