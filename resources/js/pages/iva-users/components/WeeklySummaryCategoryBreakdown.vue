<script setup>
import { formatHours } from '@/@core/utils/worklogHelpers';
import { computed, ref } from 'vue';

const props = defineProps({
  categoryBreakdown: {
    type: Array,
    required: true
  },
  isMobile: {
    type: Boolean,
    default: false
  }
});

const expandedCategories = ref({});

function toggleCategory(categoryName) {
  expandedCategories.value[categoryName] = !expandedCategories.value[categoryName];
}

const totalHours = computed(() => {
  return props.categoryBreakdown.reduce((sum, group) => sum + group.total_hours, 0);
});

function getProgressPercentage(hours) {
  return totalHours.value > 0 ? (hours / totalHours.value) * 100 : 0;
}
</script>

<template>
  <VCard>
    <VCardText>
      <h2 class="text-h6 font-weight-medium mb-4">Category Breakdown Summary</h2>

      <div v-if="!categoryBreakdown?.length" class="text-center py-8">
        <VIcon size="48" icon="ri-folder-open-line" color="grey-lighten-1" class="mb-2" />
        <p class="text-body-2">No work entries found for the selected period</p>
      </div>

      <div v-else class="category-breakdown">
        <!-- Main Category Level (Billable/Non-Billable) -->
        <div v-for="mainCategory in categoryBreakdown" :key="mainCategory.type" class="main-category-section mb-6">
          <!-- Main Category Header -->
          <VCard variant="elevated" class="mb-3"
            :color="mainCategory.type.includes('Billable') ? 'success-light' : 'info'">
            <VCardItem class="cursor-pointer" @click="toggleCategory(mainCategory.type)">
              <template v-slot:prepend>
                <VAvatar :color="mainCategory.type.includes('Billable') ? 'success' : 'info'" variant="flat" size="32">
                  <VIcon :icon="mainCategory.type.includes('Billable') ? 'ri-money-dollar-circle-line' : 'ri-time-line'"
                    size="18" />
                </VAvatar>
              </template>

              <VCardTitle class="d-flex align-center">
                <span class="mr-3">{{ mainCategory.type }} Hours</span>
                <VChip size="small" :text-color="mainCategory.type.includes('Billable') ? 'success' : 'info'">
                  {{ formatHours(mainCategory.total_hours) }}
                </VChip>
                <VChip size="small" variant="outlined" class="ml-2"
                  :text-color="mainCategory.type.includes('Billable') ? 'success' : 'info'">
                  {{ mainCategory.categories.length }} {{ mainCategory.categories.length === 1 ? 'category' :
                    'categories' }}
                </VChip>
              </VCardTitle>

              <template v-slot:append>
                <VIcon :icon="expandedCategories[mainCategory.type] ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'"
                  color="white" />
              </template>
            </VCardItem>
          </VCard>

          <!-- Progress Bar for Main Category -->
          <VProgressLinear :model-value="getProgressPercentage(mainCategory.total_hours)"
            :color="mainCategory.type.includes('Billable') ? 'success' : 'info'" height="8" rounded class="mb-4" />

          <!-- Categories within Main Category -->
          <VExpandTransition>
            <div v-show="expandedCategories[mainCategory.type]" class="ml-4">
              <div v-for="category in mainCategory.categories" :key="category.category_name"
                class="category-section mb-3">
                <!-- Category Card -->
                <VCard variant="outlined" class="category-card">
                  <VCardText class="pa-4">
                    <div class="d-flex align-center justify-space-between">
                      <div class="d-flex align-center flex-grow-1">
                        <VAvatar color="primary" variant="tonal" size="24" class="mr-3">
                          <VIcon icon="ri-folder-line" size="12" />
                        </VAvatar>
                        <div class="flex-grow-1">
                          <h3 class="text-subtitle-2 font-weight-medium mb-1">
                            {{ category.category_name }}
                          </h3>
                          <div class="text-caption text-medium-emphasis">
                            {{ category.entries_count }} {{ category.entries_count === 1 ? 'entry' : 'entries' }}
                          </div>
                        </div>
                      </div>

                      <div class="text-right">
                        <VChip color="primary" size="small" class="mb-1">
                          {{ formatHours(category.total_hours) }}
                        </VChip>
                        <div class="text-caption text-medium-emphasis">
                          {{ Math.round(getProgressPercentage(category.total_hours)) }}% of total
                        </div>
                      </div>
                    </div>

                    <!-- Progress Bar for Category -->
                    <VProgressLinear :model-value="getProgressPercentage(category.total_hours)" color="primary"
                      height="4" rounded class="mt-3" />
                  </VCardText>
                </VCard>
              </div>
            </div>
          </VExpandTransition>
        </div>

        <!-- Summary Card -->
        <VCard color="grey-lighten-4" variant="tonal" class="mt-6">
          <VCardText>
            <div class="d-flex align-center justify-space-between">
              <div class="d-flex align-center">
                <VAvatar color="secondary" variant="flat" size="32" class="mr-3">
                  <VIcon icon="ri-calculator-line" size="18" />
                </VAvatar>
                <div>
                  <h3 class="text-subtitle-1 font-weight-medium">Total Hours Summary</h3>
                  <p class="text-body-2 text-medium-emphasis mb-0">
                    Across all categories for the selected weeks
                  </p>
                </div>
              </div>
              <div class="text-right">
                <div class="text-h5 font-weight-bold text-secondary">
                  {{ formatHours(totalHours) }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{categoryBreakdown.reduce((sum, group) => sum + group.categories.length, 0)}} categories
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.main-category-section {
  border-inline-start: 4px solid #e3f2fd;
  padding-inline-start: 16px;
}

.category-section {
  border-inline-start: 3px solid #e8f5e8;
  padding-inline-start: 12px;
}

.category-card {
  background: #fafafa;
  border-inline-start: 3px solid transparent;
  transition: all 0.2s ease;
}

.category-card:hover {
  background: #f0f0f0;
  border-inline-start-color: #2196f3;
  transform: translateX(4px);
}

.cursor-pointer {
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.cursor-pointer:hover {
  background: rgba(0, 0, 0, 2%);
}

.cursor-pointer:focus-visible {
  border-radius: 4px;
  outline: 2px solid var(--v-theme-primary);
  outline-offset: 2px;
}

@media (max-width: 767px) {

  .category-section,
  .main-category-section {
    padding-inline-start: 8px;
  }

  .ml-4 {
    margin-inline-start: 8px !important;
  }

  :deep(.v-card-text) {
    padding: 16px;
  }
}
</style>
