<script setup lang="ts">
withDefaults(defineProps<{
  variant?: 'default' | 'brain'
}>(), {
  variant: 'default',
})

const defaultItems = [
  { label: 'Skill', color: 'bg-emerald-500' },
  { label: 'Run', color: 'bg-blue-500' },
  { label: 'Memory', color: 'bg-purple-600' },
  { label: 'Agent', color: 'bg-cyan-500' },
  { label: 'Rule', color: 'bg-amber-500' },
  { label: 'Conflict', color: 'bg-red-600' },
]

const brainItems = [
  { label: 'Core', color: 'bg-pink-500' },
  { label: 'Memory', color: 'bg-purple-500' },
  { label: 'Type hub', color: 'bg-indigo-500' },
]
</script>

<template>
  <div class="flex flex-wrap items-center gap-3">
    <div
      v-for="item in (variant === 'brain' ? brainItems : defaultItems)"
      :key="item.label"
      class="flex items-center gap-1.5"
    >
      <span :class="item.color" class="h-3 w-3 shrink-0 rounded-sm" />
      <span class="text-xs text-zinc-400">{{ item.label }}</span>
    </div>
  </div>
</template>
