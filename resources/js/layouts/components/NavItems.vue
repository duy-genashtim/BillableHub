<script setup>
import { useAuthStore } from '@/@core/stores/auth';
import VerticalNavSectionTitle from '@/@layouts/components/VerticalNavSectionTitle.vue';
import VerticalNavGroup from '@layouts/components/VerticalNavGroup.vue';
import VerticalNavLink from '@layouts/components/VerticalNavLink.vue';

const authStore = useAuthStore()

// Helper function to check if user has permission
const hasPermission = (permission) => {
  return authStore.user?.permissions?.some(p => p.name === permission) || false
}

// Helper function to check if user has any admin permissions
const hasAnyAdminPermission = () => {
  const adminPermissions = ['manage_roles', 'manage_users', 'view_activity_logs', 'manage_configuration']
  return adminPermissions.some(permission => hasPermission(permission))
}
console.log('hasAnyAdminPermission:', hasAnyAdminPermission.value);
console.log('User Permissions:', authStore.user?.permissions || []);
console.log('User:', authStore.user);
console.log('Auth Store:', authStore);
console.log(authStore.user);

</script>

<template>

  <!-- 👉 Dashboards -->
  <VerticalNavLink :item="{
    title: 'Dashboards',
    icon: 'ri-home-smile-line',
    to: '/dashboard',
  }" />
  <!-- <VerticalNavLink v-if="hasPermission('manage_configuration')" :item="{
    title: 'Daily Performance',
    icon: 'ri-line-chart-line',
    to: '/admin/reports/daily-performance',
  }" />

  <VerticalNavLink v-if="hasPermission('manage_configuration')" :item="{
    title: 'Weekly Performance',
    icon: 'ri-bar-chart-grouped-line',
    to: '/admin/reports/weekly-performance',
  }" /> -->
  <VerticalNavGroup v-if="hasPermission('view_reports')" :item="{
    title: 'Performance',
    icon: 'ri-speed-line',
  }">
    <VerticalNavLink :item="{
      title: 'NSH',
      icon: 'ri-time-line',
      to: '/admin/reports/nsh-performance',
    }" />

    <VerticalNavLink :item="{
      title: 'Daily',
      icon: 'ri-line-chart-line',
      to: '/admin/reports/daily-performance',
    }" />

    <VerticalNavLink :item="{
      title: 'Weekly',
      icon: 'ri-bar-chart-grouped-line',
      to: '/admin/reports/weekly-performance',
    }" />
  </VerticalNavGroup>
  <VerticalNavGroup v-if="hasPermission('view_reports')" :item="{
    title: 'Reports',
    icon: 'ri-bar-chart-line',
  }">
    <VerticalNavLink v-if="hasPermission('view_reports')" :item="{
      title: 'Region',
      icon: 'ri-map-pin-line',
      to: '/admin/reports/region-performance',
    }" />

    <VerticalNavLink v-if="hasPermission('view_reports')" :item="{
      title: 'Overall',
      icon: 'ri-global-line',
      to: '/admin/reports/overall-performance',
    }" />

    <VerticalNavLink v-if="hasPermission('export_reports')" :item="{
      title: 'Export',
      icon: 'ri-download-line',
      to: '/admin/reports/export',
    }" />

  </VerticalNavGroup>
  <!-- 👉 Admin Section -->
  <template v-if="hasAnyAdminPermission()">
    <VerticalNavSectionTitle :item="{
      heading: 'Administration',
    }" />

    <VerticalNavLink v-if="hasPermission('manage_roles')" :item="{
      title: 'Role Management',
      icon: 'ri-shield-user-line',
      to: '/admin/roles',
    }" />
    <VerticalNavLink v-if="hasPermission('manage_users')" :item="{
      title: 'User Roles',
      icon: 'ri-group-line',
      to: '/admin/users',
    }" />
    <VerticalNavLink v-if="hasPermission('manage_configuration')" :item="{
      title: 'System Settings',
      icon: 'ri-settings-3-line',
      to: '/admin/configuration',
    }" />
    <VerticalNavLink v-if="hasPermission('manage_ivas')" :item="{
      title: 'Region',
      icon: 'ri-map-pin-line',
      to: '/admin/regions',
    }" />
    <VerticalNavLink v-if="hasPermission('manage_ivas')" :item="{
      title: 'Cohorts',
      icon: 'ri-team-line',
      to: '/admin/cohorts',
    }" />
    <VerticalNavLink v-if="hasPermission('manage_ivas')" :item="{
      title: 'Task Categories',
      icon: 'ri-price-tag-3-line',
      to: '/admin/categories',
    }" />
    <VerticalNavLink v-if="hasPermission('manage_ivas')" :item="{
      title: 'IVA Users',
      icon: 'ri-user-line',
      to: '/admin/iva-users',
    }" />
    <VerticalNavLink v-if="hasPermission('manage_ivas')" :item="{
      title: 'IVA Managers',
      icon: 'ri-user-settings-line',
      to: '/admin/iva-managers',
    }" />
    <VerticalNavLink v-if="hasPermission('view_activity_logs')" :item="{
      title: 'Activity Logs',
      icon: 'ri-file-list-3-line',
      to: '/admin/activity-logs',
    }" />
  </template>

  <!-- 👉 Front Pages -->
  <VerticalNavGroup v-if="hasPermission('sync_timedoctor_data')" :item="{
    title: 'Sync Data',
    icon: 'ri-time-line',
  }">
    <VerticalNavLink v-if="hasPermission('sync_timedoctor_data')" :item="{
      title: 'TimeDoctor V1',
      icon: 'ri-progress-1-line',
      to: '/admin/timedoctor',
    }" />
    <VerticalNavLink v-if="hasPermission('sync_timedoctor_data')" :item="{
      title: 'TimeDoctor V2',
      icon: 'ri-progress-2-line',
      to: '/admin/timedoctor-v2',
    }" />
  </VerticalNavGroup>


  <!-- <VerticalNavLink :item="{
    title: 'Account Settings',
    icon: 'ri-user-settings-line',
    to: '/account-settings',
  }" /> -->


</template>
