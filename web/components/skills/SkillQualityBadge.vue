<script setup lang="ts">
const props = defineProps<{ score?: number }>()

const cls = computed(() => {
  if (props.score == null) return 'bg-zinc-800 text-zinc-500 border-zinc-700'
  if (props.score > 70) return 'bg-emerald-900/50 text-emerald-300 border-emerald-800'
  if (props.score > 40) return 'bg-yellow-900/50 text-yellow-300 border-yellow-800'
  return 'bg-red-900/50 text-red-300 border-red-800'
})
</script>

<template>
  <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border" :class="cls">
    {{ score != null ? score.toFixed(0) + '%' : 'N/A' }}
  </span>
</template>
