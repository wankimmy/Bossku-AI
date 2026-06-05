<script setup lang="ts">
defineProps<{ title: string; hint?: string }>()
</script>

<template>
  <div class="rounded-lg border border-dashed border-zinc-300 bg-zinc-50 p-4 text-sm dark:border-zinc-700 dark:bg-zinc-950">
    <p class="font-medium text-zinc-800 dark:text-zinc-100">
      {{ title }}
    </p>
    <p v-if="hint" class="mt-1 text-zinc-500 dark:text-zinc-400">
      {{ hint }}
    </p>
  </div>
</template>
