<script setup lang="ts">
defineProps<{
  title: string
  hint?: string
}>()
</script>

<template>
  <div class="rounded-xl border border-dashed border-zinc-300 px-6 py-10 text-center dark:border-zinc-700">
    <p class="font-medium text-zinc-800 dark:text-zinc-100">
      {{ title }}
    </p>
    <p v-if="hint" class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
      {{ hint }}
    </p>
  </div>
</template>
