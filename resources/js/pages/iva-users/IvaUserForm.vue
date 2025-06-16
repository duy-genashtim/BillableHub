<script setup>
import { formatDateForInput } from '@/@core/utils/helpers';
import { computed } from 'vue';

const props = defineProps({
  user: {
    type: Object,
    required: true
  },
  regions: {
    type: Array,
    default: () => []
  },
  cohorts: {
    type: Array,
    default: () => []
  },
  workStatusOptions: {
    type: Array,
    default: () => []
  },
  timedoctorVersions: {
    type: Array,
    default: () => []
  },
  isEditing: {
    type: Boolean,
    default: false
  },
  errors: {
    type: Object,
    default: () => ({})
  },
  regionChangeInfo: {
    type: Object,
    default: () => ({})
  },
  cohortChangeInfo: {
    type: Object,
    default: () => ({})
  },
  workStatusChangeInfo: {
    type: Object,
    default: () => ({})
  }
});

const emit = defineEmits(['update:regionChangeInfo', 'update:cohortChangeInfo', 'update:workStatusChangeInfo']);

// Check if region is changing
const isRegionChanging = computed(() => {
  return props.isEditing && props.user.region_id !== props.user.original_region_id;
});

// Check if cohort is changing
const isCohortChanging = computed(() => {
  return props.isEditing && props.user.cohort_id !== props.user.original_cohort_id;
});

// Check if work status is changing
const isWorkStatusChanging = computed(() => {
  return props.isEditing && props.user.work_status !== props.user.original_work_status;
});

// Format dates for input fields
const formattedHireDate = computed({
  get: () => formatDateForInput(props.user.hire_date),
  set: (value) => {
    props.user.hire_date = value;
  }
});

const formattedEndDate = computed({
  get: () => formatDateForInput(props.user.end_date),
  set: (value) => {
    props.user.end_date = value;
  }
});

// Get TimeDoctor version display label
const timedoctorVersionDisplay = computed(() => {
  if (!props.isEditing && props.user.timedoctor_version) {
    const version = props.timedoctorVersions.find(v =>
      String(v.value) === String(props.user.timedoctor_version)
    );
    return version ? version.label : props.user.timedoctor_version;
  }
  return props.user.timedoctor_version;
});

function updateRegionChangeInfo(field, value) {
  const updated = { ...props.regionChangeInfo, [field]: value };
  emit('update:regionChangeInfo', updated);
}

function updateCohortChangeInfo(field, value) {
  const updated = { ...props.cohortChangeInfo, [field]: value };
  emit('update:cohortChangeInfo', updated);
}

function updateWorkStatusChangeInfo(field, value) {
  const updated = { ...props.workStatusChangeInfo, [field]: value };
  emit('update:workStatusChangeInfo', updated);
}
</script>

