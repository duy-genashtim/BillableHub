<script setup>
import { useAuthStore } from '@/@core/stores/auth'
import { computed } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const authStore = useAuthStore()

const props = defineProps({
  isMobile: {
    type: Boolean,
    default: false
  }
})

// Helper function to check if user has permission
const hasPermission = (permission) => {
  return authStore.user?.permissions?.some(p => p.name === permission) || false
}

const quickActions = computed(() => {
  const actions = []

  // Report Actions
  if (hasPermission('view_reports')) {
    actions.push({
      title: 'Daily Performance',
      subtitle: 'View today\'s performance metrics',
      icon: 'ri-line-chart-line',
      color: 'primary',
      route: '/admin/reports/daily-performance',
      category: 'Performance'
    })

    actions.push({
      title: 'Weekly Performance',
      subtitle: 'Analyze weekly performance trends',
      icon: 'ri-bar-chart-grouped-line',
      color: 'success',
      route: '/admin/reports/weekly-performance',
      category: 'Performance'
    })

    actions.push({
      title: 'Region Reports',
      subtitle: 'Compare regional performance',
      icon: 'ri-map-pin-line',
      color: 'info',
      route: '/admin/reports/region-performance',
      category: 'Reports'
    })

    actions.push({
      title: 'Overall Reports',
      subtitle: 'View all users across regions',
      icon: 'ri-global-line',
      color: 'secondary',
      route: '/admin/reports/overall-performance',
      category: 'Reports'
    })
  }

  // Management Actions
  if (hasPermission('manage_configuration')) {
    actions.push({
      title: 'IVA Users',
      subtitle: 'Manage IVA user accounts',
      icon: 'ri-user-line',
      color: 'primary',
      route: '/admin/iva-users',
      category: 'Management'
    })

    actions.push({
      title: 'Regions',
      subtitle: 'Configure regional settings',
      icon: 'ri-map-pin-line',
      color: 'warning',
      route: '/admin/regions',
      category: 'Management'
    })

    actions.push({
      title: 'Task Categories',
      subtitle: 'Organize task classifications',
      icon: 'ri-folder-chart-line',
      color: 'success',
      route: '/admin/categories',
      category: 'Management'
    })
  }


  return actions
})

const actionsByCategory = computed(() => {
  const grouped = {}
  quickActions.value.forEach(action => {
    if (!grouped[action.category]) {
      grouped[action.category] = []
    }
    grouped[action.category].push(action)
  })
  return grouped
})

function handleActionClick(route) {
  router.push(route)
}

function getCategoryIcon(category) {
  switch (category) {
    case 'Reports': return 'ri-bar-chart-line'
    case 'Performance': return 'ri-speed-line'
    case 'Management': return 'ri-settings-3-line'
    default: return 'ri-folder-line'
  }
}

function getCategoryColor(category) {
  switch (category) {
    case 'Reports': return 'primary'
    case 'Performance': return 'warning'
    case 'Management': return 'success'
    default: return 'secondary'
  }
}
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle class="d-flex align-center">
        <VIcon icon="ri-rocket-line" color="warning" class="mr-2" />
        Quick Actions
      </VCardTitle>
    </VCardItem>

    <VCardText>
      <div v-if="quickActions.length === 0" class="text-center py-8">
        <VIcon size="48" color="secondary" icon="ri-lock-line" class="mb-4" />
        <h3 class="text-h6 font-weight-regular mb-2">No Actions Available</h3>
        <p class="text-secondary">You don't have permissions for any quick actions.</p>
      </div>

      <div v-else>
        <!-- Categories -->
        <div v-for="(actions, category) in actionsByCategory" :key="category" class="mb-6 last:mb-0">
          <!-- Category Header -->
          <div class="d-flex align-center mb-3">
            <VAvatar :color="getCategoryColor(category)" variant="tonal" size="28" class="mr-3">
              <VIcon :icon="getCategoryIcon(category)" size="14" />
            </VAvatar>
            <h3 class="text-subtitle-1 font-weight-bold">{{ category }}</h3>
          </div>

          <!-- Action Buttons -->
          <VRow>
            <VCol v-for="action in actions" :key="action.route" cols="12" :sm="isMobile ? 12 : 6" :md="6">
              <VCard variant="outlined" class="action-card h-100" hover @click="handleActionClick(action.route)">
                <VCardText class="d-flex align-center pa-4">
                  <VAvatar :color="action.color" variant="tonal" size="40" class="mr-4">
                    <VIcon :icon="action.icon" size="20" />
                  </VAvatar>

                  <div class="flex-grow-1">
                    <div class="text-subtitle-2 font-weight-bold mb-1">
                      {{ action.title }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ action.subtitle }}
                    </div>
                  </div>

                  <VIcon icon="ri-arrow-right-s-line" color="secondary" size="20" class="ml-2" />
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.action-card {
  cursor: pointer;
  transition: all 0.2s ease;
}

.action-card:hover {
  box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 0.15);
  transform: translateY(-2px);
}

.last\:mb-0:last-child {
  margin-block-end: 0 !important;
}

@media (max-width: 767px) {
  .action-card:hover {
    transform: none;
  }

  :deep(.v-card-text) {
    padding: 12px !important;
  }
}
</style>
