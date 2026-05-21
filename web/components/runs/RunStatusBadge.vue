<script setup lang="ts">
const props = defineProps<{ status: string }>()

const cls = computed(() => {
  switch (props.status) {
    case 'running': return 'bg-blue-900/50 text-blue-300 border-blue-800'
    case 'completed': return 'bg-emerald-900/50 text-emerald-300 border-emerald-800'
    case 'failed': return 'bg-red-900/50 text-red-300 border-red-800'
    case 'paused': return 'bg-yellow-900/50 text-yellow-300 border-yellow-800'
    default: return 'bg-zinc-800 text-zinc-400 border-zinc-700'
  }
})
</script>

<template>
  <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border" :class="cls">
    {{ status }}
  </span>
</template>
