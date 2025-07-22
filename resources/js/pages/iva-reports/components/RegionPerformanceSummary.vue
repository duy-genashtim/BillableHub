<script setup>
import { formatHours } from '@/@core/utils/worklogHelpers';
import { computed } from 'vue';

const props = defineProps({
  summary: {
    type: Object,
    required: true
  },
  categorySummary: {
    type: Array,
    default: () => []
  },
  isMobile: {
    type: Boolean,
    default: false
  }
});

// Computed properties - show all categories instead of just top 5
const allCategories = computed(() => {
  return props.categorySummary;
});

function getPerformanceColor(percentage) {
  if (percentage >= 100) return 'success';
  if (percentage >= 90) return 'warning';
  return 'error';
}

function getPerformanceIcon(percentage) {
  if (percentage >= 100) return 'ri-trophy-line';
  if (percentage >= 90) return 'ri-alert-line';
  return 'ri-close-circle-line';
}
</script>

<template>
  <div>
    <!-- Overall Summary -->
    <VCard class="mb-6">
      <VCardItem>
        <VCardTitle class="d-flex align-center">
          <VIcon icon="ri-bar-chart-box-line" color="primary" class="mr-2" />
          Overall Summary
        </VCardTitle>
      </VCardItem>

      <VCardText>
        <VRow>
          <VCol cols="6" md="2">
            <div class="text-center">
              <VIcon icon="ri-group-line" color="primary" size="32" class="mb-2" />
              <div class="text-h4 font-weight-bold">{{ summary.overall?.total_users || 0 }}</div>
              <div class="text-body-2">Total Users</div>
            </div>
          </VCol>

          <VCol cols="6" md="2">
            <div class="text-center">
              <VIcon icon="ri-money-dollar-circle-line" color="success" size="32" class="mb-2" />
              <div class="text-h4 font-weight-bold text-success">
                {{ summary.overall?.total_billable_hours?.toFixed(0) || 0 }}
              </div>
              <div class="text-body-2">Billable Hours</div>
            </div>
          </VCol>

          <VCol cols="6" md="2">
            <div class="text-center">
              <VIcon icon="ri-time-line" color="info" size="32" class="mb-2" />
              <div class="text-h4 font-weight-bold text-info">
                {{ summary.overall?.total_non_billable_hours?.toFixed(0) || 0 }}
              </div>
              <div class="text-body-2">Non-Billable</div>
            </div>
          </VCol>

          <VCol cols="6" md="2">
            <div class="text-center">
              <VIcon icon="ri-flag-line" color="warning" size="32" class="mb-2" />
              <div class="text-h4 font-weight-bold text-warning">
                {{ summary.overall?.total_target_hours?.toFixed(0) || 0 }}
              </div>
              <div class="text-body-2">Target Hours</div>
            </div>
          </VCol>

          <VCol cols="6" md="2">
            <div class="text-center">
              <VIcon :icon="getPerformanceIcon(summary.overall?.avg_performance || 0)"
                :color="getPerformanceColor(summary.overall?.avg_performance || 0)" size="32" class="mb-2" />
              <div class="text-h4 font-weight-bold"
                :class="`text-${getPerformanceColor(summary.overall?.avg_performance || 0)}`">
                {{ summary.overall?.avg_performance || 0 }}%
              </div>
              <div class="text-body-2">Avg Performance</div>
            </div>
          </VCol>

          <VCol cols="6" md="2">
            <div class="text-center">
              <VIcon icon="ri-calendar-check-line" color="secondary" size="32" class="mb-2" />
              <div class="text-h4 font-weight-bold text-secondary">
                {{ summary.overall?.total_nad_count || 0 }}
              </div>
              <div class="text-body-2">Total NADs</div>
            </div>
          </VCol>
        </VRow>

        <!-- Performance Breakdown -->
        <VDivider class="my-4" />

        <div class="d-flex justify-space-around flex-wrap">
          <div class="text-center pa-2">
            <VChip color="success" size="large" variant="flat" text-color="white">
              {{ summary.overall?.performance_breakdown?.exceeded || 0 }}
            </VChip>
            <div class="text-caption mt-1">Exceeded Target</div>
          </div>

          <div class="text-center pa-2">
            <VChip color="warning" size="large" variant="flat" text-color="white">
              {{ summary.overall?.performance_breakdown?.meet || 0 }}
            </VChip>
            <div class="text-caption mt-1">Met Target</div>
          </div>

          <div class="text-center pa-2">
            <VChip color="error" size="large" variant="flat" text-color="white">
              {{ summary.overall?.performance_breakdown?.below || 0 }}
            </VChip>
            <div class="text-caption mt-1">Below Target</div>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- Work Status Comparison -->
    <VRow>
      <!-- Full-Time Summary -->
      <VCol cols="12" md="6">
        <VCard>
          <VCardItem>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="ri-user-fill" color="primary" class="mr-2" />
              Full-Time Summary
            </VCardTitle>
          </VCardItem>

          <VCardText>
            <VList density="compact">
              <VListItem>
                <template #prepend>
                  <VIcon icon="ri-group-line" />
                </template>
                <VListItemTitle>Total Employees</VListItemTitle>
                <template #append>
                  <span class="font-weight-bold">{{ summary.full_time?.total_users || 0 }}</span>
                </template>
              </VListItem>

              <VListItem>
                <template #prepend>
                  <VIcon icon="ri-time-line" />
                </template>
                <VListItemTitle>Total Hours</VListItemTitle>
                <template #append>
                  <span class="font-weight-bold">{{ formatHours(summary.full_time?.total_hours || 0) }}</span>
                </template>
              </VListItem>

              <VListItem>
                <template #prepend>
                  <VIcon icon="ri-money-dollar-circle-line" />
                </template>
                <VListItemTitle>Billable Hours</VListItemTitle>
                <template #append>
                  <span class="font-weight-bold text-success">
                    {{ formatHours(summary.full_time?.total_billable_hours || 0) }}
                  </span>
                </template>
              </VListItem>

              <VListItem>
                <template #prepend>
                  <VIcon icon="ri-trophy-line" />
                </template>
                <VListItemTitle>Average Performance</VListItemTitle>
                <template #append>
                  <VChip :color="getPerformanceColor(summary.full_time?.avg_performance || 0)" size="small"
                    variant="flat" text-color="white">
                    {{ summary.full_time?.avg_performance || 0 }}%
                  </VChip>
                </template>
              </VListItem>

              <VListItem>
                <template #prepend>
                  <VIcon icon="ri-calendar-check-line" />
                </template>
                <VListItemTitle>NAD Hours</VListItemTitle>
                <template #append>
                  <span class="font-weight-bold">
                    {{ formatHours(summary.full_time?.total_nad_hours || 0) }}
                    <span class="text-caption">({{ summary.full_time?.total_nad_count || 0 }} NADs)</span>
                  </span>
                </template>
              </VListItem>
            </VList>

            <!-- Mini Performance Breakdown -->
            <div class="d-flex justify-space-around mt-4">
              <div class="text-center">
                <VAvatar color="success" size="32">
                  {{ summary.full_time?.performance_breakdown?.exceeded || 0 }}
                </VAvatar>
                <div class="text-caption">Exceeded</div>
              </div>
              <div class="text-center">
                <VAvatar color="warning" size="32">
                  {{ summary.full_time?.performance_breakdown?.meet || 0 }}
                </VAvatar>
                <div class="text-caption">Met</div>
              </div>
              <div class="text-center">
                <VAvatar color="error" size="32">
                  {{ summary.full_time?.performance_breakdown?.below || 0 }}
                </VAvatar>
                <div class="text-caption">Below</div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Part-Time Summary -->
      <VCol cols="12" md="6">
        <VCard>
          <VCardItem>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="ri-user-3-line" color="secondary" class="mr-2" />
              Part-Time Summary
            </VCardTitle>
          </VCardItem>

          <VCardText>
            <VList density="compact">
              <VListItem>
                <template #prepend>
                  <VIcon icon="ri-group-line" />
                </template>
                <VListItemTitle>Total Employees</VListItemTitle>
                <template #append>
                  <span class="font-weight-bold">{{ summary.part_time?.total_users || 0 }}</span>
                </template>
              </VListItem>

              <VListItem>
                <template #prepend>
                  <VIcon icon="ri-time-line" />
                </template>
                <VListItemTitle>Total Hours</VListItemTitle>
                <template #append>
                  <span class="font-weight-bold">{{ formatHours(summary.part_time?.total_hours || 0) }}</span>
                </template>
              </VListItem>

              <VListItem>
                <template #prepend>
                  <VIcon icon="ri-money-dollar-circle-line" />
                </template>
                <VListItemTitle>Billable Hours</VListItemTitle>
                <template #append>
                  <span class="font-weight-bold text-success">
                    {{ formatHours(summary.part_time?.total_billable_hours || 0) }}
                  </span>
                </template>
              </VListItem>

              <VListItem>
                <template #prepend>
                  <VIcon icon="ri-trophy-line" />
                </template>
                <VListItemTitle>Average Performance</VListItemTitle>
                <template #append>
                  <VChip :color="getPerformanceColor(summary.part_time?.avg_performance || 0)" size="small"
                    variant="flat" text-color="white">
                    {{ summary.part_time?.avg_performance || 0 }}%
                  </VChip>
                </template>
              </VListItem>

              <VListItem>
                <template #prepend>
                  <VIcon icon="ri-calendar-check-line" />
                </template>
                <VListItemTitle>NAD Hours</VListItemTitle>
                <template #append>
                  <span class="font-weight-bold">
                    {{ formatHours(summary.part_time?.total_nad_hours || 0) }}
                    <span class="text-caption">({{ summary.part_time?.total_nad_count || 0 }} NADs)</span>
                  </span>
                </template>
              </VListItem>
            </VList>

            <!-- Mini Performance Breakdown -->
            <div class="d-flex justify-space-around mt-4">
              <div class="text-center">
                <VAvatar color="success" size="32">
                  {{ summary.part_time?.performance_breakdown?.exceeded || 0 }}
                </VAvatar>
                <div class="text-caption">Exceeded</div>
              </div>
              <div class="text-center">
                <VAvatar color="warning" size="32">
                  {{ summary.part_time?.performance_breakdown?.meet || 0 }}
                </VAvatar>
                <div class="text-caption">Met</div>
              </div>
              <div class="text-center">
                <VAvatar color="error" size="32">
                  {{ summary.part_time?.performance_breakdown?.below || 0 }}
                </VAvatar>
                <div class="text-caption">Below</div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- All Categories -->
    <VCard v-if="allCategories.length > 0" class="mt-6">
      <VCardItem>
        <VCardTitle class="d-flex align-center">
          <VIcon icon="ri-folder-chart-line" color="primary" class="mr-2" />
          All Categories by Hours
        </VCardTitle>
      </VCardItem>

      <VCardText>
        <VList density="compact">
          <VListItem v-for="(category, index) in allCategories" :key="category.category_id">
            <template #prepend>
              <VAvatar size="24" color="primary" variant="tonal">
                {{ index + 1 }}
              </VAvatar>
            </template>

            <VListItemTitle>{{ category.category_name }}</VListItemTitle>
            <VListItemSubtitle>
              {{ category.user_count }} users • Avg {{ category.avg_hours_per_user }}h/user
            </VListItemSubtitle>

            <template #append>
              <div class="text-right">
                <div class="font-weight-bold">{{ formatHours(category.total_hours) }}</div>
                <VProgressLinear :model-value="allCategories[0] ? (category.total_hours / allCategories[0].total_hours) * 100 : 0"
                  color="primary" height="4" rounded class="mt-1" style="inline-size: 100px;" />
              </div>
            </template>
          </VListItem>
        </VList>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
/* Enhanced styling for summary cards */
:deep(.v-list-item) {
  border-radius: 8px;
  margin-block-end: 4px;
  transition: background-color 0.2s;
}

:deep(.v-list-item:hover) {
  background-color: rgba(var(--v-theme-on-surface), 0.04);
}

/* Avatar styling */
.v-avatar {
  font-weight: 600;
}

/* Progress bar styling */
:deep(.v-progress-linear) {
  transition: all 0.3s ease;
}

/* Mobile responsiveness */
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
