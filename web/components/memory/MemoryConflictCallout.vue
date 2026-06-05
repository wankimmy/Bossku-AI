<script setup lang="ts">
const props = defineProps<{
  conflictingIds: string[]
}>()
</script>

<template>
  <div v-if="conflictingIds.length > 0" class="rounded-xl border border-yellow-700/50 bg-yellow-950/30 px-4 py-3 space-y-2">
    <div class="flex items-center gap-2">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
      </svg>
      <p class="text-sm font-medium text-yellow-300">
        {{ conflictingIds.length }} conflicting {{ conflictingIds.length === 1 ? 'memory' : 'memories' }} detected.
      </p>
    </div>
    <ul class="space-y-0.5 pl-6">
      <li v-for="id in conflictingIds" :key="id" class="font-mono text-xs text-zinc-400">{{ id }}</li>
    </ul>
  </div>
</template>