<template>
  <VRow>
    <VCol cols="12" md="6">
      <VTextField v-model="user.full_name" density="comfortable" label="Full Name" :readonly="!isEditing"
        variant="outlined" :error-messages="errors.full_name" required aria-label="User's full name" />
    </VCol>

    <VCol cols="12" md="6">
      <VTextField v-model="user.email" density="comfortable" label="Email" :readonly="!isEditing" variant="outlined"
        :error-messages="errors.email" required aria-label="User's email address" />
    </VCol>

    <VCol cols="12" md="6">
      <VSelect v-model="user.region_id" :items="regions" item-title="name" item-value="id" label="Region"
        density="comfortable" :readonly="!isEditing" variant="outlined" :error-messages="errors.region_id"
        :clearable="isEditing" aria-label="User's region" />

      <!-- Region Change Info -->
      <div v-if="isEditing && isRegionChanging" class="mt-3">
        <VAlert type="info" variant="tonal" density="compact" class="mb-3">
          Region change detected. Please provide details below.
        </VAlert>

        <VRow>
          <VCol cols="12" md="6">
            <VTextField :model-value="regionChangeInfo.reason"
              @update:model-value="updateRegionChangeInfo('reason', $event)" label="Reason for Region Change"
              density="comfortable" variant="outlined" :error-messages="errors['region_change_info.reason']" required
              aria-label="Reason for region change" />
          </VCol>
          <VCol cols="12" md="6">
            <VTextField :model-value="regionChangeInfo.effectiveDate"
              @update:model-value="updateRegionChangeInfo('effectiveDate', $event)" label="Effective Date" type="date"
              density="comfortable" variant="outlined" :error-messages="errors['region_change_info.effectiveDate']"
              required aria-label="Effective date for region change" />
          </VCol>
        </VRow>
      </div>
    </VCol>

    <VCol cols="12" md="6">
      <VSelect v-model="user.cohort_id" :items="cohorts" item-title="name" item-value="id" label="Cohort"
        density="comfortable" :readonly="!isEditing" variant="outlined" :error-messages="errors.cohort_id"
        :clearable="isEditing" aria-label="User's cohort" />

      <!-- Cohort Change Info -->
      <div v-if="isEditing && isCohortChanging" class="mt-3">
        <VAlert type="info" variant="tonal" density="compact" class="mb-3">
          Cohort change detected. Please provide details below.
        </VAlert>

        <VRow>
          <VCol cols="12" md="6">
            <VTextField :model-value="cohortChangeInfo.reason"
              @update:model-value="updateCohortChangeInfo('reason', $event)" label="Reason for Cohort Change"
              density="comfortable" variant="outlined" :error-messages="errors['cohort_change_info.reason']" required
              aria-label="Reason for cohort change" />
          </VCol>
          <VCol cols="12" md="6">
            <VTextField :model-value="cohortChangeInfo.effectiveDate"
              @update:model-value="updateCohortChangeInfo('effectiveDate', $event)" label="Effective Date" type="date"
              density="comfortable" variant="outlined" :error-messages="errors['cohort_change_info.effectiveDate']"
              required aria-label="Effective date for cohort change" />
          </VCol>
        </VRow>
      </div>
    </VCol>

    <VCol cols="12" md="6">
      <VSelect v-model="user.work_status" :items="workStatusOptions" item-title="label" item-value="value"
        label="Work Status" density="comfortable" :readonly="!isEditing" variant="outlined"
        :error-messages="errors.work_status" :clearable="isEditing" aria-label="User's work status" />

      <!-- Work Status Change Info -->
      <div v-if="isEditing && isWorkStatusChanging" class="mt-3">
        <VAlert type="info" variant="tonal" density="compact" class="mb-3">
          Work status change detected. Please provide details below.
        </VAlert>

        <VRow>
          <VCol cols="12" md="6">
            <VTextField :model-value="workStatusChangeInfo.reason"
              @update:model-value="updateWorkStatusChangeInfo('reason', $event)" label="Reason for Work Status Change"
              density="comfortable" variant="outlined" :error-messages="errors['work_status_change_info.reason']"
              required aria-label="Reason for work status change" />
          </VCol>
          <VCol cols="12" md="6">
            <VTextField :model-value="workStatusChangeInfo.effectiveDate"
              @update:model-value="updateWorkStatusChangeInfo('effectiveDate', $event)" label="Effective Date"
              type="date" density="comfortable" variant="outlined"
              :error-messages="errors['work_status_change_info.effectiveDate']" required
              aria-label="Effective date for work status change" />
          </VCol>
        </VRow>
      </div>
    </VCol>

    <VCol cols="12" md="6">
      <VTextField v-model="formattedHireDate" density="comfortable" label="Hire Date" :readonly="!isEditing"
        variant="outlined" type="date" :error-messages="errors.hire_date" aria-label="User's hire date" />
    </VCol>

    <VCol cols="12" md="6">
      <VTextField v-model="formattedEndDate" density="comfortable" label="End Date" :readonly="!isEditing"
        variant="outlined" type="date" :error-messages="errors.end_date" aria-label="User's end date" />
    </VCol>

    <VCol cols="12" md="6">
      <!-- Show as text field when not editing to display the label -->
      <VTextField v-if="!isEditing" :model-value="timedoctorVersionDisplay" density="comfortable"
        label="TimeDoctor Version" readonly variant="outlined" aria-label="User's TimeDoctor version" />

      <!-- Show as select when editing -->
      <VSelect v-else v-model="user.timedoctor_version" :items="timedoctorVersions" item-title="label"
        item-value="value" label="TimeDoctor Version" density="comfortable" variant="outlined"
        :error-messages="errors.timedoctor_version" required aria-label="User's TimeDoctor version" />
    </VCol>

    <VCol v-if="isEditing" cols="12" md="6">
      <VCheckbox v-model="user.is_active" label="Active User" :error-messages="errors.is_active"
        aria-label="Set user as active" hint="Active users will be displayed in the region and overall report."
        persistent-hint />
    </VCol>
  </VRow>
</template>
