<script setup>
import { formatDate } from '@/@core/utils/helpers'
import { computed } from 'vue'

const props = defineProps({
  cohortData: {
    type: Array,
    required: true
  },
  isMobile: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['view-cohort'])

const topCohorts = computed(() => {
  return props.cohortData.slice(0, 5)
})

const totalUsers = computed(() => {
  return props.cohortData.reduce((sum, cohort) => sum + cohort.user_count, 0)
})

function getCohortProgress(userCount) {
  return totalUsers.value > 0 ? (userCount / totalUsers.value) * 100 : 0
}

function handleViewCohort(cohortId) {
  if (cohortId === 'all') {
    // Route to cohorts list page
    emit('view-cohort', 'all')
  } else {
    emit('view-cohort', cohortId)
  }
}

function formatCohortDate(dateString) {
  if (!dateString) return 'No start date'
  return formatDate(dateString)
}
</script>

<template>
  <VCard>
    <VCardItem>
      <template #title>
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center">
            <VIcon icon="ri-group-line" color="success" class="mr-2" />
            Cohort Breakdown
          </div>
          <VChip color="success" size="small" variant="tonal">
            {{ totalUsers }} Total Users
          </VChip>
        </div>
      </template>
    </VCardItem>

    <VCardText>
      <div v-if="cohortData.length === 0" class="text-center py-8">
        <VIcon size="48" color="secondary" icon="ri-group-line" class="mb-4" />
        <h3 class="text-h6 font-weight-regular mb-2">No Cohort Data</h3>
        <p class="text-secondary">No cohorts have been configured yet.</p>
      </div>

      <div v-else>
        <!-- Summary Stats -->
        <VRow class="mb-4">
          <VCol cols="4" class="text-center">
            <div class="text-h4 font-weight-bold text-success">{{ cohortData.length }}</div>
            <div class="text-caption">Active Cohorts</div>
          </VCol>
          <VCol cols="4" class="text-center">
            <div class="text-h4 font-weight-bold text-primary">
              {{ cohortData.reduce((sum, c) => sum + c.full_time_count, 0) }}
            </div>
            <div class="text-caption">Full-Time</div>
          </VCol>
          <VCol cols="4" class="text-center">
            <div class="text-h4 font-weight-bold text-warning">
              {{ cohortData.reduce((sum, c) => sum + c.part_time_count, 0) }}
            </div>
            <div class="text-caption">Part-Time</div>
          </VCol>
        </VRow>

        <VDivider class="mb-4" />

        <!-- Top Cohorts List -->
        <h4 class="text-subtitle-1 font-weight-medium mb-3">Top Cohorts by Users</h4>

        <VList density="compact">
          <VListItem
            v-for="(cohort, index) in topCohorts"
            :key="cohort.id"
            class="px-0"
            @click="handleViewCohort(cohort.id)"
          >
            <template #prepend>
              <VAvatar
                size="32"
                :color="index === 0 ? 'success' : 'secondary'"
                variant="tonal"
              >
                <span class="text-sm font-weight-bold">{{ index + 1 }}</span>
              </VAvatar>
            </template>

            <VListItemTitle class="font-weight-medium">
              {{ cohort.name }}
            </VListItemTitle>

            <VListItemSubtitle>
              Started: {{ formatCohortDate(cohort.start_date) }}
              <span v-if="cohort.description"> • {{ cohort.description }}</span>
            </VListItemSubtitle>

            <template #append>
              <div class="text-end">
                <div class="d-flex align-center mb-1">
                  <VChip
                    color="success"
                    size="small"
                    variant="tonal"
                    class="mr-2"
                  >
                    {{ cohort.user_count }}
                  </VChip>
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ cohort.full_time_count }}FT • {{ cohort.part_time_count }}PT
                </div>
              </div>
            </template>
          </VListItem>
        </VList>

        <!-- Progress Visualization -->
        <div class="mt-4">
          <h4 class="text-subtitle-1 font-weight-medium mb-3">Distribution</h4>
          <div v-for="cohort in topCohorts" :key="`progress-${cohort.id}`" class="mb-2">
            <div class="d-flex justify-space-between align-center mb-1">
              <span class="text-body-2 font-weight-medium">{{ cohort.name }}</span>
              <span class="text-caption">{{ cohort.user_count }} users</span>
            </div>
            <VProgressLinear
              :model-value="getCohortProgress(cohort.user_count)"
              color="success"
              height="6"
              rounded
            />
          </div>
        </div>

        <!-- View All Button -->
        <div class="text-center mt-4" v-if="cohortData.length > 5">
          <VBtn
            color="success"
            variant="outlined"
            size="small"
            @click="handleViewCohort('all')"
          >
            View All Cohorts ({{ cohortData.length }})
          </VBtn>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
:deep(.v-list-item) {
  border-radius: 8px;
  margin-block-end: 2px;
  cursor: pointer;
  transition: background-color 0.2s;
}

:deep(.v-list-item:hover) {
  background-color: rgba(var(--v-theme-success), 0.08);
}

:deep(.v-progress-linear) {
  transition: all 0.3s ease;
}

@media (max-width: 767px) {
  .text-h4 {
    font-size: 1.5rem !important;
  }

  :deep(.v-list-item-title) {
    font-size: 0.875rem;
  }

  :deep(.v-list-item-subtitle) {
    font-size: 0.75rem;
  }
}
</style>