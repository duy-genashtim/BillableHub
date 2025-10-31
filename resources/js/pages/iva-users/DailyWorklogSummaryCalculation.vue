<template>
  <div>
    <!-- Region Filter Notice -->
    <VAlert v-if="regionFilter.applied" type="info" variant="tonal" class="mb-6">
      <VAlertTitle class="d-flex align-center">
        <VIcon icon="ri-information-line" class="me-2" />
        Filtered View
      </VAlertTitle>
      <p class="mb-0">
        You can only calculate summaries for IVA users from your assigned region, based on your permissions.
      </p>
    </VAlert>

    <VCard>
      <!-- Header -->
      <VCardTitle class="pb-4">
        <div class="d-flex align-center justify-space-between">
          <div>
            <h2 class="text-h4 mb-1">
              Daily Worklog Summary Calculation
            </h2>
            <p class="text-body-1 mb-0">
              Calculate daily worklog summaries based on task categories
            </p>
          </div>
          <VBtn variant="text" icon="mdi-close" @click="$router.push('/admin/iva-users')" />
        </div>
      </VCardTitle>

      <VDivider />

      <!-- Form Section -->
      <VCardText v-if="!calculating && !showResults">
        <VForm ref="formRef" v-model="formValid" @submit.prevent="startCalculation">
          <VRow>
            <!-- Date Range Section -->
            <VCol cols="12">
              <VCard variant="outlined">
                <VCardTitle class="text-h6">Date Range</VCardTitle>
                <VCardText>
                  <VRow>
                    <VCol cols="12" md="4">
                      <VCheckbox v-model="calculateAll" label="Calculate All (From beginning to now)" color="primary"
                        @change="onCalculateAllChange" />
                    </VCol>
                    <VCol cols="12" md="4">
                      <VTextField v-model="startDate" :disabled="calculateAll" label="Start Date" type="date"
                        :rules="calculateAll ? [] : dateRules" :required="!calculateAll" />
                    </VCol>
                    <VCol cols="12" md="4">
                      <VTextField v-model="endDate" :disabled="calculateAll" label="End Date" type="date"
                        :rules="calculateAll ? [] : endDateRules" :required="!calculateAll" />
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>
            </VCol>

            <!-- IVA Selection Section -->
            <VCol cols="12">
              <VCard variant="outlined">
                <VCardTitle class="text-h6">IVA Users Selection</VCardTitle>
                <VCardText>
                  <VRow>
                    <VCol cols="12" md="4">
                      <VCheckbox v-model="calculateAllUsers" label="Calculate All IVA Users" color="primary"
                        @change="onCalculateAllUsersChange" />
                    </VCol>
                    <VCol cols="12" md="8">
                      <!-- VAutocomplete with return-object to fix duplicate ID warnings -->
                      <!-- Alternative fix: add 'no-virtual-scroll' prop if return-object doesn't work -->
                      <VAutocomplete v-model="selectedUserIds" :disabled="calculateAllUsers" :items="ivaUsers"
                        item-title="full_name" item-value="id" label="Select IVA Users" multiple chips closable-chips
                        :rules="userSelectionRules" clearable return-object>
                        <template #chip="{ props, item }">
                          <VChip v-bind="props" :text="item.raw.full_name" size="small" />
                        </template>
                        <template #item="{ props, item }">
                          <VListItem v-bind="props" :title="item.raw.full_name"
                            :subtitle="`${item.raw.region_name} • ${item.raw.email}`" :value="item.raw.id" />
                        </template>
                      </VAutocomplete>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Summary Section -->
            <VCol cols="12">
              <VCard variant="outlined" color="info">
                <VCardText>
                  <div class="d-flex align-center">
                    <VIcon icon="mdi-information" class="me-2" />
                    <div>
                      <p class="mb-1 font-weight-medium">Calculation Summary:</p>
                      <p class="mb-0 text-body-2">
                        {{ calculationSummary }}
                      </p>
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Action Buttons -->
            <VCol cols="12">
              <div class="d-flex gap-3">
                <VBtn type="submit" color="primary" :loading="validating" :disabled="!formValid">
                  <i class="ri-calculator-line me-2"></i>
                  Start Calculation
                </VBtn>
                <VBtn variant="outlined" @click="resetForm">
                  Reset
                </VBtn>
                <VBtn variant="text" @click="$router.push('/admin/iva-users')">
                  Cancel
                </VBtn>
              </div>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <!-- Progress Section -->
      <VCardText v-if="calculating">
        <div class="text-center">
          <VProgressCircular :model-value="calculationProgress" size="120" width="8" color="primary" class="mb-4">
            <span class="text-h6">{{ calculationProgress }}%</span>
          </VProgressCircular>

          <h3 class="text-h5 mb-2">Calculation in Progress</h3>
          <p class="text-body-1 mb-2">{{ progressMessage }}</p>
          <p class="text-body-2 mb-4 text-medium-emphasis">
            <strong>Date Range:</strong> {{ calculationDateRange }}
          </p>

          <!-- Current Progress Details -->
          <VCard variant="outlined" class="mt-4">
            <VCardText>
              <VList>
                <VListItem v-for="(user, index) in processedUsers" :key="user.iva_id"
                  :class="{ 'text-success': user.completed, 'text-error': user.error }">
                  <template #prepend>
                    <VIcon :icon="user.completed ? 'mdi-check-circle' : user.error ? 'mdi-alert-circle' : 'mdi-clock'"
                      :color="user.completed ? 'success' : user.error ? 'error' : 'primary'" />
                  </template>
                  <VListItemTitle>{{ user.iva_name }}</VListItemTitle>
                  <VListItemSubtitle>
                    {{ user.completed ? 'Completed' : user.error ? 'Error occurred' : 'Processing...' }}
                    ({{ user.success_count }}/{{ user.total_dates }} dates)
                  </VListItemSubtitle>
                </VListItem>
              </VList>
            </VCardText>
          </VCard>
        </div>
      </VCardText>

      <!-- Results Section -->
      <VCardText v-if="showResults">
        <div class="text-center mb-6">
          <VIcon :icon="calculationResult.success ? 'mdi-check-circle' : 'mdi-alert-circle'"
            :color="calculationResult.success ? 'success' : 'error'" size="64" class="mb-4" />
          <h3 class="text-h5 mb-2">
            {{ calculationResult.success ? 'Calculation Completed' : 'Calculation Failed' }}
          </h3>
          <p class="text-body-1 mb-2">{{ calculationResult.message }}</p>
          <p class="text-body-2 text-medium-emphasis">
            <strong>Date Range:</strong> {{ calculationDateRange }}
          </p>
        </div>

        <!-- Summary Stats -->
        <VRow v-if="calculationResult.success" class="mb-4">
          <VCol cols="6" md="3">
            <VCard variant="outlined">
              <VCardText class="text-center">
                <div class="text-h4 text-primary">{{ calculationResult.summary.total_ivas }}</div>
                <div class="text-body-2">IVA Users</div>
              </VCardText>
            </VCard>
          </VCol>
          <VCol cols="6" md="3">
            <VCard variant="outlined">
              <VCardText class="text-center">
                <div class="text-h4 text-info">{{ calculationResult.summary.total_dates }}</div>
                <div class="text-body-2">Total Dates</div>
              </VCardText>
            </VCard>
          </VCol>
          <VCol cols="6" md="3">
            <VCard variant="outlined">
              <VCardText class="text-center">
                <div class="text-h4 text-success">{{ calculationResult.summary.total_processed }}</div>
                <div class="text-body-2">Processed</div>
              </VCardText>
            </VCard>
          </VCol>
          <VCol cols="6" md="3">
            <VCard variant="outlined">
              <VCardText class="text-center">
                <div class="text-h4 text-error">{{ calculationResult.summary.total_errors }}</div>
                <div class="text-body-2">Errors</div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>

        <!-- Detailed Results -->
        <VCard variant="outlined">
          <VCardTitle>Detailed Results</VCardTitle>
          <VCardText>
            <VDataTable :headers="resultHeaders" :items="calculationResult.details || []" item-value="iva_id"
              class="elevation-1">
              <template #item.status="{ item }">
                <VChip :color="item.error_count > 0 ? 'warning' : 'success'" size="small">
                  {{ item.error_count > 0 ? 'With Errors' : 'Success' }}
                </VChip>
              </template>
              <template #item.progress="{ item }">
                <div class="d-flex align-center">
                  <span class="me-2">{{ item.success_count }}/{{ item.total_dates }}</span>
                  <VProgressLinear :model-value="(item.success_count / item.total_dates) * 100" height="6"
                    color="primary" class="flex-grow-1" />
                </div>
              </template>
            </VDataTable>
          </VCardText>
        </VCard>

        <!-- Action Buttons -->
        <div class="d-flex gap-3 mt-4">
          <VBtn color="primary" @click="resetCalculation">
            Calculate Again
          </VBtn>
          <VBtn variant="outlined" @click="$router.push('/admin/iva-users')">
            Back to IVA Users
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <!-- Snackbar for notifications -->
    <VSnackbar v-model="snackbar" :color="snackbarColor" timeout="3000">
      {{ snackbarText }}
    </VSnackbar>
  </div>
