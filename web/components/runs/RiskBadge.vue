<script setup lang="ts">
const props = defineProps<{ riskLevel?: string }>()

const cls = computed(() => {
  switch (props.riskLevel) {
    case 'low': return 'bg-emerald-900/50 text-emerald-300 border-emerald-800'
    case 'medium': return 'bg-yellow-900/50 text-yellow-300 border-yellow-800'
    case 'high': return 'bg-orange-900/50 text-orange-300 border-orange-800'
    case 'critical': return 'bg-red-900/50 text-red-300 border-red-800'
    default: return 'bg-zinc-800 text-zinc-400 border-zinc-700'
  }
})
</script>

<template>
  <span v-if="riskLevel" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border" :class="cls">
    {{ riskLevel }}
  </span>
  <span v-else class="text-zinc-600 text-xs">—</span>
</template>
