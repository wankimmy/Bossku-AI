<script setup lang="ts">
// Force dark mode globally — no light mode.
if (import.meta.client) {
  document.documentElement.classList.add('dark')
}
</script>

<template>
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>