</template>

<script setup>
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

// Form data
const formRef = ref()
const formValid = ref(false)
const calculateAll = ref(false)
const calculateAllUsers = ref(true)
const startDate = ref('')
const endDate = ref('')
const selectedUserIds = ref([])

// Data
const ivaUsers = ref([])
const loading = ref(true)
const regionFilter = ref({ applied: false, region_id: null, reason: null })

// Calculation state
const calculating = ref(false)
const showResults = ref(false)
const calculationProgress = ref(0)
const progressMessage = ref('')
const processedUsers = ref([])
const calculationResult = ref(null)
const validating = ref(false)

// UI state
const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref('success')

// Form validation rules
const dateRules = [
  v => !!v || 'Date is required',
  v => {
    const date = new Date(v)
    return !isNaN(date.getTime()) || 'Invalid date format'
  }
]

const endDateRules = [
  v => !!v || 'End date is required',
  v => {
    const startDateValue = new Date(startDate.value)
    const endDateValue = new Date(v)
    return endDateValue >= startDateValue || 'End date must be after start date'
  }
]

const userSelectionRules = [
  v => {
    if (calculateAllUsers.value) return true
    return (v && v.length > 0) || 'Please select at least one IVA user'
  }
]

// Computed
const calculationSummary = computed(() => {
  const userCount = calculateAllUsers.value ? ivaUsers.value.length : selectedUserIds.value.length
  const dateRange = calculateAll.value ? 'from beginning to now' : `from ${startDate.value} to ${endDate.value}`
  return `Will calculate summaries for ${userCount} IVA user(s) ${dateRange}`
})

