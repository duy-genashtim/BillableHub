<script setup>
import { formatDateTime, formatDate, safeJsonParse } from '@/@core/utils/helpers';

const props = defineProps({
  user: {
    type: Object,
    required: true
  },
  isMobile: {
    type: Boolean,
    default: false
  }
});

// Helper function to format change values for display
function formatChangeValue(change, valueType) {
  const value = valueType === 'old' ? change.old_value : change.new_value;
  const rawValue = valueType === 'old' ? change.old_value_raw : change.new_value_raw;

  // If we have a formatted display value from backend, use it
  if (change.display_values) {
    const displayValue = valueType === 'old' ? change.display_values.old_display : change.display_values.new_display;
    if (displayValue) return displayValue;
  }

  // Try to parse as JSON in case it's a JSON value
  const parsedValue = safeJsonParse(value);
  if (parsedValue !== null) {
    if (typeof parsedValue === 'object') {
      return JSON.stringify(parsedValue);
    }
    return String(parsedValue);
  }

  // Use raw value if available, otherwise use the original value
  return rawValue || value || 'N/A';
}

function getChangeIcon(fieldChanged) {
  switch (fieldChanged) {
    case 'region': return 'ri-map-pin-line';
    case 'cohort': return 'ri-group-line';
    case 'work_status': return 'ri-briefcase-line';
    case 'is_active': return 'ri-toggle-line';
    default: return 'ri-edit-line';
  }
}

function getChangeColor(fieldChanged) {
  switch (fieldChanged) {
    case 'region': return 'info';
    case 'cohort': return 'secondary';
    case 'work_status': return 'primary';
    case 'is_active': return 'warning';
    default: return 'grey';
  }
}
</script>

<template>
  <VCard>
    <VCardText>
      <h2 class="text-h6 font-weight-medium mb-4">Change History</h2>

      <div v-if="user.changelogs && user.changelogs.length > 0">
        <VTimeline align="start" :density="isMobile ? 'compact' : 'default'">
          <VTimelineItem v-for="change in user.changelogs" :key="change.id"
            :dot-color="getChangeColor(change.field_changed)" size="small">
            <template #icon>
              <VIcon :icon="getChangeIcon(change.field_changed)" size="16" />
            </template>

            <template #opposite>
              <div class="text-caption text-secondary" :class="{ 'text-right': !isMobile }">
                {{ formatDateTime(change.created_at) }}
              </div>
            </template>

            <VCard variant="outlined" class="mb-2">
              <VCardText class="pa-4">
                <div class="d-flex align-center mb-3">
                  <VChip :color="getChangeColor(change.field_changed)" size="small" variant="flat" text-color="white"
                    class="mr-2">
                    {{ change.field_changed.replace('_', ' ').toUpperCase() }}
                  </VChip>
                  <h3 class="text-subtitle-1 font-weight-medium mb-0">
                    Field Updated
                  </h3>
                </div>

                <div class="mb-3">
                  <p class="mb-2"><strong>Reason:</strong> {{ change.change_reason }}</p>

                  <div v-if="formatChangeValue(change, 'old') !== 'N/A'" class="mb-2">
                    <strong>Previous Value:</strong>
                    <VChip size="small" color="error" variant="outlined" class="ml-2">
                      {{ formatChangeValue(change, 'old') }}
                    </VChip>
                  </div>

                  <div v-if="formatChangeValue(change, 'new') !== 'N/A'" class="mb-2">
                    <strong>New Value:</strong>
                    <VChip size="small" color="success" variant="outlined" class="ml-2">
                      {{ formatChangeValue(change, 'new') }}
                    </VChip>
                  </div>
                </div>

                <VDivider class="my-3" />

                <div class="text-caption text-secondary">
                  <div class="d-flex flex-wrap gap-4">
                    <span>
                      <VIcon icon="ri-user-line" size="14" class="mr-1" />
                      {{ change.changed_by_name }}
                    </span>
                    <span>
                      <VIcon icon="ri-mail-line" size="14" class="mr-1" />
                      {{ change.changed_by_email }}
                    </span>
                    <span v-if="change.effective_date">
                      <VIcon icon="ri-calendar-line" size="14" class="mr-1" />
                      Effective: {{ formatDate(change.effective_date) }}
                    </span>
                  </div>
                </div>
              </VCardText>
            </VCard>
          </VTimelineItem>
        </VTimeline>
      </div>

      <div v-else class="text-center py-8">
        <VIcon size="48" color="secondary" icon="ri-history-line" class="mb-4" />
        <h3 class="text-h6 font-weight-regular mb-2">No change history</h3>
        <p class="text-secondary">
          No changes have been recorded for this user yet.
        </p>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
@media (max-width: 767px) {
  :deep(.v-timeline-item__opposite) {
    margin-block-end: 8px;
  }

  :deep(.v-timeline-item) {
    padding-block-end: 1rem;
  }
}
</style>
