<script setup lang="ts">
defineProps<{
  label: string
  value: string | number
  sub?: string
}>()
</script>

<template>
  <div class="rounded-lg bg-zinc-900 border border-zinc-800 p-4">
    <div class="text-xs text-zinc-500 uppercase tracking-wide mb-1">{{ label }}</div>
    <div class="text-2xl font-bold text-emerald-400">{{ value }}</div>
    <div v-if="sub" class="text-xs text-zinc-500 mt-1">{{ sub }}</div>
  </div>
</template>
