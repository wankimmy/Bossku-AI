<script setup lang="ts">
import { categoryLegendItems, depthLegendItems, type GraphColorMode } from '~/composables/useGraphColors'

defineProps<{
  colorMode: GraphColorMode
}>()
</script>

<template>
  <div class="pointer-events-auto rounded-lg border border-zinc-700/80 bg-zinc-900/95 px-3 py-2 text-[11px] shadow-xl backdrop-blur-sm max-h-[40vh] overflow-y-auto">
    <div class="font-semibold text-zinc-300 mb-1.5">
      Legend
    </div>
    <div
      v-for="[label, color] in (colorMode === 'depth' ? depthLegendItems : categoryLegendItems)"
      :key="label"
      class="flex items-center gap-2 py-0.5 text-zinc-400"
    >
      <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ background: color }" />
      <span>{{ label }}</span>
    </div>
  </div>
</template>
