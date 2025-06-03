<script setup>
// Temporary debug component - remove after fixing issues
console.log('Environment Variables:', {
  clientId: import.meta.env.VITE_AZURE_CLIENT_ID,
  tenantId: import.meta.env.VITE_AZURE_TENANT_ID,
  redirectUri: import.meta.env.VITE_AZURE_REDIRECT_URI,
  allEnv: import.meta.env
})
</script>

<template>
  <div class="pa-4">
    <h3>Debug Information</h3>
    <pre>{{ {
      clientId: $env.VITE_AZURE_CLIENT_ID,
      tenantId: $env.VITE_AZURE_TENANT_ID,
      redirectUri: $env.VITE_AZURE_REDIRECT_URI
    } }}</pre>
  </div>
</template>
