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
    <VerticalNavLink v-if="hasPermission('manage_configuration')" :item="{
      title: 'Region',
      icon: 'ri-map-pin-line',
      to: '/admin/regions',
    }" />
    <VerticalNavLink v-if="hasPermission('manage_configuration')" :item="{
      title: 'Task Categories',
      icon: 'ri-price-tag-3-line',
      to: '/admin/categories',
    }" />
    <VerticalNavLink v-if="hasPermission('manage_configuration')" :item="{
      title: 'TimeDoctor Integration',
      icon: 'ri-time-line',
      to: '/admin/timedoctor',
    }" />
    <VerticalNavLink v-if="hasPermission('view_activity_logs')" :item="{
      title: 'Activity Logs',
      icon: 'ri-file-list-3-line',
      to: '/admin/activity-logs',
    }" />
  </template>

  <!-- 👉 Front Pages -->
  <VerticalNavGroup :item="{
    title: 'Front Pages',
    icon: 'ri-file-copy-line',
    badgeContent: 'Pro',
    badgeClass: 'bg-light-primary text-primary',
  }">
    <VerticalNavLink :item="{
      title: 'Landing',
      href: 'https://demos.themeselection.com/materio-vuetify-vuejs-laravel-admin-template/demo-1/front-pages/landing-page',
      target: '_blank',
    }" />
    <VerticalNavLink :item="{
      title: 'Pricing',
      href: 'https://demos.themeselection.com/materio-vuetify-vuejs-laravel-admin-template/demo-1/front-pages/pricing',
      target: '_blank',
    }" />
    <VerticalNavLink :item="{
      title: 'Payment',
      href: 'https://demos.themeselection.com/materio-vuetify-vuejs-laravel-admin-template/demo-1/front-pages/payment',
      target: '_blank',
    }" />
    <VerticalNavLink :item="{
      title: 'Checkout',
      href: 'https://demos.themeselection.com/materio-vuetify-vuejs-laravel-admin-template/demo-1/front-pages/checkout',
      target: '_blank',
    }" />
    <VerticalNavLink :item="{
      title: 'Help Center',
      href: 'https://demos.themeselection.com/materio-vuetify-vuejs-laravel-admin-template/demo-1/front-pages/help-center',
      target: '_blank',
    }" />
  </VerticalNavGroup>

  <!-- 👉 Apps & Pages -->
  <VerticalNavSectionTitle :item="{
    heading: 'Apps & Pages',
  }" />

  <VerticalNavLink :item="{
    title: 'Account Settings',
    icon: 'ri-user-settings-line',
    to: '/account-settings',
  }" />

  <VerticalNavLink :item="{
    title: 'Login',
    icon: 'ri-login-box-line',
    to: '/login',
  }" />
  <VerticalNavLink :item="{
    title: 'Register',
    icon: 'ri-user-add-line',
    to: '/register',
  }" />
  <VerticalNavLink :item="{
    title: 'Error',
    icon: 'ri-information-line',
    to: '/no-existence',
  }" />

  <!-- 👉 User Interface -->
  <VerticalNavSectionTitle :item="{
    heading: 'User Interface',
  }" />
  <VerticalNavLink :item="{
    title: 'Typography',
    icon: 'ri-text',
    to: '/typography',
  }" />
  <VerticalNavLink :item="{
    title: 'Icons',
    icon: 'ri-remixicon-line',
    to: '/icons',
  }" />
  <VerticalNavLink :item="{
    title: 'Cards',
    icon: 'ri-bar-chart-box-line',
    to: '/cards',
  }" />

  <!-- 👉 Forms & Tables -->
  <VerticalNavSectionTitle :item="{
    heading: 'Forms & Tables',
  }" />
  <VerticalNavLink :item="{
    title: 'Form Layouts',
    icon: 'ri-layout-4-line',
    to: '/form-layouts',
  }" />

  <VerticalNavLink :item="{
    title: 'Tables',
    icon: 'ri-table-alt-line',
    to: '/tables',
  }" />


</template>
<!-- <style lang="scss">
.layout-vertical-nav {
  .nav-item-title {
    overflow: visible !important;
    line-height: 1.4;
    padding-block: 0.375rem;
    text-overflow: unset !important;
    white-space: normal !important;
  }

  .nav-group {
    margin-block-end: 0.5rem;
  }

  .nav-group > :first-child {
    margin-block-end: 0.375rem;
  }

  &:not(.layout-vertical-nav-collapsed) {
    .nav-item-title {
      padding-block: 0.375rem;
      white-space: normal !important;
    }
  }
}
</style> -->
