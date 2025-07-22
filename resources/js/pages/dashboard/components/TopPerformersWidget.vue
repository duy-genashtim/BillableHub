<script setup>
import { formatHours } from '@/@core/utils/worklogHelpers'

const props = defineProps({
  topPerformers: {
    type: Array,
    required: true
  },
  isMobile: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['view-user'])

function getWorkStatusColor(workStatus) {
  return workStatus === 'full-time' ? 'primary' : 'secondary'
}

function getWorkStatusIcon(workStatus) {
  return workStatus === 'full-time' ? 'ri-user-fill' : 'ri-user-3-line'
}

function getRankColor(index) {
  if (index === 0) return 'success'
  if (index === 1) return 'warning'
  if (index === 2) return 'info'
  return 'secondary'
}

function getRankIcon(index) {
  if (index === 0) return 'ri-trophy-line'
  if (index === 1) return 'ri-medal-line'
  if (index === 2) return 'ri-award-line'
  return 'ri-star-line'
}

function handleViewUser(userId) {
  emit('view-user', userId)
}
</script>

<template>
  <VCard>
    <VCardItem>
      <template #title>
        <div class="d-flex align-center">
          <VIcon icon="ri-trophy-line" color="warning" class="mr-2" />
          Highest Time Logged
        </div>
      </template>
      <template #subtitle>
        <VChip color="warning" size="small" variant="tonal">
          Last 3 Days
        </VChip>
      </template>
    </VCardItem>

    <VCardText>
      <div v-if="topPerformers.length === 0" class="text-center py-8">
        <VIcon size="48" color="secondary" icon="ri-trophy-line" class="mb-4" />
        <h3 class="text-h6 font-weight-regular mb-2">No Top Performers</h3>
        <p class="text-secondary">No work entries recorded in the last 3 days.</p>
      </div>

      <div v-else>
        <div v-for="(performer, index) in topPerformers" :key="performer.user_id" class="mb-3">
          <VCard variant="tonal" :color="getRankColor(index)" class="cursor-pointer transition-all hover:elevation-4"
            @click="handleViewUser(performer.user_id)">
            <VCardText class="pa-3">
              <div class="d-flex align-center">
                <!-- Rank Badge -->
                <VAvatar :color="getRankColor(index)" variant="flat" size="32" class="mr-3">
                  <VIcon :icon="getRankIcon(index)" size="16" />
                </VAvatar>

                <!-- User Info -->
                <div class="flex-grow-1">
                  <div class="d-flex align-center justify-space-between mb-1">
                    <div class="text-body-1 font-weight-bold">
                      {{ performer.user_name }}
                    </div>
                    <VChip :color="getRankColor(index)" size="small" variant="flat">
                      {{ formatHours(performer.total_hours) }}
                    </VChip>
                  </div>

                  <div class="d-flex align-center justify-space-between">
                    <div class="text-caption text-medium-emphasis">
                      {{ performer.region_name || 'Unknown Region' }}
                    </div>
                    <VChip :color="getWorkStatusColor(performer.work_status)"
                      :prepend-icon="getWorkStatusIcon(performer.work_status)" size="x-small" variant="tonal">
                      {{ performer.work_status }}
                    </VChip>
                  </div>

                  <div class="text-caption text-medium-emphasis mt-1">
                    {{ performer.total_entries }} entries • {{ formatHours(performer.avg_hours_per_entry) }} avg/entry
                  </div>
                </div>
              </div>
            </VCardText>
          </VCard>
        </div>

        <!-- View All Button -->
        <div class="text-center mt-4">
          <VBtn color="warning" variant="outlined" size="small" prepend-icon="ri-trophy-line"
            @click="$router.push('/admin/iva-users')">
            View All Users
          </VBtn>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}

.transition-all {
  transition: all 0.2s ease;
}

.hover\:elevation-4:hover {
  box-shadow: 0 4px 8px rgba(0, 0, 0, 12%), 0 2px 4px rgba(0, 0, 0, 8%) !important;
}

@media (max-width: 767px) {
  .text-body-1 {
    font-size: 0.875rem !important;
  }

  :deep(.v-card-text) {
    padding: 8px !important;
  }
}
</style>
