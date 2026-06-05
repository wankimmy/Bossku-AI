<script setup lang="ts">
defineProps<{
  class?: string
}>()
</script>

<template>
  <div
    class="animate-pulse rounded bg-zinc-200 dark:bg-zinc-800"
    :class="class"
    role="presentation"
    aria-hidden="true"
  />
</template>