const calculationDateRange = computed(() => {
  if (calculateAll.value) {
    return 'From beginning to now (all available data)'
  }
  return `${startDate.value} to ${endDate.value}`
})

const resultHeaders = [
  { title: 'IVA Name', key: 'iva_name', sortable: true },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Progress', key: 'progress', sortable: false },
  { title: 'Success Count', key: 'success_count', sortable: true },
  { title: 'Error Count', key: 'error_count', sortable: true },
]

// Methods
const loadCalculationOptions = async () => {
  try {
    loading.value = true
    const response = await axios.get('/api/admin/daily-worklog-summaries/calculation-options')

    if (response.data.success) {
      ivaUsers.value = response.data.data.iva_users

      // Handle region filter from backend
      if (response.data.region_filter) {
        regionFilter.value = response.data.region_filter
      }
    } else {
      showSnackbar('Failed to load calculation options', 'error')
    }
  } catch (error) {
    console.error('Error loading options:', error)
    showSnackbar('Failed to load calculation options', 'error')
  } finally {
    loading.value = false
  }
}

const validateCalculation = async () => {
  try {
    validating.value = true
    const params = getCalculationParams()

    const response = await axios.post('/api/admin/daily-worklog-summaries/validate-calculation', params)

    return response.data.success
  } catch (error) {
    if (error.response?.data?.errors) {
      const errors = Object.values(error.response.data.errors).flat()
      showSnackbar(errors.join(', '), 'error')
    } else {
      showSnackbar('Validation failed', 'error')
    }
    return false
  } finally {
    validating.value = false
  }
}

