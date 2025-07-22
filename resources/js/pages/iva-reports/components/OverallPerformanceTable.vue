<script setup>
import { computed } from 'vue'

const props = defineProps({
  regionsData: {
    type: Array,
    default: () => []
  },
  users: {
    type: Array,
    default: () => []
  },
  showDetails: {
    type: Boolean,
    default: false
  },
  categories: {
    type: Array,
    default: () => []
  },
  dateMode: {
    type: String,
    default: 'weekly'
  }
})

const emit = defineEmits(['view-dashboard'])

// Group users by region for display
const usersByRegion = computed(() => {
  const grouped = {};
  
  props.users.forEach(user => {
    const regionId = user.region_id;
    const regionName = user.region_name || 'Unknown Region';
    
    if (!grouped[regionId]) {
      grouped[regionId] = {
        region_id: regionId,
        region_name: regionName,
        users: [],
        summary: {
          total_users: 0,
          total_billable_hours: 0,
          total_non_billable_hours: 0,
          total_hours: 0,
          total_target_hours: 0,
          avg_performance: 0
        }
      };
    }
    
    grouped[regionId].users.push(user);
  });

  // Calculate summaries for each region
  Object.values(grouped).forEach(region => {
    const users = region.users;
    region.summary.total_users = users.length;
    region.summary.total_billable_hours = users.reduce((sum, u) => sum + (u.billable_hours || 0), 0);
    region.summary.total_non_billable_hours = users.reduce((sum, u) => sum + (u.non_billable_hours || 0), 0);
    region.summary.total_hours = region.summary.total_billable_hours + region.summary.total_non_billable_hours;
    region.summary.total_target_hours = users.reduce((sum, u) => sum + (u.target_hours || 0), 0);
    region.summary.avg_performance = region.summary.total_target_hours > 0 
      ? Math.round((region.summary.total_billable_hours / region.summary.total_target_hours) * 100 * 10) / 10
      : 0;
  });

  // Sort by region name
  return Object.values(grouped).sort((a, b) => a.region_name.localeCompare(b.region_name));
});

// Table headers
const headers = computed(() => {
  const baseHeaders = [
    { title: 'User', key: 'user', sortable: true },
    { title: 'Work Status', key: 'work_status', sortable: true },
    { title: 'Billable Hours', key: 'billable_hours', sortable: true, align: 'end' },
    { title: 'Non-Billable Hours', key: 'non_billable_hours', sortable: true, align: 'end' },
    { title: 'Total Hours', key: 'total_hours', sortable: true, align: 'end' },
    { title: 'Target Hours', key: 'target_hours', sortable: true, align: 'end' },
    { title: 'Performance', key: 'performance', sortable: true, align: 'center' },
    { title: 'NAD', key: 'nad', sortable: false, align: 'center' },
    { title: 'Actions', key: 'actions', sortable: false, align: 'center' }
  ];

  if (props.showDetails) {
    baseHeaders.splice(1, 0, 
      { title: 'Email', key: 'email', sortable: true },
      { title: 'Job Title', key: 'job_title', sortable: true }
    );
  }

  return baseHeaders;
});

function formatNumber(value, decimals = 1) {
  if (typeof value !== 'number' || isNaN(value)) return '0';
  return value.toFixed(decimals);
}

function getPerformanceColor(status) {
  switch (status) {
    case 'EXCEEDED': return 'success';
    case 'MEET': return 'info';
    case 'BELOW': return 'warning';
    default: return 'secondary';
  }
}

function getPerformanceIcon(status) {
  switch (status) {
    case 'EXCEEDED': return 'ri-arrow-up-line';
    case 'MEET': return 'ri-check-line';
    case 'BELOW': return 'ri-arrow-down-line';
    default: return 'ri-minus-line';
  }
}

function getWorkStatusColor(status) {
  return status === 'full-time' ? 'primary' : 'secondary';
}

function getWorkStatusIcon(status) {
  return status === 'full-time' ? 'ri-user-fill' : 'ri-user-3-line';
}

function handleViewDashboard(userId) {
  emit('view-dashboard', userId);
}
</script>

