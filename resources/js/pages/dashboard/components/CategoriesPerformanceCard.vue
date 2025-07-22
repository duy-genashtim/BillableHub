<script setup>
import { formatHours } from '@/@core/utils/worklogHelpers'
import { computed } from 'vue'

const props = defineProps({
  categoriesData: {
    type: Array,
    required: true
  },
  isMobile: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['view-category'])

const topCategories = computed(() => {
  return props.categoriesData.slice(0, 8)
})

const totalHours = computed(() => {
  return props.categoriesData.reduce((sum, category) => sum + category.total_hours, 0)
})

function getCategoryProgress(hours) {
  return totalHours.value > 0 ? (hours / totalHours.value) * 100 : 0
}

function getCategoryColor(index) {
  const colors = ['primary', 'success', 'warning', 'info', 'secondary', 'error']
  return colors[index % colors.length]
}

function handleViewCategory(categoryId) {
  emit('view-category', categoryId)
}
</script>

<template>
  <VCard>
    <VCardItem>
      <template #title>
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center">
            <VIcon icon="ri-folder-chart-line" color="primary" class="mr-2" />
            Top Categories (This Week)
          </div>
          <VChip color="primary" size="small" variant="tonal">
            {{ formatHours(totalHours) }} Total
          </VChip>
        </div>
      </template>
    </VCardItem>

    <VCardText>
      <div v-if="categoriesData.length === 0" class="text-center py-8">
        <VIcon size="48" color="secondary" icon="ri-folder-chart-line" class="mb-4" />
        <h3 class="text-h6 font-weight-regular mb-2">No Category Data</h3>
        <p class="text-secondary">No billable work recorded this week.</p>
      </div>

      <div v-else>
        <!-- Summary Stats -->
        <VRow class="mb-4">
          <VCol cols="4" class="text-center">
            <div class="text-h5 font-weight-bold text-primary">{{ categoriesData.length }}</div>
            <div class="text-caption">Active Categories</div>
          </VCol>
          <VCol cols="4" class="text-center">
            <div class="text-h5 font-weight-bold text-success">
              {{ categoriesData.reduce((sum, c) => sum + c.user_count, 0) }}
            </div>
            <div class="text-caption">Users Working</div>
          </VCol>
          <VCol cols="4" class="text-center">
            <div class="text-h5 font-weight-bold text-info">
              {{ categoriesData.reduce((sum, c) => sum + c.entry_count, 0) }}
            </div>
            <div class="text-caption">Total Entries</div>
          </VCol>
        </VRow>

        <VDivider class="mb-4" />

        <!-- Categories List -->
        <VList density="compact">
          <VListItem 
            v-for="(category, index) in topCategories" 
            :key="category.category_id"
            class="px-0"
            @click="handleViewCategory(category.category_id)"
          >
            <template #prepend>
              <VAvatar 
                size="32" 
                :color="getCategoryColor(index)" 
                variant="tonal"
              >
                <VIcon icon="ri-folder-line" size="16" />
              </VAvatar>
            </template>

            <VListItemTitle class="font-weight-medium">
              {{ category.category_name }}
            </VListItemTitle>
            
            <VListItemSubtitle class="d-flex align-center">
              <span class="mr-2">{{ category.user_count }} users</span>
              <VDivider vertical class="mx-2" />
              <span>{{ category.entry_count }} entries</span>
            </VListItemSubtitle>

            <template #append>
              <div class="text-end">
                <div class="text-h6 font-weight-bold mb-1">
                  {{ formatHours(category.total_hours) }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ formatHours(category.avg_hours_per_user) }}/user avg
                </div>
              </div>
            </template>
          </VListItem>
        </VList>

        <!-- Progress Visualization -->
        <div class="mt-4">
          <h4 class="text-subtitle-1 font-weight-medium mb-3">Hours Distribution</h4>
          <div v-for="(category, index) in topCategories.slice(0, 5)" :key="`progress-${category.category_id}`" class="mb-3">
            <div class="d-flex justify-space-between align-center mb-2">
              <div class="d-flex align-center">
                <VAvatar 
                  size="20" 
                  :color="getCategoryColor(index)" 
                  variant="flat" 
                  class="mr-2"
                >
                  <span class="text-xs font-weight-bold">{{ index + 1 }}</span>
                </VAvatar>
                <span class="text-body-2 font-weight-medium">{{ category.category_name }}</span>
              </div>
              <div class="text-end">
                <span class="text-body-2 font-weight-bold">{{ formatHours(category.total_hours) }}</span>
                <span class="text-caption ml-1">({{ getCategoryProgress(category.total_hours).toFixed(1) }}%)</span>
              </div>
            </div>
            <VProgressLinear 
              :model-value="getCategoryProgress(category.total_hours)"
              :color="getCategoryColor(index)"
              height="8"
              rounded
            />
          </div>
        </div>

        <!-- View All Button -->
        <div class="text-center mt-4" v-if="categoriesData.length > 8">
          <VBtn 
            color="primary" 
            variant="outlined" 
            size="small"
            @click="$router.push('/admin/categories')"
          >
            View All Categories ({{ categoriesData.length }})
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

.text-xs {
  font-size: 0.6875rem !important;
}

@media (max-width: 767px) {
  .text-h5 {
    font-size: 1.25rem !important;
  }
  
  .text-h6 {
    font-size: 1.125rem !important;
  }
  
  :deep(.v-list-item-title) {
    font-size: 0.875rem;
  }
  
  :deep(.v-list-item-subtitle) {
    font-size: 0.75rem;
  }
}
</style>