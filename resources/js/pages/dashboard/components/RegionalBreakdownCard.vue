<script setup>
import { computed } from 'vue'

const props = defineProps({
  regionalData: {
    type: Array,
    required: true
  },
  isMobile: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['view-region'])

const topRegions = computed(() => {
  return props.regionalData.slice(0, 5)
})

const totalUsers = computed(() => {
  return props.regionalData.reduce((sum, region) => sum + region.user_count, 0)
})

function getRegionProgress(userCount) {
  return totalUsers.value > 0 ? (userCount / totalUsers.value) * 100 : 0
}

function handleViewRegion(regionId) {
  emit('view-region', regionId)
}
</script>

<template>
  <VCard>
    <VCardItem>
      <template #title>
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center">
            <VIcon icon="ri-map-pin-line" color="info" class="mr-2" />
            Regional Breakdown
          </div>
          <VChip color="info" size="small" variant="tonal">
            {{ totalUsers }} Total Users
          </VChip>
        </div>
      </template>
    </VCardItem>

    <VCardText>
      <div v-if="regionalData.length === 0" class="text-center py-8">
        <VIcon size="48" color="secondary" icon="ri-map-pin-line" class="mb-4" />
        <h3 class="text-h6 font-weight-regular mb-2">No Regional Data</h3>
        <p class="text-secondary">No regions have been configured yet.</p>
      </div>

      <div v-else>
        <!-- Summary Stats -->
        <VRow class="mb-4">
          <VCol cols="4" class="text-center">
            <div class="text-h4 font-weight-bold text-primary">{{ regionalData.length }}</div>
            <div class="text-caption">Active Regions</div>
          </VCol>
          <VCol cols="4" class="text-center">
            <div class="text-h4 font-weight-bold text-success">
              {{ regionalData.reduce((sum, r) => sum + r.full_time_count, 0) }}
            </div>
            <div class="text-caption">Full-Time</div>
          </VCol>
          <VCol cols="4" class="text-center">
            <div class="text-h4 font-weight-bold text-warning">
              {{ regionalData.reduce((sum, r) => sum + r.part_time_count, 0) }}
            </div>
            <div class="text-caption">Part-Time</div>
          </VCol>
        </VRow>

        <VDivider class="mb-4" />

        <!-- Top Regions List -->
        <h4 class="text-subtitle-1 font-weight-medium mb-3">Top Regions by Users</h4>
        
        <VList density="compact">
          <VListItem 
            v-for="(region, index) in topRegions" 
            :key="region.id"
            class="px-0"
            @click="handleViewRegion(region.id)"
          >
            <template #prepend>
              <VAvatar 
                size="32" 
                :color="index === 0 ? 'primary' : 'secondary'" 
                variant="tonal"
              >
                <span class="text-sm font-weight-bold">{{ index + 1 }}</span>
              </VAvatar>
            </template>

            <VListItemTitle class="font-weight-medium">
              {{ region.name }}
            </VListItemTitle>
            
            <VListItemSubtitle>
              {{ region.description || 'No description' }}
            </VListItemSubtitle>

            <template #append>
              <div class="text-end">
                <div class="d-flex align-center mb-1">
                  <VChip 
                    color="primary" 
                    size="small" 
                    variant="tonal"
                    class="mr-2"
                  >
                    {{ region.user_count }}
                  </VChip>
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ region.full_time_count }}FT • {{ region.part_time_count }}PT
                </div>
              </div>
            </template>
          </VListItem>
        </VList>

        <!-- Progress Visualization -->
        <div class="mt-4">
          <h4 class="text-subtitle-1 font-weight-medium mb-3">Distribution</h4>
          <div v-for="region in topRegions" :key="`progress-${region.id}`" class="mb-2">
            <div class="d-flex justify-space-between align-center mb-1">
              <span class="text-body-2 font-weight-medium">{{ region.name }}</span>
              <span class="text-caption">{{ region.user_count }} users</span>
            </div>
            <VProgressLinear 
              :model-value="getRegionProgress(region.user_count)"
              color="primary"
              height="6"
              rounded
            />
          </div>
        </div>

        <!-- View All Button -->
        <div class="text-center mt-4" v-if="regionalData.length > 5">
          <VBtn 
            color="primary" 
            variant="outlined" 
            size="small"
            @click="$router.push('/admin/regions')"
          >
            View All Regions ({{ regionalData.length }})
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
  background-color: rgba(var(--v-theme-primary), 0.08);
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