const startCalculation = async () => {
  if (!formRef.value.validate()) return

  const isValid = await validateCalculation()
  if (!isValid) return

  try {
    calculating.value = true
    calculationProgress.value = 0
    progressMessage.value = 'Starting calculation...'
    processedUsers.value = []

    const params = getCalculationParams()
    const response = await axios.post('/api/admin/daily-worklog-summaries/start-calculation', params)

    calculationResult.value = response.data
    showResults.value = true

    if (response.data.success) {
      showSnackbar('Calculation completed successfully', 'success')
      processedUsers.value = response.data.details || []
      calculationProgress.value = 100
      progressMessage.value = 'Calculation completed'
    } else {
      showSnackbar(response.data.message, 'error')
    }
  } catch (error) {
    console.error('Calculation error:', error)

    // Extract detailed error information
    const errorMessage = error.response?.data?.message || error.message
    const debugInfo = error.response?.data?.debug_info

    let displayMessage = `Calculation failed: ${errorMessage}`
    if (debugInfo) {
      displayMessage += `\n\nDebug Info: ${debugInfo.file}:${debugInfo.line}`
    }

    showSnackbar(displayMessage, 'error')

    calculationResult.value = {
      success: false,
      message: errorMessage,
      summary: { total_ivas: 0, total_dates: 0, total_processed: 0, total_errors: 0 },
      details: [],
      debug_info: debugInfo
    }
    showResults.value = true
  } finally {
    calculating.value = false
  }
}

const getCalculationParams = () => {
  // Extract IDs from selected user objects (due to return-object prop)
  const userIds = calculateAllUsers.value ? [] : selectedUserIds.value.map(user => user.id || user)

  const params = {
    start_date: calculateAll.value ? null : startDate.value,
    end_date: calculateAll.value ? null : endDate.value,
    calculate_all: calculateAll.value,
    iva_user_ids: userIds
  }

  // Log parameters for debugging
  console.log('Calculation parameters:', {
    calculateAll: calculateAll.value,
    calculateAllUsers: calculateAllUsers.value,
    selectedUserIds: selectedUserIds.value,
    extractedUserIds: userIds,
    finalParams: params
  })

  return params
}

const resetForm = () => {
  calculateAll.value = false
  calculateAllUsers.value = true
  startDate.value = ''
  endDate.value = ''
  selectedUserIds.value = []
  formRef.value?.resetValidation()
}

const resetCalculation = () => {
  calculating.value = false
  showResults.value = false
  calculationProgress.value = 0
  progressMessage.value = ''
  processedUsers.value = []
  calculationResult.value = null
  resetForm()
}

const onCalculateAllChange = () => {
  if (calculateAll.value) {
    startDate.value = ''
    endDate.value = ''
  } else {
    // Set default date range to last 30 days
    const today = new Date()
    const thirtyDaysAgo = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000)

    endDate.value = today.toISOString().split('T')[0]
    startDate.value = thirtyDaysAgo.toISOString().split('T')[0]
  }
}

const onCalculateAllUsersChange = () => {
  if (calculateAllUsers.value) {
    selectedUserIds.value = []
  }
}

const showSnackbar = (message, color = 'success') => {
  snackbarText.value = message
  snackbarColor.value = color
  snackbar.value = true
}

// Lifecycle
onMounted(() => {
  loadCalculationOptions()

  // Set default date range
  const today = new Date()
  const thirtyDaysAgo = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000)

  endDate.value = today.toISOString().split('T')[0]
  startDate.value = thirtyDaysAgo.toISOString().split('T')[0]
})
</script>

<style scoped>
.calculation-form {
  margin-block: 0;
  margin-inline: auto;
  max-inline-size: 800px;
}
</style>
