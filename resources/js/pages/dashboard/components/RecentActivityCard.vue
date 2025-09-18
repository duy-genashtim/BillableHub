<script setup>
import { formatTimeAgo } from '@/@core/utils/helpers'
import { formatHours } from '@/@core/utils/worklogHelpers'
import { computed } from 'vue'

const props = defineProps({
  recentActivity: {
    type: Array,
    required: true
  },
  isMobile: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['view-user', 'view-entry'])

const recentEntries = computed(() => {
  return props.recentActivity.slice(0, 8)
})

function getWorkStatusColor(workStatus) {
  return workStatus === 'full-time' ? 'primary' : 'secondary'
}

function getWorkStatusIcon(workStatus) {
  return workStatus === 'full-time' ? 'ri-user-fill' : 'ri-user-3-line'
}

function getHoursColor(hours) {
  if (hours >= 8) return 'success'
  if (hours >= 4) return 'warning'
  return 'info'
}

function handleViewUser(userId) {
  emit('view-user', userId)
}

function handleViewEntry(entryId) {
  emit('view-entry', entryId)
}
</script>

<template>
  <VCard>
    <VCardItem>
      <template #title>
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center">
            <VIcon icon="ri-time-line" color="success" class="mr-2" />
            Non Stop Hour (TBA)
          </div>
          <VChip color="info" size="small" variant="tonal">
            Highest Hours - Last 7 Days
          </VChip>
        </div>
      </template>
    </VCardItem>

    <VCardText>
      <div v-if="recentActivity.length === 0" class="text-center py-8">
        <VIcon size="48" color="secondary" icon="ri-time-line" class="mb-4" />
        <h3 class="text-h6 font-weight-regular mb-2">No Recent Activity</h3>
        <p class="text-secondary">No work entries recorded in the last 7 days.</p>
      </div>

      <div v-else>

        <!-- Activity Feed -->
        <VTimeline side="end" align="start" truncate-line="both" density="compact">
          <VTimelineItem v-for="entry in recentEntries" :key="entry.id" :dot-color="getHoursColor(entry.hours)"
            size="small">
            <template #icon>
              <VIcon :icon="getWorkStatusIcon(entry.work_status)" size="14" />
            </template>

            <VCard variant="tonal" :color="getHoursColor(entry.hours)" class="mb-2">
              <VCardText class="pa-3">
                <div class="d-flex align-center justify-space-between mb-2">
                  <div class="d-flex align-center">
                    <VAvatar size="24" color="primary" class="mr-2">
                      <span class="text-xs font-weight-bold">
                        {{entry.user_name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase()}}
                      </span>
                    </VAvatar>
                    <div>
                      <div class="text-body-2 font-weight-medium cursor-pointer hover-underline"
                        @click="handleViewUser(entry.user_id)">
                        {{ entry.user_name }}
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        {{ entry.region_name || 'Unknown Region' }}
                      </div>
                    </div>
                  </div>
                  <VChip :color="getHoursColor(entry.hours)" size="small" variant="flat">
                    {{ formatHours(entry.hours) }}
                  </VChip>
                </div>

                <div class="text-body-2 mb-2">
                  <span class="font-weight-medium">{{ entry.task_name }}</span>
                  <span class="text-medium-emphasis"> • {{ entry.project_name }}</span>
                </div>

                <div class="d-flex align-center justify-space-between">
                  <VChip :color="getWorkStatusColor(entry.work_status)"
                    :prepend-icon="getWorkStatusIcon(entry.work_status)" size="x-small" variant="tonal">
                    {{ entry.work_status }}
                  </VChip>
                  <span class="text-caption text-medium-emphasis">
                    {{ formatTimeAgo(entry.start_time) }}
                  </span>
                </div>
              </VCardText>
            </VCard>
          </VTimelineItem>
        </VTimeline>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.text-xs {
  font-size: 0.6875rem !important;
}

.hover-underline:hover {
  text-decoration: underline;
}

:deep(.v-timeline-item__body) {
  margin-inline-start: 8px !important;
}

:deep(.v-timeline-item__opposite) {
  display: none;
}

@media (max-width: 767px) {
  .text-h5 {
    font-size: 1.25rem !important;
  }

  :deep(.v-card-text) {
    padding: 8px !important;
  }

  :deep(.v-timeline-item__body) {
    margin-inline-start: 4px !important;
  }
}
</style>
