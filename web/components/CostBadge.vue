<script setup lang="ts">
defineProps<{ tokens?: number }>()
</script>

<template>
  <span class="rounded border border-zinc-300 px-2 py-0.5 font-mono text-xs text-zinc-700 dark:border-zinc-700 dark:text-zinc-300">
    ~{{ tokens ?? 0 }} tok
  </span>
</template>
