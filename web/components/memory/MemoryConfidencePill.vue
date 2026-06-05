<script setup lang="ts">
const props = defineProps<{
  confidence: number
}>()

const colorClass = computed(() => {
  if (props.confidence >= 0.8) return 'bg-emerald-900/50 text-emerald-300 border-emerald-700/50'
  if (props.confidence >= 0.5) return 'bg-yellow-900/50 text-yellow-300 border-yellow-700/50'
  return 'bg-red-900/50 text-red-300 border-red-700/50'
})

const label = computed(() => `Confidence: ${Math.round(props.confidence * 100)}%`)
</script>

<template>
  <span :class="colorClass" class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium">
    {{ label }}
  </span>
</template>