<template>
  <div>
    <!-- Region Groups -->
    <div v-for="regionGroup in usersByRegion" :key="regionGroup.region_id" class="mb-8">
      <!-- Region Header -->
      <div class="d-flex align-center mb-4">
        <VIcon icon="ri-map-pin-line" color="primary" class="mr-2" />
        <h3 class="text-h6 font-weight-bold">{{ regionGroup.region_name }}</h3>
        <VSpacer />
        <VChip color="info" size="small" variant="tonal">
          {{ regionGroup.users.length }} user{{ regionGroup.users.length !== 1 ? 's' : '' }}
        </VChip>
      </div>

      <!-- Region Summary Cards -->
      <VRow class="mb-4">
        <VCol cols="6" sm="3">
          <VCard class="pa-3 text-center">
            <div class="text-body-2 text-medium-emphasis">Total Hours</div>
            <div class="text-h6 font-weight-bold text-primary">
              {{ formatNumber(regionGroup.summary.total_hours, 1) }}
            </div>
          </VCard>
        </VCol>
        <VCol cols="6" sm="3">
          <VCard class="pa-3 text-center">
            <div class="text-body-2 text-medium-emphasis">Billable Hours</div>
            <div class="text-h6 font-weight-bold text-success">
              {{ formatNumber(regionGroup.summary.total_billable_hours, 1) }}
            </div>
          </VCard>
        </VCol>
        <VCol cols="6" sm="3">
          <VCard class="pa-3 text-center">
            <div class="text-body-2 text-medium-emphasis">Target Hours</div>
            <div class="text-h6 font-weight-bold text-info">
              {{ formatNumber(regionGroup.summary.total_target_hours, 1) }}
            </div>
          </VCard>
        </VCol>
        <VCol cols="6" sm="3">
          <VCard class="pa-3 text-center">
            <div class="text-body-2 text-medium-emphasis">Performance</div>
            <div class="text-h6 font-weight-bold" 
                 :class="regionGroup.summary.avg_performance >= 100 ? 'text-success' : 
                         regionGroup.summary.avg_performance >= 80 ? 'text-info' : 'text-warning'">
              {{ formatNumber(regionGroup.summary.avg_performance, 1) }}%
            </div>
          </VCard>
        </VCol>
      </VRow>

      <!-- Users Table -->
      <VDataTable
        :headers="headers"
        :items="regionGroup.users"
        :items-per-page="20"
        class="elevation-2"
        item-key="id"
      >
        <!-- User column -->
        <template #item.user="{ item }">
          <div class="d-flex align-center py-2">
            <VAvatar size="32" color="primary" class="mr-3">
              <span class="text-sm font-weight-medium">
                {{ item.full_name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() }}
              </span>
            </VAvatar>
            <div>
              <div class="font-weight-medium">{{ item.full_name }}</div>
            </div>
          </div>
        </template>

        <!-- Email column (conditional) -->
        <template #item.email="{ item }" v-if="showDetails">
          <span class="text-body-2">{{ item.email || '-' }}</span>
        </template>

        <!-- Job Title column (conditional) -->
        <template #item.job_title="{ item }" v-if="showDetails">
          <span class="text-body-2">{{ item.job_title || '-' }}</span>
        </template>

        <!-- Work Status column -->
        <template #item.work_status="{ item }">
          <VChip 
            :color="getWorkStatusColor(item.work_status)" 
            :prepend-icon="getWorkStatusIcon(item.work_status)"
            size="small" 
            variant="tonal"
          >
            {{ item.work_status }}
          </VChip>
        </template>

        <!-- Hours columns -->
        <template #item.billable_hours="{ item }">
          <div class="text-end font-weight-medium text-success">
            {{ formatNumber(item.billable_hours || 0, 1) }}
          </div>
        </template>

        <template #item.non_billable_hours="{ item }">
          <div class="text-end font-weight-medium text-warning">
            {{ formatNumber(item.non_billable_hours || 0, 1) }}
          </div>
        </template>

        <template #item.total_hours="{ item }">
          <div class="text-end font-weight-bold">
            {{ formatNumber((item.billable_hours || 0) + (item.non_billable_hours || 0), 1) }}
          </div>
        </template>

        <template #item.target_hours="{ item }">
          <div class="text-end font-weight-medium text-info">
            {{ formatNumber(item.target_hours || 0, 1) }}
          </div>
        </template>

        <!-- Performance column -->
        <template #item.performance="{ item }">
          <div class="text-center">
            <VChip
              v-if="item.performance"
              :color="getPerformanceColor(item.performance.status)"
              :prepend-icon="getPerformanceIcon(item.performance.status)"
              size="small"
              variant="tonal"
              class="mb-1"
            >
              {{ item.performance.status }}
            </VChip>
            <div class="text-caption font-weight-medium">
              {{ formatNumber(item.performance?.percentage || 0, 1) }}%
            </div>
          </div>
        </template>

        <!-- NAD column -->
        <template #item.nad="{ item }">
          <div class="text-center">
            <VTooltip location="top">
              <template #activator="{ props }">
                <VChip 
                  v-bind="props"
                  :color="(item.nad_count || 0) > 0 ? 'error' : 'success'" 
                  size="small" 
                  variant="tonal"
                  prepend-icon="ri-calendar-close-line"
                >
                  {{ item.nad_count || 0 }}
                </VChip>
              </template>
              <span>NAD Count: {{ item.nad_count || 0 }} | Hours: {{ formatNumber(item.nad_hours || 0, 1) }}</span>
            </VTooltip>
          </div>
        </template>

        <!-- Actions column -->
        <template #item.actions="{ item }">
          <div class="text-center">
            <VTooltip text="View Dashboard" location="top">
              <template #activator="{ props }">
                <VBtn
                  v-bind="props"
                  icon="ri-dashboard-line"
                  size="small"
                  variant="text"
                  color="primary"
                  @click="handleViewDashboard(item.id)"
                />
              </template>
            </VTooltip>
          </div>
        </template>
      </VDataTable>
    </div>

    <!-- No Data State -->
    <div v-if="usersByRegion.length === 0" class="text-center py-8">
      <VIcon size="48" color="secondary" icon="ri-group-line" class="mb-4" />
      <h3 class="text-h6 font-weight-regular mb-2">No users found</h3>
      <p class="text-secondary">
        No users match the current filter criteria.
      </p>
    </div>
  </div>
</template>

<style scoped>
.v-data-table {
  border-radius: 8px;
}

.v-chip {
  font-weight: 500;
}

.v-avatar {
  font-size: 0.875rem;
}

/* Mobile responsive adjustments */
@media (max-width: 768px) {
  .v-data-table :deep(th) {
    padding: 8px 4px !important;
    font-size: 0.75rem;
  }
  
  .v-data-table :deep(td) {
    padding: 8px 4px !important;
  }
  
  .v-chip {
    font-size: 0.7rem;
    height: 20px;
  }
  
  .v-avatar {
    width: 28px !important;
    height: 28px !important;
    font-size: 0.75rem;
  }
}
</style>