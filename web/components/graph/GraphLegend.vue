<script setup lang="ts">
const items = [
  { label: 'Skill', color: 'bg-emerald-500' },
  { label: 'Run', color: 'bg-blue-500' },
  { label: 'Memory', color: 'bg-purple-600' },
  { label: 'Conflict', color: 'bg-red-600' },
]
</script>

<template>
  <div class="flex flex-wrap items-center gap-3">
    <div v-for="item in items" :key="item.label" class="flex items-center gap-1.5">
      <span :class="item.color" class="h-3 w-3 rounded-sm flex-shrink-0" />
      <span class="text-xs text-zinc-400">{{ item.label }}</span>
    </div>
  </div>
</template>
