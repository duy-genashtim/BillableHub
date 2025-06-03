<template>
  <div class="h-100 d-flex align-center justify-md-space-between justify-center">
    <!-- 👉 Footer: left content -->
    <span class="d-flex align-center">
      &copy;
      {{ new Date().getFullYear() }}
      By <a
        href="https://genashtim.com"
        target="_blank"
        rel="noopener noreferrer"
        class="text-primary ms-1"
      >genashtim</a>
    </span>
    <!-- 👉 Footer: right content -->
    <span class="d-md-flex gap-x-4 text-primary d-none">
      Version
      <span class="text-body font-weight-bold">
       {{ appVersion }}
       </span>
    </span>
  </div>
</template>
<script setup >
import { appVersion } from '@/@core/config/siteConfigs.js';
</script>
